<?php

namespace Zoltiq\Agents\Includes\Abilities\Cron;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Cron_Helpers;

defined( 'ABSPATH' ) || exit;

class Create_Cron_Schedule extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/create-cron-schedule',
			'args' => array(
				'label'               => __( 'Create Custom Schedule', 'zoltiq-agents' ),
				'description'         => __( 'Register a persistent custom cron schedule. The schedule is saved to wp_options and added back via the cron_schedules filter on every load.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-cron',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'name'     => array( 'type' => 'string' ),
						'interval' => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'Number of seconds between runs.', 'zoltiq-agents' ),
						),
						'display'  => array( 'type' => 'string' ),
					),
					'required'             => array( 'name', 'interval', 'display' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'  => array( 'type' => 'boolean' ),
						'name'     => array( 'type' => 'string' ),
						'interval' => array( 'type' => 'integer' ),
						'display'  => array( 'type' => 'string' ),
						'message'  => array( 'type' => 'string' ),
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
		$name     = sanitize_key( (string) ( $input['name'] ?? '' ) );
		$interval = (int) ( $input['interval'] ?? 0 );
		$display  = sanitize_text_field( (string) ( $input['display'] ?? '' ) );

		if ( '' === $name || $interval < 1 || '' === $display ) {
			return array(
				'success' => false,
				'message' => __( 'name (kebab-case), positive interval, and display are required.', 'zoltiq-agents' ),
			);
		}

		Cron_Helpers::add_custom( $name, $interval, $display );

		return array(
			'success'  => true,
			'name'     => $name,
			'interval' => $interval,
			'display'  => $display,
			'message'  => sprintf( __( 'Registered custom schedule "%s".', 'zoltiq-agents' ), $name ),
		);
	}
}
