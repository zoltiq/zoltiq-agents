<?php

namespace Zoltiq\Agents\Includes\Abilities\FileManager;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Read_Debug_Log extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/read-debug-log',
			'args' => array(
				'label'               => __( 'Read Debug Log', 'zoltiq-agents' ),
				'description'         => __( 'Returns the contents of wp-content/debug.log. Use the lines parameter to limit output to the last N lines.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-file-manager',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'lines' => array(
							'type'        => 'integer',
							'default'     => 0,
							'minimum'     => 0,
							'maximum'     => 10000,
							'description' => __( 'Return only the last N lines. 0 returns the full file.', 'zoltiq-agents' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'content' => array( 'type' => 'string' ),
						'size'    => array( 'type' => 'integer' ),
						'message' => array( 'type' => 'string' ),
					),
					'required'             => array( 'success' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => false,
						'type'   => 'tool',
					),
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		$log_path = WP_CONTENT_DIR . '/debug.log';

		if ( ! is_file( $log_path ) ) {
			$logging_on = ( defined( 'WP_DEBUG_LOG' ) && \WP_DEBUG_LOG ) && ( defined( 'WP_DEBUG' ) && \WP_DEBUG );
			$reason     = $logging_on
				? __( 'No entries have been written yet.', 'zoltiq-agents' )
				: __( 'WP_DEBUG and/or WP_DEBUG_LOG are not enabled in wp-config.php, so nothing is being written.', 'zoltiq-agents' );
			return array(
				'success' => false,
				'message' => sprintf( __( 'debug.log does not exist. %s', 'zoltiq-agents' ), $reason ),
			);
		}

		$content = file_get_contents( $log_path );

		if ( false === $content ) {
			return array(
				'success' => false,
				'message' => __( 'Could not read debug.log.', 'zoltiq-agents' ),
			);
		}

		$lines = isset( $input['lines'] ) ? (int) $input['lines'] : 0;

		if ( $lines > 0 ) {
			$all_lines = explode( "\n", $content );
			$content   = implode( "\n", array_slice( $all_lines, -$lines ) );
		}

		return array(
			'success' => true,
			'content' => $content,
			'size'    => strlen( $content ),
		);
	}
}
