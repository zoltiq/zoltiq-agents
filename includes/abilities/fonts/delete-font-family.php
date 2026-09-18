<?php

namespace Zoltiq\Agents\Includes\Abilities\Fonts;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Delete_Font_Family extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/delete-font-family',
			'args' => array(
				'label'               => __( 'Delete Font Family', 'zoltiq-agents' ),
				'description'         => __( 'Permanently delete a Font Library font family and all of its child font faces. Trash is not supported for font CPTs — deletion is immediate.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-fonts',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id' => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'Font family post ID.', 'zoltiq-agents' ),
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
						'family'  => array( 'type' => 'object' ),
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
				'message' => __( 'A valid font family ID is required.', 'zoltiq-agents' ),
			);
		}

		$request = new \WP_REST_Request( 'DELETE', '/wp/v2/font-families/' . $id );
		$request->set_param( 'force', true );

		$response = rest_do_request( $request );
		if ( $response->is_error() ) {
			$error = $response->as_error();
			return array(
				'success' => false,
				'message' => $error->get_error_message(),
			);
		}

		$data = (array) $response->get_data();

		return array(
			'success' => true,
			'deleted' => ! empty( $data['deleted'] ),
			'family'  => isset( $data['previous'] ) ? (array) $data['previous'] : array(),
			'message' => sprintf( __( 'Deleted font family #%d.', 'zoltiq-agents' ), $id ),
		);
	}
}
