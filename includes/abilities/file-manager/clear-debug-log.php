<?php

namespace Zoltiq\Agents\Includes\Abilities\FileManager;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\File_Mods_Guard;

defined( 'ABSPATH' ) || exit;

class Clear_Debug_Log extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/clear-debug-log',
			'args' => array(
				'label'               => __( 'Clear Debug Log', 'zoltiq-agents' ),
				'description'         => __( 'Truncates wp-content/debug.log to zero bytes.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-file-manager',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'properties'           => array(),
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
						'destructive' => true,
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

		$log_path = WP_CONTENT_DIR . '/debug.log';

		if ( ! is_file( $log_path ) ) {
			return array(
				'success' => true,
				'message' => __( 'debug.log does not exist; nothing to clear.', 'zoltiq-agents' ),
			);
		}

		if ( false === file_put_contents( $log_path, '' ) ) { 
			return array(
				'success' => false,
				'message' => __( 'Could not clear debug.log.', 'zoltiq-agents' ),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'debug.log cleared.', 'zoltiq-agents' ),
		);
	}
}
