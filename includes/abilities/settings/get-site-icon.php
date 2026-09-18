<?php

namespace Zoltiq\Agents\Includes\Abilities\Settings;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Get_Site_Icon extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/get-site-icon',
			'args' => array(
				'label'               => __( 'Get Site Icon', 'zoltiq-agents' ),
				'description'         => __( 'Returns the current site icon (favicon) — attachment ID plus URLs at the standard sizes (32, 192, 270, 512).', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-settings',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'       => array( 'type' => 'boolean' ),
						'has_icon'      => array( 'type' => 'boolean' ),
						'attachment_id' => array( 'type' => 'integer' ),
						'url'           => array( 'type' => 'string' ),
						'urls'          => array( 'type' => 'object' ),
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
		$attachment_id = (int) get_option( 'site_icon', 0 );
		$has_icon      = $attachment_id > 0 && wp_attachment_is_image( $attachment_id );

		$urls = array();
		foreach ( array( 32, 192, 270, 512 ) as $size ) {
			$url = get_site_icon_url( $size );
			if ( $url ) {
				$urls[ (string) $size ] = $url;
			}
		}

		return array(
			'success'       => true,
			'has_icon'      => $has_icon,
			'attachment_id' => $attachment_id,
			'url'           => $has_icon ? (string) get_site_icon_url() : '',
			'urls'          => (object) $urls,
		);
	}
}
