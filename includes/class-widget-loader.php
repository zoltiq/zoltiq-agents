<?php


namespace Zoltiq\Agents;

/**
 * Main WordPress Widget_Loader class.
 *
 * Handles widget initialization, rendering, and script/style enqueueing for both admin and front-end.
 */
class Widget_Loader {

	private $options;

	private $agents;

	/**
	 * Option name where agents settings are stored in the database.
	 */
	private const OPTION_SETTINGS = 'zoltiq_agents_options';
	
	/**
	 * Option name where agents are stored in the database.
	 */
	private const OPTION_AGENTS = 'zoltiq_agents';

	/**
	 * Constructor.
	 *
	 * Initializes plugin options and agents, and registers enqueue hooks for scripts/styles.
	 */
	public function __construct() {
		
		$this->options = get_option( self::OPTION_SETTINGS, [] );
		$this->agents  = get_option( self::OPTION_AGENTS, [] );
	
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ], 20 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ], 20 );
	}

	/**
	 * Enqueues chatbot scripts and styles.
	 *
	 * Determines page type (shop, blog, or custom) and renders chatbot HTML if not disabled for this page.
	 *
	 * @return void
	 */
	public function enqueue() {
		
		if ( is_admin() ) {

			// If the 'show_in_admin' key exists in options, use it to display the chatbot
			if ( empty( $this->options['chatbot_page']['show_in_admin'] ) ) {
				return;
			}
			$this->render_html();

		} else {
			global $wp_query;
			$page_type = '';
			
			// Determine page type
			if(function_exists('is_shop') && ( is_shop() || is_product() || is_product_category() || is_product_tag() )) {
				// WooCommerce shop page
				$page_type = get_post_field( 'post_name', wc_get_page_id('shop') ); 
			} elseif ( is_home() || is_single() || is_archive() || is_category() || is_tag() ) {
				// Blog page
				$page_type = get_post_field('post_name', get_option('page_for_posts'));
			} elseif( isset( $wp_query->queried_object->post_name ) ) {
				$page_type = $wp_query->queried_object->post_name;
			} 

			// Render HTML if the chatbot is not disabled on this page
			$disable_pages =  $this->options['chatbot_page']['disable_chatbot_pages'] ?? [];
			if (!in_array($page_type, $disable_pages, true)) {
				$this->render_html();
			}

		}

	}

	/**
	 * Generates the HTML and loading logic for the agents iframe.
	 *
	 * Sets up parameters, authentication code, locale, user role, and enqueues styles and scripts.
	 *
	 * @return void
	 */
	private function render_html() {

		wp_enqueue_style(
        	'agents-frame-style',
        	plugins_url('assets/css/frame-style.css', dirname(__FILE__, 1)),
        	array(),
        	'1.40'
    	);
	

		$current_locale = get_locale(); 
		$lang_code = substr($current_locale, 0, 2);
       
		$chatbot_page = $this->options['chatbot_page'] ?? [];
		$style_page   = $this->options['style_page'] ?? [];
		$llm_models   = $this->options['api_page']['agents'] ?? [];

		$enable_raw_tool = current_user_can('manage_options') ? ($chatbot_page['enable_raw_tool'] ?? null) : false;
		$is_admin  = is_admin() && current_user_can('manage_options');

		$agents = $is_admin ? $this->agents['backend_agents'] : $this->agents['frontend_agents'];
		$select_id = $agents['select_id'];
		$agent = [];
		foreach ( $agents['items'] as $_agent ) {
			if ( $_agent['id'] === $select_id ) {
				$agent = $_agent;
				break;
			}
		}
	
		$params = array_merge(
			[
				'rest_url'           => esc_url_raw(rest_url()),
				'nonce'              => wp_create_nonce('wp_rest'),
				'wc_store_api_nonce' => wp_create_nonce('wc_store_api'),
				'locale'             => $lang_code,
				'user_role'          => $this->get_role(),
				'plugin_dir_url'     => ZOLTIQ_AGENTS_URL,
				'site_icon_url'      => get_site_icon_url(100),
				'is_admin'           => $is_admin
			],
			array_filter([
				'title'            => $chatbot_page['title'] ?? null,
				'initial_greeting' => $chatbot_page['initial_greeting'] ?? null,
				'emoji'            => $chatbot_page['emoji'] ?? null,
				'start_status'     => $chatbot_page['start_status'] ?? null,
				'agent'            => $agent,
				'enable_raw_tool'  => $enable_raw_tool,
				'llm_models'	   => $llm_models	
			], fn($v) => $v !== null),
			$style_page
		);

		
		
		$json_params = wp_json_encode($params);
						
		$is_dev = false; 
		
		if($is_dev){
		
			// Load Vite client for development (required for HMR)
			wp_enqueue_script('vite-client', 'http://localhost:5173/@vite/client', [], null, true);

			add_filter('script_loader_tag', function($tag, $handle, $src) {
				
				// Convert specific scripts to module type
				if (in_array($handle, ['vite-client'])) {
					return '<script type="module" src="' . esc_url($src) . '" id="' . esc_attr($handle) . '"></script>';
				}
					return $tag;
			}, 10, 3);

			$module_url = "http://localhost:5173/src/main.js";
		} else {
			$module_url = plugins_url('assets/js/chat.bundle.es.js?ver=2.69', dirname(__FILE__, 1));
		}
				
		
		$inline_module_script = "
			import { createChat } from '$module_url';
			document.addEventListener('DOMContentLoaded', () => {
				createChat($json_params);
			});
		";

			
		// Choose the appropriate footer hook
		$footer_hook = is_admin() ? 'admin_footer' : 'wp_footer';
		
		// Inject iframe into the footer
		add_action( $footer_hook, function() use ($inline_module_script) {

			?>
			
			<iframe style="display: none;" id="chatbotFrame"
				srcdoc="<?php
					echo esc_attr( "

					    <link rel='preconnect' href='https://fonts.googleapis.com'>
						<link rel='preconnect' href='https://fonts.gstatic.com' crossorigin>
						<link href='https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap'	rel='stylesheet'>
   						<link rel='stylesheet' href='" . plugins_url( 'assets/css/widget.min.css?ver=3.24', dirname( __FILE__, 1 ) ) . "'>						<script src='https://bundle.run/buffer@6.0.3'></script>
   						<script>window.Buffer = window.buffer.Buffer;</script>
						<script type='module'>{$inline_module_script}</script>
						
					");
				?>">
			</iframe>

			<?php
		});
	}

	/**
	 * Returns the current user's primary WordPress role.
	 *
	 * @return string Role slug, e.g. 'editor', or empty string if not logged in.
	 */
	private function get_role() {
		$user = wp_get_current_user();

		if ( $user->ID === 0 ) {
			// No logged-in user
			return '';
		}

		// Array of role slugs, e.g., ['editor']
    	$roles = $user->roles;

		// Return the first role
		$primary_role = ! empty( $roles ) ? $roles[0] : '';
		return $primary_role;
	}

}