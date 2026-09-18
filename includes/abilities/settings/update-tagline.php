<?php

namespace Zoltiq\Agents\Includes\Abilities\Settings;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Update_Tagline extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/update-tagline',
			'args' => array(
				'label'               => __( 'Update Tagline', 'zoltiq-agents' ),
				'description'         => __( 'Updates the site tagline (the "blogdescription" option). Empty values are accepted to clear the tagline.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-settings',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'tagline' => array(
							'type'        => 'string',
							'description' => __( 'New tagline. Empty string clears it.', 'zoltiq-agents' ),
						),
					),
					'required'             => array( 'tagline' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'          => array( 'type' => 'boolean' ),
						'message'          => array( 'type' => 'string' ),
						'tagline'          => array( 'type' => 'string' ),
						'previous_tagline' => array( 'type' => 'string' ),
						'updated'          => array( 'type' => 'boolean' ),
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
		if ( ! array_key_exists( 'tagline', $input ) ) {
			return array(
				'success' => false,
				'message' => __( 'A "tagline" value is required (pass an empty string to clear it).', 'zoltiq-agents' ),
			);
		}

		$new      = sanitize_text_field( (string) $input['tagline'] );
		$previous = (string) get_option( 'blogdescription', '' );
		$updated  = update_option( 'blogdescription', $new );

		return array(
			'success'          => true,
			'message'          => '' === $new
				? __( 'Tagline cleared.', 'zoltiq-agents' )
				: sprintf( __( 'Tagline updated to "%s".', 'zoltiq-agents' ), $new ),
			'tagline'          => wp_specialchars_decode( (string) get_option( 'blogdescription', '' ), ENT_QUOTES ),
			'previous_tagline' => wp_specialchars_decode( $previous, ENT_QUOTES ),
			'updated'          => (bool) $updated,
		);
	}
}
