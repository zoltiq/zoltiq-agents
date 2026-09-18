<?php

namespace Zoltiq\Agents\Includes\Abilities\Content;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Update_Post_Meta extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/update-post-meta',
			'args' => array(
				'label'               => __( 'Update Post Meta', 'zoltiq-agents' ),
				'description'         => __( 'Set a post meta value via update_post_meta(). If the meta is registered via register_meta() and protected, the request will be rejected.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-content',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_id'    => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'key'        => array( 'type' => 'string' ),
						'meta_key'   => array(
							'type'        => 'string',
							'description' => __( 'Alias for "key" (matches WordPress core naming). If both are provided, "key" wins.', 'zoltiq-agents' ),
						),
						'value'      => array( 'type' => array( 'string', 'integer', 'number', 'boolean', 'array', 'object', 'null' ) ),
						'meta_value' => array(
							'type'        => array( 'string', 'integer', 'number', 'boolean', 'array', 'object', 'null' ),
							'description' => __( 'Alias for "value" (matches WordPress core naming). If both are provided, "value" wins.', 'zoltiq-agents' ),
						),
					),
					
					array( 'required' => array( 'post_id' ) ),
										
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'updated' => array( 'type' => 'boolean' ),
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
		$post_id = (int) ( $input['post_id'] ?? 0 );
		$raw_key = ! empty( $input['key'] ) ? $input['key'] : ( $input['meta_key'] ?? '' );
		$key     = sanitize_text_field( (string) $raw_key );

		if ( $post_id <= 0 || ! get_post( $post_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Post not found.', 'zoltiq-agents' ),
			);
		}
		if ( '' === $key ) {
			return array(
				'success' => false,
				'message' => __( 'Meta key is empty. Pass "key" (or its alias "meta_key").', 'zoltiq-agents' ),
			);
		}
		if ( is_protected_meta( $key, 'post' ) ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Meta key "%s" is protected and cannot be modified.', 'zoltiq-agents' ), $key ),
			);
		}

		$value  = array_key_exists( 'value', $input ) ? $input['value'] : ( $input['meta_value'] ?? '' );
		$result = update_post_meta( $post_id, $key, $value );

		return array(
			'success' => true,
			'updated' => (bool) $result,
			'message' => sprintf( __( 'Wrote meta "%1$s" on post #%2$d.', 'zoltiq-agents' ), $key, $post_id ),
		);
	}
}
