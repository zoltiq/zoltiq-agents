<?php

namespace Zoltiq\Agents\Includes\Abilities\Cron;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Get_Cron_Status extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/get-cron-status',
			'args' => array(
				'label'               => __( 'Get Cron Status', 'zoltiq-agents' ),
				'description'         => __( 'Report whether WP-Cron is disabled (DISABLE_WP_CRON) or running in alternate mode (ALTERNATE_WP_CRON), the wp-cron.php URL, and the current server timestamp.', 'zoltiq-agents' ),
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
						'success'         => array( 'type' => 'boolean' ),
						'disabled'        => array( 'type' => 'boolean' ),
						'alternate'       => array( 'type' => 'boolean' ),
						'cron_url'        => array( 'type' => 'string' ),
						'timestamp'       => array( 'type' => 'integer' ),
						'datetime'        => array( 'type' => 'string' ),
						'timezone_string' => array( 'type' => 'string' ),
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
		$now = (int) time();
		return array(
			'success'         => true,
			'disabled'        => defined( 'DISABLE_WP_CRON' ) && \DISABLE_WP_CRON,
			'alternate'       => defined( 'ALTERNATE_WP_CRON' ) && \ALTERNATE_WP_CRON,
			'cron_url'        => site_url( 'wp-cron.php' ),
			'timestamp'       => $now,
			'datetime'        => gmdate( 'c', $now ),
			'timezone_string' => (string) wp_timezone_string(),
		);
	}
}
