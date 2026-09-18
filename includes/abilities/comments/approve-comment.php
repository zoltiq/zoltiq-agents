<?php

namespace Zoltiq\Agents\Includes\Abilities\Comments;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Approve_Comment extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/approve-comment',
			'args' => array(
				'label'               => __( 'Approve Comment', 'zoltiq-agents' ),
				'description'         => __( 'Approve a comment via POST /wp/v2/comments/{id} with status=approved.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-comments',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id' => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
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
		return Moderation::set_status( (int) ( $input['id'] ?? 0 ), 'approved' );
	}
}
