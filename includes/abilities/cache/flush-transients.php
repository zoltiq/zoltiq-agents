<?php

namespace Zoltiq\Agents\Includes\Abilities\Cache;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Flush_Transients extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/flush-transients',
			'args' => array(
				'label'               => __( 'Flush Transients', 'zoltiq-agents' ),
				'description'         => __( 'Deletes WordPress transients. Use scope "expired" (default) to remove only expired transients, or "all" to remove every transient regardless of expiry.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-cache',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array( 'scope' => 'expired' ),
					'properties'           => array(
						'scope' => array(
							'type'        => 'string',
							'enum'        => array( 'expired', 'all' ),
							'default'     => 'expired',
							'description' => __( '"expired" deletes only expired transients; "all" deletes every transient.', 'zoltiq-agents' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'scope'   => array( 'type' => 'string' ),
						'deleted' => array(
							'type'        => 'integer',
							'description' => __( 'Number of rows deleted (available for scope "all" only; -1 when not applicable).', 'zoltiq-agents' ),
						),
						'message' => array( 'type' => 'string' ),
					),
					'required'             => array( 'success', 'scope', 'deleted', 'message' ),
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
		global $wpdb;

		$scope = isset( $input['scope'] ) && 'all' === $input['scope'] ? 'all' : 'expired';

		if ( 'all' === $scope ) {
			$deleted = (int) $wpdb->query(
				"DELETE FROM {$wpdb->options}
				 WHERE option_name LIKE '\_transient\_%'
				    OR option_name LIKE '\_site\_transient\_%'"
			);

			return array(
				'success' => true,
				'scope'   => 'all',
				'deleted' => $deleted,
				'message' => sprintf(
					_n( '%d transient deleted.', '%d transients deleted.', $deleted, 'zoltiq-agents' ),
					$deleted
				),
			);
		}

		delete_expired_transients( true );

		return array(
			'success' => true,
			'scope'   => 'expired',
			'deleted' => -1,
			'message' => __( 'Expired transients deleted.', 'zoltiq-agents' ),
		);
	}
}
