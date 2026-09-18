<?php

namespace Zoltiq\Agents\Includes\Abilities\Content;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Get_Cpt_Item extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/get-cpt-item',
			'args' => array(
				'label'               => __( 'Get CPT Item', 'zoltiq-agents' ),
				'description'         => __( 'Fetch a custom post type record by ID. post_type is required and must match the post.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-content',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_type' => array( 'type' => 'string' ),
						'id'        => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
					),
					'required'             => array( 'post_type', 'id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'item'    => array( 'type' => 'object' ),
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
		$post_type = sanitize_key( (string) ( $input['post_type'] ?? '' ) );
		$id        = (int) ( $input['id'] ?? 0 );

		if ( '' === $post_type || ! post_type_exists( $post_type ) ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Unknown post type "%s".', 'zoltiq-agents' ), $post_type ),
			);
		}

		$post = $id > 0 ? get_post( $id, ARRAY_A ) : null;
		if ( ! $post || $post['post_type'] !== $post_type ) {
			return array(
				'success' => false,
				'message' => __( 'Item not found.', 'zoltiq-agents' ),
			);
		}

		return array(
			'success' => true,
			'item'    => $post,
		);
	}
}
