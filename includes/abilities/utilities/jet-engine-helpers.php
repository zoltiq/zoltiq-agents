<?php

namespace Zoltiq\Agents\Includes\Abilities\Utilities;

defined( 'ABSPATH' ) || exit;

final class Jet_Engine_Helpers {

	public static function is_available(): bool {
		return function_exists( 'jet_engine' )
			&& is_object( jet_engine() )
			&& isset( jet_engine()->options_pages )
			&& is_object( jet_engine()->options_pages );
	}

	public static function missing_plugin_error(): \WP_Error {
		return new \WP_Error(
			'no_jet_engine',
			__( 'Jet Engine is not active. Install and activate Jet Engine to use Options Pages abilities.', 'zoltiq-agents' )
		);
	}

	public static function get_pages() {
		if ( ! self::is_available() ) {
			return self::missing_plugin_error();
		}

		$module = jet_engine()->options_pages;
		$items  = array();

		if ( method_exists( $module, 'get_items_for_register' ) ) {
			$items = (array) $module->get_items_for_register();
		} elseif ( isset( $module->data ) && is_object( $module->data ) && method_exists( $module->data, 'get_items' ) ) {
			$items = (array) $module->data->get_items();
		}

		$out = array();
		foreach ( $items as $item ) {
			$arr   = is_object( $item ) ? get_object_vars( $item ) : (array) $item;
			$out[] = array(
				'id'         => isset( $arr['id'] ) ? (int) $arr['id'] : 0,
				'slug'       => isset( $arr['slug'] ) ? (string) $arr['slug'] : '',
				'name'       => isset( $arr['name'] ) ? (string) $arr['name'] : '',
				'option_key' => self::resolve_option_key( $arr ),
				'fields'     => isset( $arr['meta_fields'] ) ? (array) $arr['meta_fields'] : array(),
			);
		}
		return $out;
	}

	public static function get_page( string $slug ) {
		$pages = self::get_pages();
		if ( is_wp_error( $pages ) ) {
			return $pages;
		}
		foreach ( $pages as $page ) {
			if ( $page['slug'] === $slug ) {
				$page['values'] = (array) get_option( $page['option_key'], array() );
				return $page;
			}
		}
		return new \WP_Error( 'page_not_found', __( 'Options page not found.', 'zoltiq-agents' ) );
	}

	public static function update_field( string $slug, string $field, $value ) {
		$page = self::get_page( $slug );
		if ( is_wp_error( $page ) ) {
			return $page;
		}
		$values           = (array) ( $page['values'] ?? array() );
		$values[ $field ] = $value;
		update_option( $page['option_key'], $values );
		return true;
	}

	private static function resolve_option_key( array $arr ): string {
		if ( ! empty( $arr['option_name'] ) ) {
			return (string) $arr['option_name'];
		}
		if ( ! empty( $arr['slug'] ) ) {
			return 'jet-engine-options-page-' . sanitize_key( $arr['slug'] );
		}
		return '';
	}
}
