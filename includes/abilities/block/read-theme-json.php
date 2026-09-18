<?php

namespace Zoltiq\Agents\Includes\Abilities\Block;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Global_Styles\Global_Styles_File;

defined( 'ABSPATH' ) || exit;

class Read_Theme_Json extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/read-theme-json',
			'args' => array(
				'label'               => __( 'Read theme.json', 'zoltiq-agents' ),
				'description'         => __( 'Returns the raw parsed contents of a theme.json file. Defaults to the active stylesheet; pass theme_slug to target a specific theme folder, or theme_type=parent to read the parent theme when a child is active.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-block',
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
							'description' => __( 'Theme folder name. Defaults to the active stylesheet.', 'zoltiq-agents' ),
						),
						'theme_type' => array(
							'type'        => 'string',
							'enum'        => array( '', 'child', 'parent' ),
							'default'     => '',
							'description' => __( 'When a child theme is active, "parent" forces reading the parent theme.json. Ignored when theme_slug is set.', 'zoltiq-agents' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'theme'   => array( 'type' => 'string' ),
						'path'    => array( 'type' => 'string' ),
						'data'    => array( 'type' => 'object' ),
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
		$theme_slug = sanitize_key( $input['theme_slug'] ?? '' );
		$theme_type = sanitize_text_field( $input['theme_type'] ?? '' );

		if ( '' !== $theme_slug ) {
			$dir = Global_Styles_File::resolve_theme_dir( $theme_slug );
			if ( is_wp_error( $dir ) ) {
				return array(
					'success' => false,
					'message' => $dir->get_error_message(),
				);
			}
			$theme = $theme_slug;
		} elseif ( 'parent' === $theme_type ) {
			$dir   = Global_Styles_File::get_parent_theme_dir();
			$theme = basename( $dir );
		} else {
			$child = Global_Styles_File::get_child_theme_dir();
			if ( null !== $child ) {
				$dir   = $child;
				$theme = basename( $child );
			} else {
				$dir   = Global_Styles_File::get_parent_theme_dir();
				$theme = basename( $dir );
			}
		}

		$path = Global_Styles_File::theme_json_path( $dir );
		if ( ! is_file( $path ) ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'theme.json not found at %s.', 'zoltiq-agents' ), $path ),
				'theme'   => $theme,
				'path'    => $path,
			);
		}

		$data = Global_Styles_File::read_json( $path );
		if ( is_wp_error( $data ) ) {
			return array(
				'success' => false,
				'message' => $data->get_error_message(),
				'theme'   => $theme,
				'path'    => $path,
			);
		}

		return array(
			'success' => true,
			'theme'   => $theme,
			'path'    => $path,
			'data'    => $data,
		);
	}
}
