<?php

namespace Zoltiq\Agents\Includes\Abilities\FileManager;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\File_Mods_Guard;

defined( 'ABSPATH' ) || exit;

class Edit_Wp_Config extends Ability_Definition {

	private const PROTECTED = array(
		'DB_NAME',
		'DB_USER',
		'DB_PASSWORD',
		'DB_HOST',
		'AUTH_KEY',
		'SECURE_AUTH_KEY',
		'LOGGED_IN_KEY',
		'NONCE_KEY',
		'AUTH_SALT',
		'SECURE_AUTH_SALT',
		'LOGGED_IN_SALT',
		'NONCE_SALT',
		'SECRET_KEY',
	);

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/edit-wp-config',
			'args' => array(
				'label'               => __( 'Edit wp-config.php', 'zoltiq-agents' ),
				'description'         => __( 'Updates the value of an existing non-sensitive constant in wp-config.php. Protected credential and secret constants cannot be modified.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-file-manager',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'constant_name' => array(
							'type'        => 'string',
							'description' => __( 'Name of the constant to update (e.g. WP_DEBUG).', 'zoltiq-agents' ),
						),
						'value'         => array(
							'type'        => 'string',
							'description' => __( 'New string value for the constant.', 'zoltiq-agents' ),
						),
					),
					'required'             => array( 'constant_name', 'value' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'message' => array( 'type' => 'string' ),
					),
					'required'             => array( 'success', 'message' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => false,
						'type'   => 'tool',
					),
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		$blocked = File_Mods_Guard::blocked_response();
		if ( null !== $blocked ) {
			return $blocked;
		}

		$name  = strtoupper( sanitize_text_field( $input['constant_name'] ?? '' ) );
		$value = $input['value'] ?? '';

		if ( '' === $name || ! preg_match( '/^[A-Z_][A-Z0-9_]*$/', $name ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid constant name.', 'zoltiq-agents' ),
			);
		}

		if ( in_array( $name, self::PROTECTED, true ) ) {
			return array(
				'success' => false,
				'message' => __( 'This constant is protected and cannot be modified.', 'zoltiq-agents' ),
			);
		}

		$config_path = $this->locate_wp_config();

		if ( null === $config_path ) {
			return array(
				'success' => false,
				'message' => __( 'wp-config.php not found.', 'zoltiq-agents' ),
			);
		}

		$raw     = file_get_contents( $config_path );
		$escaped = addslashes( $value );
		$pattern = "/define\(\s*(['\"])" . preg_quote( $name, '/' ) . "\\1\s*,\s*(?:'[^']*'|\"[^\"]*\"|[^)]+)\s*\)/";
		$updated = preg_replace( $pattern, "define( '{$name}', '{$escaped}' )", $raw, -1, $count );

		if ( 0 === $count ) {
			return array(
				'success' => false,
				'message' => __( 'Constant not found in wp-config.php.', 'zoltiq-agents' ),
			);
		}

		if ( false === file_put_contents( $config_path, $updated ) ) {
			return array(
				'success' => false,
				'message' => __( 'Could not write wp-config.php.', 'zoltiq-agents' ),
			);
		}

		return array(
			'success' => true,
			'message' => sprintf( __( 'Constant %s updated.', 'zoltiq-agents' ), $name ),
		);
	}

	private function locate_wp_config(): ?string {
		$candidates = array(
			ABSPATH . 'wp-config.php',
			dirname( rtrim( ABSPATH, '/' ) ) . '/wp-config.php',
		);
		foreach ( $candidates as $path ) {
			if ( is_file( $path ) ) {
				return $path;
			}
		}
		return null;
	}
}
