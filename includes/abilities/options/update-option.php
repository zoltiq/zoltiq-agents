<?php

namespace Zoltiq\Agents\Includes\Abilities\Options;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Update_Option extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/update-option',
			'args' => array(
				'label'               => __( 'Update Option', 'zoltiq-agents' ),
				'description'         => __( 'Write a wp_options row via update_option(). Creates the option if it does not exist.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-options',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'name'         => array(
							'type'        => 'string',
							'description' => __( 'Option name.', 'zoltiq-agents' ),
						),
						'option_name'  => array(
							'type'        => 'string',
							'description' => __( 'Alias for "name". If both are provided, "name" wins.', 'zoltiq-agents' ),
						),
						'value'        => array( 'type' => array( 'string', 'integer', 'number', 'boolean', 'array', 'object', 'null' ) ),
						'option_value' => array(
							'type'        => array( 'string', 'integer', 'number', 'boolean', 'array', 'object', 'null' ),
							'description' => __( 'Alias for "value". If both are provided, "value" wins.', 'zoltiq-agents' ),
						),
						'autoload'     => array(
							'type'    => array( 'boolean', 'null' ),
							'default' => null,
						),
					),
					'anyOf'                => array(
						array( 'required' => array( 'name' ) ),
						array( 'required' => array( 'option_name' ) ),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'updated' => array( 'type' => 'boolean' ),
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
						'idempotent'  => true,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		$raw_name = ! empty( $input['name'] ) ? $input['name'] : ( $input['option_name'] ?? '' );
		$name     = sanitize_text_field( (string) $raw_name );
		if ( '' === $name ) {
			return array(
				'success' => false,
				'message' => __( 'name (or its alias "option_name") is required.', 'zoltiq-agents' ),
			);
		}

		$value    = array_key_exists( 'value', $input ) ? $input['value'] : ( $input['option_value'] ?? '' );
		$autoload = $input['autoload'] ?? null;

		$result = update_option( $name, $value, $autoload );

		return array(
			'success' => true,
			'updated' => (bool) $result,
			'message' => sprintf( __( 'Wrote option "%s".', 'zoltiq-agents' ), $name ),
		);
	}
}
