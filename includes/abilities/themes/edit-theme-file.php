<?php

namespace Zoltiq\Agents\Includes\Abilities\Themes;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\File_Mods_Guard;

defined( 'ABSPATH' ) || exit;

class Edit_Theme_File extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/edit-theme-file',
			'args' => array(
				'label'               => __( 'Create or Overwrite Theme File', 'zoltiq-agents' ),
				'description'         => __( 'Creates a new file or overwrites an existing one inside a theme directory. Parent directory must already exist. Defaults to the active theme.', 'zoltiq-agents' ),
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
							'description' => __( 'File path relative to the theme root.', 'zoltiq-agents' ),
						),
						'path'       => array(
							'type'        => 'string',
							'description' => __( 'Alias for "file_path". If both are provided, "file_path" wins.', 'zoltiq-agents' ),
						),
						'content'    => array(
							'type'        => 'string',
							'description' => __( 'New file content.', 'zoltiq-agents' ),
						),
					),
					'allOf'                => array(
						array( 'required' => array( 'content' ) ),
						array(
							'anyOf' => array(
								array( 'required' => array( 'file_path' ) ),
								array( 'required' => array( 'path' ) ),
							),
						),
					),
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

		$slug       = sanitize_text_field( $input['theme_slug'] ?? '' );
		$raw_file   = ! empty( $input['file_path'] ) ? $input['file_path'] : ( $input['path'] ?? '' );
		$rel_file   = sanitize_text_field( (string) $raw_file );
		$content    = $input['content'] ?? '';
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

		$abs_file = $theme_dir . '/' . ltrim( $rel_file, '/' );
		$parent   = realpath( dirname( $abs_file ) );

		if ( false === $parent || ( $parent !== $theme_dir && 0 !== strpos( $parent, $theme_dir . '/' ) ) ) {
			return array(
				'success' => false,
				'message' => __( 'File path is not within the theme directory.', 'zoltiq-agents' ),
			);
		}

		$result = file_put_contents( $abs_file, $content );

		if ( false === $result ) {
			return array(
				'success' => false,
				'message' => __( 'Could not write file.', 'zoltiq-agents' ),
			);
		}

		return array(
			'success' => true,
			'path'    => $abs_file,
			'message' => __( 'Theme file saved.', 'zoltiq-agents' ),
		);
	}
}
