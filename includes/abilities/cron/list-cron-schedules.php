<?php

namespace Zoltiq\Agents\Includes\Abilities\Cron;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class List_Cron_Schedules extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/list-cron-schedules',
			'args' => array(
				'label'               => __( 'List Schedules', 'zoltiq-agents' ),
				'description'         => __( 'List every registered cron schedule via wp_get_schedules() — includes core schedules (hourly/twicedaily/daily/weekly), schedules added by other plugins, and persisted custom schedules.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-cron',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'   => array( 'type' => 'boolean' ),
						'schedules' => array( 'type' => 'array' ),
						'total'     => array( 'type' => 'integer' ),
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
		$schedules = wp_get_schedules();
		$out       = array();
		foreach ( $schedules as $name => $def ) {
			$out[] = array(
				'name'     => (string) $name,
				'interval' => isset( $def['interval'] ) ? (int) $def['interval'] : 0,
				'display'  => isset( $def['display'] ) ? (string) $def['display'] : '',
			);
		}

		return array(
			'success'   => true,
			'schedules' => $out,
			'total'     => count( $out ),
		);
	}
}
