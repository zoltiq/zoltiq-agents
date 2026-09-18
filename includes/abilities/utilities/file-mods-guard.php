<?php

namespace Zoltiq\Agents\Includes\Abilities\Utilities;

defined( 'ABSPATH' ) || exit;

final class File_Mods_Guard {

	public static function check( string $context = 'edit' ) {
		if ( ! self::file_mods_allowed( $context ) ) {
			return new \WP_Error(
				'file_mods_disabled',
				__( 'File modifications are disabled on this site (DISALLOW_FILE_MODS is set or blocked by the file_mod_allowed filter). Save to the database instead, or remove the restriction in wp-config.php.', 'zoltiq-agents' )
			);
		}

		if ( 'edit' === $context && defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) {
			return new \WP_Error(
				'file_edit_disabled',
				__( 'In-place file editing is disabled on this site (DISALLOW_FILE_EDIT is set). Save to the database instead, or remove the constant in wp-config.php.', 'zoltiq-agents' )
			);
		}

		return true;
	}

	public static function blocked_response( string $context = 'edit' ): ?array {
		$check = self::check( $context );
		if ( ! is_wp_error( $check ) ) {
			return null;
		}
		return array(
			'success' => false,
			'message' => $check->get_error_message(),
		);
	}

	private static function file_mods_allowed( string $context ): bool {
		if ( function_exists( 'wp_is_file_mod_allowed' ) ) {
			return (bool) wp_is_file_mod_allowed( 'zoltiq_core_abilities_' . $context );
		}
		return ! ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS );
	}
}
