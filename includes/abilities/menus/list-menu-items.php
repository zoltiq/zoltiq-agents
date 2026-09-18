<?php

namespace Zoltiq\Agents\Includes\Abilities\Menus;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class List_Menu_Items extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/list-menu-items',
			'args' => array(
				'label'               => __( 'List Menu Items', 'zoltiq-agents' ),
				'description'         => __( 'List menu items via GET /wp/v2/menu-items. Use menus={id} to scope to a single menu.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-menus',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'menus'    => array( 'type' => 'integer' ),
						'page'     => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100,
							'default' => 25,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'items'   => array( 'type' => 'array' ),
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
		$page     = max( 1, (int) ( $input['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $input['per_page'] ?? 25 ) ) );

		if ( ! empty( $input['menus'] ) ) {
			$menu_id = (int) $input['menus'];
			$all     = wp_get_nav_menu_items( $menu_id, array( 'update_post_term_cache' => false ) );
			if ( ! is_array( $all ) ) {
				$all = array();
			}
		} else {
			$query = new \WP_Query(
				array(
					'post_type'      => 'nav_menu_item',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'orderby'        => 'menu_order',
					'order'          => 'ASC',
				)
			);
			$all   = $query->posts;
		}

		$total = count( $all );
		$slice = array_slice( $all, ( $page - 1 ) * $per_page, $per_page );

		$formatted = array_map(
			array( Menu_Formatter::class, 'item_to_array' ),
			array_values(
				array_filter(
					$slice,
					static function ( $p ): bool {
						return $p instanceof \WP_Post;
					}
				)
			)
		);

		return array(
			'success' => true,
			'items'   => $formatted,
			'total'   => $total,
		);
	}
}
