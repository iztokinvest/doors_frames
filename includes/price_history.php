<?php

/** History is deliberately independent of the price tables and their writes. */
function doors_frames_history_db($callback)
{
	global $wpdb;
	$state = array();
	foreach (array('last_error', 'last_query', 'last_result', 'num_rows', 'rows_affected', 'insert_id') as $key) {
		$state[$key] = $wpdb->$key;
	}
	$suppressed = $wpdb->suppress_errors(true);
	$wpdb->last_error = '';
	try {
		$result = $callback($wpdb);
		if ($wpdb->last_error) {
			error_log('Doors Frames price history: ' . $wpdb->last_error);
			return false;
		}
		return $result;
	} catch (Throwable $error) {
		error_log('Doors Frames price history: ' . $error->getMessage());
		return false;
	} finally {
		$wpdb->suppress_errors($suppressed);
		foreach ($state as $key => $value) {
			$wpdb->$key = $value;
		}
	}
}

function doors_frames_install_price_history()
{
	global $wpdb;
	if (get_option('doors_frames_history_schema_version') === '1') {
		return;
	}
	$created = doors_frames_history_db(function ($db) {
		$table = $db->prefix . 'doors_frames_price_history';
		$collate = $db->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta("CREATE TABLE $table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			operation_id varchar(36) NOT NULL,
			changed_at datetime NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_name varchar(250) NOT NULL DEFAULT '',
			product_id bigint(20) unsigned NOT NULL,
			object_type varchar(20) NOT NULL,
			object_id bigint(20) unsigned NOT NULL,
			frame_id int(11) NOT NULL DEFAULT 0,
			object_name text NOT NULL,
			price_type varchar(20) NOT NULL,
			old_price decimal(26,8) DEFAULT NULL,
			new_price decimal(26,8) DEFAULT NULL,
			currency varchar(10) NOT NULL,
			action varchar(30) NOT NULL,
			pending_id bigint(20) unsigned NOT NULL DEFAULT 0,
			source_history_id bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY product_history (product_id,id),
			KEY user_history (user_id,id),
			KEY changed_at (changed_at),
			KEY operation_id (operation_id),
			KEY preparation (object_type,object_id,pending_id,price_type,id)
		) $collate;");
		return $db->get_var($db->prepare('SHOW TABLES LIKE %s', $db->esc_like($table))) === $table;
	});
	if ($created) {
		update_option('doors_frames_history_schema_version', '1', false);
	}
}
add_action('admin_init', 'doors_frames_install_price_history');

/** Remove expired history in small batches when a plugin page is opened. */
function doors_frames_cleanup_price_history()
{
	if (!current_user_can('manage_options')) {
		return;
	}
	$cutoff = (new DateTimeImmutable('now', wp_timezone()))->modify('-1 year')
		->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
	doors_frames_history_db(function ($db) use ($cutoff) {
		$history = $db->prefix . 'doors_frames_price_history';
		$products = $db->prefix . 'doors_frames_products';
		$frames = $db->prefix . 'doors_frames';
		do {
			// Keep only the latest preparation for each price still awaiting activation.
			// Older revisions of that preparation expire normally.
			$ids = $db->get_col($db->prepare(
				"SELECT h.id FROM $history h
				 LEFT JOIN $products p ON h.object_type IN ('product','variation')
				 AND p.id = h.pending_id AND p.product_id = h.product_id
				 LEFT JOIN $frames f ON h.object_type = 'frame' AND f.id = h.pending_id
				 AND f.id = h.object_id AND f.active = 0
				 WHERE h.changed_at < %s AND NOT (
				 h.action IN ('prepared','bulk_prepared','copy_prepared')
				 AND (p.id IS NOT NULL OR f.id IS NOT NULL)
				 AND NOT EXISTS (
					 SELECT 1 FROM $history newer WHERE newer.object_type = h.object_type
					 AND newer.object_id = h.object_id AND newer.pending_id = h.pending_id
					 AND newer.price_type = h.price_type AND newer.id > h.id
					 AND newer.action IN ('prepared','bulk_prepared','copy_prepared')
				 )) ORDER BY h.changed_at, h.id LIMIT 1000",
				$cutoff
			));
			if ($db->last_error || !$ids) {
				return !$db->last_error;
			}
			$placeholders = implode(',', array_fill(0, count($ids), '%d'));
			if ($db->query($db->prepare("DELETE FROM $history WHERE id IN ($placeholders) AND changed_at < %s", array_merge($ids, array($cutoff)))) === false) {
				return false;
			}
		} while (count($ids) === 1000);
		return true;
	});
}

function doors_frames_history_number($price, $type)
{
	if ($price === null || $price === '' || ($type === 'sale' && (float) $price == 0)) {
		return null;
	}
	return number_format((float) $price, 8, '.', '');
}

function doors_frames_history_product_snapshot($product)
{
	return array(
		'product_id' => $product->is_type('variation') ? $product->get_parent_id() : $product->get_id(),
		'object_type' => $product->is_type('variation') ? 'variation' : 'product',
		'object_id' => $product->get_id(),
		'object_name' => $product->get_name(),
		'frame_id' => 0,
		'regular' => $product->get_regular_price('edit'),
		'sale' => $product->get_sale_price('edit'),
	);
}

/** A request shares one operation; bulk activation may span AJAX requests. */
function doors_frames_history_operation()
{
	static $operation;
	if (!$operation) {
		$requested = isset($_POST['history_operation_id']) ? wp_unslash($_POST['history_operation_id']) : '';
		$operation = is_string($requested) && preg_match('/^[a-f0-9-]{36}$/i', $requested)
			? $requested : wp_generate_uuid4();
	}
	return $operation;
}

/** One row per changed price also preserves different authors for regular/sale prices. */
function doors_frames_history_record($context, $before, $after, $action, $result, $pending_id = 0)
{
	if (!$result || $before === false) {
		return;
	}
	doors_frames_history_db(function ($db) use ($context, $before, $after, $action, $pending_id) {
		$table = $db->prefix . 'doors_frames_price_history';
		$user = wp_get_current_user();
		foreach (array('regular', 'sale') as $type) {
			if (!array_key_exists($type, $after)) {
				continue;
			}
			$old = doors_frames_history_number(isset($before[$type]) ? $before[$type] : null, $type);
			$new = doors_frames_history_number($after[$type], $type);
			$first_preparation = in_array($action, array('prepared', 'bulk_prepared', 'copy_prepared'), true)
				&& (!empty($context['first_preparation'][$type]) || !array_key_exists($type, (array) $before));
			if ($old === $new && !$first_preparation) {
				continue;
			}
			$user_id = $user->ID;
			$user_name = $user->ID ? $user->display_name . ' (' . $user->user_login . ')' : 'Неизвестен автор';
			$source_id = 0;
			if ($action === 'activated') {
				// Never attribute prepared prices to the person pressing Activate.
				$source = $db->get_row($db->prepare(
					"SELECT id, user_id, user_name, new_price FROM $table
					 WHERE object_type = %s AND object_id = %d AND pending_id = %d AND price_type = %s
					 AND action IN ('prepared','bulk_prepared','copy_prepared') ORDER BY id DESC LIMIT 1",
					$context['object_type'], $context['object_id'], $pending_id, $type
				));
				$user_id = 0;
				$user_name = 'Неизвестен автор';
				if ($source && doors_frames_history_number($source->new_price, $type) === $new) {
					$user_id = $source->user_id;
					$user_name = $source->user_name;
					$source_id = $source->id;
				}
			}
			$written = $db->insert($table, array(
				'operation_id' => doors_frames_history_operation(),
				'changed_at' => current_time('mysql', true),
				'user_id' => $user_id,
				'user_name' => $user_name,
				'product_id' => $context['product_id'],
				'object_type' => $context['object_type'],
				'object_id' => $context['object_id'],
				'frame_id' => $context['frame_id'],
				'object_name' => $context['object_name'],
				'price_type' => $type,
				'old_price' => $old,
				'new_price' => $new,
				'currency' => get_woocommerce_currency(),
				'action' => $action,
				'pending_id' => $pending_id,
				'source_history_id' => $source_id,
			));
			if ($written === false) {
				return false;
			}
		}
		return true;
	});
}

function doors_frames_history_product($product, $before, $action, $result, $pending_id = 0)
{
	if ($result) {
		$after = doors_frames_history_product_snapshot($product);
		doors_frames_history_record($after, $before, $after, $action, $result, $pending_id);
	}
}

function doors_frames_history_pending_snapshot($product_id)
{
	return doors_frames_history_db(function ($db) use ($product_id) {
		return $db->get_row($db->prepare("SELECT * FROM {$db->prefix}doors_frames_products WHERE product_id = %d", $product_id));
	});
}

function doors_frames_history_pending_product($product, $before, $action, $result, $fields = array('regular', 'sale'))
{
	if (!$result || $before === false) {
		return;
	}
	$after = doors_frames_history_pending_snapshot($product->get_id());
	if (!$after) {
		return;
	}
	$context = doors_frames_history_product_snapshot($product);
	$old_prices = array();
	$new_prices = array();
	foreach ($fields as $type) {
		$column = $type === 'regular' ? 'product_price' : 'product_promo_price';
		$has_previous = $before && $before->$column !== null;
		$context['first_preparation'][$type] = !$has_previous;
		$old_prices[$type] = $has_previous ? $before->$column : $context[$type];
		$new_prices[$type] = $after->$column;
		// Product activation interprets a prepared zero as removal of the price.
		if ($type === 'regular') {
			if ($has_previous && (float) $old_prices[$type] == 0) {
				$old_prices[$type] = '';
			}
			if ($new_prices[$type] !== null && (float) $new_prices[$type] == 0) {
				$new_prices[$type] = '';
			}
		}
	}
	doors_frames_history_record($context, $old_prices, $new_prices, $action, $result, $after->id);
}

/** Decode each saved variation list once, regardless of the number of variations. */
function doors_frames_history_pending_variations($product, $before, $action, $result)
{
	if (!$result || $before === false) {
		return;
	}
	$after = doors_frames_history_pending_snapshot($product->get_id());
	if (!$after) {
		return;
	}
	$previous = array();
	foreach ($before && $before->variations ? (array) json_decode($before->variations, true) : array() as $variation) {
		$previous[$variation['variation_id']] = array('regular' => $variation['regular_price'], 'sale' => $variation['sale_price']);
	}
	foreach ((array) json_decode($after->variations, true) as $variation) {
		$subject = wc_get_product($variation['variation_id']);
		if (!$subject) {
			continue;
		}
		$context = doors_frames_history_product_snapshot($subject);
		$has_previous = isset($previous[$variation['variation_id']]);
		$context['first_preparation'] = array('regular' => !$has_previous, 'sale' => !$has_previous);
		$old_prices = $has_previous ? $previous[$variation['variation_id']] : $context;
		$new_prices = array('regular' => $variation['regular_price'], 'sale' => $variation['sale_price']);
		doors_frames_history_record($context, $old_prices, $new_prices, $action, $result, $after->id);
	}
}

function doors_frames_history_frame_snapshot($id)
{
	return doors_frames_history_db(function ($db) use ($id) {
		return $db->get_row($db->prepare("SELECT * FROM {$db->prefix}doors_frames WHERE id = %d", $id));
	});
}

function doors_frames_history_frame($before, $after, $action, $result, $baseline = null)
{
	$row = $after ?: $before;
	if (!$result || $before === false || !$row || $row->frame_id <= 0) {
		return;
	}
	$context = array(
		'product_id' => $row->product_id,
		'object_type' => 'frame',
		'object_id' => $row->id,
		'frame_id' => $row->frame_id,
		'object_name' => get_the_title($row->product_id) . ' — ' . wp_strip_all_tags($row->frame_description),
	);
	$old_prices = array('regular' => $before ? $before->frame_price : null, 'sale' => $before ? $before->frame_promo_price : null);
	$new_prices = array('regular' => $after ? $after->frame_price : null, 'sale' => $after ? $after->frame_promo_price : null);
	if (!$before && !$row->active) {
		if ($baseline === null) {
			$baseline = doors_frames_history_db(function ($db) use ($row) {
				return $db->get_row($db->prepare("SELECT * FROM {$db->prefix}doors_frames WHERE product_id = %d AND frame_id = %d AND active = 1 ORDER BY id DESC LIMIT 1", $row->product_id, $row->frame_id));
			});
		}
		$old_prices = array('regular' => $baseline ? $baseline->frame_price : null, 'sale' => $baseline ? $baseline->frame_promo_price : null);
		$context['first_preparation'] = array('regular' => true, 'sale' => true);
	}
	doors_frames_history_record($context, $old_prices, $new_prices, $action, $result, (!$row->active || $action === 'activated') ? $row->id : 0);
}
