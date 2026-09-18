<?php

namespace Zoltiq\Agents\Includes\Abilities\Themes;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Read_Theme_Code extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/read-theme-code',
			'args' => array(
				'label'               => __( 'Read Theme Code', 'zoltiq-agents' ),
				'description'         => __( 'Reads the contents of a file inside a theme directory. Defaults to the active theme.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-themes',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'theme_slug' => array(
							'type'        => 'string',
							'default'     => '',
							'description' => __( 'Theme folder name. Defaults to the active theme.', 'zoltiq-agents' ),
						),
						'file_path'  => array(
							'type'        => 'string',
							'description' => __( 'File path relative to the theme root (e.g. functions.php).', 'zoltiq-agents' ),
						),
					),
					'required'             => array( 'file_path' ),
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
		$slug       = sanitize_text_field( $input['theme_slug'] ?? '' );
		$rel_file   = sanitize_text_field( $input['file_path'] ?? '' );
		$themes_dir = rtrim( get_theme_root(), '/' );
		$theme_dir  = '' !== $slug
			? realpath( $themes_dir . '/' . $slug )
			: realpath( get_stylesheet_directory() );

		if ( false === $theme_dir || 0 !== strpos( $theme_dir, $themes_dir . '/' ) || ! is_dir( $theme_dir ) ) {
			return array(
				'success' => false,
				'message' => __( 'Theme directory not found.', 'zoltiq-agents' ),
			);
		}

		$abs_file = realpath( $theme_dir . '/' . ltrim( $rel_file, '/' ) );

		if ( false === $abs_file || 0 !== strpos( $abs_file, $theme_dir . '/' ) || ! is_file( $abs_file ) ) {
			return array(
				'success' => false,
				'message' => __( 'File not found within theme directory.', 'zoltiq-agents' ),
			);
		}

		$content = file_get_contents( $abs_file );

		if ( false === $content ) {
			return array(
				'success' => false,
				'message' => __( 'Could not read file.', 'zoltiq-agents' ),
			);
		}

		return array(
			'success' => true,
			'content' => $content,
			'path'    => $abs_file,
			'size'    => strlen( $content ),
		);
	}
}
