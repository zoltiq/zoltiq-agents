<?php

namespace Zoltiq\Agents\Includes\Abilities\Utilities\Pattern;

defined( 'ABSPATH' ) || exit;

final class Pattern_Db {

	public static function post_type(): string {
		$candidate = (string) apply_filters( 'zoltiq_agents_pattern_post_type', 'wp_block' );
		if ( '' === $candidate ) {
			return 'wp_block';
		}
		return $candidate;
	}

	public static function find_by_slug( string $slug ): ?\WP_Post {
		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			return null;
		}
		$posts = get_posts(
			array(
				'post_type'      => self::post_type(),
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'name'           => $slug,
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			)
		);
		return ! empty( $posts ) ? $posts[0] : null;
	}

	public static function list_all( int $limit = 200 ): array {
		return (array) get_posts(
			array(
				'post_type'      => self::post_type(),
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page' => max( 1, min( 500, $limit ) ),
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);
	}

	public static function create( array $data ) {
		$slug = sanitize_title( (string) ( $data['slug'] ?? '' ) );
		if ( '' === $slug ) {
			return new \WP_Error( 'invalid_slug', __( 'Pattern slug is required.', 'zoltiq-agents' ) );
		}
		if ( self::find_by_slug( $slug ) ) {
			return new \WP_Error( 'slug_conflict', __( 'A database pattern with this slug already exists.', 'zoltiq-agents' ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => self::post_type(),
				'post_status'  => self::sanitize_status( $data['status'] ?? 'publish' ),
				'post_title'   => sanitize_text_field( (string) ( $data['title'] ?? $slug ) ),
				'post_name'    => $slug,
				'post_excerpt' => sanitize_text_field( (string) ( $data['description'] ?? '' ) ),
				'post_content' => (string) ( $data['content'] ?? '' ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		self::apply_meta( (int) $post_id, $data );

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
			$update['post_content'] = (string) $data['content'];
		}
		if ( array_key_exists( 'status', $data ) ) {
			$update['post_status'] = self::sanitize_status( (string) $data['status'] );
		}

		if ( array_key_exists( 'new_slug', $data ) && '' !== $data['new_slug'] ) {
			$new_slug = sanitize_title( (string) $data['new_slug'] );
			if ( '' === $new_slug ) {
				return new \WP_Error( 'invalid_new_slug', __( 'new_slug is invalid.', 'zoltiq-agents' ) );
			}
			if ( $new_slug !== $post->post_name && self::find_by_slug( $new_slug ) ) {
				return new \WP_Error( 'new_slug_conflict', __( 'A database pattern with new_slug already exists.', 'zoltiq-agents' ) );
			}
			$update['post_name'] = $new_slug;
		}

		if ( count( $update ) > 1 ) {
			$result = wp_update_post( $update, true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		self::apply_meta( (int) $post->ID, $data );

		return (int) $post->ID;
	}

	public static function delete( \WP_Post $post ): bool {
		$result = wp_delete_post( (int) $post->ID, true );
		return false !== $result && null !== $result;
	}

	public static function to_row( \WP_Post $post ): array {
		return array(
			'source'      => 'db',
			'slug'        => (string) $post->post_name,
			'post_id'     => (int) $post->ID,
			'post_type'   => (string) $post->post_type,
			'status'      => (string) $post->post_status,
			'title'       => (string) $post->post_title,
			'description' => (string) $post->post_excerpt,
			'content'     => (string) $post->post_content,
			'modified'    => (string) $post->post_modified_gmt,
			'sync_status' => (string) get_post_meta( $post->ID, 'wp_pattern_sync_status', true ),
		);
	}

	private static function apply_meta( int $post_id, array $data ): void {
		if ( array_key_exists( 'sync_status', $data ) && '' !== $data['sync_status'] ) {
			$value = sanitize_key( (string) $data['sync_status'] );
			if ( in_array( $value, array( 'synced', 'unsynced' ), true ) ) {
				update_post_meta( $post_id, 'wp_pattern_sync_status', $value );
			}
		}
	}

	private static function sanitize_status( string $status ): string {
		$allowed = array( 'publish', 'draft', 'private', 'pending' );
		return in_array( $status, $allowed, true ) ? $status : 'publish';
	}
}
