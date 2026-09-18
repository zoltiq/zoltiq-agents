<?php

namespace Zoltiq\Agents\Includes\Abilities\FileManager;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\File_Mods_Guard;

defined( 'ABSPATH' ) || exit;

class Delete_File extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/delete-file',
			'args' => array(
				'label'               => __( 'Delete File', 'zoltiq-agents' ),
				'description'         => __( 'Deletes a file within the WordPress installation. Path must be relative to ABSPATH.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-file-manager',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'path' => array(
							'type'        => 'string',
							'description' => __( 'File path relative to ABSPATH.', 'zoltiq-agents' ),
						),
					),
					'required'             => array( 'path' ),
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

		$rel_path = sanitize_text_field( $input['path'] ?? '' );
		$base     = rtrim( realpath( ABSPATH ) ?: ABSPATH, '/' );
		$real     = realpath( $base . '/' . ltrim( $rel_path, '/' ) );

		if ( false === $real || 0 !== strpos( $real, $base . '/' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid or disallowed file path.', 'zoltiq-agents' ),
			);
		}

		if ( ! is_file( $real ) ) {
			return array(
				'success' => false,
				'message' => __( 'File does not exist.', 'zoltiq-agents' ),
			);
		}

		if ( ! wp_delete_file( $real ) ) {
			return array(
				'success' => false,
				'message' => __( 'Could not delete file.', 'zoltiq-agents' ),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'File deleted.', 'zoltiq-agents' ),
		);
	}
}
