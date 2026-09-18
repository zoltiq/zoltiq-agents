<?php

namespace Zoltiq\Agents\Includes\Abilities\Utilities;

defined( 'ABSPATH' ) || exit;

class Theme_Helpers {

	public static function get_all_themes( string $status_filter = 'all' ): array {
		$all_themes      = wp_get_themes();
		$current_theme   = wp_get_theme();
		$active_template = $current_theme ? $current_theme->get_template() : '';
		$active_styles   = $current_theme ? $current_theme->get_stylesheet() : '';
		$themes          = array();
		$active_count    = 0;

		foreach ( $all_themes as $stylesheet => $theme ) {
			$is_active = ( $stylesheet === $active_styles ) || ( $stylesheet === $active_template );

			if ( 'active' === $status_filter && ! $is_active ) {
				continue;
			}
			if ( 'inactive' === $status_filter && $is_active ) {
				continue;
			}

			if ( $is_active ) {
				++$active_count;
			}

			$themes[] = array(
				'name'     => $theme->get( 'Name' ),
				'slug'     => $stylesheet,
				'version'  => $theme->get( 'Version' ),
				'author'   => wp_strip_all_tags( (string) $theme->get( 'Author' ) ),
				'template' => $theme->get_template(),
				'active'   => $is_active,
			);
		}

		$actions = array();
		foreach ( $themes as $theme ) {
			if ( $theme['active'] ) {
				continue;
			}
			$actions[] = array(
				'label'        => $theme['name'],
				'button_label' => __( 'Activate', 'zoltiq-agents' ),
				'action'       => 'zoltiq/activate-theme',
				'args'         => array( 'theme' => $theme['slug'] ),
			);
		}

		return array(
			'themes'  => $themes,
			'total'   => count( $themes ),
			'active'  => $active_count,
			'actions' => $actions,
		);
	}

	public static function resolve_theme( string $identifier ): array {
		$all_themes  = wp_get_themes();
		$input_lower = strtolower( trim( $identifier ) );
		$input_lower = preg_replace( '/\s+theme$/', '', $input_lower );

		if ( '' === $input_lower ) {
			return array(
				'stylesheet' => null,
				'theme_name' => null,
				'certainty'  => 0.0,
				'candidates' => array(),
			);
		}

		$matches = array();

		foreach ( $all_themes as $stylesheet => $theme ) {
			$name_lower       = strtolower( (string) $theme->get( 'Name' ) );
			$stylesheet_lower = strtolower( $stylesheet );
			$template_lower   = strtolower( $theme->get_template() );

			if ( $stylesheet_lower === $input_lower ) {
				$matches[] = array(
					'stylesheet' => $stylesheet,
					'theme_name' => $theme->get( 'Name' ),
					'tier'       => 1,
					'certainty'  => 10.0,
				);
				continue;
			}

			if ( $name_lower === $input_lower ) {
				$matches[] = array(
					'stylesheet' => $stylesheet,
					'theme_name' => $theme->get( 'Name' ),
					'tier'       => 2,
					'certainty'  => 9.5,
				);
				continue;
			}

			if ( $template_lower === $input_lower ) {
				$matches[] = array(
					'stylesheet' => $stylesheet,
					'theme_name' => $theme->get( 'Name' ),
					'tier'       => 3,
					'certainty'  => 9.0,
				);
				continue;
			}

			if ( str_starts_with( $name_lower, $input_lower ) ) {
				$matches[] = array(
					'stylesheet' => $stylesheet,
					'theme_name' => $theme->get( 'Name' ),
					'tier'       => 4,
					'certainty'  => 8.0,
				);
				continue;
			}

			if ( false !== strpos( $name_lower, $input_lower ) ) {
				$matches[] = array(
					'stylesheet' => $stylesheet,
					'theme_name' => $theme->get( 'Name' ),
					'tier'       => 5,
					'certainty'  => 6.0,
				);
				continue;
			}

			if ( false !== strpos( $input_lower, $name_lower ) ) {
				$matches[] = array(
					'stylesheet' => $stylesheet,
					'theme_name' => $theme->get( 'Name' ),
					'tier'       => 6,
					'certainty'  => 5.0,
				);
				continue;
			}
		}

		if ( empty( $matches ) ) {
			return array(
				'stylesheet' => null,
				'theme_name' => null,
				'certainty'  => 0.0,
				'candidates' => array(),
			);
		}

		usort(
			$matches,
			static function ( array $a, array $b ): int {
				if ( $a['tier'] !== $b['tier'] ) {
					return $a['tier'] - $b['tier'];
				}
				return strlen( $a['theme_name'] ) - strlen( $b['theme_name'] );
			}
		);

		$best            = $matches[0];
		$best_tier       = $best['tier'];
		$same_tier_count = 0;

		foreach ( $matches as $match ) {
			if ( $match['tier'] === $best_tier ) {
				++$same_tier_count;
			}
		}

		if ( $same_tier_count > 1 ) {
			$best['certainty'] = max( 1.0, $best['certainty'] - 1.0 );
		}

		return array(
			'stylesheet' => $best['stylesheet'],
			'theme_name' => $best['theme_name'],
			'certainty'  => $best['certainty'],
			'candidates' => array_slice( $matches, 0, 5 ),
		);
	}

	public static function get_theme_by_slug( string $stylesheet ): ?array {
		$theme = wp_get_theme( $stylesheet );

		if ( ! $theme->exists() ) {
			return null;
		}

		$current   = wp_get_theme();
		$is_active = $current && (
			$current->get_stylesheet() === $stylesheet
			|| $current->get_template() === $stylesheet
		);

		return array(
			'name'     => $theme->get( 'Name' ),
			'slug'     => $stylesheet,
			'version'  => $theme->get( 'Version' ),
			'author'   => wp_strip_all_tags( (string) $theme->get( 'Author' ) ),
			'template' => $theme->get_template(),
			'active'   => $is_active,
		);
	}

	public static function activate_theme_by_slug( string $theme_identifier ): array {
		$theme_identifier = sanitize_text_field( $theme_identifier );
		$resolved         = self::resolve_theme( $theme_identifier );

		if ( null === $resolved['stylesheet'] ) {
			return array(
				'success'   => false,
				'message'   => sprintf( __( 'No theme found matching "%s".', 'zoltiq-agents' ), $theme_identifier ),
				'certainty' => 0.0,
			);
		}

		if ( $resolved['certainty'] < 8.0 ) {
			return self::build_candidate_response(
				$resolved,
				$theme_identifier,
				'zoltiq/activate-theme',
				__( 'Activate', 'zoltiq-agents' )
			);
		}

		$stylesheet = $resolved['stylesheet'];
		$theme_name = $resolved['theme_name'];
		$certainty  = $resolved['certainty'];

		$current = wp_get_theme();
		if ( $current && $current->get_stylesheet() === $stylesheet ) {
			return array(
				'success'       => true,
				'message'       => sprintf( __( 'Theme "%s" is already active.', 'zoltiq-agents' ), $theme_name ),
				'matched_theme' => $theme_name,
				'certainty'     => $certainty,
			);
		}

		$theme = wp_get_theme( $stylesheet );
		if ( ! $theme->exists() || ! $theme->is_allowed() ) {
			return array(
				'success'       => false,
				'message'       => sprintf( __( 'Theme "%s" cannot be activated.', 'zoltiq-agents' ), $theme_name ),
				'matched_theme' => $theme_name,
				'certainty'     => $certainty,
			);
		}

		switch_theme( $stylesheet );

		$new_current = wp_get_theme();
		if ( ! $new_current || $new_current->get_stylesheet() !== $stylesheet ) {
			return array(
				'success'       => false,
				'message'       => sprintf( __( 'Failed to activate theme "%s".', 'zoltiq-agents' ), $theme_name ),
				'matched_theme' => $theme_name,
				'certainty'     => $certainty,
			);
		}

		return array(
			'success'       => true,
			'message'       => sprintf( __( 'Theme "%s" has been activated successfully.', 'zoltiq-agents' ), $theme_name ),
			'matched_theme' => $theme_name,
			'certainty'     => $certainty,
		);
	}

	public static function delete_theme_by_slug( string $theme_identifier ): array {
		$theme_identifier = sanitize_text_field( $theme_identifier );
		$resolved         = self::resolve_theme( $theme_identifier );

		if ( null === $resolved['stylesheet'] ) {
			return array(
				'success'   => false,
				'message'   => sprintf( __( 'No theme found matching "%s".', 'zoltiq-agents' ), $theme_identifier ),
				'certainty' => 0.0,
			);
		}

		if ( $resolved['certainty'] < 8.0 ) {
			return self::build_candidate_response(
				$resolved,
				$theme_identifier,
				'zoltiq/delete-theme',
				__( 'Delete', 'zoltiq-agents' )
			);
		}

		$stylesheet = $resolved['stylesheet'];
		$theme_name = $resolved['theme_name'];
		$certainty  = $resolved['certainty'];

		$current = wp_get_theme();
		if ( $current && ( $current->get_stylesheet() === $stylesheet || $current->get_template() === $stylesheet ) ) {
			return array(
				'success'       => false,
				'message'       => sprintf( __( 'Theme "%s" is currently active and cannot be deleted. Switch to another theme first.', 'zoltiq-agents' ), $theme_name ),
				'matched_theme' => $theme_name,
				'certainty'     => $certainty,
			);
		}

		if ( ! function_exists( 'delete_theme' ) ) {
			require_once ABSPATH . 'wp-admin/includes/theme.php';
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$result = delete_theme( $stylesheet );

		if ( is_wp_error( $result ) ) {
			return array(
				'success'       => false,
				'message'       => sprintf( __( 'Failed to delete theme "%1$s": %2$s', 'zoltiq-agents' ), $theme_name, $result->get_error_message() ),
				'matched_theme' => $theme_name,
				'certainty'     => $certainty,
			);
		}

		if ( false === $result || null === $result ) {
			return array(
				'success'       => false,
				'message'       => sprintf( __( 'Failed to delete theme "%s".', 'zoltiq-agents' ), $theme_name ),
				'matched_theme' => $theme_name,
				'certainty'     => $certainty,
			);
		}

		return array(
			'success'       => true,
			'message'       => sprintf( __( 'Theme "%s" has been deleted.', 'zoltiq-agents' ), $theme_name ),
			'matched_theme' => $theme_name,
			'certainty'     => $certainty,
		);
	}

	public static function build_candidate_response( array $resolved, string $original_input, string $action_id, string $button_label ): array {
		$actions = array();

		foreach ( $resolved['candidates'] as $candidate ) {
			$actions[] = array(
				'label'        => sprintf( '%s (%.1f/10)', $candidate['theme_name'], $candidate['certainty'] ),
				'button_label' => $button_label,
				'action'       => $action_id,
				'args'         => array( 'theme' => $candidate['stylesheet'] ),
			);
		}

		return array(
			'success'   => false,
			'message'   => sprintf( __( 'Multiple themes match "%s". Please select the correct one:', 'zoltiq-agents' ), $original_input ),
			'certainty' => $resolved['certainty'],
			'actions'   => $actions,
		);
	}
}
