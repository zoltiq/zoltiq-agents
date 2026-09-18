<?php

namespace Zoltiq\Agents\Includes\Abilities\Cron;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Cron_Helpers;

defined( 'ABSPATH' ) || exit;

class Delete_Cron_Schedule extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/delete-cron-schedule',
			'args' => array(
				'label'               => __( 'Delete Custom Schedule', 'zoltiq-agents' ),
				'description'         => __( 'Remove a custom schedule previously registered by cron-create-schedule. Built-in and plugin-defined schedules are not affected.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-cron',
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
						'name'    => array( 'type' => 'string' ),
						'removed' => array( 'type' => 'boolean' ),
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
		$name = sanitize_key( (string) ( $input['name'] ?? '' ) );
		if ( '' === $name ) {
			return array(
				'success' => false,
				'message' => __( 'name is required.', 'zoltiq-agents' ),
			);
		}

		$custom = Cron_Helpers::get_custom();
		if ( ! isset( $custom[ $name ] ) ) {
			return array(
				'success' => true,
				'name'    => $name,
				'removed' => false,
				'message' => sprintf( __( 'No custom schedule "%s" to remove.', 'zoltiq-agents' ), $name ),
			);
		}

		Cron_Helpers::remove_custom( $name );

		return array(
			'success' => true,
			'name'    => $name,
			'removed' => true,
			'message' => sprintf( __( 'Removed custom schedule "%s".', 'zoltiq-agents' ), $name ),
		);
	}
}
