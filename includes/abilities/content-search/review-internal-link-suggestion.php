<?php

namespace Zoltiq\Agents\Includes\Abilities\ContentSearch;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Review_Internal_Link_Suggestion extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/review-internal-link-suggestion',
			'args' => array(
				'label'               => __( 'Review Internal Link Suggestion', 'zoltiq-agents' ),
				'description'         => __( 'Mark a suggestion as approved or rejected with optional reviewer notes. Applied suggestions cannot be re-reviewed.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-content-search',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'suggestion_id' => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'verdict'       => array(
							'type' => 'string',
							'enum' => array( 'approved', 'rejected' ),
						),
						'notes'         => array( 'type' => 'string' ),
					),
					'required'             => array( 'suggestion_id', 'verdict' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'    => array( 'type' => 'boolean' ),
						'suggestion' => array( 'type' => 'object' ),
						'message'    => array( 'type' => 'string' ),
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
		$id      = absint( $input['suggestion_id'] ?? 0 );
		$verdict = sanitize_key( (string) ( $input['verdict'] ?? '' ) );
		$notes   = sanitize_text_field( (string) ( $input['notes'] ?? '' ) );
		if ( $id <= 0 || ! in_array( $verdict, array( 'approved', 'rejected' ), true ) ) {
			return array(
				'success' => false,
				'message' => __( 'suggestion_id and verdict (approved|rejected) are required.', 'zoltiq-agents' ),
			);
		}
		$existing = Suggestion_Store::get( $id );
		if ( null === $existing ) {
			return array(
				'success' => false,
				'message' => __( 'Suggestion not found.', 'zoltiq-agents' ),
			);
		}
		if ( 'applied' === (string) $existing['status'] ) {
			return array(
				'success' => false,
				'message' => __( 'Applied suggestions cannot be re-reviewed.', 'zoltiq-agents' ),
			);
		}
		Suggestion_Store::update_status( $id, $verdict, $notes );
		return array(
			'success'    => true,
			'suggestion' => (object) ( Suggestion_Store::get( $id ) ?? array() ),
			'message'    => sprintf( __( 'Suggestion #%1$d marked %2$s.', 'zoltiq-agents' ), $id, $verdict ),
		);
	}
}
