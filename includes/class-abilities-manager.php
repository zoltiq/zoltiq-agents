<?php

namespace Zoltiq\Agents;

use WP_REST_Server;
use WP_REST_Request;
use Zoltiq\Agents\Services\Llm_Provider_Factory;

/**
 * Class Abilities_Manager
 *
 * Responsible for registering ability categories and abilities
 * used by the Zoltiq Chatbot within the WordPress environment.
 */
class Abilities_Manager {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'zoltiq-agents/v1';

	/**
	 * The tool states option name.
	 */
	private const TOOL_ENABLED_OPTION = 'zoltiq_agents_tool_enabled';

	/**
	 * Database table name for tools embeddings.
	 */
	private const DB_TABLE_TOOLS_EMBEDDINGS = 'zoltiq_tools_embed';

	/**
	 * Constructor.
	 *
	 * Initializes tools and registers WordPress hooks
	 * for ability categories and abilities.
	 */
	public function __construct() {}

	/**
	 * Registers WordPress hooks.
	 *
	 * Hooks into REST API initialization.
	 */
	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Registers the REST API routes.
	 *
	 * Defines endpoints for tool listing.
	 */
	public function register_rest_routes() {
		register_rest_route(
			$this->namespace,
			'/list-tools',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_available_tools' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'semantic_query' => array(
						'description' => 'The meaning and intent of the query and the context of the text.',
						'type'        => 'string'
					),
					'category' => array(
						'description' => 'Filter the list of tools by category',
						'type'        => 'string'
					),
					'all' => array(
						'description' => 'Get all list of tools',
						'type'       => 'boolean'
					),
					'orderby' => array(
						'description' => 'Field used for sorting.',
						'type'        => 'string',
						'enum'        => array('name', 'category', 'context_group', 'source', 'enabled', 'type')
					),
					'order' => array(
    					'description' => 'Sort direction.',
    					'type'        => 'string',
    					'enum'        => array('asc', 'desc')
					),
					'page' => array(
						'description' => 'Page number.',
						'type'        => 'integer',
    					'default'     => 1,
    					'minimum'     => 1
					),
					'per_page' => array(
						'description' => 'Number of items per page.',
						'type'        => 'integer',
						'default'     => 20,
						'minimum'     => 1,
						'maximum'     => 100
					)
				)
			)
		);

		register_rest_route(
			$this->namespace,
			'/get-context-group',
			array(
				'methods'  => WP_REST_Server::READABLE,
				'callback' => array( $this, 'handle_get_context_group' ),
				'permission_callback' => array( $this, 'feature_admin_only' ),
			)
		);


		register_rest_route(
			$this->namespace,
			'/update-tool',
			array(
				'methods'  => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'handle_update_tool_statuses' ),
				'permission_callback' => array( $this, 'feature_admin_only' ),
				'args'                => array(
					'name' => array(
						'type'        => 'string',
						'required' => true
					),
					'context_group' => array(
						'type'        => 'string',
						'required' => true
					),
					'enabled' => array(
						'type'       => 'boolean',
						'required' => true
					),
					'description_ability' => array(
						'type'        => 'string',
					)
				)
			)
		);

	}
	

	/**
	 * Returns a list of available tools (abilities).
	 *
	 * Supports filtering by category, returning all tools,
	 * or context-based selection.
	 *
	 * @param WP_REST_Request $request
	 * @return array
	 */
	public function list_available_tools( WP_REST_Request $request ) {
		$query = $request->get_query_params();

		$allAbilities  = wp_get_abilities();
		$settings_tools  = get_option(self::TOOL_ENABLED_OPTION, array());
		
		$semantic_query = sanitize_text_field($query['semantic_query'] ?? '');
		$context_group  = sanitize_key($query['category'] ?? '');
		$order_by       = sanitize_key($query['orderby'] ?? '');
		$order          = strtolower($query['order'] ?? 'asc');
		$includeAll     = $query['all'] ?? null;
		$page           = $query['page'] ?? 1;
		$per_page       = $query['per_page'] ?? 20;
		
		$tools = [];
		$set_tool = [];
		$default_set_tool = [
			'enabled' => false,
			'agent_context' => null
		];


		if($semantic_query) {
			$slugs = $this->semantic_search_tools($semantic_query);
			foreach ( $slugs as &$item ) {
				$slug = $item['slug'];
				$ability = wp_get_ability( $slug );
				$set_tool = isset($settings_tools[$slug]) ? $settings_tools[$slug] : $default_set_tool; 
				
				if (!$set_tool['enabled']) {
					continue;
				}

				// Build tools
				$tools[] = $this->map_ability_to_tool($ability, $set_tool);

			}

		} else {

			global $wpdb;

			$table = $wpdb->prefix . self::DB_TABLE_TOOLS_EMBEDDINGS;

			$rows = $wpdb->get_results(
				"SELECT slug, text, embedding_date_gmt FROM {$table}",
				OBJECT_K
			);


			foreach ($allAbilities as $slug => $ability) {

				$set_tool = isset($settings_tools[$slug]) ? $settings_tools[$slug] : $default_set_tool; 

				if (!$includeAll && !$set_tool['enabled']) {
					continue;
				}

				if (!$includeAll && ($context_group != $set_tool['context_group'])) {
					continue;
				}

				if ($includeAll && isset($rows[$slug])) {
					$set_tool['description_ability'] = $rows[$slug]->text;
				}

				// Build tools
				$tools[] = $this->map_ability_to_tool($ability, $set_tool);
			}
		}


		if($order_by && $order){
			$this->sortItems($tools, $order_by, $order);
		}

		$total = count($tools);

		$offset = ($page - 1) * $per_page;

		$tools = array_slice($tools, $offset, $per_page);

		$response = rest_ensure_response($tools);

		$response->header('X-WP-Total', $total);
		$response->header('X-WP-TotalPages', (int) ceil($total / $per_page));

		return $response;
	}

	/**
	 * Maps Ability → Tool array
	 */
	private function map_ability_to_tool($ability, $set_tool) {
		
		$name = (string) $ability->get_name();
		$meta =  $ability->get_meta();
		$tool = [
			'name'          => $name,
			'label'         => $ability->get_label(),
			'category'      => $ability->get_category(),
			'description'   => $ability->get_description(),
			'input_schema'  => $ability->get_input_schema(),
			'output_schema' => $ability->get_output_schema(),
			'source'        => $this->detect_source( $name ),
			'meta'          => $meta,
			'type'          => $meta['annotations']['readonly'] ? 'resource' : 'tool',
			...$set_tool
		];

		return $tool;
	}



	private function semantic_search_tools( $query ) {
		
		$provider = Llm_Provider_Factory::make_embedding_provider();
		$emb_resp = $provider->embeddings( array( $query ) );
		
    	if ( isset( $emb_resp['error'] ) ) {
        	return array( 'error' => 'Embedding error: ' . $emb_resp['error'] );
    	}
    	if ( empty( $emb_resp['embeddings'][0] ) || ! is_array( $emb_resp['embeddings'][0] ) ) {
        	return array( 'error' => 'No embedding returned from model.' );
    	}
    	$query_embedding = $emb_resp['embeddings'][0];
		
		// Prepare JSON representation (used for VEC_FromText)
    	$json = wp_json_encode( $query_embedding );
    	if ( false === $json ) {
        	return array( 'error' => 'Failed to json_encode embedding.' );
    	}

		global $wpdb;

        $table = $wpdb->prefix . self::DB_TABLE_TOOLS_EMBEDDINGS;
    	$top_k = 5; 

    	$sql = $wpdb->prepare(
			"SELECT t.slug AS slug, 
			VEC_DISTANCE_COSINE(t.embedding, VEC_FromText(%s)) AS distance
			FROM {$table} t
			ORDER BY distance ASC
			LIMIT %d",
			$json,
			$top_k
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );

		if ( false === $rows ) {
			return array( 'error' => 'DB query failed: ' . $wpdb->last_error );
		}

		return $rows;

	}


	/**
	 * Sorts an array of items alphabetically by a selected field.
	 *
	 * Uses natural, case-insensitive comparison to provide human-friendly sorting
	 * (e.g. item-2 before item-10). Supports ascending and descending order.
	 *
	 * @param array  $items     Items array to sort (passed by reference).
	 * @param string $order_by  Field name used for sorting (default: 'name').
	 * @param string $order     Sorting direction: 'asc' or 'desc' (default: 'asc').
	 *
	 * @return void
	 */
	private function sortItems(&$items, $order_by, $order) {
		usort($items, function ($a, $b) use ($order_by, $order) {

			$valueA = $a[$order_by] ?? '';
			$valueB = $b[$order_by] ?? '';

			 if (is_bool($valueA) && is_bool($valueB)) {
				$result = $valueA <=> $valueB;
			} else {
				$result = strnatcasecmp((string) $valueA, (string) $valueB);
			}

			return $order === 'desc' ? -$result : $result;
    	});
	}
		
	
	/**
	 * Detect a basic source label from the ability namespace.
	 *
	 * @param string $name Ability name.
	 * @return string
	 */
	private function detect_source( string $name ): string {
		$namespace = $this->get_namespace( $name );

		if ( ! function_exists( 'get_plugins' ) && defined( 'ABSPATH' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( function_exists( 'get_plugins' ) ) {
			$plugins = get_plugins();
			foreach ( $plugins as $plugin_file => $plugin_data ) {
				$slug = dirname( $plugin_file );
				if ( '.' === $slug ) {
					$slug = basename( $plugin_file, '.php' );
				}

				if ( $slug === $namespace ) {
					return isset( $plugin_data['Name'] ) ? (string) $plugin_data['Name'] : $slug;
				}
			}
		}

		if ( in_array( $namespace, array( 'core', 'wordpress', 'wp' ), true ) ) {
			return $namespace;
		}

		return $namespace;
	}



	/**
	 * handle get context group.
	 *
	 * @param \WP_REST_Request $request
	 * @return array
	 */
	public function handle_get_context_group( $request ) {
		$settings_tools  = get_option(self::TOOL_ENABLED_OPTION, array());
		
		$response = array_values(
    		array_unique(array_column($settings_tools, 'context_group'))
		);
		
		return $response;
	}


	/**
	 * handle tools statuses.
	 *
	 * @param \WP_REST_Request $request
	 * @return string|array
	 */
	public function handle_update_tool_statuses( $request ) {
		
		$ability_slug = $request->get_param('name');
		$enabled = $request->get_param('enabled');
		$context_group = $request->get_param('context_group');
		$description_ability = $request->get_param('description_ability');

		$tool_data = array(
			'enabled' => $enabled,
			'context_group' => $context_group
		);

		$result_statuses = $this->update_tool_statuses( $ability_slug, $tool_data );

		$error = '';

		if(isset($description_ability)){
				
			$provider = Llm_Provider_Factory::make_embedding_provider();
			$emb_resp = $provider->embeddings( array( $description_ability ) );

			if ( isset( $emb_resp['error'] ) ) {
				return array( 'error' => 'Embedding error: ' . $emb_resp['error'] );
			}
			if ( empty( $emb_resp['embeddings'][0] ) || ! is_array( $emb_resp['embeddings'][0] ) ) {
				return array( 'error' => 'No embedding returned from model.' );
			}

			$description_embedding = $emb_resp['embeddings'][0];

			global $wpdb;

			$table = $wpdb->prefix . self::DB_TABLE_TOOLS_EMBEDDINGS;

			$now = current_time( 'mysql', true );
			$json_embedding = wp_json_encode( $description_embedding );
			if ( false === $json_embedding ) {
				error_log( '[zoltiq_agents] json_encode failed for embedding' );
				return false;
			}

			$sql = $wpdb->prepare(
				"INSERT INTO {$table} (text, slug, embedding, embedding_date_gmt)
				VALUES (%s, %s, VEC_FromText(%s), %s)
				ON DUPLICATE KEY UPDATE text = VALUES(text), slug = VALUES(slug), embedding = VALUES(embedding), embedding_date_gmt = VALUES(embedding_date_gmt)",
				$description_ability,
				$ability_slug,
				$json_embedding,
				$now
			);

			$result_embed = $wpdb->query( $sql );
			if ( false === $result_embed ) {
				error_log( "[zoltiq_agents] DB write embedding failed (tool {$ability_slug}): " . $wpdb->last_error );
			}
			if ( ! $result_embed) {
				$error = 'Failed tool embedding';
			}

		}
		
		if ( $result_statuses === false ) {
			$error = 'Failed tool setting';
		}

		if( $error ) {
			$res['status'] = 'errors';
			$res['msg'] = $error;
		}  else {
			$res['status'] = 'success';
			$res['context_group'] = array_values(
    			array_unique(array_column($result_statuses, 'context_group'))
			);

		}
		return $res;
	}


	/**
	 * Updates tool states.
	 *
	 * Existing tools are overwritten, missing ones are added.
	 *
	 * @param string $ability_name
	 * @param array	$tool_data
	 * @return bool|array
	 */
	private function update_tool_statuses( string  $ability_slug, array $tool_data ): bool|array {
	
		$tool_list_enabled = get_option(self::TOOL_ENABLED_OPTION, array());
		$tool_list_enabled = is_array( $tool_list_enabled ) ? $tool_list_enabled : array();

		$tool_list_enabled[ $ability_slug ] = $tool_data;

		try {
			update_option( self::TOOL_ENABLED_OPTION, $tool_list_enabled );
		} catch ( \Exception $e ) {
			error_log( 'Failed to update tool states option: ' . $e->getMessage() );
			return false;
		}

		return $tool_list_enabled;
	}


	/**
	 * Get namespace from ability name.
	 *
	 * @param string $name Ability name.
	 * @return string
	 */
	private function get_namespace( string $name ): string {
		$parts = explode( '/', $name, 2 );

		return $parts[0] ?? $name;
	}
		
	/**
	 * Permission callback: admin only.
	 *
	 * Checks whether the current user has administrator privileges.
	 *
	 * @return bool
	 */
	public function feature_admin_only() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission callback: all users.
	 *
	 * Allows access to all users (including non-authenticated).
	 *
	 * @return bool
	 */
	public function feature_all_user() {
		return true;
	}

}