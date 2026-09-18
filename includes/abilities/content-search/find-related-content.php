<?php

namespace Zoltiq\Agents\Includes\Abilities\ContentSearch;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Find_Related_Content extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/find-related-content',
			'args' => array(
				'label'               => __( 'Find Related Content', 'zoltiq-agents' ),
				'description'         => __( 'Return posts related to a given post via shared taxonomy terms (categories + tags by default). Fallback implementation — no learned relevance model.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-content-search',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_id'  => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 50,
							'default' => 5,
						),
					),
					'required'             => array( 'post_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'related' => array( 'type' => 'array' ),
						'message' => array( 'type' => 'string' ),
					),
					'required'             => array( 'success' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => false,
						'type'   => 'tool',
					),
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		$post_id  = absint( $input['post_id'] ?? 0 );
		$per_page = max( 1, min( 50, (int) ( $input['per_page'] ?? 5 ) ) );
		if ( $post_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'A valid post_id is required.', 'zoltiq-agents' ),
			);
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return array(
				'success' => false,
				'message' => __( 'Post not found.', 'zoltiq-agents' ),
			);
		}
		if ( ! current_user_can( 'read_post', $post_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'You do not have permission to read this post.', 'zoltiq-agents' ),
			);
		}

		$cat_ids = wp_get_post_terms( $post_id, 'category', array( 'fields' => 'ids' ) );
		$tag_ids = wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'ids' ) );
		$cat_ids = is_array( $cat_ids ) ? array_map( 'intval', $cat_ids ) : array();
		$tag_ids = is_array( $tag_ids ) ? array_map( 'intval', $tag_ids ) : array();

		$tax_query = array( 'relation' => 'OR' );
		if ( array() !== $cat_ids ) {
			$tax_query[] = array(
				'taxonomy' => 'category',
				'terms'    => $cat_ids,
			);
		}
		if ( array() !== $tag_ids ) {
			$tax_query[] = array(
				'taxonomy' => 'post_tag',
				'terms'    => $tag_ids,
			);
		}
		if ( 1 === count( $tax_query ) ) {
			return array(
				'success' => true,
				'related' => array(),
				'message' => __( 'Source post has no categories or tags; no relatives to find.', 'zoltiq-agents' ),
			);
		}

		$query = new \WP_Query(
			array(
				'post_type'      => $post->post_type,
				'post_status'    => 'publish',
				'posts_per_page' => $per_page,
				'post__not_in'   => array( $post_id ),
				'tax_query'      => $tax_query, 
			)
		);

		$related = array();
		foreach ( $query->posts as $rel ) {
			if ( ! $rel instanceof \WP_Post ) {
				continue;
			}
			if ( ! current_user_can( 'read_post', (int) $rel->ID ) ) {
				continue;
			}
			$related[] = array(
				'id'     => (int) $rel->ID,
				'title'  => sanitize_text_field( (string) $rel->post_title ),
				'url'    => (string) get_permalink( $rel ),
				'reason' => 'shared-terms',
			);
		}

		return array(
			'success' => true,
			'related' => $related,
			'message' => sprintf( __( 'Found %1$d related post(s) for post #%2$d.', 'zoltiq-agents' ), count( $related ), $post_id ),
		);
	}
}
