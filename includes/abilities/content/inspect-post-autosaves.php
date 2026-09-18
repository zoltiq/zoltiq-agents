<?php

namespace Zoltiq\Agents\Includes\Abilities\Content;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Inspect_Post_Autosaves extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/inspect-post-autosaves',
			'args' => array(
				'label'               => __( 'Inspect Autosaves', 'zoltiq-agents' ),
				'description'         => __( 'Return the autosaves attached to a post (distinct from revisions). Only one autosave per post per author exists at a time; this ability flattens them into a list.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-content',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_id' => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
					),
					'required'             => array( 'post_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'   => array( 'type' => 'boolean' ),
						'post_id'   => array( 'type' => 'integer' ),
						'autosaves' => array( 'type' => 'array' ),
						'message'   => array( 'type' => 'string' ),
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
		$post_id = absint( $input['post_id'] ?? 0 );
		if ( $post_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'A valid post_id is required.', 'zoltiq-agents' ),
			);
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return array(
				'success' => false,
				'message' => __( 'Post not found.', 'zoltiq-agents' ),
			);
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'You do not have permission to inspect autosaves for this post.', 'zoltiq-agents' ),
			);
		}
		$post_type_obj = get_post_type_object( (string) $post->post_type );
		if ( ! $post_type_obj instanceof \WP_Post_Type
			|| in_array( $post_type_obj->name, array( 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request' ), true ) ) {
			return array(
				'success' => false,
				'message' => __( 'This post type is not supported by this ability.', 'zoltiq-agents' ),
			);
		}

		$revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
				'post_status'   => 'inherit',
			)
		);
		$autosaves = array();
		if ( is_array( $revisions ) ) {
			foreach ( $revisions as $rev ) {
				if ( ! $rev instanceof \WP_Post ) {
					continue;
				}
				if ( false === strpos( (string) $rev->post_name, '-autosave' ) ) {
					continue;
				}
				$autosaves[] = array(
					'id'             => (int) $rev->ID,
					'author_id'      => (int) $rev->post_author,
					'modified_gmt'   => (string) $rev->post_modified_gmt,
					'title'          => (string) $rev->post_title,
					'content_length' => strlen( (string) $rev->post_content ),
				);
			}
		}

		return array(
			'success'   => true,
			'post_id'   => $post_id,
			'autosaves' => $autosaves,
			'message'   => sprintf( __( 'Found %1$d autosave(s) for post #%2$d.', 'zoltiq-agents' ), count( $autosaves ), $post_id ),
		);
	}
}
