<?php

namespace Zoltiq\Agents\Includes\Abilities\Comments;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Update_Comment extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/update-comment',
			'args' => array(
				'label'               => __( 'Update Comment', 'zoltiq-agents' ),
				'description'         => __( 'Update a comment via POST /wp/v2/comments/{id}. Only the supplied fields are touched.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-comments',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'           => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'content'      => array( 'type' => 'string' ),
						'status'       => array( 'type' => 'string' ),
						'author_name'  => array( 'type' => 'string' ),
						'author_email' => array( 'type' => 'string' ),
						'author_url'   => array( 'type' => 'string' ),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
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
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		$id = (int) ( $input['id'] ?? 0 );
		if ( $id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'A valid id is required.', 'zoltiq-agents' ),
			);
		}

		if ( null === get_comment( $id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Comment not found.', 'zoltiq-agents' ),
			);
		}

		$data = array( 'comment_ID' => $id );
		if ( isset( $input['content'] ) ) {
			$data['comment_content'] = (string) $input['content'];
		}
		if ( isset( $input['status'] ) ) {
			$data['comment_approved'] = self::map_input_status( (string) $input['status'] );
		}
		if ( isset( $input['author_name'] ) ) {
			$data['comment_author'] = (string) $input['author_name'];
		}
		if ( isset( $input['author_email'] ) ) {
			$data['comment_author_email'] = (string) $input['author_email'];
		}
		if ( isset( $input['author_url'] ) ) {
			$data['comment_author_url'] = (string) $input['author_url'];
		}

		if ( 1 === count( $data ) ) {
			return array(
				'success' => false,
				'message' => __( 'At least one field to update is required.', 'zoltiq-agents' ),
			);
		}

		$result = wp_update_comment( wp_slash( $data ), true );
		if ( is_wp_error( $result ) || false === $result ) {
			return Comment_Formatter::error_from(
				$result,
				sprintf( __( 'Could not update comment #%d.', 'zoltiq-agents' ), $id )
			);
		}

		$updated = get_comment( $id );
		return array(
			'success' => true,
			'comment' => null !== $updated ? Comment_Formatter::to_array( $updated ) : array(),
			'message' => sprintf( __( 'Updated comment #%d.', 'zoltiq-agents' ), $id ),
		);
	}

	private static function map_input_status( string $status ): string {
		switch ( $status ) {
			case 'hold':
			case 'unapproved':
			case '0':
				return '0';
			case 'spam':
				return 'spam';
			case 'trash':
				return 'trash';
			case 'approved':
			case 'approve':
			case '1':
			default:
				return '1';
		}
	}
}
