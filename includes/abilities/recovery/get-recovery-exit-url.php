<?php

namespace Zoltiq\Agents\Includes\Abilities\Recovery;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use WP_Recovery_Mode;

defined( 'ABSPATH' ) || exit;

class Get_Recovery_Exit_Url extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/get-recovery-exit-url',
			'args' => array(
				'label'               => __( 'Get Recovery Mode Exit URL', 'zoltiq-agents' ),
				'description'         => __( 'Returns the admin-clickable URL that exits WordPress Recovery Mode when followed inside an active recovery session. WP core does not expose a programmatic exit API (the action is cookie- and nonce-guarded); this ability returns the URL so an admin — or an agent driving a browser — can follow it. Returns null when the site is not in recovery mode.', 'zoltiq-agents' ),
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
						'success'          => array( 'type' => 'boolean' ),
						'in_recovery_mode' => array( 'type' => 'boolean' ),
						'exit_url'         => array( 'type' => array( 'string', 'null' ) ),
						'note'             => array( 'type' => 'string' ),
					),
					'required'             => array( 'success', 'in_recovery_mode', 'exit_url', 'note' ),
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
		$in_recovery = function_exists( 'wp_is_recovery_mode' ) && wp_is_recovery_mode();

		if ( ! $in_recovery ) {
			return array(
				'success'          => true,
				'in_recovery_mode' => false,
				'exit_url'         => null,
				'note'             => __( 'The site is not currently in recovery mode. Exit URL is only meaningful during an active recovery session.', 'zoltiq-agents' ),
			);
		}

		$action  = class_exists( 'WP_Recovery_Mode' ) ? WP_Recovery_Mode::EXIT_ACTION : 'exit_recovery_mode';
		$url     = wp_nonce_url(
			add_query_arg( 'action', $action, admin_url() ),
			$action
		);

		return array(
			'success'          => true,
			'in_recovery_mode' => true,
			'exit_url'         => (string) $url,
			'note'             => __( 'Follow this URL in a browser that carries the recovery-mode cookie (typically the browser that entered recovery mode) to exit. Cannot be POSTed programmatically.', 'zoltiq-agents' ),
		);
	}
}
