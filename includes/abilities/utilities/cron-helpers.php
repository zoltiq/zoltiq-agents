<?php

namespace Zoltiq\Agents\Includes\Abilities\Utilities;

defined( 'ABSPATH' ) || exit;

final class Cron_Helpers {

	public const OPTION = 'zoltiq_custom_cron_schedules';

	public static function filter_schedules( $schedules ) {
		if ( ! is_array( $schedules ) ) {
			$schedules = array();
		}
		foreach ( self::get_custom() as $name => $def ) {
			if ( ! isset( $schedules[ $name ] ) ) {
				$schedules[ $name ] = $def;
			}
		}
		return $schedules;
	}

	public static function register_filter(): void {
		add_filter( 'cron_schedules', array( self::class, 'filter_schedules' ) );
	}

	public static function get_custom(): array {
		$stored = get_option( self::OPTION, array() );
		return is_array( $stored ) ? $stored : array();
	}

	public static function add_custom( string $name, int $interval, string $display ): bool {
		$custom          = self::get_custom();
		$custom[ $name ] = array(
			'interval' => $interval,
			'display'  => $display,
		);
		return (bool) update_option( self::OPTION, $custom );
	}

	public static function remove_custom( string $name ): bool {
		$custom = self::get_custom();
		if ( ! isset( $custom[ $name ] ) ) {
			return false;
		}
		unset( $custom[ $name ] );
		return (bool) update_option( self::OPTION, $custom );
	}

	public static function flatten_events(): array {
		if ( ! function_exists( '_get_cron_array' ) ) {
			require_once ABSPATH . WPINC . '/cron.php';
		}
		$cron = _get_cron_array();
		$out  = array();
		if ( ! is_array( $cron ) ) {
			return $out;
		}
		foreach ( $cron as $timestamp => $hooks ) {
			if ( ! is_array( $hooks ) ) {
				continue;
			}
			foreach ( $hooks as $hook => $events ) {
				if ( ! is_array( $events ) ) {
					continue;
				}
				foreach ( $events as $event_key => $event ) {
					$out[] = array(
						'timestamp' => (int) $timestamp,
						'datetime'  => gmdate( 'c', (int) $timestamp ),
						'hook'      => (string) $hook,
						'schedule'  => isset( $event['schedule'] ) ? (string) $event['schedule'] : '',
						'interval'  => isset( $event['interval'] ) ? (int) $event['interval'] : 0,
						'args'      => isset( $event['args'] ) ? (array) $event['args'] : array(),
						'key'       => (string) $event_key,
					);
				}
			}
		}
		return $out;
	}
}
