<?php

namespace Zoltiq\Agents\Includes\Abilities\Users;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Create_User extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/create-user',
			'args' => array(
				'label'               => __( 'Create User', 'zoltiq-agents' ),
				'description'         => __( 'Create a new WordPress user. If no password is provided, a strong one is generated and returned.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-users',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'username'   => array(
							'type'        => 'string',
							'description' => __( 'Username (login) for the new user.', 'zoltiq-agents' ),
						),
						'email'      => array(
							'type'        => 'string',
							'description' => __( 'Email address for the new user.', 'zoltiq-agents' ),
						),
						'password'   => array(
							'type'        => 'string',
							'description' => __( 'Optional password. Auto-generated if omitted.', 'zoltiq-agents' ),
						),
						'first_name' => array(
							'type'        => 'string',
							'description' => __( 'First name.', 'zoltiq-agents' ),
						),
						'last_name'  => array(
							'type'        => 'string',
							'description' => __( 'Last name.', 'zoltiq-agents' ),
						),
						'role'       => array(
							'type'        => 'string',
							'description' => __( 'Role slug to assign (defaults to default_role option).', 'zoltiq-agents' ),
						),
					),
					'required'             => array( 'username', 'email' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'            => array( 'type' => 'boolean' ),
						'message'            => array( 'type' => 'string' ),
						'user_id'            => array( 'type' => 'integer' ),
						'user_login'         => array( 'type' => 'string' ),
						'generated_password' => array( 'type' => 'string' ),
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
						'idempotent'  => false,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		if ( empty( $input['username'] ) || empty( $input['email'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Both "username" and "email" are required.', 'zoltiq-agents' ),
			);
		}

		$username = sanitize_user( $input['username'], true );
		if ( '' === $username || ! validate_username( $username ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid username.', 'zoltiq-agents' ),
			);
		}

		$email = sanitize_email( $input['email'] );
		if ( '' === $email || ! is_email( $email ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid email address.', 'zoltiq-agents' ),
			);
		}

		if ( username_exists( $username ) ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Username "%s" already exists.', 'zoltiq-agents' ), $username ),
			);
		}

		if ( email_exists( $email ) ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Email "%s" is already registered.', 'zoltiq-agents' ), $email ),
			);
		}

		$generated = false;
		$password  = $input['password'] ?? '';
		if ( '' === $password ) {
			$password  = wp_generate_password( 16, true, true );
			$generated = true;
		}

		$user_data = array(
			'user_login' => $username,
			'user_email' => $email,
			'user_pass'  => $password,
		);

		if ( ! empty( $input['first_name'] ) ) {
			$user_data['first_name'] = sanitize_text_field( $input['first_name'] );
		}
		if ( ! empty( $input['last_name'] ) ) {
			$user_data['last_name'] = sanitize_text_field( $input['last_name'] );
		}
		if ( ! empty( $input['role'] ) ) {
			$role = sanitize_key( $input['role'] );
			if ( null === get_role( $role ) ) {
				return array(
					'success' => false,
					'message' => sprintf( __( 'Role "%s" does not exist.', 'zoltiq-agents' ), $role ),
				);
			}
			$user_data['role'] = $role;
		}

		$user_id = wp_insert_user( $user_data );

		if ( is_wp_error( $user_id ) ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Failed to create user: %s', 'zoltiq-agents' ), $user_id->get_error_message() ),
			);
		}

		$response = array(
			'success'    => true,
			'message'    => sprintf( __( 'User "%s" created successfully.', 'zoltiq-agents' ), $username ),
			'user_id'    => (int) $user_id,
			'user_login' => $username,
		);

		if ( $generated ) {
			$response['generated_password'] = $password;
		}

		return $response;
	}
}
