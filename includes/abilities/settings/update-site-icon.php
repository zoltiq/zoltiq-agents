<?php

namespace Zoltiq\Agents\Includes\Abilities\Settings;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Update_Site_Icon extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/update-site-icon',
			'args' => array(
				'label'               => __( 'Update Site Icon', 'zoltiq-agents' ),
				'description'         => __( 'Sets the site icon to a media library attachment_id. Pass 0 to remove. WordPress recommends a 512×512 image.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-settings',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'attachment_id' => array(
							'type'        => 'integer',
							'description' => __( 'Media library attachment ID of the image to use as the site icon. Pass 0 to remove.', 'zoltiq-agents' ),
						),
					),
					'required'             => array( 'attachment_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'                => array( 'type' => 'boolean' ),
						'message'                => array( 'type' => 'string' ),
						'attachment_id'          => array( 'type' => 'integer' ),
						'previous_attachment_id' => array( 'type' => 'integer' ),
						'url'                    => array( 'type' => 'string' ),
						'urls'                   => array( 'type' => 'object' ),
						'warnings'               => array( 'type' => 'array' ),
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
		if ( ! array_key_exists( 'attachment_id', $input ) ) {
			return array(
				'success' => false,
				'message' => __( 'An "attachment_id" is required (pass 0 to remove the site icon).', 'zoltiq-agents' ),
			);
		}

		$attachment_id = (int) $input['attachment_id'];
		$previous      = (int) get_option( 'site_icon', 0 );
		$warnings      = array();

		if ( 0 === $attachment_id ) {
			delete_option( 'site_icon' );
			return array(
				'success'                => true,
				'message'                => __( 'Site icon removed.', 'zoltiq-agents' ),
				'attachment_id'          => 0,
				'previous_attachment_id' => $previous,
				'url'                    => '',
				'urls'                   => (object) array(),
				'warnings'               => $warnings,
			);
		}

		$post = get_post( $attachment_id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Attachment %d not found.', 'zoltiq-agents' ), $attachment_id ),
			);
		}
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Attachment %d is not an image.', 'zoltiq-agents' ), $attachment_id ),
			);
		}

		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( is_array( $meta ) && isset( $meta['width'], $meta['height'] ) ) {
			$w = (int) $meta['width'];
			$h = (int) $meta['height'];
			if ( $w < 512 || $h < 512 ) {
				$warnings[] = sprintf( __( 'Image is %1$d×%2$d — WordPress recommends 512×512 or larger for the site icon.', 'zoltiq-agents' ), $w, $h );
			}
			if ( $w !== $h ) {
				$warnings[] = __( 'Image is not square — the site icon will appear letter-boxed on some platforms.', 'zoltiq-agents' );
			}
		}

		update_option( 'site_icon', $attachment_id );

		$urls = array();
		foreach ( array( 32, 192, 270, 512 ) as $size ) {
			$url = get_site_icon_url( $size );
			if ( $url ) {
				$urls[ (string) $size ] = $url;
			}
		}

		return array(
			'success'                => true,
			'message'                => sprintf( __( 'Site icon set to attachment %d.', 'zoltiq-agents' ), $attachment_id ),
			'attachment_id'          => $attachment_id,
			'previous_attachment_id' => $previous,
			'url'                    => (string) get_site_icon_url(),
			'urls'                   => (object) $urls,
			'warnings'               => $warnings,
		);
	}
}
