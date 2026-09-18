<?php

namespace Zoltiq\Agents\Includes\Abilities\FileManager;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Backups_Storage;
use Zoltiq\Agents\Includes\Abilities\Utilities\File_Mods_Guard;

defined( 'ABSPATH' ) || exit;

class Upload_Zip_Backup extends Ability_Definition {

	public const CHUNK_SWEEP_HOOK = 'zoltiq_agents_zip_upload_sweep_chunks';

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/upload-zip-backup',
			'args' => array(
				'label'               => __( 'Upload Zip Backup', 'zoltiq-agents' ),
				'description'         => __( "Upload a zip archive to wp-content/uploads/zoltiq-backups/ for later extraction via zip-extract. Three input modes:\n\n  1) \"data\" (base64) — single-shot, best for small zips.\n  2) \"url\" — server-side fetch via download_url().\n  3) \"data\" + \"chunk\" — session/index/is_final protocol; ≤ 8 MB base64 per chunk, ≤ 64 MB base64 per session, staged under zoltiq-staging/.\n\nOn success the response carries file_path (ABSPATH-relative), file_url, size, and sha256; hand file_path to zip-extract on the destination site.", 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-file-manager',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'url'            => array(
							'type'        => 'string',
							'format'      => 'uri',
							'description' => __( 'Remote HTTP(S) URL to fetch. Mutually exclusive with "data".', 'zoltiq-agents' ),
						),
						'data'           => array(
							'type'        => 'string',
							'description' => __( 'Base64-encoded zip bytes (raw or "data:application/zip;base64,…"). Pair with "chunk" to stream large uploads.', 'zoltiq-agents' ),
						),
						'chunk'          => array(
							'type'                 => 'object',
							'description'          => __( 'Multi-part upload marker paired with "data". Send successive chunks under the same "session_id" with 0-based sequential "index". The final call must set "is_final":true.', 'zoltiq-agents' ),
							'properties'           => array(
								'session_id' => array(
									'type'    => 'string',
									'pattern' => '^[A-Za-z0-9_-]{8,64}$',
								),
								'index'      => array(
									'type'    => 'integer',
									'minimum' => 0,
								),
								'is_final'   => array(
									'type' => 'boolean',
								),
								'total'      => array(
									'type'    => 'integer',
									'minimum' => 1,
								),
							),
							'required'             => array( 'session_id', 'index' ),
							'additionalProperties' => false,
						),
						'filename_hint'  => array(
							'type'        => 'string',
							'description' => __( 'Optional filename hint. Sanitized and combined with a random suffix; never used verbatim.', 'zoltiq-agents' ),
						),
					),
					'anyOf'                => array(
						array( 'required' => array( 'url' ) ),
						array( 'required' => array( 'data' ) ),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'        => array( 'type' => 'boolean' ),
						'finalized'      => array( 'type' => 'boolean' ),
						'file_path'      => array( 'type' => 'string' ),
						'file_url'       => array( 'type' => 'string' ),
						'size'           => array( 'type' => 'integer' ),
						'sha256'         => array( 'type' => 'string' ),
						'session_id'     => array( 'type' => 'string' ),
						'chunk_received' => array( 'type' => 'integer' ),
						'bytes_staged'   => array( 'type' => 'integer' ),
						'message'        => array( 'type' => 'string' ),
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
		$blocked = File_Mods_Guard::blocked_response( 'install' );
		if ( null !== $blocked ) {
			return $blocked;
		}

		$url  = sanitize_url( (string) ( $input['url'] ?? '' ) );
		$data = (string) ( $input['data'] ?? '' );

		if ( '' === $url && '' === $data ) {
			return array(
				'success' => false,
				'message' => __( 'Provide either "url" (remote fetch) or "data" (base64 zip bytes).', 'zoltiq-agents' ),
			);
		}

		$filename_hint = sanitize_file_name( (string) ( $input['filename_hint'] ?? '' ) );

		require_once ABSPATH . 'wp-admin/includes/file.php';

		if ( '' !== $url ) {
			return $this->finalize_from_url( $url, $filename_hint );
		}

		if ( isset( $input['chunk'] ) && is_array( $input['chunk'] ) ) {
			return $this->handle_chunk( $data, $input['chunk'], $filename_hint );
		}

		return $this->finalize_from_base64( $data, $filename_hint );
	}

	private function finalize_from_url( string $url, string $filename_hint ): array {
		if ( ! wp_http_validate_url( $url ) ) {
			return array(
				'success' => false,
				'message' => __( 'A valid http(s) URL is required.', 'zoltiq-agents' ),
			);
		}
		$tmp = download_url( $url );
		if ( is_wp_error( $tmp ) ) {
			return array(
				'success' => false,
				'message' => $tmp->get_error_message(),
			);
		}

		$magic_error = self::validate_zip_magic( $tmp );
		if ( null !== $magic_error ) {
			wp_delete_file( $tmp );
			return array(
				'success' => false,
				'message' => $magic_error,
			);
		}

		$size_cap_error = self::validate_size_cap( (int) filesize( $tmp ) );
		if ( null !== $size_cap_error ) {
			wp_delete_file( $tmp );
			return array(
				'success' => false,
				'message' => $size_cap_error,
			);
		}

		return $this->move_to_backups( $tmp, $filename_hint, 'url' );
	}

	private function finalize_from_base64( string $raw, string $filename_hint ): array {
		$decoded = $this->decode_base64_payload( $raw );
		if ( isset( $decoded['error'] ) ) {
			return array(
				'success' => false,
				'message' => $decoded['error'],
			);
		}

		$size_cap_error = self::validate_size_cap( strlen( $decoded['decoded'] ) );
		if ( null !== $size_cap_error ) {
			return array(
				'success' => false,
				'message' => $size_cap_error,
			);
		}

		$tmp = wp_tempnam( 'zoltiq-zip-upload.zip' );
		if ( ! $tmp ) {
			return array(
				'success' => false,
				'message' => __( 'Could not create a temporary file for the base64 payload.', 'zoltiq-agents' ),
			);
		}
		$wrote = file_put_contents( $tmp, $decoded['decoded'] );
		if ( false === $wrote ) {
			wp_delete_file( $tmp );
			return array(
				'success' => false,
				'message' => __( 'Could not write the decoded bytes to disk.', 'zoltiq-agents' ),
			);
		}

		$magic_error = self::validate_zip_magic( $tmp );
		if ( null !== $magic_error ) {
			wp_delete_file( $tmp );
			return array(
				'success' => false,
				'message' => $magic_error,
			);
		}

		return $this->move_to_backups( $tmp, $filename_hint, 'base64' );
	}

	private function handle_chunk( string $data, array $chunk, string $filename_hint ): array {
		$session_id = self::sanitize_session_id( (string) ( $chunk['session_id'] ?? '' ) );
		if ( '' === $session_id ) {
			return array(
				'success' => false,
				'message' => __( 'The "chunk.session_id" must match /^[A-Za-z0-9_-]{8,64}$/.', 'zoltiq-agents' ),
			);
		}
		if ( ! isset( $chunk['index'] ) || ! is_numeric( $chunk['index'] ) || (int) $chunk['index'] < 0 ) {
			return array(
				'success' => false,
				'message' => __( 'The "chunk.index" is required and must be a non-negative integer.', 'zoltiq-agents' ),
			);
		}
		$index    = (int) $chunk['index'];
		$is_final = ! empty( $chunk['is_final'] );

		$staging_dir = Backups_Storage::staging_path();
		if ( false === $staging_dir ) {
			return array(
				'success' => false,
				'message' => __( 'Could not create the chunk staging directory under wp-content/uploads/zoltiq-staging.', 'zoltiq-agents' ),
			);
		}

		list( $b64_path, $meta_path ) = self::chunk_paths( $staging_dir, $session_id );
		$meta                         = self::read_chunk_meta( $meta_path );
		$expected_index               = $meta['last_index'] + 1;

		if ( $index !== $expected_index ) {
			self::cleanup_chunk_session( $b64_path, $meta_path );
			return array(
				'success' => false,
				'message' => sprintf(
					__( 'Chunk received out of order (expected %1$d, got %2$d). Session discarded — start over from index 0.', 'zoltiq-agents' ),
					$expected_index,
					$index
				),
			);
		}

		$raw = trim( $data );
		if ( '' === $raw ) {
			self::cleanup_chunk_session( $b64_path, $meta_path );
			return array(
				'success' => false,
				'message' => __( 'The "data" field is empty for this chunk.', 'zoltiq-agents' ),
			);
		}
		if ( 0 === stripos( $raw, 'data:' ) ) {
			$comma = strpos( $raw, ',' );
			if ( false === $comma ) {
				self::cleanup_chunk_session( $b64_path, $meta_path );
				return array(
					'success' => false,
					'message' => __( 'Data URL is missing the "," separator.', 'zoltiq-agents' ),
				);
			}
			$header = substr( $raw, 5, $comma - 5 );
			if ( false === stripos( $header, ';base64' ) ) {
				self::cleanup_chunk_session( $b64_path, $meta_path );
				return array(
					'success' => false,
					'message' => __( 'Only base64-encoded data URLs are supported (";base64" required in header).', 'zoltiq-agents' ),
				);
			}
			$raw = substr( $raw, $comma + 1 );
		}
		$raw = preg_replace( '/\s+/', '', $raw ) ?? '';
		if ( '' === $raw ) {
			self::cleanup_chunk_session( $b64_path, $meta_path );
			return array(
				'success' => false,
				'message' => __( 'Chunk contained no base64 payload after stripping whitespace.', 'zoltiq-agents' ),
			);
		}

		$chunk_max = (int) apply_filters( 'zoltiq_agents_zip_upload_chunk_max_bytes', 8 * 1024 * 1024 );
		if ( strlen( $raw ) > $chunk_max ) {
			self::cleanup_chunk_session( $b64_path, $meta_path );
			return array(
				'success' => false,
				'message' => sprintf(
					__( 'Chunk exceeds the per-chunk base64 cap (%d bytes). Split into smaller chunks.', 'zoltiq-agents' ),
					$chunk_max
				),
			);
		}

		$session_max = (int) apply_filters( 'zoltiq_agents_zip_upload_session_max_bytes', 64 * 1024 * 1024 );
		if ( ( $meta['bytes'] + strlen( $raw ) ) > $session_max ) {
			self::cleanup_chunk_session( $b64_path, $meta_path );
			return array(
				'success' => false,
				'message' => sprintf(
					__( 'Session would exceed the total base64 cap (%d bytes). Session discarded.', 'zoltiq-agents' ),
					$session_max
				),
			);
		}

		$fp = fopen( $b64_path, 'ab' );
		if ( false === $fp ) {
			return array(
				'success' => false,
				'message' => __( 'Could not open the chunk staging file for writing.', 'zoltiq-agents' ),
			);
		}
		$locked = flock( $fp, LOCK_EX );
		$wrote  = fwrite( $fp, $raw );
		if ( $locked ) {
			flock( $fp, LOCK_UN );
		}
		fclose( $fp );
		if ( false === $wrote || $wrote !== strlen( $raw ) ) {
			self::cleanup_chunk_session( $b64_path, $meta_path );
			return array(
				'success' => false,
				'message' => __( 'Could not append chunk bytes to staging file.', 'zoltiq-agents' ),
			);
		}

		$now                = time();
		$meta['last_index'] = $index;
		$meta['bytes']      = $meta['bytes'] + strlen( $raw );
		$meta['updated_at'] = $now;
		if ( 0 === (int) $meta['created_at'] ) {
			$meta['created_at'] = $now;
		}
		self::write_chunk_meta( $meta_path, $meta );

		if ( ! $is_final ) {
			return array(
				'success'        => true,
				'finalized'      => false,
				'session_id'     => $session_id,
				'chunk_received' => $index,
				'bytes_staged'   => (int) $meta['bytes'],
				'message'        => sprintf(
					__( 'Chunk %1$d accepted (%2$d base64 bytes staged). Send the next chunk or set "is_final":true.', 'zoltiq-agents' ),
					$index,
					(int) $meta['bytes']
				),
			);
		}

		if ( isset( $chunk['total'] ) && is_numeric( $chunk['total'] ) ) {
			$expected_total = (int) $chunk['total'];
			if ( $expected_total !== ( $index + 1 ) ) {
				self::cleanup_chunk_session( $b64_path, $meta_path );
				return array(
					'success' => false,
					'message' => sprintf(
						__( 'Final chunk arrived with index=%2$d but "chunk.total"=%1$d — session discarded.', 'zoltiq-agents' ),
						$expected_total,
						$index
					),
				);
			}
		}

		$raw_all = @file_get_contents( $b64_path ); 
		self::cleanup_chunk_session( $b64_path, $meta_path );
		if ( false === $raw_all || '' === $raw_all ) {
			return array(
				'success' => false,
				'message' => __( 'Chunk staging file was empty or unreadable.', 'zoltiq-agents' ),
			);
		}
		$decoded = $this->decode_base64_payload( $raw_all );
		if ( isset( $decoded['error'] ) ) {
			return array(
				'success' => false,
				'message' => $decoded['error'],
			);
		}
		$size_cap_error = self::validate_size_cap( strlen( $decoded['decoded'] ) );
		if ( null !== $size_cap_error ) {
			return array(
				'success' => false,
				'message' => $size_cap_error,
			);
		}

		$tmp = wp_tempnam( 'zoltiq-zip-upload.zip' );
		if ( ! $tmp ) {
			return array(
				'success' => false,
				'message' => __( 'Could not create a temporary file for the assembled zip.', 'zoltiq-agents' ),
			);
		}
		if ( false === file_put_contents( $tmp, $decoded['decoded'] ) ) {
			wp_delete_file( $tmp );
			return array(
				'success' => false,
				'message' => __( 'Could not write the assembled zip to disk.', 'zoltiq-agents' ),
			);
		}
		$magic_error = self::validate_zip_magic( $tmp );
		if ( null !== $magic_error ) {
			wp_delete_file( $tmp );
			return array(
				'success' => false,
				'message' => $magic_error,
			);
		}

		return $this->move_to_backups( $tmp, $filename_hint, 'chunk' );
	}

	private function move_to_backups( string $tmp, string $filename_hint, string $source_label ): array {
		$backups = Backups_Storage::backups_path();
		if ( false === $backups ) {
			if ( file_exists( $tmp ) ) {
				wp_delete_file( $tmp );
			}
			return array(
				'success' => false,
				'message' => __( 'Could not create the backups directory.', 'zoltiq-agents' ),
			);
		}
		$filename = Backups_Storage::random_backup_filename( 'upload', $filename_hint );
		$dest     = trailingslashit( $backups ) . $filename;

		if ( ! @rename( $tmp, $dest ) ) { 
			if ( ! @copy( $tmp, $dest ) ) { 
				if ( file_exists( $tmp ) ) {
					wp_delete_file( $tmp );
				}
				return array(
					'success' => false,
					'message' => __( 'Could not move the finalized zip into zoltiq-backups/.', 'zoltiq-agents' ),
				);
			}
			if ( file_exists( $tmp ) ) {
				wp_delete_file( $tmp );
			}
		}

		return array(
			'success'   => true,
			'finalized' => true,
			'file_path' => Backups_Storage::to_abspath_relative( $dest ),
			'file_url'  => Backups_Storage::url_for( $dest ),
			'size'      => (int) filesize( $dest ),
			'sha256'    => Backups_Storage::sha256_of( $dest ),
			'message'   => sprintf(
				__( 'Zip upload finalized (source: %s).', 'zoltiq-agents' ),
				$source_label
			),
		);
	}

	private function decode_base64_payload( string $raw ): array {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return array( 'error' => __( 'The "data" field is empty.', 'zoltiq-agents' ) );
		}
		if ( 0 === stripos( $raw, 'data:' ) ) {
			$comma = strpos( $raw, ',' );
			if ( false === $comma ) {
				return array( 'error' => __( 'Data URL is missing the "," separator.', 'zoltiq-agents' ) );
			}
			$header = substr( $raw, 5, $comma - 5 );
			if ( false === stripos( $header, ';base64' ) ) {
				return array( 'error' => __( 'Only base64-encoded data URLs are supported.', 'zoltiq-agents' ) );
			}
			$raw = substr( $raw, $comma + 1 );
		}
		$raw = preg_replace( '/\s+/', '', $raw ) ?? '';
		$decoded = base64_decode( $raw, true );
		if ( false === $decoded || '' === $decoded ) {
			return array( 'error' => __( 'The "data" field is not valid base64.', 'zoltiq-agents' ) );
		}
		return array( 'decoded' => $decoded );
	}

	private static function sanitize_session_id( string $sid ): string {
		return preg_match( '/^[A-Za-z0-9_-]{8,64}$/', $sid ) ? $sid : '';
	}

	private static function chunk_paths( string $dir, string $session_id ): array {
		return array(
			trailingslashit( $dir ) . 'zip-' . $session_id . '.b64',
			trailingslashit( $dir ) . 'zip-' . $session_id . '.meta.json',
		);
	}

	private static function read_chunk_meta( string $meta_path ): array {
		$defaults = array(
			'last_index' => -1,
			'bytes'      => 0,
			'created_at' => 0,
			'updated_at' => 0,
		);
		if ( ! is_file( $meta_path ) ) {
			return $defaults;
		}
		$raw = @file_get_contents( $meta_path );
		if ( false === $raw || '' === $raw ) {
			return $defaults;
		}
		$parsed = json_decode( $raw, true );
		if ( ! is_array( $parsed ) ) {
			return $defaults;
		}
		return array(
			'last_index' => isset( $parsed['last_index'] ) ? (int) $parsed['last_index'] : -1,
			'bytes'      => isset( $parsed['bytes'] ) ? (int) $parsed['bytes'] : 0,
			'created_at' => isset( $parsed['created_at'] ) ? (int) $parsed['created_at'] : 0,
			'updated_at' => isset( $parsed['updated_at'] ) ? (int) $parsed['updated_at'] : 0,
		);
	}

	private static function write_chunk_meta( string $meta_path, array $meta ): bool {
		$json = wp_json_encode( $meta );
		if ( false === $json ) {
			return false;
		}
		return false !== file_put_contents( $meta_path, $json, LOCK_EX );
	}

	private static function cleanup_chunk_session( string $b64_path, string $meta_path ): void {
		if ( file_exists( $b64_path ) ) {
			wp_delete_file( $b64_path );
		}
		if ( file_exists( $meta_path ) ) {
			wp_delete_file( $meta_path );
		}
	}

	private static function validate_size_cap( int $bytes ): ?string {
		$max = (int) apply_filters( 'zoltiq_agents_zip_max_bytes', 512 * 1024 * 1024 );
		if ( $bytes > $max ) {
			return sprintf(
				__( 'Zip size (%1$d bytes) exceeds the configured cap (%2$d bytes).', 'zoltiq-agents' ),
				$bytes,
				$max
			);
		}
		return null;
	}

	private static function validate_zip_magic( string $path ): ?string {
		if ( ! is_file( $path ) ) {
			return __( 'Uploaded file could not be located after staging.', 'zoltiq-agents' );
		}
		$fp = fopen( $path, 'rb' ); 
		if ( false === $fp ) {
			return __( 'Could not read the uploaded file for zip validation.', 'zoltiq-agents' );
		}
		$head = fread( $fp, 4 );
		fclose( $fp );
		if ( false === $head || strlen( $head ) < 4 ) {
			return __( 'Uploaded file is too small to be a zip archive.', 'zoltiq-agents' );
		}
	
		$magic = substr( $head, 0, 4 );
		if ( "PK\x03\x04" === $magic || "PK\x05\x06" === $magic || "PK\x07\x08" === $magic ) {
			return null;
		}
		return __( 'Uploaded file is not a valid zip archive (missing PK signature).', 'zoltiq-agents' );
	}

	public static function sweep_chunk_sessions(): void {
		$staging = Backups_Storage::staging_path();
		if ( false === $staging ) {
			return;
		}

		$ttl    = (int) apply_filters( 'zoltiq_agents_zip_upload_session_ttl', DAY_IN_SECONDS );
		$cutoff = time() - max( $ttl, MINUTE_IN_SECONDS );

		$metas = glob( trailingslashit( $staging ) . 'zip-*.meta.json' );
		if ( ! is_array( $metas ) ) {
			return;
		}
		foreach ( $metas as $meta_path ) {
			$raw = @file_get_contents( $meta_path );
			if ( false === $raw ) {
				continue;
			}
			$parsed  = json_decode( $raw, true );
			$updated = is_array( $parsed ) && isset( $parsed['updated_at'] ) ? (int) $parsed['updated_at'] : 0;
			if ( $updated > $cutoff ) {
				continue;
			}
			$b64_path = preg_replace( '/\.meta\.json$/', '.b64', $meta_path );
			if ( is_string( $b64_path ) && file_exists( $b64_path ) ) {
				wp_delete_file( $b64_path );
			}
			wp_delete_file( $meta_path );
		}
	}

	public static function register_sweep_cron(): void {
		if ( ! wp_next_scheduled( self::CHUNK_SWEEP_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CHUNK_SWEEP_HOOK );
		}
	}

	public static function unregister_sweep_cron(): void {
		wp_clear_scheduled_hook( self::CHUNK_SWEEP_HOOK );
	}
}
