<?php

namespace Zoltiq\Agents\Includes\Abilities\Content;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Update_Post_Block extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/update-post-block',
			'args' => array(
				'label'               => __( 'Update Block', 'zoltiq-agents' ),
				'description'         => __( 'Parse a post\'s block tree, find one top-level block by 0-based index (block_index) or by (block_name, occurrence), merge the supplied attributes, replace innerHTML, and save the post. Only top-level blocks are matched; nested-block editing is not supported by this ability.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-content',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' ) && current_user_can( 'edit_posts' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_id'     => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'block_index' => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'block_name'  => array( 'type' => 'string' ),
						'occurrence'  => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'attributes'  => array( 'type' => 'object' ),
						'inner_html'  => array( 'type' => 'string' ),
					),
					'required'             => array( 'post_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'post_id' => array( 'type' => 'integer' ),
						'block'   => array( 'type' => 'object' ),
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
				'message' => __( 'You do not have permission to edit this post.', 'zoltiq-agents' ),
			);
		}
		$post_type_obj = get_post_type_object( (string) $post->post_type );
		if ( ! $post_type_obj instanceof \WP_Post_Type
			|| in_array( $post_type_obj->name, array( 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request' ), true )
			|| ! ( (bool) $post_type_obj->public || (bool) $post_type_obj->show_ui || (bool) $post_type_obj->show_in_rest ) ) {
			return array(
				'success' => false,
				'message' => __( 'This post type is not editable through this ability.', 'zoltiq-agents' ),
			);
		}

		$blocks = parse_blocks( (string) $post->post_content );
		if ( ! is_array( $blocks ) || array() === $blocks ) {
			return array(
				'success' => false,
				'message' => __( 'Post contains no parseable blocks.', 'zoltiq-agents' ),
			);
		}

		$target_index = null;
		if ( isset( $input['block_index'] ) ) {
			$target_index = (int) $input['block_index'];
			if ( $target_index < 0 || ! isset( $blocks[ $target_index ] ) ) {
				return array(
					'success' => false,
					'message' => sprintf( __( 'Block index %d out of range.', 'zoltiq-agents' ), $target_index ),
				);
			}
		} else {
			$block_name = (string) ( $input['block_name'] ?? '' );
			$occurrence = max( 0, (int) ( $input['occurrence'] ?? 0 ) );
			if ( '' === $block_name || ! preg_match( '/^[A-Za-z0-9_-]+\/[A-Za-z0-9_-]+$/', $block_name ) ) {
				return array(
					'success' => false,
					'message' => __( 'One of block_index or a valid block_name (e.g. "core/paragraph") is required.', 'zoltiq-agents' ),
				);
			}
			$hits = 0;
			foreach ( $blocks as $idx => $block ) {
				if ( ( $block['blockName'] ?? '' ) === $block_name ) {
					if ( $hits === $occurrence ) {
						$target_index = (int) $idx;
						break;
					}
					++$hits;
				}
			}
			if ( null === $target_index ) {
				return array(
					'success' => false,
					'message' => sprintf( __( 'No top-level block "%1$s" at occurrence %2$d.', 'zoltiq-agents' ), $block_name, $occurrence ),
				);
			}
		}

		if ( isset( $input['attributes'] ) && is_array( $input['attributes'] ) ) {
			$blocks[ $target_index ]['attrs'] = array_merge( (array) ( $blocks[ $target_index ]['attrs'] ?? array() ), $input['attributes'] );
		}
		if ( isset( $input['inner_html'] ) ) {
			$blocks[ $target_index ]['innerHTML']    = (string) $input['inner_html'];
			$blocks[ $target_index ]['innerContent'] = array( (string) $input['inner_html'] );
		}

		$new_content = serialize_blocks( $blocks );
		$updated     = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $new_content,
			),
			true
		);
		if ( is_wp_error( $updated ) ) {
			return array(
				'success' => false,
				'message' => $updated->get_error_message(),
			);
		}

		return array(
			'success' => true,
			'post_id' => $post_id,
			'block'   => $blocks[ $target_index ],
			'message' => sprintf( __( 'Updated block #%1$d on post #%2$d.', 'zoltiq-agents' ), $target_index, $post_id ),
		);
	}
}
