<?php

namespace Zoltiq\Agents\Includes\Abilities\Utilities;

defined( 'ABSPATH' ) || exit;

final class Mime_Types_Store {

	public const OPTION = 'zoltiq_agents_extra_mimes';

	public static function get(): array {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			return array();
		}
		$out = array();
		foreach ( $stored as $ext => $mime ) {
			$ext_key = self::sanitize_ext( (string) $ext );
			$mime_v  = self::sanitize_mime( (string) $mime );
			if ( '' !== $ext_key && null !== $mime_v ) {
				$out[ $ext_key ] = $mime_v;
			}
		}
		return $out;
	}

	public static function validate( array $input ): array {
		$stored  = array();
		$skipped = array();

		foreach ( $input as $ext => $mime ) {
			$raw_ext  = (string) $ext;
			$raw_mime = (string) $mime;
			$ext_key  = self::sanitize_ext( $raw_ext );
			$mime_v   = self::sanitize_mime( $raw_mime );

			if ( '' === $ext_key ) {
				$skipped[] = array(
					'ext'    => $raw_ext,
					'mime'   => $raw_mime,
					'reason' => __( 'Extension must be lowercase letters and digits only.', 'zoltiq-agents' ),
				);
				continue;
			}
			if ( null === $mime_v ) {
				$skipped[] = array(
					'ext'    => $raw_ext,
					'mime'   => $raw_mime,
					'reason' => __( 'MIME type must match "type/subtype" (lowercase, RFC-compatible).', 'zoltiq-agents' ),
				);
				continue;
			}
			$stored[ $ext_key ] = $mime_v;
		}

		return array(
			'stored'  => $stored,
			'skipped' => $skipped,
		);
	}

	public static function set( array $input ): array {
		$result = self::validate( $input );
		update_option( self::OPTION, $result['stored'], false );
		return $result;
	}

	public static function merge( array $additions ): array {
		$current = self::get();
		$added   = array();
		$skipped = array();

		foreach ( $additions as $ext => $mime ) {
			$raw_ext  = (string) $ext;
			$raw_mime = (string) $mime;
			$ext_key  = self::sanitize_ext( $raw_ext );
			$mime_v   = self::sanitize_mime( $raw_mime );

			if ( '' === $ext_key ) {
				$skipped[] = array(
					'ext'    => $raw_ext,
					'mime'   => $raw_mime,
					'reason' => __( 'Extension must be lowercase letters and digits only.', 'zoltiq-agents' ),
				);
				continue;
			}
			if ( null === $mime_v ) {
				$skipped[] = array(
					'ext'    => $raw_ext,
					'mime'   => $raw_mime,
					'reason' => __( 'MIME type must match "type/subtype" (lowercase, RFC-compatible).', 'zoltiq-agents' ),
				);
				continue;
			}

			if ( isset( $current[ $ext_key ] ) && $current[ $ext_key ] === $mime_v ) {
				continue;
			}

			$current[ $ext_key ] = $mime_v;
			$added[]             = array(
				'ext'  => $ext_key,
				'mime' => $mime_v,
			);
		}

		update_option( self::OPTION, $current, false );

		return array(
			'stored'  => $current,
			'added'   => $added,
			'skipped' => $skipped,
		);
	}

	public static function remove( array $exts ): array {
		$current   = self::get();
		$removed   = array();
		$not_found = array();

		foreach ( $exts as $raw ) {
			$ext = self::sanitize_ext( (string) $raw );
			if ( '' === $ext ) {
				$not_found[] = (string) $raw;
				continue;
			}
			if ( ! array_key_exists( $ext, $current ) ) {
				$not_found[] = $ext;
				continue;
			}
			unset( $current[ $ext ] );
			$removed[] = $ext;
		}

		update_option( self::OPTION, $current, false );

		return array(
			'stored'    => $current,
			'removed'   => $removed,
			'not_found' => $not_found,
		);
	}

	public static function filter_upload_mimes( $mimes ): array {
		$mimes = is_array( $mimes ) ? $mimes : array();
		foreach ( self::get() as $ext => $mime ) {
			if ( ! isset( $mimes[ $ext ] ) ) {
				$mimes[ $ext ] = $mime;
			}
		}
		return $mimes;
	}

	public static function attribute( string $ext_key, string $mime, array $core, array $extras ): string {
		if ( isset( $core[ $ext_key ] ) && $core[ $ext_key ] === $mime ) {
			return 'core';
		}
		foreach ( explode( '|', $ext_key ) as $part ) {
			$part = self::sanitize_ext( $part );
			if ( '' !== $part && isset( $extras[ $part ] ) && $extras[ $part ] === $mime ) {
				return 'this-plugin';
			}
		}
		return 'other-filter';
	}

	public static function expand_ext_key( string $ext_key ): array {
		$out = array();
		foreach ( explode( '|', $ext_key ) as $part ) {
			$part = self::sanitize_ext( $part );
			if ( '' !== $part ) {
				$out[] = $part;
			}
		}
		return $out;
	}

	private static function sanitize_ext( string $ext ): string {
		$ext = strtolower( trim( $ext ) );
		$ext = ltrim( $ext, '.' );
		if ( '' === $ext || ! preg_match( '/^[a-z0-9]+$/', $ext ) ) {
			return '';
		}
		return $ext;
	}

	private static function sanitize_mime( string $mime ): ?string {
		$mime = strtolower( trim( $mime ) );
		if ( ! preg_match( '#^[a-z0-9]+/[a-z0-9.+-]+$#', $mime ) ) {
			return null;
		}
		return $mime;
	}
}
