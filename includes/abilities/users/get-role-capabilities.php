<?php

namespace Zoltiq\Agents\Includes\Abilities\Users;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Get_Role_Capabilities extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/get-role-capabilities',
			'args' => array(
				'label'               => __( 'Get Role Capabilities', 'zoltiq-agents' ),
				'description'         => __( 'Return the full capability map for a single registered role. Useful before granting a role via user-create / user-update.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-users',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'role' => array(
							'type'        => 'string',
							'description' => __( 'Role slug (e.g. administrator, editor).', 'zoltiq-agents' ),
						),
					),
					'required'             => array( 'role' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'      => array( 'type' => 'boolean' ),
						'message'      => array( 'type' => 'string' ),
						'role'         => array( 'type' => 'string' ),
						'label'        => array( 'type' => 'string' ),
						'capabilities' => array( 'type' => 'object' ),
					),
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
		if ( empty( $input['role'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'No role specified.', 'zoltiq-agents' ),
			);
		}

		$slug = sanitize_key( $input['role'] );
		$role = get_role( $slug );

		if ( null === $role ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Role "%s" does not exist.', 'zoltiq-agents' ), $slug ),
			);
		}

		$wp_roles = wp_roles();
		$details  = $wp_roles->roles[ $slug ] ?? array();
		$label    = isset( $details['name'] ) ? translate_user_role( $details['name'] ) : $slug;

		return array(
			'success'      => true,
			'message'      => sprintf( __( 'Capabilities for role "%s".', 'zoltiq-agents' ), $label ),
			'role'         => $slug,
			'label'        => $label,
			'capabilities' => (object) $role->capabilities,
		);
	}
}
