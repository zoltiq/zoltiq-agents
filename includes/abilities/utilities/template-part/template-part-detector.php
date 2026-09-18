<?php

namespace Zoltiq\Agents\Includes\Abilities\Utilities\Template_Part;

defined( 'ABSPATH' ) || exit;

final class Template_Part_Detector {

	public static function locate( string $slug, string $theme_hint = '' ): array {
		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			return array();
		}

		$theme = '' !== $theme_hint ? sanitize_key( $theme_hint ) : (string) get_stylesheet();

		$locations = array();

		$post = Template_Part_Db::find_by_slug( $slug, $theme );
		if ( ! $post ) {
			$post = Template_Part_Db::find_by_slug( $slug );
		}
		if ( $post ) {
			$locations[] = array(
				'source'  => 'db',
				'slug'    => (string) $post->post_name,
				'post_id' => (int) $post->ID,
				'theme'   => Template_Part_Db::get_post_theme( $post ),
				'area'    => Template_Part_Db::get_post_area( $post ),
			);
		}

		$child_dir = Template_Part_File::get_child_theme_dir();
		if ( null !== $child_dir ) {
			$file = $child_dir . '/parts/' . $slug . '.html';
			if ( is_file( $file ) ) {
				$locations[] = array(
					'source'     => 'theme',
					'theme_type' => 'child',
					'theme'      => basename( $child_dir ),
					'path'       => $file,
					'slug'       => $slug,
					'writable'   => is_writable( $file ),
				);
			}
		}

		$parent_dir   = Template_Part_File::get_parent_theme_dir();
		$parent_file  = $parent_dir . '/parts/' . $slug . '.html';
		$is_child_set = null !== $child_dir;
		if ( is_file( $parent_file ) ) {
			$locations[] = array(
				'source'     => 'theme',
				'theme_type' => $is_child_set ? 'parent' : 'theme',
				'theme'      => basename( $parent_dir ),
				'path'       => $parent_file,
				'slug'       => $slug,
				'writable'   => is_writable( $parent_file ),
			);
		}

		foreach ( Template_Part_File::scan_plugins_with_parts() as $plugin ) {
			$file = $plugin['path'] . '/' . $slug . '.html';
			if ( is_file( $file ) ) {
				$locations[] = array(
					'source'        => 'plugin',
					'plugin'        => $plugin['slug'],
					'plugin_active' => (bool) $plugin['active'],
					'path'          => $file,
					'slug'          => $slug,
					'writable'      => is_writable( $file ),
				);
			}
		}

		return $locations;
	}

	public static function select( array $locations, string $source = '', string $theme_type = '', string $plugin_slug = '' ) {
		if ( empty( $locations ) ) {
			return new \WP_Error( 'not_found', __( 'No template part with this slug was found in the database, the active theme, or any plugin. Use template-part-create to add one.', 'zoltiq-agents' ) );
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
			$error = new \WP_Error( 'not_found_at_source', __( 'No template part with this slug exists at the requested source.', 'zoltiq-agents' ) );
			$error->add_data( array( 'locations' => $locations ) );
			return $error;
		}

		if ( count( $candidates ) > 1 ) {
			$error = new \WP_Error(
				'multiple_locations',
				__( 'This slug exists in more than one location. Specify "source" (and "theme_type" or "plugin_slug") to pick one. WordPress always uses the DB version when one exists.', 'zoltiq-agents' )
			);
			$error->add_data( array( 'locations' => $candidates ) );
			return $error;
		}

		return $candidates[0];
	}

	public static function effective( array $locations ): ?array {
		foreach ( array( 'db' ) as $src ) {
			foreach ( $locations as $loc ) {
				if ( ( $loc['source'] ?? '' ) === $src ) {
					return $loc;
				}
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
