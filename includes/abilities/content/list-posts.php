<?php

namespace Zoltiq\Agents\Includes\Abilities\Content;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class List_Posts extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/list-posts',
			'args' => array(
				'label'               => __( 'Get Posts', 'zoltiq-agents' ),
				'description'         => __( 'List posts of any post type via WP_Query — supports search, pagination, status filter, ordering, and a simple meta key/value filter.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-content',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_type'  => array(
							'type'    => 'string',
							'default' => 'post',
						),
						'status'     => array(
							'type'    => 'string',
							'default' => 'any',
						),
						'page'       => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
						'per_page'   => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100,
							'default' => 10,
						),
						'search'     => array( 'type' => 'string' ),
						'orderby'    => array(
							'type'    => 'string',
							'default' => 'date',
						),
						'order'      => array(
							'type'    => 'string',
							'enum'    => array( 'ASC', 'DESC', 'asc', 'desc' ),
							'default' => 'DESC',
						),
						'meta_key'   => array( 'type' => 'string' ),
						'meta_value' => array( 'type' => array( 'string', 'integer', 'number', 'boolean' ) ),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'posts'   => array( 'type' => 'array' ),
						'total'   => array( 'type' => 'integer' ),
						'pages'   => array( 'type' => 'integer' ),
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
		$post_type = sanitize_key( (string) ( $input['post_type'] ?? 'post' ) );
		if ( ! post_type_exists( $post_type ) ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Unknown post type "%s".', 'zoltiq-agents' ), $post_type ),
			);
		}

		$args = array(
			'post_type'      => $post_type,
			'post_status'    => sanitize_text_field( (string) ( $input['status'] ?? 'any' ) ),
			'paged'          => max( 1, (int) ( $input['page'] ?? 1 ) ),
			'posts_per_page' => min( 100, max( 1, (int) ( $input['per_page'] ?? 10 ) ) ),
			'orderby'        => sanitize_key( (string) ( $input['orderby'] ?? 'date' ) ),
			'order'          => strtoupper( (string) ( $input['order'] ?? 'DESC' ) ) === 'ASC' ? 'ASC' : 'DESC',
		);

		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( (string) $input['search'] );
		}
		if ( ! empty( $input['meta_key'] ) ) {
			$args['meta_key'] = sanitize_text_field( (string) $input['meta_key'] );
			if ( isset( $input['meta_value'] ) ) {
				$args['meta_value'] = is_scalar( $input['meta_value'] ) ? (string) $input['meta_value'] : '';
			}
		}

		$query = new \WP_Query( $args );
		$posts = array();
		foreach ( $query->posts as $p ) {
			$posts[] = (array) $p;
		}

		return array(
			'success' => true,
			'posts'   => $posts,
			'total'   => (int) $query->found_posts,
			'pages'   => (int) $query->max_num_pages,
		);
	}
}
