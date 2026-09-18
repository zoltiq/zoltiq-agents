<?php

namespace Zoltiq\Agents\Includes\Abilities\FileManager;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\File_Mods_Guard;

defined( 'ABSPATH' ) || exit;

class Create_File extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/create-file',
			'args' => array(
				'label'               => __( 'Create File', 'zoltiq-agents' ),
				'description'         => __( 'Creates a new file within the WordPress installation. Fails if the file already exists. Path must be relative to ABSPATH. Pass create_dirs=true to auto-create any missing parent directories.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-file-manager',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'path'        => array(
							'type'        => 'string',
							'description' => __( 'File path relative to ABSPATH.', 'zoltiq-agents' ),
						),
						'content'     => array(
							'type'        => 'string',
							'default'     => '',
							'description' => __( 'Initial file content.', 'zoltiq-agents' ),
						),
						'create_dirs' => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => __( 'If true, missing parent directories are created (wp_mkdir_p) before the file is written. Defaults to false.', 'zoltiq-agents' ),
						),
					),
					'required'             => array( 'path' ),
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

		$rel_path    = sanitize_text_field( $input['path'] ?? '' );
		$content     = $input['content'] ?? '';
		$create_dirs = ! empty( $input['create_dirs'] );
		$base        = rtrim( realpath( ABSPATH ) ?: ABSPATH, '/' );
		$abs_path    = $base . '/' . ltrim( $rel_path, '/' );
		$parent_want = dirname( $abs_path );
		$parent      = realpath( $parent_want );

		if ( false === $parent ) {
			if ( ! $create_dirs ) {
				return array(
					'success' => false,
					'message' => __( 'Parent directory does not exist. Pass create_dirs=true to create it.', 'zoltiq-agents' ),
				);
			}
			if ( 0 !== strpos( $parent_want, $base . '/' ) ) {
				return array(
					'success' => false,
					'message' => __( 'Invalid or disallowed file path.', 'zoltiq-agents' ),
				);
			}
			if ( ! wp_mkdir_p( $parent_want ) ) {
				return array(
					'success' => false,
					'message' => __( 'Could not create parent directories.', 'zoltiq-agents' ),
				);
			}
			$parent = realpath( $parent_want );
		}

		if ( false === $parent || 0 !== strpos( $parent, $base . '/' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid or disallowed file path.', 'zoltiq-agents' ),
			);
		}

		if ( file_exists( $abs_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'File already exists. Use file-edit to overwrite.', 'zoltiq-agents' ),
			);
		}

		$result = file_put_contents( $abs_path, $content ); 

		if ( false === $result ) {
			return array(
				'success' => false,
				'message' => __( 'Could not create file.', 'zoltiq-agents' ),
			);
		}

		return array(
			'success' => true,
			'path'    => $abs_path,
			'message' => __( 'File created.', 'zoltiq-agents' ),
		);
	}
}
