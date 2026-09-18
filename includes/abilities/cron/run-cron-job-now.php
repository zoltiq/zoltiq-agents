<?php

namespace Zoltiq\Agents\Includes\Abilities\Cron;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Cron_Helpers;

defined( 'ABSPATH' ) || exit;

class Run_Cron_Job_Now extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/run-cron-job-now',
			'args' => array(
				'label'               => __( 'Run Cron Job Now', 'zoltiq-agents' ),
				'description'         => __( 'Fire a scheduled cron hook synchronously via do_action(). The hook must be present in the cron array — this is not a generic do_action() runner.', 'zoltiq-agents' ),
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
						'success'  => array( 'type' => 'boolean' ),
						'hook'     => array( 'type' => 'string' ),
						'duration' => array( 'type' => 'number' ),
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

		$args    = isset( $input['args'] ) && is_array( $input['args'] ) ? $input['args'] : array();
		$is_cron = false;
		foreach ( Cron_Helpers::flatten_events() as $event ) {
			if ( $event['hook'] === $hook ) {
				$is_cron = true;
				break;
			}
		}
		if ( ! $is_cron ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Hook "%s" is not a registered cron event — refusing to fire it.', 'zoltiq-agents' ), $hook ),
			);
		}

		$started = microtime( true );
		do_action_ref_array( $hook, $args );
		$duration = round( microtime( true ) - $started, 4 );

		return array(
			'success'  => true,
			'hook'     => $hook,
			'duration' => $duration,
			'message'  => sprintf( __( 'Fired "%1$s" in %2$.4fs.', 'zoltiq-agents' ), $hook, $duration ),
		);
	}
}
