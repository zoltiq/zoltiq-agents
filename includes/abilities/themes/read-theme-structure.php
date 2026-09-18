<?php

namespace Zoltiq\Agents\Includes\Abilities\Themes;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Read_Theme_Structure extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/read-theme-structure',
			'args' => array(
				'label'               => __( 'Read Theme Structure', 'zoltiq-agents' ),
				'description'         => __( 'Lists all files within a theme directory. Defaults to the active theme when no slug is provided.', 'zoltiq-agents' ),
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
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'    => array( 'type' => 'boolean' ),
						'theme_path' => array( 'type' => 'string' ),
						'files'      => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'message'    => array( 'type' => 'string' ),
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
		$themes_dir = rtrim( get_theme_root(), '/' );
		$theme_dir  = '' !== $slug
			? realpath( $themes_dir . '/' . $slug )
			: realpath( get_stylesheet_directory() );

		if ( false === $theme_dir || 0 !== strpos( $theme_dir, $themes_dir ) || ! is_dir( $theme_dir ) ) {
			return array(
				'success' => false,
				'message' => __( 'Theme directory not found.', 'zoltiq-agents' ),
			);
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $theme_dir, \FilesystemIterator::SKIP_DOTS )
		);

		$files = array();
		foreach ( $iterator as $file ) {
			if ( $file->isFile() ) {
				$files[] = str_replace( $theme_dir . '/', '', $file->getPathname() );
			}
		}

		sort( $files );

		return array(
			'success'    => true,
			'theme_path' => $theme_dir,
			'files'      => $files,
		);
	}
}
