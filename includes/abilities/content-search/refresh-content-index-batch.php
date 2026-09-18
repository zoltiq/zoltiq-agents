<?php

namespace Zoltiq\Agents\Includes\Abilities\ContentSearch;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Refresh_Content_Index_Batch extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/refresh-content-index-batch',
			'args' => array(
				'label'               => __( 'Refresh Content Index Batch', 'zoltiq-agents' ),
				'description'         => __( 'Refresh WP core search-state for a batch of posts by calling clean_post_cache() on each. No new index table — this ability relies on WP\'s built-in `s=` search handler as the fallback index. Cap: 100 post_ids per call.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-content-search',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_ids' => array(
							'type'     => 'array',
							'items'    => array( 'type' => 'integer' ),
							'maxItems' => 100,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'indexed' => array( 'type' => 'integer' ),
						'skipped' => array( 'type' => 'integer' ),
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
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		$post_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $input['post_ids'] ?? array() ) ) ) ) );
		if ( array() === $post_ids ) {
			return array(
				'success' => true,
				'indexed' => 0,
				'skipped' => 0,
				'message' => __( 'No post_ids supplied — nothing to do.', 'zoltiq-agents' ),
			);
		}
		if ( count( $post_ids ) > 100 ) {
			return array(
				'success' => false,
				'message' => __( 'Too many post_ids (max 100 per call).', 'zoltiq-agents' ),
			);
		}
		$indexed = 0;
		$skipped = 0;
		foreach ( $post_ids as $id ) {
			$id = (int) $id;
			if ( $id <= 0 || ! get_post( $id ) ) {
				++$skipped;
				continue;
			}
			clean_post_cache( $id );
			++$indexed;
		}
		return array(
			'success' => true,
			'indexed' => $indexed,
			'skipped' => $skipped,
			'message' => sprintf( __( 'Refreshed %1$d post caches; skipped %2$d.', 'zoltiq-agents' ), $indexed, $skipped ),
		);
	}
}
