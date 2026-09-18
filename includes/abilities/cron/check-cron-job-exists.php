<?php

namespace Zoltiq\Agents\Includes\Abilities\Cron;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Check_Cron_Job_Exists extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/check-cron-job-exists',
			'args' => array(
				'label'               => __( 'Check If Cron Job Exists', 'zoltiq-agents' ),
				'description'         => __( 'Return whether a scheduled event exists for a hook (and optional args). Backed by wp_next_scheduled() — true if a future run is registered.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-cron',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'hook' => array( 'type' => 'string' ),
						'args' => array( 'type' => 'array' ),
					),
					'required'             => array( 'hook' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'exists'  => array( 'type' => 'boolean' ),
						'hook'    => array( 'type' => 'string' ),
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

		$args   = isset( $input['args'] ) && is_array( $input['args'] ) ? $input['args'] : array();
		$exists = (bool) wp_next_scheduled( $hook, $args );

		return array(
			'success' => true,
			'exists'  => $exists,
			'hook'    => $hook,
		);
	}
}
