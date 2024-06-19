<?php

wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css', array(), '5.0.2');
wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js', array('jquery'), null, true);

wp_enqueue_style('vanillajs-datepicker-css', 'https://cdn.jsdelivr.net/npm/vanillajs-datepicker@1.3.4/dist/css/datepicker.min.css');
wp_enqueue_script('vanillajs-datepicker-js', 'https://cdn.jsdelivr.net/npm/vanillajs-datepicker@1.3.4/dist/js/datepicker.min.js', array(), null, true);

wp_enqueue_style('slim-select-css', 'https://unpkg.com/slim-select@latest/dist/slimselect.css');
wp_enqueue_script('slim-select-js', 'https://unpkg.com/slim-select@latest/dist/slimselect.min.js', array(), null, true);

wp_enqueue_script('awesome-notifications-js', plugin_dir_url(__FILE__) .
	'../assets/js/awesome_notifications.js', array(), '1.0', true);
wp_enqueue_style('awesome-notifications-css', plugin_dir_url(__FILE__) .
	'../assets/css/awesome_notifications.css', array(), '1.0');

$css_version = filemtime(plugin_dir_path(__FILE__) . '../assets/css/main.css');
wp_enqueue_style('main-css', plugin_dir_url(__FILE__) .
'../assets/css/main.css', array(), $css_version);
$js_version = filemtime(plugin_dir_path(__FILE__) . '../assets/js/main.js');
wp_enqueue_script('main-js', plugins_url('../assets/js/main.js', __FILE__), array('jquery'), $js_version, true);
