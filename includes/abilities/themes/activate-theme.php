<?php

namespace Zoltiq\Agents\Includes\Abilities\Themes;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Theme_Helpers;

defined( 'ABSPATH' ) || exit;

class Activate_Theme extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/activate-theme',
			'args' => array(
				'label'               => __( 'Activate Theme', 'zoltiq-agents' ),
				'description'         => __( 'Activate an installed WordPress theme by name, stylesheet, or partial match. Works in recovery mode; only updates the active-theme option and does not load the theme file. Note: WordPress does not have a separate "deactivate-theme" ability — switching to a different theme via this ability is the way to remove an active theme.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-themes',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'theme'      => array(
							'type'        => 'string',
							'description' => __( 'Theme name, stylesheet directory (e.g. twentytwentyfour), or partial match.', 'zoltiq-agents' ),
						),
						'slug'       => array(
							'type'        => 'string',
							'description' => __( 'Alias for "theme". If multiple are provided, "theme" wins, then "stylesheet", then "slug".', 'zoltiq-agents' ),
						),
						'stylesheet' => array(
							'type'        => 'string',
							'description' => __( 'Alias for "theme" (matches WordPress core terminology).', 'zoltiq-agents' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'       => array( 'type' => 'boolean' ),
						'message'       => array( 'type' => 'string' ),
						'matched_theme' => array( 'type' => 'string' ),
						'certainty'     => array( 'type' => 'number' ),
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
		$raw_theme = ! empty( $input['theme'] )
			? $input['theme']
			: ( ! empty( $input['stylesheet'] ) ? $input['stylesheet'] : ( $input['slug'] ?? '' ) );

		if ( empty( $raw_theme ) ) {
			return array(
				'success' => false,
				'message' => __( 'No theme specified. Pass "theme" (or its aliases "stylesheet"/"slug").', 'zoltiq-agents' ),
			);
		}

		return Theme_Helpers::activate_theme_by_slug( $raw_theme );
	}
}
