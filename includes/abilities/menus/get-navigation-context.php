<?php

namespace Zoltiq\Agents\Includes\Abilities\Menus;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Get_Navigation_Context extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/get-navigation-context',
			'args' => array(
				'label'               => __( 'Get Navigation Context', 'zoltiq-agents' ),
				'description'         => __( 'Return one envelope containing every nav menu (id, name, item count) and every theme-registered nav-menu location, with the location→menu assignments resolved.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-menus',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => new \stdClass(),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'   => array( 'type' => 'boolean' ),
						'menus'     => array( 'type' => 'array' ),
						'locations' => array( 'type' => 'array' ),
						'message'   => array( 'type' => 'string' ),
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
		unset( $input );

		$menus     = array();
		$menu_list = wp_get_nav_menus();
		if ( is_array( $menu_list ) ) {
			foreach ( $menu_list as $menu ) {
				if ( ! $menu instanceof \WP_Term ) {
					continue;
				}
				$menus[] = array(
					'id'         => (int) $menu->term_id,
					'name'       => sanitize_text_field( (string) $menu->name ),
					'slug'       => sanitize_title( (string) $menu->slug ),
					'item_count' => (int) $menu->count,
				);
			}
		}

		$assigned_by_menu = array();
		$assignments      = get_nav_menu_locations();
		if ( is_array( $assignments ) ) {
			foreach ( $assignments as $location => $menu_id ) {
				$assigned_by_menu[ absint( $menu_id ) ] = sanitize_key( (string) $location );
			}
		}

		$registered = get_registered_nav_menus();
		$locations  = array();
		if ( is_array( $registered ) ) {
			foreach ( $registered as $slug => $label ) {
				$menu_id             = 0;
				$menu_name           = '';
				$assigned_menu       = array_search( sanitize_key( (string) $slug ), $assigned_by_menu, true );
				if ( false !== $assigned_menu ) {
					$menu_id     = absint( $assigned_menu );
					$menu_term   = get_term( $menu_id );
					$menu_name   = $menu_term instanceof \WP_Term ? sanitize_text_field( (string) $menu_term->name ) : '';
				}
				$locations[] = array(
					'slug'               => sanitize_key( (string) $slug ),
					'label'              => sanitize_text_field( (string) $label ),
					'assigned_menu_id'   => $menu_id,
					'assigned_menu_name' => $menu_name,
				);
			}
		}

		return array(
			'success'   => true,
			'menus'     => $menus,
			'locations' => $locations,
			'message'   => sprintf( __( '%1$d menus, %2$d locations.', 'zoltiq-agents' ), count( $menus ), count( $locations ) ),
		);
	}
}
