<?php

namespace Zoltiq\Agents\Includes\Abilities\Settings;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Get_Tagline extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/get-tagline',
			'args' => array(
				'label'               => __( 'Get Tagline', 'zoltiq-agents' ),
				'description'         => __( 'Returns the current site tagline (the "blogdescription" option — a short description shown beside the site title in many themes).', 'zoltiq-agents' ),
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
						'tagline' => array( 'type' => 'string' ),
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
			'tagline' => wp_specialchars_decode( (string) get_option( 'blogdescription', '' ), ENT_QUOTES ),
		);
	}
}
