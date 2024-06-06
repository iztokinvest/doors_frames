<?php

function menu()
{
	add_menu_page(
		'Цени и Каси',
		'Цени и Каси',
		'manage_options',
		'frames-list-page',
		'frames_list_page',
		'dashicons-money-alt',
		1.1
	);
}
add_action('admin_menu', 'menu');
