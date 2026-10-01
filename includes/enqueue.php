<?php

if (is_admin() && (!isset($_GET['page']) || $_GET['page'] !== 'doors-frames-price-history')) {
	wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css', array(), '5.0.2');
	wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js', array('jquery'), null, true);

	wp_enqueue_style('vanillajs-datepicker-css', 'https://cdn.jsdelivr.net/npm/vanillajs-datepicker@1.3.4/dist/css/datepicker.min.css');
	wp_enqueue_script('vanillajs-datepicker-js', 'https://cdn.jsdelivr.net/npm/vanillajs-datepicker@1.3.4/dist/js/datepicker.min.js', array(), null, true);

	// SlimSelect - using local files to avoid CDN loading issues
	wp_enqueue_style('slim-select-css', plugin_dir_url(__FILE__) . '../assets/lib/slimselect/slimselect.css', array(), '2.8.2');
	wp_enqueue_script('slim-select-js', plugin_dir_url(__FILE__) . '../assets/lib/slimselect/slimselect.min.js', array(), '2.8.2', true);

	wp_enqueue_script('awesome-notifications-js', plugin_dir_url(__FILE__) .
		'../assets/js/awesome_notifications.js', array(), '1.0', true);
	wp_enqueue_style('awesome-notifications-css', plugin_dir_url(__FILE__) .
		'../assets/css/awesome_notifications.css', array(), '1.0');

	$css_version = filemtime(plugin_dir_path(__FILE__) . '../assets/css/main.css');
	wp_enqueue_style('main-css', plugin_dir_url(__FILE__) .
		'../assets/css/main.css', array(), $css_version);
	$js_version = filemtime(plugin_dir_path(__FILE__) . '../assets/js/main.js');
	wp_enqueue_script('main-js', plugins_url('../assets/js/main.js', __FILE__), array('jquery', 'slim-select-js'), $js_version, true);
}

function doors_frames_enqueue_history_filters($hook)
{
	if (strpos($hook, '_page_doors-frames-price-history') === false) {
		return;
	}
	$base = plugin_dir_url(__FILE__) . '../assets/';
	wp_enqueue_style('slim-select-css', $base . 'lib/slimselect/slimselect.css', array(), '2.8.2');
	wp_enqueue_script('slim-select-js', $base . 'lib/slimselect/slimselect.min.js', array(), '2.8.2', true);
	wp_enqueue_style('doors-frames-datatables', $base . 'lib/datatables/dataTables.dataTables.min.css', array(), '3.1.2');
	wp_enqueue_script('doors-frames-datatables', $base . 'lib/datatables/dataTables.min.js', array(), '3.1.2', true);
	wp_enqueue_style('doors-frames-history', $base . 'css/price_history.css', array('slim-select-css', 'doors-frames-datatables'), filemtime(__DIR__ . '/../assets/css/price_history.css'));
	wp_enqueue_script('doors-frames-history', $base . 'js/price_history.js', array('slim-select-js', 'doors-frames-datatables'), filemtime(__DIR__ . '/../assets/js/price_history.js'), true);
	wp_localize_script('doors-frames-history', 'doorsFramesHistoryFilters', array(
		'url' => admin_url('admin-ajax.php'),
		'nonce' => wp_create_nonce('doors_frames_history_filters'),
		'tableNonce' => wp_create_nonce('doors_frames_history_table'),
	));
}
add_action('admin_enqueue_scripts', 'doors_frames_enqueue_history_filters');
