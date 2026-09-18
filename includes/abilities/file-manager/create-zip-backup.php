<?php

namespace Zoltiq\Agents\Includes\Abilities\FileManager;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Backups_Storage;
use Zoltiq\Agents\Includes\Abilities\Utilities\Zip_Target_Resolver;

defined( 'ABSPATH' ) || exit;

class Create_Zip_Backup extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/create-zip-backup',
			'args' => array(
				'label'               => __( 'Create Zip Backup', 'zoltiq-agents' ),
				'description'         => __( 'Zip a plugin, theme, uploads folder, mu-plugins folder, or an arbitrary path under ABSPATH. The archive is stored under wp-content/uploads/zoltiq-backups/ with a random filename; the response returns the download URL, ABSPATH-relative path, size, and SHA-256.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-file-manager',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'target_type'    => array(
							'type'        => 'string',
							'enum'        => Zip_Target_Resolver::supported_types(),
							'description' => __( 'What to back up: plugin, theme, uploads, mu-plugins, or an arbitrary path under ABSPATH.', 'zoltiq-agents' ),
						),
						'target'         => array(
							'type'        => 'string',
							'description' => __( 'Plugin slug (e.g. "hello-dolly"), theme stylesheet (e.g. "twentytwentyfour"), or path relative to ABSPATH. Ignored for "uploads" and "mu-plugins".', 'zoltiq-agents' ),
						),
						'include_hidden' => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => __( 'When true, include dotfiles / hidden directories (e.g. .git) in the archive.', 'zoltiq-agents' ),
						),
					),
					'required'             => array( 'target_type' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'   => array( 'type' => 'boolean' ),
						'file_path' => array( 'type' => 'string' ),
						'file_url'  => array( 'type' => 'string' ),
						'size'      => array( 'type' => 'integer' ),
						'sha256'    => array( 'type' => 'string' ),
						'target'    => array( 'type' => 'string' ),
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
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		if ( ! class_exists( '\ZipArchive' ) ) {
			return array(
				'success' => false,
				'message' => __( 'PHP ZipArchive extension is not available on this server.', 'zoltiq-agents' ),
			);
		}

		$target_type    = (string) ( $input['target_type'] ?? '' );
		$target         = (string) ( $input['target'] ?? '' );
		$include_hidden = ! empty( $input['include_hidden'] );

		$resolved = Zip_Target_Resolver::resolve_source( $target_type, $target );
		if ( is_wp_error( $resolved ) ) {
			return array(
				'success' => false,
				'message' => $resolved->get_error_message(),
			);
		}

		$src_dir = rtrim( $resolved['abs_path'], '/' );

		$backups_dir = Backups_Storage::backups_path();
		if ( false === $backups_dir ) {
			return array(
				'success' => false,
				'message' => __( 'Could not create the backups directory under wp-content/uploads/zoltiq-backups.', 'zoltiq-agents' ),
			);
		}

		$filename = Backups_Storage::random_backup_filename( $target_type, $target );
		$dest     = trailingslashit( $backups_dir ) . $filename;

		$max_bytes = (int) apply_filters( 'zoltiq_agents_zip_max_bytes', 512 * 1024 * 1024 );

		$estimated_bytes = self::estimate_tree_size( $src_dir, $include_hidden );
		if ( $estimated_bytes > $max_bytes ) {
			return array(
				'success' => false,
				'message' => sprintf(
					__( 'Source tree is ~%1$d bytes which exceeds the configured backup cap of %2$d bytes.', 'zoltiq-agents' ),
					$estimated_bytes,
					$max_bytes
				),
			);
		}

		$zip  = new \ZipArchive();
		$open = $zip->open( $dest, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );
		if ( true !== $open ) {
			return array(
				'success' => false,
				'message' => sprintf(
					__( 'Could not open a new zip archive for writing (ZipArchive error %d).', 'zoltiq-agents' ),
					(int) $open
				),
			);
		}

		$prefix   = self::archive_prefix( $resolved['label'], $src_dir );
		$appended = self::append_dir_to_zip( $zip, $src_dir, $prefix, $include_hidden );

		if ( is_wp_error( $appended ) ) {
			$zip->close();
			if ( file_exists( $dest ) ) {
				wp_delete_file( $dest );
			}
			return array(
				'success' => false,
				'message' => $appended->get_error_message(),
			);
		}

		$zip->close();

		if ( ! is_file( $dest ) ) {
			return array(
				'success' => false,
				'message' => __( 'Zip archive was not written to disk.', 'zoltiq-agents' ),
			);
		}

		$size = (int) filesize( $dest );
		if ( $size > $max_bytes ) {
			wp_delete_file( $dest );
			return array(
				'success' => false,
				'message' => sprintf(
					__( 'Zip archive size (%1$d bytes) exceeds the configured cap (%2$d bytes).', 'zoltiq-agents' ),
					$size,
					$max_bytes
				),
			);
		}

		return array(
			'success'   => true,
			'file_path' => Backups_Storage::to_abspath_relative( $dest ),
			'file_url'  => Backups_Storage::url_for( $dest ),
			'size'      => $size,
			'sha256'    => Backups_Storage::sha256_of( $dest ),
			'target'    => $resolved['label'],
			'message'   => sprintf(
				__( 'Zip created with %1$d entries (%2$d bytes).', 'zoltiq-agents' ),
				(int) $appended,
				$size
			),
		);
	}

	private static function estimate_tree_size( string $dir, bool $include_hidden ): int {
		if ( ! is_dir( $dir ) ) {
			return 0;
		}
		$dir   = rtrim( $dir, '/' );
		$total = 0;
		try {
			$iter = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::SELF_FIRST
			);
		} catch ( \Throwable $e ) {
			return 0;
		}

		foreach ( $iter as $entry ) {
			if ( ! $entry->isFile() ) {
				continue;
			}
			$rel = self::normalize_relative( $entry->getPathname(), $dir );
			if ( '' === $rel ) {
				continue;
			}
			if ( ! $include_hidden && self::has_hidden_segment( $rel ) ) {
				continue;
			}
			$total += (int) $entry->getSize();
		}
		return $total;
	}

	private static function archive_prefix( string $label, string $src_dir ): string {
		$base = basename( $src_dir );
		if ( str_starts_with( $label, 'plugin:' ) || str_starts_with( $label, 'theme:' ) ) {
			return $base . '/';
		}
		if ( 'uploads' === $label ) {
			return 'uploads/';
		}
		if ( 'mu-plugins' === $label ) {
			return 'mu-plugins/';
		}
		return $base . '/';
	}

	private static function append_dir_to_zip( \ZipArchive $zip, string $src, string $prefix, bool $include_hidden ) {
		$src   = rtrim( $src, '/' );
		$count = 0;

		try {
			$iter = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $src, \FilesystemIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::SELF_FIRST
			);
		} catch ( \Throwable $e ) {
			return new \WP_Error(
				'zip_iterate_failed',
				sprintf(
					__( 'Could not iterate source directory: %s', 'zoltiq-agents' ),
					$e->getMessage()
				)
			);
		}

		foreach ( $iter as $entry ) {
			$rel = self::normalize_relative( $entry->getPathname(), $src );
			if ( '' === $rel ) {
				continue;
			}

			if ( ! $include_hidden && self::has_hidden_segment( $rel ) ) {
				continue;
			}

			if ( $entry->isDir() ) {
				$zip->addEmptyDir( $prefix . $rel );
				continue;
			}
			if ( $entry->isFile() ) {
				if ( ! $zip->addFile( $entry->getPathname(), $prefix . $rel ) ) {
					return new \WP_Error(
						'zip_add_failed',
						sprintf(
							__( 'Could not add file to archive: %s', 'zoltiq-agents' ),
							$rel
						)
					);
				}
				++$count;
			}
		}

		return $count;
	}

	private static function normalize_relative( string $pathname, string $src ): string {
		$src_norm = str_replace( '\\', '/', rtrim( $src, '/' ) );
		$path     = str_replace( '\\', '/', $pathname );
		if ( 0 === strpos( $path, $src_norm . '/' ) ) {
			return substr( $path, strlen( $src_norm ) + 1 );
		}
		return ltrim( $path, '/' );
	}

	private static function has_hidden_segment( string $relative ): bool {
		foreach ( explode( '/', $relative ) as $segment ) {
			if ( '' !== $segment && '.' === $segment[0] ) {
				return true;
			}
		}
		return false;
	}
}
