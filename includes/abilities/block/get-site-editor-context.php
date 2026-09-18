<?php

namespace Zoltiq\Agents\Includes\Abilities\Block;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Get_Site_Editor_Context extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/get-site-editor-context',
			'args' => array(
				'label'               => __( 'Get Site Editor Context', 'zoltiq-agents' ),
				'description'         => __( 'Return the active theme\'s Site Editor context: whether the theme is a block theme, the active style variation, counts of registered templates and template parts, and the Site Editor URL.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-block',
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
						'success'                => array( 'type' => 'boolean' ),
						'is_block_theme'         => array( 'type' => 'boolean' ),
						'active_theme'           => array( 'type' => 'string' ),
						'active_style_variation' => array( 'type' => 'string' ),
						'template_count'         => array( 'type' => 'integer' ),
						'template_part_count'    => array( 'type' => 'integer' ),
						'site_editor_url'        => array( 'type' => 'string' ),
						'message'                => array( 'type' => 'string' ),
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

		$is_block_theme = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
		$theme          = wp_get_theme();
		$active_name    = $theme instanceof \WP_Theme ? sanitize_text_field( (string) $theme->get( 'Name' ) ) : '';

		$style_variation = '';
		if ( function_exists( 'WP_Theme_JSON_Resolver::get_user_data' ) ) {
			$user_data = \WP_Theme_JSON_Resolver::get_user_data();
			if ( is_object( $user_data ) && method_exists( $user_data, 'get_variations' ) ) {
				$style_variation = (string) ( $user_data->get_variations()[0]['title'] ?? '' );
			}
		}

		$templates      = get_block_templates( array(), 'wp_template' );
		$template_parts = get_block_templates( array(), 'wp_template_part' );

		return array(
			'success'                => true,
			'is_block_theme'         => (bool) $is_block_theme,
			'active_theme'           => $active_name,
			'active_style_variation' => sanitize_text_field( $style_variation ),
			'template_count'         => is_array( $templates ) ? count( $templates ) : 0,
			'template_part_count'    => is_array( $template_parts ) ? count( $template_parts ) : 0,
			'site_editor_url'        => esc_url_raw( (string) admin_url( 'site-editor.php' ) ),
			'message'                => $is_block_theme
				? __( 'Block theme active; Site Editor available.', 'zoltiq-agents' )
				: __( 'Classic theme active; Site Editor is limited or unavailable.', 'zoltiq-agents' ),
		);
	}
}
