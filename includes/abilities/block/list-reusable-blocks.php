<?php

namespace Zoltiq\Agents\Includes\Abilities\Block;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class List_Reusable_Blocks extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/list-reusable-blocks',
			'args' => array(
				'label'               => __( 'List Reusable Blocks', 'zoltiq-agents' ),
				'description'         => __( 'Enumerate reusable blocks (post_type=wp_block). Distinct from block patterns: reusable blocks are editable posts backed by the wp_block CPT, whereas patterns are code-registered.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-block',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 200,
							'default' => 50,
						),
						'page'     => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
					),
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
		$per_page = max( 1, min( 200, (int) ( $input['per_page'] ?? 50 ) ) );
		$page     = max( 1, (int) ( $input['page'] ?? 1 ) );

		$query = new \WP_Query(
			array(
				'post_type'      => 'wp_block',
				'post_status'    => array( 'publish', 'private' ),
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$items[] = array(
				'id'         => (int) $post->ID,
				'title'      => sanitize_text_field( (string) $post->post_title ),
				'slug'       => sanitize_title( (string) $post->post_name ),
				'status'     => sanitize_key( (string) $post->post_status ),
				'modified'   => sanitize_text_field( (string) $post->post_modified_gmt ),
				'has_content' => '' !== trim( (string) $post->post_content ),
			);
		}

		return array(
			'success' => true,
			'items'   => $items,
			'total'   => (int) $query->found_posts,
			'page'    => $page,
			'message' => sprintf( __( 'Returned %1$d of %2$d reusable blocks.', 'zoltiq-agents' ), count( $items ), (int) $query->found_posts ),
		);
	}
}
