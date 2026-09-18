<?php

namespace Zoltiq\Agents\Includes\Abilities\Cron;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Delete_Cron_Job extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/delete-cron-job',
			'args' => array(
				'label'               => __( 'Delete Cron Job', 'zoltiq-agents' ),
				'description'         => __( 'Unschedule a single event via wp_unschedule_event(). If timestamp is omitted, the next scheduled run for the hook+args is used.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-cron',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'hook'      => array( 'type' => 'string' ),
						'timestamp' => array( 'type' => 'integer' ),
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
						'destructive' => true,
						'idempotent'  => true,
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

		$args      = isset( $input['args'] ) && is_array( $input['args'] ) ? $input['args'] : array();
		$timestamp = isset( $input['timestamp'] ) ? (int) $input['timestamp'] : 0;

		if ( $timestamp <= 0 ) {
			$timestamp = (int) wp_next_scheduled( $hook, $args );
			if ( $timestamp <= 0 ) {
				return array(
					'success' => false,
					'message' => sprintf( __( 'No scheduled event for hook "%s" with the given args.', 'zoltiq-agents' ), $hook ),
				);
			}
		}

		$result = wp_unschedule_event( $timestamp, $hook, $args, true );
		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}
		if ( false === $result ) {
			return array(
				'success' => false,
				'message' => __( 'Could not unschedule the event.', 'zoltiq-agents' ),
			);
		}

		return array(
			'success'   => true,
			'hook'      => $hook,
			'timestamp' => $timestamp,
			'message'   => sprintf( __( 'Unscheduled "%1$s" at %2$d.', 'zoltiq-agents' ), $hook, $timestamp ),
		);
	}
}
