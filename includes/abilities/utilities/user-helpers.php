<?php

namespace Zoltiq\Agents\Includes\Abilities\Utilities;

defined( 'ABSPATH' ) || exit;

class User_Helpers {

	public static function resolve_user( $identifier ): ?\WP_User {
		if ( is_numeric( $identifier ) ) {
			$user = get_user_by( 'id', (int) $identifier );
			if ( $user ) {
				return $user;
			}
		}

		if ( ! is_string( $identifier ) ) {
			return null;
		}

		$identifier = trim( $identifier );
		if ( '' === $identifier ) {
			return null;
		}

		if ( str_contains( $identifier, '@' ) ) {
			$user = get_user_by( 'email', $identifier );
			if ( $user ) {
				return $user;
			}
		}

		$user = get_user_by( 'login', $identifier );
		if ( $user ) {
			return $user;
		}

		$user = get_user_by( 'slug', $identifier );
		if ( $user ) {
			return $user;
		}

		return null;
	}

	public static function format_user( \WP_User $user, array $opts = array() ): array {
		$data = array(
			'id'           => (int) $user->ID,
			'login'        => $user->user_login,
			'email'        => $user->user_email,
			'display_name' => $user->display_name,
			'first_name'   => (string) $user->first_name,
			'last_name'    => (string) $user->last_name,
			'slug'         => $user->user_nicename,
			'url'          => $user->user_url,
			'registered'   => $user->user_registered,
			'roles'        => array_values( (array) $user->roles ),
		);

		if ( ! empty( $opts['include_meta'] ) ) {
			$keys         = isset( $opts['meta_keys'] ) && is_array( $opts['meta_keys'] ) ? $opts['meta_keys'] : array();
			$data['meta'] = self::get_all_meta( (int) $user->ID, $keys );
		}

		if ( ! empty( $opts['include_sessions'] ) ) {
			$data['sessions'] = self::get_sessions( (int) $user->ID );
		}

		return $data;
	}

	public static function get_all_meta( int $user_id, array $keys = array() ): array {
		if ( ! empty( $keys ) ) {
			$out = array();
			foreach ( $keys as $key ) {
				$key = sanitize_text_field( (string) $key );
				if ( '' === $key ) {
					continue;
				}
				$out[ $key ] = get_user_meta( $user_id, $key, true );
			}
			return $out;
		}

		$all  = get_user_meta( $user_id );
		$flat = array();
		if ( ! is_array( $all ) ) {
			return $flat;
		}
		foreach ( $all as $key => $values ) {
			if ( '' === $key ) {
				continue;
			}
			$values = (array) $values;
			if ( 1 === count( $values ) ) {
				$flat[ $key ] = maybe_unserialize( $values[0] );
			} else {
				$flat[ $key ] = array_map( 'maybe_unserialize', $values );
			}
		}
		return $flat;
	}

	public static function apply_meta( int $user_id, array $meta ): array {
		$updated = array();
		$failed  = array();
		foreach ( $meta as $key => $value ) {
			$key = sanitize_text_field( (string) $key );
			if ( '' === $key ) {
				continue;
			}
			$value  = self::maybe_decode_json( $value );
			$result = update_user_meta( $user_id, $key, $value );
			if ( false === $result ) {
				$failed[] = $key;
			} else {
				$updated[] = $key;
			}
		}
		return array(
			'updated' => $updated,
			'failed'  => $failed,
		);
	}

	public static function delete_meta( int $user_id, array $keys ): array {
		$deleted = array();
		$failed  = array();
		foreach ( $keys as $key ) {
			$key = sanitize_text_field( (string) $key );
			if ( '' === $key ) {
				continue;
			}
			if ( delete_user_meta( $user_id, $key ) ) {
				$deleted[] = $key;
			} else {
				$failed[] = $key;
			}
		}
		return array(
			'deleted' => $deleted,
			'failed'  => $failed,
		);
	}

	public static function get_sessions( int $user_id ): array {
		$manager = \WP_Session_Tokens::get_instance( $user_id );
		$out     = array();
		foreach ( $manager->get_all() as $session ) {
			$out[] = array(
				'login'      => isset( $session['login'] ) ? (int) $session['login'] : 0,
				'expiration' => isset( $session['expiration'] ) ? (int) $session['expiration'] : 0,
				'ip'         => isset( $session['ip'] ) ? (string) $session['ip'] : '',
				'ua'         => isset( $session['ua'] ) ? (string) $session['ua'] : '',
			);
		}
		return $out;
	}

	public static function destroy_sessions( int $user_id ): int {
		$manager = \WP_Session_Tokens::get_instance( $user_id );
		$count   = count( $manager->get_all() );
		$manager->destroy_all();
		return $count;
	}

	public static function maybe_decode_json( $value ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}

		$trimmed = ltrim( $value );
		if ( '' === $trimmed || ( '{' !== $trimmed[0] && '[' !== $trimmed[0] ) ) {
			return $value;
		}

		$decoded = json_decode( $value, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return $value;
		}

		return $decoded;
	}
}
