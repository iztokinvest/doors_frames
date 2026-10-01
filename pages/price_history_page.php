<?php

require_once __DIR__ . '/../includes/history_table.php';

/** Bounded, read-only suggestions, including names retained for deleted records. */
function doors_frames_history_filter_options($type, $search = '', $selected_id = null)
{
	if (!in_array($type, array('author', 'product'), true)) {
		return array();
	}
	if ($selected_id === null && trim($search) === '') {
		return array();
	}
	$rows = doors_frames_history_db(function ($db) use ($type, $search, $selected_id) {
		$history = $db->prefix . 'doors_frames_price_history';
		$id = 'entities.id';
		if ($type === 'author') {
			$column = 'user_id';
			$snapshot = "(SELECT saved.user_name FROM $history saved WHERE saved.user_id = $id ORDER BY saved.id DESC LIMIT 1)";
			$name = "COALESCE(NULLIF(u.display_name, ''), $snapshot)";
			$join = "LEFT JOIN {$db->users} u ON u.ID = $id";
			$search_name = "($name LIKE %s OR u.user_login LIKE %s OR $snapshot LIKE %s)";
		} else {
			$column = 'product_id';
			$snapshot = "(SELECT saved.object_name FROM $history saved WHERE saved.product_id = $id ORDER BY saved.id DESC LIMIT 1)";
			$name = "COALESCE(NULLIF(p.post_title, ''), $snapshot)";
			$join = "LEFT JOIN {$db->posts} p ON p.ID = $id";
			$search_name = "$name LIKE %s";
		}
		$args = array();
		$where = '1=1';
		if ($selected_id !== null) {
			$where = "$id = %d";
			$args[] = $selected_id;
		} elseif ($search !== '') {
			$like = '%' . $db->esc_like($search) . '%';
			$where = $search_name;
			$args = $type === 'author' ? array($like, $like, $like) : array($like);
			if (ctype_digit($search)) {
				$where = "($id = %d OR $where)";
				array_unshift($args, (int) $search);
			}
		}
		// An exact ID match is placed first even when other names contain the number.
		$order = 'label ASC';
		if ($search !== '' && ctype_digit($search)) {
			$order = "CASE WHEN $id = %d THEN 0 ELSE 1 END, label ASC";
			$args[] = (int) $search;
		}
		$args[] = 20;
		return $db->get_results($db->prepare(
			"SELECT $id AS id, $name AS label
			 FROM (SELECT DISTINCT $column AS id FROM $history) entities $join
			 WHERE $where ORDER BY $order LIMIT %d", $args
		));
	});
	$options = array();
	foreach (is_array($rows) ? $rows : array() as $row) {
		$options[] = array('value' => (string) $row->id, 'text' => wp_strip_all_tags($row->label) . ' (#' . $row->id . ')');
	}
	return $options;
}

function doors_frames_history_search_filters()
{
	if (!current_user_can('manage_options')) {
		wp_send_json_error(array('message' => 'Нямате достъп до хронологията.'), 403);
	}
	check_ajax_referer('doors_frames_history_filters', 'nonce');
	$type = isset($_GET['filter_type']) && is_string($_GET['filter_type']) ? sanitize_key(wp_unslash($_GET['filter_type'])) : '';
	if (!in_array($type, array('author', 'product'), true)) {
		wp_send_json_error(array('message' => 'Невалиден филтър.'), 400);
	}
	$search = isset($_GET['search']) && is_string($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';
	$search = mb_substr(ltrim($search, '# '), 0, 100);
	wp_send_json_success(doors_frames_history_filter_options($type, $search));
}
add_action('wp_ajax_doors_frames_history_search_filters', 'doors_frames_history_search_filters');

function doors_frames_price_history_page()
{
	if (!current_user_can('manage_options')) {
		return;
	}
	$actions = doors_frames_history_actions();
	$filters = doors_frames_history_table_filters($_GET);
	$product_id = $filters['product_id'];
	$user_id = $filters['user_id'];
	$action = $filters['action'];
	$operation = $filters['operation'];
	$dates = array('from' => $filters['from'], 'to' => $filters['to']);
	$product_options = array();
	$author_options = array();
	foreach (array('product' => $product_id ?: null, 'author' => $user_id) as $type => $id) {
		if ($id === null) {
			continue;
		}
		$options = $type === 'product' ? $product_options : $author_options;
		if (!in_array((string) $id, array_column($options, 'value'), true)) {
			$selected_options = doors_frames_history_filter_options($type, '', $id);
			if (!$selected_options) {
				$selected_options[] = array('value' => (string) $id, 'text' => ($type === 'product' ? 'Артикул' : 'Автор') . ' #' . $id);
			}
			if ($type === 'product') {
				$product_options = array_merge($product_options, $selected_options);
			} else {
				$author_options = array_merge($author_options, $selected_options);
			}
		}
	}
	?>
	<div class="wrap">
		<h1>Хронология на цените</h1>
		<p>При подготвени цени авторът е човекът, който е избрал цената. Часовете са в часовата зона на сайта. Историята започва от добавянето на тази функционалност.</p>
		<p>Историята се пази една година. По-старите записи се изтриват автоматично при отваряне на плъгина, освен последната подготовка на цени, които още чакат активиране.</p>
		<form method="get" class="doors-frames-history-filters">
			<input type="hidden" name="page" value="doors-frames-price-history">
			<div class="doors-frames-history-filter doors-frames-history-product">
				<label for="history-product-filter">Артикул</label>
				<select id="history-product-filter" name="product_id" data-history-filter="product" aria-describedby="history-product-hint">
					<option value="" data-placeholder="true">Всички артикули</option>
					<?php foreach ($product_options as $option) : ?>
						<option value="<?php echo esc_attr($option['value']); ?>" <?php selected((string) $product_id, $option['value']); ?>><?php echo esc_html($option['text']); ?></option>
					<?php endforeach; ?>
				</select>
				<small id="history-product-hint">Напишете име или ID и изберете артикул от подсказките.</small>
			</div>
			<div class="doors-frames-history-filter doors-frames-history-author">
				<label for="history-author-filter">Автор</label>
				<select id="history-author-filter" name="user_id" data-history-filter="author" aria-describedby="history-author-hint">
					<option value="" data-placeholder="true">Всички автори</option>
					<?php foreach ($author_options as $option) : ?>
						<option value="<?php echo esc_attr($option['value']); ?>" <?php selected($user_id === null ? '' : (string) $user_id, $option['value']); ?>><?php echo esc_html($option['text']); ?></option>
					<?php endforeach; ?>
				</select>
				<small id="history-author-hint">Търсене по име, потребителско име или ID.</small>
			</div>
			<label>Действие <select name="history_action"><option value="">Всички</option>
				<?php foreach ($actions as $key => $label) : ?>
					<option value="<?php echo esc_attr($key); ?>" <?php selected($action, $key); ?>><?php echo esc_html($label); ?></option>
				<?php endforeach; ?>
			</select></label>
			<label>От <input type="date" name="from" value="<?php echo esc_attr($dates['from']); ?>"></label>
			<label>До <input type="date" name="to" value="<?php echo esc_attr($dates['to']); ?>"></label>
			<input type="hidden" name="operation_id" value="<?php echo esc_attr($operation); ?>">
			<button class="button">Филтрирай</button>
			<a class="button" id="history-clear-filters" href="<?php echo esc_url(admin_url('admin.php?page=doors-frames-price-history')); ?>">Изчисти</a>
		</form>
		<p id="history-operation-filter" <?php if (!$operation) : ?>hidden<?php endif; ?>>Операция: <code><?php echo esc_html($operation); ?></code></p>
		<div id="history-table-error" class="notice notice-error" hidden><p>Хронологията не може да бъде заредена. Опитайте отново.</p></div>
		<div class="doors-frames-history-table">
			<table id="doors-frames-history-table" class="display" style="width:100%">
				<thead><tr><th>Дата и час</th><th>Автор</th><th>Артикул / каса</th><th>Цена</th><th>Преди</th><th>След</th><th>Действие</th><th>Операция</th></tr></thead>
				<tbody></tbody>
			</table>
		</div>
		<noscript><p>Включете JavaScript, за да заредите и филтрирате хронологията.</p></noscript>
	</div>
	<?php
}
