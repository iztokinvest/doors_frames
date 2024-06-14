<?php

function frames_list_page()
{
	global $wpdb;
	$upload_dir = wp_upload_dir();

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
			"SELECT df.id, df.frame_id 
			FROM {$wpdb->prefix}doors_frames df 
			JOIN {$wpdb->prefix}posts p ON df.product_id = p.ID 
			JOIN {$wpdb->prefix}term_relationships tr ON p.ID = tr.object_id 
			WHERE tr.term_taxonomy_id = %d
			GROUP BY df.frame_id
			ORDER BY df.frame_id ASC",
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
								$selected = ($frame->frame_id == $selected_frame_id) ? ' selected' : '';
								echo '<option value="' . $frame->frame_id . '"' . $selected . '>Цена ' . $frame->frame_id . '</option>';
							}
							?>
						</select>
					<?php endif; ?>
					<input type="text" id="search-input" name="search_term" placeholder="Търсене на продукт" value="<?php echo esc_attr($search_term); ?>" />

					<button type="submit" class="btn btn-primary">Търсене</button>
				</div>
			</form>

			<?php if ($selected_category_id || $search_term) : ?>

				<?php if ($selected_frame_id) : ?>
					<!-- Mass Insert Form -->
					<hr>
					<form id="mass-insert-form" class="mt-2">
						<span id="mass-insert-span">
							<span class="badge bg-info">
								<select id="operator-price-select" class="operator-price-select" name="operator_price">
									<option value="+">+</option>
									<option value="-">-</option>
									<option value="+%">+%</option>
									<option value="-%">-%</option>
									<option value="=">=</option>
								</select>
								<input type="number" step="0.01" id="sum-price-input" class="price-input" name="sum_price" placeholder="Цена" required />
							</span>
							<span class="badge bg-info">
								<select id="operator-promotion-select" class="operator-promotion-select" name="operator_promotion">
									<option value="+">+</option>
									<option value="-">-</option>
									<option value="+%">+%</option>
									<option value="-%">-%</option>
									<option value="=">=</option>
								</select>
								<input type="number" step="0.01" id="sum-promotion-input" class="price-input" name="sum_promotion" placeholder="Промо" required />
							</span>
							<span id="mass-dates" class="badge bg-info text-dark">
								<input required type="text" id="mass-start-date" class="datepicker-input frame-start-date d-inline" placeholder="От дата" />
								<input required type="text" id="mass-end-date" class="datepicker-input frame-start-date d-inline" placeholder="До дата" />
							</span>
							<span class="badge bg-info text-dark checkbox-badge">
								<input type="checkbox" id="mass-edit-prices" />Редактирай цените
							</span>
							<span class="badge bg-info text-dark checkbox-badge">
								<input type="checkbox" id="mass-round-prices" />Закръгли
							</span>
							<span class="badge bg-info text-dark checkbox-badge">
								<input type="checkbox" id="mass-prices-to-promo" />Цена към промо
							</span>
						</span>
						<button type="button" id="check-mass-insert" class="btn btn-warning">Провери</button>
						<button type="button" id="apply-mass-insert" class="btn btn-success" style="display:none">Потвърди</button>
					</form>
				<?php endif; ?>

				<div id="products-table" class="mt-4">
					<table class="table table-bordered table-striped">
						<thead>
							<tr>
								<th><span class="badge bg-secondary">ID</span></th>
								<th><span class="badge bg-secondary">Име</span></th>
								<th><span class="badge bg-secondary">Цена</span></th>
								<th><span class="badge bg-secondary">Промоция</span></th>
								<?php if ($selected_frame_id) : ?>
									<th><span class="badge bg-secondary">Каса</span></th>
									<th><span class="badge bg-secondary">Описание каса</span></th>
									<th><span class="badge bg-secondary">Цена каса</span></th>
									<th><span class="badge bg-secondary">Промо каса</span></th>
								<?php endif; ?>
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
									WHERE frame_id = %d",
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

									if ($selected_frame_id) {
										$frame_data = $wpdb->get_row($wpdb->prepare(
											"SELECT frame_description, frame_image, frame_price, frame_promo_price, frame_end_date
											FROM {$wpdb->prefix}doors_frames 
											WHERE product_id = %d AND frame_id = '%d'
											ORDER BY frame_end_date DESC LIMIT 1",
											get_the_ID(),
											$selected_frame_id
										));
									}

									echo '<tr>';
									echo '<td>' . get_the_ID() . '</td>';
									echo '<td>' . get_the_title() . '</td>';
									echo '<td><input type="number" step="0.01" class="price-inputs" data-id="' . get_the_ID() . '" data-type="regular" value="' . esc_attr($regular_price) . '"></td>';
									echo '<td><input type="number" step="0.01" class="price-inputs" data-id="' . get_the_ID() . '" data-type="sale" value="' . esc_attr($sale_price) . '"></td>';
									if ($selected_frame_id) {
										echo '<td><img src="' . $upload_dir['baseurl'] . '/doors_frames/' . $frame_data->frame_image . '" style="max-height: 38px"></td>';
										echo '<td>' . $frame_data->frame_description . '</td>';
										echo '<td class="frame-table-price" data-end-date="' . $frame_data->frame_end_date . '">' . $frame_data->frame_price . '</td>';
										echo '<td class="frame-table-promo" data-end-date="' . $frame_data->frame_end_date . '">' . $frame_data->frame_promo_price . '</td>';
									}
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

	if (isset($_POST['id']) && isset($_POST['frame_price']) && isset($_POST['frame_promo_price'])) {
		$id = intval($_POST['id']);
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
				'frame_id' => $frame_id,
				'frame_price' => $frame_price,
				'frame_promo_price' => $frame_promo_price,
				'frame_image' => $frame_image,
				'frame_description' => $frame_description,
				'frame_start_date' => $frame_start_date,
				'frame_end_date' => $frame_end_date
			),
			array('id' => $id),
			array('%d', '%f', '%f', '%s', '%s', '%s', '%s'),
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
		$frame_id = intval($_POST['frame_id']);
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
				'frame_id' => $frame_id,
				'frame_price' => $new_frame_price,
				'frame_promo_price' => $new_frame_promo_price,
				'frame_image' => $new_frame_image,
				'frame_description' => $new_frame_description,
				'frame_start_date' => $new_frame_start_date,
				'frame_end_date' => $new_frame_end_date
			),
			array('%d', '%d', '%f', '%f', '%s', '%s', '%s', '%s'),
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
		$results = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE product_id = %d ORDER by frame_end_date DESC, frame_id ASC", $product_id));

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
								<th>Цена №</th>
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
						<td><input type="number" step="1" class="form-control price-input frame-id" value="$result->frame_id"></td>
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

add_action('wp_ajax_mass_insert_frames', 'mass_insert_frames');
function mass_insert_frames()
{
	global $wpdb;

	if (isset($_POST['product_ids']) && isset($_POST['operator_price']) && isset($_POST['operator_promotion'])) {
		$frame_id = intval($_POST['frame_id']);
		$product_ids = array_map('intval', $_POST['product_ids']);
		$operator_price = sanitize_text_field($_POST['operator_price']);
		$sum_price = floatval($_POST['sum_price']);
		$operator_promotion = sanitize_text_field($_POST['operator_promotion']);
		$sum_promotion = floatval($_POST['sum_promotion']);
		$edit_query = true;
		if (!empty($_POST['start_date']) && !empty($_POST['end_date'])) {
			$start_date = date_format(date_create_from_format('d/m/Y', sanitize_text_field($_POST['start_date'])), 'Y-m-d');
			$end_date = date_format(date_create_from_format('d/m/Y', sanitize_text_field($_POST['end_date'])), 'Y-m-d');
			$edit_query = false;
		}
		$prices_round = $_POST['prices_round'];
		$prices_to_promo = $_POST['prices_to_promo'];

		$table_name = $wpdb->prefix . 'doors_frames';

		foreach ($product_ids as $product_id) {
			$current_values = $wpdb->get_results($wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE product_id = %d AND frame_id = %d ORDER BY frame_end_date DESC LIMIT 1",
				$product_id, $frame_id
			));

			$new_price = calculate_new_value($current_values[0]->frame_price, $operator_price, $sum_price);
			$new_promo_price = calculate_new_value($current_values[0]->frame_promo_price, $operator_promotion, $sum_promotion);

			if ($edit_query) {
				$update_query = $wpdb->prepare(
					"UPDATE $table_name 
					SET 
						frame_price = %f,
						frame_promo_price = %f 
					WHERE 
						frame_id = %d
						AND product_id = %d 
						AND frame_end_date > %s",
					$new_price >= 0 ? $new_price : $current_values[0]->frame_price,
					$new_promo_price >= 0 ? $new_promo_price : $current_values[0]->frame_promo_price,
					$frame_id,
					$product_id,
					date('Y-m-d')
				);

				$wpdb->query($update_query);
			} else {
				$wpdb->insert(
					$table_name,
					array(
						'product_id' => $product_id,
						'frame_id' => $frame_id,
						'frame_price' => $new_price >= 0 ? $new_price : $current_values[0]->frame_price,
						'frame_promo_price' => $new_promo_price >= 0 ? $new_promo_price : $current_values[0]->frame_promo_price,
						'frame_image' => $current_values[0]->frame_image,
						'frame_description' => $current_values[0]->frame_description,
						'frame_start_date' => $start_date,
						'frame_end_date' => $end_date
					),
					array('%d', '%d', '%f', '%f', '%s', '%s', '%s', '%s')
				);
			}
		}

		wp_send_json_success();
	} else {
		wp_send_json_error();
	}
}

function calculate_new_value($current_value, $operator, $sum)
{
	switch ($operator) {
		case '+':
			return $current_value + $sum;
		case '-':
			return $current_value - $sum;
		case '+%':
			return $current_value + ($current_value * $sum / 100);
		case '-%':
			return $current_value - ($current_value * $sum / 100);
		case '=':
			return $sum;
		default:
			return $current_value;
	}
}
?>