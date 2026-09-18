<?php

namespace Zoltiq\Agents\Includes\Abilities\Block;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\File_Mods_Guard;
use Zoltiq\Agents\Includes\Abilities\Utilities\Global_Styles\Global_Styles_Db;
use Zoltiq\Agents\Includes\Abilities\Utilities\Global_Styles\Global_Styles_Detector;
use Zoltiq\Agents\Includes\Abilities\Utilities\Global_Styles\Global_Styles_File;

defined( 'ABSPATH' ) || exit;

class Update_Global_Style extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/update-global-style',
			'args' => array(
				'label'               => __( 'Update Global Style', 'zoltiq-agents' ),
				'description'         => __( 'Updates Global Styles. By default deep-merges new data into the existing record; pass merge=false to replace. Use "section" + "data" to update one section only. Supports cross-source migration via migrate_to.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-block',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'theme'         => array(
							'type'    => 'string',
							'default' => '',
						),
						'source'        => array(
							'type'    => 'string',
							'enum'    => array( '', 'db', 'theme', 'child_theme', 'plugin' ),
							'default' => '',
						),
						'theme_type'    => array(
							'type'    => 'string',
							'enum'    => array( '', 'child', 'parent', 'theme' ),
							'default' => '',
						),
						'plugin_slug'   => array(
							'type'    => 'string',
							'default' => '',
						),
						'content'       => array(
							'type'        => array( 'string', 'object' ),
							'description' => __( 'Full or partial theme.json content. Object or JSON string.', 'zoltiq-agents' ),
						),
						'section'       => array(
							'type' => 'string',
							'enum' => array( '', 'colors', 'typography', 'spacing', 'layout', 'blockStyles', 'customCss' ),
						),
						'data'          => array(
							'type'        => array( 'string', 'object' ),
							'description' => __( 'Section data; required when "section" is provided.', 'zoltiq-agents' ),
						),
						'merge'         => array(
							'type'        => 'boolean',
							'default'     => true,
							'description' => __( 'true deep-merges; false replaces.', 'zoltiq-agents' ),
						),
						'migrate_to'    => array(
							'type'    => 'string',
							'enum'    => array( '', 'db', 'child_theme' ),
							'default' => '',
						),
						'delete_source' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'    => array( 'type' => 'boolean' ),
						'record'     => array( 'type' => 'object' ),
						'migrated'   => array( 'type' => 'boolean' ),
						'warnings'   => array( 'type' => 'array' ),
						'locations'  => array( 'type' => 'array' ),
						'candidates' => array( 'type' => 'array' ),
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
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		$theme         = sanitize_key( $input['theme'] ?? '' );
		$source        = sanitize_text_field( $input['source'] ?? '' );
		$theme_type    = sanitize_text_field( $input['theme_type'] ?? '' );
		$plugin_slug   = sanitize_key( $input['plugin_slug'] ?? '' );
		$migrate_to    = sanitize_text_field( $input['migrate_to'] ?? '' );
		$delete_source = ! empty( $input['delete_source'] );
		$merge         = ! isset( $input['merge'] ) || (bool) $input['merge'];

		$locations = Global_Styles_Detector::locate( $theme );
		if ( empty( $locations ) ) {
			return array(
				'success'   => false,
				'message'   => sprintf( __( 'No Global Styles record exists for theme "%s". Use global-styles-create.', 'zoltiq-agents' ), '' !== $theme ? $theme : (string) get_stylesheet() ),
				'locations' => array(),
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

		$selected_src = (string) ( $selected['source'] ?? '' );
		if ( ( 'theme' === $selected_src || 'plugin' === $selected_src ) || '' !== $migrate_to ) {
			$blocked = File_Mods_Guard::blocked_response();
			if ( null !== $blocked ) {
				return $blocked;
			}
		}

		$payload = $this->resolve_payload( $input );
		if ( is_wp_error( $payload ) ) {
			return $this->error_response( $payload );
		}

		if ( '' !== $migrate_to ) {
			return $this->migrate( $selected, $migrate_to, $delete_source, $payload );
		}

		if ( 'theme' === $selected_src && 'parent' === ( $selected['theme_type'] ?? '' ) ) {
			return array(
				'success'   => false,
				'message'   => __( 'Refusing to edit the parent theme directly. Re-run with migrate_to=child_theme or migrate_to=db.', 'zoltiq-agents' ),
				'locations' => $locations,
			);
		}

		switch ( $selected_src ) {
			case 'db':
				return $this->update_db( $selected, $payload, $merge );
			case 'theme':
			case 'plugin':
				return $this->update_file( $selected, $payload, $merge );
		}

		return array(
			'success' => false,
			'message' => __( 'Unknown source.', 'zoltiq-agents' ),
		);
	}

	private function update_db( array $loc, array $payload, bool $merge ): array {
		$post = get_post( (int) ( $loc['post_id'] ?? 0 ) );
		if ( ! $post ) {
			return array(
				'success' => false,
				'message' => __( 'wp_global_styles post not found.', 'zoltiq-agents' ),
			);
		}

		$result = Global_Styles_Db::update( $post, $payload, $merge );
		if ( is_wp_error( $result ) ) {
			return $this->error_response( $result );
		}

		$updated = get_post( (int) $result );
		return array(
			'success'  => true,
			'message'  => __( 'Updated DB Global Styles record.', 'zoltiq-agents' ),
			'record'   => $updated ? Global_Styles_Db::to_row( $updated, true ) : array(),
			'warnings' => array(),
		);
	}

	private function update_file( array $loc, array $payload, bool $merge ): array {
		$path     = (string) ( $loc['path'] ?? '' );
		$existing = Global_Styles_File::read_json( $path );
		if ( is_wp_error( $existing ) ) {
			return $this->error_response( $existing );
		}

		$new = $merge ? Global_Styles_Db::deep_merge( is_array( $existing ) ? $existing : array(), $payload ) : $payload;

		$valid = Global_Styles_Db::validate_data( $new );
		if ( is_wp_error( $valid ) ) {
			return $this->error_response( $valid );
		}
		$valid = Global_Styles_Db::validate_block_styles( $new );
		if ( is_wp_error( $valid ) ) {
			return $this->error_response( $valid );
		}

		$bytes = Global_Styles_File::write_json( $path, $new );
		if ( is_wp_error( $bytes ) ) {
			return $this->error_response( $bytes );
		}

		$warnings = array();
		if ( 'plugin' === ( $loc['source'] ?? '' ) && false === ( $loc['plugin_active'] ?? true ) ) {
			$warnings[] = sprintf( __( 'Plugin "%s" is inactive — your edit will only take effect once the plugin is activated.', 'zoltiq-agents' ), $loc['plugin'] ?? '' );
		}

		return array(
			'success'  => true,
			'message'  => sprintf( __( 'Updated theme.json at %s.', 'zoltiq-agents' ), $path ),
			'record'   => array(
				'source'        => (string) ( $loc['source'] ?? '' ),
				'theme'         => (string) ( $loc['theme'] ?? '' ),
				'theme_type'    => (string) ( $loc['theme_type'] ?? '' ),
				'plugin'        => (string) ( $loc['plugin'] ?? '' ),
				'plugin_active' => (bool) ( $loc['plugin_active'] ?? false ),
				'path'          => $path,
				'bytes'         => (int) $bytes,
			),
			'warnings' => $warnings,
		);
	}

	private function migrate( array $loc, string $migrate_to, bool $delete_source, array $payload ): array {
		$src      = (string) ( $loc['source'] ?? '' );
		$theme    = (string) ( $loc['theme'] ?? get_stylesheet() );
		$warnings = array();

		if ( 'db' === $src ) {
			$post = get_post( (int) ( $loc['post_id'] ?? 0 ) );
			if ( ! $post ) {
				return array(
					'success' => false,
					'message' => __( 'Source DB record not found.', 'zoltiq-agents' ),
				);
			}
			$existing = Global_Styles_Db::decode_content( $post );
		} else {
			$read = Global_Styles_File::read_json( (string) ( $loc['path'] ?? '' ) );
			if ( is_wp_error( $read ) ) {
				return $this->error_response( $read );
			}
			$existing = $read;
		}

		$merged = empty( $payload )
			? ( is_array( $existing ) ? $existing : array() )
			: Global_Styles_Db::deep_merge( is_array( $existing ) ? $existing : array(), $payload );

		$valid = Global_Styles_Db::validate_data( $merged );
		if ( is_wp_error( $valid ) ) {
			return $this->error_response( $valid );
		}
		$valid = Global_Styles_Db::validate_block_styles( $merged );
		if ( is_wp_error( $valid ) ) {
			return $this->error_response( $valid );
		}

		if ( 'db' === $migrate_to ) {
			if ( Global_Styles_Db::find_by_theme( $theme ) ) {
				return array(
					'success' => false,
					'message' => __( 'A DB Global Styles record already exists for this theme. Update it directly instead of migrating.', 'zoltiq-agents' ),
				);
			}
			$id = Global_Styles_Db::create( $theme, $merged );
			if ( is_wp_error( $id ) ) {
				return $this->error_response( $id );
			}
			$new_post   = get_post( (int) $id );
			$warnings[] = __( 'DB version will override the file copy from now on.', 'zoltiq-agents' );

			if ( $delete_source && 'theme' === $src && 'parent' !== ( $loc['theme_type'] ?? '' ) ) {
				$del = Global_Styles_File::delete_file( (string) $loc['path'] );
				if ( is_wp_error( $del ) ) {
					$warnings[] = $del->get_error_message();
				}
			} elseif ( $delete_source ) {
				$warnings[] = __( 'Skipped deleting source — parent-theme and plugin files are preserved.', 'zoltiq-agents' );
			}

			return array(
				'success'  => true,
				'message'  => sprintf( __( 'Migrated Global Styles for "%s" from file to database.', 'zoltiq-agents' ), $theme ),
				'record'   => $new_post ? Global_Styles_Db::to_row( $new_post, true ) : array(),
				'migrated' => true,
				'warnings' => $warnings,
			);
		}

		$child_dir = Global_Styles_File::get_child_theme_dir();
		if ( null === $child_dir ) {
			return array(
				'success' => false,
				'message' => __( 'No child theme is active. Create one before migrating to child theme.', 'zoltiq-agents' ),
			);
		}

		$path  = Global_Styles_File::theme_json_path( $child_dir );
		$bytes = Global_Styles_File::write_json( $path, $merged );
		if ( is_wp_error( $bytes ) ) {
			return $this->error_response( $bytes );
		}

		if ( $delete_source && 'db' === $src ) {
			$post = get_post( (int) ( $loc['post_id'] ?? 0 ) );
			if ( $post ) {
				Global_Styles_Db::delete( $post );
				$warnings[] = __( 'DB record deleted — child-theme theme.json is now the only copy.', 'zoltiq-agents' );
			}
		} elseif ( $delete_source && 'theme' === $src && 'parent' === ( $loc['theme_type'] ?? '' ) ) {
			$warnings[] = __( 'Skipped deleting parent-theme source — parent files are preserved.', 'zoltiq-agents' );
		} elseif ( $delete_source && 'plugin' === $src ) {
			$warnings[] = __( 'Skipped deleting plugin source — plugin files are preserved.', 'zoltiq-agents' );
		}

		return array(
			'success'  => true,
			'message'  => sprintf( __( 'Migrated Global Styles to child theme at %s.', 'zoltiq-agents' ), $path ),
			'record'   => array(
				'source'     => 'theme',
				'theme_type' => 'child',
				'theme'      => basename( $child_dir ),
				'path'       => $path,
				'bytes'      => (int) $bytes,
			),
			'migrated' => true,
			'warnings' => $warnings,
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

			if ( 'customCss' === $norm && isset( $input['data'] ) && is_string( $input['data'] ) ) {
				return array( 'styles' => array( 'css' => (string) $input['data'] ) );
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

		if ( ! isset( $input['content'] ) ) {
			return array();
		}

		return $this->coerce_array( $input['content'] );
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

	private function error_response( \WP_Error $err ): array {
		return array(
			'success' => false,
			'message' => $err->get_error_message(),
		);
	}
}
