<?php

namespace Zoltiq\Agents\Includes\Abilities\Content;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Create_Cpt_Item extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/create-cpt-item',
			'args' => array(
				'label'               => __( 'Create CPT Item', 'zoltiq-agents' ),
				'description'         => __( 'Create a custom post type record. post_type is required and must be registered.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-content',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_type' => array( 'type' => 'string' ),
						'title'     => array( 'type' => 'string' ),
						'content'   => array( 'type' => 'string' ),
						'excerpt'   => array( 'type' => 'string' ),
						'status'    => array(
							'type'    => 'string',
							'default' => 'draft',
						),
						'slug'      => array( 'type' => 'string' ),
						'author'    => array( 'type' => 'integer' ),
						'meta'      => array( 'type' => 'object' ),
					),
					'required'             => array( 'post_type', 'title' ),
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
						'idempotent'  => false,
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

		$cap = get_post_type_object( $post_type )->cap->edit_posts ?? 'edit_posts';
		if ( ! current_user_can( $cap ) ) {
			return array(
				'success' => false,
				'message' => __( 'You do not have permission to create items of this type.', 'zoltiq-agents' ),
			);
		}

		$args = array(
			'post_type'    => $post_type,
			'post_title'   => sanitize_text_field( (string) ( $input['title'] ?? '' ) ),
			'post_content' => (string) ( $input['content'] ?? '' ),
			'post_excerpt' => (string) ( $input['excerpt'] ?? '' ),
			'post_status'  => sanitize_key( (string) ( $input['status'] ?? 'draft' ) ),
		);
		if ( ! empty( $input['slug'] ) ) {
			$args['post_name'] = sanitize_title( (string) $input['slug'] );
		}
		if ( ! empty( $input['author'] ) ) {
			$args['post_author'] = (int) $input['author'];
		}
		if ( ! empty( $input['meta'] ) && is_array( $input['meta'] ) ) {
			$args['meta_input'] = $input['meta'];
		}

		$id = wp_insert_post( $args, true );
		if ( is_wp_error( $id ) ) {
			return array(
				'success' => false,
				'message' => $id->get_error_message(),
			);
		}

		return array(
			'success' => true,
			'id'      => (int) $id,
			'item'    => (array) get_post( (int) $id, ARRAY_A ),
			'message' => sprintf( __( 'Created %1$s #%2$d.', 'zoltiq-agents' ), $post_type, $id ),
		);
	}
}
