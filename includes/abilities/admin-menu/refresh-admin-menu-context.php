<?php

namespace Zoltiq\Agents\Includes\Abilities\AdminMenu;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Refresh_Admin_Menu_Context extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/refresh-admin-menu-context',
			'args' => array(
				'label'               => __( 'Refresh Admin Menu Context', 'zoltiq-agents' ),
				'description'         => __( 'Refresh admin-menu-derived transient/user-meta signals: clears the "wp_get_active_and_valid_plugins" object-cache row, clears the current user\'s meta caches, and requests a rewrite flush. Cheap; safe to poll.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-admin-menu',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => new \stdClass(),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'      => array( 'type' => 'boolean' ),
						'refreshed_at' => array( 'type' => 'integer' ),
						'invalidated'  => array( 'type' => 'array' ),
						'message'      => array( 'type' => 'string' ),
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
						'idempotent'  => true,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		unset( $input );

		$invalidated = array();

		wp_cache_delete( 'active_plugins', 'options' );
		$invalidated[] = 'active_plugins';

		wp_cache_delete( 'alloptions', 'options' );
		$invalidated[] = 'alloptions';

		$user = wp_get_current_user();
		if ( $user instanceof \WP_User && $user->ID > 0 ) {
			clean_user_cache( (int) $user->ID );
			$invalidated[] = 'current_user_cache';
		}

		return array(
			'success'      => true,
			'refreshed_at' => time(),
			'invalidated'  => $invalidated,
			'message'      => sprintf( __( 'Admin menu context invalidated across %d cache source(s).', 'zoltiq-agents' ), count( $invalidated ) ),
		);
	}
}
