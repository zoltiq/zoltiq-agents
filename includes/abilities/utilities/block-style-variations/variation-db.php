<?php

namespace Zoltiq\Agents\Includes\Abilities\Utilities\Block_Style_Variations;

use Zoltiq\Agents\Includes\Abilities\Utilities\Global_Styles\Global_Styles_Db;

defined( 'ABSPATH' ) || exit;

final class Variation_Db {

	public const POST_TYPE = 'wp_global_styles';
	public const THEME_TAX = 'wp_theme';

	public const SECTIONS = array( 'colors', 'typography', 'spacing', 'layout', 'blockStyles' );

	public const SECTION_PATHS = array(
		'colors'      => array( array( 'settings', 'color' ), array( 'styles', 'color' ) ),
		'typography'  => array( array( 'settings', 'typography' ), array( 'styles', 'typography' ) ),
		'spacing'     => array( array( 'settings', 'spacing' ), array( 'styles', 'spacing' ) ),
		'layout'      => array( array( 'settings', 'layout' ) ),
		'blockStyles' => array( array( 'settings', 'blocks' ), array( 'styles', 'blocks' ) ),
	);

	public static function valid_sections(): array {
		return self::SECTIONS;
	}

	public static function valid_section( string $section ): bool {
		return in_array( self::normalize_section( $section ), self::SECTIONS, true );
	}

	public static function normalize_section( string $section ): string {
		$section = strtolower( trim( $section ) );
		$section = str_replace( array( '_', '-', ' ' ), '', $section );
		$map     = array(
			'colors'      => 'colors',
			'typography'  => 'typography',
			'spacing'     => 'spacing',
			'layout'      => 'layout',
			'blockstyles' => 'blockStyles',
		);
		return $map[ $section ] ?? $section;
	}


	public static function is_main_global_styles( \WP_Post $post ): bool {
		return 0 === strpos( (string) $post->post_name, 'wp-global-styles-' );
	}

	public static function find_by_slug( string $slug, string $theme = '' ): ?\WP_Post {
		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			return null;
		}
		$theme = '' !== $theme ? sanitize_key( $theme ) : (string) get_stylesheet();

		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'name'           => $slug,
				'posts_per_page' => 5,
				'no_found_rows'  => true,
				'tax_query'      => array( 
					array(
						'taxonomy' => self::THEME_TAX,
						'field'    => 'name',
						'terms'    => $theme,
					),
				),
			)
		);

		foreach ( $posts as $post ) {
			if ( ! self::is_main_global_styles( $post ) ) {
				return $post;
			}
		}
		return null;
	}


	public static function list_all( string $theme = '', int $limit = 200 ): array {
		$args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'posts_per_page' => max( 1, min( 500, $limit ) ),
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		);
		if ( '' !== $theme ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => self::THEME_TAX,
					'field'    => 'name',
					'terms'    => sanitize_key( $theme ),
				),
			);
		}

		$out = array();
		foreach ( (array) get_posts( $args ) as $post ) {
			if ( $post instanceof \WP_Post && ! self::is_main_global_styles( $post ) ) {
				$out[] = $post;
			}
		}
		return $out;
	}

	public static function create( string $theme, string $slug, array $data, array $extras = array() ) {
		$theme = '' !== $theme ? sanitize_key( $theme ) : (string) get_stylesheet();
		$slug  = sanitize_title( $slug );

		if ( '' === $theme ) {
			return new \WP_Error( 'invalid_theme', __( 'Theme is required.', 'zoltiq-agents' ) );
		}
		if ( '' === $slug ) {
			return new \WP_Error( 'invalid_slug', __( 'Variation slug is required.', 'zoltiq-agents' ) );
		}
		if ( 0 === strpos( $slug, 'wp-global-styles-' ) ) {
			return new \WP_Error( 'reserved_slug', __( 'Variation slug must not start with "wp-global-styles-" — that prefix is reserved for the main Global Styles record.', 'zoltiq-agents' ) );
		}

		$valid = Global_Styles_Db::validate_data( $data );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$valid = Global_Styles_Db::validate_block_styles( $data );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( self::find_by_slug( $slug, $theme ) ) {
			return new \WP_Error( 'slug_conflict', __( 'A variation with this slug already exists for this theme. Use update instead.', 'zoltiq-agents' ) );
		}

		$json = Global_Styles_Db::encode_json( $data );
		if ( is_wp_error( $json ) ) {
			return $json;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => sanitize_text_field( (string) ( $extras['title'] ?? ucwords( str_replace( array( '-', '_' ), ' ', $slug ) ) ) ),
				'post_name'    => $slug,
				'post_excerpt' => sanitize_text_field( (string) ( $extras['description'] ?? '' ) ),
				'post_content' => $json,
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		wp_set_object_terms( (int) $post_id, $theme, self::THEME_TAX );
		return (int) $post_id;
	}

	public static function update( \WP_Post $post, array $data, bool $merge = true, array $extras = array() ) {
		$existing = self::decode_content( $post );
		$new      = $merge ? Global_Styles_Db::deep_merge( $existing, $data ) : $data;

		$valid = Global_Styles_Db::validate_data( $new );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$valid = Global_Styles_Db::validate_block_styles( $new );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$json = Global_Styles_Db::encode_json( $new );
		if ( is_wp_error( $json ) ) {
			return $json;
		}

		$update = array(
			'ID'           => (int) $post->ID,
			'post_content' => $json,
		);
		if ( array_key_exists( 'title', $extras ) ) {
			$update['post_title'] = sanitize_text_field( (string) $extras['title'] );
		}
		if ( array_key_exists( 'description', $extras ) ) {
			$update['post_excerpt'] = sanitize_text_field( (string) $extras['description'] );
		}
		if ( array_key_exists( 'new_slug', $extras ) && '' !== $extras['new_slug'] ) {
			$new_slug = sanitize_title( (string) $extras['new_slug'] );
			if ( '' === $new_slug ) {
				return new \WP_Error( 'invalid_new_slug', __( 'new_slug is invalid.', 'zoltiq-agents' ) );
			}
			$theme = self::get_post_theme( $post );
			if ( $new_slug !== $post->post_name && self::find_by_slug( $new_slug, $theme ) ) {
				return new \WP_Error( 'new_slug_conflict', __( 'A variation with new_slug already exists for this theme.', 'zoltiq-agents' ) );
			}
			$update['post_name'] = $new_slug;
		}

		$result = wp_update_post( $update, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return (int) $result;
	}

	public static function update_section( \WP_Post $post, string $section, array $section_data ) {
		$section = self::normalize_section( $section );
		if ( ! in_array( $section, self::SECTIONS, true ) ) {
			return new \WP_Error(
				'invalid_section',
				sprintf( __( 'Section must be one of: %s.', 'zoltiq-agents' ), implode( ', ', self::SECTIONS ) )
			);
		}

		$existing = self::decode_content( $post );
		foreach ( self::SECTION_PATHS[ $section ] as $path ) {
			$value = Global_Styles_Db::path_get( $section_data, $path );
			if ( null !== $value ) {
				Global_Styles_Db::path_set( $existing, $path, $value );
			}
		}
		return self::update( $post, $existing, false );
	}

	public static function delete_section( \WP_Post $post, string $section ) {
		$section = self::normalize_section( $section );
		if ( ! in_array( $section, self::SECTIONS, true ) ) {
			return new \WP_Error(
				'invalid_section',
				sprintf( __( 'Section must be one of: %s.', 'zoltiq-agents' ), implode( ', ', self::SECTIONS ) )
			);
		}

		$existing = self::decode_content( $post );
		foreach ( self::SECTION_PATHS[ $section ] as $path ) {
			Global_Styles_Db::path_delete( $existing, $path );
		}
		return self::update( $post, $existing, false );
	}

	public static function delete( \WP_Post $post ): bool {
		$result = wp_delete_post( (int) $post->ID, true );
		return false !== $result && null !== $result;
	}


	public static function decode_content( \WP_Post $post ): array {
		$content = trim( (string) $post->post_content );
		if ( '' === $content ) {
			return array();
		}
		$decoded = json_decode( $content, true );
		return is_array( $decoded ) ? $decoded : array();
	}


	public static function get_post_theme( \WP_Post $post ): string {
		$terms = wp_get_object_terms( (int) $post->ID, self::THEME_TAX, array( 'fields' => 'names' ) );
		return ! is_wp_error( $terms ) && ! empty( $terms ) ? (string) $terms[0] : '';
	}


	public static function get_section( \WP_Post $post, string $section ): array {
		$section = self::normalize_section( $section );
		$data    = self::decode_content( $post );
		$out     = array();
		foreach ( ( self::SECTION_PATHS[ $section ] ?? array() ) as $path ) {
			$value = Global_Styles_Db::path_get( $data, $path );
			if ( null !== $value ) {
				Global_Styles_Db::path_set( $out, $path, $value );
			}
		}
		return $out;
	}


	public static function get_customized_sections( \WP_Post $post ): array {
		$data = self::decode_content( $post );
		if ( empty( $data ) ) {
			return array();
		}
		$customized = array();
		foreach ( self::SECTION_PATHS as $section => $paths ) {
			foreach ( $paths as $path ) {
				$value = Global_Styles_Db::path_get( $data, $path );
				if ( null === $value ) {
					continue;
				}
				if ( is_array( $value ) && empty( $value ) ) {
					continue;
				}
				$customized[] = $section;
				break;
			}
		}
		return $customized;
	}


	public static function to_row( \WP_Post $post, bool $include_content = false ): array {
		$theme = self::get_post_theme( $post );
		$row   = array(
			'source'              => 'db',
			'post_id'             => (int) $post->ID,
			'slug'                => (string) $post->post_name,
			'title'               => (string) $post->post_title,
			'description'         => (string) $post->post_excerpt,
			'theme'               => $theme,
			'is_active_theme'     => $theme === (string) get_stylesheet(),
			'is_active_variation' => self::is_active_variation( $post ),
			'customized_sections' => self::get_customized_sections( $post ),
			'modified'            => (string) $post->post_modified_gmt,
		);

		if ( $include_content ) {
			$row['data'] = self::decode_content( $post );
		}
		return $row;
	}


	public static function is_active_variation( \WP_Post $post ): bool {
		$theme = self::get_post_theme( $post );
		if ( '' === $theme ) {
			return false;
		}
		$main_slug = 'wp-global-styles-' . $theme;
		$mains     = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'name'           => $main_slug,
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			)
		);
		if ( empty( $mains ) ) {
			return false;
		}
		$active = (string) get_post_meta( (int) $mains[0]->ID, '_active_style_variation', true );
		return $active === (string) $post->post_name;
	}
}
