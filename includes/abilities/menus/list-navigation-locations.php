<?php

namespace Zoltiq\Agents\Includes\Abilities\Menus;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class List_Navigation_Locations extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/list-navigation-locations',
			'args' => array(
				'label'               => __( 'List Navigation Locations', 'zoltiq-agents' ),
				'description'         => __( 'List every theme-registered nav-menu location (slug + label) and the currently-assigned menu id / name (if any).', 'zoltiq-agents' ),
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

		$assignments = get_nav_menu_locations();
		if ( ! is_array( $assignments ) ) {
			$assignments = array();
		}

		$out        = array();
		$registered = get_registered_nav_menus();
		if ( is_array( $registered ) ) {
			foreach ( $registered as $slug => $label ) {
				$menu_id   = isset( $assignments[ $slug ] ) ? absint( $assignments[ $slug ] ) : 0;
				$menu_name = '';
				if ( $menu_id > 0 ) {
					$menu = get_term( $menu_id );
					if ( $menu instanceof \WP_Term ) {
						$menu_name = sanitize_text_field( (string) $menu->name );
					}
				}
				$out[] = array(
					'slug'               => sanitize_key( (string) $slug ),
					'label'              => sanitize_text_field( (string) $label ),
					'assigned_menu_id'   => $menu_id,
					'assigned_menu_name' => $menu_name,
				);
			}
		}

		return array(
			'success'   => true,
			'locations' => $out,
			'message'   => sprintf( __( '%d nav-menu locations registered.', 'zoltiq-agents' ), count( $out ) ),
		);
	}
}
