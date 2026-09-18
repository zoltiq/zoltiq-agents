<?php

namespace Zoltiq\Agents;

use Zoltiq\Agents\Services\Llm_Provider_Factory;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;


/**
 * Registers and handles REST API endpoints for proxying AI service requests.
 */
class AI_API_Proxy {
	
	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'zoltiq-agents/v1';
	
	/**
	 * REST API base route for the proxy.
	 *
	 * @var string
	 */
	protected $rest_base = 'ai-api-proxy';
		

	
	/**
	 * Constructor.
	 *
	 * Registers authentication filter for REST API requests.
	 */
	public function __construct() {
		add_filter( 'rest_authentication_errors', array( $this, 'authenticate_cookie' ) );
	}
	
	/**
	 * Authenticate using cookies.
	 *
	 * Allows access if the user is logged in via WordPress cookies.
	 *
	 * @param WP_Error|null|bool $result Existing authentication result.
	 * @return WP_Error|null|bool
	 */
	public function authenticate_cookie( $result ) {
		if ( ! empty( $result ) ) {
			return $result;
		}

		if ( is_user_logged_in() ) {
			return true;
		}

		return $result;
	}

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
	 * Defines endpoints for tool listing and AI proxying.
	 */
	public function register_rest_routes() {

		register_rest_route(
			$this->namespace,
			'/list-widgets',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback' => [$this, 'list_widgets'],
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<api_path>.*)',
			array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => array( $this, 'ai_api_proxy' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'api_path' => array(
						'description' => __( 'The path to proxy to the AI service API.', 'zoltiq-chatbot' ),
						'type'        => 'string',
						'required'    => true,
					),
				),
			)
		);
	}

	/**
	 * Proxies the request to the selected AI provider.
	 *
	 * @param WP_REST_Request $request Incoming request data.
	 * @return WP_Error|WP_REST_Response
	 */
	public function ai_api_proxy(WP_REST_Request $request) {

        $endpoint = $request->get_param('api_path');
		$provider = $request->get_header('X-Provider');
		$body     = $request->get_body();
		$response = [];

		try {
			$client = Llm_Provider_Factory::make($provider);
			if (!$client) return;

			$response = $client->generate_response($endpoint, $body);
		} catch (\Zoltiq\Agents\Exceptions\Llm_Exception $e) {
			error_log('LLM Service Error: ' . $e->getMessage());
		} catch (\Exception $e) {
			error_log('General Error: ' . $e->getMessage());
		}

		if (is_wp_error($response)) {
			return new WP_Error('proxy_error', 'AI connection error', ['status' => 500]);
		}

		return $response;
	}
	


	public function list_widgets( WP_REST_Request $request ) {
    	return apply_filters('zq_agents_available_widgets', []);
	}

	/**
	 * Checks if current user has admin permissions.
	 *
	 * @return bool
	 */
	public function check_permissions() {
    	return current_user_can( 'manage_options' );
	}
}