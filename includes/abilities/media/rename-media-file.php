<?php

namespace Zoltiq\Agents\Includes\Abilities\Media;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;

defined( 'ABSPATH' ) || exit;

class Rename_Media_File extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/rename-media-file',
			'args' => array(
				'label'               => __( 'Rename Media File', 'zoltiq-agents' ),
				'description'         => __( 'Rename an attachment\'s on-disk file and update the "_wp_attached_file" post-meta + attachment guid + regenerate intermediate size metadata. new_filename may not contain a directory separator, null byte, or a leading dot; the resolved new path must stay inside the original upload sub-directory; refuses if the target filename already exists (no clobber).', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-media',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' ) && current_user_can( 'upload_files' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'attachment_id' => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'new_filename'  => array(
							'type'      => 'string',
							'minLength' => 1,
						),
					),
					'required'             => array( 'attachment_id', 'new_filename' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'       => array( 'type' => 'boolean' ),
						'attachment_id' => array( 'type' => 'integer' ),
						'old_relative'  => array( 'type' => 'string' ),
						'new_relative'  => array( 'type' => 'string' ),
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
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		$attachment_id = absint( $input['attachment_id'] ?? 0 );
		$new_filename  = (string) ( $input['new_filename'] ?? '' );

		if ( $attachment_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'A valid attachment_id is required.', 'zoltiq-agents' ),
			);
		}

		$attachment = get_post( $attachment_id );
		if ( ! $attachment instanceof \WP_Post || 'attachment' !== $attachment->post_type ) {
			return array(
				'success' => false,
				'message' => __( 'Attachment not found.', 'zoltiq-agents' ),
			);
		}

		if ( ! current_user_can( 'edit_post', $attachment_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'You do not have permission to edit this attachment.', 'zoltiq-agents' ),
			);
		}

		if (
			'' === $new_filename
			|| str_contains( $new_filename, '/' )
			|| str_contains( $new_filename, '\\' )
			|| str_contains( $new_filename, "\0" )
			|| str_starts_with( $new_filename, '.' )
		) {
			return array(
				'success' => false,
				'message' => __( 'new_filename must not contain a directory separator, null byte, or a leading dot.', 'zoltiq-agents' ),
			);
		}

		$sanitized = sanitize_file_name( $new_filename );
		if ( '' === $sanitized ) {
			return array(
				'success' => false,
				'message' => __( 'new_filename resolves to an empty string after sanitisation.', 'zoltiq-agents' ),
			);
		}

		$old_relative = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( '' === $old_relative ) {
			return array(
				'success' => false,
				'message' => __( '_wp_attached_file meta is missing on this attachment.', 'zoltiq-agents' ),
			);
		}

		$uploads = wp_get_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return array(
				'success' => false,
				'message' => (string) $uploads['error'],
			);
		}
		$base_dir = trailingslashit( (string) $uploads['basedir'] );
		$old_abs  = $base_dir . $old_relative;
		if ( ! is_file( $old_abs ) ) {
			return array(
				'success' => false,
				'message' => __( 'Original file does not exist on disk.', 'zoltiq-agents' ),
			);
		}

		$dir_relative = ltrim( (string) ( dirname( $old_relative ) ?: '' ), '/.' );
		$new_relative = ( '' === $dir_relative ) ? $sanitized : $dir_relative . '/' . $sanitized;
		$new_abs      = $base_dir . $new_relative;

		$parent_real = realpath( dirname( $old_abs ) );
		if ( false === $parent_real ) {
			return array(
				'success' => false,
				'message' => __( 'Could not resolve the attachment\'s parent directory.', 'zoltiq-agents' ),
			);
		}
		$target_parent_real = realpath( dirname( $new_abs ) );
		if ( false === $target_parent_real || $target_parent_real !== $parent_real ) {
			return array(
				'success' => false,
				'message' => __( 'Target path would escape the attachment\'s original upload sub-directory.', 'zoltiq-agents' ),
			);
		}

		if ( file_exists( $new_abs ) ) {
			return array(
				'success' => false,
				'message' => __( 'A file already exists at the target filename; refusing to clobber.', 'zoltiq-agents' ),
			);
		}

		if ( ! @rename( $old_abs, $new_abs ) ) {
			return array(
				'success' => false,
				'message' => __( 'Rename failed on disk.', 'zoltiq-agents' ),
			);
		}

		update_post_meta( $attachment_id, '_wp_attached_file', $new_relative );
		wp_update_post(
			array(
				'ID'   => $attachment_id,
				'guid' => trailingslashit( (string) $uploads['baseurl'] ) . $new_relative,
			)
		);

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( $attachment_id, $new_abs );
		if ( is_array( $metadata ) ) {
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}

		return array(
			'success'       => true,
			'attachment_id' => $attachment_id,
			'old_relative'  => $old_relative,
			'new_relative'  => $new_relative,
			'message'       => sprintf( __( 'Renamed "%1$s" → "%2$s".', 'zoltiq-agents' ), $old_relative, $new_relative ),
		);
	}
}
