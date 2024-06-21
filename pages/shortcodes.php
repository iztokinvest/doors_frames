<?php

global $product_frames; // Declare the global variable to store the frames

add_filter('woocommerce_product_tabs', 'custom_product_tab');
function custom_product_tab($tabs)
{
	global $wpdb, $product, $product_frames;

	// Single SQL query to check for frames and retrieve them
	$table_name = $wpdb->prefix . 'doors_frames';
	$product_frames = $wpdb->get_results($wpdb->prepare(
		"SELECT * 
			FROM $table_name 
			WHERE product_id = %d",
		$product->get_id()
	));

	// Add the new tab only if there are frames
	if (!empty($product_frames)) {
		$tabs['custom_tab'] = array(
			'title'    => __('Custom Tab', 'your-text-domain'),
			'priority' => 50,
			'callback' => 'custom_product_tab_content'
		);
	}

	return $tabs;
}

function custom_product_tab_content()
{
	global $product_frames;

	$frame_rows = '';
	foreach ($product_frames as $frame) {
		$image = $frame->frame_image;
		$description = $frame->frame_description;
		$price = floatval($frame->frame_price) > 0 ? $frame->frame_price : '';
		$promo_price = '';

		if (floatval($frame->frame_promo_price) > 0) {
			$promo_price = $frame->frame_promo_price;
			$price = "<del>$price</del>";
		}

		$upload_dir = wp_upload_dir();
		$folderPath = "{$upload_dir['baseurl']}/doors_frames";

		$frame_rows .= <<<HTML
			<tr class="frame_table_row">
				<td>
					<div class="frame_image"><img src='$folderPath/$image'></div>
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
					<th>Каса</th>
					<th>Описание</th>
					<th>Цена</th>
				</tr>
			</thead>
			<tbody class="frame_table_body">
				$frame_rows
			</tbody>
		</table>
	HTML;
}
