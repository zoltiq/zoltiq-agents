<?php

namespace Zoltiq\Agents\Includes\Abilities\Content;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Create_Post extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/create-post',
			'args' => array(
				'label'               => __( 'Create Post', 'zoltiq-agents' ),
				'description'         => __( 'Create a post (or any post-type record) via wp_insert_post(). Defaults post_type to "post".', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-content',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'title'     => array( 'type' => 'string' ),
						'content'   => array( 'type' => 'string' ),
						'excerpt'   => array( 'type' => 'string' ),
						'status'    => array(
							'type'    => 'string',
							'default' => 'draft',
						),
						'post_type' => array(
							'type'    => 'string',
							'default' => 'post',
						),
						'author'    => array( 'type' => 'integer' ),
						'slug'      => array( 'type' => 'string' ),
						'date'      => array( 'type' => 'string' ),
						'meta'      => array( 'type' => 'object' ),
					),
					'required'             => array( 'title' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'id'      => array( 'type' => 'integer' ),
						'post'    => array( 'type' => 'object' ),
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
		$post_type = sanitize_key( (string) ( $input['post_type'] ?? 'post' ) );

		if ( ! post_type_exists( $post_type ) ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Unknown post type "%s".', 'zoltiq-agents' ), $post_type ),
			);
		}

		$args = array(
			'post_type'    => $post_type,
			'post_title'   => sanitize_text_field( (string) ( $input['title'] ?? '' ) ),
			'post_content' => (string) ( $input['content'] ?? '' ),
			'post_excerpt' => (string) ( $input['excerpt'] ?? '' ),
			'post_status'  => sanitize_key( (string) ( $input['status'] ?? 'draft' ) ),
		);

		if ( ! empty( $input['author'] ) ) {
			$args['post_author'] = (int) $input['author'];
		}
		if ( ! empty( $input['slug'] ) ) {
			$args['post_name'] = sanitize_title( (string) $input['slug'] );
		}
		if ( ! empty( $input['date'] ) ) {
			$args['post_date'] = (string) $input['date'];
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
			'post'    => (array) get_post( (int) $id, ARRAY_A ),
			'message' => sprintf( __( 'Created post #%d.', 'zoltiq-agents' ), $id ),
		);
	}
}
