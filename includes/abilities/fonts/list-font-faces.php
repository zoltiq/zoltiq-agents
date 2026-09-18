<?php

namespace Zoltiq\Agents\Includes\Abilities\Fonts;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class List_Font_Faces extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/list-font-faces',
			'args' => array(
				'label'               => __( 'List Font Faces', 'zoltiq-agents' ),
				'description'         => __( 'List Font Library font faces (wp_font_face CPT) registered under a specific font family.', 'zoltiq-agents' ),
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
						'page'           => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
						'per_page'       => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100,
							'default' => 10,
						),
					),
					'required'             => array( 'font_family_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'faces'   => array( 'type' => 'array' ),
						'total'   => array( 'type' => 'integer' ),
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
		if ( $family_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'A valid font_family_id is required.', 'zoltiq-agents' ),
			);
		}

		$request = new \WP_REST_Request( 'GET', '/wp/v2/font-families/' . $family_id . '/font-faces' );
		$request->set_param( 'page', max( 1, (int) ( $input['page'] ?? 1 ) ) );
		$request->set_param( 'per_page', min( 100, max( 1, (int) ( $input['per_page'] ?? 10 ) ) ) );

		$response = rest_do_request( $request );
		if ( $response->is_error() ) {
			$error = $response->as_error();
			return array(
				'success' => false,
				'message' => $error->get_error_message(),
			);
		}

		$data    = $response->get_data();
		$headers = $response->get_headers();
		$total   = isset( $headers['X-WP-Total'] ) ? (int) $headers['X-WP-Total'] : count( (array) $data );

		return array(
			'success' => true,
			'faces'   => is_array( $data ) ? $data : array(),
			'total'   => $total,
		);
	}
}
