<?php

namespace Zoltiq\Agents\Includes\Abilities\Comments;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Create_Comment extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/create-comment',
			'args' => array(
				'label'               => __( 'Create Comment', 'zoltiq-agents' ),
				'description'         => __( 'Create a comment via POST /wp/v2/comments. Requires post and content.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-comments',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post'         => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'content'      => array( 'type' => 'string' ),
						'author'       => array( 'type' => 'integer' ),
						'author_name'  => array( 'type' => 'string' ),
						'author_email' => array( 'type' => 'string' ),
						'author_url'   => array( 'type' => 'string' ),
						'parent'       => array(
							'type'    => 'integer',
							'default' => 0,
						),
						'status'       => array( 'type' => 'string' ),
					),
					'required'             => array( 'post', 'content' ),
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
						'idempotent'  => false,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		$post_id = isset( $input['post'] ) ? (int) $input['post'] : 0;
		$content = isset( $input['content'] ) ? (string) $input['content'] : '';
		if ( $post_id <= 0 || '' === $content ) {
			return array(
				'success' => false,
				'message' => __( 'post and content are required.', 'zoltiq-agents' ),
			);
		}

		$data = array(
			'comment_post_ID'  => $post_id,
			'comment_content'  => $content,
			'comment_parent'   => isset( $input['parent'] ) ? (int) $input['parent'] : 0,
			'comment_approved' => self::map_input_status( isset( $input['status'] ) ? (string) $input['status'] : 'approved' ),
		);

		if ( isset( $input['author'] ) ) {
			$data['user_id'] = (int) $input['author'];
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

		$new_id = wp_insert_comment( wp_slash( $data ) );
		if ( ! $new_id ) {
			return Comment_Formatter::error_from( false, __( 'Could not create comment.', 'zoltiq-agents' ) );
		}

		$comment = get_comment( (int) $new_id );
		if ( null === $comment ) {
			return array(
				'success' => false,
				'message' => __( 'Comment created but could not be retrieved.', 'zoltiq-agents' ),
			);
		}

		return array(
			'success' => true,
			'comment' => Comment_Formatter::to_array( $comment ),
			'message' => __( 'Comment created.', 'zoltiq-agents' ),
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
			case '':
			case 'approved':
			case 'approve':
			case '1':
			default:
				return '1';
		}
	}
}
