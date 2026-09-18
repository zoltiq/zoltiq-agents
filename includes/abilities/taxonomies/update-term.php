<?php

namespace Zoltiq\Agents\Includes\Abilities\Taxonomies;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Update_Term extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/update-term',
			'args' => array(
				'label'               => __( 'Update Term', 'zoltiq-agents' ),
				'description'         => __( 'Update a term in a taxonomy via POST /wp/v2/{rest_base}/{id}.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-taxonomies',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'taxonomy'    => array( 'type' => 'string' ),
						'id'          => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'name'        => array( 'type' => 'string' ),
						'slug'        => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
						'parent'      => array( 'type' => 'integer' ),
					),
					'required'             => array( 'taxonomy', 'id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
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
						'destructive' => false,
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

		if ( ! ( get_term( $id, $taxonomy ) instanceof \WP_Term ) ) {
			return array(
				'success' => false,
				'message' => __( 'Term not found.', 'zoltiq-agents' ),
			);
		}

		$args = array();
		if ( isset( $input['name'] ) ) {
			$args['name'] = sanitize_text_field( (string) $input['name'] );
		}
		if ( isset( $input['slug'] ) ) {
			$args['slug'] = sanitize_title( (string) $input['slug'] );
		}
		if ( isset( $input['description'] ) ) {
			$args['description'] = (string) $input['description'];
		}
		if ( isset( $input['parent'] ) ) {
			$args['parent'] = (int) $input['parent'];
		}

		if ( empty( $args ) ) {
			return array(
				'success' => false,
				'message' => __( 'At least one field to update is required.', 'zoltiq-agents' ),
			);
		}

		$result = wp_update_term( $id, $taxonomy, wp_slash( $args ) );
		if ( is_wp_error( $result ) ) {
			return Term_Formatter::error_from(
				$result,
				sprintf( __( 'Could not update term #%d.', 'zoltiq-agents' ), $id )
			);
		}

		$term = get_term( $id, $taxonomy );
		return array(
			'success' => true,
			'term'    => $term instanceof \WP_Term ? Term_Formatter::term_to_array( $term ) : array(),
			'message' => sprintf( __( 'Updated term #%d.', 'zoltiq-agents' ), $id ),
		);
	}
}
