<?php

namespace Zoltiq\Agents\Includes\Abilities\Options;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Delete_Option extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/delete-option',
			'args' => array(
				'label'               => __( 'Delete Option', 'zoltiq-agents' ),
				'description'         => __( 'Delete a wp_options row via delete_option(). Idempotent — succeeds even if the option does not exist.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-options',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'name' => array( 'type' => 'string' ),
					),
					'required'             => array( 'name' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'deleted' => array( 'type' => 'boolean' ),
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
		$name = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
		if ( '' === $name ) {
			return array(
				'success' => false,
				'message' => __( 'name is required.', 'zoltiq-agents' ),
			);
		}

		$deleted = delete_option( $name );

		return array(
			'success' => true,
			'deleted' => (bool) $deleted,
			'message' => sprintf( __( 'Deleted option "%s".', 'zoltiq-agents' ), $name ),
		);
	}
}
