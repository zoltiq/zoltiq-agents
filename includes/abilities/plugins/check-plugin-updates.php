<?php

namespace Zoltiq\Agents\Includes\Abilities\Plugins;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Check_Plugin_Updates extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/check-plugin-updates',
			'args' => array(
				'label'               => __( 'Check Updates', 'zoltiq-agents' ),
				'description'         => __( 'Check for available WordPress core, plugin, and theme updates.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-plugins',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'core'    => array(
							'type'        => 'object',
							'description' => __( 'Core update info.', 'zoltiq-agents' ),
						),
						'plugins' => array(
							'type'        => 'array',
							'description' => __( 'Plugins with available updates.', 'zoltiq-agents' ),
						),
						'themes'  => array(
							'type'        => 'array',
							'description' => __( 'Themes with available updates.', 'zoltiq-agents' ),
						),
						'total'   => array(
							'type'        => 'integer',
							'description' => __( 'Total number of available updates.', 'zoltiq-agents' ),
						),
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
		if ( ! function_exists( 'get_core_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$core_updates = get_core_updates();
		$core_info    = array(
			'current'     => get_bloginfo( 'version' ),
			'available'   => false,
			'new_version' => '',
		);

		if ( ! empty( $core_updates ) && 'upgrade' === $core_updates[0]->response ) {
			$core_info['available']   = true;
			$core_info['new_version'] = $core_updates[0]->version;
		}

		$plugin_updates  = get_plugin_updates();
		$plugins_needing = array();

		foreach ( $plugin_updates as $file => $data ) {
			$plugins_needing[] = array(
				'name'        => $data->Name,
				'slug'        => $file,
				'current'     => $data->Version,
				'new_version' => $data->update->new_version,
			);
		}

		$theme_updates  = get_theme_updates();
		$themes_needing = array();

		foreach ( $theme_updates as $slug => $theme ) {
			$themes_needing[] = array(
				'name'        => $theme->get( 'Name' ),
				'slug'        => $slug,
				'current'     => $theme->get( 'Version' ),
				'new_version' => $theme->update['new_version'],
			);
		}

		$total = ( $core_info['available'] ? 1 : 0 ) + count( $plugins_needing ) + count( $themes_needing );

		return array(
			'core'    => $core_info,
			'plugins' => $plugins_needing,
			'themes'  => $themes_needing,
			'total'   => $total,
		);
	}
}
