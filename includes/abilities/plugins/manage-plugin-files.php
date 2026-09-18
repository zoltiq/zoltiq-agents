<?php

namespace Zoltiq\Agents\Includes\Abilities\Plugins;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\File_Mods_Guard;

defined( 'ABSPATH' ) || exit;

class Manage_Plugin_Files extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/manage-plugin-files',
			'args' => array(
				'label'               => __( 'Copy or Move Plugin File', 'zoltiq-agents' ),
				'description'         => __( 'Copy or move a file within the WordPress plugins directory. Both source and destination must remain inside WP_PLUGIN_DIR.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-plugins',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'action'      => array(
							'type'        => 'string',
							'enum'        => array( 'copy', 'move' ),
							'description' => __( 'Operation to perform: copy or move.', 'zoltiq-agents' ),
						),
						'source'      => array(
							'type'        => 'string',
							'description' => __( 'Source file path relative to WP_PLUGIN_DIR.', 'zoltiq-agents' ),
						),
						'destination' => array(
							'type'        => 'string',
							'description' => __( 'Destination file path relative to WP_PLUGIN_DIR.', 'zoltiq-agents' ),
						),
					),
					'required'             => array( 'action', 'source', 'destination' ),
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
						'idempotent'  => false,
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

		$action      = sanitize_text_field( $input['action'] ?? '' );
		$plugins_dir = rtrim( WP_PLUGIN_DIR, '/' );
		$src_real    = realpath( $plugins_dir . '/' . ltrim( sanitize_text_field( $input['source'] ?? '' ), '/' ) );
		$dst_path    = $plugins_dir . '/' . ltrim( sanitize_text_field( $input['destination'] ?? '' ), '/' );
		$dst_dir     = realpath( dirname( $dst_path ) );

		if ( false === $src_real || 0 !== strpos( $src_real, $plugins_dir . '/' ) || ! is_file( $src_real ) ) {
			return array(
				'success' => false,
				'message' => __( 'Source file not found or outside plugin directory.', 'zoltiq-agents' ),
			);
		}

		if ( false === $dst_dir || ( $dst_dir !== $plugins_dir && 0 !== strpos( $dst_dir, $plugins_dir . '/' ) ) ) {
			return array(
				'success' => false,
				'message' => __( 'Destination is outside the plugin directory.', 'zoltiq-agents' ),
			);
		}

		if ( ! in_array( $action, array( 'copy', 'move' ), true ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid action. Use "copy" or "move".', 'zoltiq-agents' ),
			);
		}

		if ( file_exists( $dst_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'Destination already exists. Refusing to overwrite.', 'zoltiq-agents' ),
			);
		}

		if ( 'copy' === $action ) {
			$ok = copy( $src_real, $dst_path );
		} else {
			$ok = rename( $src_real, $dst_path );
		}

		if ( ! $ok ) {
			return array(
				'success' => false,
				'message' => __( 'File operation failed.', 'zoltiq-agents' ),
			);
		}

		return array(
			'success' => true,
			'message' => sprintf( __( 'File %1$s completed to %2$s.', 'zoltiq-agents' ), $action, $dst_path ),
		);
	}
}
