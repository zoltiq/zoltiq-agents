<?php

namespace Zoltiq\Agents\Includes\Abilities\Content;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Delete_Cpt_Item extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/delete-cpt-item',
			'args' => array(
				'label'               => __( 'Delete CPT Item', 'zoltiq-agents' ),
				'description'         => __( 'Delete a custom post type record. Defaults to trash; pass force=true to delete permanently.', 'zoltiq-agents' ),
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
						'force'     => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
					'required'             => array( 'post_type', 'id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'id'      => array( 'type' => 'integer' ),
						'force'   => array( 'type' => 'boolean' ),
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
						'destructive' => true,
						'idempotent'  => true,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		$post_type = sanitize_key( (string) ( $input['post_type'] ?? '' ) );
		$id        = (int) ( $input['id'] ?? 0 );
		$force     = ! empty( $input['force'] );

		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! $post || $post->post_type !== $post_type ) {
			return array(
				'success' => false,
				'message' => __( 'Item not found for the given post_type.', 'zoltiq-agents' ),
			);
		}
		if ( ! current_user_can( 'delete_post', $id ) ) {
			return array(
				'success' => false,
				'message' => __( 'You do not have permission to delete this item.', 'zoltiq-agents' ),
			);
		}

		$result = $force ? wp_delete_post( $id, true ) : wp_trash_post( $id );
		if ( ! $result ) {
			return array(
				'success' => false,
				'message' => __( 'Could not delete the item.', 'zoltiq-agents' ),
			);
		}

		return array(
			'success' => true,
			'id'      => $id,
			'force'   => $force,
			'message' => sprintf( __( 'Deleted %1$s #%2$d.', 'zoltiq-agents' ), $post_type, $id ),
		);
	}
}
