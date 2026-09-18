<?php

namespace Zoltiq\Agents\Includes\Abilities\Cron;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Cron_Helpers;

defined( 'ABSPATH' ) || exit;

class List_Overdue_Cron_Jobs extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/list-overdue-cron-jobs',
			'args' => array(
				'label'               => __( 'Get Overdue Cron Jobs', 'zoltiq-agents' ),
				'description'         => __( 'Return scheduled events whose timestamp is already in the past — useful to detect a stalled WP-Cron loopback or DISABLE_WP_CRON without a real cron driver.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-cron',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'grace_seconds' => array(
							'type'        => 'integer',
							'minimum'     => 0,
							'default'     => 0,
							'description' => __( 'Ignore events overdue by fewer than this many seconds.', 'zoltiq-agents' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'events'  => array( 'type' => 'array' ),
						'total'   => array( 'type' => 'integer' ),
						'now'     => array( 'type' => 'integer' ),
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
		$grace  = max( 0, (int) ( $input['grace_seconds'] ?? 0 ) );
		$now    = (int) time();
		$cutoff = $now - $grace;

		$overdue = array_values(
			array_filter(
				Cron_Helpers::flatten_events(),
				static function ( array $event ) use ( $cutoff ): bool {
					return $event['timestamp'] <= $cutoff;
				}
			)
		);

		foreach ( $overdue as &$event ) {
			$event['overdue_seconds'] = $now - $event['timestamp'];
		}
		unset( $event );

		return array(
			'success' => true,
			'events'  => $overdue,
			'total'   => count( $overdue ),
			'now'     => $now,
		);
	}
}
