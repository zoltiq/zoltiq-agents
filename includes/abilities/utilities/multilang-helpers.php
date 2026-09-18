<?php

namespace Zoltiq\Agents\Includes\Abilities\Utilities;

defined( 'ABSPATH' ) || exit;

final class Multilang_Helpers {

	public static function detect(): string {
		if ( function_exists( 'pll_languages_list' ) ) {
			return 'polylang';
		}
		if ( class_exists( '\SitePress' ) || defined( 'WPML_PLUGIN_BASENAME' ) ) {
			return 'wpml';
		}
		return '';
	}

	public static function missing_plugin_error(): \WP_Error {
		return new \WP_Error(
			'no_multilang_plugin',
			__( 'No multilingual plugin is active. Install and activate Polylang or WPML.', 'zoltiq-agents' )
		);
	}

	public static function get_translations( int $post_id ) {
		$driver = self::detect();
		if ( '' === $driver ) {
			return self::missing_plugin_error();
		}

		if ( 'polylang' === $driver && function_exists( 'pll_get_post_translations' ) ) {
			$out = pll_get_post_translations( $post_id );
			return is_array( $out ) ? array_map( 'intval', $out ) : array();
		}

		$details = apply_filters( 'wpml_post_language_details', null, $post_id );
		$type    = isset( $details['element_type'] ) ? $details['element_type'] : 'post_' . get_post_type( $post_id );
		$trid    = apply_filters( 'wpml_element_trid', null, $post_id, $type );
		if ( ! $trid ) {
			return array();
		}
		$translations = apply_filters( 'wpml_get_element_translations', null, $trid, $type );
		$out          = array();
		if ( is_array( $translations ) ) {
			foreach ( $translations as $lang => $obj ) {
				if ( isset( $obj->element_id ) ) {
					$out[ $lang ] = (int) $obj->element_id;
				}
			}
		}
		return $out;
	}

	public static function set_post_language( int $post_id, string $language ) {
		$driver = self::detect();
		if ( '' === $driver ) {
			return self::missing_plugin_error();
		}

		if ( 'polylang' === $driver && function_exists( 'pll_set_post_language' ) ) {
			pll_set_post_language( $post_id, $language );
			return true;
		}

		// WPML.
		$type = 'post_' . get_post_type( $post_id );
		do_action(
			'wpml_set_element_language_details',
			array(
				'element_id'    => $post_id,
				'element_type'  => $type,
				'language_code' => $language,
			)
		);
		return true;
	}

	public static function link_translations( array $translations ) {
		$driver = self::detect();
		if ( '' === $driver ) {
			return self::missing_plugin_error();
		}

		if ( 'polylang' === $driver && function_exists( 'pll_save_post_translations' ) ) {
			pll_save_post_translations( array_map( 'intval', $translations ) );
			return true;
		}

		$first_id = (int) reset( $translations );
		$type     = 'post_' . get_post_type( $first_id );
		$trid     = apply_filters( 'wpml_element_trid', null, $first_id, $type );

		foreach ( $translations as $lang => $post_id ) {
			do_action(
				'wpml_set_element_language_details',
				array(
					'element_id'       => (int) $post_id,
					'element_type'     => $type,
					'language_code'    => $lang,
					'trid'             => $trid,
					'check_duplicates' => false,
				)
			);
		}
		return true;
	}
}
