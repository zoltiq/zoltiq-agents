<?php

namespace Zoltiq\Agents\Includes\Abilities\Taxonomies;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Get_Taxonomy extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/get-taxonomy',
			'args' => array(
				'label'               => __( 'Get Taxonomy', 'zoltiq-agents' ),
				'description'         => __( 'Fetch a single taxonomy via GET /wp/v2/taxonomies/{taxonomy}.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-taxonomies',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'taxonomy' => array( 'type' => 'string' ),
					),
					'required'             => array( 'taxonomy' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'  => array( 'type' => 'boolean' ),
						'taxonomy' => array( 'type' => 'object' ),
						'message'  => array( 'type' => 'string' ),
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
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		$tax = sanitize_key( (string) ( $input['taxonomy'] ?? '' ) );
		if ( '' === $tax ) {
			return array(
				'success' => false,
				'message' => __( 'taxonomy is required.', 'zoltiq-agents' ),
			);
		}

		$check = Taxonomy_Routes::rest_base( $tax );
		if ( is_wp_error( $check ) ) {
			return array(
				'success' => false,
				'message' => $check->get_error_message(),
			);
		}

		$obj = get_taxonomy( $tax );
		if ( ! ( $obj instanceof \WP_Taxonomy ) ) {
			return array(
				'success' => false,
				'message' => __( 'Taxonomy not found.', 'zoltiq-agents' ),
			);
		}

		return array(
			'success'  => true,
			'taxonomy' => Term_Formatter::taxonomy_to_array( $obj ),
		);
	}
}
