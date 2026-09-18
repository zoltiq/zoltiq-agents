<?php

namespace Zoltiq\Agents\Includes\Abilities\Comments;

defined( 'ABSPATH' ) || exit;

final class Moderation {

	public static function set_status( int $id, string $status ): array {
		if ( $id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'A valid id is required.', 'zoltiq-agents' ),
			);
		}

		$comment = get_comment( $id );
		if ( null === $comment ) {
			return array(
				'success' => false,
				'message' => __( 'Comment not found.', 'zoltiq-agents' ),
			);
		}

		switch ( $status ) {
			case 'approved':
			case 'approve':
			case '1':
				$result = wp_set_comment_status( $id, 'approve', true );
				break;
			case 'hold':
			case 'unapproved':
			case '0':
				$result = wp_set_comment_status( $id, 'hold', true );
				break;
			case 'spam':
				$result = wp_spam_comment( $id );
				break;
			case 'trash':
				$result = wp_trash_comment( $id );
				break;
			default:
				return array(
					'success' => false,
					'message' => sprintf( __( 'Unsupported status "%s".', 'zoltiq-agents' ), $status ),
				);
		}

		if ( true !== $result ) {
			return Comment_Formatter::error_from(
				$result,
				sprintf( __( 'Could not set comment #%1$d to "%2$s".', 'zoltiq-agents' ), $id, $status )
			);
		}

		$updated = get_comment( $id );
		$payload = null !== $updated ? Comment_Formatter::to_array( $updated ) : array();

		return array(
			'success' => true,
			'comment' => $payload,
			'message' => sprintf( __( 'Comment #%1$d set to "%2$s".', 'zoltiq-agents' ), $id, $status ),
		);
	}
}
