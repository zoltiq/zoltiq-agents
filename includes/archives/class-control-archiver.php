<?php
/**
 * Zoltiq Chat Archiver
 *
 * Provides the following functionality:
 * - Searches for options matching 'chatbot_history_<userkey>'
 * - Archives them into ZIP files (uploads/chat-archives/chat-history-YYYY-MM-DD.zip)
 * - Optionally deletes options after archiving
 * - Removes old archives based on AGENTS_ARCHIVER_RETENTION_DAYS
 *
 */

namespace Zoltiq\Agents\Archives;

use ZipArchive;

if ( ! defined( 'AGENTS_ARCHIVER_DELETE_AFTER_ARCHIVE' ) ) {
	define( 'AGENTS_ARCHIVER_DELETE_AFTER_ARCHIVE', true );
}
if ( ! defined( 'AGENTS_ARCHIVER_RETENTION_DAYS' ) ) {
	define( 'AGENTS_ARCHIVER_RETENTION_DAYS', 0 );
}

/**
 * Class Control_Archiver
 *
 * Handles periodic archiving of chatbot history stored in WordPress options.
 */
class Control_Archiver {
	
	/**
	 * Singleton instance.
	 *
	 * @var Control_Archiver|null
	 */
	private static $instance = null;

	/**
	 * Cron hook name.
	 *
	 * @var string
	 */
	private const HOOK = 'zoltiq_agents_archiver_hourly_event';

	/**
	 * The history states option name.
	 */
	private const OPTION_NAME_HISTORY = 'zoltiq_agents_history_';

	/**
	 * Dir name for uploads chat archiver
	 */
	private const UPLOAD_ARCHIVES_DIR = 'zoltiq-agents-archives';
   
	/**
	 * Returns singleton instance.
	 *
	 * @return Control_Archiver
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * Registers cron hook listener.
	 */
	public function __construct() {
		add_action( self::HOOK, array( $this, 'archive_histories' ) );
	}

	/**
	 * Registers cron job on plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time(), 'hourly', self::HOOK );
		}
	}

	/**
	 * Clears cron job on plugin deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Main archiving process triggered by WP-Cron.
	 *
	 * @return void
	 */
	public function archive_histories() {

		global $wpdb;

		if ( ! class_exists( 'ZipArchive' ) ) {
			error_log( '[agents-archiver] The ZipArchive class is not available. Archive creation failed.' );
			return;
		}

		$dir = $this->ensure_upload_dir();
		if ( ! $dir ) {
			error_log( '[agents-archiver] Unable to create or locate the archive directory: ' . print_r( wp_upload_dir(), true ) );
			return;
		}

		$date = current_time( 'Y-m-d' );
		$zip_path = $dir . "chat-history-{$date}.zip";

		$rows = $this->get_history_rows( $wpdb );

		if ( empty( $rows ) ) {
			return;
		}

		$zip = new ZipArchive();
		if ( $zip->open( $zip_path, ZipArchive::CREATE ) !== true ) {   
			error_log( "[agents-archiver] Unable to create ZIP archive at: {$zip_path}" );
			return;
		}

		foreach ( $rows as $row ) {
			$this->add_row_to_zip( $zip, $row );
		}

		$zip->close();

		@chmod( $zip_path, 0644 );

		$this->delete_empty_zip( $zip_path );
		$this->cleanup_old_archives( $dir );
	}

	/**
	 * Ensures that uploads/chat-archives directory exists.
	 *
	 * @return string|false Directory path or false on failure
	 */
	protected function ensure_upload_dir() {
		$upload = wp_upload_dir();
		$basedir = $upload['basedir'] ?? '';

		if ( empty( $basedir ) ) {
			return false;
		}

		$dir = trailingslashit( $basedir ) . self::UPLOAD_ARCHIVES_DIR . '/';

		if ( is_dir( $dir ) || wp_mkdir_p( $dir ) ) {
			return trailingslashit( $dir );
		}

		return false;
	}

	/**
	 * Retrieves chatbot history options from the database.
	 *
	 * @param \wpdb $wpdb
	 * @return array
	 */
	protected function get_history_rows( $wpdb ) {
		$like = $wpdb->esc_like( self::OPTION_NAME_HISTORY ) . '%';
		$sql  = "
			SELECT option_name, option_value
			FROM {$wpdb->options}
			WHERE option_name LIKE %s
		";
		return $wpdb->get_results( $wpdb->prepare( $sql, $like ), ARRAY_A );
	}

	/**
	 * Adds a single history entry to the ZIP archive.
	 *
	 * @param ZipArchive $zip
	 * @param array $row
	 * @return void
	 */
	protected function add_row_to_zip( $zip, $row ) {
		$option_name  = $row['option_name'];
		$user_key_raw = str_replace( self::OPTION_NAME_HISTORY, '', $option_name );
		$user_key     = preg_replace( '/[^A-Za-z0-9_\-]/', '-', $user_key_raw );
		$filename     = "msg_user_{$user_key}.json";

		$history = maybe_unserialize( $row['option_value'] );
		if ( empty( $history ) ) {
			return;
		}

		if ( ! is_array( $history ) ) {
			$history = array( $history );
		}

		$existing_raw = $zip->getFromName( $filename );
		$existing     = array();

		if ( $existing_raw !== false ) {
			$decoded = json_decode( $existing_raw, true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
				$existing = $decoded;
			} else {
				$unser = maybe_unserialize( $existing_raw );
				$existing = ( is_array( $unser ) ) ? $unser : array( $existing_raw );
			}
		}

		$merged = array_merge( (array) $existing, (array) $history );

		$json = wp_json_encode(
			$merged,
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);

		$content = ( $json === false ) ? maybe_serialize( $merged ) : $json;
	
		$added = $zip->addFromString( $filename, $content, ZipArchive::FL_OVERWRITE );
		if ( ! $added ) {
			error_log( "[agents-archiver] Failed to add file to ZIP archive: {$filename}" );
			return;
		}

		if ( defined( 'AGENTS_ARCHIVER_DELETE_AFTER_ARCHIVE' ) && AGENTS_ARCHIVER_DELETE_AFTER_ARCHIVE ) {
			$deleted = delete_option( $option_name );
			if ( ! $deleted ) {
				error_log( "[agents-archiver] Failed to delete option: {$option_name}" );
			}
		}
	}

	/**
	 * Deletes ZIP file if empty.
	 *
	 * @param string $zip_path
	 * @return void
	 */
	protected function delete_empty_zip( $zip_path ) {
		if ( file_exists( $zip_path ) && filesize( $zip_path ) === 0 ) {
			@unlink( $zip_path );
		}
	}

	/**
	 * Removes old archives based on retention policy.
	 *
	 * @param string $dir
	 * @return void
	 */
	protected function cleanup_old_archives( $dir ) {

		if ( ! defined( 'AGENTS_ARCHIVER_RETENTION_DAYS' ) || AGENTS_ARCHIVER_RETENTION_DAYS <= 0 ) {
			return;
		}

		$files = glob( $dir . 'chat-history-*.zip' );
		$cutoff = time() - ( AGENTS_ARCHIVER_RETENTION_DAYS * DAY_IN_SECONDS );

		if ( ! is_array( $files ) || empty( $files ) ) {
			return;
		}
	
		foreach ( $files as $file ) {
			if ( filemtime( $file ) < $cutoff ) {
				@unlink( $file );
			}
		}
	}
}