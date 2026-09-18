<?php

namespace Zoltiq\Agents\Includes\Abilities\Block;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Block_Style_Variations\Variation_Db;
use Zoltiq\Agents\Includes\Abilities\Utilities\Block_Style_Variations\Variation_Detector;
use Zoltiq\Agents\Includes\Abilities\Utilities\Block_Style_Variations\Variation_File;
use Zoltiq\Agents\Includes\Abilities\Utilities\File_Mods_Guard;
use Zoltiq\Agents\Includes\Abilities\Utilities\Global_Styles\Global_Styles_Db;

defined( 'ABSPATH' ) || exit;

class Create_Block_Style_Variation extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/create-block-style-variation',
			'args' => array(
				'label'               => __( 'Create Block Style Variation', 'zoltiq-agents' ),
				'description'         => __( 'Creates a Block Style Variation. Defaults to the database. Pass source=child_theme / theme / plugin to write a <slug>.json file. Provide "content" (full variation JSON) or "section"+"data" to seed one section.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-block',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'slug'        => array(
							'type'        => 'string',
							'description' => __( 'Variation slug (e.g. "dark", "high-contrast"). Lowercase letters, digits, dashes, underscores.', 'zoltiq-agents' ),
						),
						'title'       => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
						'theme'       => array(
							'type'    => 'string',
							'default' => '',
						),
						'source'      => array(
							'type'    => 'string',
							'enum'    => array( 'db', 'theme', 'child_theme', 'plugin' ),
							'default' => 'db',
						),
						'theme_slug'  => array(
							'type'    => 'string',
							'default' => '',
						),
						'plugin_slug' => array(
							'type'    => 'string',
							'default' => '',
						),
						'content'     => array(
							'type'        => array( 'string', 'object' ),
							'description' => __( 'Full variation content as JSON string or object.', 'zoltiq-agents' ),
						),
						'section'     => array(
							'type' => 'string',
							'enum' => array( '', 'colors', 'typography', 'spacing', 'layout', 'blockStyles' ),
						),
						'data'        => array(
							'type'        => array( 'string', 'object' ),
							'description' => __( 'Section data; required when "section" is provided.', 'zoltiq-agents' ),
						),
					),
					'required'             => array( 'slug' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'   => array( 'type' => 'boolean' ),
						'variation' => array( 'type' => 'object' ),
						'warnings'  => array( 'type' => 'array' ),
						'locations' => array( 'type' => 'array' ),
						'message'   => array( 'type' => 'string' ),
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
						'idempotent'  => false,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		$slug        = sanitize_title( (string) ( $input['slug'] ?? '' ) );
		$source      = sanitize_text_field( $input['source'] ?? 'db' );
		$theme       = sanitize_key( $input['theme'] ?? '' );
		$theme_slug  = sanitize_key( $input['theme_slug'] ?? '' );
		$plugin_slug = sanitize_key( $input['plugin_slug'] ?? '' );

		if ( '' === $slug || ! Variation_File::is_valid_bare_slug( $slug ) ) {
			return array(
				'success' => false,
				'message' => __( 'Slug is invalid. Use lowercase letters, digits, dashes, or underscores.', 'zoltiq-agents' ),
			);
		}

		// File-mods guard for any file destination (Scenario 16).
		if ( in_array( $source, array( 'theme', 'child_theme', 'plugin' ), true ) ) {
			$blocked = File_Mods_Guard::blocked_response();
			if ( null !== $blocked ) {
				return $blocked;
			}
		}

		$payload = $this->resolve_payload( $input );
		if ( is_wp_error( $payload ) ) {
			return $this->error_response( $payload );
		}

		$existing = Variation_Detector::locate( $slug, $theme );
		if ( ! empty( $existing ) ) {
			return array(
				'success'   => false,
				'message'   => sprintf( __( 'A Block Style Variation with slug "%s" already exists. Use update or pick a different slug.', 'zoltiq-agents' ), $slug ),
				'locations' => $existing,
			);
		}

		switch ( $source ) {
			case 'db':
				return $this->create_db( $slug, $payload, $theme, $input );
			case 'child_theme':
				return $this->create_theme_file( $slug, $payload, true, '', $input );
			case 'theme':
				return $this->create_theme_file( $slug, $payload, false, $theme_slug, $input );
			case 'plugin':
				return $this->create_plugin_file( $slug, $payload, $plugin_slug, $input );
		}

		return array(
			'success' => false,
			'message' => __( 'Unknown source.', 'zoltiq-agents' ),
		);
	}

	private function resolve_payload( array $input ) {
		$section = (string) ( $input['section'] ?? '' );
		if ( '' !== $section ) {
			$norm = Variation_Db::normalize_section( $section );
			if ( ! Variation_Db::valid_section( $norm ) ) {
				return new \WP_Error(
					'invalid_section',
					sprintf( __( 'Invalid section. Allowed: %s.', 'zoltiq-agents' ), implode( ', ', Variation_Db::valid_sections() ) )
				);
			}
			$data = $this->coerce_array( $input['data'] ?? null );
			if ( is_wp_error( $data ) ) {
				return $data;
			}
			if ( empty( $data ) ) {
				return new \WP_Error( 'empty_section_data', __( 'Section data is required when "section" is provided.', 'zoltiq-agents' ) );
			}
			$payload = array();
			foreach ( Variation_Db::SECTION_PATHS[ $norm ] as $path ) {
				$value = Global_Styles_Db::path_get( $data, $path );
				if ( null !== $value ) {
					Global_Styles_Db::path_set( $payload, $path, $value );
				}
			}
			return $payload;
		}

		return $this->coerce_array( $input['content'] ?? null );
	}

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

	private function create_db( string $slug, array $payload, string $theme, array $input ): array {
		$theme = '' !== $theme ? $theme : (string) get_stylesheet();
		$id    = Variation_Db::create(
			$theme,
			$slug,
			$payload,
			array(
				'title'       => (string) ( $input['title'] ?? '' ),
				'description' => (string) ( $input['description'] ?? '' ),
			)
		);
		if ( is_wp_error( $id ) ) {
			return $this->error_response( $id );
		}

		$post     = get_post( (int) $id );
		$warnings = array();
		if ( is_multisite() ) {
			$warnings[] = __( 'On multisite, this DB variation is scoped to the current site only.', 'zoltiq-agents' );
		}
		if ( $theme !== (string) get_stylesheet() ) {
			$warnings[] = __( 'You are creating a variation for a non-active theme — it will only appear after that theme is activated.', 'zoltiq-agents' );
		}

		return array(
			'success'   => true,
			'message'   => sprintf( __( 'Created variation "%1$s" for theme "%2$s".', 'zoltiq-agents' ), $slug, $theme ),
			'variation' => $post ? Variation_Db::to_row( $post, true ) : array(),
			'warnings'  => $warnings,
		);
	}

	private function create_theme_file( string $slug, array $payload, bool $force_child, string $theme_slug, array $input ): array {
		$warnings = array();
		if ( $force_child ) {
			$dir = Variation_File::get_child_theme_dir();
			if ( null === $dir ) {
				return array(
					'success' => false,
					'message' => __( 'No child theme is active. Create a child theme first, or use source=db.', 'zoltiq-agents' ),
				);
			}
		} else {
			$dir = '' !== $theme_slug ? Variation_File::resolve_theme_dir( $theme_slug ) : Variation_File::get_parent_theme_dir();
			if ( is_wp_error( $dir ) ) {
				return $this->error_response( $dir );
			}
			$child = Variation_File::get_child_theme_dir();
			if ( null !== $child && $child !== $dir ) {
				$warnings[] = __( 'Writing to the parent theme — changes will be lost when the theme updates. Prefer source=child_theme.', 'zoltiq-agents' );
			}
		}

		$valid = Global_Styles_Db::validate_data( $payload );
		if ( is_wp_error( $valid ) ) {
			return $this->error_response( $valid );
		}
		$valid = Global_Styles_Db::validate_block_styles( $payload );
		if ( is_wp_error( $valid ) ) {
			return $this->error_response( $valid );
		}

		$styles_dir = Variation_File::ensure_styles_dir( $dir );
		if ( is_wp_error( $styles_dir ) ) {
			return $this->error_response( $styles_dir );
		}

		$abs = Variation_File::resolve_variation_path( $dir, $slug );
		if ( is_wp_error( $abs ) ) {
			return $this->error_response( $abs );
		}

		$bytes = Variation_File::write_json( $abs, $payload );
		if ( is_wp_error( $bytes ) ) {
			return $this->error_response( $bytes );
		}

		$child      = Variation_File::get_child_theme_dir();
		$is_child   = ( null !== $child && $dir === $child );
		$warnings[] = __( 'Site Editor saves create a wp_global_styles DB record that will override this file copy.', 'zoltiq-agents' );

		return array(
			'success'   => true,
			'message'   => sprintf( __( 'Wrote variation "%1$s" to %2$s.', 'zoltiq-agents' ), $slug, $abs ),
			'variation' => array(
				'source'     => 'theme',
				'theme_type' => $is_child ? 'child' : ( null !== $child ? 'parent' : 'theme' ),
				'theme'      => basename( $dir ),
				'slug'       => $slug,
				'path'       => $abs,
				'bytes'      => (int) $bytes,
			),
			'warnings'  => $warnings,
		);
	}

	private function create_plugin_file( string $slug, array $payload, string $plugin_slug, array $input ): array {
		if ( '' === $plugin_slug ) {
			return array(
				'success' => false,
				'message' => __( 'plugin_slug is required when source=plugin.', 'zoltiq-agents' ),
			);
		}

		$plugin = Variation_File::resolve_plugin_dir( $plugin_slug );
		if ( is_wp_error( $plugin ) ) {
			return $this->error_response( $plugin );
		}

		$valid = Global_Styles_Db::validate_data( $payload );
		if ( is_wp_error( $valid ) ) {
			return $this->error_response( $valid );
		}
		$valid = Global_Styles_Db::validate_block_styles( $payload );
		if ( is_wp_error( $valid ) ) {
			return $this->error_response( $valid );
		}

		$warnings = array();
		if ( ! $plugin['active'] ) {
			$warnings[] = sprintf( __( 'Plugin "%s" is inactive — the variation will not register until the plugin is activated.', 'zoltiq-agents' ), $plugin_slug );
		}

		$styles_dir = Variation_File::ensure_styles_dir( $plugin['path'] );
		if ( is_wp_error( $styles_dir ) ) {
			return $this->error_response( $styles_dir );
		}

		$abs = Variation_File::resolve_variation_path( $plugin['path'], $slug );
		if ( is_wp_error( $abs ) ) {
			return $this->error_response( $abs );
		}

		$bytes = Variation_File::write_json( $abs, $payload );
		if ( is_wp_error( $bytes ) ) {
			return $this->error_response( $bytes );
		}

		return array(
			'success'   => true,
			'message'   => sprintf( __( 'Wrote variation "%1$s" to %2$s.', 'zoltiq-agents' ), $slug, $abs ),
			'variation' => array(
				'source'        => 'plugin',
				'plugin'        => $plugin_slug,
				'plugin_active' => (bool) $plugin['active'],
				'slug'          => $slug,
				'path'          => $abs,
				'bytes'         => (int) $bytes,
			),
			'warnings'  => $warnings,
		);
	}

	private function error_response( \WP_Error $err ): array {
		return array(
			'success' => false,
			'message' => $err->get_error_message(),
		);
	}
}
