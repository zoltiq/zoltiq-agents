<?php

namespace Zoltiq\Agents\Includes\Abilities\Block;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\File_Mods_Guard;
use Zoltiq\Agents\Includes\Abilities\Utilities\Global_Styles\Global_Styles_Db;
use Zoltiq\Agents\Includes\Abilities\Utilities\Global_Styles\Global_Styles_File;

defined( 'ABSPATH' ) || exit;

class Update_Theme_Json extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/update-theme-json',
			'args' => array(
				'label'               => __( 'Update theme.json', 'zoltiq-agents' ),
				'description'         => __( 'Writes a theme.json file directly. Defaults to the active child theme (or single theme); refuses to edit the parent theme. By default deep-merges into the existing file; pass merge=false to replace.', 'zoltiq-agents' ),
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
							'description' => __( 'Target theme folder. Defaults to the active child theme; falls back to the parent when no child is active.', 'zoltiq-agents' ),
						),
						'content'    => array(
							'type'        => array( 'string', 'object' ),
							'description' => __( 'theme.json content as a JSON string or object. Cannot be empty.', 'zoltiq-agents' ),
						),
						'merge'      => array(
							'type'        => 'boolean',
							'default'     => true,
							'description' => __( 'true deep-merges with the existing file; false replaces it.', 'zoltiq-agents' ),
						),
					),
					'required'             => array( 'content' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'  => array( 'type' => 'boolean' ),
						'theme'    => array( 'type' => 'string' ),
						'path'     => array( 'type' => 'string' ),
						'bytes'    => array( 'type' => 'integer' ),
						'warnings' => array( 'type' => 'array' ),
						'message'  => array( 'type' => 'string' ),
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

		$theme_slug = sanitize_key( $input['theme_slug'] ?? '' );
		$merge      = ! isset( $input['merge'] ) || (bool) $input['merge'];

		$warnings = array();
		if ( '' !== $theme_slug ) {
			$dir = Global_Styles_File::resolve_theme_dir( $theme_slug );
			if ( is_wp_error( $dir ) ) {
				return array(
					'success' => false,
					'message' => $dir->get_error_message(),
				);
			}
			$child      = Global_Styles_File::get_child_theme_dir();
			$parent_dir = Global_Styles_File::get_parent_theme_dir();
			if ( null !== $child && $dir === $parent_dir ) {
				return array(
					'success' => false,
					'message' => __( 'Refusing to edit the parent theme directly when a child theme is active. Write to the child theme or use Global Styles instead.', 'zoltiq-agents' ),
				);
			}
		} else {
			$dir = Global_Styles_File::get_child_theme_dir();
			if ( null === $dir ) {
				$dir        = Global_Styles_File::get_parent_theme_dir();
				$warnings[] = __( 'No child theme is active — writing to the parent theme. Updates will be lost when the theme is upgraded. Create a child theme to manage theme.json safely.', 'zoltiq-agents' );
			}
		}

		$payload = $this->coerce_array( $input['content'] ?? null );
		if ( is_wp_error( $payload ) ) {
			return $this->error_response( $payload );
		}

		$path     = Global_Styles_File::theme_json_path( $dir );
		$existing = is_file( $path ) ? Global_Styles_File::read_json( $path ) : array();
		if ( is_wp_error( $existing ) ) {
			return $this->error_response( $existing );
		}

		$next = $merge ? Global_Styles_Db::deep_merge( is_array( $existing ) ? $existing : array(), $payload ) : $payload;

		$valid = Global_Styles_Db::validate_data( $next );
		if ( is_wp_error( $valid ) ) {
			return $this->error_response( $valid );
		}
		$valid = Global_Styles_Db::validate_block_styles( $next );
		if ( is_wp_error( $valid ) ) {
			return $this->error_response( $valid );
		}

		$bytes = Global_Styles_File::write_json( $path, $next );
		if ( is_wp_error( $bytes ) ) {
			return $this->error_response( $bytes );
		}

		$warnings[] = __( 'Site Editor saves will create a wp_global_styles DB record that overrides this file on the next save.', 'zoltiq-agents' );

		return array(
			'success'  => true,
			'message'  => sprintf( __( 'Wrote theme.json to %s.', 'zoltiq-agents' ), $path ),
			'theme'    => basename( $dir ),
			'path'     => $path,
			'bytes'    => (int) $bytes,
			'warnings' => $warnings,
		);
	}

	/**
	 * @return array|\WP_Error
	 */
	private function coerce_array( $value ) {
		if ( is_array( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			return Global_Styles_Db::parse_json( $value );
		}
		if ( is_object( $value ) ) {
			return json_decode( wp_json_encode( $value ), true ) ?: array();
		}
		return new \WP_Error( 'missing_content', __( 'Content is required.', 'zoltiq-agents' ) );
	}

	private function error_response( \WP_Error $err ): array {
		return array(
			'success' => false,
			'message' => $err->get_error_message(),
		);
	}
}
