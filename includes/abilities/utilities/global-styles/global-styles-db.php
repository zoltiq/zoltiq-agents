<?php

namespace Zoltiq\Agents\Includes\Abilities\Utilities\Global_Styles;

defined( 'ABSPATH' ) || exit;

final class Global_Styles_Db {

	public const POST_TYPE = 'wp_global_styles';
	public const THEME_TAX = 'wp_theme';

	public const SECTIONS = array( 'colors', 'typography', 'spacing', 'layout', 'blockStyles', 'customCss' );

	public const SECTION_PATHS = array(
		'colors'      => array( array( 'settings', 'color' ), array( 'styles', 'color' ) ),
		'typography'  => array( array( 'settings', 'typography' ), array( 'styles', 'typography' ) ),
		'spacing'     => array( array( 'settings', 'spacing' ), array( 'styles', 'spacing' ) ),
		'layout'      => array( array( 'settings', 'layout' ) ),
		'blockStyles' => array( array( 'settings', 'blocks' ), array( 'styles', 'blocks' ) ),
		'customCss'   => array( array( 'styles', 'css' ) ),
	);

	public static function valid_sections(): array {
		return self::SECTIONS;
	}

	public static function valid_section( string $section ): bool {
		return in_array( self::normalize_section( $section ), self::SECTIONS, true );
	}

	public static function normalize_section( string $section ): string {
		$section = strtolower( trim( $section ) );
		$section = str_replace( array( '_', '-', ' ' ), '', $section );
		$map     = array(
			'colors'      => 'colors',
			'typography'  => 'typography',
			'spacing'     => 'spacing',
			'layout'      => 'layout',
			'blockstyles' => 'blockStyles',
			'customcss'   => 'customCss',
		);
		return $map[ $section ] ?? $section;
	}

	public static function find_by_theme( string $theme = '' ): ?\WP_Post {
		$theme = '' !== $theme ? sanitize_key( $theme ) : (string) get_stylesheet();
		if ( '' === $theme ) {
			return null;
		}

		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'tax_query'      => array( 
					array(
						'taxonomy' => self::THEME_TAX,
						'field'    => 'name',
						'terms'    => $theme,
					),
				),
			)
		);
		return ! empty( $posts ) ? $posts[0] : null;
	}

	public static function list_all( int $limit = 200 ): array {
		return (array) get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => max( 1, min( 500, $limit ) ),
				'no_found_rows'  => true,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}

	public static function create( string $theme, array $data ) {
		$theme = '' !== $theme ? sanitize_key( $theme ) : (string) get_stylesheet();
		if ( '' === $theme ) {
			return new \WP_Error( 'invalid_theme', __( 'Theme is required.', 'zoltiq-agents' ) );
		}

		$valid = self::validate_data( $data );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$valid = self::validate_block_styles( $data );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( self::find_by_theme( $theme ) ) {
			return new \WP_Error( 'exists', __( 'A Global Styles record already exists for this theme. Use update instead.', 'zoltiq-agents' ) );
		}

		$json = self::encode_json( $data );
		if ( is_wp_error( $json ) ) {
			return $json;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => sprintf( __( 'Custom Styles - %s', 'zoltiq-agents' ), $theme ),
				'post_name'    => 'wp-global-styles-' . $theme,
				'post_content' => $json,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		wp_set_object_terms( (int) $post_id, $theme, self::THEME_TAX );
		return (int) $post_id;
	}

	public static function update( \WP_Post $post, array $data, bool $merge = true ) {
		$existing = self::decode_content( $post );
		$new      = $merge ? self::deep_merge( $existing, $data ) : $data;

		$valid = self::validate_data( $new );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$valid = self::validate_block_styles( $new );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$json = self::encode_json( $new );
		if ( is_wp_error( $json ) ) {
			return $json;
		}

		$result = wp_update_post(
			array(
				'ID'           => (int) $post->ID,
				'post_content' => $json,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return (int) $result;
	}

	public static function delete( \WP_Post $post ): bool {
		$result = wp_delete_post( (int) $post->ID, true );
		return false !== $result && null !== $result;
	}


	public static function get_section( \WP_Post $post, string $section ): array {
		$section = self::normalize_section( $section );
		$data    = self::decode_content( $post );
		$out     = array();

		foreach ( ( self::SECTION_PATHS[ $section ] ?? array() ) as $path ) {
			$value = self::path_get( $data, $path );
			if ( null !== $value ) {
				self::path_set( $out, $path, $value );
			}
		}
		return $out;
	}


	public static function update_section( \WP_Post $post, string $section, array $section_data ) {
		$section = self::normalize_section( $section );
		if ( ! in_array( $section, self::SECTIONS, true ) ) {
			return new \WP_Error(
				'invalid_section',
				sprintf( __( 'Section must be one of: %s.', 'zoltiq-agents' ), implode( ', ', self::SECTIONS ) )
			);
		}

		$existing = self::decode_content( $post );
		foreach ( self::SECTION_PATHS[ $section ] as $path ) {
			$value = self::path_get( $section_data, $path );
			if ( null !== $value ) {
				self::path_set( $existing, $path, $value );
			}
		}

		return self::update( $post, $existing, false );
	}

	public static function delete_section( \WP_Post $post, string $section ) {
		$section = self::normalize_section( $section );
		if ( ! in_array( $section, self::SECTIONS, true ) ) {
			return new \WP_Error(
				'invalid_section',
				sprintf( __( 'Section must be one of: %s.', 'zoltiq-agents' ), implode( ', ', self::SECTIONS ) )
			);
		}

		$existing = self::decode_content( $post );
		foreach ( self::SECTION_PATHS[ $section ] as $path ) {
			self::path_delete( $existing, $path );
		}

		return self::update( $post, $existing, false );
	}

	public static function decode_content( \WP_Post $post ): array {
		$content = trim( (string) $post->post_content );
		if ( '' === $content ) {
			return array();
		}
		$decoded = json_decode( $content, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	public static function to_row( \WP_Post $post, bool $include_content = false ): array {
		$theme = self::get_post_theme( $post );
		$row   = array(
			'source'              => 'db',
			'post_id'             => (int) $post->ID,
			'title'               => (string) $post->post_title,
			'theme'               => $theme,
			'is_active_theme'     => $theme === (string) get_stylesheet(),
			'customized_sections' => self::get_customized_sections( $post ),
			'modified'            => (string) $post->post_modified_gmt,
		);

		if ( $include_content ) {
			$row['data'] = self::decode_content( $post );
		}
		return $row;
	}

	public static function get_post_theme( \WP_Post $post ): string {
		$terms = wp_get_object_terms( (int) $post->ID, self::THEME_TAX, array( 'fields' => 'names' ) );
		return ! is_wp_error( $terms ) && ! empty( $terms ) ? (string) $terms[0] : '';
	}

	public static function get_customized_sections( \WP_Post $post ): array {
		$data = self::decode_content( $post );
		if ( empty( $data ) ) {
			return array();
		}

		$customized = array();
		foreach ( self::SECTION_PATHS as $section => $paths ) {
			foreach ( $paths as $path ) {
				$value = self::path_get( $data, $path );
				if ( null === $value ) {
					continue;
				}
				if ( is_array( $value ) && empty( $value ) ) {
					continue;
				}
				$customized[] = $section;
				break;
			}
		}
		return $customized;
	}

	public static function validate_data( array $data ) {
		if ( empty( $data ) ) {
			return new \WP_Error( 'empty_content', __( 'Global Styles content cannot be empty.', 'zoltiq-agents' ) );
		}

		$allowed = array( 'version', 'settings', 'styles', 'customTemplates', 'templateParts', 'patterns', '$schema', 'title', 'description', 'blockTypes' );
		foreach ( array_keys( $data ) as $key ) {
			if ( ! in_array( $key, $allowed, true ) ) {
				return new \WP_Error(
					'invalid_structure',
					sprintf( __( 'Unknown top-level key "%s" in theme.json structure. Allowed: $schema, version, title, description, settings, styles, customTemplates, templateParts, patterns, blockTypes.', 'zoltiq-agents' ), $key )
				);
			}
		}

	
		foreach ( array( 'settings', 'styles' ) as $key ) {
			if ( isset( $data[ $key ] ) && ! is_array( $data[ $key ] ) ) {
				return new \WP_Error(
					'invalid_structure',
					sprintf( __( '"%s" must be a JSON object.', 'zoltiq-agents' ), $key )
				);
			}
		}

		return true;
	}

	public static function validate_block_styles( array $data ) {
		$blocks = array();
		foreach ( array( array( 'settings', 'blocks' ), array( 'styles', 'blocks' ) ) as $path ) {
			$value = self::path_get( $data, $path );
			if ( is_array( $value ) ) {
				foreach ( array_keys( $value ) as $name ) {
					$blocks[ (string) $name ] = true;
				}
			}
		}
		if ( empty( $blocks ) ) {
			return true;
		}

		if ( ! class_exists( '\WP_Block_Type_Registry' ) ) {
			return true; 
		}

		$registry = \WP_Block_Type_Registry::get_instance();
		$invalid  = array();
		foreach ( array_keys( $blocks ) as $name ) {
			if ( ! $registry->is_registered( $name ) ) {
				$invalid[] = $name;
			}
		}

		if ( ! empty( $invalid ) ) {
			return new \WP_Error(
				'unregistered_blocks',
				sprintf( __( 'Block style references unregistered blocks: %s.', 'zoltiq-agents' ), implode( ', ', $invalid ) ),
				array( 'invalid_blocks' => $invalid )
			);
		}

		return true;
	}

	public static function parse_json( string $json ) {
		$json = trim( $json );
		if ( '' === $json ) {
			return new \WP_Error( 'empty_content', __( 'JSON content cannot be empty.', 'zoltiq-agents' ) );
		}
		$decoded = json_decode( $json, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new \WP_Error(
				'invalid_json',
				sprintf( __( 'Invalid JSON: %s', 'zoltiq-agents' ), json_last_error_msg() )
			);
		}
		if ( ! is_array( $decoded ) ) {
			return new \WP_Error( 'invalid_json', __( 'JSON must decode to an object.', 'zoltiq-agents' ) );
		}
		return $decoded;
	}

	public static function encode_json( array $data ) {
		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $json ) {
			return new \WP_Error( 'json_encode_failed', __( 'Could not encode Global Styles data as JSON.', 'zoltiq-agents' ) );
		}
		return (string) $json;
	}

	public static function path_get( array $data, array $path ) {
		foreach ( $path as $key ) {
			if ( ! is_array( $data ) || ! array_key_exists( $key, $data ) ) {
				return null;
			}
			$data = $data[ $key ];
		}
		return $data;
	}

	public static function path_set( array &$data, array $path, $value ): void {
		if ( empty( $path ) ) {
			return;
		}
		$cursor = &$data;
		foreach ( $path as $key ) {
			if ( ! isset( $cursor[ $key ] ) || ! is_array( $cursor[ $key ] ) ) {
				$cursor[ $key ] = array();
			}
			$cursor = &$cursor[ $key ];
		}
		$cursor = $value;
	}

	public static function path_delete( array &$data, array $path ): void {
		if ( empty( $path ) ) {
			return;
		}
		$leaf   = array_pop( $path );
		$cursor = &$data;
		foreach ( $path as $key ) {
			if ( ! is_array( $cursor ) || ! array_key_exists( $key, $cursor ) ) {
				return;
			}
			$cursor = &$cursor[ $key ];
		}
		if ( is_array( $cursor ) ) {
			unset( $cursor[ $leaf ] );
		}
	}

	public static function deep_merge( array $a, array $b ): array {
		foreach ( $b as $key => $value ) {
			if ( is_array( $value ) && isset( $a[ $key ] ) && is_array( $a[ $key ] ) ) {
				$a[ $key ] = self::deep_merge( $a[ $key ], $value );
			} else {
				$a[ $key ] = $value;
			}
		}
		return $a;
	}
}
