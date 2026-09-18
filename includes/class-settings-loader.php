<?php
/**
 * Zoltiq Chatbot for WordPress - Settings Page
 *
 * Provides the admin interface for configuring the chatbot,
 * including API settings, UI options, and other parameters.
 *
 * @package zoltiq-chatbot
 */


namespace Zoltiq\Agents;

use Zoltiq\Agents\Utils;


/**
 * Handles registration and rendering of the plugin settings page.
 */
class Settings_Page {
	
	/**
	 * Cached plugin options.
	 *
	 * @var array
	 */
	private $options;
	
	/**
	 * The tool states option name.
	 */
	private const OPTION_SETTINGS = 'zoltiq_agents_options';

	/**
	 * Cached LLM model list option key.
	 */
	private const CACHE_MODEL_LIST = 'zoltiq_agents_cache_model_list';

	/**
	 * Providers storage option.
	 */
	private const OPTION_API_PROVIDERS = 'zoltiq_agents_api_providers';

	/**
	 * Option name where database meta.
	 */
	public const OPTION_DB_VERSION = 'zoltiq_agents_database_version';

	/**
	 * Constructor.
	 *
	 * Registers admin hooks for menu and assets.
	 */
	public function __construct() {
		// Hooki
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

	}

	/**
	 * Add a settings link to the plugin entry in the plugins list.
	 *
	 * @param array $links Existing plugin action links.
	 * @return array Modified plugin action links with a settings page link.
	 */
	public function plugin_action_links($links) {
		$settings_link = '<a href="../wp-admin/options-general.php?page=zoltiq-agents">' . __('Settings', 'default') . '</a>';
		array_unshift($links, $settings_link);
		return $links;
	}

	/**
	 * Adds the Agents settings page to the admin menu.
 	 *
	 * @return void
	 */ 
	public function add_settings_page() {
		add_menu_page(
			'Zoltiq Agents Settings',
			'Zoltiq Agents',
			'manage_options',
			'zoltiq-agents',
			[ $this, 'render_settings_page' ],
			'dashicons-admin-generic',
			30
		);
	}


	/**
	 * Registers and enqueues styles and scripts for the admin panel.
	 *
	 * @param string $hook The current admin page.
	 * @return void
	*/
	public function enqueue_assets($hook) {
		if ($hook !== 'toplevel_page_zoltiq-agents') {
			return;
		}

		wp_enqueue_style(
			'agents-settings-admin-style',
			plugins_url('assets/css/admin-panel.min.css', dirname(__FILE__, 1)),
			['wp-color-picker'],
			'1.2.98'
		);

		wp_register_script(
			'agents-settings-admin-script',
			plugins_url('assets/js/admin-panel.min.js', dirname(__FILE__, 1)),
			['wp-api', 'wp-color-picker'], 
			'1.6.99',
			true
		);
		
		// Prepare configuration data for frontend.
		$this->options = get_option( self::OPTION_SETTINGS, [] );
	
		// Load cached model list
		$model_cache = get_option(self::CACHE_MODEL_LIST, []);

		// Loading available providers
		$providers = get_option(self::OPTION_API_PROVIDERS, []);

		$this->options['api_page']['model_cache'] = $model_cache;
		$this->options['api_page']['providers'] = $providers;
			
		// Add locale
		$this->options['locale'] = get_locale();
		
		// Add REST nonce
		$this->options['nonce'] = wp_create_nonce( 'wp_rest' );
		
		// Add db_version
		$this->options['db_version'] = get_option(self::OPTION_DB_VERSION, []);

		// Load WordPress pages for chatbot visibility settings.
		$pages = get_pages(); 
		$this->options['chatbot_page']['pages'] = [];
		foreach ($pages as $page) {
			$this->options['chatbot_page']['pages'][] = array(
				'title' => esc_html( $page->post_title ),
				'slug'  => esc_html( $page->post_name ),
				'chatbot_enable' => !in_array(
            		$page->post_name,
            		$this->options['chatbot_page']['disable_chatbot_pages'] ?? [],
            		true
        		)
			);
		}
		
		wp_localize_script('agents-settings-admin-script', 'chatParams', $this->options);
		wp_enqueue_script('agents-settings-admin-script');
	}
   

	/**
	 * Renders the settings page container.
	 *
	 * Also validates environment requirements (e.g., DB version)
	 * and displays any admin notices.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		
		if (!current_user_can('manage_options')) {
			return;
		}

		// Validate database version
		Utils::check_minimum_db_version();
		settings_errors('agents_settings_messages');
		
	}

}