<?php

namespace Zoltiq\Agents\Includes\Abilities\Core;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\File_Mods_Guard;

defined( 'ABSPATH' ) || exit;

class Update_Wp_Core extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/update-wp-core',
			'args' => array(
				'label'               => __( 'Update WordPress Core', 'zoltiq-agents' ),
				'description'         => __( 'Apply the pending WordPress core update via WP core\'s Core_Upgrader. When called with no arguments, upgrades to the latest available offer. Provide "version" (+ optional "locale") to pin to a specific offer. Re-running when no update is available is a clean no-op. Honours DISALLOW_FILE_MODS.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-core',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' ) && current_user_can( 'update_core' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'properties'           => array(
						'version' => array(
							'type'        => 'string',
							'description' => __( 'Optional WordPress version to pin the upgrade to (e.g. "6.9.1"). When omitted, upgrades to the latest available offer.', 'zoltiq-agents' ),
						),
						'locale'  => array(
							'type'        => 'string',
							'description' => __( 'Optional locale of the update offer to pin to (e.g. "en_US"). When omitted, defaults to the site\'s active locale from get_locale().', 'zoltiq-agents' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'      => array( 'type' => 'boolean' ),
						'updated'      => array( 'type' => 'boolean' ),
						'from_version' => array( 'type' => 'string' ),
						'to_version'   => array( 'type' => 'string' ),
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
		$blocked = File_Mods_Guard::blocked_response( 'install' );
		if ( null !== $blocked ) {
			return $blocked;
		}

		if ( is_multisite() && ! current_user_can( 'update_core' ) ) {
			return array(
				'success' => false,
				'updated' => false,
				'message' => __( 'Core updates on multisite require the update_core capability at the network level.', 'zoltiq-agents' ),
			);
		}

		if ( ! function_exists( 'get_core_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}
		if ( ! class_exists( '\Core_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';

		$from_version = (string) get_bloginfo( 'version' );

		$version = isset( $input['version'] ) ? sanitize_text_field( (string) $input['version'] ) : '';
		$locale  = isset( $input['locale'] ) ? sanitize_text_field( (string) $input['locale'] ) : '';

		if ( '' !== $version ) {
			$update = find_core_update( $version, '' !== $locale ? $locale : get_locale() );
		} else {
			$updates = get_core_updates();
			$update  = ( is_array( $updates ) && ! empty( $updates ) && is_object( $updates[0] ) ) ? $updates[0] : null;
		}

		if ( null === $update || false === $update ) {
			return array(
				'success'      => true,
				'updated'      => false,
				'from_version' => $from_version,
				'to_version'   => $from_version,
				'message'      => '' !== $version
					? sprintf(
						__( 'No core update offer found for version "%s".', 'zoltiq-agents' ),
						$version
					)
					: __( 'No core update available.', 'zoltiq-agents' ),
			);
		}

		$response = isset( $update->response ) ? (string) $update->response : '';
		if ( 'upgrade' !== $response ) {
			return array(
				'success'      => true,
				'updated'      => false,
				'from_version' => $from_version,
				'to_version'   => $from_version,
				'message'      => sprintf(
					__( 'Core offer is not upgradable (response=%s).', 'zoltiq-agents' ),
					'' !== $response ? $response : 'unknown'
				),
			);
		}

		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Core_Upgrader( $skin );
		$result   = $upgrader->upgrade( $update );

		$to_version = (string) get_bloginfo( 'version' );

		if ( is_wp_error( $result ) ) {
			return array(
				'success'      => true,
				'updated'      => false,
				'from_version' => $from_version,
				'to_version'   => $to_version,
				'message'      => $result->get_error_message(),
			);
		}

		if ( null === $result || false === $result ) {
			$skin_errors = $skin->get_errors();
			$msg         = is_wp_error( $skin_errors ) && $skin_errors->has_errors()
				? $skin_errors->get_error_message()
				: __( 'Core upgrade did not report success.', 'zoltiq-agents' );
			return array(
				'success'      => true,
				'updated'      => false,
				'from_version' => $from_version,
				'to_version'   => $to_version,
				'message'      => $msg,
			);
		}

		return array(
			'success'      => true,
			'updated'      => true,
			'from_version' => $from_version,
			'to_version'   => $to_version,
			'message'      => sprintf(
				__( 'WordPress upgraded from %1$s to %2$s.', 'zoltiq-agents' ),
				$from_version,
				$to_version
			),
		);
	}
}
