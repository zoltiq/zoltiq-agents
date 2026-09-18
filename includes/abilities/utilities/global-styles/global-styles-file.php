<?php

namespace Zoltiq\Agents\Includes\Abilities\Utilities\Global_Styles;

use Zoltiq\Agents\Includes\Abilities\Utilities\File_Mods_Guard;

defined( 'ABSPATH' ) || exit;

final class Global_Styles_File {

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

	public static function scan_plugins_with_theme_json(): array {
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

			$json = WP_PLUGIN_DIR . '/' . $slug . '/theme.json';
			if ( ! is_file( $json ) ) {
				continue;
			}
			$found[] = array(
				'slug'   => $slug,
				'path'   => $json,
				'active' => is_plugin_active( $rel ),
			);
		}
		return $found;
	}

	public static function theme_json_path( string $container_dir ): string {
		return rtrim( $container_dir, '/' ) . '/theme.json';
	}

	public static function read_file( string $abs_path ) {
		if ( ! is_file( $abs_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'theme.json file not found.', 'zoltiq-agents' ) );
		}
		$contents = file_get_contents( $abs_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $contents ) {
			return new \WP_Error( 'read_failed', __( 'Could not read theme.json file.', 'zoltiq-agents' ) );
		}
		return $contents;
	}

	public static function read_json( string $abs_path ) {
		$raw = self::read_file( $abs_path );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}
		if ( '' === trim( $raw ) ) {
			return array();
		}
		$decoded = json_decode( $raw, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new \WP_Error(
				'invalid_json',
				sprintf( __( 'Invalid JSON in %1$s: %2$s', 'zoltiq-agents' ), $abs_path, json_last_error_msg() )
			);
		}
		return is_array( $decoded ) ? $decoded : array();
	}

	public static function write_file( string $abs_path, string $content ) {
		$guard = File_Mods_Guard::check();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$dir = dirname( $abs_path );
		if ( ! is_dir( $dir ) ) {
			if ( ! wp_mkdir_p( $dir ) ) {
				return new \WP_Error( 'mkdir_failed', __( 'Could not create directory for theme.json.', 'zoltiq-agents' ) );
			}
		}
		if ( file_exists( $abs_path ) && ! is_writable( $abs_path ) ) {
			return new \WP_Error(
				'file_not_writable',
				sprintf( __( 'theme.json is read-only: %s. Check file permissions or save to the database instead.', 'zoltiq-agents' ), $abs_path )
			);
		}
		if ( ! file_exists( $abs_path ) && ! is_writable( $dir ) ) {
			return new \WP_Error(
				'dir_not_writable',
				sprintf( __( 'Theme/plugin directory is not writable: %s.', 'zoltiq-agents' ), $dir )
			);
		}

		$bytes = file_put_contents( $abs_path, $content ); 
		if ( false === $bytes ) {
			return new \WP_Error( 'write_failed', __( 'Could not write theme.json file.', 'zoltiq-agents' ) );
		}
		return (int) $bytes;
	}

	public static function write_json( string $abs_path, array $data ) {
		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $json ) {
			return new \WP_Error( 'json_encode_failed', __( 'Could not encode theme.json data.', 'zoltiq-agents' ) );
		}
		return self::write_file( $abs_path, (string) $json );
	}

	public static function delete_file( string $abs_path ) {
		$guard = File_Mods_Guard::check();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		if ( ! file_exists( $abs_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'theme.json file not found.', 'zoltiq-agents' ) );
		}
		if ( ! is_writable( $abs_path ) ) {
			return new \WP_Error(
				'file_not_writable',
				sprintf( __( 'Cannot delete theme.json (permission denied): %s.', 'zoltiq-agents' ), $abs_path )
			);
		}
		if ( ! @unlink( $abs_path ) ) { 
			return new \WP_Error( 'unlink_failed', __( 'Could not delete theme.json file.', 'zoltiq-agents' ) );
		}
		return true;
	}
}
