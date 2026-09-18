<?php

namespace Zoltiq\Agents\Includes\Abilities\Plugins;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Read_Plugin_Code extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/read-plugin-code',
			'args' => array(
				'label'               => __( 'Read Plugin Code', 'zoltiq-agents' ),
				'description'         => __( 'Reads the contents of a file inside a plugin directory.', 'zoltiq-agents' ),
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
							'description' => __( 'Plugin folder name.', 'zoltiq-agents' ),
						),
						'plugin'      => array(
							'type'        => 'string',
							'description' => __( 'Alias for "plugin_slug". If multiple are provided, "plugin_slug" wins, then "plugin", then "slug".', 'zoltiq-agents' ),
						),
						'slug'        => array(
							'type'        => 'string',
							'description' => __( 'Alias for "plugin_slug". If multiple are provided, "plugin_slug" wins.', 'zoltiq-agents' ),
						),
						'file_path'   => array(
							'type'        => 'string',
							'description' => __( 'File path relative to the plugin root (e.g. includes/class-main.php).', 'zoltiq-agents' ),
						),
						'path'        => array(
							'type'        => 'string',
							'description' => __( 'Alias for "file_path". If both are provided, "file_path" wins.', 'zoltiq-agents' ),
						),
					),
					'allOf'                => array(
						array(
							'anyOf' => array(
								array( 'required' => array( 'plugin_slug' ) ),
								array( 'required' => array( 'plugin' ) ),
								array( 'required' => array( 'slug' ) ),
							),
						),
						array(
							'anyOf' => array(
								array( 'required' => array( 'file_path' ) ),
								array( 'required' => array( 'path' ) ),
							),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'content' => array( 'type' => 'string' ),
						'path'    => array( 'type' => 'string' ),
						'size'    => array( 'type' => 'integer' ),
						'message' => array( 'type' => 'string' ),
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
		$raw_slug    = ! empty( $input['plugin_slug'] )
			? $input['plugin_slug']
			: ( ! empty( $input['plugin'] ) ? $input['plugin'] : ( $input['slug'] ?? '' ) );
		$slug        = sanitize_text_field( (string) $raw_slug );
		$raw_file    = ! empty( $input['file_path'] ) ? $input['file_path'] : ( $input['path'] ?? '' );
		$rel_file    = sanitize_text_field( (string) $raw_file );
		$plugins_dir = rtrim( WP_PLUGIN_DIR, '/' );
		$plugin_path = realpath( $plugins_dir . '/' . $slug );

		if ( false === $plugin_path || 0 !== strpos( $plugin_path, $plugins_dir . '/' ) || ! is_dir( $plugin_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'Plugin directory not found.', 'zoltiq-agents' ),
			);
		}

		$abs_file = realpath( $plugin_path . '/' . ltrim( $rel_file, '/' ) );

		if ( false === $abs_file || 0 !== strpos( $abs_file, $plugin_path . '/' ) || ! is_file( $abs_file ) ) {
			return array(
				'success' => false,
				'message' => __( 'File not found within plugin directory.', 'zoltiq-agents' ),
			);
		}

		$content = file_get_contents( $abs_file ); 

		if ( false === $content ) {
			return array(
				'success' => false,
				'message' => __( 'Could not read file.', 'zoltiq-agents' ),
			);
		}

		return array(
			'success' => true,
			'content' => $content,
			'path'    => $abs_file,
			'size'    => strlen( $content ),
		);
	}
}
