<?php

namespace Zoltiq\Agents\Includes\Abilities\Content;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Jet_Engine_Helpers;

defined( 'ABSPATH' ) || exit;

class Get_Jet_Engine_Options_Page extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/get-jet-engine-options-page',
			'args' => array(
				'label'               => __( 'Get Options Page', 'zoltiq-agents' ),
				'description'         => __( 'Return a Jet Engine options page by slug, including the stored field values from wp_options.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-content',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'slug' => array( 'type' => 'string' ),
					),
					'required'             => array( 'slug' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'page'    => array( 'type' => 'object' ),
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
		$slug = sanitize_key( (string) ( $input['slug'] ?? '' ) );
		if ( '' === $slug ) {
			return array(
				'success' => false,
				'message' => __( 'slug is required.', 'zoltiq-agents' ),
			);
		}

		$page = Jet_Engine_Helpers::get_page( $slug );
		if ( is_wp_error( $page ) ) {
			return array(
				'success' => false,
				'message' => $page->get_error_message(),
			);
		}

		return array(
			'success' => true,
			'page'    => $page,
		);
	}
}
