<?php

namespace Zoltiq\Agents\Includes\Abilities\Block;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Block_Style_Variations\Variation_Db;
use Zoltiq\Agents\Includes\Abilities\Utilities\Block_Style_Variations\Variation_Detector;
use Zoltiq\Agents\Includes\Abilities\Utilities\Block_Style_Variations\Variation_File;
use Zoltiq\Agents\Includes\Abilities\Utilities\File_Mods_Guard;
use Zoltiq\Agents\Includes\Abilities\Utilities\Global_Styles\Global_Styles_Db;

defined( 'ABSPATH' ) || exit;

class Update_Block_Style_Variation extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/update-block-style-variation',
			'args' => array(
				'label'               => __( 'Update Block Style Variation', 'zoltiq-agents' ),
				'description'         => __( 'Updates a Block Style Variation. Auto-detects location; supports section-scoped updates, rename via new_slug, and cross-source migration via migrate_to.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-block',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'slug'          => array( 'type' => 'string' ),
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
							'description' => __( 'Full or partial variation content.', 'zoltiq-agents' ),
						),
						'section'       => array(
							'type' => 'string',
							'enum' => array( '', 'colors', 'typography', 'spacing', 'layout', 'blockStyles' ),
						),
						'data'          => array(
							'type'        => array( 'string', 'object' ),
							'description' => __( 'Section data; required when "section" is provided.', 'zoltiq-agents' ),
						),
						'merge'         => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'title'         => array( 'type' => 'string' ),
						'description'   => array( 'type' => 'string' ),
						'new_slug'      => array(
							'type'        => 'string',
							'description' => __( 'Rename to this slug. Renames the .json file or DB post_name.', 'zoltiq-agents' ),
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
					'required'             => array( 'slug' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'    => array( 'type' => 'boolean' ),
						'variation'  => array( 'type' => 'object' ),
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
		$slug          = sanitize_title( (string) ( $input['slug'] ?? '' ) );
		$theme         = sanitize_key( $input['theme'] ?? '' );
		$source        = sanitize_text_field( $input['source'] ?? '' );
		$theme_type    = sanitize_text_field( $input['theme_type'] ?? '' );
		$plugin_slug   = sanitize_key( $input['plugin_slug'] ?? '' );
		$migrate_to    = sanitize_text_field( $input['migrate_to'] ?? '' );
		$delete_source = ! empty( $input['delete_source'] );
		$merge         = ! isset( $input['merge'] ) || (bool) $input['merge'];

		if ( '' === $slug ) {
			return array(
				'success' => false,
				'message' => __( 'Slug is required.', 'zoltiq-agents' ),
			);
		}

		$locations = Variation_Detector::locate( $slug, $theme );
		if ( empty( $locations ) ) {
			return array(
				'success'   => false,
				'message'   => sprintf( __( 'No Block Style Variation with slug "%s" was found. Use block-style-variations-create.', 'zoltiq-agents' ), $slug ),
				'locations' => array(),
			);
		}

		$selected = Variation_Detector::select( $locations, $source, $theme_type, $plugin_slug );
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
			return $this->migrate( $selected, $migrate_to, $delete_source, $payload, $input );
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
				return $this->update_db( $selected, $payload, $merge, $input );
			case 'theme':
			case 'plugin':
				return $this->update_file( $selected, $payload, $merge, $input );
		}

		return array(
			'success' => false,
			'message' => __( 'Unknown source.', 'zoltiq-agents' ),
		);
	}

	private function update_db( array $loc, array $payload, bool $merge, array $input ): array {
		$post = get_post( (int) ( $loc['post_id'] ?? 0 ) );
		if ( ! $post ) {
			return array(
				'success' => false,
				'message' => __( 'wp_global_styles variation post not found.', 'zoltiq-agents' ),
			);
		}

		$extras = array();
		foreach ( array( 'title', 'description', 'new_slug' ) as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$extras[ $key ] = (string) $input[ $key ];
			}
		}

		$result = Variation_Db::update( $post, $payload, $merge, $extras );
		if ( is_wp_error( $result ) ) {
			return $this->error_response( $result );
		}

		$updated = get_post( (int) $result );
		return array(
			'success'   => true,
			'message'   => __( 'Updated DB variation.', 'zoltiq-agents' ),
			'variation' => $updated ? Variation_Db::to_row( $updated, true ) : array(),
			'warnings'  => array(),
		);
	}

	private function update_file( array $loc, array $payload, bool $merge, array $input ): array {
		$path     = (string) ( $loc['path'] ?? '' );
		$existing = Variation_File::read_json( $path );
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

		$bytes = Variation_File::write_json( $path, $next );
		if ( is_wp_error( $bytes ) ) {
			return $this->error_response( $bytes );
		}

		$final_path = $path;
		$final_slug = (string) ( $loc['slug'] ?? '' );
		if ( ! empty( $input['new_slug'] ) ) {
			$new_slug = sanitize_title( (string) $input['new_slug'] );
			if ( '' === $new_slug || ! Variation_File::is_valid_bare_slug( $new_slug ) ) {
				return array(
					'success' => false,
					'message' => __( 'new_slug is invalid.', 'zoltiq-agents' ),
				);
			}
			$container = dirname( dirname( $path ) );
			$new_abs   = Variation_File::resolve_variation_path( $container, $new_slug );
			if ( is_wp_error( $new_abs ) ) {
				return $this->error_response( $new_abs );
			}
			if ( file_exists( $new_abs ) && $new_abs !== $path ) {
				return array(
					'success' => false,
					'message' => sprintf( __( 'Target slug already has a file at %s.', 'zoltiq-agents' ), $new_abs ),
				);
			}
			if ( ! @rename( $path, $new_abs ) ) { 
				return array(
					'success' => false,
					'message' => __( 'Failed to rename variation file.', 'zoltiq-agents' ),
				);
			}
			$final_path = $new_abs;
			$final_slug = $new_slug;
		}

		$warnings = array();
		if ( 'plugin' === ( $loc['source'] ?? '' ) && false === ( $loc['plugin_active'] ?? true ) ) {
			$warnings[] = sprintf( __( 'Plugin "%s" is inactive — your edit will only take effect once the plugin is activated.', 'zoltiq-agents' ), $loc['plugin'] ?? '' );
		}

		return array(
			'success'   => true,
			'message'   => sprintf( __( 'Updated variation "%s".', 'zoltiq-agents' ), $final_slug ),
			'variation' => array(
				'source'        => (string) ( $loc['source'] ?? '' ),
				'theme'         => (string) ( $loc['theme'] ?? '' ),
				'theme_type'    => (string) ( $loc['theme_type'] ?? '' ),
				'plugin'        => (string) ( $loc['plugin'] ?? '' ),
				'plugin_active' => (bool) ( $loc['plugin_active'] ?? false ),
				'slug'          => $final_slug,
				'path'          => $final_path,
				'bytes'         => (int) $bytes,
			),
			'warnings'  => $warnings,
		);
	}

	private function migrate( array $loc, string $migrate_to, bool $delete_source, array $payload, array $input ): array {
		$slug     = (string) ( $loc['slug'] ?? '' );
		$src      = (string) ( $loc['source'] ?? '' );
		$theme    = sanitize_key( $input['theme'] ?? '' );
		$warnings = array();

		if ( 'db' === $src ) {
			$post = get_post( (int) ( $loc['post_id'] ?? 0 ) );
			if ( ! $post ) {
				return array(
					'success' => false,
					'message' => __( 'Source DB variation not found.', 'zoltiq-agents' ),
				);
			}
			$existing = Variation_Db::decode_content( $post );
		} else {
			$read = Variation_File::read_json( (string) ( $loc['path'] ?? '' ) );
			if ( is_wp_error( $read ) ) {
				return $this->error_response( $read );
			}
			$existing = $read;
		}

		$merged = empty( $payload ) ? ( is_array( $existing ) ? $existing : array() ) : Global_Styles_Db::deep_merge( is_array( $existing ) ? $existing : array(), $payload );

		$valid = Global_Styles_Db::validate_data( $merged );
		if ( is_wp_error( $valid ) ) {
			return $this->error_response( $valid );
		}
		$valid = Global_Styles_Db::validate_block_styles( $merged );
		if ( is_wp_error( $valid ) ) {
			return $this->error_response( $valid );
		}

		if ( 'db' === $migrate_to ) {
			$target_theme = '' !== $theme ? $theme : (string) get_stylesheet();
			if ( Variation_Db::find_by_slug( $slug, $target_theme ) ) {
				return array(
					'success' => false,
					'message' => __( 'A DB variation with this slug already exists for this theme. Update it directly instead of migrating.', 'zoltiq-agents' ),
				);
			}
			$id = Variation_Db::create(
				$target_theme,
				$slug,
				$merged,
				array(
					'title'       => (string) ( $input['title'] ?? '' ),
					'description' => (string) ( $input['description'] ?? '' ),
				)
			);
			if ( is_wp_error( $id ) ) {
				return $this->error_response( $id );
			}
			$warnings[] = __( 'DB version will override the file copy from now on.', 'zoltiq-agents' );

			if ( $delete_source && 'theme' === $src && 'parent' !== ( $loc['theme_type'] ?? '' ) ) {
				$del = Variation_File::delete_file( (string) $loc['path'] );
				if ( is_wp_error( $del ) ) {
					$warnings[] = $del->get_error_message();
				}
			} elseif ( $delete_source ) {
				$warnings[] = __( 'Skipped deleting source — parent-theme and plugin files are preserved.', 'zoltiq-agents' );
			}

			$new_post = get_post( (int) $id );
			return array(
				'success'   => true,
				'message'   => sprintf( __( 'Migrated variation "%s" from file to database.', 'zoltiq-agents' ), $slug ),
				'variation' => $new_post ? Variation_Db::to_row( $new_post, true ) : array(),
				'migrated'  => true,
				'warnings'  => $warnings,
			);
		}

		$child_dir = Variation_File::get_child_theme_dir();
		if ( null === $child_dir ) {
			return array(
				'success' => false,
				'message' => __( 'No child theme is active. Create one before migrating to child theme.', 'zoltiq-agents' ),
			);
		}
		$styles_dir = Variation_File::ensure_styles_dir( $child_dir );
		if ( is_wp_error( $styles_dir ) ) {
			return $this->error_response( $styles_dir );
		}
		$abs = Variation_File::resolve_variation_path( $child_dir, $slug );
		if ( is_wp_error( $abs ) ) {
			return $this->error_response( $abs );
		}
		$bytes = Variation_File::write_json( $abs, $merged );
		if ( is_wp_error( $bytes ) ) {
			return $this->error_response( $bytes );
		}

		if ( $delete_source && 'db' === $src ) {
			$post = get_post( (int) ( $loc['post_id'] ?? 0 ) );
			if ( $post ) {
				Variation_Db::delete( $post );
				$warnings[] = __( 'DB record deleted — child-theme file is now the only copy.', 'zoltiq-agents' );
			}
		} elseif ( $delete_source && 'theme' === $src && 'parent' === ( $loc['theme_type'] ?? '' ) ) {
			$warnings[] = __( 'Skipped deleting parent-theme source — parent files are preserved.', 'zoltiq-agents' );
		} elseif ( $delete_source && 'plugin' === $src ) {
			$warnings[] = __( 'Skipped deleting plugin source — plugin files are preserved.', 'zoltiq-agents' );
		}

		return array(
			'success'   => true,
			'message'   => sprintf( __( 'Migrated variation "%1$s" to child theme at %2$s.', 'zoltiq-agents' ), $slug, $abs ),
			'variation' => array(
				'source'     => 'theme',
				'theme_type' => 'child',
				'theme'      => basename( $child_dir ),
				'slug'       => $slug,
				'path'       => $abs,
				'bytes'      => (int) $bytes,
			),
			'migrated'  => true,
			'warnings'  => $warnings,
		);
	}

	/**
	 * @return array|\WP_Error
	 */
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
