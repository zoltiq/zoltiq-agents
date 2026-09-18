<?php

namespace Zoltiq\Agents\Includes\Abilities\Utilities\Template;

defined( 'ABSPATH' ) || exit;

final class Template_Db {

	public const POST_TYPE = 'wp_template';
	public const THEME_TAX = 'wp_theme';

	public static function valid_content( string $content ): bool {
		return '' !== trim( $content );
	}

	public static function find_by_slug( string $slug, string $theme = '' ): ?\WP_Post {
		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			return null;
		}

		$args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
			'name'           => $slug,
			'posts_per_page' => 1,
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

		$posts = get_posts( $args );
		return ! empty( $posts ) ? $posts[0] : null;
	}

	public static function list_all( string $theme = '', int $limit = 200 ): array {
		$args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
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

		return (array) get_posts( $args );
	}

	public static function create( array $data ) {
		$slug = sanitize_title( (string) ( $data['slug'] ?? '' ) );
		if ( '' === $slug ) {
			return new \WP_Error( 'invalid_slug', __( 'Template slug is required.', 'zoltiq-agents' ) );
		}

		$theme = '' !== ( $data['theme'] ?? '' )
			? sanitize_key( (string) $data['theme'] )
			: get_stylesheet();

		$content = (string) ( $data['content'] ?? '' );
		if ( ! self::valid_content( $content ) ) {
			return new \WP_Error( 'invalid_content', __( 'Template content cannot be empty.', 'zoltiq-agents' ) );
		}

		if ( self::find_by_slug( $slug, $theme ) ) {
			return new \WP_Error( 'slug_conflict', __( 'A database template with this slug already exists for this theme.', 'zoltiq-agents' ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => self::sanitize_status( $data['status'] ?? 'publish' ),
				'post_title'   => sanitize_text_field( (string) ( $data['title'] ?? $slug ) ),
				'post_name'    => $slug,
				'post_excerpt' => sanitize_text_field( (string) ( $data['description'] ?? '' ) ),
				'post_content' => $content,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		wp_set_object_terms( (int) $post_id, $theme, self::THEME_TAX );

		return (int) $post_id;
	}

	public static function update( \WP_Post $post, array $data ) {
		$update = array( 'ID' => (int) $post->ID );

		if ( array_key_exists( 'title', $data ) ) {
			$update['post_title'] = sanitize_text_field( (string) $data['title'] );
		}
		if ( array_key_exists( 'description', $data ) ) {
			$update['post_excerpt'] = sanitize_text_field( (string) $data['description'] );
		}
		if ( array_key_exists( 'content', $data ) ) {
			$content = (string) $data['content'];
			if ( ! self::valid_content( $content ) ) {
				return new \WP_Error( 'invalid_content', __( 'Template content cannot be empty.', 'zoltiq-agents' ) );
			}
			$update['post_content'] = $content;
		}
		if ( array_key_exists( 'status', $data ) ) {
			$update['post_status'] = self::sanitize_status( (string) $data['status'] );
		}

		if ( array_key_exists( 'new_slug', $data ) && '' !== $data['new_slug'] ) {
			$new_slug = sanitize_title( (string) $data['new_slug'] );
			if ( '' === $new_slug ) {
				return new \WP_Error( 'invalid_new_slug', __( 'new_slug is invalid.', 'zoltiq-agents' ) );
			}
			$theme = self::get_post_theme( $post );
			if ( $new_slug !== $post->post_name && self::find_by_slug( $new_slug, $theme ) ) {
				return new \WP_Error( 'new_slug_conflict', __( 'A database template with new_slug already exists for this theme.', 'zoltiq-agents' ) );
			}
			$update['post_name'] = $new_slug;
		}

		if ( count( $update ) > 1 ) {
			$result = wp_update_post( $update, true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		if ( array_key_exists( 'theme', $data ) && '' !== $data['theme'] ) {
			wp_set_object_terms( (int) $post->ID, sanitize_key( (string) $data['theme'] ), self::THEME_TAX );
		}

		return (int) $post->ID;
	}


	public static function delete( \WP_Post $post ): bool {
		$result = wp_delete_post( (int) $post->ID, true );
		return false !== $result && null !== $result;
	}

	public static function to_row( \WP_Post $post ): array {
		$theme = self::get_post_theme( $post );
		$slug  = (string) $post->post_name;

		return array(
			'source'      => 'db',
			'slug'        => $slug,
			'full_slug'   => '' !== $theme ? $theme . '//' . $slug : $slug,
			'post_id'     => (int) $post->ID,
			'status'      => (string) $post->post_status,
			'title'       => (string) $post->post_title,
			'description' => (string) $post->post_excerpt,
			'content'     => (string) $post->post_content,
			'theme'       => $theme,
			'modified'    => (string) $post->post_modified_gmt,
		);
	}

	public static function get_post_theme( \WP_Post $post ): string {
		$terms = wp_get_object_terms( (int) $post->ID, self::THEME_TAX, array( 'fields' => 'names' ) );
		return ! is_wp_error( $terms ) && ! empty( $terms ) ? (string) $terms[0] : '';
	}

	private static function sanitize_status( $status ): string {
		$allowed = array( 'publish', 'draft', 'private', 'pending' );
		$status  = is_string( $status ) ? $status : 'publish';
		return in_array( $status, $allowed, true ) ? $status : 'publish';
	}
}
