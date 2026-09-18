<?php

namespace Zoltiq\Agents\Includes\Abilities\Menus;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Get_Menu extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/get-menu',
			'args' => array(
				'label'               => __( 'Get Menu', 'zoltiq-agents' ),
				'description'         => __( 'Fetch a nav menu via GET /wp/v2/menus/{id}.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-menus',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id' => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'menu'    => array( 'type' => 'object' ),
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
		$id = (int) ( $input['id'] ?? 0 );
		if ( $id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'A valid id is required.', 'zoltiq-agents' ),
			);
		}

		$menu = wp_get_nav_menu_object( $id );
		if ( ! ( $menu instanceof \WP_Term ) ) {
			return array(
				'success' => false,
				'message' => __( 'Menu not found.', 'zoltiq-agents' ),
			);
		}

		return array(
			'success' => true,
			'menu'    => Menu_Formatter::menu_to_array( $menu ),
		);
	}
}
