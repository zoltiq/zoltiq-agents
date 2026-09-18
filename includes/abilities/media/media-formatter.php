<?php

namespace Zoltiq\Agents\Includes\Abilities\Media;

use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Media_Formatter {

	public static function to_array( WP_Post $post ): array {
		$id = (int) $post->ID;

		$caption_raw      = (string) $post->post_excerpt;
		$caption_rendered = apply_filters( 'the_excerpt', apply_filters( 'get_the_excerpt', $caption_raw, $post ) );

		$description_raw      = (string) $post->post_content;
		$description_rendered = apply_filters( 'the_content', $description_raw );

		$title_raw      = (string) $post->post_title;
		$title_rendered = get_the_title( $post );

		$metadata = wp_get_attachment_metadata( $id );

		return array(
			'id'                  => $id,
			'date'                => mysql_to_rfc3339( $post->post_date ),
			'date_gmt'            => mysql_to_rfc3339( $post->post_date_gmt ),
			'guid'                => array(
				'rendered' => (string) apply_filters( 'get_the_guid', $post->guid, $id ),
				'raw'      => (string) $post->guid,
			),
			'modified'            => mysql_to_rfc3339( $post->post_modified ),
			'modified_gmt'        => mysql_to_rfc3339( $post->post_modified_gmt ), 
			'slug'                => (string) $post->post_name,
			'status'              => (string) $post->post_status,
			'type'                => 'attachment',
			'link'                => (string) get_permalink( $post ),
			'title'               => array(
				'raw'      => $title_raw,
				'rendered' => (string) $title_rendered,
			),
			'author'              => (int) $post->post_author,
			'comment_status'      => (string) $post->comment_status,
			'ping_status'         => (string) $post->ping_status,
			'meta'                => self::build_meta_map( $id ),
			'alt_text'            => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
			'caption'             => array(
				'raw'      => $caption_raw,
				'rendered' => (string) $caption_rendered,
			),
			'description'         => array(
				'raw'      => $description_raw,
				'rendered' => (string) $description_rendered,
			),
			'media_type'          => wp_attachment_is_image( $post ) ? 'image' : 'file',
			'mime_type'           => (string) $post->post_mime_type,
			'media_details'       => is_array( $metadata ) ? $metadata : array(),
			'post'                => (int) $post->post_parent,
			'source_url'          => (string) wp_get_attachment_url( $id ),
			'missing_image_sizes' => array(),
		);
	}

	public static function build_meta_map( int $post_id ): array {
		if ( $post_id <= 0 || ! function_exists( 'get_registered_meta_keys' ) ) {
			return array();
		}

		$keys = get_registered_meta_keys( 'post', 'attachment' );
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

	public static function is_meta_key_writable( string $key ): bool {
		if ( '' === $key || ! function_exists( 'get_registered_meta_keys' ) ) {
			return false;
		}
		$keys = get_registered_meta_keys( 'post', 'attachment' );
		if ( isset( $keys[ $key ] ) && ! empty( $keys[ $key ]['show_in_rest'] ) ) {
			return true;
		}
		$keys = get_registered_meta_keys( 'post' );
		return isset( $keys[ $key ] ) && ! empty( $keys[ $key ]['show_in_rest'] );
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
