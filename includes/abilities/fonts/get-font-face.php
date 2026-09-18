<?php

namespace Zoltiq\Agents\Includes\Abilities\Fonts;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Get_Font_Face extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/get-font-face',
			'args' => array(
				'label'               => __( 'Get Font Face', 'zoltiq-agents' ),
				'description'         => __( 'Fetch a single Font Library font face (wp_font_face CPT) under a given font family.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-fonts',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'font_family_id' => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'Parent font family post ID.', 'zoltiq-agents' ),
						),
						'id'             => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'Font face post ID.', 'zoltiq-agents' ),
						),
					),
					'required'             => array( 'font_family_id', 'id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'face'    => array( 'type' => 'object' ),
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
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		$family_id = (int) ( $input['font_family_id'] ?? 0 );
		$face_id   = (int) ( $input['id'] ?? 0 );
		if ( $family_id <= 0 || $face_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Both font_family_id and id are required.', 'zoltiq-agents' ),
			);
		}

		$request = new \WP_REST_Request( 'GET', '/wp/v2/font-families/' . $family_id . '/font-faces/' . $face_id );

		$response = rest_do_request( $request );
		if ( $response->is_error() ) {
			$error = $response->as_error();
			return array(
				'success' => false,
				'message' => $error->get_error_message(),
			);
		}

		return array(
			'success' => true,
			'face'    => (array) $response->get_data(),
		);
	}
}
