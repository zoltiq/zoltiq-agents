<?php

namespace Zoltiq\Agents\Archives;

use ZipArchive;
use WP_Error;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use DateTime;

class API_Archiver {

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
	protected $rest_base = 'api-archive';

	/**
	 * The history states option name.
	 */
	private const AGENTS_HISTORY = 'zoltiq_agents_history_';

	/**
	 * Dir name for uploads agents archiver
	 */
	private const UPLOAD_ARCHIVES_DIR = 'zoltiq-agents-archives';

	/**
	 * The cookie user key  
	 */
	private const COOKIE_SESSION_KEY = 'zoltiq_agents_session';

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
			'/' . $this->rest_base . '/store',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'store_archive' ),
				'permission_callback' => '__return_true',
			)
		);

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/get-archive-list',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'handle_get_archive_list' ),
                'permission_callback' => array( $this, 'check_permissions' ),
            )
        );

    	register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/get-archive-items',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_get_archive_items' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => array(
            		'date' => array(
						'required' => true,
						'type'     => 'string',
						'validate_callback' => array( $this, 'validate_date' ),
            			'sanitize_callback' => function( $param ) {
            				return sanitize_text_field( $param );
        				},	
					)  
        		),
			)
		);

     	register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/get-archive',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_get_archive' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => array(
					'date' => array(
						'required' => true,
						'type'     => 'string',
						'validate_callback' => array( $this, 'validate_date' ),
						'sanitize_callback' => function( $param ) {
							return sanitize_text_field( $param );
						},	
					),  
					'id' => array(
						'required' => true,
						'type'     => 'string',
						'validate_callback' => function( $param ) {
    						return preg_match('/^[a-f0-9]{32}$/i', $param) === 1;
						},	
						'sanitize_callback' => function( $param ) {
							return sanitize_text_field( $param );
						}
					),  
        		),
			)
		);
    }

	/**
	 * Generates or retrieves a unique anonymous user key stored in a cookie.
	 * If the cookie does not exist, a new UUID is generated and stored for 1 days.
	 *
	 * @return string The sanitized unique user key.
	 */
	private function get_user_session_key() {

		if ( isset( $_COOKIE[ self::COOKIE_SESSION_KEY ] ) ) {
			$value = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_SESSION_KEY ] ) );

			if ( preg_match( '/^[a-f0-9]{32}$/i', $value ) ) {
				return $value;
			}

			return new WP_Error(
            	'invalid_session_cookie',
            	'Invalid session cookie format',
            	[ 'status' => 400 ]
        	);
		}
			
		$unique_id = str_replace( '-', '', wp_generate_uuid4() );
		setcookie(
			self::COOKIE_SESSION_KEY,
			$unique_id,
			[
				'expires'  => time() + DAY_IN_SECONDS,
				'path'     => COOKIEPATH,
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			]
		);

		$_COOKIE[ self::COOKIE_SESSION_KEY ] = $unique_id;
       	return $unique_id;
	}

	/**
	 * Stores chatbot conversation archive for the current (anonymous) user.
	 *
	 * @param WP_REST_Request $request Incoming REST request containing 'messages'.
	 * @return WP_REST_Response JSON response indicating success or failure.
	 */
	public function store_archive(WP_REST_Request $request) {

		try {
			$messages = $request->get_param('messages');

			// Walidacja
			if (!is_array($messages)) {
				return new WP_REST_Response([
					'success' => false,
					'message' => 'Invalid messages format'
				], 400);
			}

			$key = self::AGENTS_HISTORY . $this->get_user_session_key();

			// Zapis
			$updated = update_option($key, $messages, false);

			if (!$updated) {
				return new WP_REST_Response([
					'success' => false,
					'message' => 'Failed to save history'
				], 500);
			}

			return new WP_REST_Response([
				'success' => true
			], 200);

		} catch (Throwable $e) {

			return new WP_REST_Response([
				'success' => false,
				'message' => 'Server error',
				'error' => $e->getMessage()
			], 500);
		}
	}

    /**
	 * Retrieves chat archive from ZIP archive.
	 *
	 * @param \WP_REST_Request $request
	 * @return WP_REST_Response|\WP_Error
	 */
	public function handle_get_archive( $request ) {
		$date = $request->get_param( 'date' );
		$id   = $request->get_param( 'id' );

		$zip_path = $this->get_zip_path( $date );
		
		$zip = new ZipArchive();
		$res = $zip->open( $zip_path );

		if ( $res !== true ) {
			return new WP_Error(
				'cannot_open_zip',
				__( 'Cannot open ZIP archive.' ),
				array( 'status' => 500 )
			);
		}

		$opened = true;
		try {
			$json_file = 'msg_user_' . $id . '.json';
			$index     = $zip->locateName( $json_file, ZipArchive::FL_NOCASE | ZipArchive::FL_NODIR );
			
			if ( $index === false ) {
				return new WP_Error( 
					'not_in_archive',
					sprintf( __( 'File %s not in archive.' ), esc_html( $json_file ) ),
					array( 'status' => 404 )
				);
			}
		
			$stream = $zip->getStream( $json_file );
			if ( ! $stream ) {
				return new WP_Error(
					'cannot_open_stream',
					sprintf( __( 'Cannot open stream for %s' ), esc_html( $json_file ) ),
					array( 'status' => 500 )
				);
			}

			// Ograniczenie rozmiaru na wczytywany JSON (np. 5 MB)
			$max_bytes = 5 * 1024 * 1024;
			$contents  = stream_get_contents( $stream, $max_bytes + 1 );
			fclose( $stream );

			if ( $contents === false ) {
				return new WP_Error(
					'read_failed',
					__( 'Failed to read file from archive.' ),
					array( 'status' => 500 )
				);
			}
			
			$data = json_decode( $contents, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return new WP_Error(
					'invalid_json',
					__( 'Invalid JSON in archive: ' ) . json_last_error_msg(),
					array( 'status' => 500 )
				);
			}

			return rest_ensure_response( $data );
		} finally {
			if ( isset( $opened ) && $opened ) {
				$zip->close();
			}
		}   
		
	}

    /**
	 * Returns available archive dates.
	 *
	 * @param \WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function handle_get_archive_list( $request ) {
		$uploads = wp_upload_dir();
		$dir = wp_normalize_path( untrailingslashit( $uploads['basedir'] ) . '/' . self::UPLOAD_ARCHIVES_DIR );

		$files = glob( $dir . '/chat-history-*.zip' );

		$dates = [];
		foreach ( $files as $path ) {
			if ( preg_match( '/(\d{4}-\d{2}-\d{2})/', basename( $path ), $m ) ) {
				$dates[] = $m[1];
			}
		}
		
		$dates = array_unique( $dates );
		sort( $dates );
		return rest_ensure_response( array_values( $dates ) );
	}
	
	/**
	 * Returns list of chat archive files from archive.
	 *
	 * @param \WP_REST_Request $request
	 * @return WP_REST_Response|\WP_Error
	 */
	public function handle_get_archive_items( $request ) {
		
		$date = $request->get_param( 'date' );
		$zip_path = $this->get_zip_path( $date );

		$zip = new ZipArchive();
		$res = $zip->open( $zip_path );
		
		if ( $res !== true ) {
			return new WP_Error(
				'cannot_open_zip', __( 'Cannot open ZIP archive.' ), array( 'status' => 500 )
			);
		}

		$list = [];
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$stat = $zip->statIndex( $i );
			if ( $stat && isset( $stat['name'] ) ) {
				// tylko pliki JSON z katalogu głównego
				if ( str_ends_with( strtolower( $stat['name'] ), '.json' ) ) {
					$fileName = basename( $stat['name'], '.json' );
					$id = str_replace('msg_user_', '', $fileName);
					$list[] = $id;
				}
			}
		}

		$zip->close();
		return rest_ensure_response( array_values( $list ) );
	}

	/**
	 * Returns the ZIP archive path for a given date.
	 *
	 * @param string $date Date in YYYY-MM-DD format.
	 * @return string|\WP_Error File path or error if not found.
	 */
    private function get_zip_path( $date ){

		$uploads = wp_upload_dir();
		$archives_dir = wp_normalize_path( untrailingslashit( $uploads['basedir'] ) . '/' . self::UPLOAD_ARCHIVES_DIR );

		$zip_path = "{$archives_dir}/chat-history-{$date}.zip";

		if ( ! is_readable( $zip_path ) ) {
			return new WP_Error( 
				'archive_not_found', sprintf( __( 'Archive not found for date %s.' ), esc_html( $date ) ),
				array ( 'status' => 404 )
			);
		}

		return $zip_path;

	}
	
	/**
	 * Validates a date string in YYYY-MM-DD format.
	 *
	 * @param string $param   Date value.
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_date( $param ) {
        $d = DateTime::createFromFormat('Y-m-d', $param);
        return $d && $d->format('Y-m-d') === $param;
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
