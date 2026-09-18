<?php

namespace Zoltiq\Agents\Includes\Abilities\Plugins;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Plugin_Helpers;

defined( 'ABSPATH' ) || exit;

class List_Plugins extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/list-plugins',
			'args' => array(
				'label'               => __( 'List Plugins', 'zoltiq-agents' ),
				'description'         => __( 'List all installed WordPress plugins, optionally filtered by status.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-plugins',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'status' => array(
							'type'        => 'string',
							'enum'        => array( 'all', 'active', 'inactive' ),
							'default'     => 'all',
							'description' => __( 'Filter plugins by status.', 'zoltiq-agents' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'plugins' => array( 'type' => 'array' ),
						'total'   => array( 'type' => 'integer' ),
						'active'  => array( 'type' => 'integer' ),
					),
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
		$status = isset( $input['status'] ) ? sanitize_text_field( $input['status'] ) : 'all';

		if ( ! in_array( $status, array( 'all', 'active', 'inactive' ), true ) ) {
			$status = 'all';
		}

		return Plugin_Helpers::get_all_plugins( $status );
	}
}
