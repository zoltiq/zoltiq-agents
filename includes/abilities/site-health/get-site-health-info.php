<?php

namespace Zoltiq\Agents\Includes\Abilities\SiteHealth;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use WP_Debug_Data;

defined( 'ABSPATH' ) || exit;

class Get_Site_Health_Info extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/get-site-health-info',
			'args' => array(
				'label'               => __( 'Get Site Health Info', 'zoltiq-agents' ),
				'description'         => __( 'Return the Site Health Info report (WP_Debug_Data::debug_data()) — server, database, WordPress, themes, plugins, media, filesystem, constants and paths/sizes. Optionally filter to specific sections.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-site-health',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'sections' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => __( 'Optional list of section keys to return (e.g. wp-core, wp-server, wp-database, wp-active-theme, wp-plugins-active). Empty/omitted returns all sections.', 'zoltiq-agents' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'  => array( 'type' => 'boolean' ),
						'sections' => array(
							'type'        => 'object',
							'description' => __( 'Map of section key → { label, description, fields[] }.', 'zoltiq-agents' ),
						),
						'error'    => array( 'type' => 'string' ),
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
		if ( ! class_exists( 'WP_Debug_Data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
		}

		$requested = array();
		if ( isset( $input['sections'] ) && is_array( $input['sections'] ) ) {
			$requested = array_values(
				array_filter(
					array_map( 'sanitize_key', $input['sections'] ),
					static function ( $key ): bool {
						return '' !== $key;
					}
				)
			);
		}

		try {
			$info = WP_Debug_Data::debug_data();
		} catch ( \Throwable $e ) {
			return array(
				'success'  => false,
				'sections' => array(),
				'error'    => $e->getMessage(),
			);
		}

		if ( ! empty( $requested ) ) {
			$info = array_intersect_key( $info, array_flip( $requested ) );
		}

		$sections = array();
		foreach ( $info as $section_key => $section ) {
			$sections[ $section_key ] = array(
				'label'       => isset( $section['label'] ) ? (string) $section['label'] : (string) $section_key,
				'description' => isset( $section['description'] ) ? (string) $section['description'] : '',
				'fields'      => $this->flatten_fields( $section['fields'] ?? array() ),
			);
		}

		return array(
			'success'  => true,
			'sections' => $sections,
		);
	}

	private function flatten_fields( array $fields ): array {
		$out = array();
		foreach ( $fields as $field_key => $field ) {
			if ( ! is_array( $field ) ) {
				$out[] = array(
					'key'   => (string) $field_key,
					'label' => (string) $field_key,
					'value' => $field,
					'debug' => null,
				);
				continue;
			}
			$out[] = array(
				'key'   => (string) $field_key,
				'label' => isset( $field['label'] ) ? (string) $field['label'] : (string) $field_key,
				'value' => $field['value'] ?? null,
				'debug' => $field['debug'] ?? null,
			);
		}
		return $out;
	}
}
