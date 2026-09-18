<?php

namespace Zoltiq\Agents\Includes\Abilities\Utilities\Template;

use Zoltiq\Agents\Includes\Abilities\Utilities\File_Mods_Guard;

defined( 'ABSPATH' ) || exit;

final class Template_File {

	public static function resolve_theme_dir( string $slug ) {
		$themes_dir = rtrim( get_theme_root(), '/' );
		$theme_dir  = '' !== $slug
			? realpath( $themes_dir . '/' . $slug )
			: realpath( get_stylesheet_directory() );

		if ( false === $theme_dir || 0 !== strpos( $theme_dir, $themes_dir ) || ! is_dir( $theme_dir ) ) {
			return new \WP_Error( 'theme_not_found', __( 'Theme directory not found.', 'zoltiq-agents' ) );
		}

		return $theme_dir;
	}

	public static function get_child_theme_dir(): ?string {
		if ( get_template() === get_stylesheet() ) {
			return null;
		}
		$path = realpath( get_stylesheet_directory() );
		return $path ? $path : null;
	}

	public static function get_parent_theme_dir(): string {
		return rtrim( get_template_directory(), '/' );
	}

	public static function resolve_plugin_dir( string $slug ) {
		$slug = sanitize_key( $slug );
		if ( '' === $slug ) {
			return new \WP_Error( 'invalid_plugin_slug', __( 'Plugin slug is required.', 'zoltiq-agents' ) );
		}

		$plugins_dir = rtrim( WP_PLUGIN_DIR, '/' );
		$plugin_dir  = realpath( $plugins_dir . '/' . $slug );

		if ( false === $plugin_dir || 0 !== strpos( $plugin_dir, $plugins_dir ) || ! is_dir( $plugin_dir ) ) {
			return new \WP_Error( 'plugin_not_found', __( 'Plugin directory not found.', 'zoltiq-agents' ) );
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugin_file = '';
		$active      = false;
		foreach ( array_keys( get_plugins() ) as $rel ) {
			if ( 0 === strpos( $rel, $slug . '/' ) ) {
				$plugin_file = $rel;
				$active      = is_plugin_active( $rel );
				break;
			}
		}

		return array(
			'path'        => $plugin_dir,
			'active'      => $active,
			'plugin_file' => $plugin_file,
		);
	}

	public static function scan_plugins_with_templates(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$found = array();
		$seen  = array();
		foreach ( array_keys( get_plugins() ) as $rel ) {
			$parts = explode( '/', $rel );
			$slug  = $parts[0] ?? '';
			if ( '' === $slug || isset( $seen[ $slug ] ) ) {
				continue;
			}
			$seen[ $slug ] = true;

			$templates_dir = WP_PLUGIN_DIR . '/' . $slug . '/templates';
			if ( ! is_dir( $templates_dir ) ) {
				continue;
			}
			$found[] = array(
				'slug'   => $slug,
				'path'   => $templates_dir,
				'active' => is_plugin_active( $rel ),
			);
		}
		return $found;
	}

	public static function templates_dir( string $container_dir ): string {
		return rtrim( $container_dir, '/' ) . '/templates';
	}

	public static function resolve_template_path( string $container_dir, string $filename_or_slug ) {
		$filename = self::sanitize_filename( $filename_or_slug );
		if ( '' === $filename ) {
			return new \WP_Error( 'invalid_filename', __( 'Invalid template filename.', 'zoltiq-agents' ) );
		}

		$templates_dir = self::templates_dir( $container_dir );
		$abs_path      = $templates_dir . '/' . $filename;

		$resolved_dir = realpath( $templates_dir );
		if ( false !== $resolved_dir ) {
			$candidate = realpath( $abs_path );
			if ( false !== $candidate && 0 !== strpos( $candidate, $resolved_dir ) ) {
				return new \WP_Error( 'path_escape', __( 'Template path escapes the /templates directory.', 'zoltiq-agents' ) );
			}
		}

		return $abs_path;
	}

	public static function sanitize_filename( string $value ): string {
		$value = basename( $value );
		if ( ! preg_match( '/\.html$/i', $value ) ) {
			$value .= '.html';
		}
		$base = preg_replace( '/\.html?$/i', '', $value );
		$base = sanitize_title( $base );
		if ( '' === $base ) {
			return '';
		}
		return $base . '.html';
	}

	public static function is_valid_bare_slug( string $slug ): bool {
		return (bool) preg_match( '#^[a-z0-9_-]+$#', $slug );
	}

	public static function ensure_templates_dir( string $container_dir ) {
		$guard = File_Mods_Guard::check();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$templates_dir = self::templates_dir( $container_dir );
		if ( ! is_dir( $templates_dir ) ) {
			if ( ! wp_mkdir_p( $templates_dir ) ) {
				return new \WP_Error( 'mkdir_failed', __( 'Could not create /templates directory.', 'zoltiq-agents' ) );
			}
		}
		if ( ! is_writable( $templates_dir ) ) {
			return new \WP_Error( 'dir_not_writable', __( 'The /templates directory is not writable.', 'zoltiq-agents' ) );
		}
		return $templates_dir;
	}

	public static function read_file( string $abs_path ) {
		if ( ! is_file( $abs_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'Template file not found.', 'zoltiq-agents' ) );
		}
		$contents = file_get_contents( $abs_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $contents ) {
			return new \WP_Error( 'read_failed', __( 'Could not read template file.', 'zoltiq-agents' ) );
		}
		return $contents;
	}

	public static function write_file( string $abs_path, string $content ) {
		$guard = File_Mods_Guard::check();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$dir = dirname( $abs_path );
		if ( ! is_dir( $dir ) ) {
			if ( ! wp_mkdir_p( $dir ) ) {
				return new \WP_Error( 'mkdir_failed', __( 'Could not create directory for template.', 'zoltiq-agents' ) );
			}
		}
		if ( file_exists( $abs_path ) && ! is_writable( $abs_path ) ) {
			return new \WP_Error(
				'file_not_writable',
				sprintf( __( 'Template file is read-only: %s. Check file permissions or save to the database instead.', 'zoltiq-agents' ), $abs_path )
			);
		}
		if ( ! file_exists( $abs_path ) && ! is_writable( $dir ) ) {
			return new \WP_Error(
				'dir_not_writable',
				sprintf( __( 'Templates directory is not writable: %s.', 'zoltiq-agents' ), $dir )
			);
		}

		$bytes = file_put_contents( $abs_path, $content );
		if ( false === $bytes ) {
			return new \WP_Error( 'write_failed', __( 'Could not write template file.', 'zoltiq-agents' ) );
		}
		return (int) $bytes;
	}

	public static function delete_file( string $abs_path ) {
		$guard = File_Mods_Guard::check();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		if ( ! file_exists( $abs_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'Template file not found.', 'zoltiq-agents' ) );
		}
		if ( ! is_writable( $abs_path ) ) {
			return new \WP_Error(
				'file_not_writable',
				sprintf( __( 'Cannot delete template file (permission denied): %s.', 'zoltiq-agents' ), $abs_path )
			);
		}
		if ( ! @unlink( $abs_path ) ) { 
			return new \WP_Error( 'unlink_failed', __( 'Could not delete template file.', 'zoltiq-agents' ) );
		}
		return true;
	}
}
