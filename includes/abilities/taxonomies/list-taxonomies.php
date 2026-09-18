<?php

namespace Zoltiq\Agents\Includes\Abilities\Taxonomies;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class List_Taxonomies extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/list-taxonomies',
			'args' => array(
				'label'               => __( 'List Taxonomies', 'zoltiq-agents' ),
				'description'         => __( 'List registered taxonomies via the core REST endpoint GET /wp/v2/taxonomies.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-taxonomies',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'type' => array(
							'type'        => 'string',
							'description' => __( 'Optional: restrict to taxonomies attached to a specific post type.', 'zoltiq-agents' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'    => array( 'type' => 'boolean' ),
						'taxonomies' => array( 'type' => 'array' ),
						'total'      => array( 'type' => 'integer' ),
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
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		$args = array( 'show_in_rest' => true );
		if ( ! empty( $input['type'] ) ) {
			$args['object_type'] = array( sanitize_key( (string) $input['type'] ) );
		}

		$objects = get_taxonomies( $args, 'objects' );

		$formatted = array_values(
			array_map(
				array( Term_Formatter::class, 'taxonomy_to_array' ),
				$objects
			)
		);

		return array(
			'success'    => true,
			'taxonomies' => $formatted,
			'total'      => count( $formatted ),
		);
	}
}
