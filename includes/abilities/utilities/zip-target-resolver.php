<?php

namespace Zoltiq\Agents\Includes\Abilities\Utilities;

defined( 'ABSPATH' ) || exit;

final class Zip_Target_Resolver {

	public const TYPE_PLUGIN     = 'plugin';
	public const TYPE_THEME      = 'theme';
	public const TYPE_UPLOADS    = 'uploads';
	public const TYPE_MU_PLUGINS = 'mu-plugins';
	public const TYPE_PATH       = 'path';

	public static function supported_types(): array {
		return array(
			self::TYPE_PLUGIN,
			self::TYPE_THEME,
			self::TYPE_UPLOADS,
			self::TYPE_MU_PLUGINS,
			self::TYPE_PATH,
		);
	}

	public static function resolve_source( string $target_type, string $target ) {
		$resolved = self::resolve( $target_type, $target, false );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		if ( ! is_dir( $resolved['abs_path'] ) ) {
			return new \WP_Error(
				'target_not_found',
				sprintf(
					__( 'The resolved target directory does not exist: %s', 'zoltiq-agents' ),
					$resolved['abs_path']
				)
			);
		}
		return $resolved;
	}

	public static function resolve_destination( string $target_type, string $target ) {
		$resolved = self::resolve( $target_type, $target, true );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		if ( ! is_dir( $resolved['abs_path'] ) ) {
			if ( ! wp_mkdir_p( $resolved['abs_path'] ) ) {
				return new \WP_Error(
					'destination_create_failed',
					sprintf(
						__( 'Could not create destination directory: %s', 'zoltiq-agents' ),
						$resolved['abs_path']
					)
				);
			}
		}
		return $resolved;
	}

	private static function resolve( string $target_type, string $target, bool $for_destination ) {
		$target_type = sanitize_key( $target_type );
		$target      = sanitize_text_field( $target );

		if ( ! in_array( $target_type, self::supported_types(), true ) ) {
			return new \WP_Error(
				'invalid_target_type',
				sprintf(
					__( 'Unsupported target_type "%1$s". Must be one of: %2$s', 'zoltiq-agents' ),
					$target_type,
					implode( ', ', self::supported_types() )
				)
			);
		}

		switch ( $target_type ) {
			case self::TYPE_PLUGIN:
				return self::resolve_plugin_dir( $target );

			case self::TYPE_THEME:
				return self::resolve_theme_dir( $target );

			case self::TYPE_UPLOADS:
				$uploads = wp_upload_dir( null, false );
				if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
					return new \WP_Error(
						'uploads_unavailable',
						__( 'Uploads directory is unavailable.', 'zoltiq-agents' )
					);
				}
				return array(
					'abs_path' => rtrim( (string) $uploads['basedir'], '/' ),
					'label'    => 'uploads',
				);

			case self::TYPE_MU_PLUGINS:
				if ( ! defined( 'WPMU_PLUGIN_DIR' ) ) {
					return new \WP_Error(
						'mu_plugins_unavailable',
						__( 'WPMU_PLUGIN_DIR is not defined on this install.', 'zoltiq-agents' )
					);
				}
				$mu_dir = rtrim( (string) WPMU_PLUGIN_DIR, '/' );
				if ( $for_destination && ! is_dir( $mu_dir ) ) {
					if ( ! wp_mkdir_p( $mu_dir ) ) {
						return new \WP_Error(
							'mu_plugins_create_failed',
							__( 'Could not create the mu-plugins directory.', 'zoltiq-agents' )
						);
					}
				}
				return array(
					'abs_path' => $mu_dir,
					'label'    => 'mu-plugins',
				);

			case self::TYPE_PATH:
			default:
				return self::resolve_abspath_relative( $target, $for_destination );
		}
	}

	private static function resolve_plugin_dir( string $target ) {
		if ( '' === $target ) {
			return new \WP_Error(
				'invalid_target',
				__( 'A plugin slug is required when target_type is "plugin".', 'zoltiq-agents' )
			);
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_file = null;

		if ( function_exists( '\get_plugins' ) ) {
			$installed = get_plugins();
			if ( isset( $installed[ $target ] ) ) {
				$plugin_file = $target;
			}
		}

		if ( null === $plugin_file ) {
			$resolved = Plugin_Helpers::resolve_plugin( $target );
			if ( ! empty( $resolved['plugin_file'] ) && $resolved['certainty'] >= 8.0 ) {
				$plugin_file = (string) $resolved['plugin_file'];
			}
		}

		if ( null === $plugin_file ) {
			$plugins_dir = defined( 'WP_PLUGIN_DIR' ) ? rtrim( (string) WP_PLUGIN_DIR, '/' ) : rtrim( WP_CONTENT_DIR, '/' ) . '/plugins';
			$candidate   = $plugins_dir . '/' . ltrim( $target, '/' );
			$candidate   = rtrim( $candidate, '/' );
			if ( is_dir( $candidate ) ) {
				return array(
					'abs_path' => $candidate,
					'label'    => 'plugin:' . basename( $candidate ),
				);
			}

			return new \WP_Error(
				'plugin_not_found',
				sprintf(
					__( 'Could not resolve plugin "%s".', 'zoltiq-agents' ),
					$target
				)
			);
		}

		$plugins_dir = defined( 'WP_PLUGIN_DIR' ) ? rtrim( (string) WP_PLUGIN_DIR, '/' ) : rtrim( WP_CONTENT_DIR, '/' ) . '/plugins';

		$slug_dir = strtok( $plugin_file, '/' );
		if ( false === $slug_dir || '' === $slug_dir || strpos( $plugin_file, '/' ) === false ) {
			return new \WP_Error(
				'plugin_single_file',
				sprintf(
					__( 'Plugin "%s" is a single file with no directory to archive.', 'zoltiq-agents' ),
					$plugin_file
				)
			);
		}

		$abs = $plugins_dir . '/' . $slug_dir;
		if ( ! is_dir( $abs ) ) {
			return new \WP_Error(
				'plugin_dir_missing',
				sprintf(
					__( 'Resolved plugin directory does not exist: %s', 'zoltiq-agents' ),
					$abs
				)
			);
		}

		return array(
			'abs_path' => $abs,
			'label'    => 'plugin:' . $slug_dir,
		);
	}

	private static function resolve_theme_dir( string $target ) {
		if ( '' === $target ) {
			return new \WP_Error(
				'invalid_target',
				__( 'A theme stylesheet is required when target_type is "theme".', 'zoltiq-agents' )
			);
		}

		$stylesheet = null;

		if ( function_exists( '\wp_get_theme' ) ) {
			$theme = wp_get_theme( $target );
			if ( $theme && $theme->exists() ) {
				$stylesheet = (string) $theme->get_stylesheet();
			}
		}

		if ( null === $stylesheet ) {
			$resolved = Theme_Helpers::resolve_theme( $target );
			if ( ! empty( $resolved['stylesheet'] ) && $resolved['certainty'] >= 8.0 ) {
				$stylesheet = (string) $resolved['stylesheet'];
			}
		}

		if ( null === $stylesheet ) {
			return new \WP_Error(
				'theme_not_found',
				sprintf(
					__( 'Could not resolve theme "%s".', 'zoltiq-agents' ),
					$target
				)
			);
		}

		$themes_root = get_theme_root( $stylesheet );
		if ( ! is_string( $themes_root ) || '' === $themes_root ) {
			$themes_root = rtrim( WP_CONTENT_DIR, '/' ) . '/themes';
		}
		$abs = rtrim( $themes_root, '/' ) . '/' . $stylesheet;

		if ( ! is_dir( $abs ) ) {
			return new \WP_Error(
				'theme_dir_missing',
				sprintf(
					__( 'Resolved theme directory does not exist: %s', 'zoltiq-agents' ),
					$abs
				)
			);
		}

		return array(
			'abs_path' => $abs,
			'label'    => 'theme:' . $stylesheet,
		);
	}

	private static function resolve_abspath_relative( string $rel_path, bool $for_destination ) {
		if ( '' === $rel_path ) {
			return new \WP_Error(
				'invalid_target',
				__( 'A path is required when target_type is "path".', 'zoltiq-agents' )
			);
		}
		if ( false !== strpos( $rel_path, "\0" ) ) {
			return new \WP_Error(
				'invalid_path',
				__( 'Path contains disallowed characters.', 'zoltiq-agents' )
			);
		}

		$base      = rtrim( realpath( ABSPATH ) ?: ABSPATH, '/' );
		$candidate = $base . '/' . ltrim( $rel_path, '/' );

		if ( ! $for_destination ) {
			$abs = realpath( $candidate );
			if ( false === $abs || ( $abs !== $base && 0 !== strpos( $abs, $base . '/' ) ) ) {
				return new \WP_Error(
					'path_out_of_bounds',
					__( 'Path must resolve inside ABSPATH.', 'zoltiq-agents' )
				);
			}
			return array(
				'abs_path' => $abs,
				'label'    => 'path:' . $rel_path,
			);
		}

		$parent = realpath( dirname( $candidate ) );
		if ( false === $parent || ( $parent !== $base && 0 !== strpos( $parent, $base . '/' ) ) ) {
			return new \WP_Error(
				'path_out_of_bounds',
				__( 'Destination parent must resolve inside ABSPATH.', 'zoltiq-agents' )
			);
		}

		return array(
			'abs_path' => $parent . '/' . basename( $candidate ),
			'label'    => 'path:' . $rel_path,
		);
	}
}
