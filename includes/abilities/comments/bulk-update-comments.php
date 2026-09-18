<?php

namespace Zoltiq\Agents\Includes\Abilities\Comments;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Bulk_Update_Comments extends Ability_Definition {

	private const MAX_IDS = 100;

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/bulk-update-comments',
			'args' => array(
				'label'               => __( 'Bulk Update Comments', 'zoltiq-agents' ),
				'description'         => __( 'Apply the same status change (approve / hold / spam / trash) to up to 100 comments in one call. Enforces manage_options + moderate_comments. Returns per-comment success/failure entries.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-comments',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' ) && current_user_can( 'moderate_comments' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'comment_ids' => array(
							'type'     => 'array',
							'items'    => array( 'type' => 'integer' ),
							'minItems' => 1,
							'maxItems' => self::MAX_IDS,
						),
						'status'      => array(
							'type' => 'string',
							'enum' => array( 'approve', 'hold', 'spam', 'trash' ),
						),
					),
					'required'             => array( 'comment_ids', 'status' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'   => array( 'type' => 'boolean' ),
						'status'    => array( 'type' => 'string' ),
						'succeeded' => array( 'type' => 'array' ),
						'failed'    => array( 'type' => 'array' ),
						'message'   => array( 'type' => 'string' ),
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
		$status      = sanitize_key( (string) ( $input['status'] ?? '' ) );
		$comment_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $input['comment_ids'] ?? array() ) ) ) ) );

		$status = match ( $status ) {
			'approved'   => 'approve',
			'pending', 'unapproved', 'unapprove' => 'hold',
			default      => $status,
		};

		if ( '' === $status || ! in_array( $status, array( 'approve', 'hold', 'spam', 'trash' ), true ) ) {
			return array(
				'success' => false,
				'message' => __( 'A valid status is required (approve / hold / spam / trash).', 'zoltiq-agents' ),
			);
		}

		if ( array() === $comment_ids ) {
			return array(
				'success' => false,
				'message' => __( 'At least one comment_id is required.', 'zoltiq-agents' ),
			);
		}

		if ( count( $comment_ids ) > self::MAX_IDS ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Too many comment_ids (max %d per call).', 'zoltiq-agents' ), self::MAX_IDS ),
			);
		}

		$succeeded = array();
		$failed    = array();

		foreach ( $comment_ids as $id ) {
			$id = absint( $id );
			if ( $id <= 0 ) {
				$failed[] = array(
					'id'      => $id,
					'message' => __( 'Invalid comment_id.', 'zoltiq-agents' ),
				);
				continue;
			}
			$comment = get_comment( $id );
			if ( ! $comment instanceof \WP_Comment ) {
				$failed[] = array(
					'id'      => $id,
					'message' => __( 'Comment not found.', 'zoltiq-agents' ),
				);
				continue;
			}
			if ( ! current_user_can( 'edit_comment', $id ) && ! current_user_can( 'edit_post', (int) $comment->comment_post_ID ) ) {
				$failed[] = array(
					'id'      => $id,
					'message' => __( 'You do not have permission to moderate this comment.', 'zoltiq-agents' ),
				);
				continue;
			}
			$result = wp_set_comment_status( $id, $status, true );
			if ( is_wp_error( $result ) || false === $result ) {
				$failed[] = array(
					'id'      => $id,
					'message' => is_wp_error( $result ) ? $result->get_error_message() : __( 'wp_set_comment_status returned false.', 'zoltiq-agents' ),
				);
				continue;
			}
			$succeeded[] = $id;
		}

		return array(
			'success'   => array() === $failed,
			'status'    => $status,
			'succeeded' => $succeeded,
			'failed'    => $failed,
			'message'   => sprintf( __( 'Updated %1$d comments; %2$d failed.', 'zoltiq-agents' ), count( $succeeded ), count( $failed ) ),
		);
	}
}
