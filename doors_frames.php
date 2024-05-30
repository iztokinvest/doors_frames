<?php
/*
Plugin Name: Doors Frames
Plugin URI: https://github.com/iztokinvest/doors_frames
Description: Цени на каси.
Version: 0.1.0
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

	$charset_collate = $wpdb->get_charset_collate();

	$sql_frames = "CREATE TABLE IF NOT EXISTS $frames_table (
		fp_id int(11) NOT NULL AUTO_INCREMENT,
		product_id int(11) NOT NULL,
		price1 float NOT NULL,
		price2 float NOT NULL,
		price3 float NOT NULL,
		price4 float NOT NULL,
		price5 float NOT NULL,
		price6 float NOT NULL,
		price7 float NOT NULL,
		price8 float NOT NULL,
		price9 float NOT NULL,
		price10 float NOT NULL,
		price11 float NOT NULL,
		price12 float NOT NULL,
		price13 float NOT NULL,
		price14 float NOT NULL,
		price15 float NOT NULL,
		promo1 float NOT NULL,
		promo2 float NOT NULL,
		promo3 float NOT NULL,
		promo4 float NOT NULL,
		promo5 float NOT NULL,
		promo6 float NOT NULL,
		promo7 float NOT NULL,
		promo8 float NOT NULL,
		promo9 float NOT NULL,
		promo10 float NOT NULL,
		promo11 float NOT NULL,
		promo12 float NOT NULL,
		promo13 float NOT NULL,
		promo14 float NOT NULL,
		promo15 float NOT NULL,
		price0_desc varchar(400) NOT NULL,
		price1_desc varchar(400) NOT NULL,
		price2_desc varchar(400) NOT NULL,
		price3_desc varchar(400) NOT NULL,
		price4_desc varchar(400) NOT NULL,
		price5_desc varchar(400) NOT NULL,
		price6_desc varchar(400) NOT NULL,
		price7_desc varchar(400) NOT NULL,
		price8_desc varchar(400) NOT NULL,
		price9_desc varchar(400) NOT NULL,
		price10_desc varchar(400) NOT NULL,
		price11_desc varchar(400) NOT NULL,
		price12_desc varchar(400) NOT NULL,
		price13_desc varchar(400) NOT NULL,
		price14_desc varchar(400) NOT NULL,
		price15_desc varchar(400) NOT NULL,
		pic0 varchar(120) DEFAULT NULL,
		pic1 varchar(120) DEFAULT NULL,
		pic2 varchar(120) DEFAULT NULL,
		pic3 varchar(120) DEFAULT NULL,
		pic4 varchar(120) DEFAULT NULL,
		pic5 varchar(120) DEFAULT NULL,
		pic6 varchar(120) DEFAULT NULL,
		pic7 varchar(120) DEFAULT NULL,
		pic8 varchar(120) DEFAULT NULL,
		pic9 varchar(120) DEFAULT NULL,
		pic10 varchar(120) DEFAULT NULL,
		pic11 varchar(120) DEFAULT NULL,
		pic12 varchar(120) DEFAULT NULL,
		pic13 varchar(120) DEFAULT NULL,
		pic14 varchar(120) DEFAULT NULL,
		pic15 varchar(120) DEFAULT NULL,
		description varchar(500) NOT NULL,
		PRIMARY KEY (fp_id)
	) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql_frames);
}
register_activation_hook(__FILE__, 'create_tables');

include_once(plugin_dir_path(__FILE__) . 'includes/enqueue.php');
include_once(plugin_dir_path(__FILE__) . 'includes/menu.php');

include_once(plugin_dir_path(__FILE__) . 'pages/frames_list_page.php');