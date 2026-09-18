<?php

namespace Zoltiq\Agents\Includes\Abilities\Block;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\File_Mods_Guard;
use Zoltiq\Agents\Includes\Abilities\Utilities\Global_Styles\Global_Styles_Db;
use Zoltiq\Agents\Includes\Abilities\Utilities\Global_Styles\Global_Styles_Detector;
use Zoltiq\Agents\Includes\Abilities\Utilities\Global_Styles\Global_Styles_File;

defined( 'ABSPATH' ) || exit;

class Create_Global_Style extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/create-global-style',
			'args' => array(
				'label'               => __( 'Create Global Style', 'zoltiq-agents' ),
				'description'         => __( 'Creates Global Styles for a theme. Defaults to the database (wp_global_styles). Pass source=child_theme / theme / plugin to write theme.json. Provide either "content" (full theme.json JSON object or string) or "section" + "data" to seed a single section.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-block',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'theme'       => array(
							'type'        => 'string',
							'default'     => '',
							'description' => __( 'Theme slug. Defaults to the active stylesheet.', 'zoltiq-agents' ),
						),
						'source'      => array(
							'type'    => 'string',
							'enum'    => array( 'db', 'theme', 'child_theme', 'plugin' ),
							'default' => 'db',
						),
						'theme_slug'  => array(
							'type'        => 'string',
							'default'     => '',
							'description' => __( 'Container theme folder for source=theme. Ignored otherwise.', 'zoltiq-agents' ),
						),
						'plugin_slug' => array(
							'type'        => 'string',
							'default'     => '',
							'description' => __( 'Container plugin folder for source=plugin.', 'zoltiq-agents' ),
						),
						'content'     => array(
							'type'        => array( 'string', 'object' ),
							'description' => __( 'Full theme.json content as a JSON string or object. Cannot be empty.', 'zoltiq-agents' ),
						),
						'section'     => array(
							'type' => 'string',
							'enum' => array( '', 'colors', 'typography', 'spacing', 'layout', 'blockStyles', 'customCss' ),
						),
						'data'        => array(
							'type'        => array( 'string', 'object' ),
							'description' => __( 'Section data (string or object). Required when "section" is provided; ignored otherwise.', 'zoltiq-agents' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'   => array( 'type' => 'boolean' ),
						'record'    => array( 'type' => 'object' ),
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
		$source      = sanitize_text_field( $input['source'] ?? 'db' );
		$theme       = sanitize_key( $input['theme'] ?? '' );
		$theme_slug  = sanitize_key( $input['theme_slug'] ?? '' );
		$plugin_slug = sanitize_key( $input['plugin_slug'] ?? '' );

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

		$existing = Global_Styles_Detector::locate( $theme );
		if ( ! empty( $existing ) ) {
			return array(
				'success'   => false,
				'message'   => sprintf( __( 'Global Styles already exist for theme "%s". Use global-styles-update.', 'zoltiq-agents' ), '' !== $theme ? $theme : (string) get_stylesheet() ),
				'locations' => $existing,
			);
		}

		switch ( $source ) {
			case 'db':
				return $this->create_db( $theme, $payload );

			case 'child_theme':
				return $this->create_theme_file( $payload, true, '' );

			case 'theme':
				return $this->create_theme_file( $payload, false, $theme_slug );

			case 'plugin':
				return $this->create_plugin_file( $payload, $plugin_slug );
		}

		return array(
			'success' => false,
			'message' => __( 'Unknown source.', 'zoltiq-agents' ),
		);
	}

	private function resolve_payload( array $input ) {
		$section = (string) ( $input['section'] ?? '' );
		if ( '' !== $section ) {
			$norm = Global_Styles_Db::normalize_section( $section );
			if ( ! Global_Styles_Db::valid_section( $norm ) ) {
				return new \WP_Error(
					'invalid_section',
					sprintf( __( 'Invalid section. Allowed: %s.', 'zoltiq-agents' ), implode( ', ', Global_Styles_Db::valid_sections() ) )
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
			foreach ( Global_Styles_Db::SECTION_PATHS[ $norm ] as $path ) {
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

	private function create_db( string $theme, array $payload ): array {
		$theme = '' !== $theme ? $theme : (string) get_stylesheet();

		$id = Global_Styles_Db::create( $theme, $payload );
		if ( is_wp_error( $id ) ) {
			return $this->error_response( $id );
		}

		$post     = get_post( (int) $id );
		$warnings = array();
		if ( is_multisite() ) {
			$warnings[] = __( 'On multisite, this DB record is scoped to the current site only.', 'zoltiq-agents' );
		}
		if ( $theme !== (string) get_stylesheet() ) {
			$warnings[] = __( 'You are creating styles for a non-active theme. Site visitors will only see them after that theme is activated.', 'zoltiq-agents' );
		}

		return array(
			'success'  => true,
			/* translators: %s: theme */
			'message'  => sprintf( __( 'Created Global Styles record for theme "%s".', 'zoltiq-agents' ), $theme ),
			'record'   => $post ? Global_Styles_Db::to_row( $post, true ) : array(),
			'warnings' => $warnings,
		);
	}

	private function create_theme_file( array $payload, bool $force_child, string $theme_slug ): array {
		$warnings = array();
		if ( $force_child ) {
			$dir = Global_Styles_File::get_child_theme_dir();
			if ( null === $dir ) {
				return array(
					'success' => false,
					'message' => __( 'No child theme is active. Create a child theme first, or use source=db.', 'zoltiq-agents' ),
				);
			}
		} else {
			$dir = '' !== $theme_slug ? Global_Styles_File::resolve_theme_dir( $theme_slug ) : Global_Styles_File::get_parent_theme_dir();
			if ( is_wp_error( $dir ) ) {
				return $this->error_response( $dir );
			}
			$child = Global_Styles_File::get_child_theme_dir();
			if ( null !== $child && $child !== $dir ) {
				$warnings[] = __( 'Writing to the parent theme — your changes will be lost when the theme updates. Prefer source=child_theme.', 'zoltiq-agents' );
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

		$path = Global_Styles_File::theme_json_path( $dir );
		if ( file_exists( $path ) ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'theme.json already exists at %s. Use global-styles-update.', 'zoltiq-agents' ), $path ),
			);
		}

		$bytes = Global_Styles_File::write_json( $path, $payload );
		if ( is_wp_error( $bytes ) ) {
			return $this->error_response( $bytes );
		}

		$warnings[] = __( 'Site Editor saves will create a DB record that overrides this file copy on the next save.', 'zoltiq-agents' );

		return array(
			'success'  => true,
			'message'  => sprintf( __( 'Wrote theme.json to %s.', 'zoltiq-agents' ), $path ),
			'record'   => array(
				'source'   => 'theme',
				'theme'    => basename( $dir ),
				'path'     => $path,
				'bytes'    => (int) $bytes,
				'writable' => is_writable( $path ),
			),
			'warnings' => $warnings,
		);
	}

	private function create_plugin_file( array $payload, string $plugin_slug ): array {
		if ( '' === $plugin_slug ) {
			return array(
				'success' => false,
				'message' => __( 'plugin_slug is required when source=plugin.', 'zoltiq-agents' ),
			);
		}

		$plugin = Global_Styles_File::resolve_plugin_dir( $plugin_slug );
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

		$path = Global_Styles_File::theme_json_path( $plugin['path'] );
		if ( file_exists( $path ) ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'theme.json already exists at %s. Use global-styles-update.', 'zoltiq-agents' ), $path ),
			);
		}

		$bytes = Global_Styles_File::write_json( $path, $payload );
		if ( is_wp_error( $bytes ) ) {
			return $this->error_response( $bytes );
		}

		$warnings = array();
		if ( ! $plugin['active'] ) {
			$warnings[] = sprintf( __( 'Plugin "%s" is inactive — its theme.json will not register until the plugin is activated.', 'zoltiq-agents' ), $plugin_slug );
		}

		return array(
			'success'  => true,
			'message'  => sprintf( __( 'Wrote plugin theme.json to %s.', 'zoltiq-agents' ), $path ),
			'record'   => array(
				'source'        => 'plugin',
				'plugin'        => $plugin_slug,
				'plugin_active' => (bool) $plugin['active'],
				'path'          => $path,
				'bytes'         => (int) $bytes,
			),
			'warnings' => $warnings,
		);
	}

	private function error_response( \WP_Error $err ): array {
		return array(
			'success' => false,
			'message' => $err->get_error_message(),
		);
	}
}
