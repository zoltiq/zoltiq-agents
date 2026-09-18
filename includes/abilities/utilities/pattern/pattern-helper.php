<?php

namespace Zoltiq\Agents\Includes\Abilities\Utilities\Pattern;

defined( 'ABSPATH' ) || exit;

final class Pattern_Helper {

	private const HEADERS = array(
		'Title',
		'Slug',
		'Description',
		'Viewport Width',
		'Inserter',
		'Categories',
		'Keywords',
		'Block Types',
		'Post Types',
		'Template Types',
	);

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

	public static function scan_plugins_with_patterns(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$found = array();
		foreach ( array_keys( get_plugins() ) as $rel ) {
			$parts = explode( '/', $rel );
			$slug  = $parts[0] ?? '';
			if ( '' === $slug || isset( $seen[ $slug ] ) ) {
				continue;
			}
			$seen[ $slug ] = true;

			$patterns_dir = WP_PLUGIN_DIR . '/' . $slug . '/patterns';
			if ( ! is_dir( $patterns_dir ) ) {
				continue;
			}
			$found[] = array(
				'slug'   => $slug,
				'path'   => $patterns_dir,
				'active' => is_plugin_active( $rel ),
			);
		}
		return $found;
	}

	public static function resolve_pattern_path( string $container_dir, string $filename_or_slug ) {
		$filename = self::sanitize_filename( $filename_or_slug );
		if ( '' === $filename ) {
			return new \WP_Error( 'invalid_filename', __( 'Invalid pattern filename.', 'zoltiq-agents' ) );
		}

		$patterns_dir = $container_dir . '/patterns';
		$abs_path     = $patterns_dir . '/' . $filename;

		$resolved_dir = realpath( $patterns_dir );
		if ( false !== $resolved_dir ) {
			$candidate = realpath( $abs_path );
			if ( false !== $candidate && 0 !== strpos( $candidate, $resolved_dir ) ) {
				return new \WP_Error( 'path_escape', __( 'Pattern path escapes the /patterns directory.', 'zoltiq-agents' ) );
			}
		}

		return $abs_path;
	}

	public static function sanitize_filename( string $value ): string {
		$value = basename( $value );
		if ( ! preg_match( '/\.php$/i', $value ) ) {
			$value .= '.php';
		}
		$base = preg_replace( '/\.php$/i', '', $value );
		$base = sanitize_title( $base );
		if ( '' === $base ) {
			return '';
		}
		return $base . '.php';
	}

	public static function is_valid_bare_slug( string $slug ): bool {
		return (bool) preg_match( '#^[a-z0-9_-]+$#', $slug );
	}

	public static function is_valid_full_slug( string $slug ): bool {
		return (bool) preg_match( '#^[a-z0-9_-]+/[a-z0-9_-]+$#', $slug );
	}

	public static function build_full_slug( string $container_slug, string $bare_slug ): string {
		return sanitize_key( $container_slug ) . '/' . sanitize_key( $bare_slug );
	}

	public static function is_valid_content( string $content ): bool {
		return '' !== trim( $content );
	}

	public static function build_file( array $headers, string $body ): string {
		$lines = array( '<?php', '/**' );
		foreach ( self::HEADERS as $name ) {
			if ( isset( $headers[ $name ] ) && '' !== $headers[ $name ] ) {
				$lines[] = ' * ' . $name . ': ' . self::sanitize_header_value( (string) $headers[ $name ] );
			}
		}
		$lines[] = ' */';
		$lines[] = '?>';

		return implode( "\n", $lines ) . "\n" . $body;
	}

	public static function parse_file( string $contents ): array {
		$headers = array();
		$body    = $contents;

		if ( preg_match( '#^\s*<\?php\s*/\*\*(.*?)\*/\s*\?>\s*#s', $contents, $m, PREG_OFFSET_CAPTURE ) ) {
			$header_block = $m[1][0];
			$body         = substr( $contents, $m[0][1] + strlen( $m[0][0] ) );

			foreach ( self::HEADERS as $name ) {
				if ( preg_match( '/^\s*\*?\s*' . preg_quote( $name, '/' ) . '\s*:\s*(.+)$/mi', $header_block, $hm ) ) {
					$headers[ $name ] = trim( $hm[1] );
				}
			}
		}

		return array(
			'headers' => $headers,
			'body'    => ltrim( $body, "\n" ),
		);
	}

	private static function sanitize_header_value( string $value ): string {
		$value = (string) preg_replace( '/\s+/', ' ', $value );
		return trim( $value );
	}

	public static function input_to_header_map(): array {
		return array(
			'title'          => 'Title',
			'slug_full'      => 'Slug',
			'description'    => 'Description',
			'viewport_width' => 'Viewport Width',
			'inserter'       => 'Inserter',
			'categories'     => 'Categories',
			'keywords'       => 'Keywords',
			'block_types'    => 'Block Types',
			'post_types'     => 'Post Types',
			'template_types' => 'Template Types',
		);
	}

	public static function header_fields(): array {
		return self::HEADERS;
	}
}
