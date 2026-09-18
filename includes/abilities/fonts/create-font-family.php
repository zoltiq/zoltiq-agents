<?php

namespace Zoltiq\Agents\Includes\Abilities\Fonts;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Create_Font_Family extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/create-font-family',
			'args' => array(
				'label'               => __( 'Create Font Family', 'zoltiq-agents' ),
				'description'         => __( 'Create a Font Library font family (wp_font_family CPT). Requires name, slug, and fontFamily — matches the theme.json font family preset shape.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-fonts',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'name'       => array(
							'type'        => 'string',
							'description' => __( 'Human-readable font family name, e.g. "Inter".', 'zoltiq-agents' ),
						),
						'slug'       => array(
							'type'        => 'string',
							'description' => __( 'Kebab-case unique identifier, e.g. "inter".', 'zoltiq-agents' ),
						),
						'fontFamily' => array(
							'type'        => 'string',
							'description' => __( 'CSS font-family value, e.g. "Inter, sans-serif".', 'zoltiq-agents' ),
						),
						'preview'    => array(
							'type'        => 'string',
							'default'     => '',
							'description' => __( 'Optional URL to a preview image.', 'zoltiq-agents' ),
						),
					),
					'required'             => array( 'name', 'slug', 'fontFamily' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'family'  => array( 'type' => 'object' ),
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
						'idempotent'  => false,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		$name        = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
		$slug        = sanitize_title( (string) ( $input['slug'] ?? '' ) );
		$font_family = (string) ( $input['fontFamily'] ?? '' );
		$preview     = sanitize_url( (string) ( $input['preview'] ?? '' ) );

		if ( '' === $name || '' === $slug || '' === $font_family ) {
			return array(
				'success' => false,
				'message' => __( 'name, slug, and fontFamily are required.', 'zoltiq-agents' ),
			);
		}

		$settings = array(
			'name'       => $name,
			'slug'       => $slug,
			'fontFamily' => $font_family,
		);
		if ( '' !== $preview ) {
			$settings['preview'] = $preview;
		}

		$request = new \WP_REST_Request( 'POST', '/wp/v2/font-families' );
		$request->set_param( 'font_family_settings', wp_json_encode( $settings ) );

		$response = rest_do_request( $request );
		if ( $response->is_error() ) {
			$error = $response->as_error();
			return array(
				'success' => false,
				'message' => $error->get_error_message(),
			);
		}

		return array(
			'success' => true,
			'message' => sprintf( __( 'Created font family "%s".', 'zoltiq-agents' ), $name ),
			'family'  => (array) $response->get_data(),
		);
	}
}
