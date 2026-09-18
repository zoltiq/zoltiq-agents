<?php

namespace Zoltiq\Agents\Includes\Abilities\Cron;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Get_Cron_Schedule extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/get-cron-schedule',
			'args' => array(
				'label'               => __( 'Get Schedule Details', 'zoltiq-agents' ),
				'description'         => __( 'Return a single schedule definition by name (interval + display) from wp_get_schedules().', 'zoltiq-agents' ),
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
						'success'  => array( 'type' => 'boolean' ),
						'schedule' => array( 'type' => array( 'object', 'null' ) ),
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
						'readonly'    => true,
						'destructive' => false,
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

		$schedules = wp_get_schedules();
		if ( ! isset( $schedules[ $name ] ) ) {
			return array(
				'success'  => true,
				'schedule' => null,
				'message'  => sprintf( __( 'No schedule registered under "%s".', 'zoltiq-agents' ), $name ),
			);
		}

		return array(
			'success'  => true,
			'schedule' => array(
				'name'     => $name,
				'interval' => isset( $schedules[ $name ]['interval'] ) ? (int) $schedules[ $name ]['interval'] : 0,
				'display'  => isset( $schedules[ $name ]['display'] ) ? (string) $schedules[ $name ]['display'] : '',
			),
		);
	}
}
