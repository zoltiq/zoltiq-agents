<?php

namespace Zoltiq\Agents\Includes\Abilities\Cron;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Delete_Cron_Jobs_By_Hook extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/delete-cron-jobs-by-hook',
			'args' => array(
				'label'               => __( 'Delete All Cron Jobs By Hook', 'zoltiq-agents' ),
				'description'         => __( 'Unschedule every event for the given hook (across all args sets) via wp_unschedule_hook(). Returns the number of events removed.', 'zoltiq-agents' ),
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
						'removed' => array( 'type' => 'integer' ),
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

		$removed = wp_unschedule_hook( $hook );
		if ( is_wp_error( $removed ) ) {
			return array(
				'success' => false,
				'message' => $removed->get_error_message(),
			);
		}

		return array(
			'success' => true,
			'hook'    => $hook,
			'removed' => (int) $removed,
			'message' => sprintf( __( 'Removed %1$d event(s) for hook "%2$s".', 'zoltiq-agents' ), (int) $removed, $hook ),
		);
	}
}
