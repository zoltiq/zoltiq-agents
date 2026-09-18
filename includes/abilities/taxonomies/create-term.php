<?php

namespace Zoltiq\Agents\Includes\Abilities\Taxonomies;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Create_Term extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/create-term',
			'args' => array(
				'label'               => __( 'Create Term', 'zoltiq-agents' ),
				'description'         => __( 'Create a term in a taxonomy via POST /wp/v2/{rest_base}.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-taxonomies',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'taxonomy'    => array( 'type' => 'string' ),
						'name'        => array( 'type' => 'string' ),
						'slug'        => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
						'parent'      => array(
							'type'    => 'integer',
							'default' => 0,
						),
					),
					'required'             => array( 'taxonomy', 'name' ),
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
						'idempotent'  => false,
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

		$name = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
		if ( '' === $name ) {
			return array(
				'success' => false,
				'message' => __( 'name is required.', 'zoltiq-agents' ),
			);
		}

		$args = array();
		if ( ! empty( $input['slug'] ) ) {
			$args['slug'] = sanitize_title( (string) $input['slug'] );
		}
		if ( isset( $input['description'] ) ) {
			$args['description'] = (string) $input['description'];
		}
		if ( isset( $input['parent'] ) ) {
			$args['parent'] = (int) $input['parent'];
		}

		$result = wp_insert_term( wp_slash( $name ), $taxonomy, wp_slash( $args ) );
		if ( is_wp_error( $result ) ) {
			return Term_Formatter::error_from( $result, __( 'Could not create term.', 'zoltiq-agents' ) );
		}

		$term = get_term( (int) $result['term_id'], $taxonomy );
		if ( ! ( $term instanceof \WP_Term ) ) {
			return array(
				'success' => false,
				'message' => __( 'Term created but could not be retrieved.', 'zoltiq-agents' ),
			);
		}

		return array(
			'success' => true,
			'term'    => Term_Formatter::term_to_array( $term ),
			'message' => sprintf( __( 'Created term "%1$s" in "%2$s".', 'zoltiq-agents' ), $name, $taxonomy ),
		);
	}
}
