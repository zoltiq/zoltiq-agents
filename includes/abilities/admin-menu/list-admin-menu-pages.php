<?php

namespace Zoltiq\Agents\Includes\Abilities\AdminMenu;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class List_Admin_Menu_Pages extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/list-admin-menu-pages',
			'args' => array(
				'label'               => __( 'List Admin Menu Pages', 'zoltiq-agents' ),
				'description'         => __( 'Enumerate every top-level admin menu entry plus its submenus. Reads the WP core $menu / $submenu globals populated after the admin_menu hook. Returns an empty result with a clear message when invoked outside the admin request lifecycle.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-admin-menu',
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
						'success' => array( 'type' => 'boolean' ),
						'items'   => array( 'type' => 'array' ),
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
		unset( $input );
		global $menu, $submenu;

		if ( ! did_action( 'admin_menu' ) && function_exists( 'do_action' ) ) {
			require_once ABSPATH . 'wp-admin/includes/menu.php';
		}

		$title_max = 200;
		$sanitize_title = static function ( string $raw ) use ( $title_max ): string {
			$t = sanitize_text_field( wp_strip_all_tags( $raw ) );
			return strlen( $t ) > $title_max ? rtrim( substr( $t, 0, $title_max ) ) . '...' : $t;
		};

		$items = array();
		if ( is_array( $menu ) ) {
			foreach ( $menu as $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}
				$title      = $sanitize_title( (string) ( $entry[0] ?? '' ) );
				$capability = sanitize_key( (string) ( $entry[1] ?? '' ) );
				$slug       = sanitize_text_field( (string) ( $entry[2] ?? '' ) );
				if ( '' === $slug ) {
					continue;
				}
				// Only surface menu entries the current user could actually reach.
				if ( '' !== $capability && ! current_user_can( $capability ) ) {
					continue;
				}
				$sub = array();
				if ( isset( $submenu[ $slug ] ) && is_array( $submenu[ $slug ] ) ) {
					foreach ( $submenu[ $slug ] as $sub_entry ) {
						if ( ! is_array( $sub_entry ) ) {
							continue;
						}
						$sub_cap  = sanitize_key( (string) ( $sub_entry[1] ?? '' ) );
						$sub_slug = sanitize_text_field( (string) ( $sub_entry[2] ?? '' ) );
						if ( '' !== $sub_cap && ! current_user_can( $sub_cap ) ) {
							continue;
						}
						$sub[] = array(
							'title'      => $sanitize_title( (string) ( $sub_entry[0] ?? '' ) ),
							'capability' => $sub_cap,
							'slug'       => $sub_slug,
						);
					}
				}
				$items[] = array(
					'title'      => $title,
					'capability' => $capability,
					'slug'       => $slug,
					'url'        => esc_url_raw( (string) menu_page_url( $slug, false ) ),
					'submenu'    => $sub,
				);
			}
		}

		return array(
			'success' => true,
			'items'   => $items,
			'message' => sprintf( __( '%d top-level admin menu entries.', 'zoltiq-agents' ), count( $items ) ),
		);
	}
}
