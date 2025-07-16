<?php
session_start();

function frames_list_page()
{
	global $wpdb;
	$upload_dir = wp_upload_dir();

	// Get categories
	$categories = get_terms(array(
		'taxonomy' => 'product_cat',
		'hide_empty' => isset($_GET['filters']) && in_array('drafts', $_GET['filters']) ? false : true,
		'parent' => 0
	));

	$selected_category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : '';
	$selected_frame_ids = isset($_GET['frame_id']) ? $_GET['frame_id'] : false;

	$frames = array();
	if ($selected_category_id) {

		$category = get_term($selected_category_id, 'product_cat');

		$frames = $wpdb->get_results($wpdb->prepare(
			"SELECT df.id, df.frame_id 
            FROM {$wpdb->prefix}doors_frames df
            JOIN {$wpdb->prefix}posts p ON df.product_id = p.ID 
            JOIN {$wpdb->prefix}term_relationships tr ON p.ID = tr.object_id 
            JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id 
            JOIN {$wpdb->prefix}terms t ON tt.term_id = t.term_id
            WHERE tt.taxonomy = 'product_cat'
            AND t.name = %s
            GROUP BY df.frame_id
            ORDER BY df.frame_id ASC",
			$category->name
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
				<select id="search-type" style="display: none;">
					<option value="include">цялото име</option>
					<option value="starts">началото на името</option>
					<option value="ends">края на името</option>
				</select>
				<input type="text" id="search-input" placeholder="Търсене на продукт">
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
			<form method="get" id="chose-frames" action="">
				<input type="hidden" name="page" value="frames-list-page">
				<div class="form-group">
					<select id="category-select" name="category_id">
						<option value=""></option>
						<?php
						foreach ($categories as $category) {
							$selected = ($category->term_id == $selected_category_id) ? ' selected' : '';
							echo '<option style="font-weight:bold" value="' . $category->term_id . '"' . $selected . '>' . $category->name . ' (' . $category->count . ')</option>';
							$subcategories = get_terms(array(
								'taxonomy' => 'product_cat',
								'hide_empty' => isset($_GET['filters']) && in_array('drafts', $_GET['filters']) ? false : true,
								'parent' => $category->term_id
							));
							foreach ($subcategories as $subcategory) {
								$selected = ($subcategory->term_id == $selected_category_id) ? ' selected' : '';
								echo '<option value="' . $subcategory->term_id . '"' . $selected . '>&nbsp;&nbsp;&nbsp;&nbsp;' . $subcategory->name . ' (' . $subcategory->count . ')</option>';
							}
						}
						?>
					</select>
					<select id="filters-select" class="slim-select" name="filters[]" style="min-width:120px" multiple>
						<option value="drafts" data-mandatory="true" <?php echo (isset($_GET['filters']) && in_array('drafts', $_GET['filters'])) ? ' selected' : ''; ?>>Чернови</option>
					</select>
					<?php if ($frames) : ?>
						<span class="badge text-dark" style="background-color: #e2e3e5;">
							<span id=" frame-prices-select">
								Каси №
								<select id="frame-select" class="slim-select" name="frame_id[]" style="min-width:120px" multiple>
									<optgroup data-selectall="true" data-selectalltext="Всички цени">
										<?php
										foreach ($frames as $frame) {
											$selected = (is_array($selected_frame_ids) && in_array($frame->frame_id, $selected_frame_ids)) ? ' selected' : '';
											if ($frame->frame_id == -5) {
												echo '<option value="' . $frame->frame_id . '"' . $selected . ' data-mandatory="true">Основна</option>';
											} else {
												echo '<option value="' . $frame->frame_id . '"' . $selected . ' data-mandatory="true">' . $frame->frame_id . '</option>';
											}
										}
										?>
									</optgroup>
								</select>
							</span>
						</span>
					<?php endif; ?>
					<?php if ($selected_frame_ids) : ?>
						<select id="active-select" name="active" onchange="this.form.submit()">
							<option value="1" <?php echo ((isset($_GET['active']) && $_GET['active'] == 1) || !isset($_GET['active'])) ? ' selected' : ''; ?>>Активни каси</option>
							<option value="0" <?php echo (isset($_GET['active']) && $_GET['active'] == 0) ? ' selected' : ''; ?>>Неактивни каси</option>
							<option value="2" <?php echo (isset($_GET['active']) && $_GET['active'] == 2) ? ' selected' : ''; ?>>Всички каси</option>
						</select>
					<?php endif; ?>
				</div>
			</form>

			<?php if ($selected_category_id) : ?>

				<?php
				if ($selected_frame_ids) {
					$mass_title = <<<HTML
	<div id="mass-frame-title"><b>Масова промяна на цени на каси <span class="badge bg-warning text-dark" title="От тук се променят масово цените на избраните каси.&#013;&#013;Има два варианта за промяна на цени - редактиране на текущите или създаване на нови за по-късно:&#013;   1. Ако е маркирана отметката 'Промени текущите цени', тогава директно ще бъдат заменени цените на касите в момента.&#013;   2. Ако отметката не е маркирана, тогава цените ще бъдат запаметени като неактивни и ще могат да се активират по-късно.&#013;&#013;Ще бъдат променени само продуктите, които са визуализирани в момента и са маркирани с отметка. По подразбиране всички са маркирани, но при нужна отметката може да се премахне от определени продукти.&#013;&#013;Ще бъдат променени само цените на избраните номера на каси. Ако например от горното меню 'Цени на каси №' са избрани само 1 и 2, тогава промяната ще важи само за тях и останалите каси няма да бъдат засегнати.">?</span></b></div>
HTML;
				} else {
					$mass_title = <<<HTML
	<div id="mass-product-title"><b>Масова промяна на цени на продукти <span class="badge bg-warning text-dark" title="От тук се променят масово цените на избраните продукти.&#013;&#013;Има два варианта за промяна на цени - редактиране на текущите или създаване на нови за по-късно:&#013;   1. Ако е маркирана отметката 'Промени текущите цени', тогава директно ще бъдат заменени цените на продуктите в момента.&#013;   2. Ако отметката не е маркирана, тогава цените ще бъдат запазени и ще могат да се активират по-късно.&#013;&#013;Ще бъдат променени само продуктите, които са визуализирани в момента и са маркирани с отметка. По подразбиране всички са маркирани, но при нужна отметката може да се премахне от определени продукти.">?</span></b></div>
HTML;
				}

				echo <<<HTML
	<form id="mass-insert-form" class="mt-2">
		<span id="mass-insert-span">
			$mass_title
			<span class="badge bg-info">
				<select id="operator-price-select" class="operator-price-select" name="operator_price">
					<option value="=">=</option>
					<option value="+">+</option>
					<option value="-">-</option>
					<option value="+%">+%</option>
					<option value="-%">-%</option>
				</select>
				<input type="number" id="sum-price-input" class="price-input" name="sum_price" placeholder="Цена" required />
			</span>
			<span class="badge bg-info">
				<select id="operator-promotion-select" class="operator-promotion-select" name="operator_promotion">
					<option value="=">=</option>
					<option value="+">+</option>
					<option value="-">-</option>
					<option value="+%">+%</option>
					<option value="-%">-%</option>
				</select>
				<input type="number" id="sum-promotion-input" class="price-input" name="sum_promotion" placeholder="Промо" required />
			</span>
			<span class="badge bg-info text-dark checkbox-badge">
				<input type="checkbox" id="mass-edit-prices" />Промени текущите цени
			</span>
			<span class="badge bg-info text-dark checkbox-badge">
				<input type="checkbox" id="mass-round-prices" checked />Закръгли
			</span>
			<span class="badge bg-info text-dark checkbox-badge" id="mass-prices-to-promo-container" style="display:none">
				<select id="mass-prices-to-promo"></select>
			</span>
		</span>
		<button type="button" id="check-mass-insert" class="btn btn-warning">Промени</button>
		<button type="button" id="apply-mass-insert" class="btn btn-success" style="display:none"></button>
	</form>
HTML;

				$order = isset($_SESSION['order_by_price']) ? $_SESSION['order_by_price'] : 'ASC';
				$icon = $order === 'ASC' ? '▲' : '▼';
				?>
				<div id="products-table" class="mt-4">
					<table class="table table-bordered">
						<thead>
							<tr class="table-light">
								<th><span class="badge bg-secondary">
										<input type="checkbox" class="check-all-products">
										ID
									</span></th>
								<th>🖼️</th>
								<th><span class="badge bg-secondary">Име</span></th>
								<th><span class="badge bg-secondary">Статус</span></th>
								<th><span class="badge bg-secondary">Цена</span> <span id="order-by-price-icon" class="pointer text-primary"><?php echo $icon; ?></span></th>
								<th><span class="badge bg-secondary">Промоция</span></th>
								<?php if ($selected_frame_ids) : ?>
									<th><span class="badge bg-secondary">Цена №</span></th>
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
							// Prepare the base arguments
							$args = array(
								'post_type'      => 'product',
								'posts_per_page' => -1,
								'post_status' => isset($_GET['filters']) && in_array('drafts', $_GET['filters']) ? 'draft' : 'publish',
							);

							if ($selected_category_id) {
								$args['tax_query'] = array(
									array(
										'taxonomy' => 'product_cat',
										'field'    => 'term_id',
										'terms'    => $selected_category_id,
									),
								);
							}

							if ($selected_frame_ids) {
								$frame_products = $wpdb->get_col($wpdb->prepare(
									"SELECT product_id 
									FROM {$wpdb->prefix}doors_frames 
									WHERE frame_id IN (%s)",
									implode(',', $selected_frame_ids)
								));
								$args['post__in'] = $frame_products ? $frame_products : array(0);
							}

							// Query all products
							$all_products_query = new WP_Query($args);

							// If no products found, remove the search term and re-query
							$finded_products = false;
							if (!$all_products_query->have_posts()) {
								unset($args['s']);
								$all_products_query = new WP_Query($args);
								$finded_products = true;
							} else {

								// Collect product IDs and prices
								$product_prices = array();
								if ($all_products_query->have_posts()) {
									while ($all_products_query->have_posts()) {
										$all_products_query->the_post();
										$product_id = get_the_ID();
										$product = wc_get_product($product_id);

										if ($product->is_type('variable')) {
											$variation_prices = $product->get_variation_prices();
											$min_price = $variation_prices['price'][min(array_keys($variation_prices['price']))];
										} else {
											$min_price = $product->get_price();
										}

										$product_prices[$product_id] = $min_price !== '' ? floatval($min_price) : PHP_INT_MAX;
									}
									wp_reset_postdata();
								}

								// Sort products by price
								if ($order === 'ASC') {
									asort($product_prices);
								} else {
									arsort($product_prices);
								}

								// Final query to get sorted products
								$query = new WP_Query(array(
									'post_type'      => 'product',
									'posts_per_page' => -1,
									'post__in'       => array_keys($product_prices),
									'orderby'        => 'post__in',
									'order'          => 'ASC', // Order by 'post__in' will maintain the order from array_keys
								));

								// Remove the filter after the query to prevent affecting other queries
								remove_filter('posts_search', 'custom_search', 10, 2);
							}
							if (!$finded_products && $query->have_posts()) {
								while ($query->have_posts()) {
									$query->the_post();
									$product = wc_get_product(get_the_ID());
									$regular_price = $product->get_regular_price();
									$sale_price = $product->get_sale_price();
									$product_image_thumbnail = get_the_post_thumbnail_url(get_the_ID(), 'thumbnail');
									$product_image_full = get_the_post_thumbnail_url(get_the_ID(), 'full');


									if ($selected_frame_ids) {
										$frame_ids_placeholder = implode(',', array_fill(0, count($selected_frame_ids), '%d'));

										$sql = $wpdb->prepare(
											"SELECT frame_id, frame_description, frame_image, frame_price, frame_promo_price, active
											FROM {$wpdb->prefix}doors_frames 
											WHERE product_id = %d AND frame_id IN ($frame_ids_placeholder) AND active LIKE %s
											ORDER BY frame_id ASC",
											array_merge([get_the_ID()], $selected_frame_ids, [active_status($_GET['active'] ?? '1')])
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

									if ($product_image_thumbnail) {
										$product_image = "<a target='_blank' href='$product_image_full'><img src='$product_image_thumbnail' class='product-image-thumb' loading='lazy'></a>";
									} else {
										$product_image = "";
									}

									echo '<tr class="table-secondary">';
									echo '<td rowspan="' . $rowspan . '" class="' . $product_row_class . '">
									' . (!$product->is_type('variable') || $selected_frame_ids ? '<input type="checkbox" class="check-product" data-product-id="' . get_the_ID() . '">' : '') .  get_the_ID() . '
									</td>';
									echo '<td rowspan="' . $rowspan . '" class="' . $product_row_class . '">' . $product_image . '</td>';
									echo '<td rowspan="' . $rowspan . '" class="' . $product_row_class . '"><a class="product-title" target="_blank" href="' . get_the_permalink() . '">' . get_the_title() . '</a></td>';
									echo '<td rowspan="' . $rowspan . '" class="' . $product_row_class . '"><select class="product-status" data-product-id="' . get_the_ID() . '"><option value="publish"' . (get_post_status() == 'publish' ? 'selected' : '') . '>Публична</option><option value="draft" ' . (get_post_status() == 'draft' ? 'selected' : '') . '>Чернова</option></select></td>';

									$products_table_name = $wpdb->prefix . 'doors_frames_products';
									$saved_prices = $wpdb->get_row($wpdb->prepare(
										"SELECT * FROM $products_table_name WHERE product_id = %d",
										get_the_ID()
									));

									if ($product->is_type('variable')) {
										$available_variations = $product->get_available_variations();
										$min_regular_price = null;
										$min_sale_price = null;
										$min_regular_saved_price = null;
										$min_sale_saved_price = null;

										foreach ($available_variations as $variation) {
											$saved_regular_price = '';
											$saved_sale_price = '';
											$variation_product = wc_get_product($variation['variation_id']);

											if ($saved_prices) {
												$variations_array = json_decode($saved_prices->variations, true);
											}

											$regular_price = $variation_product->get_regular_price();
											$sale_price = $variation_product->get_sale_price();

											if ($min_regular_price === null || $regular_price < $min_regular_price) {
												if ($saved_prices && is_array($variations_array)) {
													foreach ($variations_array as $var) {
														if ($var['variation_id'] == $variation['variation_id']) {
															$min_regular_saved_price = $var['regular_price'];
															break;
														} else {
															$min_regular_saved_price = null;
														}
													}
												}
												$min_regular_price = $regular_price;
											}
											if ($min_sale_price === null || $sale_price < $min_sale_price) {
												if ($saved_prices && is_array($variations_array)) {
													foreach ($variations_array as $var) {
														if ($var['variation_id'] == $variation['variation_id']) {
															$min_sale_saved_price = $var['sale_price'];
															break;
														} else {
															$min_sale_saved_price = null;
														}
													}
												}
												$min_sale_price = $sale_price > 0 ? $sale_price : '';
											}

											$saved_regular_price = $min_regular_saved_price && $min_regular_saved_price >= 0 ? "<div><span class='badge bg-warning text-dark' id='price-badge-" . get_the_ID() . "' title='Запазена цена за по-късно'>$min_regular_saved_price</span></div>" : '';
											$saved_sale_price = $min_sale_saved_price >= 0 ? "<div><span class='badge bg-warning text-dark' id='price-promo-badge-" . get_the_ID() . "' title='Запазена цена за по-късно'>$min_sale_saved_price</span></div>" : '';
										}



										echo '<td rowspan="' . $rowspan . '" class="' . $product_row_class . ' open-variations-modal" role="button" title="Виж вариациите на продукта" data-id="' . get_the_ID() . '">' . $saved_regular_price . $min_regular_price . '</td>';
										echo '<td rowspan="' . $rowspan . '" class="' . $product_row_class . ' open-variations-modal" role="button" title="Виж вариациите на продукта" data-id="' . get_the_ID() . '">' . $saved_sale_price . $min_sale_price . '</td>';
									} else {
										if ($regular_price > 0 && $sale_price > 0) {
											$salePercent = ((floatval($regular_price) - floatval($sale_price)) / floatval($regular_price)) * 100;
											$salePercent = "Отстъпка: " . round($salePercent, 2) . "%";
										} else {
											$salePercent = '';
										}

										$saved_regular_price = $saved_prices && $saved_prices->product_price >= 0 ? "<div><span class='badge bg-warning text-dark' id='price-badge-" . get_the_ID() . "' title='Запазена цена за по-късно'>$saved_prices->product_price</span></div>" : '';
										$saved_sale_price = $saved_prices && $saved_prices->product_promo_price >= 0 ? "<div><span class='badge bg-warning text-dark' id='price-promo-badge-" . get_the_ID() . "' title='Запазена цена за по-късно'>$saved_prices->product_promo_price</span></div>" : '';

										echo '<td rowspan="' . $rowspan . '" class="' . $product_row_class . '">' . $saved_regular_price . '<input type="number" class="price-inputs product-price-input" data-product-id="' . get_the_ID() . '" data-type="regular" ' . (!$selected_frame_ids ? 'data-change-price = "false"' : '') . ' data-promo-price="' . esc_attr($sale_price) . '" data-saved-promo-price="' . ($saved_prices ? esc_attr($saved_prices->product_promo_price) : '') . '" data-saved-price="' . ($saved_prices ? esc_attr($saved_prices->product_price) : '') . '" data-value = "' . esc_attr($regular_price) . '" value="' . esc_attr($regular_price) . '" readonly></td>';
										echo '<td rowspan="' . $rowspan . '" class="' . $product_row_class . '">' . $saved_sale_price . '<input type="number" class="price-inputs product-promo-input" data-product-id="' . get_the_ID() . '" data-type="sale" ' . (!$selected_frame_ids ? 'data-change-price = "false"' : '') . ' data-price="' . esc_attr($regular_price) . '" data-saved-price="' . ($saved_prices ? esc_attr($saved_prices->product_price) : '') . '" data-saved-promo-price="' . ($saved_prices ? esc_attr($saved_prices->product_promo_price) : '') . '" data-value = "' . esc_attr($sale_price) . '" value="' . esc_attr($sale_price) . '" title="' . $salePercent . '" readonly></td>';
									}

									$modal_button = '<button class="frames-button btn btn-primary open-modal" data-id="' . get_the_ID() . '">Цени на каси</button>';

									if (isset($frame_data_list) && is_array($frame_data_list)) {
										$first_frame = true;
										foreach ($frame_data_list as $frame_data) {
											$frames_table_name = $wpdb->prefix . 'doors_frames';
											$saved_frame_prices = $wpdb->get_row($wpdb->prepare(
												"SELECT frame_price, frame_promo_price FROM $frames_table_name WHERE product_id = %d AND frame_id = %d AND active = 0",
												get_the_ID(),
												$frame_data->frame_id
											));

											if ($saved_frame_prices && $saved_frame_prices->frame_price >= 0) {
												$saved_frame_price =
													'<div bis_skin_checked="1"><span class="badge bg-warning text-dark" id="price-promo-badge-60" title="Запазена цена за по-късно">' . $saved_frame_prices->frame_price . '</span></div>';
											} else {
												$saved_frame_price = "";
											}

											if ($saved_frame_prices && $saved_frame_prices->frame_promo_price >= 0) {
												$saved_frame_promo_price = '<div bis_skin_checked="1"><span class="badge bg-warning text-dark" id="price-promo-badge-60" title="Запазена цена за по-късно">' . $saved_frame_prices->frame_promo_price . '</span></div>';
											} else {
												$saved_frame_promo_price = "";
											}

											if (!$first_frame) {
												echo '<tr>';
											}

											if ($first_frame && isset($_GET['frame_id']) && count($_GET['frame_id']) > 1) {
												$product_row_class = 'product-row';
											} else {
												$product_row_class = '';
											}

											if ($frame_data->active == 1) {
												$active_status = 'active-frame';
											} else {
												$active_status = 'inactive-frame';
											}

											echo '<td class="' . $active_status . ' ' . $product_row_class . '">' . ($frame_data->frame_id > 0 ? $frame_data->frame_id : 'Основна') . '</td>';
											echo '<td class="' . $active_status . ' ' . $product_row_class . '"><img src="' . $upload_dir['baseurl'] . '/doors_frames/' . $frame_data->frame_image . '" style="max-height: 38px"></td>';
											echo '<td class="' . $active_status . ' ' . $product_row_class . '">' . $frame_data->frame_description . '</td>';
											if ($frame_data->frame_id == -5) {
												if ($product->is_type('variable')) {
													echo '<td class="' . $active_status . ' ' . $product_row_class . '">' . esc_attr($min_regular_price) . '</td>';
													echo '<td class="' . $active_status . ' ' . $product_row_class . '">' . esc_attr($min_sale_price) . '</td>';
												} else {
													echo '<td class="' . $active_status . ' ' . $product_row_class . '">' . $saved_regular_price . esc_attr($regular_price) . '</td>';
													echo '<td class="' . $active_status . ' ' . $product_row_class . '">' . $saved_sale_price . esc_attr($sale_price) . '</td>';
												}
											} else {
												echo '<td class="frame-table-price ' . $active_status . ' ' . $product_row_class . '" data-promo-price = "' . $frame_data->frame_promo_price . '" data-saved-promo-price = "' . ($saved_frame_prices ? $saved_frame_prices->frame_promo_price : '') . '" data-saved-price = "' . ($saved_frame_prices ? $saved_frame_prices->frame_price : '') . '" data-change-price="false" data-product-id="' . get_the_ID() . '">' . $saved_frame_price . '<span class="saved">' . $frame_data->frame_price . '</span></td>';
												echo '<td class="frame-table-promo ' . $active_status . ' ' . $product_row_class . '" data-price = "' . $frame_data->frame_price . '" data-saved-price = "' . ($saved_frame_prices ? $saved_frame_prices->frame_price : '') . '" data-saved-promo-price = "' . ($saved_frame_prices ? $saved_frame_prices->frame_promo_price : '') . '" data-change-price="false" data-product-id="' . get_the_ID() . '">' . $saved_frame_promo_price . '<span class="saved">' . $frame_data->frame_promo_price . '</span></td>';
											}

											if ($first_frame) {
												echo '<td rowspan="' . $rowspan . '" class="' . $product_row_class . '">' . $modal_button . '</td>';
												$first_frame = false;
											}

											echo '</tr>';
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
					<div id="progress-div" class="progress m-3" style="display: none;">
						<div class="progress-bar progress-bar-striped progress-bar-animated bg-warning" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%;">
							<span class="progress-bar-text">0%</span>
						</div>
					</div>
					<select id="edit-prices-type">
						<option value="">🚫 Забранена промяна на единичните цени</option>
						<option value="later">💾 Запазване на единичните цени за по-късно</option>
						<option value="now">⚡ Незабавна промяна на единичните цени</option>
					</select>
					<button id="btn-activate-prices" class="btn btn-sm btn-info">Активирай всички запазени цени на продукти</button>
					<button id="btn-activate-frame-prices" class="btn btn-sm btn-info">Активирай всички запазени цени на каси</button>
				</div>
			<?php endif; ?>
		</div>
	</div>
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
	<div id="variationsModal" class="modal" style="display:none;">
		<div class="modal-dialog modal-xl">
			<div class="modal-content">
				<div class="modal-header">
					<h4 class="modal-title">Вариации</h4>
					<button type="button" id="close-variations" class=" btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div id="variations-modal-body" style="max-height: calc(100vh - 200px); overflow-y: auto;">
					<!-- Dynamic content will be loaded here -->
				</div>
				<div class=" modal-footer">
					<button type="button" class="btn btn-secondary closeVariations" data-bs-dismiss="modal">Затвори</button>
					<button type="button" id="save-modal-variation-prices" class="btn btn-primary" style="display:none;">Запази промените</button>
				</div>
			</div>
		</div>
	</div>
<?php
	echo "<hr><div class='float-end me-5'>Версия на разширението: <span id='extension-version'>" . pluginData()['Version'] . '</span></div>';
}

function active_status($status_id)
{
	if (in_array($status_id, [0, 1])) {
		return $status_id;
	}

	return '%';
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

add_action('wp_ajax_change_product_status', 'change_product_status');
function change_product_status()
{
	if (isset($_POST['product_id']) && isset($_POST['status'])) {
		$product_id = intval($_POST['product_id']);
		$status = sanitize_text_field($_POST['status']);

		$post_data = [
			'ID' => $product_id,
			'post_status' => $status
		];
		wp_update_post($post_data);

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
		$edit_prices_type = sanitize_text_field($_POST['edit_prices_type']);

		$product = wc_get_product($product_id);
		if ($product) {
			if ($edit_prices_type == 'now') {
				if ($price_type == 'regular') {
					$product->set_regular_price($new_price != 0 ? $new_price : '');
				} elseif ($price_type == 'sale') {
					$product->set_sale_price($new_price != 0 ? $new_price : '');
				}
				$product->save();
			}

			if ($edit_prices_type == 'later') {
				global $wpdb;
				$products_table_name = $wpdb->prefix . 'doors_frames_products';

				if ($price_type == 'regular') {
					$sql = $wpdb->prepare(
						"INSERT INTO $products_table_name (product_id, product_price) 
						VALUES (%d, %f) 
						ON DUPLICATE KEY UPDATE 
						product_price = VALUES(product_price)",
						$product_id,
						$new_price
					);
				} elseif ($price_type == 'sale') {
					$sql = $wpdb->prepare(
						"INSERT INTO $products_table_name (product_id, product_promo_price) 
						VALUES (%d, %f) 
						ON DUPLICATE KEY UPDATE 
						product_promo_price = VALUES(product_promo_price)",
						$product_id,
						$new_price
					);
				}

				$wpdb->query($sql);
			}

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

	if (isset($_POST['frames'])) {
		$frames = $_POST['frames'];
		$table_name = $wpdb->prefix . 'doors_frames';
		$errors = false;

		foreach ($frames as $frame) {
			$frame_id = intval($frame['frame_id']);
			$frame_image = sanitize_text_field($frame['frame_image']);
			$frame_description = $frame['frame_description'];
			$is_new = filter_var($frame['is_new'], FILTER_VALIDATE_BOOLEAN);

			if ($is_new) { // Insert new frame
				$product_id = intval($frame['product_id']);
				$frame_price = floatval($frame['frame_price']);
				$frame_promo_price = floatval($frame['frame_promo_price']);

				$result = $wpdb->insert(
					$table_name,
					array(
						'product_id' => $product_id,
						'frame_id' => $frame_id,
						'frame_price' => $frame_price,
						'frame_promo_price' => $frame_promo_price,
						'frame_image' => $frame_image,
						'frame_description' => $frame_description,
					),
					array('%d', '%d', '%f', '%f', '%s', '%s')
				);
			} else {
				$id = intval($frame['id']);
				$delete_frame = sanitize_text_field($frame['delete_frame']);

				if ($delete_frame === 'true') {
					$result = $wpdb->delete($table_name, array('id' => $id), array('%d'));
					if ($result === false) {
						$errors = true;
					}
					continue;
				}

				if ($frame_id > 0) {
					$frame_price = floatval($frame['frame_price']);
					$frame_promo_price = floatval($frame['frame_promo_price']);

					$result = $wpdb->update(
						$table_name,
						array(
							'frame_id' => $frame_id,
							'frame_price' => $frame_price,
							'frame_promo_price' => $frame_promo_price,
							'frame_image' => $frame_image,
							'frame_description' => $frame_description,
						),
						array('id' => $id),
						array('%d', '%f', '%f', '%s', '%s'),
						array('%d')
					);
				} else {
					$result = $wpdb->update(
						$table_name,
						array(
							'frame_id' => $frame_id,
							'frame_image' => $frame_image,
							'frame_description' => $frame_description,
						),
						array('id' => $id),
						array('%d', '%s', '%s'),
						array('%d')
					);
				}
			}

			if ($result === false) {
				$errors = true;
			}
		}

		if (!$errors) {
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

			$result = $wpdb->insert(
				$table_name,
				array(
					'product_id' => $product_id,
					'frame_id' => $frame_id,
					'frame_price' => $new_frame_price,
					'frame_promo_price' => $new_frame_promo_price,
					'frame_image' => $new_frame_image,
					'frame_description' => $new_frame_description,
				),
				array('%d', '%d', '%f', '%f', '%s', '%s')
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
				array('%d', '%d', '%s', '%s')
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
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($folderPath, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::SELF_FIRST
		);

		$imageFiles = [];
		foreach ($iterator as $file) {
			if ($file->isFile() && in_array($file->getExtension(), ['jpg', 'png'])) {
				// Get the relative path from the base directory
				$relativePath = $iterator->getSubPathname();
				$imageFiles[] = $relativePath;
			}
		}

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
		$results = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE product_id = %d ORDER by active DESC, frame_id ASC", $product_id));

		$html_product_title = "<h5 id='product-title' class='text-center' data-static-images-path='{$upload_dir['baseurl']}/doors_frames/'><mark>$product_title</mark></h5><input type='hidden' id='modal-product-id' value='$product_id'>";

		$html_new_table = <<<HTML
	<div id="all-frame-images" class="m-1" data-frame-options="$blankImageOptions">
		<table class="table bg-info" id="new-frame-table" style="display: none;">
			<thead>
				<tbody></tbody>
			</tbody>
		</table>
		<div>
			<button class="btn btn-success mb-3 mx-3" id="add-new-frame" data-id="$product_id">Добави нова цена на каса</button>
			<button class="btn btn-primary mb-3 mx-3" id="copy-frames" data-id="$product_id" data-name="$product_title">Копирай касите</button>
			<button class="btn btn-primary mb-3 mx-3" id="paste-frames" data-id="$product_id" style='display: none;'></button>
		</div>
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
					<th>Изтрий</th>
				</tr>
			</thead>
			<tbody>
HTML;

			$show_prices = '';
			foreach ($results as $result) {
				if ($result->frame_id > 0) {
					if ($result->frame_price > 0 && $result->frame_promo_price > 0) {
						$salePercent = ((floatval($result->frame_price) - floatval($result->frame_promo_price)) / floatval($result->frame_price)) * 100;
						$salePercent = "Отстъпка: " . round($salePercent, 2) . "%";
					} else {
						$salePercent = '';
					}

					$show_prices = <<<HTML
	<td><input type="number" class="form-control price-input frame-price" value="$result->frame_price"></td>
	<td><input type="number" class="form-control price-input frame-promo-price" value="$result->frame_promo_price" title="$salePercent"></td>
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
					$show_prices = "<td>" . $frame_price . "</td><td>" . $frame_promo_price . "</td>";
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

				if ($result->active != '1') {
					$frame_status = 'inactive-frame';
				} else {
					$frame_status = '';
				}

				$html .= <<<HTML
	<tr class="frame-id $frame_status" data-id="$result->id">
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
		<td><button class="btn btn-primary btn-sm frame-duplicate" data-id="$product_id">Дублирай</button></td>
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

add_action('wp_ajax_fetch_variation_prices', 'fetch_variation_prices');
function fetch_variation_prices()
{
	global $wpdb;

	if (isset($_POST['product_id'])) {
		$product_id = intval($_POST['product_id']);
		$product = wc_get_product($product_id);
		$product_title = get_the_title($product_id);
		$available_variations = $product->get_available_variations();
		$html_product_title = "<h5 id='product-title' class='text-center'><mark>$product_title</mark></h5><input type='hidden' id='modal-product-id' value='$product_id'>";



		usort($available_variations, function ($a, $b) {
			return $a['menu_order'] - $b['menu_order'];
		});

		$variations_with_prices = array();

		foreach ($available_variations as $variation) {
			$variation_id = $variation['variation_id'];
			$variation_product = wc_get_product($variation_id);
			$regular_price = floatval($variation_product->get_regular_price());
			$sale_price = floatval($variation_product->get_sale_price());

			$filtered_variation = array(
				'variation_id' => $variation_id,
				'regular_price' => $regular_price,
				'sale_price' => $sale_price,
				'variation_attributes' => $variation['attributes']
			);

			$variations_with_prices[] = $filtered_variation;
		}

		if ($variations_with_prices) {

			$variations_table = $wpdb->prefix . 'doors_frames_products';
			$saved_prices = $wpdb->get_var($wpdb->prepare("SELECT variations FROM $variations_table WHERE product_id = %d LIMIT 1", $product_id));

			$saved_variations = json_decode($saved_prices, true);

			$structuredVariations = array();
			foreach ($saved_variations as $saved_variation) {
				$structuredVariations[$saved_variation['variation_id']] = array(
					'sale_price' => $saved_variation['sale_price'],
					'regular_price' => $saved_variation['regular_price']
				);
			}

			$html = <<<HTML
	<form id="mass-insert-form" class="mt-2">
		<span class="badge bg-info">
			<select id="variation-operator-price-select" class="operator-price-select" name="operator_price">
				<option value="=">=</option>
				<option value="+">+</option>
				<option value="-">-</option>
				<option value="+%">+%</option>
				<option value="-%">-%</option>
			</select>
			<input type="number" id="variation-price-input" class="price-inputs" placeholder="Цена">
		</span>
		<span class="badge bg-info">
			<select id="variation-operator-promotion-select" class="operator-promotion-select" name="operator_promotion">
				<option value="=">=</option>
				<option value="+">+</option>
				<option value="-">-</option>
				<option value="+%">+%</option>
				<option value="-%">-%</option>
			</select>
			<input type="number" id="variation-promotion-input" class="price-inputs" placeholder="Промо" required="">
		</span>
		<span class="badge bg-info text-dark checkbox-badge">
			<input type="checkbox" id="variation-mass-round-prices" checked="">Закръгли
		</span>
		<span class="badge bg-info text-dark checkbox-badge" id="mass-variation-prices-to-promo-container" style="display: none;">
			<select id="variation-prices-to-promo"><option value=""></option><option value="promo-to-price" title="Базовата цена се изчислява според текущата промоционална цена на продукта.">Промо към цена</option><option value="new-promo-to-price" title="Базовата цена се изчислява според запазената за по-късно промоционална цена на продукта.">Запазено промо към цена</option><option value="price-to-promo" title="Базовата цена се изчислява според запазената за по-късно промоционална цена на продукта.">Цена към промо</option><option value="new-price-to-promo" title="Промоционалната цена се изчислява според запазената за по-късно цена на продукта.">Запазена цена към промо</option></select>
		</span>

		<button type="button" id="variation-mass-prices" class="btn btn-success">Промени</button>
	</form>
HTML;

			$html .= <<<HTML
<div class="m-1">
	<table class="table table-secondary table-striped">
		<thead>
			<tr>
				<th>ID</th>
				<th>Атрибути</th>
				<th>Цена</th>
				<th>Промоция</th>
			</tr>
		</thead>
		<tbody>
HTML;

			foreach ($variations_with_prices as $variation) {
				$attributes = array_map(function ($value) {
					return "<span class='badge bg-white text-dark'>" . htmlspecialchars($value) . "</span>";
				}, $variation['variation_attributes']);
				$attributes = implode(' ', $attributes);

				if (count($structuredVariations) > 0) {
					$saved_variation_price = '<div class="badge-container" bis_skin_checked="1"><span id="price-badge-60" class="badge bg-warning text-dark">' . $structuredVariations[$variation['variation_id']]['regular_price'] . '</span></div>';
					$saved_variation_promo = '<div class="badge-container" bis_skin_checked="1"><span id="price-badge-60" class="badge bg-warning text-dark">' . $structuredVariations[$variation['variation_id']]['sale_price'] . '</span></div>';
				} else {
					$saved_variation_price = '';
					$saved_variation_promo = '';
				}

				$html .= <<<HTML
	<tr class="variation-row" data-variation-id="$variation[variation_id]">
		<td><input type="checkbox" checked> $variation[variation_id]</td>
		<td>$attributes</td>
		<td>$saved_variation_price<input type="number" class="form-control price-input variation-price" data-variation-id="$variation[variation_id]" data-product-id="$product_id" data-type="regular" data-regular-price="$variation[regular_price]" data-sale-price="$variation[sale_price]" data-saved-regular-price="{$structuredVariations[$variation['variation_id']]['regular_price']}" data-saved-sale-price="{$structuredVariations[$variation['variation_id']]['sale_price']}" value="$variation[regular_price]" readonly></td>
		<td>$saved_variation_promo<input type="number" class="form-control price-input variation-promo-price" data-variation-id="$variation[variation_id]" data-product-id="$product_id" data-type="sale" data-regular-price="$variation[regular_price]" data-sale-price="$variation[sale_price]" data-saved-regular-price="{$structuredVariations[$variation['variation_id']]['regular_price']}" data-saved-sale-price="{$structuredVariations[$variation['variation_id']]['sale_price']}" value="$variation[sale_price]" readonly></td>
	</tr>
HTML;
			}

			$html .= <<<HTML
		</tbody>
	</table>
</div>
HTML;

			wp_send_json_success($html_product_title . $html);
		} else {
			wp_send_json_error();
		}
	}
}

add_action('wp_ajax_update_variation_prices', 'update_variation_prices');
function update_variation_prices()
{
	global $wpdb;

	if (isset($_POST['variations'])) {
		$product_id = $_POST['product_id'];

		foreach ($_POST['variations'] as $variation) {
			$variation_id = $variation['variation_id'];
			$variation_price_input = $variation['variation_price_input'];
			$variation_promo_input = $variation['variation_promo_input'];
			$variation_data = new WC_Product_Variation($variation_id);
			$regular_price = $variation['variation_price'];
			$sale_price = $variation['variation_promo_price'];
			$old_regular_price = $variation['variation_old_price'];
			$old_sale_price = $variation['variation_old_promo_price'];
			$variation_price_badge = $variation['variation_price_badge'];
			$variation_promo_badge = $variation['variation_promo_badge'];

			if ($_POST['edit_type'] == 'now') {
				if ($sale_price == 0) {
					$sale_price = "";
				}

				$variation_data->set_regular_price($regular_price);
				$variation_data->set_sale_price($sale_price);

				$variation_data->save();
			} else {
				if ($regular_price == $old_regular_price) {
					$regular_price = $variation_price_badge;
				}

				if ($sale_price == $old_sale_price) {
					$sale_price = $variation_promo_badge;
				}

				$variations_array[] = [
					'variation_id' => $variation_id,
					'regular_price' => $regular_price,
					'sale_price' => $sale_price,
				];
			}
		}

		if (!empty($variations_array)) {
			$products_table_name = $wpdb->prefix . 'doors_frames_products';

			$variations_json = json_encode($variations_array);

			$sql = $wpdb->prepare(
				"INSERT INTO $products_table_name (product_id, variations) 
						VALUES (%d, %s) 
						ON DUPLICATE KEY UPDATE 
						variations = VALUES(variations)",
				$product_id,
				$variations_json
			);

			$wpdb->query($sql);
		}

		wp_send_json_success();
	} else {
		wp_send_json_error();
	}
}

add_action('wp_ajax_order_by_price', 'order_by_price');
function order_by_price()
{
	if (isset($_POST['toggle_order_by_price'])) {
		if (!isset($_SESSION['order_by_price'])) {
			$_SESSION['order_by_price'] = 'DESC';
		} elseif ($_SESSION['order_by_price'] === 'ASC') {
			$_SESSION['order_by_price'] = 'DESC';
		} else {
			$_SESSION['order_by_price'] = 'ASC';
		}
		wp_send_json_success();
	}
}

add_action('wp_ajax_paste_frames', 'paste_frames');
function paste_frames()
{
	global $wpdb;

	$frames_table_name = $wpdb->prefix . 'doors_frames';

	$existing_records = $wpdb->get_results(
		$wpdb->prepare("SELECT * FROM $frames_table_name WHERE product_id = %d", $_POST['copy_id'])
	);

	if ($existing_records) {
		foreach ($existing_records as $record) {
			// Prepare data for insertion, changing only the product_id
			$data = array(
				'product_id' => $_POST['paste_id'],
				'frame_id' => $record->frame_id,
				'frame_price' => $record->frame_price,
				'frame_promo_price' => $record->frame_promo_price,
				'frame_description' => $record->frame_description,
				'frame_image' => $record->frame_image,
				'active' => $record->active
			);

			// Insert the new record
			$wpdb->insert($frames_table_name, $data);
		}
	}

	if (!$wpdb->last_error) {
		wp_send_json_success();
	}
}

add_action('wp_ajax_mass_insert_frames', 'mass_insert_frames');
function mass_insert_frames()
{
	global $wpdb;

	if (isset($_POST['product_ids']) && isset($_POST['operator_price']) && isset($_POST['operator_promotion'])) {
		$product_ids = array_map('intval', $_POST['product_ids']);
		$operator_price = sanitize_text_field($_POST['operator_price']);
		$sum_price = floatval($_POST['sum_price']);
		$operator_promotion = sanitize_text_field($_POST['operator_promotion']);
		$sum_promotion = floatval($_POST['sum_promotion']);
		$price_edit = $_POST['price_edit'];
		$prices_round = $_POST['prices_round'];
		$prices_to_promo = $_POST['prices_to_promo'];

		$frames_table_name = $wpdb->prefix . 'doors_frames';
		$products_table_name = $wpdb->prefix . 'doors_frames_products';

		if ($_POST['frame_ids']) {
			$frame_ids =
				implode(',', $_POST['frame_ids']);

			foreach ($product_ids as $product_id) {
				$current_values_sql = $wpdb->prepare(
					"SELECT * FROM {$frames_table_name} WHERE product_id = %d AND frame_id IN ($frame_ids) AND active = 1",
					array_merge([$product_id])
				);
				$current_values = $wpdb->get_results($current_values_sql);

				foreach ($current_values as $current_value) {
					$frames_table_name = $wpdb->prefix . 'doors_frames';
					$saved_prices = $wpdb->get_row($wpdb->prepare(
						"SELECT frame_price, frame_promo_price FROM $frames_table_name WHERE product_id = %d AND frame_id = %d AND active = 0",
						$product_id,
						$current_value->frame_id
					));

					switch ($prices_to_promo) {
						case 'old-to-promo':
							$target_value =	$current_value->frame_price;
							break;
						case 'new-to-promo':
							$target_value = $saved_prices->frame_price;
							break;
						case 'new-promo-to-promo':
							$target_value =	$saved_prices->frame_promo_price;
							break;
						case 'old-promo-to-price':
							$target_value =	$current_value->frame_promo_price;
							$priceBeforeSale = true;
							break;
						case 'new-promo-to-price':
							$target_value = $saved_prices->frame_promo_price;
							$priceBeforeSale = true;
							break;
						case 'new-to-price':
							$target_value = $saved_prices->frame_price;
							$priceBeforeSale = true;
							break;
						default:
							$target_value = null;
							break;
					}

					$new_price = calculate_new_value($current_value->frame_price, $operator_price, $sum_price, $prices_round, $target_value, $priceBeforeSale);
					$new_promo_price = calculate_new_value($current_value->frame_promo_price, $operator_promotion, $sum_promotion, $prices_round, $target_value);

					if ($price_edit == 'true') {
						$update_query = $wpdb->prepare(
							"UPDATE $frames_table_name 
							SET 
								frame_price = %f,
								frame_promo_price = %f 
							WHERE 
								id = %d AND active LIKE %s",
							$new_price >= 0 ? $new_price : $current_value->frame_price,
							$new_promo_price >= 0 ? $new_promo_price : $current_value->frame_promo_price,
							$current_value->id,
							active_status($_POST['active'])
						);

						$wpdb->query($update_query);
					} else {
						if ($_POST['sum_price'] == '-1') {
							if ($saved_prices->frame_price) {
								$new_price = $saved_prices->frame_price;
							} else {
								$new_price = $current_value->frame_price;
							}
						}

						if ($_POST['sum_promotion'] == '-1') {
							if ($saved_prices->frame_promo_price) {
								$new_promo_price = $saved_prices->frame_promo_price;
							} else {
								$new_promo_price = $current_value->frame_promo_price;
							}
						}

						$data = array(
							'product_id' => $product_id,
							'frame_id' => $current_value->frame_id,
							'frame_price' => $new_price,
							'frame_promo_price' => $new_promo_price,
							'frame_image' => $current_value->frame_image,
							'frame_description' => $current_value->frame_description,
							'active' => 0
						);

						$data_format = array('%d', '%d', '%f', '%f', '%s', '%s', '%d');

						$existing_record = $wpdb->get_row(
							$wpdb->prepare(
								"SELECT ID FROM $frames_table_name WHERE product_id = %d AND frame_id = %d AND active = %d",
								$product_id,
								$current_value->frame_id,
								0
							)
						);

						if ($existing_record) {
							$wpdb->update(
								$frames_table_name,
								$data,
								array('ID' => $existing_record->ID),
								$data_format,
								array('%d')
							);
						} else {
							$wpdb->insert($frames_table_name, $data, $data_format);
						}
					}
				}
			}
		} else {

			foreach ($product_ids as $product_id) {
				$product = wc_get_product($product_id);
				$regular_price = floatval($product->get_regular_price());
				$sale_price = floatval($product->get_sale_price());

				$products_table_name = $wpdb->prefix . 'doors_frames_products';
				$saved_prices = $wpdb->get_row($wpdb->prepare(
					"SELECT product_price, product_promo_price FROM $products_table_name WHERE product_id = %d",
					$product_id
				));

				switch ($prices_to_promo) {
					case 'old-to-promo':
						$target_value =	$regular_price;
						break;
					case 'new-to-promo':
						$target_value =	$saved_prices->product_price;
						break;
					case 'new-promo-to-promo':
						$target_value =	$saved_prices->product_promo_price;
						break;
					case 'old-promo-to-price':
						$target_value =	$sale_price;
						$priceBeforeSale = true;
						break;
					case 'new-promo-to-price':
						$target_value = $saved_prices->product_promo_price;
						$priceBeforeSale = true;
						break;
					case 'new-to-price':
						$target_value = $saved_prices->product_price;
						break;
					default:
						$target_value = null;
						break;
				}

				$new_price = calculate_new_value($regular_price, $operator_price, $sum_price, $prices_round, $target_value, $priceBeforeSale);
				$new_promo_price = calculate_new_value($sale_price, $operator_promotion, $sum_promotion, $prices_round, $target_value);

				if ($new_price == 0) {
					$new_price = "";
				}
				if ($new_promo_price <= 0) {
					$new_promo_price = "";
				}

				if ($price_edit == 'true') {
					if ($_POST['sum_price'] == '-1') {
						$new_price = $regular_price;
					}

					if ($_POST['sum_promotion'] == '-1') {
						$new_promo_price = $sale_price;
					}

					if ($new_promo_price <= 0) {
						$new_promo_price = "";
					}

					$product->set_regular_price($new_price);
					$product->set_sale_price($new_promo_price);
					$product->save();
				} else {
					if ($_POST['sum_price'] == '-1') {
						if ($saved_prices) {
							$new_price = $saved_prices->product_price;
						} else {
							$new_price = $regular_price;
						}
					}

					if ($_POST['sum_promotion'] == '-1') {
						if ($saved_prices) {
							$new_promo_price = $saved_prices->product_promo_price;
						} else {
							$new_promo_price = $sale_price;
						}
					}

					$sql = $wpdb->prepare(
						"INSERT INTO $products_table_name (product_id, product_price, product_promo_price) 
						VALUES (%d, %f, %f) 
						ON DUPLICATE KEY UPDATE 
						product_price = VALUES(product_price), 
						product_promo_price = VALUES(product_promo_price)",
						$product_id,
						$new_price,
						$new_promo_price
					);

					$wpdb->query($sql);
				}

				usleep(200000);
			}
		}

		wp_send_json_success();
	} else {
		wp_send_json_error();
	}
}

function calculate_new_value($current_value, $operator, $sum, $round, $target_value, $priceBeforeSale = false)
{
	if ($round === 'true') {
		$round = true;
	}

	if ($target_value) {
		$current_value = $target_value;
	}

	switch ($operator) {
		case '+':
			$total = $current_value + $sum;
			break;
		case '-':
			$total =  $current_value - $sum;
			break;
		case '+%':
			if ($priceBeforeSale) {
				$total = $current_value / (1 - $sum / 100);
			} else {
				$total =  $current_value + ($current_value * $sum / 100);
			}
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

add_action('wp_ajax_get_product_ids', function () {
	global $wpdb;
	$products_table_name = $wpdb->prefix . 'doors_frames_products';
	$product_ids = $wpdb->get_col("SELECT product_id FROM $products_table_name ORDER BY product_id ASC");
	wp_send_json_success($product_ids);
});

add_action('wp_ajax_activate_single_price', 'activate_single_price');
function activate_single_price()
{
	global $wpdb;
	$products_table_name = $wpdb->prefix . 'doors_frames_products';

	$product_id = intval($_POST['product_id']);

	$saved_product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $products_table_name WHERE product_id = %d", $product_id));

	if ($saved_product) {
		$regular_price = !is_null($saved_product->product_price) ? (float)$saved_product->product_price : null;
		$promo_price = !is_null($saved_product->product_promo_price) ? (float)$saved_product->product_promo_price : null;
		$variations = $saved_product->variations;
		$product = wc_get_product($product_id);

		if ($product) {
			if (!is_null($variations)) {
				$variations_data = json_decode($variations, true);

				foreach ($variations_data as $variation) {
					if ($variation['sale_price'] == 0) {
						$variation['sale_price'] = '';
					}
					$variation_id = $variation['variation_id'];
					$variation_data = new WC_Product_Variation($variation_id);
					$variation_data->set_regular_price($variation['regular_price']);
					$variation_data->set_sale_price($variation['sale_price']);
					$variation_data->save();
				}
			} else {
				if (!is_null($regular_price)) {
					if ($regular_price == 0) {
						$product->set_regular_price('');
					} else {
						$product->set_regular_price($regular_price);
					}
				}

				if (!is_null($promo_price)) {
					if ($promo_price == 0) {
						$product->set_sale_price('');
					} else {
						$product->set_sale_price($promo_price);
					}
				}

				$product->save();
			}
		} else {
			error_log("Product with ID $product_id not found in WooCommerce.");
			wp_send_json_error(array('message' => 'Product not found in WooCommerce.'));
			return;
		}

		$wpdb->delete($products_table_name, array('product_id' => $product_id), array('%d'));

		wp_send_json_success(array('product_id' => $product_id, 'product_title' => $product->get_title()));
	} else {
		wp_send_json_error('Product not found.');
	}
}

add_action('wp_ajax_activate_frame_prices', 'activate_frame_prices');
function activate_frame_prices()
{
	global $wpdb;

	$frames_table_name = $wpdb->prefix . 'doors_frames';

	$inactive_frames = $wpdb->get_results("SELECT id, product_id, frame_id FROM $frames_table_name WHERE active = 0");

	foreach ($inactive_frames as $frame) {
		$id = $frame->id;
		$product_id = $frame->product_id;
		$frame_id = $frame->frame_id;

		$wpdb->query($wpdb->prepare(
			"DELETE FROM $frames_table_name WHERE product_id = %d AND frame_id = %d AND active = 1",
			$product_id,
			$frame_id
		));

		$wpdb->update(
			$frames_table_name,
			array('active' => 1),
			array('id' => $id)
		);
	}

	wp_send_json_success();
}

//Две цени в лев и евро

add_filter('woocommerce_get_price_html', 'custom_dual_currency_price', 100, 2);

function custom_dual_currency_price($price, $product)
{
	// Фиксиран курс EUR/BGN
	$convert_rate = 1.95583;

	// За вариативни продукти намираме най-ниската цена
	if ($product->is_type('variable')) {
		$prices = $product->get_variation_prices();
		$regular_price = ! empty($prices['regular_price']) ? min($prices['regular_price']) : 0;
		$sale_price = ! empty($prices['sale_price']) ? min($prices['sale_price']) : 0;
	} else {
		$regular_price = $product->get_regular_price();
		$sale_price = $product->get_sale_price();
	}

	// Ако няма валидна редовна цена, връщаме оригиналната
	if (! is_numeric($regular_price) || $regular_price <= 0) {
		return $price;
	}

	// Форматиране на редовната цена в лева
	$regular_price_html = wc_price($regular_price, array('currency' => 'BGN'));

	// Проверка за промоция
	if ($product->is_on_sale() && is_numeric($sale_price) && $sale_price > 0) {
		$sale_price_eur = $sale_price / $convert_rate;
		$sale_price_html = wc_price($sale_price, array('currency' => 'BGN'));
		$sale_price_eur_html = euroPriceFormat($sale_price_eur);
		return '<del>' . $regular_price_html . '</del> <ins>' . $sale_price_html . '</ins> <span class="price-euro woocommerce-Price-amount amount">' . $sale_price_eur_html . '</span>';
	}

	// Без промоция
	$regular_price_eur = $regular_price / $convert_rate;
	$regular_price_eur_html = euroPriceFormat($regular_price_eur);
	return $regular_price_html . ' <span class="price-euro woocommerce-Price-amount amount">' . $regular_price_eur_html . '</span>';
}

// Цени в падащия списък на вариациите
add_filter('woocommerce_dropdown_variation_attribute_options_html', 'custom_variation_dropdown_price', 100, 2);
function custom_variation_dropdown_price($html, $args)
{
	$product = $args['product'];
	$options = $args['options'];
	$attribute = $args['attribute'];

	if (empty($options) || ! $product) {
		return $html;
	}

	// Фиксиран курс EUR/BGN
	$convert_rate = 1.95583;

	// Извличане на вариациите
	$variations = $product->get_available_variations();
	$new_options_html = '';

	foreach ($options as $option) {
		$option_html = esc_html($option);
		foreach ($variations as $variation_data) {
			// Нормализиране на името на атрибута
			$attribute_key = 'attribute_' . wc_attribute_taxonomy_name($attribute);
			if (! isset($variation_data['attributes'][$attribute_key])) {
				// Опитваме с ненормализирано име (за нестандартни атрибути)
				$attribute_key = 'attribute_' . $attribute;
			}

			if (isset($variation_data['attributes'][$attribute_key]) && $variation_data['attributes'][$attribute_key] === $option) {
				$variation_obj = wc_get_product($variation_data['variation_id']);
				$regular_price = $variation_obj->get_regular_price();
				$sale_price = $variation_obj->get_sale_price();

				if (! is_numeric($regular_price) || $regular_price <= 0) {
					continue;
				}

				$regular_price_html = wc_price($regular_price, array('currency' => 'BGN'));

				if ($variation_obj->is_on_sale() && is_numeric($sale_price) && $sale_price > 0) {
					$sale_price_eur = $sale_price / $convert_rate;
					$sale_price_html = wc_price($sale_price, array('currency' => 'BGN'));
					$sale_price_eur_html = euroPriceFormat($sale_price_eur);
					$option_html .= ' - <del>' . $regular_price_html . '</del> <ins>' . $sale_price_html . '</ins> <span class="price-euro woocommerce-Price-amount amount">' . $sale_price_eur_html . '</span>';
				} else {
					$regular_price_eur = $regular_price / $convert_rate;
					$regular_price_eur_html = euroPriceFormat($regular_price_eur);
					$option_html .= ' - ' . $regular_price_html . ' <span class="price-euro woocommerce-Price-amount amount">' . $regular_price_eur_html . '</span>';
				}
			}
		}
		$new_options_html .= '<option value="' . esc_attr($option) . '">' . $option_html . '</option>';
	}

	// Замяна на HTML в падащия списък
	$html = str_replace('<select', '<select data-variation-prices="1"', $html);
	$html = preg_replace('/<option value="([^"]*)">([^<]*)<\/option>/', $new_options_html, $html);

	return $html;
}

// Добавяне на валутни символи за BGN и EUR
add_filter('woocommerce_currencies', 'add_bgn_currency');
function add_bgn_currency($currencies)
{
	$currencies['BGN'] = __('Bulgarian Lev', 'woocommerce');
	return $currencies;
}

add_filter('woocommerce_currency_symbol', 'add_bgn_currency_symbol', 10, 2);
function add_bgn_currency_symbol($currency_symbol, $currency)
{
	switch ($currency) {
		case 'BGN':
			$currency_symbol = 'лв.';
			break;
		case 'EUR':
			$currency_symbol = '€';
			break;
	}
	return $currency_symbol;
}

function euroPriceFormat($price)
{
	if (is_numeric($price)) {
		return number_format($price, 2, ',', ' ') . ' €';
	}
	return $price;
}

// Край на две цени в лев и евро

?>