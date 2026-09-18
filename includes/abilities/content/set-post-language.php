<?php

namespace Zoltiq\Agents\Includes\Abilities\Content;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Multilang_Helpers;

defined( 'ABSPATH' ) || exit;

class Set_Post_Language extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/set-post-language',
			'args' => array(
				'label'               => __( 'Set Post Language', 'zoltiq-agents' ),
				'description'         => __( 'Assign a language code to a post. Polylang uses pll_set_post_language(); WPML uses the wpml_set_element_language_details action.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-content',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_id'  => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'language' => array( 'type' => 'string' ),
					),
					'required'             => array( 'post_id', 'language' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'  => array( 'type' => 'boolean' ),
						'driver'   => array( 'type' => 'string' ),
						'language' => array( 'type' => 'string' ),
						'message'  => array( 'type' => 'string' ),
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
		$post_id  = (int) ( $input['post_id'] ?? 0 );
		$language = sanitize_key( (string) ( $input['language'] ?? '' ) );

		if ( $post_id <= 0 || ! get_post( $post_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Post not found.', 'zoltiq-agents' ),
			);
		}
		if ( '' === $language ) {
			return array(
				'success' => false,
				'message' => __( 'A language code is required.', 'zoltiq-agents' ),
			);
		}

		$result = Multilang_Helpers::set_post_language( $post_id, $language );
		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		return array(
			'success'  => true,
			'driver'   => Multilang_Helpers::detect(),
			'language' => $language,
			'message'  => sprintf( __( 'Set post #%1$d language to "%2$s".', 'zoltiq-agents' ), $post_id, $language ),
		);
	}
}
