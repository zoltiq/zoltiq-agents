<?php

namespace Zoltiq\Agents\Includes\Abilities\Comments;

use WP_Comment;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Comment_Formatter {

	public static function to_array( WP_Comment $comment ): array {
		$comment_id = (int) $comment->comment_ID;

		return array(
			'id'                 => $comment_id,
			'post'               => (int) $comment->comment_post_ID,
			'parent'             => (int) $comment->comment_parent,
			'author'             => (int) $comment->user_id,
			'author_name'        => (string) $comment->comment_author,
			'author_email'       => (string) $comment->comment_author_email,
			'author_url'         => (string) $comment->comment_author_url,
			'author_ip'          => (string) $comment->comment_author_IP,
			'author_user_agent'  => (string) $comment->comment_agent,
			'date'               => mysql_to_rfc3339( $comment->comment_date ),
			'date_gmt'           => mysql_to_rfc3339( $comment->comment_date_gmt ),
			'content'            => array(
				'rendered' => (string) apply_filters( 'comment_text', $comment->comment_content, $comment, array() ),
				'raw'      => (string) $comment->comment_content,
			),
			'link'               => (string) get_comment_link( $comment ),
			'status'             => self::map_status( (string) $comment->comment_approved ),
			'type'               => (string) get_comment_type( $comment_id ),
			'author_avatar_urls' => function_exists( 'rest_get_avatar_urls' ) ? rest_get_avatar_urls( $comment ) : array(),
			'meta'               => self::build_meta_map( $comment_id ),
		);
	}

	public static function build_meta_map( int $comment_id ): array {
		if ( $comment_id <= 0 || ! function_exists( 'get_registered_meta_keys' ) ) {
			return array();
		}

		$out  = array();
		$keys = get_registered_meta_keys( 'comment' );
		foreach ( $keys as $key => $args ) {
			if ( empty( $args['show_in_rest'] ) ) {
				continue;
			}
			$single      = ! empty( $args['single'] );
			$out[ $key ] = get_comment_meta( $comment_id, $key, $single );
		}
		return $out;
	}

	public static function is_meta_key_writable( string $key ): bool {
		if ( '' === $key || ! function_exists( 'get_registered_meta_keys' ) ) {
			return false;
		}
		$keys = get_registered_meta_keys( 'comment' );
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

	private static function map_status( string $comment_approved ): string {
		switch ( $comment_approved ) {
			case 'hold':
			case '0':
				return 'hold';
			case 'approve':
			case '1':
				return 'approved';
			default:
				return $comment_approved;
		}
	}
}
