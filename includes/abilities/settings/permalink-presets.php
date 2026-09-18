<?php

namespace Zoltiq\Agents\Includes\Abilities\Settings;

defined( 'ABSPATH' ) || exit;

final class Permalink_Presets {

	public const PRESETS = array(
		'plain'          => '',
		'day-and-name'   => '/%year%/%monthnum%/%day%/%postname%/',
		'month-and-name' => '/%year%/%monthnum%/%postname%/',
		'numeric'        => '/archives/%post_id%',
		'post-name'      => '/%postname%/',
	);

	/**
	 * Recognised WordPress rewrite tags inside a permalink structure.
	 */
	public const TAGS = array(
		'%year%',
		'%monthnum%',
		'%day%',
		'%hour%',
		'%minute%',
		'%second%',
		'%post_id%',
		'%postname%',
		'%category%',
		'%author%',
	);

	public static function resolve( string $value ): string {
		$value = trim( $value );

		if ( '' === $value || 'plain' === strtolower( $value ) || 'default' === strtolower( $value ) ) {
			return '';
		}

		$normalized = strtolower( $value );
		if ( array_key_exists( $normalized, self::PRESETS ) ) {
			return self::PRESETS[ $normalized ];
		}

		return $value;
	}

	public static function match( string $structure ): string {
		$structure = trim( $structure );
		if ( '' === $structure ) {
			return 'plain';
		}
		foreach ( self::PRESETS as $name => $value ) {
			if ( '' === $value ) {
				continue;
			}
			if ( $structure === $value ) {
				return $name;
			}
		}
		return 'custom';
	}

	public static function validate( string $structure ) {
		$structure = trim( $structure );
		if ( '' === $structure ) {
			return true;
		}

		foreach ( self::TAGS as $tag ) {
			if ( false !== strpos( $structure, $tag ) ) {
				return true;
			}
		}

		return new \WP_Error(
			'invalid_structure',
			sprintf(
				__( 'Permalink structure must contain at least one rewrite tag (%s) or be empty for "plain".', 'zoltiq-agents' ),
				implode( ', ', self::TAGS )
			)
		);
	}
}
