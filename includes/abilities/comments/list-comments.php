<?php

namespace Zoltiq\Agents\Includes\Abilities\Comments;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class List_Comments extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/list-comments',
			'args' => array(
				'label'               => __( 'List Comments', 'zoltiq-agents' ),
				'description'         => __( 'List comments via GET /wp/v2/comments. Supports search, post filter, status filter, and pagination.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-comments',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'page'     => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100,
							'default' => 10,
						),
						'search'   => array( 'type' => 'string' ),
						'post'     => array( 'type' => 'integer' ),
						'status'   => array( 'type' => 'string' ),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'  => array( 'type' => 'boolean' ),
						'comments' => array( 'type' => 'array' ),
						'total'    => array( 'type' => 'integer' ),
						'message'  => array( 'type' => 'string' ),
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

		$filters = array();
		if ( ! empty( $input['search'] ) ) {
			$filters['search'] = sanitize_text_field( (string) $input['search'] );
		}
		if ( ! empty( $input['post'] ) ) {
			$filters['post_id'] = (int) $input['post'];
		}
		if ( ! empty( $input['status'] ) ) {
			$filters['status'] = self::map_status_filter( (string) $input['status'] );
		}

		$items = get_comments(
			array_merge(
				$filters,
				array(
					'number'  => $per_page,
					'offset'  => ( $page - 1 ) * $per_page,
					'orderby' => 'comment_date_gmt',
					'order'   => 'DESC',
				)
			)
		);

		$total = (int) get_comments( array_merge( $filters, array( 'count' => true ) ) );

		return array(
			'success'  => true,
			'comments' => array_values( array_map( array( Comment_Formatter::class, 'to_array' ), $items ) ),
			'total'    => $total,
		);
	}

	private static function map_status_filter( string $status ): string {
		$normalized = sanitize_key( $status );
		if ( 'approved' === $normalized ) {
			return 'approve';
		}
		return $normalized;
	}
}
