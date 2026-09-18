<?php

namespace Zoltiq\Agents\Includes\Abilities\Core;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\File_Mods_Guard;

defined( 'ABSPATH' ) || exit;

class Reinstall_Wp_Core extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/reinstall-wp-core',
			'args' => array(
				'label'               => __( 'Reinstall WordPress Core', 'zoltiq-agents' ),
				'description'         => __( 'Reinstall the currently installed WordPress version by re-downloading and re-applying the same release via WP core\'s Core_Upgrader with response="reinstall". Equivalent to the wp-admin "Re-install version X" action at /wp-admin/update-core.php?action=do-core-reinstall. Honours DISALLOW_FILE_MODS. Idempotent — safe to re-run.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-core',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' ) && current_user_can( 'update_core' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'properties'           => array(
						'locale' => array(
							'type'        => 'string',
							'description' => __( 'Optional locale of the reinstall offer to pin to (e.g. "en_US"). When omitted, defaults to the site\'s active locale from get_locale().', 'zoltiq-agents' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'     => array( 'type' => 'boolean' ),
						'reinstalled' => array( 'type' => 'boolean' ),
						'version'     => array( 'type' => 'string' ),
						'message'     => array( 'type' => 'string' ),
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
						'destructive' => true,
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
				'success'     => false,
				'reinstalled' => false,
				'message'     => __( 'Core reinstall on multisite requires the update_core capability at the network level.', 'zoltiq-agents' ),
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

		$current_version = (string) get_bloginfo( 'version' );

		$locale = isset( $input['locale'] ) ? sanitize_text_field( (string) $input['locale'] ) : '';
		if ( '' === $locale ) {
			$locale = get_locale();
		}

		$update = find_core_update( $current_version, $locale );

		if ( null === $update || false === $update ) {
			return array(
				'success'     => true,
				'reinstalled' => false,
				'version'     => $current_version,
				'message'     => __( 'No core update offer available for reinstall. Run wp-core-update-check first to refresh the update_core transient.', 'zoltiq-agents' ),
			);
		}

		$update->response = 'reinstall';

		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Core_Upgrader( $skin );
		$result   = $upgrader->upgrade(
			$update,
			array(
				'allow_relaxed_file_ownership' => false,
			)
		);

		if ( is_wp_error( $result ) ) {
			return array(
				'success'     => true,
				'reinstalled' => false,
				'version'     => $current_version,
				'message'     => $result->get_error_message(),
			);
		}

		if ( null === $result || false === $result ) {
			$skin_errors = $skin->get_errors();
			$msg         = is_wp_error( $skin_errors ) && $skin_errors->has_errors()
				? $skin_errors->get_error_message()
				: __( 'Core reinstall did not report success.', 'zoltiq-agents' );
			return array(
				'success'     => true,
				'reinstalled' => false,
				'version'     => $current_version,
				'message'     => $msg,
			);
		}

		return array(
			'success'     => true,
			'reinstalled' => true,
			'version'     => $current_version,
			'message'     => sprintf(
				__( 'WordPress %s reinstalled.', 'zoltiq-agents' ),
				$current_version
			),
		);
	}
}
