<?php

namespace Zoltiq\Agents\Includes\Abilities\Content;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Update_Cpt_Item extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/update-cpt-item',
			'args' => array(
				'label'               => __( 'Update CPT Item', 'zoltiq-agents' ),
				'description'         => __( 'Update a custom post type record via wp_update_post(). post_type is validated against the post; only supplied fields are touched.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-content',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_type' => array( 'type' => 'string' ),
						'id'        => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'title'     => array( 'type' => 'string' ),
						'content'   => array( 'type' => 'string' ),
						'excerpt'   => array( 'type' => 'string' ),
						'status'    => array( 'type' => 'string' ),
						'slug'      => array( 'type' => 'string' ),
						'meta'      => array( 'type' => 'object' ),
					),
					'required'             => array( 'post_type', 'id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'id'      => array( 'type' => 'integer' ),
						'item'    => array( 'type' => 'object' ),
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
		$post_type = sanitize_key( (string) ( $input['post_type'] ?? '' ) );
		$id        = (int) ( $input['id'] ?? 0 );

		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! $post || $post->post_type !== $post_type ) {
			return array(
				'success' => false,
				'message' => __( 'Item not found for the given post_type.', 'zoltiq-agents' ),
			);
		}

		if ( ! current_user_can( 'edit_post', $id ) ) {
			return array(
				'success' => false,
				'message' => __( 'You do not have permission to edit this item.', 'zoltiq-agents' ),
			);
		}

		$args = array( 'ID' => $id );
		foreach ( array(
			'title'   => 'post_title',
			'content' => 'post_content',
			'excerpt' => 'post_excerpt',
			'status'  => 'post_status',
			'slug'    => 'post_name',
		) as $in => $out ) {
			if ( isset( $input[ $in ] ) ) {
				$args[ $out ] = 'slug' === $in ? sanitize_title( (string) $input[ $in ] ) : sanitize_text_field( (string) $input[ $in ] );
				if ( 'content' === $in ) {
					$args[ $out ] = (string) $input[ $in ];
				}
			}
		}
		if ( ! empty( $input['meta'] ) && is_array( $input['meta'] ) ) {
			$args['meta_input'] = $input['meta'];
		}

		$result = wp_update_post( $args, true );
		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		return array(
			'success' => true,
			'id'      => (int) $result,
			'item'    => (array) get_post( (int) $result, ARRAY_A ),
			'message' => sprintf( __( 'Updated %1$s #%2$d.', 'zoltiq-agents' ), $post_type, $result ),
		);
	}
}
