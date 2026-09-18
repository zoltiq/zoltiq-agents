<?php

namespace Zoltiq\Agents\Includes\Abilities\Taxonomies;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Set_Term_Image extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/set-term-image',
			'args' => array(
				'label'               => __( 'Set Term Image', 'zoltiq-agents' ),
				'description'         => __( 'Attach (or clear) an attachment as the "image" of a term by writing the term-meta key _thumbnail_id. Matches the convention used by WooCommerce and most theme frameworks. Pass attachment_id=0 to clear.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-taxonomies',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'term_id'       => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'attachment_id' => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
					),
					'required'             => array( 'term_id', 'attachment_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'       => array( 'type' => 'boolean' ),
						'term_id'       => array( 'type' => 'integer' ),
						'attachment_id' => array( 'type' => 'integer' ),
						'message'       => array( 'type' => 'string' ),
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
		$term_id       = absint( $input['term_id'] ?? 0 );
		$attachment_id = absint( $input['attachment_id'] ?? 0 );

		if ( $term_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'A valid term_id is required.', 'zoltiq-agents' ),
			);
		}

		$term = get_term( $term_id );
		if ( ! $term instanceof \WP_Term ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Term #%d does not exist.', 'zoltiq-agents' ), $term_id ),
			);
		}

		$taxonomy_obj = get_taxonomy( (string) $term->taxonomy );
		if ( ! $taxonomy_obj instanceof \WP_Taxonomy || ! current_user_can( $taxonomy_obj->cap->edit_terms ) ) {
			return array(
				'success' => false,
				'message' => __( 'You do not have permission to edit terms in this taxonomy.', 'zoltiq-agents' ),
			);
		}

		if ( $attachment_id > 0 ) {
			$attachment = get_post( $attachment_id );
			if ( ! $attachment instanceof \WP_Post || 'attachment' !== $attachment->post_type ) {
				return array(
					'success' => false,
					'message' => sprintf( __( 'Attachment #%d does not exist.', 'zoltiq-agents' ), $attachment_id ),
				);
			}
			if ( ! function_exists( 'wp_attachment_is_image' ) || ! wp_attachment_is_image( $attachment_id ) ) {
				return array(
					'success' => false,
					'message' => __( 'Attachment must be an image.', 'zoltiq-agents' ),
				);
			}
			if ( ! current_user_can( 'read_post', $attachment_id ) ) {
				return array(
					'success' => false,
					'message' => __( 'You do not have permission to use this attachment.', 'zoltiq-agents' ),
				);
			}
			update_term_meta( $term_id, '_thumbnail_id', $attachment_id );
			return array(
				'success'       => true,
				'term_id'       => $term_id,
				'attachment_id' => $attachment_id,
				'message'       => sprintf( __( 'Set term #%1$d image to attachment #%2$d.', 'zoltiq-agents' ), $term_id, $attachment_id ),
			);
		}

		delete_term_meta( $term_id, '_thumbnail_id' );
		return array(
			'success'       => true,
			'term_id'       => $term_id,
			'attachment_id' => 0,
			'message'       => sprintf( __( 'Cleared image on term #%d.', 'zoltiq-agents' ), $term_id ),
		);
	}
}
