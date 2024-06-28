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
	$selected_frame_ids = isset($_GET['frame_id']) ? $_GET['frame_id'] : false;
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
		<?php if ($selected_category_id) {
			$tabs_table = $wpdb->prefix . 'doors_frames_tabs';
			$tab_data = $wpdb->get_row($wpdb->prepare(
				"SELECT tab_title, table_text 
				FROM $tabs_table 
				WHERE category_id = %d",
				$selected_category_id
			));
		?>
			<span class="float-end">
				<button type="button" id="edit-tab" class="btn btn-secondary" title="Име на таба според категорията и описание под таблицата.">
					<?php echo ($tab_data ? 'Таб <span class="badge bg-warning text-dark">' . $tab_data->tab_title . '</span>' : 'Таб <span class="badge bg-danger">без име</span>'); ?>
				</button>
				<span id="tab-box" style="display:none; align-items: center;">
					<form class="form-floating me-2">
						<input type="text" class="form-control" id="tab-title" data-category-id="<?php echo $selected_category_id; ?>" value="<?php echo ($tab_data ? $tab_data->tab_title : ''); ?>" placeholder="Име на таба">
						<label for="tab-title">Име на таба</label>
					</form>
					<form class="form-floating me-2">
						<input type="text" class="form-control" id="table-text" value="<?php echo ($tab_data ? $tab_data->table_text : ''); ?>" placeholder="Текст под таблицата">
						<label for="table-text">Текст под таблицата</label>
					</form>
					<button type="button" id="tab-button" class="btn btn-primary">Запази</button>
				</span>
			</span>
		<?php } ?>
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
						<select id="frame-select" class="slim-select" name="frame_id[]" multiple>
							<optgroup data-selectall="true" data-selectalltext="Всички цени">
								<?php
								foreach ($frames as $frame) {
									if ($frame->frame_id > 0) {
										$selected = (is_array($selected_frame_ids) && in_array($frame->frame_id, $selected_frame_ids)) ? ' selected' : '';
										echo '<option value="' . $frame->frame_id . '"' . $selected . '>Цена ' . $frame->frame_id . '</option>';
									}
								}
								?>
							</optgroup>
						</select>
					<?php endif; ?>
					<input type="text" id="search-input" name="search_term" placeholder="Търсене на продукт" value="<?php echo esc_attr($search_term); ?>" />

					<button type="submit" class="btn btn-primary">Търсене</button>
				</div>
			</form>

			<?php if ($selected_category_id || $search_term) : ?>

				<?php if ($selected_frame_ids) : ?>
					<!-- Mass Insert Form -->
					<form id="mass-insert-form" class="mt-2">
						<span id="mass-insert-span">
							<div><b>Масова промяна <span class="badge bg-warning text-dark" title="Ако в даден артикул има цени на каси с различни крайни дати, масовата промяна ще се изпълни само за най-новата дата.&#013;&#013;Има два варианта за промяна на цени - редактиране на текущите или създаване на нови:&#013;   1. Ако е маркирана отметката 'Редактирай цените', тогава ще бъдат редактирани цените с най-новата крайна дата.&#013;   2. Ако са въведени дати в полетата 'От дата' и 'До дата', тогава ще бъдат създадени нови цени на каси с избраните дати, а старите ще бъдат с валидност до начална дата на новите цени.">?</span></b></div>
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
						<button type="button" id="check-mass-insert" class="btn btn-warning">Промени</button>
						<button type="button" id="apply-mass-insert" class="btn btn-success" style="display:none">Запази</button>
					</form>
				<?php endif; ?>

				<div id="products-table" class="mt-4">
					<table class="table table-bordered">
						<thead>
							<tr class="table-light">
								<th><span class="badge bg-secondary">ID</span></th>
								<th><span class="badge bg-secondary">Име</span></th>
								<th><span class="badge bg-secondary">Цена</span></th>
								<th><span class="badge bg-secondary">Промоция</span></th>
								<?php if ($selected_frame_ids) : ?>
									<th><span class="badge bg-secondary">Цена №</span></th>
									<th><span class="badge bg-secondary">Каса</span></th>
									<th><span class="badge bg-secondary">Описание каса</span></th>
									<th><span class="badge bg-secondary">Цена каса</span></th>
									<th><span class="badge bg-secondary">Промо каса</span></th>
									<th><span class="badge bg-secondary">До дата</span></th>
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

							if ($selected_frame_ids) {
								$frame_products = $wpdb->get_col($wpdb->prepare(
									"SELECT product_id 
									FROM {$wpdb->prefix}doors_frames 
									WHERE frame_id IN (%s)",
									implode(',', $selected_frame_ids)
								));
								if ($frame_products) {
									$args['post__in'] = $frame_products;
								} else {
									$args['post__in'] = array(0);
								}
							}

							$query = new WP_Query($args);

							if ($query->have_posts()) {
								while ($query->have_posts()) {
									$query->the_post();
									$product = wc_get_product(get_the_ID());
									$regular_price = $product->get_regular_price();
									$sale_price = $product->get_sale_price();

									if ($selected_frame_ids) {
										$frame_ids_placeholder = implode(',', array_fill(0, count($selected_frame_ids), '%d'));

										$sql = $wpdb->prepare(
											"SELECT frame_id, frame_description, frame_image, frame_price, frame_promo_price, frame_end_date
											FROM {$wpdb->prefix}doors_frames 
											WHERE product_id = %d AND frame_id IN ($frame_ids_placeholder) AND frame_end_date >= CURDATE()
											ORDER BY frame_end_date DESC, frame_id ASC",
											array_merge([get_the_ID()], $selected_frame_ids)
										);

										$frame_data_list = $wpdb->get_results($sql);
									}

									if (isset($frame_data_list) && is_array($frame_data_list)) {
										$rowspan = count($frame_data_list);
									} else {
										$rowspan = 1;
									}

									if (isset($_GET['frame_id']) && count($_GET['frame_id']) > 1) {
										$product_row_class = 'product-row';
									} else {
										$product_row_class = '';
									}

									echo '<tr class="table-secondary">';
									echo '<td rowspan="' . $rowspan . '" class="' . $product_row_class . '">' . get_the_ID() . '</td>';
									echo '<td rowspan="' . $rowspan . '" class="' . $product_row_class . '"><a target="_blank" href="' . get_the_permalink() . '">' . get_the_title() . '</a></td>';

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

										echo '<td rowspan="' . $rowspan . '" class="' . $product_row_class . '">' . $min_regular_price . '</td>';
										echo '<td rowspan="' . $rowspan . '" class="' . $product_row_class . '">' . $min_sale_price . '</td>';
									} else {
										echo '<td rowspan="' . $rowspan . '" class="' . $product_row_class . '"><input type="number" step="0.01" class="price-inputs" data-id="' . get_the_ID() . '" data-type="regular" value="' . esc_attr($regular_price) . '"></td>';
										echo '<td rowspan="' . $rowspan . '" class="' . $product_row_class . '"><input type="number" step="0.01" class="price-inputs" data-id="' . get_the_ID() . '" data-type="sale" value="' . esc_attr($sale_price) . '"></td>';
									}

									$modal_button = '<button class="btn btn-primary open-modal" data-id="' . get_the_ID() . '">Цени на каси</button>';

									if (isset($frame_data_list) && is_array($frame_data_list)) {
										$first_frame = true;
										$last_date = '';
										$change_price = 'true';
										foreach ($frame_data_list as $frame_data) {
											if (!$first_frame) {
												echo '<tr>';
											}

											if (isset($frame_data->frame_end_date) && $frame_data->frame_end_date < date('Y-m-d')) {
												$expired_frame = "expired-date";
											} else {
												$expired_frame = "not-expired-date";
											}

											if (!empty($last_date) && $last_date != $frame_data->frame_end_date) {
												$change_price = 'false';
											}

											if ($first_frame && isset($_GET['frame_id']) && count($_GET['frame_id']) > 1) {
												$product_row_class = 'product-row';
											} else {
												$product_row_class = '';
											}

											$expired_date = date('d.m.Y', strtotime($frame_data->frame_end_date));
											echo '<td class="' . $expired_frame . ' ' . $product_row_class . '">' . $frame_data->frame_id . '</td>';
											echo '<td class="' . $expired_frame . ' ' . $product_row_class . '"><img src="' . $upload_dir['baseurl'] . '/doors_frames/' . $frame_data->frame_image . '" style="max-height: 38px"></td>';
											echo '<td class="' . $expired_frame . ' ' . $product_row_class . '">' . $frame_data->frame_description . '</td>';
											echo '<td class="frame-table-price ' . $expired_frame . ' ' . $product_row_class . '" data-end-date="' . $frame_data->frame_end_date . '" data-change-price="' . $change_price . '">' . $frame_data->frame_price . '</td>';
											echo '<td class="frame-table-promo ' . $expired_frame . ' ' . $product_row_class . '" data-end-date="' . $frame_data->frame_end_date . '" data-price = "' . $frame_data->frame_price . '" data-change-price="' . $change_price . '">' . $frame_data->frame_promo_price . '</td>';
											echo '<td class="' . $expired_frame . ' ' . $product_row_class . '">' . $expired_date . '</td>';

											if ($first_frame) {
												echo '<td rowspan="' . $rowspan . '" class="' . $product_row_class . '">' . $modal_button . '</td>';
												$first_frame = false;
											}

											echo '</tr>';

											$last_date = $frame_data->frame_end_date;
										}
									} else {
										echo '<td class="' . $product_row_class . '">' . $modal_button . '</td>';
									}
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

add_action('wp_ajax_update_tab', 'update_tab');
function update_tab()
{
	global $wpdb;
	$tabs_table = $wpdb->prefix . 'doors_frames_tabs';

	if (isset($_POST['tab_text'])) {
		$category_id = intval($_POST['category_id']);
		$tab_title = sanitize_text_field($_POST['tab_text']);
		$table_text = sanitize_text_field($_POST['table_text']);

		$existing_tab = $wpdb->get_row($wpdb->prepare("SELECT id FROM $tabs_table WHERE category_id = %d", $category_id));

		if ($existing_tab) {
			$wpdb->update(
				$tabs_table,
				[
					'tab_title' => $tab_title,
					'table_text' => $table_text,
				],
				['category_id' => $category_id]
			);
		} else {
			$wpdb->insert(
				$tabs_table,
				[
					'category_id' => $category_id,
					'tab_title' => $tab_title,
					'table_text' => $table_text,
				]
			);
		}

		wp_send_json_success();
	} else {
		wp_send_json_error();
	}
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

	if (isset($_POST['id']) && isset($_POST['frame_id'])) {
		$table_name = $wpdb->prefix . 'doors_frames';

		$id = intval($_POST['id']);
		$frame_id = intval($_POST['frame_id']);
		$frame_image = sanitize_text_field($_POST['frame_image']);
		$frame_description = sanitize_textarea_field($_POST['frame_description']);

		$delete_frame = sanitize_text_field($_POST['delete_frame']);

		if ($delete_frame == 'true') {
			$result = $wpdb->delete(
				$table_name,
				array('id' => $id),
				array('%d')
			);
			if ($result !== false) {
				wp_send_json_success();
			} else {
				wp_send_json_error();
			}
		}

		if ($frame_id > 0) {
			$frame_price = floatval($_POST['frame_price']);
			$frame_promo_price = floatval($_POST['frame_promo_price']);
			$frame_start_date = date_format(date_create_from_format('d/m/Y', sanitize_text_field($_POST['frame_start_date'])), 'Y-m-d');
			$frame_end_date = date_format(date_create_from_format('d/m/Y', sanitize_text_field($_POST['frame_end_date'])), 'Y-m-d');

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
		} else {
			$result = $wpdb->update(
				$table_name,
				array(
					'frame_id' => $frame_id,
					'frame_image' => $frame_image,
					'frame_description' => $frame_description
				),
				array('id' => $id),
				array('%d', '%s', '%s'),
				array('%d')
			);
		}

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
		$new_frame_image = sanitize_text_field($_POST['new_frame_image']);
		$new_frame_description = sanitize_textarea_field($_POST['new_frame_description']);

		$table_name = $wpdb->prefix . 'doors_frames';

		if ($frame_id > 0) {
			$new_frame_price = floatval($_POST['new_frame_price']);
			$new_frame_promo_price = floatval($_POST['new_frame_promo_price']);
			$new_frame_start_date = date_format(date_create_from_format('d/m/Y', sanitize_text_field($_POST['new_frame_start_date'])), 'Y-m-d');
			$new_frame_end_date = date_format(date_create_from_format('d/m/Y', sanitize_text_field($_POST['new_frame_end_date'])), 'Y-m-d');

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
		} else {
			$result = $wpdb->insert(
				$table_name,
				array(
					'product_id' => $product_id,
					'frame_id' => $frame_id,
					'frame_image' => $new_frame_image,
					'frame_description' => $new_frame_description
				),
				array('%d', '%d', '%s', '%s'),
			);
		}

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

		$folderPath = "{$upload_dir['basedir']}/doors_frames";
		$images = glob($folderPath . '/*.{jpg,png}', GLOB_BRACE);
		$imageFiles = array_map('basename', $images);
		natsort($imageFiles);

		function imageOptions($imageFiles, $selected)
		{
			$image_options = '';
			foreach ($imageFiles as $image) {
				$image_options .= "<option value='$image' " . ($image == $selected ? 'selected' : '') . ">$image</option>";
			}

			return $image_options;
		}

		function blankImageOptions($imageFiles)
		{
			$image_options = '';
			foreach ($imageFiles as $image) {
				$image_options .= "<option value='$image'>$image</option>";
			}

			return $image_options;
		}
		$blankImageOptions = blankImageOptions($imageFiles);

		$table_name = $wpdb->prefix . 'doors_frames';
		$results = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE product_id = %d ORDER by frame_end_date IS NULL DESC, frame_end_date DESC, frame_id ASC", $product_id));

		$html_product_title = "<h5 id='product-title' class='text-center' data-static-images-path='{$upload_dir['baseurl']}/doors_frames/'><mark>$product_title</mark></h5>";

		$html_new_table = <<<HTML
			<div id="all-frame-images" class="m-1" data-frame-options="$blankImageOptions">
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
								<th>Изтрий</th>
							</tr>
						</thead>
						<tbody>
			HTML;

			$show_prices = '';
			foreach ($results as $result) {
				if ($result->frame_start_date && $result->frame_end_date) {
					$start_date_value = date_format(date_create_from_format('Y-m-d', $result->frame_start_date), 'd/m/Y');
					$end_date_value = date_format(date_create_from_format('Y-m-d', $result->frame_end_date), 'd/m/Y');
					$show_prices = <<<HTML
						<td><input type="number" step="0.01" class="form-control price-input frame-price" value="$result->frame_price"></td>
						<td><input type="number" step="0.01" class="form-control price-input frame-promo-price" value="$result->frame_promo_price"></td>
						<td><input required type="text" class="form-control datepicker-input frame-start-date" value="$start_date_value" /></td>
						<td><input required type="text" class="form-control datepicker-input frame-end-date" value="$end_date_value" /></td>
					HTML;
				} else {
					$product = wc_get_product($result->product_id);
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
					$show_prices = "<td>" . $frame_price . "</td><td>" . $frame_promo_price . "</td><td></td><td></td>";
				}

				if ($result->frame_end_date && $result->frame_end_date < date('Y-m-d')) {
					$expired = 'expired-date';
				} else {
					$expired = '';
				}

				$image_options = imageOptions($imageFiles, $result->frame_image);

				$frame_id_options = '';
				for ($i = -5; $i <= 15; $i++) {
					if ($i > 0) {
						$frame_id_options .= "<option value='$i' " . ($i == $result->frame_id ? 'selected' : '') . ">$i</option>";
					}
				}
				if ($result->frame_id == -5) {
					$frame_id_options = "<option value='-5' selected>Основна цена</option>";
				}

				$html .= <<<HTML
					<tr class="frame-id $expired" data-id="$result->id">
						<td>
							<select class="form-control price-input frame-id">
								$frame_id_options
							</select>
						</td>
						<td class="frame-image-container">
							<img id="frame-img-$result->id" src="{$upload_dir['baseurl']}/doors_frames/$result->frame_image" class="frame-img">
							<select class="form-control frame-image change-frame-image" data-image-id="frame-img-$result->id">
								$image_options
							</select>
						</td>
						<td><textarea class="form-control frame-description" cols="30" rows="3">$result->frame_description</textarea></td>
						$show_prices
						<td><input type="checkbox" class="form-control delete-frame"></td>
					</tr>
				HTML;
			}

			$html .= <<<HTML
					</tbody>
				</table>
			</div>
			HTML;

			$html .= $html_new_table;

			wp_send_json_success($html_product_title . $html);
		} else {
			wp_send_json_success($html_product_title . $html_new_table);
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
		$frame_ids =
			implode(',', array_fill(0, count($_POST['frame_ids']), '%d'));
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
			$max_date_sql = $wpdb->prepare(
				"SELECT MAX(frame_end_date) FROM {$table_name} WHERE product_id = %d AND frame_id IN ($frame_ids) AND frame_end_date >= CURDATE()",
				array_merge([$product_id], $_POST['frame_ids'])
			);
			$max_date = $wpdb->get_var($max_date_sql);

			$current_values_sql = $wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE product_id = %d AND frame_id IN ($frame_ids) AND frame_end_date = %s",
				array_merge([$product_id], $_POST['frame_ids'], [$max_date])
			);
			$current_values = $wpdb->get_results($current_values_sql);

			foreach ($current_values as $current_value) {

				$new_price = calculate_new_value($current_value->frame_price, $operator_price, $sum_price, $prices_round);
				$new_promo_price = calculate_new_value($current_value->frame_promo_price, $operator_promotion, $sum_promotion, $prices_round, $prices_to_promo, $current_value->frame_price);

				if ($edit_query) {
					$update_query = $wpdb->prepare(
						"UPDATE $table_name 
					SET 
						frame_price = %f,
						frame_promo_price = %f 
					WHERE 
						id = %d",
						$new_price >= 0 ? $new_price : $current_value->frame_price,
						$new_promo_price >= 0 ? $new_promo_price : $current_value->frame_promo_price,
						$current_value->id
					);

					$wpdb->query($update_query);
				} else {
					$update_query = $wpdb->prepare(
						"UPDATE $table_name 
					SET 
						frame_end_date = %s
					WHERE 
						id = %d",
						$start_date,
						$current_value->id
					);
					$wpdb->query($update_query);

					$wpdb->insert(
						$table_name,
						array(
							'product_id' => $product_id,
							'frame_id' => $current_value->frame_id,
							'frame_price' => $new_price >= 0 ? $new_price : $current_value->frame_price,
							'frame_promo_price' => $new_promo_price >= 0 ? $new_promo_price : $current_value->frame_promo_price,
							'frame_image' => $current_value->frame_image,
							'frame_description' => $current_value->frame_description,
							'frame_start_date' => $start_date,
							'frame_end_date' => $end_date
						),
						array('%d', '%d', '%f', '%f', '%s', '%s', '%s', '%s')
					);
				}
			}
		}

		wp_send_json_success();
	} else {
		wp_send_json_error();
	}
}

function calculate_new_value($current_value, $operator, $sum, $round, $to_promo = 'false', $to_promo_value = 0)
{
	if ($round === 'true') {
		$round = true;
	}

	if ($to_promo === 'true') {
		$current_value = $to_promo_value;
	}

	switch ($operator) {
		case '+':
			$total = $current_value + $sum;
			break;
		case '-':
			$total =  $current_value - $sum;
			break;
		case '+%':
			$total =  $current_value + ($current_value * $sum / 100);
			break;
		case '-%':
			$total =  $current_value - ($current_value * $sum / 100);
			break;
		case '=':
			$total =  $sum;
			break;
		default:
			$total =  $current_value;
			break;
	}

	if ($round === true) {
		return round($total);
	}

	return $total;
}
?>