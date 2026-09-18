<?php

namespace Zoltiq\Agents\Includes\Abilities\Taxonomies;

defined( 'ABSPATH' ) || exit;

final class Taxonomy_Routes {

	public static function rest_base( string $taxonomy ) {
		if ( '' === $taxonomy ) {
			return new \WP_Error( 'invalid_taxonomy', __( 'taxonomy is required.', 'zoltiq-agents' ) );
		}

		$obj = get_taxonomy( $taxonomy );
		if ( ! $obj ) {
			return new \WP_Error(
				'unknown_taxonomy',
				sprintf( __( 'Unknown taxonomy "%s".', 'zoltiq-agents' ), $taxonomy )
			);
		}
		if ( empty( $obj->show_in_rest ) ) {
			return new \WP_Error(
				'taxonomy_not_in_rest',
				sprintf( __( 'Taxonomy "%s" is not exposed via REST (show_in_rest is false).', 'zoltiq-agents' ), $taxonomy )
			);
		}

		$base = ! empty( $obj->rest_base ) ? $obj->rest_base : $taxonomy;
		return (string) $base;
	}
}
