<?php

function menu()
{
	add_menu_page(
		'Цени и Каси',
		'Цени и Каси',
		'manage_options',
		'frames-list-page',
		'frames_list_page',
		'dashicons-money-alt',
		1.1
	);

	// Settings submenu
	add_submenu_page(
		'frames-list-page',
		'Настройки',
		'Настройки',
		'manage_options',
		'doors-frames-settings',
		'doors_frames_settings_page'
	);
}
add_action('admin_menu', 'menu');

/**
 * Settings page for Doors Frames
 */
function doors_frames_settings_page()
{
	global $wpdb;

	if (! current_user_can('manage_options')) {
		return;
	}

	$upload_dir = wp_upload_dir();
	$images_directory = trailingslashit($upload_dir['basedir']) . 'doors_frames';
	$images_url = trailingslashit($upload_dir['baseurl']) . 'doors_frames';
	wp_mkdir_p($images_directory);

	// Create folders, upload images or delete unused images before rendering the page.
	if (isset($_POST['doors_frames_image_action'])) {
		$image_action = sanitize_key(wp_unslash($_POST['doors_frames_image_action']));

		if (! isset($_POST['doors_frames_images_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['doors_frames_images_nonce'])), 'doors_frames_manage_images')) {
			doors_frames_images_redirect('invalid_nonce');
		}

		if ('create_directory' === $image_action) {
			$parent_directory = isset($_POST['doors_frames_parent_directory'])
				? doors_frames_sanitize_relative_path(wp_unslash($_POST['doors_frames_parent_directory']))
				: '';
			$directory_name = isset($_POST['doors_frames_new_directory'])
				? sanitize_file_name(wp_unslash($_POST['doors_frames_new_directory']))
				: '';
			$available_directories = doors_frames_get_image_directories($images_directory);

			if (! $directory_name || ! in_array($parent_directory, $available_directories, true)) {
				doors_frames_images_redirect('invalid_directory');
			}

			$relative_directory = $parent_directory
				? trailingslashit($parent_directory) . $directory_name
				: $directory_name;
			$new_directory = trailingslashit($images_directory) . $relative_directory;
			$parent_path = $parent_directory
				? trailingslashit($images_directory) . $parent_directory
				: $images_directory;

			if (is_dir($new_directory)) {
				doors_frames_images_redirect('directory_exists');
			}

			if (! is_writable($parent_path)) {
				doors_frames_images_redirect('directory_not_writable');
			}

			if (! wp_mkdir_p($new_directory)) {
				doors_frames_images_redirect('directory_error');
			}

			doors_frames_images_redirect('directory_created');
		}

		if ('upload' === $image_action) {
			$relative_directory = isset($_POST['doors_frames_image_directory'])
				? doors_frames_sanitize_relative_path(wp_unslash($_POST['doors_frames_image_directory']))
				: '';
			$available_directories = doors_frames_get_image_directories($images_directory);

			if (! in_array($relative_directory, $available_directories, true)) {
				doors_frames_images_redirect('invalid_directory');
			}

			if (empty($_FILES['doors_frames_images']['name']) || ! is_array($_FILES['doors_frames_images']['name'])) {
				doors_frames_images_redirect('upload_error');
			}

			$target_directory = $relative_directory
				? trailingslashit($images_directory) . $relative_directory
				: $images_directory;

			if (! is_dir($target_directory) || ! is_writable($target_directory)) {
				doors_frames_images_redirect('directory_not_writable');
			}

			$files_to_upload = array();
			$file_count = count($_FILES['doors_frames_images']['name']);

			for ($index = 0; $index < $file_count; $index++) {
				$name = wp_unslash($_FILES['doors_frames_images']['name'][$index]);
				$tmp_name = $_FILES['doors_frames_images']['tmp_name'][$index];
				$error = (int) $_FILES['doors_frames_images']['error'][$index];

				if (! $name || UPLOAD_ERR_OK !== $error || ! is_uploaded_file($tmp_name)) {
					doors_frames_images_redirect('upload_error');
				}

				$extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
				if ('zip' === $extension) {
					if (! class_exists('ZipArchive')) {
						doors_frames_images_redirect('zip_unavailable');
					}

					$files_to_upload[] = array(
						'name'     => $name,
						'tmp_name' => $tmp_name,
						'type'     => 'archive',
					);
					continue;
				}

				if (! doors_frames_is_valid_image_file($tmp_name, $name)) {
					doors_frames_images_redirect('invalid_file');
				}

				$filename = sanitize_file_name($name);
				$files_to_upload[] = array(
					'tmp_name' => $tmp_name,
					'target'   => trailingslashit($target_directory) . $filename,
					'type'     => 'image',
				);
			}

			$uploaded_files = array();
			foreach ($files_to_upload as $file_to_upload) {
				if ('archive' === $file_to_upload['type']) {
					$archive_result = doors_frames_import_image_archive($file_to_upload['tmp_name'], $target_directory);

					if (is_wp_error($archive_result)) {
						doors_frames_images_redirect($archive_result->get_error_code());
					}

					$uploaded_files = array_merge($uploaded_files, $archive_result);
					continue;
				}

				if (! doors_frames_store_uploaded_image($file_to_upload['tmp_name'], $file_to_upload['target'])) {
					doors_frames_images_redirect('upload_error');
				}

				$uploaded_files[] = $file_to_upload['target'];
			}

			doors_frames_images_redirect('uploaded', count($uploaded_files), $relative_directory);
		}

		if ('delete' === $image_action) {
			$relative_file = isset($_POST['doors_frames_image_file'])
				? doors_frames_sanitize_relative_path(wp_unslash($_POST['doors_frames_image_file']))
				: '';
			$target_file = realpath(trailingslashit($images_directory) . $relative_file);
			$real_images_directory = realpath($images_directory);

			if (! $relative_file || ! $target_file || ! $real_images_directory || ! is_file($target_file) || 0 !== strpos(wp_normalize_path($target_file), trailingslashit(wp_normalize_path($real_images_directory)))) {
				doors_frames_images_redirect('invalid_file');
			}

			$frames_table = $wpdb->prefix . 'doors_frames';
			$usage_count = (int) $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) FROM $frames_table WHERE frame_image = %s",
				$relative_file
			));

			if ($usage_count > 0) {
				doors_frames_images_redirect('image_in_use', $usage_count);
			}

			if (! unlink($target_file)) {
				doors_frames_images_redirect('delete_error');
			}

			doors_frames_images_redirect('deleted');
		}

		if ('delete_all' === $image_action) {
			$deleted_count = doors_frames_delete_all_images($images_directory);

			if (is_wp_error($deleted_count)) {
				doors_frames_images_redirect('delete_all_error');
			}

			doors_frames_images_redirect('deleted_all', $deleted_count, '');
		}
	}

	// Save settings
	if (isset($_POST['doors_frames_settings_submitted'])) {
		if (! isset($_POST['doors_frames_nonce']) || ! wp_verify_nonce($_POST['doors_frames_nonce'], 'doors_frames_save_settings')) {
			echo '<div class="notice notice-error"><p>Невалиден nonce.</p></div>';
		} else {
			$value = isset($_POST['doors_frames_remove_decimal_for_integers']) ? 1 : 0;
			update_option('doors_frames_remove_decimal_for_integers', $value);
			wp_redirect(admin_url('admin.php?page=doors-frames-settings&saved=1'));
			exit;
		}
	}

	$saved = isset($_GET['saved']) && $_GET['saved'] == 1;
	$enabled = get_option('doors_frames_remove_decimal_for_integers', 0);

	if ($saved) {
		echo '<div class="notice notice-success is-dismissible"><p>Настройките са записани.</p></div>';
	}

	echo '<div class="wrap">';
	echo '<h1>Настройки - Doors Frames</h1>';
	echo '<form method="post" action="">';
	wp_nonce_field('doors_frames_save_settings', 'doors_frames_nonce');
	echo '<table class="form-table"><tr><th scope="row">Премахване на нулите след десетичната запетая за цели числа</th><td><label><input type="checkbox" name="doors_frames_remove_decimal_for_integers" value="1" ' . checked(1, $enabled, false) . ' /> Включено</label></td></tr></table>';
	echo '<input type="hidden" name="doors_frames_settings_submitted" value="1" />';
	submit_button();
	echo '</form>';

	doors_frames_render_images_section($images_directory, $images_url);
	echo '</div>';
}

function doors_frames_images_redirect($status, $count = 0, $folder = null)
{
	if (null === $folder) {
		$folder = isset($_POST['doors_frames_current_folder'])
			? doors_frames_sanitize_relative_path(wp_unslash($_POST['doors_frames_current_folder']))
			: '';
	}

	$args = array(
		'page'                => 'doors-frames-settings',
		'doors_frames_images' => sanitize_key($status),
		'image_count'         => absint($count),
	);

	if ($folder) {
		$args['doors_frames_folder'] = $folder;
	}

	$url = add_query_arg($args, admin_url('admin.php'));

	wp_safe_redirect($url);
	exit;
}

function doors_frames_sanitize_relative_path($path)
{
	$path = str_replace('\\', '/', (string) $path);
	$path = trim($path, '/');
	$parts = array_filter(explode('/', $path), 'strlen');

	foreach ($parts as $part) {
		if ('.' === $part || '..' === $part) {
			return '';
		}
	}

	return implode('/', array_map('sanitize_text_field', $parts));
}

function doors_frames_get_image_directories($base_directory)
{
	$directories = array('');

	if (! is_dir($base_directory) || ! is_readable($base_directory)) {
		return $directories;
	}

	doors_frames_collect_image_directories($base_directory, '', $directories);

	natcasesort($directories);
	return array_values($directories);
}

function doors_frames_collect_image_directories($base_directory, $relative_directory, &$directories)
{
	$current_directory = $relative_directory
		? trailingslashit($base_directory) . $relative_directory
		: $base_directory;
	$entries = @scandir($current_directory);

	if (false === $entries) {
		return;
	}

	foreach ($entries as $entry) {
		if ('.' === $entry || '..' === $entry) {
			continue;
		}

		$absolute_path = trailingslashit($current_directory) . $entry;
		if (! is_dir($absolute_path) || is_link($absolute_path)) {
			continue;
		}

		$child_directory = $relative_directory
			? trailingslashit($relative_directory) . $entry
			: $entry;
		$child_directory = str_replace('\\', '/', $child_directory);
		$directories[] = $child_directory;
		doors_frames_collect_image_directories($base_directory, $child_directory, $directories);
	}
}

function doors_frames_get_images($base_directory, $relative_directory = '')
{
	$images = array();
	$directory = $relative_directory
		? trailingslashit($base_directory) . $relative_directory
		: $base_directory;

	if (! is_dir($directory) || ! is_readable($directory)) {
		return $images;
	}

	$iterator = new DirectoryIterator($directory);

	foreach ($iterator as $item) {
		if (! $item->isDot() && $item->isFile() && in_array(strtolower($item->getExtension()), array('jpg', 'jpeg', 'png', 'webp'), true)) {
			$relative_path = $relative_directory
				? trailingslashit($relative_directory) . $item->getFilename()
				: $item->getFilename();
			$images[] = array(
				'path' => str_replace('\\', '/', $relative_path),
				'size' => size_format($item->getSize()),
			);
		}
	}

	usort($images, function ($first, $second) {
		return strnatcasecmp($first['path'], $second['path']);
	});

	return $images;
}

function doors_frames_get_child_directories($directories, $current_directory)
{
	$children = array();
	$prefix = $current_directory ? trailingslashit($current_directory) : '';

	foreach ($directories as $directory) {
		if (! $directory || 0 !== strpos($directory, $prefix)) {
			continue;
		}

		$remainder = substr($directory, strlen($prefix));
		if ($remainder && false === strpos($remainder, '/')) {
			$children[] = $directory;
		}
	}

	return $children;
}

function doors_frames_delete_all_images($base_directory)
{
	if (! is_dir($base_directory) || ! is_writable($base_directory)) {
		return new WP_Error('directory_not_writable');
	}

	$deleted_count = 0;
	$has_error = false;
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($base_directory, RecursiveDirectoryIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ($iterator as $item) {
		if ($item->isFile()) {
			$extension = strtolower($item->getExtension());
			if (in_array($extension, array('jpg', 'jpeg', 'png', 'webp'), true)) {
				if (unlink($item->getPathname())) {
					$deleted_count++;
				} else {
					$has_error = true;
				}
			}
		} elseif ($item->isDir() && ! rmdir($item->getPathname()) && is_dir($item->getPathname())) {
			$has_error = true;
		}
	}

	return $has_error ? new WP_Error('delete_all_error') : $deleted_count;
}

function doors_frames_store_uploaded_image($uploaded_file, $target_file)
{
	$target_directory = dirname($target_file);
	$staging_file = trailingslashit($target_directory) . '.doors-frames-upload-' . uniqid('', true) . '.tmp';

	if (! move_uploaded_file($uploaded_file, $staging_file)) {
		return false;
	}

	$backup_file = false;
	if (is_file($target_file)) {
		$backup_file = wp_tempnam(basename($target_file) . '.backup');

		if (! $backup_file || ! @copy($target_file, $backup_file)) {
			unlink($staging_file);
			return false;
		}
	}

	$moved = @rename($staging_file, $target_file);

	if (! $moved && is_file($target_file)) {
		unlink($target_file);
		$moved = @rename($staging_file, $target_file);
	}

	if (! $moved) {
		$moved = @copy($staging_file, $target_file);
		if ($moved) {
			unlink($staging_file);
		}
	}

	if (! $moved && $backup_file && is_file($backup_file)) {
		@copy($backup_file, $target_file);
	}

	if (is_file($staging_file)) {
		unlink($staging_file);
	}

	if ($backup_file && is_file($backup_file)) {
		unlink($backup_file);
	}

	return $moved;
}

function doors_frames_folder_url($folder)
{
	$args = array('page' => 'doors-frames-settings');

	if ($folder) {
		$args['doors_frames_folder'] = $folder;
	}

	return add_query_arg($args, admin_url('admin.php'));
}

function doors_frames_import_image_archive($archive_file, $target_directory)
{
	$zip = new ZipArchive();
	$open_result = $zip->open($archive_file);

	if (true !== $open_result) {
		return new WP_Error('invalid_archive');
	}

	$maximum_files = 1000;
	$maximum_file_size = 10 * 1024 * 1024;
	$maximum_total_size = 100 * 1024 * 1024;
	$total_size = 0;
	$staged_files = array();
	$directories = array();

	if ($zip->numFiles > $maximum_files) {
		$zip->close();
		return new WP_Error('archive_too_large');
	}

	for ($index = 0; $index < $zip->numFiles; $index++) {
		$entry_name = $zip->getNameIndex($index);

		if (false === $entry_name || false !== strpos($entry_name, "\0")) {
			doors_frames_cleanup_staged_archive_files($staged_files);
			$zip->close();
			return new WP_Error('invalid_archive');
		}

		$entry_name = str_replace('\\', '/', $entry_name);
		if ('/' === substr($entry_name, 0, 1) || preg_match('/^[a-zA-Z]:\//', $entry_name)) {
			doors_frames_cleanup_staged_archive_files($staged_files);
			$zip->close();
			return new WP_Error('invalid_archive');
		}

		$entry_parts = array_filter(explode('/', trim($entry_name, '/')), 'strlen');
		foreach ($entry_parts as $entry_part) {
			if ('.' === $entry_part || '..' === $entry_part) {
				doors_frames_cleanup_staged_archive_files($staged_files);
				$zip->close();
				return new WP_Error('invalid_archive');
			}
		}

		if (! $entry_parts || '__MACOSX' === reset($entry_parts)) {
			continue;
		}

		$safe_parts = array_map('sanitize_file_name', $entry_parts);
		if (in_array('', $safe_parts, true)) {
			continue;
		}

		$safe_path = implode('/', $safe_parts);
		if ('/' === substr($entry_name, -1)) {
			$directories[] = $safe_path;
			continue;
		}

		$extension = strtolower(pathinfo($safe_path, PATHINFO_EXTENSION));
		if (! in_array($extension, array('jpg', 'jpeg', 'png', 'webp'), true)) {
			continue;
		}

		if (0 === strpos(basename($safe_path), '._')) {
			continue;
		}

		$entry_stat = $zip->statIndex($index);
		$entry_size = isset($entry_stat['size']) ? (int) $entry_stat['size'] : 0;
		$total_size += $entry_size;

		if ($entry_size > $maximum_file_size || $total_size > $maximum_total_size) {
			doors_frames_cleanup_staged_archive_files($staged_files);
			$zip->close();
			return new WP_Error('archive_too_large');
		}

		$stream = $zip->getStream($entry_name);
		$temporary_file = wp_tempnam(basename($safe_path));
		if (! $stream || ! $temporary_file) {
			if ($stream) {
				fclose($stream);
			}
			doors_frames_cleanup_staged_archive_files($staged_files);
			$zip->close();
			return new WP_Error('archive_import_error');
		}

		$temporary_handle = fopen($temporary_file, 'wb');
		if (! $temporary_handle) {
			fclose($stream);
			unlink($temporary_file);
			doors_frames_cleanup_staged_archive_files($staged_files);
			$zip->close();
			return new WP_Error('archive_import_error');
		}

		while (! feof($stream)) {
			$chunk = fread($stream, 8192);
			if (false === $chunk || false === fwrite($temporary_handle, $chunk)) {
				fclose($stream);
				fclose($temporary_handle);
				unlink($temporary_file);
				doors_frames_cleanup_staged_archive_files($staged_files);
				$zip->close();
				return new WP_Error('archive_import_error');
			}
		}

		fclose($stream);
		fclose($temporary_handle);

		if (! doors_frames_is_valid_image_file($temporary_file, basename($safe_path))) {
			unlink($temporary_file);
			continue;
		}

		$staged_files[] = array(
			'temporary_file'    => $temporary_file,
			'relative_directory' => dirname($safe_path),
			'filename'           => basename($safe_path),
		);
	}

	$zip->close();

	if (! $staged_files) {
		return new WP_Error('archive_empty');
	}

	foreach ($directories as $relative_directory) {
		wp_mkdir_p(trailingslashit($target_directory) . $relative_directory);
	}

	$created_files = array();
	$backup_files = array();
	foreach ($staged_files as $staged_file) {
		$relative_directory = '.' === $staged_file['relative_directory'] ? '' : $staged_file['relative_directory'];
		$final_directory = $relative_directory
			? trailingslashit($target_directory) . $relative_directory
			: $target_directory;

		if (! wp_mkdir_p($final_directory)) {
			doors_frames_cleanup_staged_archive_files($staged_files);
			doors_frames_cleanup_created_archive_files($created_files);
			doors_frames_restore_archive_backups($backup_files);
			return new WP_Error('archive_import_error');
		}

		$filename = $staged_file['filename'];
		$final_file = trailingslashit($final_directory) . $filename;

		if (! array_key_exists($final_file, $backup_files)) {
			$backup_files[$final_file] = false;

			if (is_file($final_file)) {
				$backup_file = wp_tempnam($filename . '.backup');
				if (! $backup_file || ! @copy($final_file, $backup_file)) {
					doors_frames_cleanup_staged_archive_files($staged_files);
					doors_frames_cleanup_created_archive_files($created_files);
					doors_frames_restore_archive_backups($backup_files);
					return new WP_Error('archive_import_error');
				}

				$backup_files[$final_file] = $backup_file;
			}
		}

		$moved = @rename($staged_file['temporary_file'], $final_file);

		if (! $moved) {
			$moved = @copy($staged_file['temporary_file'], $final_file);
			if ($moved) {
				unlink($staged_file['temporary_file']);
			}
		}

		if (! $moved) {
			doors_frames_cleanup_staged_archive_files($staged_files);
			doors_frames_cleanup_created_archive_files($created_files);
			doors_frames_restore_archive_backups($backup_files);
			return new WP_Error('archive_import_error');
		}

		$created_files[] = $final_file;
	}

	doors_frames_cleanup_archive_backups($backup_files);

	return $created_files;
}

function doors_frames_cleanup_staged_archive_files($staged_files)
{
	foreach ($staged_files as $staged_file) {
		if (isset($staged_file['temporary_file']) && is_file($staged_file['temporary_file'])) {
			unlink($staged_file['temporary_file']);
		}
	}
}

function doors_frames_cleanup_created_archive_files($created_files)
{
	foreach ($created_files as $created_file) {
		if (is_file($created_file)) {
			unlink($created_file);
		}
	}
}

function doors_frames_restore_archive_backups($backup_files)
{
	foreach ($backup_files as $final_file => $backup_file) {
		if ($backup_file && is_file($backup_file)) {
			@copy($backup_file, $final_file);
			unlink($backup_file);
		}
	}
}

function doors_frames_cleanup_archive_backups($backup_files)
{
	foreach ($backup_files as $backup_file) {
		if ($backup_file && is_file($backup_file)) {
			unlink($backup_file);
		}
	}
}

function doors_frames_is_valid_image_file($file, $filename)
{
	$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
	if (! in_array($extension, array('jpg', 'jpeg', 'jpe', 'png', 'webp'), true)) {
		return false;
	}

	$handle = fopen($file, 'rb');

	if (! $handle) {
		return false;
	}

	$header = fread($handle, 12);
	fclose($handle);

	$is_jpeg = strlen($header) >= 3
		&& "\xFF\xD8\xFF" === substr($header, 0, 3);
	$is_png = strlen($header) >= 8
		&& "\x89PNG\x0D\x0A\x1A\x0A" === substr($header, 0, 8);
	$is_webp = 12 === strlen($header)
			&& 'RIFF' === substr($header, 0, 4)
			&& 'WEBP' === substr($header, 8, 4);

	return $is_jpeg || $is_png || $is_webp;
}

function doors_frames_render_images_section($images_directory, $images_url)
{
	global $wpdb;

	$status = isset($_GET['doors_frames_images']) ? sanitize_key(wp_unslash($_GET['doors_frames_images'])) : '';
	$image_count = isset($_GET['image_count']) ? absint($_GET['image_count']) : 0;
	$messages = array(
		'uploaded'          => array('success', sprintf('Успешно качени снимки: %d.', $image_count)),
		'deleted'           => array('success', 'Снимката е изтрита успешно.'),
		'deleted_all'       => array('success', sprintf('Изтрити снимки: %d.', $image_count)),
		'directory_created' => array('success', 'Папката е създадена успешно.'),
		'directory_exists'  => array('warning', 'Папка с това име вече съществува на избраното място.'),
		'directory_error'   => array('error', 'Папката не можа да бъде създадена.'),
		'directory_not_writable' => array('error', 'WordPress няма права за запис в избраната папка.'),
		'invalid_nonce'     => array('error', 'Сесията е изтекла. Опитайте отново.'),
		'invalid_directory' => array('error', 'Избраната папка е невалидна.'),
		'upload_error'      => array('error', 'Снимката не можа да бъде качена.'),
		'invalid_file'      => array('error', 'Позволени са само JPG, PNG и WEBP изображения.'),
		'zip_unavailable'   => array('error', 'ZIP архивите не се поддържат от PHP конфигурацията на този хостинг.'),
		'invalid_archive'   => array('error', 'ZIP архивът е невалиден или съдържа опасни пътища.'),
		'archive_empty'     => array('warning', 'В ZIP архива няма JPG, PNG или WEBP изображения.'),
		'archive_too_large' => array('error', 'ZIP архивът съдържа твърде много или твърде големи файлове.'),
		'archive_import_error' => array('error', 'ZIP архивът не можа да бъде разпакетиран.'),
		'delete_error'      => array('error', 'Снимката не можа да бъде изтрита.'),
		'delete_all_error'  => array('error', 'Не всички снимки и папки можаха да бъдат изтрити.'),
		'image_in_use'      => array('warning', sprintf('Снимката не е изтрита, защото се използва от %d каси.', $image_count)),
	);

	if (isset($messages[$status])) {
		echo '<div class="notice notice-' . esc_attr($messages[$status][0]) . ' is-dismissible"><p>' . esc_html($messages[$status][1]) . '</p></div>';
	}

	$directories = doors_frames_get_image_directories($images_directory);
	$current_directory = isset($_GET['doors_frames_folder'])
		? doors_frames_sanitize_relative_path(wp_unslash($_GET['doors_frames_folder']))
		: '';

	if (! in_array($current_directory, $directories, true)) {
		$current_directory = '';
	}

	$child_directories = doors_frames_get_child_directories($directories, $current_directory);
	$images = doors_frames_get_images($images_directory, $current_directory);
	$frames_table = $wpdb->prefix . 'doors_frames';
	$usage_rows = $wpdb->get_results("SELECT frame_image, COUNT(*) AS usage_count FROM $frames_table WHERE frame_image IS NOT NULL AND frame_image != '' GROUP BY frame_image");
	$usage_by_image = array();

	foreach ($usage_rows as $usage_row) {
		$usage_by_image[$usage_row->frame_image] = (int) $usage_row->usage_count;
	}

	echo '<hr><h2>Снимки на каси</h2>';
	echo '<p>Снимките се съхраняват в <code>wp-content/uploads/doors_frames</code>. Подпапките се запазват и участват в пътя към избраната снимка.</p>';
	echo '<form method="post" style="float:right;margin-top:-46px;" onsubmit="return confirm(\'Сигурни ли сте, че искате да изтриете всички снимки и подпапки? Използваните каси ще останат без изображения, докато не качите файловете отново.\');">';
	wp_nonce_field('doors_frames_manage_images', 'doors_frames_images_nonce');
	echo '<input type="hidden" name="doors_frames_image_action" value="delete_all">';
	echo '<input type="hidden" name="doors_frames_current_folder" value="' . esc_attr($current_directory) . '">';
	echo '<button type="submit" class="button button-link-delete">Изтрий всички</button>';
	echo '</form>';
	echo '<form method="post" style="display:flex;align-items:end;gap:12px;margin:18px 0;">';
	wp_nonce_field('doors_frames_manage_images', 'doors_frames_images_nonce');
	echo '<input type="hidden" name="doors_frames_image_action" value="create_directory">';
	echo '<input type="hidden" name="doors_frames_current_folder" value="' . esc_attr($current_directory) . '">';
	echo '<div><label for="doors-frames-new-directory"><strong>Нова папка</strong></label><br><input id="doors-frames-new-directory" type="text" name="doors_frames_new_directory" required></div>';
	echo '<div><label for="doors-frames-parent-directory"><strong>Създай в</strong></label><br><select id="doors-frames-parent-directory" name="doors_frames_parent_directory">';

	foreach ($directories as $directory) {
		echo '<option value="' . esc_attr($directory) . '"' . selected($current_directory, $directory, false) . '>' . esc_html($directory ?: 'Основна папка') . '</option>';
	}

	echo '</select></div>';
	submit_button('Създай папка', 'secondary', 'submit', false);
	echo '</form>';
	echo '<form method="post" enctype="multipart/form-data" style="display:flex;align-items:end;gap:12px;margin:18px 0 24px;">';
	wp_nonce_field('doors_frames_manage_images', 'doors_frames_images_nonce');
	echo '<input type="hidden" name="doors_frames_image_action" value="upload">';
	echo '<input type="hidden" name="doors_frames_current_folder" value="' . esc_attr($current_directory) . '">';
	echo '<div><label for="doors-frames-images"><strong>Нови снимки или ZIP архив</strong></label><br><input id="doors-frames-images" type="file" name="doors_frames_images[]" accept="image/jpeg,image/png,image/webp,.zip,application/zip" multiple required></div>';
	echo '<div><label for="doors-frames-image-directory"><strong>Папка</strong></label><br><select id="doors-frames-image-directory" name="doors_frames_image_directory">';

	foreach ($directories as $directory) {
		echo '<option value="' . esc_attr($directory) . '"' . selected($current_directory, $directory, false) . '>' . esc_html($directory ?: 'Основна папка') . '</option>';
	}

	echo '</select></div>';
	submit_button('Качи снимките', 'primary', 'submit', false);
	echo '</form>';

	echo '<div style="margin:16px 0 8px;font-size:14px;"><strong>Път:</strong> ';
	echo '<a href="' . esc_url(doors_frames_folder_url('')) . '">Основна папка</a>';

	if ($current_directory) {
		$breadcrumb_parts = explode('/', $current_directory);
		$breadcrumb_path = '';
		foreach ($breadcrumb_parts as $breadcrumb_part) {
			$breadcrumb_path = $breadcrumb_path ? $breadcrumb_path . '/' . $breadcrumb_part : $breadcrumb_part;
			echo ' <span aria-hidden="true">/</span> <a href="' . esc_url(doors_frames_folder_url($breadcrumb_path)) . '">' . esc_html($breadcrumb_part) . '</a>';
		}
	}

	echo '</div>';

	echo '<table class="widefat striped"><thead><tr><th style="width:110px;">Преглед</th><th>Файл</th><th>Размер</th><th>Използване</th><th style="width:120px;">Действие</th></tr></thead><tbody>';

	if ($current_directory) {
		$parent_directory = dirname($current_directory);
		$parent_directory = '.' === $parent_directory ? '' : str_replace('\\', '/', $parent_directory);
		echo '<tr>';
		echo '<td style="font-size:28px;line-height:1;">📁</td>';
		echo '<td><a href="' . esc_url(doors_frames_folder_url($parent_directory)) . '"><strong>..</strong></a></td>';
		echo '<td>—</td><td>—</td>';
		echo '<td><a class="button" href="' . esc_url(doors_frames_folder_url($parent_directory)) . '">Нагоре</a></td>';
		echo '</tr>';
	}

	foreach ($child_directories as $child_directory) {
		echo '<tr>';
		echo '<td style="font-size:28px;line-height:1;">📁</td>';
		echo '<td><a href="' . esc_url(doors_frames_folder_url($child_directory)) . '"><strong>' . esc_html(basename($child_directory)) . '</strong></a></td>';
		echo '<td>—</td><td>Папка</td>';
		echo '<td><a class="button" href="' . esc_url(doors_frames_folder_url($child_directory)) . '">Отвори</a></td>';
		echo '</tr>';
	}

	if (! $images && ! $child_directories) {
		echo '<tr><td colspan="5">В тази папка няма качени снимки на каси.</td></tr>';
	}

	foreach ($images as $image) {
		$usage_count = isset($usage_by_image[$image['path']]) ? $usage_by_image[$image['path']] : 0;
		$image_src = trailingslashit($images_url) . str_replace('%2F', '/', rawurlencode($image['path']));
		echo '<tr>';
		echo '<td><a href="' . esc_url($image_src) . '" target="_blank" rel="noopener noreferrer" title="Отвори снимката в нов таб"><img src="' . esc_url($image_src) . '" alt="" style="display:block;max-width:90px;max-height:70px;object-fit:contain;"></a></td>';
		echo '<td><code>' . esc_html($image['path']) . '</code></td>';
		echo '<td>' . esc_html($image['size']) . '</td>';
		echo '<td>' . ($usage_count ? esc_html(sprintf('%d каси', $usage_count)) : 'Не се използва') . '</td>';
		echo '<td><form method="post" onsubmit="return confirm(\'Сигурни ли сте, че искате да изтриете тази снимка?\');">';
		wp_nonce_field('doors_frames_manage_images', 'doors_frames_images_nonce');
		echo '<input type="hidden" name="doors_frames_image_action" value="delete">';
		echo '<input type="hidden" name="doors_frames_current_folder" value="' . esc_attr($current_directory) . '">';
		echo '<input type="hidden" name="doors_frames_image_file" value="' . esc_attr($image['path']) . '">';
		echo '<button type="submit" class="button button-link-delete"' . ($usage_count ? ' disabled title="Снимката се използва и не може да бъде изтрита."' : '') . '>Изтрий</button>';
		echo '</form></td>';
		echo '</tr>';
	}

	echo '</tbody></table>';
}
