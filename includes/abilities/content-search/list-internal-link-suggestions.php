<?php

namespace Zoltiq\Agents\Includes\Abilities\ContentSearch;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class List_Internal_Link_Suggestions extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/list-internal-link-suggestions',
			'args' => array(
				'label'               => __( 'List Internal Link Suggestions', 'zoltiq-agents' ),
				'description'         => __( 'List all suggestions in the option-backed store, optionally filtered by post_id and/or status (pending / approved / rejected / applied).', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-content-search',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_id' => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'status'  => array(
							'type' => 'string',
							'enum' => array( 'pending', 'approved', 'rejected', 'applied' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'items'   => array( 'type' => 'array' ),
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
		$post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : null;
		if ( null !== $post_id && $post_id <= 0 ) {
			$post_id = null;
		}
		$status = isset( $input['status'] ) ? sanitize_key( (string) $input['status'] ) : null;
		if ( null !== $status && ! in_array( $status, array( 'pending', 'approved', 'rejected', 'applied' ), true ) ) {
			$status = null;
		}
		$items  = Suggestion_Store::list( $post_id, $status );
		return array(
			'success' => true,
			'items'   => $items,
			'message' => sprintf( __( '%d suggestion(s) matched the filter.', 'zoltiq-agents' ), count( $items ) ),
		);
	}
}
