<?php

namespace Zoltiq\Agents\Includes\Abilities\AdminMenu;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class List_Admin_Settings extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/list-admin-settings',
			'args' => array(
				'label'               => __( 'List Admin Settings', 'zoltiq-agents' ),
				'description'         => __( 'List every Settings API section + field per settings page. Reads the WP core $wp_settings_sections / $wp_settings_fields globals. Filter with the optional `page` input.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-admin-menu',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'page' => array( 'type' => 'string' ),
					),
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
		global $wp_settings_sections, $wp_settings_fields;

		$page_filter = isset( $input['page'] ) ? sanitize_key( (string) $input['page'] ) : '';

		$max = 200;
		$cap = static function ( string $raw ) use ( $max ): string {
			$t = sanitize_text_field( wp_strip_all_tags( $raw ) );
			return strlen( $t ) > $max ? rtrim( substr( $t, 0, $max ) ) . '...' : $t;
		};

		$items = array();

		if ( is_array( $wp_settings_sections ) ) {
			foreach ( $wp_settings_sections as $page => $sections ) {
				if ( '' !== $page_filter && (string) $page !== $page_filter ) {
					continue;
				}
				if ( ! is_array( $sections ) ) {
					continue;
				}
				foreach ( $sections as $section_id => $section ) {
					$section_title = is_array( $section ) ? $cap( (string) ( $section['title'] ?? '' ) ) : '';
					$fields        = array();
					if ( isset( $wp_settings_fields[ $page ][ $section_id ] ) && is_array( $wp_settings_fields[ $page ][ $section_id ] ) ) {
						foreach ( $wp_settings_fields[ $page ][ $section_id ] as $field_id => $field ) {
							$fields[] = array(
								'id'    => sanitize_text_field( (string) $field_id ),
								'label' => is_array( $field ) ? $cap( (string) ( $field['title'] ?? '' ) ) : '',
							);
						}
					}
					$items[] = array(
						'page'    => sanitize_text_field( (string) $page ),
						'section' => sanitize_text_field( (string) $section_id ),
						'title'   => $section_title,
						'fields'  => $fields,
					);
				}
			}
		}

		return array(
			'success' => true,
			'items'   => $items,
			'message' => sprintf( __( '%d settings section(s) found.', 'zoltiq-agents' ), count( $items ) ),
		);
	}
}
