<?php

namespace Zoltiq\Agents\Includes\Abilities\Menus;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Get_Menu_Item extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/get-menu-item',
			'args' => array(
				'label'               => __( 'Get Menu Item', 'zoltiq-agents' ),
				'description'         => __( 'Fetch a menu item via GET /wp/v2/menu-items/{id}.', 'zoltiq-agents' ),
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
						'item'    => array( 'type' => 'object' ),
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

		$post = get_post( $id );
		if ( ! ( $post instanceof \WP_Post ) || 'nav_menu_item' !== $post->post_type ) {
			return array(
				'success' => false,
				'message' => __( 'Menu item not found.', 'zoltiq-agents' ),
			);
		}

		return array(
			'success' => true,
			'item'    => Menu_Formatter::item_to_array( $post ),
		);
	}
}
