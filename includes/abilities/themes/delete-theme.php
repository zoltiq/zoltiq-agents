<?php

namespace Zoltiq\Agents\Includes\Abilities\Themes;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\File_Mods_Guard;
use Zoltiq\Agents\Includes\Abilities\Utilities\Theme_Helpers;

defined( 'ABSPATH' ) || exit;

class Delete_Theme extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/delete-theme',
			'args' => array(
				'label'               => __( 'Delete Theme', 'zoltiq-agents' ),
				'description'         => __( 'Delete an installed WordPress theme by name, stylesheet, or partial match. The active theme cannot be deleted.', 'zoltiq-agents' ),
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
					'anyOf'                => array(
						array( 'required' => array( 'theme' ) ),
						array( 'required' => array( 'stylesheet' ) ),
						array( 'required' => array( 'slug' ) ),
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

		$raw_theme = ! empty( $input['theme'] )
			? $input['theme']
			: ( ! empty( $input['stylesheet'] ) ? $input['stylesheet'] : ( $input['slug'] ?? '' ) );

		if ( empty( $raw_theme ) ) {
			return array(
				'success' => false,
				'message' => __( 'No theme specified. Pass "theme" (or its aliases "stylesheet"/"slug").', 'zoltiq-agents' ),
			);
		}

		return Theme_Helpers::delete_theme_by_slug( $raw_theme );
	}
}
