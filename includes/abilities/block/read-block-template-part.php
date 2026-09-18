<?php

namespace Zoltiq\Agents\Includes\Abilities\Block;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Template_Part\Template_Part_Db;
use Zoltiq\Agents\Includes\Abilities\Utilities\Template_Part\Template_Part_Detector;
use Zoltiq\Agents\Includes\Abilities\Utilities\Template_Part\Template_Part_File;

defined( 'ABSPATH' ) || exit;

class Read_Block_Template_Part extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/read-block-template-part',
			'args' => array(
				'label'               => __( 'Read Block Template Part', 'zoltiq-agents' ),
				'description'         => __( 'Reads a single block template part by slug from the database, theme, or plugin. When the slug exists in multiple locations, returns "multiple_locations" with the candidate list — pick one with source / theme_type / plugin_slug.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-block',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'slug'        => array(
							'type'        => 'string',
							'description' => __( 'Template part slug (bare, e.g. "header").', 'zoltiq-agents' ),
						),
						'source'      => array(
							'type'    => 'string',
							'enum'    => array( '', 'db', 'theme', 'child_theme', 'plugin' ),
							'default' => '',
						),
						'theme_type'  => array(
							'type'    => 'string',
							'enum'    => array( '', 'child', 'parent', 'theme' ),
							'default' => '',
						),
						'plugin_slug' => array(
							'type'    => 'string',
							'default' => '',
						),
						'theme'       => array(
							'type'        => 'string',
							'default'     => '',
							'description' => __( 'Theme hint used when looking up DB rows. Defaults to the active stylesheet.', 'zoltiq-agents' ),
						),
					),
					'required'             => array( 'slug' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'   => array( 'type' => 'boolean' ),
						'part'      => array( 'type' => 'object' ),
						'locations' => array( 'type' => 'array' ),
						'warnings'  => array( 'type' => 'array' ),
						'message'   => array( 'type' => 'string' ),
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
		$slug        = sanitize_title( (string) ( $input['slug'] ?? '' ) );
		$source      = sanitize_text_field( $input['source'] ?? '' );
		$theme_type  = sanitize_text_field( $input['theme_type'] ?? '' );
		$plugin_slug = sanitize_key( $input['plugin_slug'] ?? '' );
		$theme_hint  = sanitize_key( $input['theme'] ?? '' );

		if ( '' === $slug ) {
			return array(
				'success' => false,
				'message' => __( 'Slug is required.', 'zoltiq-agents' ),
			);
		}

		$locations = Template_Part_Detector::locate( $slug, $theme_hint );

		if ( empty( $locations ) ) {
			return array(
				'success'   => false,
				'message'   => sprintf( __( 'No template part with slug "%s" was found. Use template-part-create to add one.', 'zoltiq-agents' ), $slug ),
				'locations' => array(),
			);
		}

		$selected = Template_Part_Detector::select( $locations, $source, $theme_type, $plugin_slug );

		if ( is_wp_error( $selected ) ) {
			$data = $selected->get_error_data();
			return array(
				'success'    => false,
				'message'    => $selected->get_error_message(),
				'locations'  => $locations,
				'candidates' => is_array( $data ) ? ( $data['locations'] ?? array() ) : array(),
			);
		}

		$warnings = $this->collect_warnings( $locations, $selected );
		$part     = $this->materialise( $selected );

		if ( is_wp_error( $part ) ) {
			return array(
				'success'   => false,
				'message'   => $part->get_error_message(),
				'locations' => $locations,
			);
		}

		return array(
			'success'   => true,
			'part'      => $part,
			'locations' => $locations,
			'warnings'  => $warnings,
		);
	}

	private function collect_warnings( array $locations, array $selected ): array {
		$warnings  = array();
		$effective = Template_Part_Detector::effective( $locations );

		if ( count( $locations ) > 1 ) {
			$warnings[] = __( 'This slug exists in multiple locations. WordPress always serves the highest-priority copy: DB → child theme → parent theme → plugin.', 'zoltiq-agents' );
		}

		if ( $effective && ( $selected['source'] ?? '' ) !== ( $effective['source'] ?? '' ) ) {
			$warnings[] = sprintf(
				__( 'You are reading the %1$s copy, but WordPress is currently serving the %2$s copy.', 'zoltiq-agents' ),
				$this->describe( $selected ),
				$this->describe( $effective )
			);
		}

		foreach ( $locations as $loc ) {
			if ( ( $loc['source'] ?? '' ) === 'plugin' && false === ( $loc['plugin_active'] ?? true ) ) {
				$warnings[] = sprintf( __( 'Plugin "%s" is inactive — its template part will not register until the plugin is activated.', 'zoltiq-agents' ), $loc['plugin'] ?? '' );
			}
		}

		return $warnings;
	}

	private function describe( array $loc ): string {
		$src = (string) ( $loc['source'] ?? '' );
		if ( 'theme' === $src ) {
			return 'theme:' . ( $loc['theme_type'] ?? 'theme' );
		}
		if ( 'plugin' === $src ) {
			return 'plugin:' . ( $loc['plugin'] ?? '' );
		}
		return $src;
	}

	private function materialise( array $loc ) {
		$src = (string) ( $loc['source'] ?? '' );

		if ( 'db' === $src ) {
			$post = get_post( (int) ( $loc['post_id'] ?? 0 ) );
			if ( ! $post ) {
				return new \WP_Error( 'db_post_missing', __( 'Template part post not found in the database.', 'zoltiq-agents' ) );
			}
			return Template_Part_Db::to_row( $post );
		}

		$path     = (string) ( $loc['path'] ?? '' );
		$contents = Template_Part_File::read_file( $path );
		if ( is_wp_error( $contents ) ) {
			return $contents;
		}

		$row = array(
			'source'   => $src,
			'slug'     => (string) ( $loc['slug'] ?? '' ),
			'path'     => $path,
			'content'  => $contents,
			'writable' => (bool) ( $loc['writable'] ?? false ),
		);

		if ( 'theme' === $src ) {
			$row['theme']      = (string) ( $loc['theme'] ?? '' );
			$row['theme_type'] = (string) ( $loc['theme_type'] ?? '' );
			$row['full_slug']  = ( $row['theme'] ) . '//' . $row['slug'];
		} else {
			$row['plugin']        = (string) ( $loc['plugin'] ?? '' );
			$row['plugin_active'] = (bool) ( $loc['plugin_active'] ?? false );
		}

		return $row;
	}
}
