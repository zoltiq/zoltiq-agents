<?php

namespace Zoltiq\Agents\Includes\Abilities\Users;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\User_Helpers;

defined( 'ABSPATH' ) || exit;

class Reset_User_Password extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/reset-user-password',
			'args' => array(
				'label'               => __( 'Reset User Password', 'zoltiq-agents' ),
				'description'         => __( 'Send a password reset email to a user, or set a new password directly. Email notification is configurable.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-users',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'user'       => array(
							'type'        => array( 'string', 'integer' ),
							'description' => __( 'User ID, login, email, or slug.', 'zoltiq-agents' ),
						),
						'method'     => array(
							'type'        => 'string',
							'enum'        => array( 'email', 'direct' ),
							'default'     => 'email',
							'description' => __( 'How to reset: "email" generates a reset link; "direct" sets a new password immediately.', 'zoltiq-agents' ),
						),
						'password'   => array(
							'type'        => 'string',
							'description' => __( 'New password (only used when method=direct). Auto-generated if omitted.', 'zoltiq-agents' ),
						),
						'send_email' => array(
							'type'        => 'boolean',
							'default'     => true,
							'description' => __( 'Whether to email the user. With method=email, false returns the reset link in the response instead. With method=direct, false skips the password-change notice.', 'zoltiq-agents' ),
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
						'method'             => array( 'type' => 'string' ),
						'user_id'            => array( 'type' => 'integer' ),
						'email_sent'         => array( 'type' => 'boolean' ),
						'reset_link'         => array( 'type' => 'string' ),
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
						'destructive' => true,
						'idempotent'  => false,
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

		$method = isset( $input['method'] ) ? sanitize_text_field( $input['method'] ) : 'email';
		if ( ! in_array( $method, array( 'email', 'direct' ), true ) ) {
			$method = 'email';
		}

		$send_email = array_key_exists( 'send_email', $input ) ? (bool) $input['send_email'] : true;

		if ( 'direct' === $method ) {
			return $this->reset_direct( $user, $input, $send_email );
		}

		return $this->reset_via_email( $user, $send_email );
	}

	private function reset_direct( \WP_User $user, array $input, bool $send_email ): array {
		$generated = false;
		$password  = $input['password'] ?? '';
		if ( '' === $password ) {
			$password  = wp_generate_password( 16, true, true );
			$generated = true;
		}

		wp_set_password( $password, (int) $user->ID );

		$email_sent = false;
		if ( $send_email ) {
			$email_sent = $this->send_password_changed_notice( $user, $generated ? $password : '' );
		}

		$response = array(
			'success'    => true,
			'message'    => sprintf( __( 'Password for "%s" has been updated.', 'zoltiq-agents' ), $user->user_login ),
			'method'     => 'direct',
			'user_id'    => (int) $user->ID,
			'email_sent' => $email_sent,
		);

		if ( $send_email && ! $email_sent ) {
			$response['message'] .= ' ' . sprintf( __( '(Notification email to %s failed to send.)', 'zoltiq-agents' ), $user->user_email );
		}

		if ( $generated ) {
			$response['generated_password'] = $password;
		}

		return $response;
	}

	private function reset_via_email( \WP_User $user, bool $send_email ): array {
		if ( $send_email ) {
			$result = retrieve_password( $user->user_login );

			if ( is_wp_error( $result ) ) {
				return array(
					'success'    => false,
					'message'    => sprintf( __( 'Failed to send reset email: %s', 'zoltiq-agents' ), $result->get_error_message() ),
					'method'     => 'email',
					'user_id'    => (int) $user->ID,
					'email_sent' => false,
				);
			}

			return array(
				'success'    => true,
				'message'    => sprintf( __( 'Password reset email sent to %s.', 'zoltiq-agents' ), $user->user_email ),
				'method'     => 'email',
				'user_id'    => (int) $user->ID,
				'email_sent' => true,
			);
		}

		$key = get_password_reset_key( $user );

		if ( is_wp_error( $key ) ) {
			return array(
				'success'    => false,
				'message'    => sprintf( __( 'Failed to generate reset link: %s', 'zoltiq-agents' ), $key->get_error_message() ),
				'method'     => 'email',
				'user_id'    => (int) $user->ID,
				'email_sent' => false,
			);
		}

		$reset_link = network_site_url(
			'wp-login.php?action=rp&key=' . rawurlencode( $key ) . '&login=' . rawurlencode( $user->user_login ),
			'login'
		);

		return array(
			'success'    => true,
			'message'    => sprintf( __( 'Password reset link generated for "%s" (no email sent).', 'zoltiq-agents' ), $user->user_login ),
			'method'     => 'email',
			'user_id'    => (int) $user->ID,
			'email_sent' => false,
			'reset_link' => $reset_link,
		);
	}


	private function send_password_changed_notice( \WP_User $user, string $generated_password ): bool {
		$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );

		$subject = sprintf( __( '[%s] Your password has been changed', 'zoltiq-agents' ), $blogname );

		$display_name = $user->display_name ? $user->display_name : $user->user_login;

		$message = sprintf( __( 'Hi %s,', 'zoltiq-agents' ), $display_name ) . "\r\n\r\n";
		$message .= sprintf( __( 'Your password on %s has been reset by an administrator.', 'zoltiq-agents' ), home_url() ) . "\r\n\r\n";

		if ( '' !== $generated_password ) {
			$message .= sprintf( __( 'Your new password is: %s', 'zoltiq-agents' ), $generated_password ) . "\r\n\r\n";
			$message .= __( 'Please log in and change it as soon as possible.', 'zoltiq-agents' ) . "\r\n\r\n";
		}

		$message .= __( 'If you did not expect this change, please contact the site administrator immediately.', 'zoltiq-agents' ) . "\r\n";

		return (bool) wp_mail( $user->user_email, $subject, $message );
	}
}
