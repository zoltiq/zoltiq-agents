<?php

namespace Zoltiq\Agents\Includes\Abilities\Taxonomies;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class List_Cpt_Taxonomies extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/list-cpt-taxonomies',
			'args' => array(
				'label'               => __( 'Get CPT Taxonomies', 'zoltiq-agents' ),
				'description'         => __( 'Return the taxonomies attached to a given post type via get_object_taxonomies( $post_type, "objects" ).', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-taxonomies',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_type' => array( 'type' => 'string' ),
					),
					'required'             => array( 'post_type' ),
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
		$post_type = sanitize_key( (string) ( $input['post_type'] ?? '' ) );
		if ( '' === $post_type || ! post_type_exists( $post_type ) ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Unknown post type "%s".', 'zoltiq-agents' ), $post_type ),
			);
		}

		$taxonomies = get_object_taxonomies( $post_type, 'objects' );
		$out        = array();
		foreach ( $taxonomies as $slug => $obj ) {
			$out[] = array(
				'slug'         => (string) $slug,
				'label'        => isset( $obj->label ) ? (string) $obj->label : (string) $slug,
				'hierarchical' => (bool) $obj->hierarchical,
				'public'       => (bool) $obj->public,
				'show_in_rest' => (bool) $obj->show_in_rest,
				'rest_base'    => isset( $obj->rest_base ) && $obj->rest_base ? (string) $obj->rest_base : (string) $slug,
			);
		}

		return array(
			'success'    => true,
			'taxonomies' => $out,
			'total'      => count( $out ),
		);
	}
}
