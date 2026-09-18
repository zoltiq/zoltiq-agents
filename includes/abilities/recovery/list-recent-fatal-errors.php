<?php

namespace Zoltiq\Agents\Includes\Abilities\Recovery;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class List_Recent_Fatal_Errors extends Ability_Definition {

	private const ERROR_LINE_REGEX = '/^\[(?<ts>[^\]]+)\]\s+PHP\s+(?<type>Fatal|Parse|Compile)\s+error:\s+(?<message>.*?)(?:\s+in\s+(?<file>[^\s]+?)(?::(?<line_after_colon>\d+)|(?:\s+on\s+line\s+(?<line_after_on>\d+)))?)?$/i';

	private const MAX_SCAN_BYTES = 20 * 1024 * 1024;

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/list-recent-fatal-errors',
			'args' => array(
				'label'               => __( 'List Recent Fatal Errors', 'zoltiq-agents' ),
				'description'         => __( 'Extracts PHP Fatal / Parse / Compile error entries from debug.log within the last N days, groups them by unique signature (type + file + line + message), and returns the top-M groups sorted by most-recent occurrence. Streams the log from disk with a 20 MB tail cap to guard against runaway logs.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-recovery',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'since_days' => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => 90,
							'default'     => 7,
							'description' => __( 'Only include errors logged within the last N days.', 'zoltiq-agents' ),
						),
						'limit'      => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => 200,
							'default'     => 20,
							'description' => __( 'Maximum number of grouped errors to return.', 'zoltiq-agents' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'errors'  => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'first_seen' => array( 'type' => 'string' ),
									'last_seen'  => array( 'type' => 'string' ),
									'count'      => array( 'type' => 'integer' ),
									'type'       => array( 'type' => 'string' ),
									'message'    => array( 'type' => 'string' ),
									'file'       => array( 'type' => 'string' ),
									'line'       => array( 'type' => 'integer' ),
								),
							),
						),
						'scanned_bytes' => array( 'type' => 'integer' ),
						'truncated'     => array( 'type' => 'boolean' ),
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
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		$log_path = WP_CONTENT_DIR . '/debug.log';

		if ( ! is_file( $log_path ) ) {
			$logging_on = ( defined( 'WP_DEBUG_LOG' ) && \WP_DEBUG_LOG ) && ( defined( 'WP_DEBUG' ) && \WP_DEBUG );
			$reason     = $logging_on
				? __( 'No entries have been written yet.', 'zoltiq-agents' )
				: __( 'WP_DEBUG and/or WP_DEBUG_LOG are not enabled in wp-config.php, so nothing is being written.', 'zoltiq-agents' );
			return array(
				'success' => true,
				'errors'  => array(),
				'message' => sprintf( __( 'debug.log does not exist. %s', 'zoltiq-agents' ), $reason ),
			);
		}

		$since_days = isset( $input['since_days'] ) ? max( 1, min( 90, (int) $input['since_days'] ) ) : 7;
		$limit      = isset( $input['limit'] ) ? max( 1, min( 200, (int) $input['limit'] ) ) : 20;
		$cutoff_ts  = time() - ( $since_days * DAY_IN_SECONDS );

		$file_size = filesize( $log_path );
		if ( false === $file_size ) {
			return array(
				'success' => false,
				'errors'  => array(),
				'message' => __( 'Could not stat debug.log.', 'zoltiq-agents' ),
			);
		}

		$truncated  = $file_size > self::MAX_SCAN_BYTES;
		$seek_start = $truncated ? ( $file_size - self::MAX_SCAN_BYTES ) : 0;

		$fh = fopen( $log_path, 'r' ); 
		if ( false === $fh ) {
			return array(
				'success' => false,
				'errors'  => array(),
				'message' => __( 'Could not open debug.log.', 'zoltiq-agents' ),
			);
		}

		if ( $seek_start > 0 ) {
			fseek( $fh, $seek_start );
			fgets( $fh ); 
		}

		$groups = array();

		while ( ! feof( $fh ) ) {
			$line = fgets( $fh );
			if ( false === $line ) {
				break;
			}

			if ( ! preg_match( self::ERROR_LINE_REGEX, rtrim( $line ), $m ) ) {
				continue;
			}

			$ts = strtotime( $m['ts'] );
			if ( false === $ts || $ts < $cutoff_ts ) {
				continue;
			}

			$type    = ucfirst( strtolower( $m['type'] ) ) . ' Error';
			$file    = isset( $m['file'] ) ? $m['file'] : '';
			$line_no = 0;
			if ( ! empty( $m['line_after_colon'] ) ) {
				$line_no = (int) $m['line_after_colon'];
			} elseif ( ! empty( $m['line_after_on'] ) ) {
				$line_no = (int) $m['line_after_on'];
			}
			$message = trim( $m['message'] );

			$sig = md5( $type . '|' . $file . '|' . $line_no . '|' . $message );
			$iso = gmdate( 'c', $ts );

			if ( ! isset( $groups[ $sig ] ) ) {
				$groups[ $sig ] = array(
					'first_seen' => $iso,
					'last_seen'  => $iso,
					'count'      => 0,
					'type'       => $type,
					'message'    => $message,
					'file'       => $file,
					'line'       => $line_no,
				);
			}
			$groups[ $sig ]['last_seen'] = $iso;
			++$groups[ $sig ]['count'];
		}

		fclose( $fh );

		usort( $groups, static function ( array $a, array $b ): int {
			return strcmp( $b['last_seen'], $a['last_seen'] );
		} );

		$groups = array_slice( $groups, 0, $limit );

		return array(
			'success'       => true,
			'errors'        => array_values( $groups ),
			'scanned_bytes' => (int) ( $file_size - $seek_start ),
			'truncated'     => $truncated,
			'message'       => $truncated
				? sprintf(
					__( 'debug.log exceeds %d bytes; scanned only the tail.', 'zoltiq-agents' ),
					self::MAX_SCAN_BYTES
				)
				: '',
		);
	}
}
