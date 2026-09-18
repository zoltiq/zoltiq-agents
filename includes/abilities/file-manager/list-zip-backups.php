<?php

namespace Zoltiq\Agents\Includes\Abilities\FileManager;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Backups_Storage;

defined( 'ABSPATH' ) || exit;

class List_Zip_Backups extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/list-zip-backups',
			'args' => array(
				'label'               => __( 'List Zip Backups', 'zoltiq-agents' ),
				'description'         => __( 'List zips inside zoltiq-backups/ (default) or zoltiq-staging/, newest first, with size, sha256, created_at.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-file-manager',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'dir'    => array(
							'type'        => 'string',
							'enum'        => array( Backups_Storage::BACKUPS_DIR, Backups_Storage::STAGING_DIR ),
							'default'     => Backups_Storage::BACKUPS_DIR,
							'description' => __( 'Which managed directory to list: "zoltiq-backups" (default) or "zoltiq-staging".', 'zoltiq-agents' ),
						),
						'limit'  => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => 200,
							'default'     => 50,
							'description' => __( 'Max entries to return (1..200).', 'zoltiq-agents' ),
						),
						'offset' => array(
							'type'    => 'integer',
							'minimum' => 0,
							'default' => 0,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success' => array( 'type' => 'boolean' ),
						'items'   => array(
							'type'  => 'array',
							'items' => array(
								'type'                 => 'object',
								'properties'           => array(
									'file_path'  => array( 'type' => 'string' ),
									'file_url'   => array( 'type' => 'string' ),
									'size'       => array( 'type' => 'integer' ),
									'sha256'     => array( 'type' => 'string' ),
									'created_at' => array( 'type' => 'string' ),
								),
								'additionalProperties' => false,
							),
						),
						'total'   => array( 'type' => 'integer' ),
						'message' => array( 'type' => 'string' ),
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
		$dir    = sanitize_key( (string) ( $input['dir'] ?? Backups_Storage::BACKUPS_DIR ) );
		$limit  = isset( $input['limit'] ) ? (int) $input['limit'] : 50;
		$offset = isset( $input['offset'] ) ? (int) $input['offset'] : 0;

		if ( Backups_Storage::BACKUPS_DIR !== $dir && Backups_Storage::STAGING_DIR !== $dir ) {
			return array(
				'success' => false,
				'message' => __( '"dir" must be "zoltiq-backups" or "zoltiq-staging".', 'zoltiq-agents' ),
			);
		}

		$result = Backups_Storage::list_entries( $dir, $limit, $offset );
		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		return array(
			'success' => true,
			'items'   => $result['items'],
			'total'   => (int) $result['total'],
		);
	}
}
