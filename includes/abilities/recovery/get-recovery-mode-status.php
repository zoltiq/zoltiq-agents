<?php

namespace Zoltiq\Agents\Includes\Abilities\Recovery;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Get_Recovery_Mode_Status extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/get-recovery-mode-status',
			'args' => array(
				'label'               => __( 'Get Recovery Mode Status', 'zoltiq-agents' ),
				'description'         => __( 'Detects whether the site is currently in WordPress Recovery Mode (active only when a fatal error has been captured on a protected endpoint) and returns summary counters: paused-plugin count, paused-theme count, and whether the WP fatal-error handler is enabled.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-recovery',
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
						'success'               => array( 'type' => 'boolean' ),
						'in_recovery_mode'      => array( 'type' => 'boolean' ),
						'paused_plugin_count'   => array( 'type' => 'integer' ),
						'paused_theme_count'    => array( 'type' => 'integer' ),
						'fatal_handler_enabled' => array( 'type' => 'boolean' ),
					),
					'required'             => array( 'success', 'in_recovery_mode', 'paused_plugin_count', 'paused_theme_count', 'fatal_handler_enabled' ),
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
		$paused_plugins = function_exists( 'wp_paused_plugins' ) ? wp_paused_plugins()->get_all() : array();
		$paused_themes  = function_exists( 'wp_paused_themes' ) ? wp_paused_themes()->get_all() : array();

		return array(
			'success'               => true,
			'in_recovery_mode'      => function_exists( 'wp_is_recovery_mode' ) ? (bool) wp_is_recovery_mode() : false,
			'paused_plugin_count'   => count( $paused_plugins ),
			'paused_theme_count'    => count( $paused_themes ),
			'fatal_handler_enabled' => function_exists( 'wp_is_fatal_error_handler_enabled' ) ? (bool) wp_is_fatal_error_handler_enabled() : false,
		);
	}
}
