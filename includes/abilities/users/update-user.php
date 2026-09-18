<?php

namespace Zoltiq\Agents\Includes\Abilities\Users;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\User_Helpers;

defined( 'ABSPATH' ) || exit;

class Update_User extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/update-user',
			'args' => array(
				'label'               => __( 'Update User', 'zoltiq-agents' ),
				'description'         => __( 'Update an existing WordPress user. Only provided fields are changed. Pass "meta" to set user_meta values (JSON strings auto-decoded) and "delete_meta_keys" to remove keys. Pass "add_roles" / "remove_roles" to mutate role membership, or "set_roles" to replace all current roles with the given list — set_roles takes precedence over add/remove. Pass "force_logout: true" to destroy every active login session after the update (useful when changing password or revoking access).', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-users',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'user'             => array(
							'type'        => array( 'string', 'integer' ),
							'description' => __( 'User ID, login, email, or slug.', 'zoltiq-agents' ),
						),
						'email'            => array( 'type' => 'string' ),
						'first_name'       => array( 'type' => 'string' ),
						'last_name'        => array( 'type' => 'string' ),
						'display_name'     => array( 'type' => 'string' ),
						'url'              => array( 'type' => 'string' ),
						'password'         => array( 'type' => 'string' ),
						'meta'             => array(
							'type'        => 'object',
							'description' => __( 'Map of user_meta key => value to set. String values that look like JSON are auto-decoded into arrays/objects.', 'zoltiq-agents' ),
						),
						'delete_meta_keys' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => __( 'user_meta keys to remove.', 'zoltiq-agents' ),
						),
						'add_roles'        => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => __( 'Role slugs to add to the user.', 'zoltiq-agents' ),
						),
						'remove_roles'     => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => __( 'Role slugs to remove from the user.', 'zoltiq-agents' ),
						),
						'set_roles'        => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => __( 'Replace all current roles with this list. Takes precedence over add_roles / remove_roles.', 'zoltiq-agents' ),
						),
						'force_logout'     => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => __( 'Destroy every active login session for this user after the update completes.', 'zoltiq-agents' ),
						),
					),
					'required'             => array( 'user' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'            => array( 'type' => 'boolean' ),
						'message'            => array( 'type' => 'string' ),
						'user_id'            => array( 'type' => 'integer' ),
						'user'               => array( 'type' => 'object' ),
						'meta_updated'       => array( 'type' => 'array' ),
						'meta_failed'        => array( 'type' => 'array' ),
						'meta_deleted'       => array( 'type' => 'array' ),
						'roles_added'        => array( 'type' => 'array' ),
						'roles_removed'      => array( 'type' => 'array' ),
						'roles_failed'       => array( 'type' => 'array' ),
						'sessions_destroyed' => array( 'type' => 'integer' ),
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
						'destructive' => false,
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

		$update    = array( 'ID' => $user->ID );
		$has_field = false;

		if ( array_key_exists( 'email', $input ) ) {
			$email = sanitize_email( (string) $input['email'] );
			if ( '' === $email || ! is_email( $email ) ) {
				return array(
					'success' => false,
					'message' => __( 'Invalid email address.', 'zoltiq-agents' ),
				);
			}
			$update['user_email'] = $email;
			$has_field            = true;
		}
		if ( array_key_exists( 'first_name', $input ) ) {
			$update['first_name'] = sanitize_text_field( (string) $input['first_name'] );
			$has_field            = true;
		}
		if ( array_key_exists( 'last_name', $input ) ) {
			$update['last_name'] = sanitize_text_field( (string) $input['last_name'] );
			$has_field           = true;
		}
		if ( array_key_exists( 'display_name', $input ) ) {
			$update['display_name'] = sanitize_text_field( (string) $input['display_name'] );
			$has_field              = true;
		}
		if ( array_key_exists( 'url', $input ) ) {
			$update['user_url'] = esc_url_raw( (string) $input['url'] );
			$has_field          = true;
		}
		if ( ! empty( $input['password'] ) ) {
			$update['user_pass'] = (string) $input['password'];
			$has_field           = true;
		}

		if ( $has_field ) {
			$result = wp_update_user( $update );

			if ( is_wp_error( $result ) ) {
				return array(
					'success' => false,
					'message' => sprintf( __( 'Failed to update user: %s', 'zoltiq-agents' ), $result->get_error_message() ),
				);
			}
		}

		$user_id = (int) $user->ID;

		$meta_updated = array();
		$meta_failed  = array();
		if ( ! empty( $input['meta'] ) && ( is_array( $input['meta'] ) || is_object( $input['meta'] ) ) ) {
			$meta_result  = User_Helpers::apply_meta( $user_id, (array) $input['meta'] );
			$meta_updated = $meta_result['updated'];
			$meta_failed  = $meta_result['failed'];
		}

		$meta_deleted = array();
		if ( ! empty( $input['delete_meta_keys'] ) && is_array( $input['delete_meta_keys'] ) ) {
			$delete_result = User_Helpers::delete_meta( $user_id, $input['delete_meta_keys'] );
			$meta_deleted  = $delete_result['deleted'];
			$meta_failed   = array_merge( $meta_failed, $delete_result['failed'] );
		}

		$role_changes = $this->apply_role_changes( $user, $input );

		$sessions_destroyed = 0;
		if ( ! empty( $input['force_logout'] ) ) {
			$sessions_destroyed = User_Helpers::destroy_sessions( $user_id );
		}

		$updated_user = get_user_by( 'id', $user_id );

		return array(
			'success'            => true,
			'message'            => sprintf( __( 'User "%s" updated successfully.', 'zoltiq-agents' ), $user->user_login ),
			'user_id'            => $user_id,
			'user'               => $updated_user ? User_Helpers::format_user( $updated_user ) : array(),
			'meta_updated'       => $meta_updated,
			'meta_failed'        => $meta_failed,
			'meta_deleted'       => $meta_deleted,
			'roles_added'        => $role_changes['added'],
			'roles_removed'      => $role_changes['removed'],
			'roles_failed'       => $role_changes['failed'],
			'sessions_destroyed' => $sessions_destroyed,
		);
	}

	private function apply_role_changes( \WP_User $user, array $input ): array {
		$added   = array();
		$removed = array();
		$failed  = array();

		$sanitize_role_list = static function ( $value ): array {
			if ( ! is_array( $value ) ) {
				return array();
			}
			return array_values( array_filter( array_map( 'sanitize_key', $value ) ) );
		};

		if ( isset( $input['set_roles'] ) ) {
			$desired = $sanitize_role_list( $input['set_roles'] );
			if ( empty( $desired ) ) {
				return array(
					'added'   => $added,
					'removed' => $removed,
					'failed'  => $failed,
				);
			}

			foreach ( $desired as $slug ) {
				if ( null === get_role( $slug ) ) {
					$failed[] = $slug;
				}
			}
			if ( ! empty( $failed ) ) {
				return array(
					'added'   => $added,
					'removed' => $removed,
					'failed'  => $failed,
				);
			}

			$current = (array) $user->roles;
			foreach ( $current as $existing ) {
				$user->remove_role( $existing );
				$removed[] = $existing;
			}
			foreach ( $desired as $slug ) {
				$user->add_role( $slug );
				$added[] = $slug;
			}
			return array(
				'added'   => $added,
				'removed' => $removed,
				'failed'  => $failed,
			);
		}

		if ( ! empty( $input['add_roles'] ) ) {
			foreach ( $sanitize_role_list( $input['add_roles'] ) as $slug ) {
				if ( null === get_role( $slug ) ) {
					$failed[] = $slug;
					continue;
				}
				if ( ! in_array( $slug, (array) $user->roles, true ) ) {
					$user->add_role( $slug );
					$added[] = $slug;
				}
			}
		}

		if ( ! empty( $input['remove_roles'] ) ) {
			foreach ( $sanitize_role_list( $input['remove_roles'] ) as $slug ) {
				if ( in_array( $slug, (array) $user->roles, true ) ) {
					$user->remove_role( $slug );
					$removed[] = $slug;
				}
			}
		}

		return array(
			'added'   => $added,
			'removed' => $removed,
			'failed'  => $failed,
		);
	}
}
