<?php

namespace Zoltiq\Agents\Includes\Abilities\Settings;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Flush_Permalink_Structure extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/flush-permalink-structure',
			'args' => array(
				'label'               => __( 'Reset / Flush Permalinks', 'zoltiq-agents' ),
				'description'         => __( 'Rebuilds WordPress rewrite rules — useful after registering custom post types, taxonomies, or rewrite endpoints. Pass hard=true to also regenerate .htaccess (Apache) where supported.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-settings',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'hard' => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => __( 'Regenerate .htaccess on Apache (and equivalent on IIS).', 'zoltiq-agents' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'   => array( 'type' => 'boolean' ),
						'message'   => array( 'type' => 'string' ),
						'hard'      => array( 'type' => 'boolean' ),
						'structure' => array( 'type' => 'string' ),
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
		$hard = ! empty( $input['hard'] );

		flush_rewrite_rules( $hard );

		return array(
			'success'   => true,
			'message'   => $hard
				? __( 'Rewrite rules flushed and .htaccess regenerated where supported.', 'zoltiq-agents' )
				: __( 'Rewrite rules flushed.', 'zoltiq-agents' ),
			'hard'      => $hard,
			'structure' => (string) get_option( 'permalink_structure', '' ),
		);
	}
}
