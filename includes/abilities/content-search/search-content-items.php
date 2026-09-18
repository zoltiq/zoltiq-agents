<?php

namespace Zoltiq\Agents\Includes\Abilities\ContentSearch;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Search_Content_Items extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/search-content-items',
			'args' => array(
				'label'               => __( 'Search Content Items', 'zoltiq-agents' ),
				'description'         => __( 'Search post-type items by keyword. Backed by WP_Query `s=` (LIKE-based title + content search). Score is a simple 0/1 relevance stub — WP core\'s search does not produce ranked scores natively.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-content-search',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'query'      => array(
							'type'      => 'string',
							'minLength' => 1,
						),
						'per_page'   => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100,
							'default' => 20,
						),
						'page'       => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
						'post_types' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
					'required'             => array( 'query' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'items'   => array( 'type' => 'array' ),
						'total'   => array( 'type' => 'integer' ),
						'page'    => array( 'type' => 'integer' ),
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
		$query    = sanitize_text_field( (string) ( $input['query'] ?? '' ) );
		$per_page = max( 1, min( 100, (int) ( $input['per_page'] ?? 20 ) ) );
		$page     = max( 1, (int) ( $input['page'] ?? 1 ) );

		$blocked      = array( 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation' );
		$requested    = array_values( array_filter( array_map( 'sanitize_key', (array) ( $input['post_types'] ?? array( 'post', 'page' ) ) ) ) );
		$safe_types   = array();
		foreach ( $requested as $pt ) {
			if ( in_array( $pt, $blocked, true ) ) {
				continue;
			}
			$pt_obj = get_post_type_object( $pt );
			if ( $pt_obj instanceof \WP_Post_Type && ( (bool) $pt_obj->public || (bool) $pt_obj->show_in_rest ) ) {
				$safe_types[] = $pt;
			}
		}
		if ( array() === $safe_types ) {
			$safe_types = array( 'post', 'page' );
		}

		if ( '' === $query ) {
			return array(
				'success' => false,
				'message' => __( 'query is required.', 'zoltiq-agents' ),
			);
		}

		$wp_query = new \WP_Query(
			array(
				's'              => $query,
				'post_type'      => $safe_types,
				'post_status'    => 'publish',
				'posts_per_page' => $per_page,
				'paged'          => $page,
			)
		);

		$items = array();
		foreach ( $wp_query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
	
			if ( ! current_user_can( 'read_post', (int) $post->ID ) ) {
				continue;
			}
			$items[] = array(
				'id'      => (int) $post->ID,
				'title'   => sanitize_text_field( (string) $post->post_title ),
				'url'     => (string) get_permalink( $post ),
				'excerpt' => wp_trim_words( wp_strip_all_tags( (string) $post->post_content ), 40 ),
				'score'   => 1.0,
			);
		}

		return array(
			'success' => true,
			'items'   => $items,
			'total'   => (int) $wp_query->found_posts,
			'page'    => $page,
			'message' => sprintf( __( '%1$d match(es) for "%2$s".', 'zoltiq-agents' ), (int) $wp_query->found_posts, $query ),
		);
	}
}
