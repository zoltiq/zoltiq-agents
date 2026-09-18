<?php

namespace Zoltiq\Agents\Includes\Abilities\Themes;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Theme_Helpers;

defined( 'ABSPATH' ) || exit;

class List_Themes extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/list-themes',
			'args' => array(
				'label'               => __( 'List Themes', 'zoltiq-agents' ),
				'description'         => __( 'List all installed WordPress themes, optionally filtered by status.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-themes',
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
							'description' => __( 'Filter themes by status.', 'zoltiq-agents' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'themes' => array( 'type' => 'array' ),
						'total'  => array( 'type' => 'integer' ),
						'active' => array( 'type' => 'integer' ),
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

		return Theme_Helpers::get_all_themes( $status );
	}
}
