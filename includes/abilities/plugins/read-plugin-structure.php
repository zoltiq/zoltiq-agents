<?php

namespace Zoltiq\Agents\Includes\Abilities\Plugins;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Read_Plugin_Structure extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/read-plugin-structure',
			'args' => array(
				'label'               => __( 'Read Plugin Structure', 'zoltiq-agents' ),
				'description'         => __( 'Lists all files within a plugin directory. Provide a plugin slug (folder name) or leave empty to list top-level plugin directories.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-plugins',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'plugin_slug' => array(
							'type'        => 'string',
							'default'     => '',
							'description' => __( 'Plugin folder name (e.g. woocommerce). Leave empty to list all plugin directories.', 'zoltiq-agents' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'     => array( 'type' => 'boolean' ),
						'plugin_path' => array( 'type' => 'string' ),
						'files'       => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
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
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		$slug        = sanitize_text_field( $input['plugin_slug'] ?? '' );
		$plugins_dir = rtrim( WP_PLUGIN_DIR, '/' );

		if ( '' === $slug ) {
			$dirs = glob( $plugins_dir . '/*', GLOB_ONLYDIR );
			return array(
				'success'     => true,
				'plugin_path' => $plugins_dir,
				'files'       => array_map( 'basename', $dirs ?: array() ),
			);
		}

		$plugin_path = realpath( $plugins_dir . '/' . $slug );

		if ( false === $plugin_path || 0 !== strpos( $plugin_path, $plugins_dir ) || ! is_dir( $plugin_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'Plugin directory not found.', 'zoltiq-agents' ),
			);
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $plugin_path, \FilesystemIterator::SKIP_DOTS )
		);

		$files = array();
		foreach ( $iterator as $file ) {
			if ( $file->isFile() ) {
				$files[] = str_replace( $plugin_path . '/', '', $file->getPathname() );
			}
		}

		sort( $files );

		return array(
			'success'     => true,
			'plugin_path' => $plugin_path,
			'files'       => $files,
		);
	}
}
