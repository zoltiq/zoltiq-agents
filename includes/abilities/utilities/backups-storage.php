<?php

namespace Zoltiq\Agents\Includes\Abilities\Utilities;

defined( 'ABSPATH' ) || exit;

final class Backups_Storage {

	public const BACKUPS_DIR = 'zoltiq-backups';
	public const STAGING_DIR = 'zoltiq-staging';

	public static function backups_path() {
		return self::resolve_dir( self::BACKUPS_DIR );
	}

	public static function backups_url() {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) || empty( $uploads['baseurl'] ) ) {
			return false;
		}
		return trailingslashit( $uploads['baseurl'] ) . self::BACKUPS_DIR;
	}

	public static function staging_path() {
		return self::resolve_dir( self::STAGING_DIR );
	}

	public static function random_backup_filename( string $target_type, string $target ): string {
		$slug = self::filename_slug_segment( $target_type, $target );

		list( $unix, $ms ) = self::filename_time_segments();

		return $slug . '-' . $unix . '-' . $ms . '.zip';
	}

	private static function filename_slug_segment( string $target_type, string $target ): string {
		$slug = sanitize_key( str_replace( array( '/', '\\', '.' ), '-', $target ) );
		if ( '' !== $slug ) {
			return $slug;
		}
		$type = sanitize_key( $target_type );
		if ( '' !== $type ) {
			return $type;
		}
		return 'backup';
	}

	private static function filename_time_segments(): array {
		$now  = microtime( true );
		$unix = (int) floor( $now );
		$ms   = (int) floor( ( $now - $unix ) * 1000 );
		return array( (string) $unix, str_pad( (string) $ms, 3, '0', STR_PAD_LEFT ) );
	}

	public static function resolve_managed_path( string $rel_or_name ) {
		$rel_or_name = trim( $rel_or_name );
		if ( '' === $rel_or_name ) {
			return new \WP_Error(
				'invalid_path',
				__( 'A file path is required.', 'zoltiq-agents' )
			);
		}

		if ( false !== strpos( $rel_or_name, "\0" ) ) {
			return new \WP_Error(
				'invalid_path',
				__( 'Path contains disallowed characters.', 'zoltiq-agents' )
			);
		}

		$backups = self::backups_path();
		$staging = self::staging_path();
		if ( false === $backups && false === $staging ) {
			return new \WP_Error(
				'uploads_unavailable',
				__( 'Uploads directory is unavailable on this site.', 'zoltiq-agents' )
			);
		}

		if ( false === strpos( $rel_or_name, '/' ) && false === strpos( $rel_or_name, '\\' ) ) {
			if ( false === $backups ) {
				return new \WP_Error(
					'uploads_unavailable',
					__( 'Backups directory is unavailable on this site.', 'zoltiq-agents' )
				);
			}
			$candidate = trailingslashit( $backups ) . $rel_or_name;
		} else {
			$base      = rtrim( realpath( ABSPATH ) ?: ABSPATH, '/' );
			$candidate = $base . '/' . ltrim( $rel_or_name, '/' );
		}

		$parent = realpath( dirname( $candidate ) );
		if ( false === $parent ) {
			return new \WP_Error(
				'invalid_path',
				__( 'Path does not resolve.', 'zoltiq-agents' )
			);
		}

		$parent_slash = $parent . '/';
		$in_backups   = false !== $backups && ( $parent === rtrim( $backups, '/' ) || 0 === strpos( $parent_slash, trailingslashit( $backups ) ) );
		$in_staging   = false !== $staging && ( $parent === rtrim( $staging, '/' ) || 0 === strpos( $parent_slash, trailingslashit( $staging ) ) );

		if ( ! $in_backups && ! $in_staging ) {
			return new \WP_Error(
				'path_out_of_bounds',
				__( 'Path must resolve inside zoltiq-backups/ or zoltiq-staging/.', 'zoltiq-agents' )
			);
		}

		return $candidate;
	}

	public static function list_entries( string $which, int $limit = 50, int $offset = 0 ) {
		$which = self::BACKUPS_DIR === $which ? self::BACKUPS_DIR : self::STAGING_DIR;
		$dir   = self::resolve_dir( $which );
		if ( false === $dir ) {
			return new \WP_Error(
				'uploads_unavailable',
				__( 'Directory is unavailable on this site.', 'zoltiq-agents' )
			);
		}

		$limit  = max( 1, min( 200, $limit ) );
		$offset = max( 0, $offset );

		$files = glob( trailingslashit( $dir ) . '*.zip' );
		if ( ! is_array( $files ) ) {
			$files = array();
		}

		usort(
			$files,
			static function ( string $a, string $b ): int {
				$ma = (int) @filemtime( $a ); 
				$mb = (int) @filemtime( $b ); 
				return $mb <=> $ma;
			}
		);

		$total = count( $files );
		$slice = array_slice( $files, $offset, $limit );

		$uploads_base    = wp_upload_dir( null, false );
		$uploads_basedir = ! empty( $uploads_base['basedir'] ) ? rtrim( (string) $uploads_base['basedir'], '/' ) : '';
		$uploads_baseurl = ! empty( $uploads_base['baseurl'] ) ? rtrim( (string) $uploads_base['baseurl'], '/' ) : '';
		$abspath         = rtrim( realpath( ABSPATH ) ?: ABSPATH, '/' );

		$items = array();
		foreach ( $slice as $abs ) {
			$rel = $abs;
			if ( '' !== $abspath && 0 === strpos( $abs, $abspath . '/' ) ) {
				$rel = substr( $abs, strlen( $abspath ) + 1 );
			}

			$url = '';
			if ( '' !== $uploads_basedir && 0 === strpos( $abs, $uploads_basedir . '/' ) ) {
				$url = $uploads_baseurl . '/' . ltrim( substr( $abs, strlen( $uploads_basedir ) ), '/' );
			}

			$items[] = array(
				'file_path'  => $rel,
				'file_url'   => $url,
				'size'       => (int) @filesize( $abs ),
				'sha256'     => self::sha256_of( $abs ),
				'created_at' => gmdate( 'c', (int) @filemtime( $abs ) ),
			);
		}

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	public static function sha256_of( string $abs_path ): string {
		if ( ! is_file( $abs_path ) || ! is_readable( $abs_path ) ) {
			return '';
		}
		$hash = @hash_file( 'sha256', $abs_path );
		return is_string( $hash ) ? $hash : '';
	}

	public static function to_abspath_relative( string $abs_path ): string {
		$abspath = rtrim( realpath( ABSPATH ) ?: ABSPATH, '/' );
		if ( '' !== $abspath && 0 === strpos( $abs_path, $abspath . '/' ) ) {
			return substr( $abs_path, strlen( $abspath ) + 1 );
		}
		return $abs_path;
	}

	public static function url_for( string $abs_path ): string {
		$uploads = wp_upload_dir( null, false );
		if ( empty( $uploads['basedir'] ) || empty( $uploads['baseurl'] ) ) {
			return '';
		}
		$basedir = rtrim( (string) $uploads['basedir'], '/' );
		$baseurl = rtrim( (string) $uploads['baseurl'], '/' );
		if ( 0 === strpos( $abs_path, $basedir . '/' ) ) {
			return $baseurl . '/' . ltrim( substr( $abs_path, strlen( $basedir ) ), '/' );
		}
		return '';
	}

	private static function resolve_dir( string $subdir ) {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return false;
		}
		$dir = trailingslashit( (string) $uploads['basedir'] ) . $subdir;
		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		$htaccess = $dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			$rules = "Options -Indexes\n"
				. "<FilesMatch \"\\.(php|phtml|phar|pl|py|jsp|asp|htm|html|shtml)$\">\n"
				. "\tDeny from all\n"
				. "\t<IfModule mod_authz_core.c>\n"
				. "\t\tRequire all denied\n"
				. "\t</IfModule>\n"
				. "</FilesMatch>\n";
			@file_put_contents( $htaccess, $rules );
		}
		$index_php = $dir . '/index.php';
		if ( ! file_exists( $index_php ) ) {
			@file_put_contents( $index_php, "<?php\n// Silence is golden.\n" ); 
		}
		return $dir;
	}
}
