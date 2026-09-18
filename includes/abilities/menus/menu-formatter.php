<?php

namespace Zoltiq\Agents\Includes\Abilities\Menus;

use WP_Error;
use WP_Post;
use WP_Term;

defined( 'ABSPATH' ) || exit;

final class Menu_Formatter {

	public static function menu_to_array( WP_Term $menu ): array {
		return array(
			'id'          => (int) $menu->term_id,
			'description' => (string) $menu->description,
			'name'        => (string) $menu->name,
			'slug'        => (string) $menu->slug,
			'meta'        => self::build_term_meta_map( (int) $menu->term_id ),
			'locations'   => self::get_menu_locations( (int) $menu->term_id ),
			'auto_add'    => self::get_menu_auto_add( (int) $menu->term_id ),
		);
	}

	public static function item_to_array( WP_Post $post ): array {
		$item = wp_setup_nav_menu_item( $post );

		$menus = 0;
		$terms = get_the_terms( $post, 'nav_menu' );
		if ( is_array( $terms ) && ! empty( $terms ) ) {
			$first = array_shift( $terms );
			if ( $first instanceof WP_Term ) {
				$menus = (int) $first->term_id;
			}
		}

		$title = isset( $item->title ) ? (string) $item->title : '';
		return array(
			'id'          => (int) $item->ID,
			'title'       => array(
				'raw'      => $title,
				'rendered' => (string) apply_filters( 'the_title', $title, $item->ID ),
			),
			'status'      => (string) $item->post_status,
			'url'         => (string) ( $item->url ?? '' ),
			'attr_title'  => (string) ( $item->attr_title ?? '' ),
			'description' => (string) ( $item->description ?? '' ),
			'type'        => (string) ( $item->type ?? 'custom' ),
			'type_label'  => (string) ( $item->type_label ?? '' ),
			'object'      => (string) ( $item->object ?? '' ),
			'object_id'   => absint( $item->object_id ?? 0 ),
			'parent'      => (int) ( $item->menu_item_parent ?? 0 ),
			'menu_order'  => (int) ( $item->menu_order ?? 0 ),
			'target'      => (string) ( $item->target ?? '' ),
			'classes'     => array_values( (array) ( $item->classes ?? array() ) ),
			'xfn'         => array_values( array_filter( array_map( 'sanitize_html_class', explode( ' ', (string) ( $item->xfn ?? '' ) ) ) ) ),
			'invalid'     => (bool) ( $item->_invalid ?? false ),
			'meta'        => self::build_post_meta_map( (int) $item->ID ),
			'menus'       => $menus,
		);
	}

	public static function build_term_meta_map( int $term_id ): array {
		if ( $term_id <= 0 || ! function_exists( 'get_registered_meta_keys' ) ) {
			return array();
		}
		$out  = array();
		$keys = get_registered_meta_keys( 'term' );
		foreach ( $keys as $key => $args ) {
			if ( empty( $args['show_in_rest'] ) ) {
				continue;
			}
			$single      = ! empty( $args['single'] );
			$out[ $key ] = get_term_meta( $term_id, $key, $single );
		}
		return $out;
	}

	public static function build_post_meta_map( int $post_id ): array {
		if ( $post_id <= 0 || ! function_exists( 'get_registered_meta_keys' ) ) {
			return array();
		}
		$keys = get_registered_meta_keys( 'post', 'nav_menu_item' );
		if ( empty( $keys ) ) {
			$keys = get_registered_meta_keys( 'post' );
		}
		$out = array();
		foreach ( $keys as $key => $args ) {
			if ( empty( $args['show_in_rest'] ) ) {
				continue;
			}
			$single      = ! empty( $args['single'] );
			$out[ $key ] = get_post_meta( $post_id, $key, $single );
		}
		return $out;
	}

	public static function get_menu_locations( int $menu_id ): array {
		$locations = (array) get_nav_menu_locations();
		$assigned  = array();
		foreach ( $locations as $location => $term_id ) {
			if ( (int) $term_id === $menu_id ) {
				$assigned[] = (string) $location;
			}
		}
		return $assigned;
	}

	public static function get_menu_auto_add( int $menu_id ): array {
		$options  = (array) get_option( 'nav_menu_options', array() );
		$auto_add = isset( $options['auto_add'] ) && is_array( $options['auto_add'] ) ? array_map( 'intval', $options['auto_add'] ) : array();
		return in_array( $menu_id, $auto_add, true ) ? array( $menu_id ) : array();
	}

	public static function set_menu_locations( int $menu_id, array $locations ): void {
		$current = (array) get_nav_menu_locations();
		foreach ( $current as $loc => $term_id ) {
			if ( (int) $term_id === $menu_id && ! in_array( $loc, $locations, true ) ) {
				unset( $current[ $loc ] );
			}
		}
		foreach ( $locations as $loc ) {
			$current[ $loc ] = $menu_id;
		}
		set_theme_mod( 'nav_menu_locations', $current );
	}

	public static function error_from( $result, string $fallback ): array {
		if ( $result instanceof WP_Error ) {
			$message = $result->get_error_message();
			return array(
				'success' => false,
				'message' => '' !== $message ? $message : $fallback,
			);
		}
		return array(
			'success' => false,
			'message' => $fallback,
		);
	}
}
