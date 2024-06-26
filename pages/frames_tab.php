<?php

global $product_frames, $tab_data;

add_filter('woocommerce_product_tabs', 'custom_product_tab');
function custom_product_tab($tabs)
{
	global $wpdb, $product, $tab_data, $product_frames;

	$product_id = $product->get_id();
	$category_ids = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
	$category_ids_string = implode(',', array_map('intval', $category_ids));

	$tabs_table = $wpdb->prefix . 'doors_frames_tabs';
	$frames_table = $wpdb->prefix . 'doors_frames';

	$tab_data = $wpdb->get_results("
        SELECT tab_title, table_text 
        FROM $tabs_table 
        WHERE category_id IN ($category_ids_string)
    ");

	$product_frames = $wpdb->get_results($wpdb->prepare(
		"SELECT * 
		 FROM $frames_table 
		 WHERE product_id = %d
		 AND ((frame_start_date IS NULL AND frame_end_date IS NULL) OR (frame_start_date <= CURDATE() AND frame_end_date >= CURDATE()))
		 ORDER by frame_end_date IS NULL DESC, frame_end_date DESC, frame_id ASC",
		$product_id
	));

	$frame_prices_exists = $wpdb->get_var("SHOW TABLES LIKE 'frame_prices'");

	if (!empty($product_frames) && $tab_data && !empty($tab_data[0]->tab_title) && (current_user_can('administrator') || empty($frame_prices_exists))) {
		$tabs['frames'] = array(
			'title'    => __($tab_data[0]->tab_title, 'woocommerce'),
			'priority' => 31,
			'callback' => 'custom_product_tab_content'
		);
	}

	return $tabs;
}

function custom_product_tab_content()
{
	global $product, $product_frames, $tab_data;

	if (!empty($product_frames)) {
		$frame_rows = '';
		foreach ($product_frames as $frame) {
			$image = $frame->frame_image;
			$description = esc_html($frame->frame_description);

			if ($frame->frame_id == '-5') {
				$frame_price = $product->get_regular_price();
				$frame_promo_price = $product->get_sale_price();
			} else {
				$frame_price = $frame->frame_price;
				$frame_promo_price = $frame->frame_promo_price;
			}

			$price = floatval($frame_price) > 0 ? esc_html($frame_price) . ' лв.' : '';
			$promo_price = '';

			if (floatval($frame_promo_price) > 0) {
				$promo_price = esc_html($frame_promo_price) . ' лв.';
				$price = "<del>" . esc_html($frame_price) . " лв.</del>";
			}

			$upload_dir = wp_upload_dir();
			$folderPath = esc_url("{$upload_dir['baseurl']}/doors_frames");

			$frame_rows .= <<<HTML
				<tr class="frame_table_row">
					<td>
						<div class="frame_image_div"><img class="frame_image" src='$folderPath/$image' alt='$description'></div>
					</td>
					<td>
						<div class="frame_description">$description</div>
					</td>
					<td>
						<div class="frame_promo_price">$promo_price</div>
						<div class="frame_price">$price</div>
					</td>
				</tr>
			HTML;
		}

		echo <<<HTML
			<table class="frame_table">
				<thead class="frame_table_head">
					<tr class="frame_table_head_row">
						<th class="frame_table_head_image_cell">Каса</th>
						<th class="frame_table_head_description_cell">Описание</th>
						<th class="frame_table_head_price_cell">Цена</th>
					</tr>
				</thead>
				<tbody class="frame_table_body">
					$frame_rows
				</tbody>
			</table>
		HTML;

		if ($tab_data && !empty($tab_data->table_text)) {
			echo '<div class="frame_table_text">' . esc_html($tab_data->table_text) . '</div>';
		}
	}
}
