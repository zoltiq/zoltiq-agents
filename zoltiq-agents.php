<?php
/**
 * Plugin Name: Zoltiq Agents
 * Description: Build and deploy AI agents for WordPress with conversational chat, widgets, workflows, and tool integrations.
 * Version:     1.8.1
 * Author:      Sebastian Rakowicz
 * Author URI:  https://zoltiq.com
 * License:     GPLv3 or later
 * Text Domain: zoltiq-agents
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin Constants
 */
define('ZOLTIQ_AGENTS_VERSION', '1.8.1');
define('ZOLTIQ_AGENTS_PATH', plugin_dir_path( __FILE__ ) );
define('ZOLTIQ_AGENTS_URL', plugin_dir_url( __FILE__ ) );
define('ZOLTIQ_AGENTS_FILE', __FILE__);
define('ZOLTIQ_AGENTS_BASENAME', plugin_basename(__FILE__));


require_once ZOLTIQ_AGENTS_PATH . 'includes/class-ai-api-proxy.php';
require_once ZOLTIQ_AGENTS_PATH . 'includes/class-widget-loader.php';
require_once ZOLTIQ_AGENTS_PATH . 'includes/class-settings-loader.php';
require_once ZOLTIQ_AGENTS_PATH . 'includes/class-settings.php';
require_once ZOLTIQ_AGENTS_PATH . 'includes/class-utils.php';
require_once ZOLTIQ_AGENTS_PATH . 'includes/class-embedding.php';
require_once ZOLTIQ_AGENTS_PATH . 'includes/class-abilities-manager.php';
require_once ZOLTIQ_AGENTS_PATH . 'includes/exceptions/llm-exception.php';
require_once ZOLTIQ_AGENTS_PATH . 'includes/exceptions/gemini-exception.php';
require_once ZOLTIQ_AGENTS_PATH . 'includes/exceptions/openai-exception.php';
require_once ZOLTIQ_AGENTS_PATH . 'includes/exceptions/anthropic-exception.php';
require_once ZOLTIQ_AGENTS_PATH . 'includes/exceptions/ollama-exception.php';
require_once ZOLTIQ_AGENTS_PATH . 'includes/interfaces/interface-llm-models.php';
require_once ZOLTIQ_AGENTS_PATH . 'includes/interfaces/interface-embeddings.php';
require_once ZOLTIQ_AGENTS_PATH . 'includes/interfaces/interface-ability-tool.php';
require_once ZOLTIQ_AGENTS_PATH . 'includes/services/class-llm-provider-factory.php';
require_once ZOLTIQ_AGENTS_PATH . 'includes/services/class-openai-client.php';
require_once ZOLTIQ_AGENTS_PATH . 'includes/services/class-gemini-client.php';
require_once ZOLTIQ_AGENTS_PATH . 'includes/services/class-anthropic-client.php';
require_once ZOLTIQ_AGENTS_PATH . 'includes/services/class-ollama-client.php';
require_once ZOLTIQ_AGENTS_PATH . 'includes/services/class-resource-discovery.php';
require_once ZOLTIQ_AGENTS_PATH . 'plugin-update-checker/plugin-update-checker.php';
require_once ZOLTIQ_AGENTS_PATH . 'vendor/autoload.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
use Zoltiq\Agents\Services\Resource_Discovery;

/**
 * Initialize plugin update checker.
 *
 * This sets up automatic update checks using an external JSON endpoint
 * that provides plugin metadata and version information.
 */
$details_url = 'https://plugins.zoltiq.com/agents/details-'. get_locale() .'.json';

$updateChecker = PucFactory::buildUpdateChecker(
	$details_url,
	__FILE__,
	'zoltiq-agents'
);

/**
 * Initialize core plugin services.
 *
 * - Registers API proxy hooks (REST endpoints, integrations).
 * - Registers settings-related hooks.
 */
$proxy_instance = new \Zoltiq\Agents\AI_API_Proxy();
$proxy_instance->register_hooks();

$settings_instance = new \Zoltiq\Agents\Settings();
$settings_instance->register_hooks();


$abilities_instance = new \Zoltiq\Agents\Abilities_Manager();
$abilities_instance->register_hooks();

/**
 * Initialize embedding service instance.
 *
 * Responsible for managing embeddings associated with posts.
 */
$embedding_instance = new \Zoltiq\Agents\Embedding_Indexer();

/**
 * Hook: before_delete_post
 *
 * Removes embeddings associated with a post before it is deleted.
 */
add_action( 'before_delete_post', array( $embedding_instance, 'delete_post_embeddings' ), 10, 1 );

/**
 * Hook: transition_post_status
 *
 * Updates embeddings when a post status changes (e.g. publish, draft).
 */
add_action( 'transition_post_status', array( $embedding_instance, 'status_transition' ), 10, 3 );

/**
 * Plugin bootstrap function.
 *
 * The method:
 * - Registers custom abilities (Feature API integration).
 * - Initializes the chatbot frontend logic.
 * - Registers the settings page and adds a shortcut link
 *   to the plugin actions list in the admin panel.
 *
 * @return void
 */
function plugin_init() {
	new \Zoltiq\Agents\Abilities_Manager();
	new \Zoltiq\Agents\Widget_Loader();
	
	$settings_page_instance = new \Zoltiq\Agents\Settings_Page();

	
	add_filter(
		'plugin_action_links_' . plugin_basename(__FILE__),
		[ $settings_page_instance, 'plugin_action_links' ]
	);

	add_filter('zq_agents_available_widgets', function() {
		$registry = new Resource_Discovery();
		return $registry->get_widgets();
	});


}

/**
 * Hook into plugins_loaded.
 *
 * Ensures proper initialization timing and compatibility
 * with WordPress Feature API resolution.
 */
add_action( 'plugins_loaded', 'plugin_init' );

/**
 * Register activation hooks.
 *
 * - Initializes archive system (e.g. scheduled tasks, storage).
 * - Initializes embedding system.
 */
register_activation_hook(
	__FILE__,
	function () {
		\Zoltiq\Agents\Embedding_Indexer::activate();
	}
);


//////////////////////////////////////////////////////////////////////////////////////////
require ZOLTIQ_AGENTS_PATH . 'includes/abilities/abilities-main.php';
require ZOLTIQ_AGENTS_PATH . 'includes/abilities/abilities-loader.php';
require ZOLTIQ_AGENTS_PATH . 'includes/abilities/abilities-bootstrap.php';
require ZOLTIQ_AGENTS_PATH . 'includes/abilities/ability-definition.php';


use Zoltiq\Agents\Includes\Main;


function abilities_instances_run() {

	$plugin = Main::instance();

	add_action( 'plugins_loaded', array( $plugin, 'run' ), 0 );
}

abilities_instances_run();