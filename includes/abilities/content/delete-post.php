<?php

namespace Zoltiq\Agents\Includes\Abilities\Content;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Delete_Post extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/delete-post',
			'args' => array(
				'label'               => __( 'Delete Post', 'zoltiq-agents' ),
				'description'         => __( 'Delete a post (any post type) via wp_delete_post(). Defaults to trash; pass force=true to delete permanently.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-content',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'    => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'force' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'id'      => array( 'type' => 'integer' ),
						'force'   => array( 'type' => 'boolean' ),
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
						'destructive' => true,
						'idempotent'  => true,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		$id    = (int) ( $input['id'] ?? 0 );
		$force = ! empty( $input['force'] );

		if ( $id <= 0 || ! get_post( $id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Post not found.', 'zoltiq-agents' ),
			);
		}

		if ( ! current_user_can( 'delete_post', $id ) ) {
			return array(
				'success' => false,
				'message' => __( 'You do not have permission to delete this post.', 'zoltiq-agents' ),
			);
		}

		$result = $force ? wp_delete_post( $id, true ) : wp_trash_post( $id );
		if ( ! $result ) {
			return array(
				'success' => false,
				'message' => __( 'Could not delete the post.', 'zoltiq-agents' ),
			);
		}

		return array(
			'success' => true,
			'id'      => $id,
			'force'   => $force,
			'message' => $force
				? sprintf( __( 'Permanently deleted post #%d.', 'zoltiq-agents' ), $id )
				: sprintf( __( 'Moved post #%d to trash.', 'zoltiq-agents' ), $id ),
		);
	}
}
