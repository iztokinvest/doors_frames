<?php

function frames_list_page()
{
	global $wpdb;

	// Get categories and subcategories
	$categories = get_terms(array(
		'taxonomy' => 'product_cat',
		'hide_empty' => false,
		'parent' => 0
	));

	$selected_category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : '';
	$search_term = isset($_GET['search_term']) ? sanitize_text_field($_GET['search_term']) : '';

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
							// Add an option for the base category
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
							echo '</optgroup>';
						}
						?>
					</select>
					<input type="text" id="search-input" name="search_term" placeholder="Търсене на продукт" value="<?php echo esc_attr($search_term); ?>" />

					<button type="submit" class="btn btn-primary">Търсене</button>
				</div>
			</form>

			<?php if ($selected_category_id || $search_term) : ?>
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
							// Fetch products based on selected category and search term
							$args = array(
								'post_type' => 'product',
								'posts_per_page' => -1,
								's' => $search_term
							);

							// Add tax_query if a category is selected
							if ($selected_category_id) {
								$args['tax_query'] = array(
									array(
										'taxonomy' => 'product_cat',
										'field' => 'term_id',
										'terms' => $selected_category_id
									)
								);
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
								echo '<tr><td colspan="4">Няма намерени продукти.</td></tr>';
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
		<div class="modal-dialog modal-dialog-scrollable modal-xl">
			<div class="modal-content">
				<div class="modal-header">
					<h4 class="modal-title">Цени на каси</h4>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div id="modal-body">
					<!-- Dynamic content will be loaded here -->
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary close" data-bs-dismiss="modal">Затвори</button>
					<button type="button" id="save-modal-prices" class="btn btn-primary">Запази промените</button>
				</div>
			</div>
		</div>


	</div>
<?php
}

// Add AJAX actions
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
		$frame_start_date = sanitize_text_field($_POST['frame_start_date']);
		$frame_end_date = sanitize_text_field($_POST['frame_end_date']);

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
			array('%f', '%f', '%s', '%s' , '%s', '%s'),
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

add_action('wp_ajax_fetch_frame_prices', 'fetch_frame_prices');
function fetch_frame_prices()
{
	global $wpdb;

	if (isset($_POST['product_id'])) {
		$product_id = intval($_POST['product_id']);
		$product_title = get_the_title($product_id);

		$table_name = $wpdb->prefix . 'doors_frames';
		$results = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE product_id = %d", $product_id));

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
				$html .= <<<HTML
					<tr class="frame-id" data-id="$result->id">
						<td><input type="text" class="form-control frame-image" value="$result->frame_image"></td>
						<td><textarea class="form-control frame-description">$result->frame_description</textarea></td>
						<td><input type="number" step="0.01" class="form-control frame-price" value="$result->frame_price"></td>
						<td><input type="number" step="0.01" class="form-control frame-promo-price" value="$result->frame_promo_price"></td>
						<td><input required type="text" class="form-control datepicker-input frame-start-date" /></td>
						<td><input required type="text" class="form-control datepicker-input frame-end-date" /></td>
					</tr>
				HTML;
			}

			$html .= <<<HTML
				</tbody>
					</table>
			</div>
			HTML;

			wp_send_json_success($html);
		} else {
			wp_send_json_error();
		}
	} else {
		wp_send_json_error();
	}
}
?>