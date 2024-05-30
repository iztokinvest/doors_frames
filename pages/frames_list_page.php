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
								<th>ID</th>
								<th>Име</th>
								<th>Цена 0</th>
								<th>Цена 1</th>
								<th>Цена 2</th>
								<th>Цена 3</th>
								<th>Цена 4</th>
								<th>Цена 5</th>
								<th>Цена 6</th>
								<th>Цена 7</th>
								<th>Цена 8</th>
								<th>Цена 9</th>
								<th>Цена 10</th>
								<th>Цена 11</th>
								<th>Цена 12</th>
								<th>Цена 13</th>
								<th>Цена 14</th>
								<th>Цена 15</th>
								<th>Промо 0</th>
								<th>Промо 1</th>
								<th>Промо 2</th>
								<th>Промо 3</th>
								<th>Промо 4</th>
								<th>Промо 5</th>
								<th>Промо 6</th>
								<th>Промо 7</th>
								<th>Промо 8</th>
								<th>Промо 9</th>
								<th>Промо 10</th>
								<th>Промо 11</th>
								<th>Промо 12</th>
								<th>Промо 13</th>
								<th>Промо 14</th>
								<th>Промо 15</th>
								<th>Описание 0</th>
								<th>Описание 1</th>
								<th>Описание 2</th>
								<th>Описание 3</th>
								<th>Описание 4</th>
								<th>Описание 5</th>
								<th>Описание 6</th>
								<th>Описание 7</th>
								<th>Описание 8</th>
								<th>Описание 9</th>
								<th>Описание 10</th>
								<th>Описание 11</th>
								<th>Описание 12</th>
								<th>Описание 13</th>
								<th>Описание 14</th>
								<th>Описание 15</th>
								<th>Снимка 0</th>
								<th>Снимка 1</th>
								<th>Снимка 2</th>
								<th>Снимка 3</th>
								<th>Снимка 4</th>
								<th>Снимка 5</th>
								<th>Снимка 6</th>
								<th>Снимка 7</th>
								<th>Снимка 8</th>
								<th>Снимка 9</th>
								<th>Снимка 10</th>
								<th>Снимка 11</th>
								<th>Снимка 12</th>
								<th>Снимка 13</th>
								<th>Снимка 14</th>
								<th>Снимка 15</th>
								<th>Описание в края</th>
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
									echo '<tr><td>' . get_the_ID() . '</td><td>' . get_the_title() . '</td>';
									echo '<td>' . $regular_price . '</td>';
									echo '<td>' . ($sale_price ? $sale_price : '-') . '</td></tr>';
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
<?php
}
