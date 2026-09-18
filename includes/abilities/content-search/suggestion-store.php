<?php

namespace Zoltiq\Agents\Includes\Abilities\ContentSearch;

defined( 'ABSPATH' ) || exit;

final class Suggestion_Store {

	public const OPTION = 'zoltiq_agents_link_suggestions';

	public const MAX_SUGGESTIONS = 500;

	public static function read(): array {
		$raw = get_option( self::OPTION, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		return array(
			'next_id' => isset( $raw['next_id'] ) ? max( 1, (int) $raw['next_id'] ) : 1,
			'items'   => isset( $raw['items'] ) && is_array( $raw['items'] ) ? $raw['items'] : array(),
		);
	}

	public static function write( array $store ): void {
		update_option( self::OPTION, $store, false );
	}

	public static function insert( array $data ): int {
		$store = self::read();
		if ( count( $store['items'] ) >= self::MAX_SUGGESTIONS ) {
			return 0;
		}
		$id = $store['next_id'];
		$store['items'][ $id ] = array(
			'id'          => $id,
			'post_id'     => (int) ( $data['post_id'] ?? 0 ),
			'target_url'  => esc_url_raw( (string) ( $data['target_url'] ?? '' ) ),
			'anchor_text' => sanitize_text_field( (string) ( $data['anchor_text'] ?? '' ) ),
			'status'      => 'pending',
			'notes'       => sanitize_text_field( (string) ( $data['notes'] ?? '' ) ),
			'created_at'  => time(),
		);
		$store['next_id'] = $id + 1;
		self::write( $store );
		return $id;
	}

	public static function update_status( int $id, string $status, string $notes = '' ): bool {
		$store = self::read();
		if ( ! isset( $store['items'][ $id ] ) ) {
			return false;
		}
		$store['items'][ $id ]['status'] = $status;
		if ( '' !== $notes ) {
			$store['items'][ $id ]['notes'] = sanitize_text_field( $notes );
		}
		self::write( $store );
		return true;
	}

	public static function get( int $id ): ?array {
		$store = self::read();
		return isset( $store['items'][ $id ] ) ? $store['items'][ $id ] : null;
	}

	public static function list( ?int $post_id = null, ?string $status = null ): array {
		$store  = self::read();
		$result = array();
		foreach ( $store['items'] as $item ) {
			if ( null !== $post_id && (int) $item['post_id'] !== $post_id ) {
				continue;
			}
			if ( null !== $status && (string) $item['status'] !== $status ) {
				continue;
			}
			$result[] = $item;
		}
		return $result;
	}
}
