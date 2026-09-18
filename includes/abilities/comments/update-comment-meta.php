<?php

namespace Zoltiq\Agents\Includes\Abilities\Comments;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Update_Comment_Meta extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/update-comment-meta',
			'args' => array(
				'label'               => __( 'Update Comment Meta', 'zoltiq-agents' ),
				'description'         => __( 'Write meta values on a comment via POST /wp/v2/comments/{id} with a meta object. Only keys registered with register_meta show_in_rest=true accept writes.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-comments',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'   => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'meta' => array(
							'type'        => 'object',
							'description' => __( 'Object of meta keys → values to write.', 'zoltiq-agents' ),
						),
					),
					'required'             => array( 'id', 'meta' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'meta'    => array( 'type' => 'object' ),
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
		$id   = (int) ( $input['id'] ?? 0 );
		$meta = isset( $input['meta'] ) && is_array( $input['meta'] ) ? $input['meta'] : array();
		if ( $id <= 0 || empty( $meta ) ) {
			return array(
				'success' => false,
				'message' => __( 'id and a non-empty meta object are required.', 'zoltiq-agents' ),
			);
		}

		if ( null === get_comment( $id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Comment not found.', 'zoltiq-agents' ),
			);
		}

		foreach ( $meta as $key => $value ) {
			$key = (string) $key;
			if ( ! Comment_Formatter::is_meta_key_writable( $key ) ) {
				continue;
			}
			$sanitized = sanitize_meta( $key, $value, 'comment' );
			update_comment_meta( $id, $key, wp_slash( $sanitized ) );
		}

		return array(
			'success' => true,
			'meta'    => Comment_Formatter::build_meta_map( $id ),
			'message' => sprintf( __( 'Wrote meta on comment #%d.', 'zoltiq-agents' ), $id ),
		);
	}
}
