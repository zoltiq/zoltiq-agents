<?php

namespace Zoltiq\Agents\Includes\Abilities\Media;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class List_Media extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/list-media',
			'args' => array(
				'label'               => __( 'List Media', 'zoltiq-agents' ),
				'description'         => __( 'List media items via GET /wp/v2/media. Supports search, pagination, and a mime_type filter.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-media',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'page'      => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
						'per_page'  => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100,
							'default' => 10,
						),
						'search'    => array( 'type' => 'string' ),
						'mime_type' => array( 'type' => 'string' ),
						'parent'    => array( 'type' => 'integer' ),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'media'   => array( 'type' => 'array' ),
						'total'   => array( 'type' => 'integer' ),
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
		$page     = max( 1, (int) ( $input['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $input['per_page'] ?? 10 ) ) );

		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'no_found_rows'  => false,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( (string) $input['search'] );
		}
		if ( ! empty( $input['mime_type'] ) ) {
			$args['post_mime_type'] = sanitize_mime_type( (string) $input['mime_type'] );
		}
		if ( isset( $input['parent'] ) ) {
			$args['post_parent'] = (int) $input['parent'];
		}

		$query = new \WP_Query( $args );

		$formatted = array_map(
			array( Media_Formatter::class, 'to_array' ),
			array_values(
				array_filter(
					$query->posts,
					static function ( $p ): bool {
						return $p instanceof \WP_Post;
					}
				)
			)
		);

		return array(
			'success' => true,
			'media'   => $formatted,
			'total'   => (int) $query->found_posts,
		);
	}
}
