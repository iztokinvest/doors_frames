<?php
/*
Plugin Name: Doors Frames
Plugin URI: https://github.com/iztokinvest/doors_frames
Description: Цени на каси.
Version: 1.6.1
Author: Martin Mladenov
GitHub Plugin URI: https://github.com/iztokinvest/doors_frames
GitHub Branch: main
*/

class WP_Frames_Updater
{
	private $slug;
	private $pluginData;
	private $repo;
	private $githubAPIResult;

	public function __construct($plugin_file)
	{
		add_filter('pre_set_site_transient_update_plugins', [$this, 'set_update_transient']);
		add_filter('plugins_api', [$this, 'set_plugin_info'], 10, 3);
		add_filter('upgrader_post_install', [$this, 'post_install'], 10, 3);
		$this->slug = plugin_basename($plugin_file);

		if (!function_exists('get_plugin_data')) {
			require_once(ABSPATH . 'wp-admin/includes/plugin.php');
		}

		$this->pluginData = get_plugin_data($plugin_file);
		$this->repo = 'iztokinvest/doors_frames';
	}

	private function get_repository_info()
	{
		if (is_null($this->githubAPIResult)) {
			$request = 'https://api.github.com/repos/' . $this->repo . '/releases/latest';
			$response = wp_remote_get($request);
			if (is_wp_error($response)) {
				return false;
			}
			$this->githubAPIResult = json_decode(wp_remote_retrieve_body($response));
		}
		return $this->githubAPIResult;
	}

	public function set_update_transient($transient)
	{
		if (empty($transient->checked)) {
			return $transient;
		}
		$this->get_repository_info();
		if ($this->githubAPIResult) {
			$do_update = version_compare($this->githubAPIResult->tag_name, $this->pluginData['Version'], '>');
			if ($do_update) {
				$package = $this->githubAPIResult->zipball_url;
				$transient->response[$this->slug] = (object) [
					'slug' => $this->slug,
					'new_version' => $this->githubAPIResult->tag_name,
					'url' => $this->pluginData['PluginURI'],
					'package' => $package,
				];
			}
		}
		return $transient;
	}

	public function set_plugin_info($false, $action, $response)
	{
		if (empty($response->slug) || $response->slug != $this->slug) {
			return false;
		}
		$this->get_repository_info();
		if ($this->githubAPIResult) {
			$response->last_updated = $this->githubAPIResult->published_at;
			$response->slug = $this->slug;
			$response->plugin_name  = $this->pluginData['Name'];
			$response->version = $this->githubAPIResult->tag_name;
			$response->author = $this->pluginData['AuthorName'];
			$response->homepage = $this->pluginData['PluginURI'];
			$response->download_link = $this->githubAPIResult->zipball_url;
			$response->sections = [
				'description' => $this->pluginData['Description'],
			];
		}
		return $response;
	}

	public function post_install($true, $hook_extra, $result)
	{
		global $wp_filesystem;
		$plugin_folder = WP_PLUGIN_DIR . '/' . dirname($this->slug);
		$wp_filesystem->move($result['destination'], $plugin_folder);
		$result['destination'] = $plugin_folder;
		activate_plugin($this->slug);
		return $result;
	}
}

if (is_admin()) {
	new WP_Frames_Updater(__FILE__);
}

function create_tables()
{
	global $wpdb;

	$frames_table = $wpdb->prefix . 'doors_frames';
	$products_table = $wpdb->prefix . 'doors_frames_products';
	$tabs_table = $wpdb->prefix . 'doors_frames_tabs';

	$charset_collate = $wpdb->get_charset_collate();

	$sql_frames = "CREATE TABLE IF NOT EXISTS $frames_table (
		id int(11) NOT NULL AUTO_INCREMENT,
		product_id int(11) NOT NULL,
		frame_id int(11) NOT NULL,
		frame_price float NULL,
		frame_promo_price float NULL,
		frame_description varchar(500) NOT NULL,
		frame_image varchar(250) DEFAULT NULL,
		active tinyint(1) NOT NULL DEFAULT 1,
		PRIMARY KEY (id)
	) $charset_collate;";

	$sql_products = "CREATE TABLE IF NOT EXISTS $products_table (
		id int(11) NOT NULL AUTO_INCREMENT,
		product_id int(11) NOT NULL,
		product_price float NULL,
		product_promo_price float NULL,
		PRIMARY KEY (id),
		UNIQUE KEY (product_id)
	) $charset_collate;";

	$sql_tabs = "CREATE TABLE IF NOT EXISTS $tabs_table (
		id int(11) NOT NULL AUTO_INCREMENT,
		category_id int(11) NOT NULL,
		tab_title varchar(250) DEFAULT NULL,
		table_text varchar(500) NOT NULL,
		PRIMARY KEY (id)
	) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql_frames);
	dbDelta($sql_products);
	dbDelta($sql_tabs);
}
register_activation_hook(__FILE__, 'create_tables');

include_once(plugin_dir_path(__FILE__) . 'includes/enqueue.php');
include_once(plugin_dir_path(__FILE__) . 'includes/menu.php');

include_once(plugin_dir_path(__FILE__) . 'pages/frames_list_page.php');
include_once(plugin_dir_path(__FILE__) . 'pages/frames_tab.php');
