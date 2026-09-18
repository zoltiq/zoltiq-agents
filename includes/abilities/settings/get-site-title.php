<?php

namespace Zoltiq\Agents\Includes\Abilities\Settings;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Get_Site_Title extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/get-site-title',
			'args' => array(
				'label'               => __( 'Get Site Title', 'zoltiq-agents' ),
				'description'         => __( 'Returns the current site title (the "blogname" option, displayed in the browser tab and across the site).', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-settings',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'title'   => array( 'type' => 'string' ),
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
		return array(
			'success' => true,
			'title'   => wp_specialchars_decode( (string) get_option( 'blogname', '' ), ENT_QUOTES ),
		);
	}
}
