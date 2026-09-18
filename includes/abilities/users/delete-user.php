<?php

namespace Zoltiq\Agents\Includes\Abilities\Users;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\User_Helpers;

defined( 'ABSPATH' ) || exit;

class Delete_User extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/delete-user',
			'args' => array(
				'label'               => __( 'Delete User', 'zoltiq-agents' ),
				'description'         => __( 'Delete a WordPress user. Optionally reassign their content to another user.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-users',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'user'     => array(
							'type'        => array( 'string', 'integer' ),
							'description' => __( 'User ID, login, email, or slug to delete.', 'zoltiq-agents' ),
						),
						'reassign' => array(
							'type'        => array( 'string', 'integer' ),
							'description' => __( 'Optional user ID/login/email to reassign content to. Omit to delete content.', 'zoltiq-agents' ),
						),
					),
					'required'             => array( 'user' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'       => array( 'type' => 'boolean' ),
						'message'       => array( 'type' => 'string' ),
						'deleted_user'  => array( 'type' => 'string' ),
						'reassigned_to' => array( 'type' => 'integer' ),
					),
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
		if ( empty( $input['user'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'No user specified.', 'zoltiq-agents' ),
			);
		}

		$user = User_Helpers::resolve_user( $input['user'] );

		if ( null === $user ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'No user found matching "%s".', 'zoltiq-agents' ), (string) $input['user'] ),
			);
		}

		if ( get_current_user_id() === (int) $user->ID ) {
			return array(
				'success' => false,
				'message' => __( 'You cannot delete the currently logged-in user.', 'zoltiq-agents' ),
			);
		}

		$reassign_id = null;
		if ( ! empty( $input['reassign'] ) ) {
			$reassign_user = User_Helpers::resolve_user( $input['reassign'] );
			if ( null === $reassign_user ) {
				return array(
					'success' => false,
					'message' => sprintf( __( 'No reassign user found matching "%s".', 'zoltiq-agents' ), (string) $input['reassign'] ),
				);
			}
			$reassign_id = (int) $reassign_user->ID;
		}

		if ( is_multisite() ) {
			if ( ! function_exists( 'wpmu_delete_user' ) ) {
				require_once ABSPATH . 'wp-admin/includes/ms.php';
			}
			$result = wpmu_delete_user( (int) $user->ID );
		} else {
			if ( ! function_exists( 'wp_delete_user' ) ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
			}
			$result = wp_delete_user( (int) $user->ID, $reassign_id );
		}

		if ( ! $result ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Failed to delete user "%s".', 'zoltiq-agents' ), $user->user_login ),
			);
		}

		$response = array(
			'success'      => true,
			'message'      => sprintf( __( 'User "%s" deleted.', 'zoltiq-agents' ), $user->user_login ),
			'deleted_user' => $user->user_login,
		);

		if ( null !== $reassign_id ) {
			$response['reassigned_to'] = $reassign_id;
		}

		return $response;
	}
}
