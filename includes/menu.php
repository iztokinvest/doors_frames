<?php

function menu()
{
	add_menu_page(
		'Каси',
		'Каси',
		'manage_options',
		'frames-list-page',
		'frames_list_page',
		'dashicons-money-alt',
		1.1
	);
}
add_action('admin_menu', 'menu');
