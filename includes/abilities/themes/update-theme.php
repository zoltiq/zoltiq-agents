<?php

namespace Zoltiq\Agents\Includes\Abilities\Themes;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\File_Mods_Guard;
use Zoltiq\Agents\Includes\Abilities\Utilities\Theme_Helpers;

defined( 'ABSPATH' ) || exit;

class Update_Theme extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/update-theme',
			'args' => array(
				'label'               => __( 'Update Theme', 'zoltiq-agents' ),
				'description'         => __( 'Apply the pending update for one or more installed themes. Accepts stylesheet directory names (e.g. "twentytwentyfour") or theme names (resolved via Theme_Helpers). Re-running when no update is available is a no-op.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-themes',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' ) && current_user_can( 'update_themes' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'stylesheets'        => array(
							'type'        => 'array',
							'minItems'    => 1,
							'items'       => array( 'type' => 'string' ),
							'description' => __( 'One or more theme stylesheets or theme names to update.', 'zoltiq-agents' ),
						),
						'clear_update_cache' => array(
							'type'    => 'boolean',
							'default' => true,
						),
					),
					'required'             => array( 'stylesheets' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'       => array( 'type' => 'boolean' ),
						'results'       => array(
							'type'  => 'array',
							'items' => array(
								'type'                 => 'object',
								'properties'           => array(
									'stylesheet'   => array( 'type' => 'string' ),
									'from_version' => array( 'type' => 'string' ),
									'to_version'   => array( 'type' => 'string' ),
									'updated'      => array( 'type' => 'boolean' ),
									'message'      => array( 'type' => 'string' ),
								),
								'additionalProperties' => false,
							),
						),
						'updated_count' => array( 'type' => 'integer' ),
						'failed_count'  => array( 'type' => 'integer' ),
						'message'       => array( 'type' => 'string' ),
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
		$blocked = File_Mods_Guard::blocked_response( 'install' );
		if ( null !== $blocked ) {
			return $blocked;
		}

		$stylesheets = isset( $input['stylesheets'] ) && is_array( $input['stylesheets'] ) ? $input['stylesheets'] : array();
		if ( empty( $stylesheets ) ) {
			return array(
				'success' => false,
				'message' => __( 'Provide at least one theme stylesheet in "stylesheets".', 'zoltiq-agents' ),
			);
		}

		if ( ! function_exists( 'get_theme_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}
		if ( ! class_exists( '\Theme_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';

		$all_themes      = wp_get_themes();
		$theme_updates   = get_theme_updates();
		$per_slug_result = array();
		$to_upgrade      = array();

		foreach ( $stylesheets as $raw_slug ) {
			$identifier = sanitize_text_field( (string) $raw_slug );
			if ( '' === $identifier ) {
				continue;
			}

			$stylesheet = null;
			if ( isset( $all_themes[ $identifier ] ) ) {
				$stylesheet = $identifier;
			} else {
				$resolved = Theme_Helpers::resolve_theme( $identifier );
				if ( ! empty( $resolved['stylesheet'] ) && $resolved['certainty'] >= 8.0 ) {
					$stylesheet = (string) $resolved['stylesheet'];
				}
			}

			if ( null === $stylesheet ) {
				$per_slug_result[ $identifier ] = array(
					'stylesheet'   => $identifier,
					'from_version' => '',
					'to_version'   => '',
					'updated'      => false,
					'message'      => __( 'Could not resolve theme.', 'zoltiq-agents' ),
				);
				continue;
			}

			$from_version = isset( $all_themes[ $stylesheet ] ) ? (string) $all_themes[ $stylesheet ]->get( 'Version' ) : '';
			$to_version   = '';
			if ( isset( $theme_updates[ $stylesheet ]->update['new_version'] ) ) {
				$to_version = (string) $theme_updates[ $stylesheet ]->update['new_version'];
			}

			if ( ! isset( $theme_updates[ $stylesheet ] ) ) {
				$per_slug_result[ $stylesheet ] = array(
					'stylesheet'   => $stylesheet,
					'from_version' => $from_version,
					'to_version'   => $from_version,
					'updated'      => false,
					'message'      => __( 'No update available.', 'zoltiq-agents' ),
				);
				continue;
			}

			$to_upgrade[]                   = $stylesheet;
			$per_slug_result[ $stylesheet ] = array(
				'stylesheet'   => $stylesheet,
				'from_version' => $from_version,
				'to_version'   => $to_version,
				'updated'      => false,
				'message'      => __( 'Pending upgrade.', 'zoltiq-agents' ),
			);
		}

		if ( empty( $to_upgrade ) ) {
			$updated_count = 0;
			$failed_count  = 0;
			foreach ( $per_slug_result as $entry ) {
				if ( ! $entry['updated'] && __( 'No update available.', 'zoltiq-agents' ) !== $entry['message'] ) {
					++$failed_count;
				}
			}
			return array(
				'success'       => true,
				'results'       => array_values( $per_slug_result ),
				'updated_count' => $updated_count,
				'failed_count'  => $failed_count,
				'message'       => __( 'No pending theme updates for the given stylesheets.', 'zoltiq-agents' ),
			);
		}

		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Theme_Upgrader( $skin );
		$results  = $upgrader->bulk_upgrade(
			$to_upgrade,
			array(
				'clear_update_cache' => ! isset( $input['clear_update_cache'] ) || (bool) $input['clear_update_cache'],
			)
		);

		wp_clean_themes_cache();
		$themes_after = wp_get_themes();

		$updated_count = 0;
		$failed_count  = 0;

		foreach ( $to_upgrade as $stylesheet ) {
			$result      = $results[ $stylesheet ] ?? null;
			$now_version = isset( $themes_after[ $stylesheet ] ) ? (string) $themes_after[ $stylesheet ]->get( 'Version' ) : '';

			if ( is_wp_error( $result ) ) {
				$per_slug_result[ $stylesheet ]['updated']    = false;
				$per_slug_result[ $stylesheet ]['message']    = $result->get_error_message();
				$per_slug_result[ $stylesheet ]['to_version'] = $now_version;
				++$failed_count;
				continue;
			}
			if ( null === $result || false === $result ) {
				$skin_errors = $skin->get_errors();
				$msg         = is_wp_error( $skin_errors ) && $skin_errors->has_errors()
					? $skin_errors->get_error_message()
					: __( 'Upgrade did not report success.', 'zoltiq-agents' );
				$per_slug_result[ $stylesheet ]['updated']    = false;
				$per_slug_result[ $stylesheet ]['message']    = $msg;
				$per_slug_result[ $stylesheet ]['to_version'] = $now_version;
				++$failed_count;
				continue;
			}

			$per_slug_result[ $stylesheet ]['updated']    = true;
			$per_slug_result[ $stylesheet ]['to_version'] = $now_version;
			$per_slug_result[ $stylesheet ]['message']    = __( 'Updated.', 'zoltiq-agents' );
			++$updated_count;
		}

		return array(
			'success'       => true,
			'results'       => array_values( $per_slug_result ),
			'updated_count' => $updated_count,
			'failed_count'  => $failed_count,
			'message'       => sprintf(
				__( 'Theme update completed: %1$d updated, %2$d failed.', 'zoltiq-agents' ),
				$updated_count,
				$failed_count
			),
		);
	}
}
