<?php

function doors_frames_history_actions()
{
	return array(
		'changed' => 'Промяна',
		'bulk_changed' => 'Масова промяна',
		'prepared' => 'Подготовка за по-късно',
		'bulk_prepared' => 'Масова подготовка',
		'activated' => 'Прилагане на подготвени цени',
		'created' => 'Добавяне',
		'copied' => 'Копиране',
		'copy_prepared' => 'Копиране за по-късно',
		'deleted' => 'Изтриване',
	);
}

function doors_frames_history_table_filters($request)
{
	$value = function ($key) use ($request) {
		return isset($request[$key]) && is_scalar($request[$key]) ? sanitize_text_field(wp_unslash((string) $request[$key])) : '';
	};
	$filters = array(
		'product_id' => absint($value('product_id')),
		'user_id' => $value('user_id') === '' ? null : absint($value('user_id')),
		'action' => sanitize_key($value('history_action')),
		'operation' => $value('operation_id'),
	);
	foreach (array('from', 'to') as $key) {
		$date = DateTimeImmutable::createFromFormat('!Y-m-d', $value($key), wp_timezone());
		$filters[$key] = $date && $date->format('Y-m-d') === $value($key) ? $value($key) : '';
	}
	return $filters;
}

/** All values are bound; sorting columns and direction come from fixed allowlists. */
function doors_frames_history_table_data($request)
{
	$filters = doors_frames_history_table_filters($request);
	$draw = isset($request['draw']) && is_scalar($request['draw']) ? max(0, (int) $request['draw']) : 0;
	$start = isset($request['start']) && is_scalar($request['start']) ? max(0, (int) $request['start']) : 0;
	$length = isset($request['length']) && is_scalar($request['length']) ? (int) $request['length'] : 50;
	$length = $length <= 0 ? 50 : min($length, 100);
	$where = array('1=1');
	$args = array();
	foreach (array('product_id', 'user_id') as $key) {
		if (($key === 'product_id' && $filters[$key]) || ($key === 'user_id' && $filters[$key] !== null)) {
			$where[] = "h.$key = %d";
			$args[] = $filters[$key];
		}
	}
	if (isset(doors_frames_history_actions()[$filters['action']])) {
		$where[] = 'h.action = %s';
		$args[] = $filters['action'];
	}
	if ($filters['operation'] !== '') {
		$where[] = 'h.operation_id = %s';
		$args[] = $filters['operation'];
	}
	if ($filters['from']) {
		$where[] = 'h.changed_at >= %s';
		$args[] = get_gmt_from_date($filters['from'] . ' 00:00:00');
	}
	if ($filters['to']) {
		$where[] = 'h.changed_at < %s';
		$end = new DateTimeImmutable($filters['to'], wp_timezone());
		$args[] = $end->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
	}
	$search = isset($request['search']['value']) && is_string($request['search']['value'])
		? mb_substr(sanitize_text_field(wp_unslash($request['search']['value'])), 0, 100) : '';
	$columns = array('h.changed_at', 'h.user_name', 'h.object_name', 'h.price_type', 'h.old_price', 'h.new_price', 'h.action', 'h.operation_id');
	$order = 'h.id DESC';
	if (isset($request['order'][0]) && is_array($request['order'][0])) {
		$requested = $request['order'][0];
		$column = isset($requested['column']) && is_scalar($requested['column']) ? (int) $requested['column'] : -1;
		$direction = isset($requested['dir']) && $requested['dir'] === 'asc' ? 'ASC' : 'DESC';
		if (isset($columns[$column])) {
			$order = $columns[$column] . ' ' . $direction . ', h.id DESC';
		}
	}
	$result = doors_frames_history_db(function ($db) use ($where, $args, $search, $order, $start, $length) {
		$table = $db->prefix . 'doors_frames_price_history';
		if ($search !== '') {
			$like = '%' . $db->esc_like($search) . '%';
			$matches = array('h.user_name LIKE %s', 'h.object_name LIKE %s', 'h.operation_id LIKE %s');
			array_push($args, $like, $like, $like);
			foreach (doors_frames_history_actions() as $key => $label) {
				if (mb_stripos($label, $search) !== false) {
					$matches[] = 'h.action = %s';
					$args[] = $key;
				}
			}
			foreach (array('regular' => 'Основна', 'sale' => 'Промоционална') as $key => $label) {
				if (mb_stripos($label, $search) !== false) {
					$matches[] = 'h.price_type = %s';
					$args[] = $key;
				}
			}
			if (ctype_digit($search)) {
				foreach (array('product_id', 'object_id', 'frame_id', 'user_id') as $column) {
					$matches[] = "h.$column = %d";
					$args[] = (int) $search;
				}
			}
			if (is_numeric(str_replace(',', '.', $search))) {
				$matches[] = '(h.old_price = %f OR h.new_price = %f)';
				array_push($args, (float) str_replace(',', '.', $search), (float) str_replace(',', '.', $search));
			}
			$where[] = '(' . implode(' OR ', $matches) . ')';
		}
		$conditions = implode(' AND ', $where);
		$total = (int) $db->get_var("SELECT COUNT(*) FROM $table");
		if ($db->last_error) {
			return false;
		}
		$filtered = count($where) === 1 ? $total : (int) $db->get_var($db->prepare("SELECT COUNT(*) FROM $table h WHERE $conditions", $args));
		if ($db->last_error) {
			return false;
		}
		$rows = $db->get_results($db->prepare(
			"SELECT h.*, source.changed_at AS prepared_at FROM $table h
			 LEFT JOIN $table source ON source.id = h.source_history_id
			 WHERE $conditions ORDER BY $order LIMIT %d OFFSET %d",
			array_merge($args, array($length, $start))
		));
		return array('recordsTotal' => $total, 'recordsFiltered' => $filtered, 'rows' => $rows);
	});
	$response = array('draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => array());
	if ($result === false) {
		$response['error'] = 'Хронологията не може да бъде прочетена. Опитайте отново.';
		return $response;
	}
	$response['recordsTotal'] = $result['recordsTotal'];
	$response['recordsFiltered'] = $result['recordsFiltered'];
	foreach ($result['rows'] as $row) {
		$response['data'][] = doors_frames_history_table_cells($row);
	}
	return $response;
}

function doors_frames_history_table_cells($row)
{
	$date = esc_html(get_date_from_gmt($row->changed_at, 'd.m.Y H:i:s'));
	if ($row->prepared_at) {
		$date .= '<br><small>Подготвена: ' . esc_html(get_date_from_gmt($row->prepared_at, 'd.m.Y H:i:s')) . '</small>';
	}
	$author = esc_html($row->user_name);
	if ($row->user_id) {
		$author .= '<br><small>ID: ' . esc_html($row->user_id) . '</small>';
	}
	$item = esc_html($row->object_name) . '<br><small>Артикул #' . esc_html($row->product_id);
	if ($row->object_type === 'frame') {
		$item .= ' / Каса №' . esc_html($row->frame_id);
	} elseif ($row->object_type === 'variation') {
		$item .= ' / Вариация #' . esc_html($row->object_id);
	}
	$item .= '</small>';
	$prices = array();
	foreach (array('old_price', 'new_price') as $key) {
		$prices[] = $row->$key === null ? '—' : wp_kses_post(wc_price($row->$key, array('currency' => $row->currency)));
	}
	$actions = doors_frames_history_actions();
	$operation = '<a data-history-operation="' . esc_attr($row->operation_id) . '" title="' . esc_attr($row->operation_id) . '" href="'
		. esc_url(add_query_arg(array('page' => 'doors-frames-price-history', 'operation_id' => $row->operation_id), admin_url('admin.php'))) . '">'
		. esc_html(substr($row->operation_id, 0, 8)) . '</a>';
	return array($date, $author, $item, $row->price_type === 'regular' ? 'Основна' : 'Промоционална', $prices[0], $prices[1], esc_html($actions[$row->action] ?? $row->action), $operation);
}

function doors_frames_history_load_table()
{
	if (!current_user_can('manage_options')) {
		wp_send_json_error(array('message' => 'Нямате достъп до хронологията.'), 403);
	}
	check_ajax_referer('doors_frames_history_table', 'nonce');
	wp_send_json(doors_frames_history_table_data($_GET));
}
add_action('wp_ajax_doors_frames_history_load_table', 'doors_frames_history_load_table');
