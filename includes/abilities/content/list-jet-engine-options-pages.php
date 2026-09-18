<?php

namespace Zoltiq\Agents\Includes\Abilities\Content;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Jet_Engine_Helpers;

defined( 'ABSPATH' ) || exit;

class List_Jet_Engine_Options_Pages extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/list-jet-engine-options-pages',
			'args' => array(
				'label'               => __( 'List Options Pages', 'zoltiq-agents' ),
				'description'         => __( 'List Jet Engine options pages (slug, name, option key, fields). Requires Jet Engine to be active.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-content',
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
						'pages'   => array( 'type' => 'array' ),
						'total'   => array( 'type' => 'integer' ),
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
		$pages = Jet_Engine_Helpers::get_pages();
		if ( is_wp_error( $pages ) ) {
			return array(
				'success' => false,
				'message' => $pages->get_error_message(),
			);
		}

		return array(
			'success' => true,
			'pages'   => $pages,
			'total'   => count( $pages ),
		);
	}
}
