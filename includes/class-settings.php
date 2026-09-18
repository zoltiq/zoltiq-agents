<?php

namespace Zoltiq\Agents;

use WP_REST_Server;
use WP_REST_Response;
use WP_Error;
use Zoltiq\Agents\Services\Llm_Provider_Factory;
use Zoltiq\Agents\Embedding_Indexer;

/**
 * Class Settings
 *
 * Handles plugin settings, REST API endpoints,
 * LLM integrations, caching, and file management.
 */
class Settings {

	/**
	 * Main plugin options key.
	 */
	private const OPTION_SETTINGS = 'zoltiq_agents_options';
	
	/**
	 * Stored agent metadata.
	 */
	private const OPTION_AGENTS = 'zoltiq_agents';
	
	/**
	 * Cached LLM model list option key.
	 */
	private const CACHE_MODEL_LIST = 'zoltiq_agents_cache_model_list';

	/**
	 * API key storage option.
	 */
	private const OPTION_API_KEY = 'zoltiq_agents_api_key';

	/**
	 * Providers storage option.
	 */
	private const OPTION_API_PROVIDERS = 'zoltiq_agents_api_providers';

	/**
	 * Cache lifetime for LLM models (in seconds).
	 */
	private const MODEL_CACHE_TTL = 6 * HOUR_IN_SECONDS;

  	/**
	 * REST API base route.
	 *
	 * @var string
	 */
	protected $namespace = 'zoltiq-agents/v1';


	/**
	 * REST API base route for the proxy.
	 *
	 * @var string
	 */
	protected $rest_base = 'ai-api-settings';

	/**
	 * JSON schemas used for validation.
	 *
	 * @var array
	 */
	private $schemas = [];

	/**
	 * Constructor.
	 *
	 * Loads schema definitions if available.
	 */
	public function __construct() {
		$file = plugin_dir_path( __FILE__ ) . 'schemas.php';
		if ( file_exists( $file ) ) {
			$this->schemas = require $file;
		} else {
			$this->schemas = [];
		}
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Registers REST API routes for the plugin.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
    	register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/settings',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_save_options' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);


    	register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/list-posts-embed',
			array(
				'methods'  => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_posts' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args' => array(
					'per_page' => array(
						'type' => 'intval'
					),
					'page' => array(
						'type' => 'intval'
					)
				)
			)
    	);


		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/set-provider',
			array(
				'methods'  => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'set_llm_provider' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => array(
					'provider' => array(
						'required' => true, 'type' => 'string',
						'enum'     => array( 'openai', 'google', 'anthropic', 'ollama' )
					),  
					'api_key' => array( 'type' => 'string' ),
					'url' => array( 'type' => 'string', 'format' => 'uri' ),
				)
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/list-models',
			array(
				'methods'  => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_llm_models' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => array(
					'agent_models' => array(
						'type'        => 'array',
						'items'       => array(
							'type' => 'string',
							'enum' => array( 'openai', 'google', 'anthropic', 'ollama' ),
						),
						
					),
					'embed_models' => array(
						'type'        => 'string',
						'enum'        => array( 'openai', 'google', 'anthropic', 'ollama' ),
					)
				)
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/agent',
			array(
				// GET Method 
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_file_agent' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'			   	  => array(
						'filename' => array(
							'required'       => true,
							'type'           => 'string' 
						)
					)
				),

				// POST Method
				array(
					'methods'             => WP_REST_Server::CREATABLE, // lub 'POST'
					'callback'            => array( $this, 'update_file_agent' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'content' => array(
							'required'      => true,
							'type'          => 'string'
						),
						'filename' => array(
							'required'       => true,
							'type'           => 'string' 
						),
						'name' => array(
							'required'       => true,
							'type'           => 'string' 
						),
						'id' => array(
							'required'      => true,
							'type'          => 'number'
						),
						'type' => array(
							'required'    => true,
							'type'        => 'string',
							'enum'        => array( 'frontend', 'backend' ),
						)

					)
				),
				
				// DELETE method
				array(
            		'methods'             => WP_REST_Server::DELETABLE,
            		'callback'            => array( $this, 'delete_agent' ),
            		'permission_callback' => array( $this, 'check_permissions' ),
            		'args'                => array(
                		'id' => array(
                    		'required'          => true,
                    		'validate_callback' => function( $param ) {
                       			return is_numeric( $param );
                    		},
                		),
						'type' => array(
							'required'    => true,
							'type'        => 'string',
							'enum'        => array( 'frontend', 'backend' ),
						)
            		)
        		)
			)
		);


		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/agents-items',
			array(
				// GET Method 
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_list_agents' ),
					'permission_callback' => array( $this, 'check_permissions' )
				),
				// POST Method
				array(
					'methods'             => WP_REST_Server::CREATABLE, 
					'callback'            => array( $this, 'update_agents_items' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'backend_agents'  => $this->schemas['agents_items'],
						'frontend_agents' => $this->schemas['agents_items']
					)
				)
			)
		);
		

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/set-embed-model',
			array(
				'methods'  			  => WP_REST_Server::CREATABLE,
				'callback' 			  => array( $this, 'set_embed_model' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args' => array(
					'provider' => array(
						'type' => 'string',
						'required' => true
					),
					'model' => array(
						'type' => 'string',
						'required' => true
					)
				)
			)
    	);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/agent-model',
			array(
				array(
					'methods'  			  => WP_REST_Server::CREATABLE,
					'callback' 			  => array( $this, 'set_agent_model' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args' => array(
						'provider' => array( 'type' => 'string', 'required' => true ), 
						'model' => array( 'type' => 'string', 'required' => true ),
						'name' => array( 'type' => 'string', 'required' => true ),
						'slug' => array( 'type' => 'string', 'required' => true ),
						'temperature' => array( 'type' => 'number', 'required' => true )
					)
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_agent_model' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'slug' => array( 'type' => 'string',  'required' => true )
					)
				)
			)
    	);

	}



	public function set_llm_provider( $request ) {

		$provider = $request->get_param( 'provider' );
		$api_key = $request->get_param( 'api_key' );
		$url = $request->get_param( 'url' );

		if($api_key){
			$api_keys = get_option( self::OPTION_API_KEY, [] );
			if ( ! is_array( $api_keys ) ) $api_keys = [];
			$api_keys[$provider] = $api_key;
			update_option( self::OPTION_API_KEY, $api_keys );
		}

		$type = 'agent_models';
		$connected = false;
		$models = $this->refresh_cache_llm_models( $provider, $type );
		
		if ( is_array($models) ) {
			$connected = true;
		} 

		$api_providers = get_option( self::OPTION_API_PROVIDERS, [] );
		if ( ! is_array( $api_providers ) ) $api_providers = [];
		
		$api_provider = [
		    'connected' => $connected,
			...($url ? ['url' => $url] : []),
		];
		
		$api_providers[$provider] = $api_provider;
		update_option( self::OPTION_API_PROVIDERS, $api_providers);

		return rest_ensure_response( array(
			'success' => true,
			'message' => 'The provider has been saved',
			'models'  => $models,
			'connected' => $connected
		));  
	}

	
    public function set_agent_model( $request ) {
	
		$options = get_option( self::OPTION_SETTINGS, [] );

		if ( ! isset( $options['api_page']['agents'] ) || ! is_array( $options['api_page']['agents'] ) ) {
        	$options['api_page']['agents'] = [];
    	}

		$slug = $request->get_param( 'slug' );

		if ( ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug ) ) {
			return rest_ensure_response( array(
				'success' => false,
				'message' => 'Invalid slug',
			)); 
		} 

		$index = array_search($slug, array_column( $options['api_page']['agents'], 'slug' ),  true );

		$agent = [
			'provider'     => $request->get_param( 'provider' ),
			'model_choice' => $request->get_param( 'model' ),
			'name'         => $request->get_param( 'name' ),
			'slug'         => $slug,
			'temperature'  => $request->get_param( 'temperature' ),
    	];

		if ( $index === false ) {
        	$options['api_page']['agents'][] = $agent;
    	} else {
          	$options['api_page']['agents'][$index] = $agent;
    	}

		update_option( self::OPTION_SETTINGS, $options );

		return rest_ensure_response( array(
			'success' => true,
			'message' => 'The agent model has been saved',
		));  
	}


	public function delete_agent_model( $request ) {

		$slug = $request->get_param( 'slug' );
			
		$options = get_option( self::OPTION_SETTINGS, [] );
		if ( isset( $cache[ $provider ] ) )

		if ( ! isset($options['api_page']['agents']) ) {
			return rest_ensure_response( array(
            	'success' => false,
            	'message' => 'The agent model has not been removed',
       		));
		}

		$index = array_search($slug, array_column( $options['api_page']['agents'], 'slug' ),  true );

		if ( $index === false ) {
        	return rest_ensure_response( array(
            	'success' => false,
            	'message' => 'The agent model has not been removed',
       		));
    	}
		
		unset( $options['api_page']['agents'][$index] );
		$options['api_page']['agents'] = array_values( $options['api_page']['agents'] );
		
		update_option( self::OPTION_SETTINGS, $options );

		return rest_ensure_response( array(
			'success' => true,
			'message' => 'The agent model has been removed',
		));  
	}
		


	public function set_embed_model( $request ) {
		$provider = $request->get_param( 'provider' );
		$model = $request->get_param( 'model' );
		$options = get_option( self::OPTION_SETTINGS, [] );
				
		if ( empty($options[ 'api_page' ]['embeddings']['provider']) ) {
			return new WP_Error(
				'settings_model', 'No embedding provider configuration',
				array( 'status' => 400 )
			);
		}

		$options[ 'api_page' ]['embeddings']['model_choice'] = $model;
		update_option( self::OPTION_SETTINGS, $options );

		$provider = Llm_Provider_Factory::make_embedding_provider();
		$emb_resp = $provider->embeddings( array( 'Get Count Sample Text' ) );
		
		$err =  isset( $emb_resp['error']) || 
				empty( $emb_resp['embeddings'][0] ) ||
				! is_array( $emb_resp['embeddings'][0] );
		if ( $err ) {
			return new WP_Error(
				'settings_model', 'No embedding provider configuration',
				array( 'status' => 400 )
			);
		}

		$vector = $emb_resp['embeddings'][0];
		$count_vector = count($vector);
		$result = Embedding_Indexer::recreate_embedding_table($count_vector);
		if( ! $result ) {
			return new WP_Error(
				'settings_model', 'Vector database configuration error',
				array( 'status' => 400 )
			);
		}

		return rest_ensure_response( array(
			'success' => true,
			'message' => 'The model has been saved',
		));  
	
	}


	/**
	 * Retrieves LLM models for given providers using cached data.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function get_llm_models( $request ) {

		$agent_providers = $request->get_param( 'agent_models' );
		$embed_provider  = $request->get_param( 'embed_models' );

		// Normalizacja wejścia
		if ( ! is_array( $agent_providers ) ) {
			$agent_providers = $agent_providers ? [ $agent_providers ] : [];
		}

		$cache = get_option( self::CACHE_MODEL_LIST, [] );
		$now   = time();
		$result = [];

		// =========================
		// AGENT MODELS
		// =========================
		foreach ( $agent_providers as $provider ) {

			$needs_refresh = true;

			if ( isset( $cache[ $provider ] ) ) {
				$entry = $cache[ $provider ];

				if (
					! empty( $entry['agent_models'] ) &&
					isset( $entry['date'] ) &&
					( $now - $entry['date'] ) < self::MODEL_CACHE_TTL
				) {
					$needs_refresh = false;
				}
			}

			if ( $needs_refresh ) {
				$fresh = $this->refresh_cache_llm_models( $provider, 'agent_models' );
				if ( ! empty( $fresh ) ) {
					$result[ $provider ]['agent_models'] = $fresh;
				}
			} else {
				$result[ $provider ]['agent_models'] = $cache[ $provider ]['agent_models'];
			}
		}

		// =========================
		// EMBEDDING MODELS
		// =========================
		if ( ! empty( $embed_provider ) ) {

			$needs_refresh = true;

			if ( isset( $cache[ $embed_provider ] ) ) {
				$entry = $cache[ $embed_provider ];

				if (
					! empty( $entry['embed_models'] ) &&
					isset( $entry['date'] ) &&
					( $now - $entry['date'] ) < self::MODEL_CACHE_TTL
				) {
					$needs_refresh = false;
				}
			}

			if ( $needs_refresh ) {
				$fresh = $this->refresh_cache_llm_models( $embed_provider, 'embed_models' );

				if ( ! empty( $fresh ) ) {
					$result[ $embed_provider ]['embed_models'] = $fresh;
				}
			} else {
				$result[ $embed_provider ]['embed_models'] = $cache[ $embed_provider ]['embed_models'];
			}
		}

		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * Fetches models from provider and updates cache.
	 *
	 * @param string $type
	 * @param string $provider
	 * @return array
	 */
	private function refresh_cache_llm_models( $provider, $type ) {
	
		// Get the list of models from the LLM provider
		$models = [];
		try {
			
			if($type === 'agent_models') {
				$client = Llm_Provider_Factory::make( $provider );
				if (!$client) return;
				$models = $client->list_models();
			}
			else if($type === 'embed_models') {
				$client = Llm_Provider_Factory::make_embedding_provider();
				if (!$client) return;
				$models = $client->list_models_embeddings();
			}

		} catch (\Zoltiq\Agents\Exceptions\Llm_Exception $e) {
			error_log('LLM Service Error: ' . $e->getMessage());
		} catch (\Exception $e) {
			error_log('General Error: ' . $e->getMessage());
		}

		usort($models, fn($a, $b) => $a['name'] <=> $b['name']);
		
		// Refresh the cache 
		$cache = get_option(self::CACHE_MODEL_LIST, []);
	
		$cache[$provider] = [
			$type => $models, 
			'date' => current_time( 'timestamp' )
		];
		
		update_option(self::CACHE_MODEL_LIST, $cache);
	
		return $models;
	}
      
	/**
	 * Saves plugin settings from REST request.
	 *
	 * @param \WP_REST_Request $request
	 * @return WP_REST_Response|\WP_Error
	 */
	public function handle_save_options( $request ) {

		$params = $request->get_json_params(); 
		$res = [];
		
		if ( empty( $params ) || ! is_array( $params ) ) {
			return new WP_Error(
				'empty_data',
				__( 'Empty data', 'zoltiq-agents' ),
				array( 'status' => 400 )
			);
		}

		if(isset($params['embedding_page'])){
			$embedding_page = $params['embedding_page'];
			$post_ids       = $embedding_page['select_post_ids'];  
			$page           = $embedding_page['page'] ?: 1;  
			$per_page       = $embedding_page['per_page'] ?: 50;  
			$all_posts_type = $embedding_page['select_all_posts_type'] ?? null; 

			$embedder       = new Embedding_Indexer();
			$res            = $embedder->embedding_posts( $post_ids, $page, $per_page, [], $all_posts_type);
			$res['status']  = ( ! empty($res['errors']) ) ? 'error' : 'success'; 
		}
		
		$pages = ['api_page', 'chatbot_page', 'style_page'];
		foreach ($pages as $page) {
			if (isset($params[$page])) {
				$payload = $params[$page];
				$res     = $this->update_options_chatbot($payload, $page);
			}
		}

		return rest_ensure_response( $res );
	}

   
	/**
	 * Returns paginated post list.
	 *
	 * @param \WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_posts( $request ) {
		$per_page = $request->get_param('per_page') ?: 50;
		$page     = $request->get_param('page') ?: 1;
		$embedder = new Embedding_Indexer();
		$r        = $embedder->spl_rest_get_post_list($page, $per_page);
		$response = rest_ensure_response($r);
		$response->header( 'X-Total-Count', $r['total'] );
		return $response;
	}


	/**
	 * Returns contents and metadata of Agent.js file.
	 *
	 * @param \WP_REST_Request $request
	 * @return array
	 */
	public function get_list_agents( $request ) {
		
		$agents = get_option( self::OPTION_AGENTS );
				
		return $agents;
	}

	/**
	 * Returns contents and metadata of Agent.js file.
	 *
	 * @param \WP_REST_Request $request
	 * @return array|\WP_Error
	 */
	public function get_file_agent( $request ) {
		$filename = $request->get_param('filename') ?? 'Agent.js';

		$file_path = ZOLTIQ_AGENTS_PATH . 'assets/agents/' . $filename;

		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		WP_Filesystem();
    	global $wp_filesystem;

		if ( ! $wp_filesystem->exists( $file_path ) ) {
        	return new WP_Error( 'file_missing', 'Plik ' . $filename . ' nie istnieje.', array( 'status' => 404 ) );
    	}

		$content = $wp_filesystem->get_contents( $file_path );
		$last_modified = filemtime( $file_path ); 

		$data = array(
			'name'          => basename( $file_path ),
			'last_modified' => date( 'Y-m-d H:i:s', $last_modified ),
			'timestamp'     => $last_modified,                      
			'content'       => $content
		);

		return $data;
	}

	/**
	 * Updates Agent.js file content.
	 *
	 * @param \WP_REST_Request $request
	 * @return WP_REST_Response|\WP_Error
	 */
	public function update_file_agent( $request ) {
		
		if ( ! current_user_can( 'manage_options' ) ) {
        	return new WP_Error(
				'forbidden',
				'You do not have sufficient permissions to edit the script files.',
				array( 'status' => 403 )
			);
    	}

		$filename = $request->get_param('filename');
		$name = $request->get_param('name');
		$new_content = $request->get_param('content');
		$id = $request->get_param('id');
		$type = $request->get_param('type');

		$file_path = ZOLTIQ_AGENTS_PATH . 'assets/agents/' . $filename;
				
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		if ( ! WP_Filesystem() ) {
			return new WP_Error(
				'filesystem_error',
				'Could not initialize the WordPress filesystem.',
				array( 'status' => 500 )
			);
		}
		global $wp_filesystem;

		if ( $wp_filesystem->put_contents( $file_path, $new_content, FS_CHMOD_FILE ) ) {

			$last_modified = filemtime( $file_path );
			$this->save_agent($filename, $name, $last_modified, $id, $type);
			
			return rest_ensure_response( array(
				'success'       => true,
				'last_modified' => date( 'Y-m-d H:i:s', $last_modified ),
				'timestamp'     => $last_modified
			));
		} 

		return new WP_Error(
			'save_error',
			'The file could not be saved.
			Check the folder permissions.',
			array( 'status' => 500 )
		);
		
	}

	/**
	 * Saves agent definition.
	 *
	 * @param string $filename
	 * @param string $name
	 * @param int    $last_modified
	 * @param int    $id
	 * @param string $type
	 * @return bool
	 */
	private function save_agent( $filename, $name, $last_modified, $id, $type ) {

		$data = get_option( self::OPTION_AGENTS, array() );
		$type = $type . '_agents';

		// Upewnij się, że struktura istnieje.
		if ( ! isset( $data[ $type ]['items'] ) || ! is_array( $data[ $type ]['items'] ) ) {
			$data[ $type ]['items'] = array();
		}

		$agents = $data[ $type ]['items'];
		$agent = array(
			'name'      => $name,
			'id'        => (int) $id,
			'filename'  => $filename,
			'timestamp' => $last_modified,
		);

		$found = false;

		foreach ( $agents as $key => $item ) {
			if ( (int) $item['id'] === (int) $id ) {

				// Aktualizacja istniejącego agenta.
				$agents[ $key ] = $agent;

				$found = true;
				break;
			}
		}

		// Nie znaleziono ID — dodaj nowego agenta.
		if ( ! $found ) {
			$agents[] = $agent;
		}

		// Zaktualizuj dane całej opcji.
		$data[ $type ]['items'] = $agents;

		try {
			if ( ! update_option( self::OPTION_AGENTS, $data ) ) {
				return false;
			}
		} catch ( \Exception $e ) {
			error_log(
				'Failed to update agent states option: ' . $e->getMessage()
			);
			return false;
		}
		return true;
	}


	/**
	 * Saves definitions agent.
	 *
	 * @param \WP_REST_Request $request
	 * @return WP_REST_Response|\WP_Error
	 */
	public function delete_agent( $request ) {

		$data = get_option( self::OPTION_AGENTS, array() );
		$id = (int) $request->get_param( 'id' );
		$type = $request->get_param('type');
		$type = $type . '_agents';
		
		// Upewnij się, że struktura istnieje.
		if ( ! isset( $data[ $type ]['items'] ) || ! is_array( $data[ $type ]['items'] ) ) {
			$data[ $type ]['items'] = array();
		}
		$agents = $data[ $type ]['items'];
		
		foreach ( $agents as $key => $item ) {
			if ( $item['id'] === $id ) {
				
				// Usuniecie pliku.
				$this->delete_agent_file($item['filename']);
				
				// Usunięcie pozycji.
				unset( $agents[ $key ] );
				break;
			}
		}

		$new_items = array_values( $agents );

		// Zaktualizuj dane całej opcji.
		$data[ $type ]['items'] = $new_items;

		try {
			update_option( self::OPTION_AGENTS, $data );
		} catch ( \Exception $e ) {
			return new WP_Error(
				'delete_error',
				'Delete error',
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data
			),
			200
		);
	}


	private function delete_agent_file( $filename ) {

		$filename  = basename( $filename );
		$file_path = ZOLTIQ_AGENTS_PATH . 'assets/agents/' . $filename;

		require_once ABSPATH . 'wp-admin/includes/file.php';

		if ( ! WP_Filesystem() ) {
			return new WP_Error(
				'filesystem_error',
				'Could not initialize the WordPress filesystem.',
				array( 'status' => 500 )
			);
		}

		global $wp_filesystem;

		if ( ! $wp_filesystem->exists( $file_path ) ) {
			return true;
		}

		if ( ! $wp_filesystem->delete( $file_path ) ) {
			return new WP_Error(
				'delete_failed',
				'Could not delete the agent file.',
				array( 'status' => 500 )
			);
		}

		return true;
	}



	/**
	 * Saves definitions agent.
	 *
	 * @param \WP_REST_Request $request
	 * @return bool
	 */
	public function update_agents_items( $request ) {

		$backend_agents  = $request->get_param( 'backend_agents' );
		$frontend_agents = $request->get_param( 'frontend_agents' );

		$agents = [
			'backend_agents'  => $backend_agents,
			'frontend_agents' => $frontend_agents,
		];
						      
    	try {
			update_option( self::OPTION_AGENTS, $agents );
		} catch ( \Exception $e ) {
			error_log( 'Failed to update agent states option: ' . $e->getMessage() );
			return false;
		}
		return true;
	}


	/**
	 * Validates and saves chatbot options using schema.
	 *
	 * @param array  $payload
	 * @param string $page_key
	 * @return array|\WP_Error
	 */
	private function update_options_chatbot( $payload, $page_key ) {
		$schema = $this->get_schema($page_key);
		
		$valid = rest_validate_value_from_schema( $payload, $schema, 'payload' );
		if ( is_wp_error( $valid ) ) {
			return $valid; // np. WP_Error z opisem co nie przeszło
		}

		$clean = rest_sanitize_value_from_schema( $payload, $schema, 'payload' );
		if ( is_wp_error( $clean ) ) {
			return $clean;
		}
		$final = $clean;

		$options = get_option( self::OPTION_SETTINGS, [] );
		if ( ! is_array( $options ) ) {
			$options = [];
		}

		$options[ $page_key ] = $final;

		$updated = update_option( self::OPTION_SETTINGS, $options );

		if ( $updated === false ) {
			return  [
				'success'  => true,
				'message' => 'No changes detected (option unchanged).',
			];
		}

		return [
			'success'  => true,
			'message' => sprintf( 'Options for "%s" saved.', $page_key ),
		];

	}
   
   
	/**
	 * Retrieves a schema configuration by key from the internal schemas array.
	 *
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	private function get_schema( $key, $default = [] ) {
		return $this->schemas[ $key ] ?? $default;
	}
	

	/**
	 * Checks if the current user has the required permissions to manage options.
	 *
	 * @return bool
	 */
	public function check_permissions() {
		return current_user_can( 'manage_options' );
	}
}