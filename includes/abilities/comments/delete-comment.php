<?php

namespace Zoltiq\Agents\Includes\Abilities\Comments;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Delete_Comment extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/delete-comment',
			'args' => array(
				'label'               => __( 'Delete Comment', 'zoltiq-agents' ),
				'description'         => __( 'Delete a comment via DELETE /wp/v2/comments/{id}. Defaults to trash; pass force=true to delete permanently.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-comments',
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
						'deleted' => array( 'type' => 'boolean' ),
						'comment' => array( 'type' => 'object' ),
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
		if ( $id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'A valid id is required.', 'zoltiq-agents' ),
			);
		}

		$comment = get_comment( $id );
		if ( null === $comment ) {
			return array(
				'success' => false,
				'message' => __( 'Comment not found.', 'zoltiq-agents' ),
			);
		}

		$snapshot = Comment_Formatter::to_array( $comment );

		$deleted = wp_delete_comment( $id, $force );
		if ( ! $deleted ) {
			return Comment_Formatter::error_from(
				false,
				sprintf( __( 'Could not delete comment #%d.', 'zoltiq-agents' ), $id )
			);
		}

		return array(
			'success' => true,
			'deleted' => true,
			'comment' => $snapshot,
			'message' => $force
				? sprintf( __( 'Permanently deleted comment #%d.', 'zoltiq-agents' ), $id )
				: sprintf( __( 'Trashed comment #%d.', 'zoltiq-agents' ), $id ),
		);
	}
}
