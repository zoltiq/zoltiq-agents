<?php

namespace Zoltiq\Agents\Includes\Abilities\Cron;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Cron_Helpers;

defined( 'ABSPATH' ) || exit;

class Get_Cron_Job extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/get-cron-job',
			'args' => array(
				'label'               => __( 'Get Cron Job Details', 'zoltiq-agents' ),
				'description'         => __( 'Return all scheduled WP-Cron events for a given hook name (multiple instances possible — different args, recurring + one-off, etc.).', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-cron',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'hook' => array( 'type' => 'string' ),
					),
					'required'             => array( 'hook' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'hook'    => array( 'type' => 'string' ),
						'events'  => array( 'type' => 'array' ),
						'total'   => array( 'type' => 'integer' ),
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
						'readonly'    => true,
						'destructive' => false,
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

		$events = array_values(
			array_filter(
				Cron_Helpers::flatten_events(),
				static function ( array $event ) use ( $hook ): bool {
					return $event['hook'] === $hook;
				}
			)
		);

		return array(
			'success' => true,
			'hook'    => $hook,
			'events'  => $events,
			'total'   => count( $events ),
		);
	}
}
