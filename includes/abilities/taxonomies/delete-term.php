<?php

namespace Zoltiq\Agents\Includes\Abilities\Taxonomies;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Delete_Term extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/delete-term',
			'args' => array(
				'label'               => __( 'Delete Term', 'zoltiq-agents' ),
				'description'         => __( 'Delete a term in a taxonomy via DELETE /wp/v2/{rest_base}/{id}. Terms do not support trash — force=true is sent implicitly.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-taxonomies',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'taxonomy' => array( 'type' => 'string' ),
						'id'       => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
					),
					'required'             => array( 'taxonomy', 'id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'deleted' => array( 'type' => 'boolean' ),
						'term'    => array( 'type' => 'object' ),
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
		$taxonomy = sanitize_key( (string) ( $input['taxonomy'] ?? '' ) );
		$check    = Taxonomy_Routes::rest_base( $taxonomy );
		if ( is_wp_error( $check ) ) {
			return array(
				'success' => false,
				'message' => $check->get_error_message(),
			);
		}
		$id = (int) ( $input['id'] ?? 0 );
		if ( $id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'A valid id is required.', 'zoltiq-agents' ),
			);
		}

		$term = get_term( $id, $taxonomy );
		if ( ! ( $term instanceof \WP_Term ) ) {
			return array(
				'success' => false,
				'message' => __( 'Term not found.', 'zoltiq-agents' ),
			);
		}

		$snapshot = Term_Formatter::term_to_array( $term );
		$result   = wp_delete_term( $id, $taxonomy );

		if ( is_wp_error( $result ) || false === $result || 0 === $result ) {
			return Term_Formatter::error_from(
				$result,
				sprintf( __( 'Could not delete term #%d.', 'zoltiq-agents' ), $id )
			);
		}

		return array(
			'success' => true,
			'deleted' => true,
			'term'    => $snapshot,
			'message' => sprintf( __( 'Deleted term #%d.', 'zoltiq-agents' ), $id ),
		);
	}
}
