<?php

namespace Zoltiq\Agents\Includes\Abilities\FileManager;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\File_Mods_Guard;
use Zoltiq\Agents\Includes\Abilities\Utilities\Zip_Target_Resolver;

defined( 'ABSPATH' ) || exit;

class Extract_Zip_Backup extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/extract-zip-backup',
			'args' => array(
				'label'               => __( 'Extract Zip Backup', 'zoltiq-agents' ),
				'description'         => __( 'Extract a zip archive (already on disk or fetched from a URL) into the resolved target directory. Every entry is checked for path traversal before extraction; DISALLOW_FILE_MODS short-circuits the ability.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-file-manager',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'source'      => array(
							'type'                 => 'object',
							'description'          => __( 'Where to read the zip from. Provide either "path" (ABSPATH-relative) or "url".', 'zoltiq-agents' ),
							'properties'           => array(
								'path' => array(
									'type'        => 'string',
									'description' => __( 'ABSPATH-relative path to a zip already on disk.', 'zoltiq-agents' ),
								),
								'url'  => array(
									'type'        => 'string',
									'format'      => 'uri',
									'description' => __( 'Remote HTTP(S) URL for the zip.', 'zoltiq-agents' ),
								),
							),
							'anyOf'                => array(
								array( 'required' => array( 'path' ) ),
								array( 'required' => array( 'url' ) ),
							),
							'additionalProperties' => false,
						),
						'target_type' => array(
							'type'        => 'string',
							'enum'        => Zip_Target_Resolver::supported_types(),
							'description' => __( 'Destination type: plugin, theme, uploads, mu-plugins, or an arbitrary path under ABSPATH.', 'zoltiq-agents' ),
						),
						'target'      => array(
							'type'        => 'string',
							'description' => __( 'Plugin slug, theme stylesheet, or ABSPATH-relative path. Ignored for "uploads" and "mu-plugins".', 'zoltiq-agents' ),
						),
						'overwrite'   => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => __( 'When true, existing files in the target directory are overwritten. When false (default), unzip_file() is used with clobber=false semantics.', 'zoltiq-agents' ),
						),
					),
					'required'             => array( 'source', 'target_type' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'      => array( 'type' => 'boolean' ),
						'extracted_to' => array( 'type' => 'string' ),
						'files_count'  => array( 'type' => 'integer' ),
						'target'       => array( 'type' => 'string' ),
						'message'      => array( 'type' => 'string' ),
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
						'destructive' => true,
						'idempotent'  => false,
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

		if ( ! class_exists( '\ZipArchive' ) ) {
			return array(
				'success' => false,
				'message' => __( 'PHP ZipArchive extension is not available on this server.', 'zoltiq-agents' ),
			);
		}

		$source = isset( $input['source'] ) && is_array( $input['source'] ) ? $input['source'] : array();
		if ( empty( $source['path'] ) && empty( $source['url'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Provide "source.path" (zip already on disk) or "source.url" (remote fetch).', 'zoltiq-agents' ),
			);
		}

		$target_type = (string) ( $input['target_type'] ?? '' );
		$target      = (string) ( $input['target'] ?? '' );
		$overwrite   = ! empty( $input['overwrite'] );

		$resolved = Zip_Target_Resolver::resolve_destination( $target_type, $target );
		if ( is_wp_error( $resolved ) ) {
			return array(
				'success' => false,
				'message' => $resolved->get_error_message(),
			);
		}
		$dest_dir = rtrim( $resolved['abs_path'], '/' );

		$fetched = $this->resolve_zip_source( $source );
		if ( isset( $fetched['error'] ) ) {
			return array(
				'success' => false,
				'message' => $fetched['error'],
			);
		}
		$zip_path        = $fetched['path'];
		$delete_zip_when = $fetched['cleanup']; 

		$audit = $this->audit_zip_entries( $zip_path );
		if ( isset( $audit['error'] ) ) {
			if ( 'always' === $delete_zip_when && file_exists( $zip_path ) ) {
				wp_delete_file( $zip_path );
			}
			return array(
				'success' => false,
				'message' => $audit['error'],
			);
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		global $wp_filesystem;
		if ( ! function_exists( '\WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! WP_Filesystem() ) {
			if ( 'always' === $delete_zip_when && file_exists( $zip_path ) ) {
				wp_delete_file( $zip_path );
			}
			return array(
				'success' => false,
				'message' => __( 'Could not initialize WP_Filesystem for extraction.', 'zoltiq-agents' ),
			);
		}

		if ( ! wp_mkdir_p( $dest_dir ) ) {
			if ( 'always' === $delete_zip_when && file_exists( $zip_path ) ) {
				wp_delete_file( $zip_path );
			}
			return array(
				'success' => false,
				'message' => sprintf(
					__( 'Could not create destination directory: %s', 'zoltiq-agents' ),
					$dest_dir
				),
			);
		}

		if ( $overwrite ) {
			$extracted = $this->extract_with_overwrite( $zip_path, $dest_dir );
		} else {
			$extracted = unzip_file( $zip_path, $dest_dir );
		}

		if ( 'always' === $delete_zip_when && file_exists( $zip_path ) ) {
			wp_delete_file( $zip_path );
		}

		if ( is_wp_error( $extracted ) ) {
			return array(
				'success' => false,
				'message' => $extracted->get_error_message(),
			);
		}

		return array(
			'success'      => true,
			'extracted_to' => $dest_dir,
			'files_count'  => (int) $audit['files_count'],
			'target'       => $resolved['label'],
			'message'      => sprintf(
				__( 'Extracted %1$d entries into %2$s.', 'zoltiq-agents' ),
				(int) $audit['files_count'],
				$dest_dir
			),
		);
	}

	private function resolve_zip_source( array $source ): array {
		if ( ! empty( $source['url'] ) ) {
			$url = sanitize_url( (string) $source['url'] );
			if ( ! wp_http_validate_url( $url ) ) {
				return array( 'error' => __( 'A valid http(s) URL is required.', 'zoltiq-agents' ) );
			}
			require_once ABSPATH . 'wp-admin/includes/file.php';
			$tmp = download_url( $url );
			if ( is_wp_error( $tmp ) ) {
				return array( 'error' => $tmp->get_error_message() );
			}
			return array(
				'path'    => (string) $tmp,
				'cleanup' => 'always',
			);
		}

		$rel_path = sanitize_text_field( (string) $source['path'] );
		if ( '' === $rel_path ) {
			return array( 'error' => __( 'The "source.path" is empty.', 'zoltiq-agents' ) );
		}
		$base = rtrim( realpath( ABSPATH ) ?: ABSPATH, '/' );
		$abs  = realpath( $base . '/' . ltrim( $rel_path, '/' ) );
		if ( false === $abs || 0 !== strpos( $abs, $base . '/' ) ) {
			return array( 'error' => __( 'The "source.path" must resolve to a zip file inside ABSPATH.', 'zoltiq-agents' ) );
		}
		if ( ! is_file( $abs ) ) {
			return array( 'error' => __( 'The zip file does not exist at the given "source.path".', 'zoltiq-agents' ) );
		}
		return array(
			'path'    => $abs,
			'cleanup' => 'never',
		);
	}

	private function audit_zip_entries( string $zip_path ): array {
		$zip = new \ZipArchive();
		$rc  = $zip->open( $zip_path, \ZipArchive::CHECKCONS );
		if ( true !== $rc ) {
			return array(
				'error' => sprintf(
					__( 'Could not open zip for inspection (ZipArchive error %d).', 'zoltiq-agents' ),
					(int) $rc
				),
			);
		}

		$max_bytes = (int) apply_filters( 'zoltiq_agents_zip_max_bytes', 512 * 1024 * 1024 );

		$files_count  = 0;
		$uncompressed = 0;
		$num          = $zip->numFiles;
		for ( $i = 0; $i < $num; $i++ ) {
			$stat = $zip->statIndex( $i );
			if ( ! is_array( $stat ) ) {
				$zip->close();
				return array(
					'error' => sprintf(
						__( 'Could not stat zip entry %d.', 'zoltiq-agents' ),
						$i
					),
				);
			}
			$name = (string) ( $stat['name'] ?? '' );
			if ( '' === $name ) {
				$zip->close();
				return array( 'error' => __( 'Zip archive contains an unnamed entry.', 'zoltiq-agents' ) );
			}
			if ( false !== strpos( $name, "\0" ) ) {
				$zip->close();
				return array( 'error' => __( 'Zip archive contains an entry with a null byte.', 'zoltiq-agents' ) );
			}
			if ( false !== strpos( $name, '\\' ) ) {
				$zip->close();
				return array(
					'error' => sprintf(
						__( 'Zip archive contains an entry with a backslash: %s', 'zoltiq-agents' ),
						$name
					),
				);
			}
			if ( '/' === substr( $name, 0, 1 ) ) {
				$zip->close();
				return array(
					'error' => sprintf(
						__( 'Zip archive contains an entry with an absolute path: %s', 'zoltiq-agents' ),
						$name
					),
				);
			}
			$parts = explode( '/', $name );
			foreach ( $parts as $part ) {
				if ( '..' === $part ) {
					$zip->close();
					return array(
						'error' => sprintf(
							__( 'Zip archive contains a path-traversal entry: %s', 'zoltiq-agents' ),
							$name
						),
					);
				}
			}
			$uncompressed += (int) ( $stat['size'] ?? 0 );
			if ( '/' !== substr( $name, -1 ) ) {
				++$files_count;
			}
		}

		$zip->close();

		if ( $uncompressed > $max_bytes ) {
			return array(
				'error' => sprintf(
					__( 'Zip decompresses to %1$d bytes which exceeds the configured cap of %2$d bytes.', 'zoltiq-agents' ),
					$uncompressed,
					$max_bytes
				),
			);
		}

		return array(
			'files_count'  => $files_count,
			'uncompressed' => $uncompressed,
		);
	}

	private function extract_with_overwrite( string $zip_path, string $dest_dir ) {
		$zip = new \ZipArchive();
		$rc  = $zip->open( $zip_path );
		if ( true !== $rc ) {
			return new \WP_Error(
				'zip_open_failed',
				sprintf(
					__( 'Could not open zip for extraction (ZipArchive error %d).', 'zoltiq-agents' ),
					(int) $rc
				)
			);
		}
		if ( ! $zip->extractTo( $dest_dir ) ) {
			$zip->close();
			return new \WP_Error(
				'zip_extract_failed',
				__( 'ZipArchive::extractTo() failed.', 'zoltiq-agents' )
			);
		}
		$zip->close();
		return true;
	}
}
