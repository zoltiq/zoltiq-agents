<?php

namespace Zoltiq\Agents\Includes\Abilities\Content;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Get_Post extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/get-post',
			'args' => array(
				'label'               => __( 'Get Post', 'zoltiq-agents' ),
				'description'         => __( 'Fetch a post (any post type) by ID via get_post().', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-content',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'        => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'post_type' => array(
							'type'        => 'string',
							'description' => __( 'Optional: error if the post does not match this type.', 'zoltiq-agents' ),
						),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'post'    => array( 'type' => 'object' ),
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
		$id   = (int) ( $input['id'] ?? 0 );
		$post = $id > 0 ? get_post( $id, ARRAY_A ) : null;
		if ( ! $post ) {
			return array(
				'success' => false,
				'message' => __( 'Post not found.', 'zoltiq-agents' ),
			);
		}

		$expected = sanitize_key( (string) ( $input['post_type'] ?? '' ) );
		if ( '' !== $expected && $expected !== $post['post_type'] ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Post is not of type "%1$s" (actual: "%2$s").', 'zoltiq-agents' ), $expected, $post['post_type'] ),
			);
		}

		return array(
			'success' => true,
			'post'    => $post,
		);
	}
}
