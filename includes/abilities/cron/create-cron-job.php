<?php

namespace Zoltiq\Agents\Includes\Abilities\Cron;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Create_Cron_Job extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/create-cron-job',
			'args' => array(
				'label'               => __( 'Create Cron Job', 'zoltiq-agents' ),
				'description'         => __( 'Schedule a WP-Cron event. Pass "schedule" (e.g. hourly, daily, or any registered name) for a recurring event; omit it for a one-off via wp_schedule_single_event().', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-cron',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'hook'      => array( 'type' => 'string' ),
						'schedule'  => array(
							'type'        => 'string',
							'default'     => '',
							'description' => __( 'Recurring schedule name from wp_get_schedules(). Leave empty for a one-off event.', 'zoltiq-agents' ),
						),
						'timestamp' => array(
							'type'        => 'integer',
							'description' => __( 'When the event should first fire (UNIX timestamp). Defaults to now.', 'zoltiq-agents' ),
						),
						'args'      => array( 'type' => 'array' ),
					),
					'required'             => array( 'hook' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'   => array( 'type' => 'boolean' ),
						'hook'      => array( 'type' => 'string' ),
						'timestamp' => array( 'type' => 'integer' ),
						'schedule'  => array( 'type' => 'string' ),
						'message'   => array( 'type' => 'string' ),
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
						'idempotent'  => false,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		$hook = sanitize_text_field( (string) ( $input['hook'] ?? '' ) );
		if ( '' === $hook ) {
			return array(
				'success' => false,
				'message' => __( 'hook is required.', 'zoltiq-agents' ),
			);
		}

		$schedule  = sanitize_key( (string) ( $input['schedule'] ?? '' ) );
		$timestamp = isset( $input['timestamp'] ) ? (int) $input['timestamp'] : (int) time();
		$args      = isset( $input['args'] ) && is_array( $input['args'] ) ? $input['args'] : array();

		if ( '' !== $schedule ) {
			$schedules = wp_get_schedules();
			if ( ! isset( $schedules[ $schedule ] ) ) {
				return array(
					'success' => false,
					'message' => sprintf( __( 'Unknown schedule "%s".', 'zoltiq-agents' ), $schedule ),
				);
			}
			$result = wp_schedule_event( $timestamp, $schedule, $hook, $args, true );
		} else {
			$result = wp_schedule_single_event( $timestamp, $hook, $args, true );
		}

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		return array(
			'success'   => true,
			'hook'      => $hook,
			'timestamp' => $timestamp,
			'schedule'  => $schedule,
			'message'   => '' !== $schedule
				? sprintf( __( 'Scheduled "%1$s" on schedule "%2$s".', 'zoltiq-agents' ), $hook, $schedule )
				: sprintf( __( 'Scheduled one-off "%s".', 'zoltiq-agents' ), $hook ),
		);
	}
}
