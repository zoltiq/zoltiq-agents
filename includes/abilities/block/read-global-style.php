<?php

namespace Zoltiq\Agents\Includes\Abilities\Block;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Global_Styles\Global_Styles_Db;
use Zoltiq\Agents\Includes\Abilities\Utilities\Global_Styles\Global_Styles_Detector;
use Zoltiq\Agents\Includes\Abilities\Utilities\Global_Styles\Global_Styles_File;

defined( 'ABSPATH' ) || exit;

class Read_Global_Style extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/read-global-style',
			'args' => array(
				'label'               => __( 'Read Global Style', 'zoltiq-agents' ),
				'description'         => __( 'Reads a Global Styles record (or one section of it) for a theme. Defaults to the database; falls back to theme.json defaults when no DB record exists. Pass "section" to return only colors / typography / spacing / layout / blockStyles / customCss.', 'zoltiq-agents' ),
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
							'enum'    => array( '', 'db', 'theme', 'child_theme', 'plugin' ),
							'default' => '',
						),
						'theme_type'  => array(
							'type'    => 'string',
							'enum'    => array( '', 'child', 'parent', 'theme' ),
							'default' => '',
						),
						'plugin_slug' => array(
							'type'    => 'string',
							'default' => '',
						),
						'section'     => array(
							'type'        => 'string',
							'enum'        => array( '', 'colors', 'typography', 'spacing', 'layout', 'blockStyles', 'customCss' ),
							'default'     => '',
							'description' => __( 'Return only this section instead of the full record.', 'zoltiq-agents' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'    => array( 'type' => 'boolean' ),
						'record'     => array( 'type' => 'object' ),
						'section'    => array( 'type' => 'string' ),
						'data'       => array( 'type' => 'object' ),
						'origin'     => array( 'type' => 'string' ),
						'locations'  => array( 'type' => 'array' ),
						'candidates' => array( 'type' => 'array' ),
						'warnings'   => array( 'type' => 'array' ),
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
		$theme       = sanitize_key( $input['theme'] ?? '' );
		$source      = sanitize_text_field( $input['source'] ?? '' );
		$theme_type  = sanitize_text_field( $input['theme_type'] ?? '' );
		$plugin_slug = sanitize_key( $input['plugin_slug'] ?? '' );
		$section_raw = (string) ( $input['section'] ?? '' );

		$section = '';
		if ( '' !== $section_raw ) {
			$normalized = Global_Styles_Db::normalize_section( $section_raw );
			if ( ! Global_Styles_Db::valid_section( $normalized ) ) {
				return array(
					'success' => false,
					'message' => sprintf( __( 'Invalid section. Allowed: %s.', 'zoltiq-agents' ), implode( ', ', Global_Styles_Db::valid_sections() ) ),
				);
			}
			$section = $normalized;
		}

		$locations = Global_Styles_Detector::locate( $theme );

		if ( empty( $locations ) ) {
			$fallback = $this->read_theme_json_defaults();
			if ( '' !== $section ) {
				$fallback['data'] = $this->extract_section( $fallback['data'], $section );
			}
			return array(
				'success'   => true,
				'origin'    => $fallback['origin'],
				'section'   => $section,
				'data'      => $fallback['data'],
				'locations' => array(),
				'warnings'  => array( __( 'No Global Styles record exists for this theme. Returning theme.json defaults.', 'zoltiq-agents' ) ),
				'message'   => __( 'No DB record — returning theme.json defaults.', 'zoltiq-agents' ),
			);
		}

		$selected = Global_Styles_Detector::select( $locations, $source, $theme_type, $plugin_slug );
		if ( is_wp_error( $selected ) ) {
			$data = $selected->get_error_data();
			return array(
				'success'    => false,
				'message'    => $selected->get_error_message(),
				'locations'  => $locations,
				'candidates' => is_array( $data ) ? ( $data['locations'] ?? array() ) : array(),
			);
		}

		$materialised = $this->materialise( $selected );
		if ( is_wp_error( $materialised ) ) {
			return array(
				'success'   => false,
				'message'   => $materialised->get_error_message(),
				'locations' => $locations,
			);
		}

		if ( '' !== $section ) {
			$slice  = $this->extract_section( $materialised['data'], $section );
			$origin = $materialised['origin'];
			if ( empty( $slice ) ) {
				$fallback = $this->read_theme_json_defaults();
				$slice    = $this->extract_section( $fallback['data'], $section );
				$origin   = $fallback['origin'];
			}
			$materialised['data']   = $slice;
			$materialised['origin'] = $origin;
		}

		$warnings = $this->collect_warnings( $locations, $selected );

		return array(
			'success'   => true,
			'record'    => $this->summarise( $selected ),
			'section'   => $section,
			'data'      => $materialised['data'],
			'origin'    => $materialised['origin'],
			'locations' => $locations,
			'warnings'  => $warnings,
		);
	}

	private function summarise( array $loc ): array {
		return array(
			'source'        => (string) ( $loc['source'] ?? '' ),
			'theme'         => (string) ( $loc['theme'] ?? '' ),
			'theme_type'    => (string) ( $loc['theme_type'] ?? '' ),
			'plugin'        => (string) ( $loc['plugin'] ?? '' ),
			'plugin_active' => (bool) ( $loc['plugin_active'] ?? false ),
			'post_id'       => (int) ( $loc['post_id'] ?? 0 ),
			'path'          => (string) ( $loc['path'] ?? '' ),
		);
	}

	private function materialise( array $loc ) {
		$src = (string) ( $loc['source'] ?? '' );
		if ( 'db' === $src ) {
			$post = get_post( (int) ( $loc['post_id'] ?? 0 ) );
			if ( ! $post ) {
				return new \WP_Error( 'db_post_missing', __( 'wp_global_styles post not found.', 'zoltiq-agents' ) );
			}
			return array(
				'data'   => Global_Styles_Db::decode_content( $post ),
				'origin' => 'db',
			);
		}

		$data = Global_Styles_File::read_json( (string) ( $loc['path'] ?? '' ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		return array(
			'data'   => $data,
			'origin' => 'plugin' === $src ? 'plugin:theme.json' : 'theme:theme.json',
		);
	}

	private function extract_section( array $data, string $section ): array {
		$out = array();
		foreach ( ( Global_Styles_Db::SECTION_PATHS[ $section ] ?? array() ) as $path ) {
			$value = Global_Styles_Db::path_get( $data, $path );
			if ( null !== $value ) {
				Global_Styles_Db::path_set( $out, $path, $value );
			}
		}
		return $out;
	}

	private function read_theme_json_defaults(): array {
		$parent_dir  = Global_Styles_File::get_parent_theme_dir();
		$parent_json = Global_Styles_File::theme_json_path( $parent_dir );
		$parent      = is_file( $parent_json ) ? Global_Styles_File::read_json( $parent_json ) : array();
		if ( is_wp_error( $parent ) ) {
			$parent = array();
		}

		$child_dir = Global_Styles_File::get_child_theme_dir();
		if ( null === $child_dir ) {
			return array(
				'data'   => $parent,
				'origin' => 'theme.json:parent',
			);
		}

		$child_json = Global_Styles_File::theme_json_path( $child_dir );
		$child      = is_file( $child_json ) ? Global_Styles_File::read_json( $child_json ) : array();
		if ( is_wp_error( $child ) ) {
			$child = array();
		}

		return array(
			'data'   => Global_Styles_Db::deep_merge( is_array( $parent ) ? $parent : array(), is_array( $child ) ? $child : array() ),
			'origin' => 'theme.json:merged',
		);
	}

	private function collect_warnings( array $locations, array $selected ): array {
		$warnings  = array();
		$effective = Global_Styles_Detector::effective( $locations );

		if ( count( $locations ) > 1 ) {
			$warnings[] = __( 'Multiple Global Styles locations exist. WordPress always serves the highest-priority copy: DB → child theme → parent theme → plugin.', 'zoltiq-agents' );
		}

		if ( $effective && ( $selected['source'] ?? '' ) !== ( $effective['source'] ?? '' ) ) {
			$warnings[] = __( 'You are reading a copy that is not the one WordPress is currently serving.', 'zoltiq-agents' );
		}

		foreach ( $locations as $loc ) {
			if ( ( $loc['source'] ?? '' ) === 'plugin' && false === ( $loc['plugin_active'] ?? true ) ) {
				/* translators: %s: plugin slug */
				$warnings[] = sprintf( __( 'Plugin "%s" is inactive — its theme.json will not register until the plugin is activated.', 'zoltiq-agents' ), $loc['plugin'] ?? '' );
			}
		}

		return $warnings;
	}
}
