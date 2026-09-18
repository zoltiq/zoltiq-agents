<?php

namespace Zoltiq\Agents\Includes\Abilities\FileManager;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Backups_Storage;
use Zoltiq\Agents\Includes\Abilities\Utilities\File_Mods_Guard;

defined( 'ABSPATH' ) || exit;

class Delete_Zip_Backup extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/delete-zip-backup',
			'args' => array(
				'label'               => __( 'Delete Zip Backup', 'zoltiq-agents' ),
				'description'         => __( 'Delete a zip stored under zoltiq-backups/ or zoltiq-staging/. Path outside those two directories is rejected; deleting a missing file returns success with a note.', 'zoltiq-agents' ),
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
							'description' => __( 'ABSPATH-relative path or bare filename inside zoltiq-backups/ or zoltiq-staging/.', 'zoltiq-agents' ),
						),
					),
					'required'             => array( 'file_path' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'      => array( 'type' => 'boolean' ),
						'deleted_path' => array( 'type' => 'string' ),
						'existed'      => array( 'type' => 'boolean' ),
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
						'idempotent'  => true,
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

		$file_path = sanitize_text_field( (string) ( $input['file_path'] ?? '' ) );

		$resolved = Backups_Storage::resolve_managed_path( $file_path );
		if ( is_wp_error( $resolved ) ) {
			return array(
				'success' => false,
				'message' => $resolved->get_error_message(),
			);
		}

		$rel      = Backups_Storage::to_abspath_relative( $resolved );
		$existed  = is_file( $resolved );

		if ( ! $existed ) {
			return array(
				'success'      => true,
				'deleted_path' => $rel,
				'existed'      => false,
				'message'      => __( 'File was already absent — nothing to delete.', 'zoltiq-agents' ),
			);
		}

		wp_delete_file( $resolved );

		if ( is_file( $resolved ) ) {
			return array(
				'success'      => false,
				'deleted_path' => $rel,
				'existed'      => true,
				'message'      => __( 'File could not be deleted.', 'zoltiq-agents' ),
			);
		}

		return array(
			'success'      => true,
			'deleted_path' => $rel,
			'existed'      => true,
			'message'      => __( 'File deleted.', 'zoltiq-agents' ),
		);
	}
}
