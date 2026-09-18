<?php

namespace Zoltiq\Agents\Includes\Abilities\Block;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Block_Style_Variations\Variation_Db;
use Zoltiq\Agents\Includes\Abilities\Utilities\Block_Style_Variations\Variation_Detector;
use Zoltiq\Agents\Includes\Abilities\Utilities\Block_Style_Variations\Variation_File;
use Zoltiq\Agents\Includes\Abilities\Utilities\File_Mods_Guard;
use Zoltiq\Agents\Includes\Abilities\Utilities\Global_Styles\Global_Styles_Db;

defined( 'ABSPATH' ) || exit;

class Delete_Block_Style_Variation extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/delete-block-style-variation',
			'args' => array(
				'label'               => __( 'Delete Block Style Variation', 'zoltiq-agents' ),
				'description'         => __( 'Deletes a Block Style Variation. Without "section", deletes the whole record/file (requires confirm=true). With "section", removes just that slice. Refuses to delete parent-theme files; active variations require confirm_active.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-block',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'slug'           => array( 'type' => 'string' ),
						'theme'          => array(
							'type'    => 'string',
							'default' => '',
						),
						'source'         => array(
							'type'    => 'string',
							'enum'    => array( '', 'db', 'theme', 'child_theme', 'plugin' ),
							'default' => '',
						),
						'theme_type'     => array(
							'type'    => 'string',
							'enum'    => array( '', 'child', 'parent', 'theme' ),
							'default' => '',
						),
						'plugin_slug'    => array(
							'type'    => 'string',
							'default' => '',
						),
						'section'        => array(
							'type'    => 'string',
							'enum'    => array( '', 'colors', 'typography', 'spacing', 'layout', 'blockStyles' ),
							'default' => '',
						),
						'confirm'        => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => __( 'Required when deleting the entire variation.', 'zoltiq-agents' ),
						),
						'confirm_active' => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => __( 'Required when the variation is currently active on the site.', 'zoltiq-agents' ),
						),
					),
					'required'             => array( 'slug' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'    => array( 'type' => 'boolean' ),
						'deleted'    => array( 'type' => 'object' ),
						'mode'       => array( 'type' => 'string' ),
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
						'destructive' => true,
						'idempotent'  => true,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		$slug           = sanitize_title( (string) ( $input['slug'] ?? '' ) );
		$theme          = sanitize_key( $input['theme'] ?? '' );
		$source         = sanitize_text_field( $input['source'] ?? '' );
		$theme_type     = sanitize_text_field( $input['theme_type'] ?? '' );
		$plugin_slug    = sanitize_key( $input['plugin_slug'] ?? '' );
		$section_raw    = (string) ( $input['section'] ?? '' );
		$confirm        = ! empty( $input['confirm'] );
		$confirm_active = ! empty( $input['confirm_active'] );

		if ( '' === $slug ) {
			return array(
				'success' => false,
				'message' => __( 'Slug is required.', 'zoltiq-agents' ),
			);
		}

		$section = '';
		if ( '' !== $section_raw ) {
			$normalized = Variation_Db::normalize_section( $section_raw );
			if ( ! Variation_Db::valid_section( $normalized ) ) {
				return array(
					'success' => false,
					'message' => sprintf( __( 'Invalid section. Allowed: %s.', 'zoltiq-agents' ), implode( ', ', Variation_Db::valid_sections() ) ),
				);
			}
			$section = $normalized;
		}

		$locations = Variation_Detector::locate( $slug, $theme );
		if ( empty( $locations ) ) {
			return array(
				'success'   => false,
				'message'   => sprintf( __( 'No Block Style Variation with slug "%s" was found.', 'zoltiq-agents' ), $slug ),
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

		if ( 'theme' === $selected_src || 'plugin' === $selected_src ) {
			$blocked = File_Mods_Guard::blocked_response();
			if ( null !== $blocked ) {
				return $blocked;
			}
		}

		if ( 'theme' === $selected_src && 'parent' === ( $selected['theme_type'] ?? '' ) ) {
			return array(
				'success'   => false,
				'message'   => __( 'Refusing to delete a parent-theme variation file.', 'zoltiq-agents' ),
				'locations' => $locations,
			);
		}

		if ( '' === $section && 'db' === $selected_src && ! empty( $selected['is_active_variation'] ) && ! $confirm_active ) {
			return array(
				'success'   => false,
				'message'   => __( 'This variation is currently active on the site. Re-run with confirm_active=true to delete it; the site will revert to default styles.', 'zoltiq-agents' ),
				'locations' => $locations,
			);
		}

		if ( '' !== $section ) {
			return $this->delete_section( $selected, $section );
		}

		if ( ! $confirm ) {
			return array(
				'success'   => false,
				'message'   => __( 'Re-run with confirm=true to delete the entire variation. All customisations in this variation will be lost.', 'zoltiq-agents' ),
				'locations' => $locations,
			);
		}

		return $this->delete_whole( $selected, $locations );
	}

	private function delete_section( array $loc, string $section ): array {
		$src = (string) ( $loc['source'] ?? '' );

		if ( 'db' === $src ) {
			$post = get_post( (int) ( $loc['post_id'] ?? 0 ) );
			if ( ! $post ) {
				return array(
					'success' => false,
					'message' => __( 'wp_global_styles variation post not found.', 'zoltiq-agents' ),
				);
			}
			$result = Variation_Db::delete_section( $post, $section );
			if ( is_wp_error( $result ) ) {
				return $this->error_response( $result );
			}
			$updated = get_post( (int) $result );
			return array(
				'success'  => true,
				'message'  => sprintf( __( 'Removed "%s" from the DB variation.', 'zoltiq-agents' ), $section ),
				'deleted'  => $updated ? Variation_Db::to_row( $updated, true ) : array(),
				'mode'     => 'section',
				'warnings' => array(),
			);
		}

		$path = (string) ( $loc['path'] ?? '' );
		$data = Variation_File::read_json( $path );
		if ( is_wp_error( $data ) ) {
			return $this->error_response( $data );
		}
		foreach ( Variation_Db::SECTION_PATHS[ $section ] as $p ) {
			Global_Styles_Db::path_delete( $data, $p );
		}
		$bytes = Variation_File::write_json( $path, $data );
		if ( is_wp_error( $bytes ) ) {
			return $this->error_response( $bytes );
		}

		return array(
			'success'  => true,
			'message'  => sprintf( __( 'Removed "%1$s" from %2$s.', 'zoltiq-agents' ), $section, $path ),
			'deleted'  => array(
				'source'     => $src,
				'theme'      => (string) ( $loc['theme'] ?? '' ),
				'theme_type' => (string) ( $loc['theme_type'] ?? '' ),
				'plugin'     => (string) ( $loc['plugin'] ?? '' ),
				'slug'       => (string) ( $loc['slug'] ?? '' ),
				'path'       => $path,
				'bytes'      => (int) $bytes,
			),
			'mode'     => 'section',
			'warnings' => array(),
		);
	}

	private function delete_whole( array $loc, array $locations ): array {
		$src      = (string) ( $loc['source'] ?? '' );
		$warnings = array();

		switch ( $src ) {
			case 'db':
				$post = get_post( (int) ( $loc['post_id'] ?? 0 ) );
				if ( ! $post || ! Variation_Db::delete( $post ) ) {
					return array(
						'success' => false,
						'message' => __( 'Failed to delete DB variation.', 'zoltiq-agents' ),
					);
				}
				break;

			case 'theme':
			case 'plugin':
				if ( 'plugin' === $src && false === ( $loc['plugin_active'] ?? true ) ) {
					$warnings[] = sprintf( __( 'Plugin "%s" is inactive — deleted the file directly anyway.', 'zoltiq-agents' ), $loc['plugin'] ?? '' );
				}
				$result = Variation_File::delete_file( (string) ( $loc['path'] ?? '' ) );
				if ( is_wp_error( $result ) ) {
					return $this->error_response( $result );
				}
				break;

			default:
				return array(
					'success' => false,
					'message' => __( 'Unknown source.', 'zoltiq-agents' ),
				);
		}

		$remaining = array_values(
			array_filter(
				$locations,
				static function ( $other ) use ( $loc ): bool {
					return ! self::is_same_location( $other, $loc );
				}
			)
		);
		if ( ! empty( $remaining ) ) {
			$warnings[] = __( 'Other copies still exist; WordPress will fall back to the next-highest-priority location.', 'zoltiq-agents' );
		} else {
			$warnings[] = __( 'No other copies remain — this variation is fully removed.', 'zoltiq-agents' );
		}

		return array(
			'success'   => true,
			'message'   => sprintf( __( 'Deleted variation "%s".', 'zoltiq-agents' ), (string) ( $loc['slug'] ?? '' ) ),
			'deleted'   => $loc,
			'mode'      => 'whole',
			'warnings'  => $warnings,
			'locations' => $remaining,
		);
	}

	private static function is_same_location( array $a, array $b ): bool {
		if ( ( $a['source'] ?? '' ) !== ( $b['source'] ?? '' ) ) {
			return false;
		}
		if ( 'db' === ( $a['source'] ?? '' ) ) {
			return (int) ( $a['post_id'] ?? 0 ) === (int) ( $b['post_id'] ?? 0 );
		}
		return ( $a['path'] ?? '' ) === ( $b['path'] ?? '' );
	}

	private function error_response( \WP_Error $err ): array {
		return array(
			'success' => false,
			'message' => $err->get_error_message(),
		);
	}
}
