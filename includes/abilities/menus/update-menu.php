<?php

namespace Zoltiq\Agents\Includes\Abilities\Menus;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Update_Menu extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/update-menu',
			'args' => array(
				'label'               => __( 'Update Menu', 'zoltiq-agents' ),
				'description'         => __( 'Update a nav menu via POST /wp/v2/menus/{id}. Only the supplied fields are touched.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-menus',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'          => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'name'        => array( 'type' => 'string' ),
						'slug'        => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
						'locations'   => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
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
						'readonly'    => false,
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

		$args = array();
		if ( isset( $input['name'] ) ) {
			$args['menu-name'] = sanitize_text_field( (string) $input['name'] );
		}
		if ( isset( $input['slug'] ) ) {
			$args['menu-slug'] = sanitize_title( (string) $input['slug'] );
		}
		if ( isset( $input['description'] ) ) {
			$args['description'] = (string) $input['description'];
		}

		if ( ! empty( $args ) ) {
			$result = wp_update_nav_menu_object( $id, wp_slash( $args ) );
			if ( is_wp_error( $result ) ) {
				return Menu_Formatter::error_from(
					$result,
					sprintf( __( 'Could not update menu #%d.', 'zoltiq-agents' ), $id )
				);
			}
		}

		if ( isset( $input['locations'] ) && is_array( $input['locations'] ) ) {
			Menu_Formatter::set_menu_locations(
				$id,
				array_values( array_filter( array_map( 'sanitize_key', $input['locations'] ) ) )
			);
		}

		$updated = wp_get_nav_menu_object( $id );
		return array(
			'success' => true,
			'menu'    => $updated instanceof \WP_Term ? Menu_Formatter::menu_to_array( $updated ) : array(),
			'message' => sprintf( __( 'Updated menu #%d.', 'zoltiq-agents' ), $id ),
		);
	}
}
