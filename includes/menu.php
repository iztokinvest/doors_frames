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
	if (! current_user_can('manage_options')) {
		return;
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
	echo '</div>';
}

