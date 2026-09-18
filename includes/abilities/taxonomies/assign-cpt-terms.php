<?php

namespace Zoltiq\Agents\Includes\Abilities\Taxonomies;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Assign_Cpt_Terms extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/assign-cpt-terms',
			'args' => array(
				'label'               => __( 'Assign Terms', 'zoltiq-agents' ),
				'description'         => __( 'Set or append terms on a post in a given taxonomy via wp_set_object_terms(). Term IDs or slugs may be mixed.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-taxonomies',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_id'  => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'taxonomy' => array( 'type' => 'string' ),
						'terms'    => array(
							'type'  => 'array',
							'items' => array( 'type' => array( 'integer', 'string' ) ),
						),
						'append'   => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
					'required'             => array( 'post_id', 'taxonomy', 'terms' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'post_id' => array( 'type' => 'integer' ),
						'terms'   => array( 'type' => 'array' ),
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
		$post_id  = (int) ( $input['post_id'] ?? 0 );
		$taxonomy = sanitize_key( (string) ( $input['taxonomy'] ?? '' ) );
		$terms    = isset( $input['terms'] ) && is_array( $input['terms'] ) ? $input['terms'] : array();
		$append   = ! empty( $input['append'] );

		if ( $post_id <= 0 || ! get_post( $post_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Post not found.', 'zoltiq-agents' ),
			);
		}
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Unknown taxonomy "%s".', 'zoltiq-agents' ), $taxonomy ),
			);
		}
		if ( ! current_user_can( 'assign_terms', $taxonomy ) && ! current_user_can( 'edit_post', $post_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'You do not have permission to assign terms to this post.', 'zoltiq-agents' ),
			);
		}

		$normalized = array();
		foreach ( $terms as $t ) {
			$normalized[] = is_numeric( $t ) ? (int) $t : sanitize_text_field( (string) $t );
		}

		$result = wp_set_object_terms( $post_id, $normalized, $taxonomy, $append );
		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		return array(
			'success' => true,
			'post_id' => $post_id,
			'terms'   => array_map( 'intval', (array) $result ),
			'message' => sprintf( __( 'Assigned %1$s terms to post #%2$d.', 'zoltiq-agents' ), $taxonomy, $post_id ),
		);
	}
}
