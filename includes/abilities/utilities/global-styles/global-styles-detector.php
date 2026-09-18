<?php

namespace Zoltiq\Agents\Includes\Abilities\Utilities\Global_Styles;

defined( 'ABSPATH' ) || exit;

final class Global_Styles_Detector {

	public static function locate( string $theme_hint = '' ): array {
		$theme = '' !== $theme_hint ? sanitize_key( $theme_hint ) : (string) get_stylesheet();

		$locations = array();

		$post = Global_Styles_Db::find_by_theme( $theme );
		if ( $post ) {
			$locations[] = array(
				'source'              => 'db',
				'theme'               => $theme,
				'post_id'             => (int) $post->ID,
				'customized_sections' => Global_Styles_Db::get_customized_sections( $post ),
			);
		}

		$child_dir = Global_Styles_File::get_child_theme_dir();
		if ( null !== $child_dir ) {
			$file = Global_Styles_File::theme_json_path( $child_dir );
			if ( is_file( $file ) ) {
				$locations[] = array(
					'source'     => 'theme',
					'theme_type' => 'child',
					'theme'      => basename( $child_dir ),
					'path'       => $file,
					'writable'   => is_writable( $file ),
				);
			}
		}

		$parent_dir   = Global_Styles_File::get_parent_theme_dir();
		$parent_file  = Global_Styles_File::theme_json_path( $parent_dir );
		$is_child_set = null !== $child_dir;
		if ( is_file( $parent_file ) ) {
			$locations[] = array(
				'source'     => 'theme',
				'theme_type' => $is_child_set ? 'parent' : 'theme',
				'theme'      => basename( $parent_dir ),
				'path'       => $parent_file,
				'writable'   => is_writable( $parent_file ),
			);
		}

		foreach ( Global_Styles_File::scan_plugins_with_theme_json() as $plugin ) {
			$locations[] = array(
				'source'        => 'plugin',
				'plugin'        => $plugin['slug'],
				'plugin_active' => (bool) $plugin['active'],
				'path'          => $plugin['path'],
				'writable'      => is_writable( $plugin['path'] ),
			);
		}

		return $locations;
	}

	public static function select( array $locations, string $source = '', string $theme_type = '', string $plugin_slug = '' ) {
		if ( empty( $locations ) ) {
			return new \WP_Error( 'not_found', __( 'No Global Styles record was found for this theme in the database, theme files, or any plugin. Use global-styles-create to add one.', 'zoltiq-agents' ) );
		}

		$candidates = $locations;

		if ( '' !== $source ) {
			$normalized = ( 'child_theme' === $source ) ? 'theme' : $source;
			$want_child = ( 'child_theme' === $source );
			$candidates = array_values(
				array_filter(
					$candidates,
					static function ( $loc ) use ( $normalized, $want_child ): bool {
						if ( ( $loc['source'] ?? '' ) !== $normalized ) {
							return false;
						}
						if ( $want_child ) {
							return ( $loc['theme_type'] ?? '' ) === 'child';
						}
						return true;
					}
				)
			);
		}

		if ( 'theme' === $source && '' !== $theme_type ) {
			$candidates = array_values(
				array_filter(
					$candidates,
					static function ( $loc ) use ( $theme_type ): bool {
						return ( $loc['theme_type'] ?? '' ) === $theme_type;
					}
				)
			);
		}

		if ( 'plugin' === $source && '' !== $plugin_slug ) {
			$candidates = array_values(
				array_filter(
					$candidates,
					static function ( $loc ) use ( $plugin_slug ): bool {
						return ( $loc['plugin'] ?? '' ) === $plugin_slug;
					}
				)
			);
		}

		if ( empty( $candidates ) ) {
			$error = new \WP_Error( 'not_found_at_source', __( 'No Global Styles record exists at the requested source.', 'zoltiq-agents' ) );
			$error->add_data( array( 'locations' => $locations ) );
			return $error;
		}

		if ( count( $candidates ) > 1 ) {
			$error = new \WP_Error(
				'multiple_locations',
				__( 'Global Styles exist in more than one location. Specify "source" (and "theme_type" or "plugin_slug") to pick one. WordPress always uses the DB version when one exists.', 'zoltiq-agents' )
			);
			$error->add_data( array( 'locations' => $candidates ) );
			return $error;
		}

		return $candidates[0];
	}

	public static function effective( array $locations ): ?array {
		foreach ( $locations as $loc ) {
			if ( ( $loc['source'] ?? '' ) === 'db' ) {
				return $loc;
			}
		}
		foreach ( $locations as $loc ) {
			if ( ( $loc['source'] ?? '' ) === 'theme' && ( $loc['theme_type'] ?? '' ) === 'child' ) {
				return $loc;
			}
		}
		foreach ( $locations as $loc ) {
			if ( ( $loc['source'] ?? '' ) === 'theme' ) {
				return $loc;
			}
		}
		foreach ( $locations as $loc ) {
			if ( ( $loc['source'] ?? '' ) === 'plugin' ) {
				return $loc;
			}
		}
		return null;
	}
}
