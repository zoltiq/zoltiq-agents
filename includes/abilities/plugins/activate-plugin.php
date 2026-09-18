<?php

namespace Zoltiq\Agents\Includes\Abilities\Plugins;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Plugin_Helpers;

defined( 'ABSPATH' ) || exit;

class Activate_Plugin extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/activate-plugin',
			'args' => array(
				'label'               => __( 'Activate Plugin', 'zoltiq-agents' ),
				'description'         => __( 'Activate an installed WordPress plugin by name, slug, or partial match. Works in recovery mode; only updates the active-plugins option and does not load the plugin file.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-plugins',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'plugin' => array(
							'type'        => 'string',
							'description' => __( 'Plugin name, file path (e.g. akismet/akismet.php), or partial match.', 'zoltiq-agents' ),
						),
						'slug'   => array(
							'type'        => 'string',
							'description' => __( 'Alias for "plugin". If both are provided, "plugin" wins.', 'zoltiq-agents' ),
						),
					),
					
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'        => array( 'type' => 'boolean' ),
						'message'        => array( 'type' => 'string' ),
						'matched_plugin' => array( 'type' => 'string' ),
						'certainty'      => array( 'type' => 'number' ),
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
		$raw_plugin = ! empty( $input['plugin'] ) ? $input['plugin'] : ( $input['slug'] ?? '' );

		if ( empty( $raw_plugin ) ) {
			return array(
				'success' => false,
				'message' => __( 'No plugin specified. Pass "plugin" (or its alias "slug").', 'zoltiq-agents' ),
			);
		}

		return Plugin_Helpers::activate_plugin_by_slug( $raw_plugin );
	}
}
