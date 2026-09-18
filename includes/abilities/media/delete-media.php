<?php

namespace Zoltiq\Agents\Includes\Abilities\Media;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Delete_Media extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/delete-media',
			'args' => array(
				'label'               => __( 'Delete Media', 'zoltiq-agents' ),
				'description'         => __( 'Permanently delete a media attachment via DELETE /wp/v2/media/{id}. Attachments do not support trash.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-media',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id' => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'deleted' => array( 'type' => 'boolean' ),
						'media'   => array( 'type' => 'object' ),
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
		$id = (int) ( $input['id'] ?? 0 );
		if ( $id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'A valid id is required.', 'zoltiq-agents' ),
			);
		}

		$post = get_post( $id );
		if ( ! ( $post instanceof \WP_Post ) || 'attachment' !== $post->post_type ) {
			return array(
				'success' => false,
				'message' => __( 'Attachment not found.', 'zoltiq-agents' ),
			);
		}

		$snapshot = Media_Formatter::to_array( $post );

		$deleted = wp_delete_attachment( $id, true );
		if ( ! $deleted ) {
			return Media_Formatter::error_from(
				false,
				sprintf( __( 'Could not delete attachment #%d.', 'zoltiq-agents' ), $id )
			);
		}

		return array(
			'success' => true,
			'deleted' => true,
			'media'   => $snapshot,
			'message' => sprintf( __( 'Deleted attachment #%d.', 'zoltiq-agents' ), $id ),
		);
	}
}
