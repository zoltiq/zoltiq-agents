<?php

namespace Zoltiq\Agents\Includes\Abilities\Content;

use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Revision_Formatter {

	/**
	 * @param WP_Post $rev A WP_Post whose post_type is 'revision'.
	 * @return array<string, mixed>
	 */
	public static function to_array( WP_Post $rev ): array {
		$author_id   = (int) $rev->post_author;
		$author_name = '';
		if ( $author_id > 0 ) {
			$user = get_userdata( $author_id );
			if ( $user ) {
				$author_name = (string) $user->display_name;
			}
		}

		return array(
			'id'           => (int) $rev->ID,
			'parent'       => (int) $rev->post_parent,
			'date'         => mysql_to_rfc3339( $rev->post_date ),
			'date_gmt'     => mysql_to_rfc3339( $rev->post_date_gmt ),
			'modified'     => mysql_to_rfc3339( $rev->post_modified ),
			'modified_gmt' => mysql_to_rfc3339( $rev->post_modified_gmt ),
			'author'       => $author_id,
			'author_name'  => $author_name,
			'title'        => (string) $rev->post_title,
			'content'      => (string) $rev->post_content,
			'excerpt'      => (string) $rev->post_excerpt,
			'is_autosave'  => (bool) wp_is_post_autosave( $rev ),
		);
	}

	public static function paginate( array $all, bool $include_autosaves, int $page, int $per_page ): array {
		if ( ! $include_autosaves ) {
			$all = array_filter(
				$all,
				static function ( $rev ): bool {
					return $rev instanceof WP_Post && ! wp_is_post_autosave( $rev );
				}
			);
		}
		$all   = array_values( $all );
		$total = count( $all );
		$slice = array_slice( $all, ( $page - 1 ) * $per_page, $per_page );
		return array( $slice, $total );
	}
}
