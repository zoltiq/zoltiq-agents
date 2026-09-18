<?php

namespace Zoltiq\Agents\Includes\Abilities\Content;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Get_Post_Meta extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/get-post-meta',
			'args' => array(
				'label'               => __( 'Get Post Meta', 'zoltiq-agents' ),
				'description'         => __( 'Fetch post meta via get_post_meta(). Pass key="" (the default) to retrieve every meta key for the post.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-content',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_id'  => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'key'      => array(
							'type'    => 'string',
							'default' => '',
						),
						'meta_key' => array(
							'type'        => 'string',
							'description' => __( 'Alias for "key" (matches WordPress core naming). If both are provided, "key" wins.', 'zoltiq-agents' ),
						),
						'single'   => array(
							'type'    => 'boolean',
							'default' => true,
						),
					),
					'required'             => array( 'post_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'meta'    => array( 'type' => array( 'object', 'string', 'array', 'integer', 'boolean', 'null' ) ),
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
		$post_id = (int) ( $input['post_id'] ?? 0 );
		if ( $post_id <= 0 || ! get_post( $post_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Post not found.', 'zoltiq-agents' ),
			);
		}

		$key    = (string) ( ! empty( $input['key'] ) ? $input['key'] : ( $input['meta_key'] ?? '' ) );
		$single = ! isset( $input['single'] ) || ! empty( $input['single'] );

		$value = get_post_meta( $post_id, $key, $single );

		return array(
			'success' => true,
			'meta'    => $value,
		);
	}
}
