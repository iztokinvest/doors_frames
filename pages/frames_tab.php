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

	if ($tab_data && !empty($tab_data[0]->tab_title)) {
		$tab_title = $tab_data[0]->tab_title;
	} else {
		$tab_title = 'Цени според касата';
	}

	$product_frames = $wpdb->get_results($wpdb->prepare(
		"SELECT * 
		 FROM $frames_table 
		 WHERE product_id = %d AND active = 1
		 ORDER by frame_id ASC",
		$product_id
	));

	$frame_prices_exists = $wpdb->get_var("SHOW TABLES LIKE 'frame_prices'");

	if (!empty($product_frames) && (current_user_can('administrator') || empty($frame_prices_exists))) {
		$tabs['frames'] = array(
			'title'    => __($tab_title, 'woocommerce'),
			'priority' => 31,
			'callback' => 'custom_product_tab_content'
		);
	}

	return $tabs;
}

function price($sum) {
    return number_format($sum, 2, '.', ' ');
}

function custom_product_tab_content()
{
	global $product, $product_frames, $tab_data;
	$priceExists = false;
	$price = '';
	$promo_price = '';
	$price_th = '';
	$price_td = '';

	foreach ($product_frames as $item) {
		if (!is_null($item->frame_price) && (float)$item->frame_price > 0) {
			$priceExists = true;
			break;
		}
	}

	// Евро
	function euroPrice($sum) {
		$euroPrice = $sum / 1.95583;
		return " <span class='price-euro-frame'>" . number_format($euroPrice, 2, '.', ' ') . " €</span>";
		return "";
	}

	if (!empty($product_frames)) {
		$frame_rows = '';
		foreach ($product_frames as $frame) {
			$image = $frame->frame_image;
			$description = $frame->frame_description;
			$alt = strip_tags($frame->frame_description);

			if ($frame->frame_id == '-5') {
				if ($product->is_type('variable')) {
					$available_variations = $product->get_available_variations();
					$min_regular_price = null;
					$min_sale_price = null;

					foreach ($available_variations as $variation) {
						$variation_product = wc_get_product($variation['variation_id']);

						$regular_price = $variation_product->get_regular_price();
						$sale_price = $variation_product->get_sale_price();

						if ($min_regular_price === null || $regular_price < $min_regular_price) {
							$min_regular_price = $regular_price;
						}
						if ($sale_price && ($min_sale_price === null || $sale_price < $min_sale_price)) {
							$min_sale_price = $sale_price;
						}
					}

					$frame_price = $min_regular_price;
					$frame_promo_price = $min_sale_price;
				} else {
					$frame_price = $product->get_regular_price();
					$frame_promo_price = $product->get_sale_price();
				}
			} else {
				$frame_price = $frame->frame_price;
				$frame_promo_price = $frame->frame_promo_price;
			}

			if ($priceExists) {
				$price = floatval($frame_price) > 0 ? price($frame_price) . 'лв.' . euroPrice($frame_price) : '';
				$promo_price = '';

				if (floatval($frame_promo_price) > 0) {
					$promo_price = price($frame_promo_price) . 'лв.' . euroPrice($frame_promo_price);
					$price = "<del>" . price($frame_price) . "лв.</del>";
				}

				$price_th = '<th>Цена</th>';
				$price_td = "<td data-th='Цена' class='price-css'>$promo_price $price</td>";
			}

			$upload_dir = wp_upload_dir();
			$folderPath = esc_url("{$upload_dir['baseurl']}/doors_frames");

			
$frame_rows .= <<<HTML
	<tr>
		<td data-th="Каса">
			<img src='$folderPath/$image' alt='$alt'>
		</td>
		<td data-th="Описание">
			$description
		</td>
		$price_td
	</tr>
HTML;
		}

		if ($tab_data && !empty($tab_data[0]->table_text)) {
			$text = $tab_data[0]->table_text;
			$rows_text = explode('|', $text);
			$rows_html = '<div>' . implode('</div><div>', $rows_text) . '</div>';

			$text_rows = '<div class="frame_table_text">' . $rows_html . '</div>';
		}

echo <<<HTML
<div class="container-kasi">
	<table class="kasi-table">
		<tr>
			<th>Каса</th>
			<th>Описание</th>
			$price_th
		</tr>
		$frame_rows
	</table>
	$text_rows
</div>
HTML;
	}
}
