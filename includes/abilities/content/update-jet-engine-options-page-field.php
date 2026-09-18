<?php

namespace Zoltiq\Agents\Includes\Abilities\Content;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Jet_Engine_Helpers;

defined( 'ABSPATH' ) || exit;

class Update_Jet_Engine_Options_Page_Field extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/update-jet-engine-options-page-field',
			'args' => array(
				'label'               => __( 'Update Options Page Field', 'zoltiq-agents' ),
				'description'         => __( 'Write a single field value into a Jet Engine options page. The field value is stored inside the page\'s wp_options row.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-content',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'slug'  => array( 'type' => 'string' ),
						'field' => array( 'type' => 'string' ),
						'value' => array( 'type' => array( 'string', 'integer', 'number', 'boolean', 'array', 'object', 'null' ) ),
					),
					'required'             => array( 'slug', 'field' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'slug'    => array( 'type' => 'string' ),
						'field'   => array( 'type' => 'string' ),
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
		$slug  = sanitize_key( (string) ( $input['slug'] ?? '' ) );
		$field = sanitize_key( (string) ( $input['field'] ?? '' ) );
		if ( '' === $slug || '' === $field ) {
			return array(
				'success' => false,
				'message' => __( 'slug and field are required.', 'zoltiq-agents' ),
			);
		}

		$result = Jet_Engine_Helpers::update_field( $slug, $field, $input['value'] ?? '' );
		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		return array(
			'success' => true,
			'slug'    => $slug,
			'field'   => $field,
			'message' => sprintf( __( 'Updated field "%1$s" on options page "%2$s".', 'zoltiq-agents' ), $field, $slug ),
		);
	}
}
