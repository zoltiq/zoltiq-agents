<?php

namespace Zoltiq\Agents\Includes\Abilities\Themes;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\File_Mods_Guard;
use Zoltiq\Agents\Includes\Abilities\Utilities\Theme_Helpers;

defined( 'ABSPATH' ) || exit;

class Install_Theme extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/install-theme',
			'args' => array(
				'label'               => __( 'Install Theme', 'zoltiq-agents' ),
				'description'         => __( 'Install a theme from the WordPress.org theme directory by name or slug.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-themes',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'theme'    => array(
							'type'        => 'string',
							'description' => __( 'The theme name or slug to install from WordPress.org.', 'zoltiq-agents' ),
						),
						'activate' => array(
							'type'        => 'boolean',
							'description' => __( 'Whether to activate the theme after installing.', 'zoltiq-agents' ),
							'default'     => false,
						),
					),
					'required'             => array( 'theme' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'    => array( 'type' => 'boolean' ),
						'message'    => array( 'type' => 'string' ),
						'theme_name' => array( 'type' => 'string' ),
						'theme_slug' => array( 'type' => 'string' ),
						'activated'  => array( 'type' => 'boolean' ),
					),
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
		$blocked = File_Mods_Guard::blocked_response( 'install' );
		if ( null !== $blocked ) {
			return $blocked;
		}

		if ( empty( $input['theme'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'No theme specified.', 'zoltiq-agents' ),
			);
		}

		$theme_slug = sanitize_text_field( $input['theme'] );
		$activate   = ! empty( $input['activate'] );

		$theme_slug = sanitize_title( $theme_slug );

		if ( '' === $theme_slug ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid theme slug.', 'zoltiq-agents' ),
			);
		}

		$resolved = Theme_Helpers::resolve_theme( $theme_slug );
		if ( null !== $resolved['stylesheet'] && $resolved['certainty'] >= 8.0 ) {
			$theme_data = Theme_Helpers::get_theme_by_slug( $resolved['stylesheet'] );
			$status     = $theme_data && $theme_data['active'] ? __( 'active', 'zoltiq-agents' ) : __( 'inactive', 'zoltiq-agents' );

			return array(
				'success'           => true,
				'message'           => sprintf( __( 'Theme "%1$s" is already installed (%2$s).', 'zoltiq-agents' ), $resolved['theme_name'], $status ),
				'already_installed' => true,
				'theme_name'        => $resolved['theme_name'],
				'theme_slug'        => $resolved['stylesheet'],
				'active'            => $theme_data && $theme_data['active'],
			);
		}

		if ( ! function_exists( 'themes_api' ) ) {
			require_once ABSPATH . 'wp-admin/includes/theme.php';
		}
		if ( ! class_exists( 'Theme_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$api = themes_api(
			'theme_information',
			array(
				'slug'   => $theme_slug,
				'fields' => array(
					'sections'      => false,
					'screenshot'    => false,
					'rating'        => false,
					'downloaded'    => false,
					'download_link' => true,
					'last_updated'  => false,
					'homepage'      => false,
					'tags'          => false,
				),
			)
		);

		if ( is_wp_error( $api ) ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Could not find theme "%1$s" on WordPress.org: %2$s', 'zoltiq-agents' ), $theme_slug, $api->get_error_message() ),
			);
		}

		if ( empty( $api->download_link ) ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'No download link available for "%s".', 'zoltiq-agents' ), $api->name ?? $theme_slug ),
			);
		}

		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Theme_Upgrader( $skin );
		$result   = $upgrader->install( $api->download_link );

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Failed to install "%1$s": %2$s', 'zoltiq-agents' ), $api->name, $result->get_error_message() ),
			);
		}

		if ( true !== $result ) {
			$errors    = $skin->get_errors();
			$feedback  = $skin->get_upgrade_messages();
			$error_msg = '';

			if ( is_wp_error( $errors ) && $errors->has_errors() ) {
				$error_msg = $errors->get_error_message();
			} elseif ( ! empty( $feedback ) ) {
				$error_msg = implode( ' ', $feedback );
			} else {
				$error_msg = __( 'Unknown error during installation.', 'zoltiq-agents' );
			}

			return array(
				'success' => false,
				'message' => sprintf( __( 'Failed to install "%1$s": %2$s', 'zoltiq-agents' ), $api->name, $error_msg ),
			);
		}

		$activated = false;

		if ( $activate ) {
			wp_clean_themes_cache();
			$installed = Theme_Helpers::resolve_theme( $theme_slug );
			if ( null !== $installed['stylesheet'] ) {
				switch_theme( $installed['stylesheet'] );
				$current   = wp_get_theme();
				$activated = $current && $current->get_stylesheet() === $installed['stylesheet'];
			}
		}

		if ( $activate && $activated ) {
			$message = sprintf( __( 'Theme "%s" has been installed and activated successfully.', 'zoltiq-agents' ), $api->name );
		} elseif ( $activate && ! $activated ) {
			$message = sprintf( __( 'Theme "%s" was installed but could not be activated.', 'zoltiq-agents' ), $api->name );
		} else {
			$message = sprintf( __( 'Theme "%s" has been installed successfully.', 'zoltiq-agents' ), $api->name );
		}

		return array(
			'success'    => true,
			'message'    => $message,
			'theme_name' => $api->name,
			'theme_slug' => $theme_slug,
			'activated'  => $activated,
		);
	}
}
