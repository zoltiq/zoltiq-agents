<?php

namespace Zoltiq\Agents\Includes\Abilities\FileManager;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Backups_Storage;

defined( 'ABSPATH' ) || exit;

class Download_Zip_Backup extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/download-zip-backup',
			'args' => array(
				'label'               => __( 'Download Zip Backup', 'zoltiq-agents' ),
				'description'         => __( 'Look up a zip already stored under zoltiq-backups/ or zoltiq-staging/ and return its download URL plus metadata (size, sha256, created_at).', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-file-manager',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'file_path' => array(
							'type'        => 'string',
							'description' => __( 'ABSPATH-relative path (or bare filename) that resolves inside zoltiq-backups/ or zoltiq-staging/.', 'zoltiq-agents' ),
						),
					),
					'required'             => array( 'file_path' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'    => array( 'type' => 'boolean' ),
						'file_path'  => array( 'type' => 'string' ),
						'file_url'   => array( 'type' => 'string' ),
						'size'       => array( 'type' => 'integer' ),
						'sha256'     => array( 'type' => 'string' ),
						'created_at' => array( 'type' => 'string' ),
						'message'    => array( 'type' => 'string' ),
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
		$file_path = sanitize_text_field( (string) ( $input['file_path'] ?? '' ) );

		$resolved = Backups_Storage::resolve_managed_path( $file_path );
		if ( is_wp_error( $resolved ) ) {
			return array(
				'success' => false,
				'message' => $resolved->get_error_message(),
			);
		}

		if ( ! is_file( $resolved ) ) {
			return array(
				'success' => false,
				'message' => __( 'File does not exist.', 'zoltiq-agents' ),
			);
		}

		return array(
			'success'    => true,
			'file_path'  => Backups_Storage::to_abspath_relative( $resolved ),
			'file_url'   => Backups_Storage::url_for( $resolved ),
			'size'       => (int) filesize( $resolved ),
			'sha256'     => Backups_Storage::sha256_of( $resolved ),
			'created_at' => gmdate( 'c', (int) filemtime( $resolved ) ),
		);
	}
}
