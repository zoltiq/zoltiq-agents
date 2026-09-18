<?php

namespace Zoltiq\Agents\Includes\Abilities\FileManager;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\File_Mods_Guard;

defined( 'ABSPATH' ) || exit;

class Edit_File extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/edit-file',
			'args' => array(
				'label'               => __( 'Create or Overwrite File', 'zoltiq-agents' ),
				'description'         => __( 'Creates a new file or overwrites an existing one within the WordPress installation. Parent directory must already exist. Path must be relative to ABSPATH.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-file-manager',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'path'    => array(
							'type'        => 'string',
							'description' => __( 'File path relative to ABSPATH.', 'zoltiq-agents' ),
						),
						'content' => array(
							'type'        => 'string',
							'description' => __( 'New file content.', 'zoltiq-agents' ),
						),
					),
					'required'             => array( 'path', 'content' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'path'    => array( 'type' => 'string' ),
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

		$rel_path = sanitize_text_field( $input['path'] ?? '' );
		$content  = $input['content'] ?? '';
		$base     = rtrim( realpath( ABSPATH ) ?: ABSPATH, '/' );
		$abs_path = $base . '/' . ltrim( $rel_path, '/' );
		$parent   = realpath( dirname( $abs_path ) );

		if ( false === $parent || ( $parent !== $base && 0 !== strpos( $parent, $base . '/' ) ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid or disallowed file path.', 'zoltiq-agents' ),
			);
		}

		$result = file_put_contents( $abs_path, $content ); 

		if ( false === $result ) {
			return array(
				'success' => false,
				'message' => __( 'Could not write file.', 'zoltiq-agents' ),
			);
		}

		return array(
			'success' => true,
			'path'    => $abs_path,
			'message' => __( 'File saved.', 'zoltiq-agents' ),
		);
	}
}
