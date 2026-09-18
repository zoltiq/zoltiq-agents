<?php

namespace Zoltiq\Agents\Includes\Abilities\FileManager;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Read_File extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/read-file',
			'args' => array(
				'label'               => __( 'Read File', 'zoltiq-agents' ),
				'description'         => __( 'Reads the contents of a file within the WordPress installation. Path must be relative to ABSPATH.', 'zoltiq-agents' ),
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
							'description' => __( 'File path relative to ABSPATH (e.g. wp-content/uploads/test.txt).', 'zoltiq-agents' ),
						),
					),
					'required'             => array( 'path' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'content' => array( 'type' => 'string' ),
						'path'    => array( 'type' => 'string' ),
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
		$rel_path = sanitize_text_field( $input['path'] ?? '' );
		$base     = rtrim( realpath( ABSPATH ) ?: ABSPATH, '/' );
		$abs_path = $base . '/' . ltrim( $rel_path, '/' );
		$parent   = realpath( dirname( $abs_path ) );

		if ( false === $parent || ( $parent !== $base && 0 !== strpos( $parent, $base . '/' ) ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid or disallowed file path.', 'zoltiq-agents' ),
			);
		}

		if ( ! is_file( $abs_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'File does not exist.', 'zoltiq-agents' ),
			);
		}

		$content = file_get_contents( $abs_path );

		if ( false === $content ) {
			return array(
				'success' => false,
				'message' => __( 'Could not read file.', 'zoltiq-agents' ),
			);
		}

		return array(
			'success' => true,
			'content' => $content,
			'path'    => realpath( $abs_path ) ?: $abs_path,
			'size'    => strlen( $content ),
		);
	}
}
