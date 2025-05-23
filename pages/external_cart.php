<?php

// Prevent direct access to the file
if (!defined('ABSPATH')) {
	exit;
}

// Check for active WooCommerce
function woo_external_cart_check_dependencies()
{
	if (!class_exists('WooCommerce')) {
		add_action('admin_notices', function () {
			echo '<div class="error"><p>' . __('WooCommerce External Cart requires WooCommerce to be installed and active.', 'woo-external-cart') . '</p></div>';
		});
		return false;
	}
	return true;
}

// Allow draft products to be added to cart
add_filter('woocommerce_is_purchasable', function ($is_purchasable, $product) {
	$is_external_cart_product = get_post_meta($product->get_id(), '_virtual', true) === 'yes' && get_post_meta($product->get_id(), '_visibility', true) === 'hidden';
	if ($is_external_cart_product && $product->get_status() === 'draft') {
		return true;
	}
	return $is_purchasable;
}, 10, 2);

// Register REST API endpoint
add_action('rest_api_init', function () {
	if (!woo_external_cart_check_dependencies()) {
		return;
	}

	register_rest_route('woo-external-cart/v1', '/add-to-cart/', array(
		'methods' => 'POST',
		'callback' => 'woo_external_cart_add_to_cart',
		'permission_callback' => '__return_true',
	));
});

// Handle JSON request for multiple products with quantities
function woo_external_cart_add_to_cart(WP_REST_Request $request)
{
	$data = $request->get_json_params();

	// Fallback to query parameters if JSON is empty
	if (empty($data)) {
		$data = array(
			'products' => array(
				array(
					'title' => $request->get_param('title'),
					'content' => $request->get_param('content'),
					'price' => $request->get_param('price'),
					'quantity' => $request->get_param('quantity'),
				),
			),
			'first_name' => $request->get_param('first_name'),
			'last_name' => $request->get_param('last_name'),
			'address' => $request->get_param('address'),
			'city' => $request->get_param('city'),
			'phone' => $request->get_param('phone'),
			'email' => $request->get_param('email'),
			'postcode' => $request->get_param('postcode'),
		);
	}

	// Validate required customer fields
	$required_customer_fields = array('first_name', 'last_name', 'address', 'city', 'phone');
	foreach ($required_customer_fields as $field) {
		if (empty($data[$field])) {
			return new WP_Error('invalid_data', sprintf(__('Missing required customer field: %s', 'woo-external-cart'), $field), array('status' => 400));
		}
	}

	// Validate products array
	if (empty($data['products']) || !is_array($data['products'])) {
		return new WP_Error('invalid_data', __('Missing or invalid products array', 'woo-external-cart'), array('status' => 400));
	}

	$first_name = sanitize_text_field($data['first_name']);
	$last_name = sanitize_text_field($data['last_name']);
	$address = sanitize_text_field($data['address']);
	$city = sanitize_text_field($data['city']);
	$phone = sanitize_text_field($data['phone']);
	$email = isset($data['email']) ? sanitize_email($data['email']) : '';
	$postcode = !empty($data['postcode']) ? sanitize_text_field($data['postcode']) : '1000'; // Default to Sofia postcode
	$product_ids = array();

	// Process each product
	foreach ($data['products'] as $index => $product_data) {
		// Validate required product fields
		$required_product_fields = array('title', 'content', 'price');
		foreach ($required_product_fields as $field) {
			if (empty($product_data[$field])) {
				return new WP_Error('invalid_data', sprintf(__('Missing required field for product %d: %s', 'woo-external-cart'), $index + 1, $field), array('status' => 400));
			}
		}

		$product_title = sanitize_text_field($product_data['title']);
		$product_content = sanitize_text_field($product_data['content']);
		$product_price = floatval($product_data['price']);
		$quantity = isset($product_data['quantity']) ? max(1, intval($product_data['quantity'])) : 1; // Default to 1 if not provided

		// Create draft product
		$product_id = wp_insert_post(array(
			'post_title'   => $product_title,
			'post_content' => $product_content,
			'post_status'  => 'draft',
			'post_type'    => 'product',
			'post_author'  => 1,
		));

		if (is_wp_error($product_id)) {
			return new WP_Error('product_creation_failed', sprintf(__('Failed to create product %d', 'woo-external-cart'), $index + 1), array('status' => 500));
		}

		// Set product type and meta data
		wp_set_object_terms($product_id, 'simple', 'product_type');
		update_post_meta($product_id, '_price', $product_price);
		update_post_meta($product_id, '_regular_price', $product_price);
		update_post_meta($product_id, '_visibility', 'hidden');
		update_post_meta($product_id, '_virtual', 'yes');
		update_post_meta($product_id, '_stock_status', 'instock');
		update_post_meta($product_id, '_manage_stock', 'no');
		update_post_meta($product_id, '_sold_individually', 'no'); // Allow multiple quantities

		$product_ids[] = array(
			'id' => $product_id,
			'quantity' => $quantity,
		);
	}

	// Add products to cart
	if (class_exists('WooCommerce')) {
		if (is_null(WC()->session)) {
			WC()->session = new WC_Session_Handler();
			WC()->session->init();
		}

		if (is_null(WC()->cart)) {
			WC()->cart = new WC_Cart();
		}

		if (is_null(WC()->customer)) {
			WC()->customer = new WC_Customer(0, true);
		}

		// Set customer details
		WC()->customer->set_billing_first_name($first_name);
		WC()->customer->set_billing_last_name($last_name);
		WC()->customer->set_billing_address_1($address);
		WC()->customer->set_billing_city($city);
		WC()->customer->set_billing_phone($phone);
		WC()->customer->set_billing_email($email);
		WC()->customer->set_billing_postcode($postcode);
		WC()->customer->set_shipping_first_name($first_name);
		WC()->customer->set_shipping_last_name($last_name);
		WC()->customer->set_shipping_address_1($address);
		WC()->customer->set_shipping_city($city);
		WC()->customer->set_shipping_phone($phone);
		WC()->customer->set_shipping_postcode($postcode);
		WC()->customer->save();

		// Clear cart and add products
		WC()->cart->empty_cart();
		$all_added = true;
		foreach ($product_ids as $index => $product) {
			$added = WC()->cart->add_to_cart($product['id'], $product['quantity']);
			if (!$added) {
				$all_added = false;
				$wc_product = wc_get_product($product['id']);
				$debug_info = array(
					'product_id' => $product['id'],
					'quantity' => $product['quantity'],
					'is_purchasable' => $wc_product ? $wc_product->is_purchasable() : 'product_not_found',
					'stock_status' => get_post_meta($product['id'], '_stock_status', true),
					'price' => get_post_meta($product['id'], '_price', true),
					'status' => get_post_status($product['id']),
				);
				error_log('Failed to add product to cart: ' . print_r($debug_info, true));
			}
		}

		if ($all_added) {
			// Create a new WooCommerce order
			$order = wc_create_order();
			if (is_wp_error($order)) {
				foreach ($product_ids as $product) {
					wp_delete_post($product['id'], true);
				}
				return new WP_Error('order_creation_failed', __('Failed to create order', 'woo-external-cart'), array('status' => 500));
			}

			// Add products to the order
			foreach ($product_ids as $product) {
				$wc_product = wc_get_product($product['id']);
				if ($wc_product) {
					$order->add_product($wc_product, $product['quantity']);
				}
			}

			// Set customer details in the order
			$order->set_billing_first_name($first_name);
			$order->set_billing_last_name($last_name);
			$order->set_billing_address_1($address);
			$order->set_billing_city($city);
			$order->set_billing_phone($phone);
			$order->set_billing_email($email);
			$order->set_billing_postcode($postcode);
			$order->set_shipping_first_name($first_name);
			$order->set_shipping_last_name($last_name);
			$order->set_shipping_address_1($address);
			$order->set_shipping_city($city);
			$order->set_shipping_phone($phone);
			$order->set_shipping_postcode($postcode);

			// Set order status to 'pending'
			$order->set_status('pending');
			$order->calculate_totals();
			$order->save();

			// Generate order pay URL
			$order_key = $order->get_order_key();
			$order_id = $order->get_id();
			$pay_url = wc_get_endpoint_url('order-pay', $order_id, wc_get_checkout_url()) . '?pay_for_order=true&key=' . $order_key;

			// Generate cart key and store cart data
			$cart_key = wp_generate_uuid4();
			$cart_data = array(
				'products' => array_map(function ($product) {
					return array(
						'product_id' => $product['id'],
						'quantity' => $product['quantity'],
					);
				}, $product_ids),
				'customer' => array(
					'first_name' => $first_name,
					'last_name' => $last_name,
					'address' => $address,
					'city' => $city,
					'phone' => $phone,
					'email' => $email,
					'postcode' => $postcode,
				),
			);
			set_transient('woo_external_cart_' . $cart_key, $cart_data, HOUR_IN_SECONDS);

			$checkout_url = add_query_arg('cart_key', $cart_key, wc_get_checkout_url());

			// Clear cart after order creation
			WC()->cart->empty_cart();

			return array(
				'status' => 'success',
				'message' => __('Products added to cart and order created', 'woo-external-cart'),
				'checkout_url' => $checkout_url,
				'pay_url' => $pay_url,
				'cart_key' => $cart_key,
				'order_id' => $order_id,
				'product_ids' => array_column($product_ids, 'id'),
			);
		} else {
			foreach ($product_ids as $product) {
				wp_delete_post($product['id'], true);
			}
			return new WP_Error('cart_add_failed', __('Failed to add one or more products to cart', 'woo-external-cart'), array('status' => 500));
		}
	} else {
		foreach ($product_ids as $product) {
			wp_delete_post($product['id'], true);
		}
		return new WP_Error('woocommerce_not_active', __('WooCommerce is not active', 'woo-external-cart'), array('status' => 500));
	}
}

// Restore cart and customer data on checkout page
add_action('wp', function () {
	if (!woo_external_cart_check_dependencies()) {
		return;
	}

	if (is_checkout() && isset($_GET['cart_key'])) {
		$cart_key = sanitize_text_field($_GET['cart_key']);
		$cart_data = get_transient('woo_external_cart_' . $cart_key);

		if ($cart_data) {
			if (is_null(WC()->session)) {
				WC()->session = new WC_Session_Handler();
				WC()->session->init();
			}

			if (is_null(WC()->cart)) {
				WC()->cart = new WC_Cart();
			}

			if (is_null(WC()->customer)) {
				WC()->customer = new WC_Customer(0, true);
			}

			if (!empty($cart_data['customer'])) {
				WC()->customer->set_billing_first_name($cart_data['customer']['first_name']);
				WC()->customer->set_billing_last_name($cart_data['customer']['last_name']);
				WC()->customer->set_billing_address_1($cart_data['customer']['address']);
				WC()->customer->set_billing_city($cart_data['customer']['city']);
				WC()->customer->set_billing_phone($cart_data['customer']['phone']);
				WC()->customer->set_billing_email($cart_data['customer']['email']);
				WC()->customer->set_billing_postcode($cart_data['customer']['postcode']);
				WC()->customer->set_shipping_first_name($cart_data['customer']['first_name']);
				WC()->customer->set_shipping_last_name($cart_data['customer']['last_name']);
				WC()->customer->set_shipping_address_1($cart_data['customer']['address']);
				WC()->customer->set_shipping_city($cart_data['customer']['city']);
				WC()->customer->set_shipping_phone($cart_data['customer']['phone']);
				WC()->customer->set_shipping_postcode($cart_data['customer']['postcode']);
				WC()->customer->save();
			}

			WC()->cart->empty_cart();
			$all_added = true;
			foreach ($cart_data['products'] as $product) {
				$added = WC()->cart->add_to_cart($product['product_id'], $product['quantity']);
				if (!$added) {
					$all_added = false;
					error_log('Failed to restore product to cart: ' . print_r($product, true));
				}
			}

			if (!$all_added) {
				error_log('Failed to restore one or more products to cart: ' . print_r($cart_data, true));
			}

			delete_transient('woo_external_cart_' . $cart_key);
		}
	}
});

// Clean up old draft products
add_action('wp', function () {
	if (!woo_external_cart_check_dependencies()) {
		return;
	}

	$args = array(
		'post_type' => 'product',
		'post_status' => 'draft',
		'meta_query' => array(
			array(
				'key' => '_visibility',
				'value' => 'hidden',
			),
			array(
				'key' => '_virtual',
				'value' => 'yes',
			),
		),
		'date_query' => array(
			array(
				'before' => '1 hour ago',
			),
		),
	);

	$old_products = get_posts($args);
	foreach ($old_products as $product) {
		wp_delete_post($product->ID, true);
	}
});

// Test shortcode for adding multiple products with quantities
add_shortcode('woo_external_cart_test', function () {
	ob_start();
?>
	<div>
		<h2><?php _e('Test External Cart', 'woo-external-cart'); ?></h2>
		<div id="product-list">
			<div class="product-entry">
				<h3><?php _e('Product 1', 'woo-external-cart'); ?></h3>
				<div>
					<label for="title_1"><?php _e('Title:', 'woo-external-cart'); ?></label>
					<input type="text" id="title_1" name="title_1" required>
				</div>
				<div>
					<label for="content_1"><?php _e('Content:', 'woo-external-cart'); ?></label>
					<textarea id="content_1" name="content_1" required></textarea>
				</div>
				<div>
					<label for="price_1"><?php _e('Price:', 'woo-external-cart'); ?></label>
					<input type="number" id="price_1" name="price_1" step="0.01" required>
				</div>
				<div>
					<label for="quantity_1"><?php _e('Quantity:', 'woo-external-cart'); ?></label>
					<input type="number" id="quantity_1" name="quantity_1" min="1" value="1" required>
				</div>
			</div>
		</div>
		<button onclick="addProductField()"><?php _e('Add Another Product', 'woo-external-cart'); ?></button>
		<hr>
		<div>
			<label for="first_name"><?php _e('First Name:', 'woo-external-cart'); ?></label>
			<input type="text" id="first_name" name="first_name" required>
		</div>
		<div>
			<label for="last_name"><?php _e('Last Name:', 'woo-external-cart'); ?></label>
			<input type="text" id="last_name" name="last_name" required>
		</div>
		<div>
			<label for="address"><?php _e('Address:', 'woo-external-cart'); ?></label>
			<input type="text" id="address" name="address" required>
		</div>
		<div>
			<label for="city"><?php _e('City:', 'woo-external-cart'); ?></label>
			<input type="text" id="city" name="city" required>
		</div>
		<div>
			<label for="phone"><?php _e('Phone:', 'woo-external-cart'); ?></label>
			<input type="text" id="phone" name="phone" required>
		</div>
		<div>
			<label for="email"><?php _e('Email:', 'woo-external-cart'); ?></label>
			<input type="email" id="email" name="email">
		</div>
		<div>
			<label for="postcode"><?php _e('Postcode:', 'woo-external-cart'); ?></label>
			<input type="text" id="postcode" name="postcode" placeholder="1000">
		</div>
		<button onclick="testAddToCart()"><?php _e('Add Products to Cart', 'woo-external-cart'); ?></button>
		<script>
			let productCount = 1;

			function addProductField() {
				productCount++;
				const productList = document.getElementById('product-list');
				const newProduct = document.createElement('div');
				newProduct.className = 'product-entry';
				newProduct.innerHTML = `
                    <h3><?php _e('Product', 'woo-external-cart'); ?> ${productCount}</h3>
                    <div>
                        <label for="title_${productCount}"><?php _e('Title:', 'woo-external-cart'); ?></label>
                        <input type="text" id="title_${productCount}" name="title_${productCount}" required>
                    </div>
                    <div>
                        <label for="content_${productCount}"><?php _e('Content:', 'woo-external-cart'); ?></label>
                        <textarea id="content_${productCount}" name="content_${productCount}" required></textarea>
                    </div>
                    <div>
                        <label for="price_${productCount}"><?php _e('Price:', 'woo-external-cart'); ?></label>
                        <input type="number" id="price_${productCount}" name="price_${productCount}" step="0.01" required>
                    </div>
                    <div>
                        <label for="quantity_${productCount}"><?php _e('Quantity:', 'woo-external-cart'); ?></label>
                        <input type="number" id="quantity_${productCount}" name="quantity_${productCount}" min="1" value="1" required>
                    </div>
                `;
				productList.appendChild(newProduct);
			}

			function testAddToCart() {
				const products = [];
				for (let i = 1; i <= productCount; i++) {
					products.push({
						title: document.getElementById(`title_${i}`).value,
						content: document.getElementById(`content_${i}`).value,
						price: parseFloat(document.getElementById(`price_${i}`).value),
						quantity: parseInt(document.getElementById(`quantity_${i}`).value) || 1,
					});
				}

				const data = {
					products: products,
					first_name: document.getElementById('first_name').value,
					last_name: document.getElementById('last_name').value,
					address: document.getElementById('address').value,
					city: document.getElementById('city').value,
					phone: document.getElementById('phone').value,
					email: document.getElementById('email').value,
					postcode: document.getElementById('postcode').value || '1000'
				};

				for (const key in data) {
					if (key !== 'products' && key !== 'email' && key !== 'postcode' && !data[key]) {
						alert(`Please fill in all required customer fields. Missing: ${key}`);
						return;
					}
				}

				for (let i = 0; i < products.length; i++) {
					for (const key in products[i]) {
						if (key !== 'quantity' && !products[i][key]) {
							alert(`Please fill in all fields for product ${i + 1}. Missing: ${key}`);
							return;
						}
					}
				}

				fetch('<?php echo esc_url(rest_url('woo-external-cart/v1/add-to-cart/')); ?>', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
						},
						body: JSON.stringify(data)
					})
					.then(response => response.json())
					.then(data => {
						if (data.status === 'success') {
							alert('Checkout link: ' + data.checkout_url + '\nPayment link: ' + data.pay_url + '\nShare these links with someone to complete the payment.');
							window.location.href = data.checkout_url;
						} else {
							alert('Error: ' + (data.message || 'Unknown error') + (data.debug_info ? '\nDebug Info: ' + JSON.stringify(data.debug_info) : ''));
						}
					})
					.catch(error => {
						alert('Error: ' + error.message);
					});
			}
		</script>
	</div>
<?php
	return ob_get_clean();
});
?>