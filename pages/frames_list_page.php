<?php

function frames_list_page()
{
	global $wpdb;

	// Get categories
	$categories = get_terms(array(
		'taxonomy' => 'product_cat',
		'hide_empty' => false,
		'parent' => 0
	));

	$selected_category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : '';
	$selected_frame_id = isset($_GET['frame_id']) ? intval($_GET['frame_id']) : '';
	$search_term = isset($_GET['search_term']) ? sanitize_text_field($_GET['search_term']) : '';

	$frames = array();
	if ($selected_category_id) {
		$frames = $wpdb->get_results($wpdb->prepare(
			"SELECT df.id, df.frame_image 
			FROM {$wpdb->prefix}doors_frames df 
			JOIN {$wpdb->prefix}posts p ON df.product_id = p.ID 
			JOIN {$wpdb->prefix}term_relationships tr ON p.ID = tr.object_id 
			WHERE tr.term_taxonomy_id = %d",
			$selected_category_id
		));
	}

?>
	<div class="wrap">
		<h1>Цени на каси</h1>
		<div>
			<form method="get" action="">
				<input type="hidden" name="page" value="frames-list-page">
				<div class="form-group">
					<select id="category-select" name="category_id" onchange="this.form.submit()">
						<option value=""></option>
						<?php
						foreach ($categories as $category) {
							$selected = ($category->term_id == $selected_category_id) ? ' selected' : '';
							echo '<option style="font-weight:bold" value="' . $category->term_id . '"' . $selected . '>' . $category->name . '</option>';
							$subcategories = get_terms(array(
								'taxonomy' => 'product_cat',
								'hide_empty' => false,
								'parent' => $category->term_id
							));
							foreach ($subcategories as $subcategory) {
								$selected = ($subcategory->term_id == $selected_category_id) ? ' selected' : '';
								echo '<option value="' . $subcategory->term_id . '"' . $selected . '>&nbsp;&nbsp;&nbsp;&nbsp;' . $subcategory->name . '</option>';
							}
						}
						?>
					</select>
					<?php if ($frames) : ?>
						<select id="frame-select" name="frame_id" onchange="this.form.submit()">
							<option value=""></option>
							<?php
							foreach ($frames as $frame) {
								$selected = ($frame->id == $selected_frame_id) ? ' selected' : '';
								echo '<option value="' . $frame->id . '"' . $selected . '>' . $frame->frame_image . '</option>';
							}
							?>
						</select>
					<?php endif; ?>
					<input type="text" id="search-input" name="search_term" placeholder="Търсене на продукт" value="<?php echo esc_attr($search_term); ?>" />

					<button type="submit" class="btn btn-primary">Търсене</button>
				</div>
			</form>

			<?php if ($selected_category_id || $search_term || $selected_frame_id) : ?>
				<div id="products-table" class="mt-4">
					<table class="table table-bordered table-striped">
						<thead>
							<tr>
								<th><span class="badge bg-secondary">ID</span></th>
								<th><span class="badge bg-secondary">Име</span></th>
								<th><span class="badge bg-secondary">Цена</span></th>
								<th><span class="badge bg-secondary">Промоция</span></th>
								<th><span class="badge bg-secondary">Действия</span></th>
							</tr>
						</thead>
						<tbody>
							<?php
							$args = array(
								'post_type' => 'product',
								'posts_per_page' => -1,
								's' => $search_term
							);

							if ($selected_category_id) {
								$args['tax_query'] = array(
									array(
										'taxonomy' => 'product_cat',
										'field' => 'term_id',
										'terms' => $selected_category_id
									)
								);
							}

							if ($selected_frame_id) {
								$frame_products = $wpdb->get_col($wpdb->prepare(
									"SELECT product_id 
									FROM {$wpdb->prefix}doors_frames 
									WHERE id = %d",
									$selected_frame_id
								));
								if ($frame_products) {
									$args['post__in'] = $frame_products;
								} else {
									$args['post__in'] = array(0); // No products found
								}
							}

							$query = new WP_Query($args);

							if ($query->have_posts()) {
								while ($query->have_posts()) {
									$query->the_post();
									$product = wc_get_product(get_the_ID());
									$regular_price = $product->get_regular_price();
									$sale_price = $product->get_sale_price();
									echo '<tr>';
									echo '<td>' . get_the_ID() . '</td>';
									echo '<td>' . get_the_title() . '</td>';
									echo '<td><input type="number" step="0.01" class="price-input" data-id="' . get_the_ID() . '" data-type="regular" value="' . esc_attr($regular_price) . '"></td>';
									echo '<td><input type="number" step="0.01" class="price-input" data-id="' . get_the_ID() . '" data-type="sale" value="' . esc_attr($sale_price) . '"></td>';
									echo '<td><button class="btn btn-primary open-modal" data-id="' . get_the_ID() . '">Цени на каси</button></td>';
									echo '</tr>';
								}
								wp_reset_postdata();
							} else {
								echo '<tr><td colspan="5">Няма намерени продукти.</td></tr>';
							}
							?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<!-- Modal Structure -->
	<div id="frameModal" class="modal" style="display:none;">
		<div class="modal-dialog modal-xl">
			<div class="modal-content">
				<div class="modal-header">
					<h4 class="modal-title">Цени на каси</h4>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div id="modal-body" style="max-height: calc(100vh - 200px); overflow-y: auto;">
					<!-- Dynamic content will be loaded here -->
				</div>
				<div class=" modal-footer">
					<button type="button" class="btn btn-secondary close" data-bs-dismiss="modal">Затвори</button>
					<button type="button" id="save-modal-prices" class="btn btn-primary">Запази промените</button>
				</div>
			</div>
		</div>
	</div>
<?php
}

add_action('wp_ajax_update_product_price', 'update_product_price');
function update_product_price()
{
	if (isset($_POST['product_id']) && isset($_POST['new_price']) && isset($_POST['price_type'])) {
		$product_id = intval($_POST['product_id']);
		$new_price = floatval($_POST['new_price']);
		$price_type = sanitize_text_field($_POST['price_type']);

		$product = wc_get_product($product_id);
		if ($product) {
			if ($price_type == 'regular') {
				$product->set_regular_price($new_price);
			} elseif ($price_type == 'sale') {
				$product->set_sale_price($new_price);
			}
			$product->save();
			wp_send_json_success();
		} else {
			wp_send_json_error();
		}
	} else {
		wp_send_json_error();
	}
}

add_action('wp_ajax_update_frame_prices', 'update_frame_prices');
function update_frame_prices()
{
	global $wpdb;

	if (isset($_POST['frame_id']) && isset($_POST['frame_price']) && isset($_POST['frame_promo_price'])) {
		$frame_id = intval($_POST['frame_id']);
		$frame_price = floatval($_POST['frame_price']);
		$frame_promo_price = floatval($_POST['frame_promo_price']);
		$frame_image = sanitize_text_field($_POST['frame_image']);
		$frame_description = sanitize_textarea_field($_POST['frame_description']);
		$frame_start_date = date_format(date_create_from_format('d/m/Y', sanitize_text_field($_POST['frame_start_date'])), 'Y-m-d');
		$frame_end_date = date_format(date_create_from_format('d/m/Y', sanitize_text_field($_POST['frame_end_date'])), 'Y-m-d');

		$table_name = $wpdb->prefix . 'doors_frames';

		$result = $wpdb->update(
			$table_name,
			array(
				'frame_price' => $frame_price,
				'frame_promo_price' => $frame_promo_price,
				'frame_image' => $frame_image,
				'frame_description' => $frame_description,
				'frame_start_date' => $frame_start_date,
				'frame_end_date' => $frame_end_date
			),
			array('id' => $frame_id),
			array('%f', '%f', '%s', '%s', '%s', '%s'),
			array('%d')
		);

		if ($result !== false) {
			wp_send_json_success();
		} else {
			wp_send_json_error();
		}
	} else {
		wp_send_json_error();
	}
}

add_action('wp_ajax_add_frame_prices', 'add_frame_prices');
function add_frame_prices()
{
	global $wpdb;

	if (isset($_POST['product_id']) && isset($_POST['new_frame_price']) && isset($_POST['new_frame_promo_price'])) {
		$product_id = intval($_POST['product_id']);
		$new_frame_price = floatval($_POST['new_frame_price']);
		$new_frame_promo_price = floatval($_POST['new_frame_promo_price']);
		$new_frame_image = sanitize_text_field($_POST['new_frame_image']);
		$new_frame_description = sanitize_textarea_field($_POST['new_frame_description']);
		$new_frame_start_date = date_format(date_create_from_format('d/m/Y', sanitize_text_field($_POST['new_frame_start_date'])), 'Y-m-d');
		$new_frame_end_date = date_format(date_create_from_format('d/m/Y', sanitize_text_field($_POST['new_frame_end_date'])), 'Y-m-d');

		$table_name = $wpdb->prefix . 'doors_frames';

		$result = $wpdb->insert(
			$table_name,
			array(
				'product_id' => $product_id,
				'frame_price' => $new_frame_price,
				'frame_promo_price' => $new_frame_promo_price,
				'frame_image' => $new_frame_image,
				'frame_description' => $new_frame_description,
				'frame_start_date' => $new_frame_start_date,
				'frame_end_date' => $new_frame_end_date
			),
			array('%d', '%f', '%f', '%s', '%s', '%s', '%s'),
		);

		if ($result !== false) {
			wp_send_json_success();
		} else {
			wp_send_json_error();
		}
	} else {
		wp_send_json_error();
	}
}

add_action('wp_ajax_fetch_frame_prices', 'fetch_frame_prices');
function fetch_frame_prices()
{
	global $wpdb;
	$upload_dir = wp_upload_dir();

	if (isset($_POST['product_id'])) {
		$product_id = intval($_POST['product_id']);
		$product_title = get_the_title($product_id);

		$table_name = $wpdb->prefix . 'doors_frames';
		$results = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE product_id = %d ORDER by frame_end_date DESC", $product_id));

		$html_new_table = <<<HTML
			<div class="m-1">
				<table class="table bg-info" id="new-frame-table" style="display: none;">
					<thead>
						<tbody></tbody>
					</tbody>
				</table>
				<div><button class="btn btn-success mb-3 mx-3" id="add-new-frame" data-id="$product_id">Добави нова цена на каса</button></div>
			</div>
		HTML;

		if (!empty($results)) {
			$result = $results[0];

			$html = <<<HTML
				<div class="m-1">
					<h5>$product_title</h5>
					<table class="table table-striped">
						<thead>
							<tr>
								<th>Каса</th>
								<th>Описание</th>
								<th>Цена</th>
								<th>Промоция</th>
								<th>Начална дата</th>
								<th>Крайна дата</th>
							</tr>
						</thead>
						<tbody>
			HTML;

			foreach ($results as $result) {
				$start_date_value = date_format(date_create_from_format('Y-m-d', $result->frame_start_date), 'd/m/Y');
				$end_date_value = date_format(date_create_from_format('Y-m-d', $result->frame_end_date), 'd/m/Y');
				if ($result->frame_end_date < date('Y-m-d')) {
					$expired = 'style="background: #ff000040"';
				} else {
					$expired = '';
				}
				$html .= <<<HTML
					<tr class="frame-id" $expired data-id="$result->id">
						<td class="frame-image-container">
							<img src="{$upload_dir['baseurl']}/doors_frames/$result->frame_image" class="frame-img">
							<input type="text" class="form-control frame-image" value="$result->frame_image">
						</td>
						<td><textarea class="form-control frame-description" cols="30" rows="3">$result->frame_description</textarea></td>
						<td><input type="number" step="0.01" class="form-control price-input frame-price" value="$result->frame_price"></td>
						<td><input type="number" step="0.01" class="form-control price-input frame-promo-price" value="$result->frame_promo_price"></td>
						<td><input required type="text" class="form-control datepicker-input frame-start-date" value="$start_date_value" /></td>
						<td><input required type="text" class="form-control datepicker-input frame-end-date" value="$end_date_value" /></td>
					</tr>
				HTML;
			}

			$html .= <<<HTML
					</tbody>
				</table>
			</div>
			HTML;

			$html .= $html_new_table;

			wp_send_json_success($html);
		} else {
			wp_send_json_success($html_new_table);
		}
	} else {
		wp_send_json_error();
	}
}
?>