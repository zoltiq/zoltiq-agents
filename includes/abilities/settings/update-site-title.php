<?php

namespace Zoltiq\Agents\Includes\Abilities\Settings;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Update_Site_Title extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/update-site-title',
			'args' => array(
				'label'               => __( 'Update Site Title', 'zoltiq-agents' ),
				'description'         => __( 'Updates the site title (the "blogname" option). Whitespace is trimmed; the value cannot be empty.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-settings',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'title' => array(
							'type'        => 'string',
							'description' => __( 'New site title.', 'zoltiq-agents' ),
						),
					),
					'required'             => array( 'title' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'        => array( 'type' => 'boolean' ),
						'message'        => array( 'type' => 'string' ),
						'title'          => array( 'type' => 'string' ),
						'previous_title' => array( 'type' => 'string' ),
						'updated'        => array( 'type' => 'boolean' ),
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
		$new = trim( (string) ( $input['title'] ?? '' ) );
		if ( '' === $new ) {
			return array(
				'success' => false,
				'message' => __( 'Site title cannot be empty.', 'zoltiq-agents' ),
			);
		}

		$previous = (string) get_option( 'blogname', '' );
		$updated  = update_option( 'blogname', sanitize_text_field( $new ) );

		return array(
			'success'        => true,
			'message'        => sprintf( __( 'Site title updated to "%s".', 'zoltiq-agents' ), $new ),
			'title'          => wp_specialchars_decode( (string) get_option( 'blogname', '' ), ENT_QUOTES ),
			'previous_title' => wp_specialchars_decode( $previous, ENT_QUOTES ),
			'updated'        => (bool) $updated,
		);
	}
}
