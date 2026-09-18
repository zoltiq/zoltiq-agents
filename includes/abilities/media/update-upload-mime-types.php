<?php

namespace Zoltiq\Agents\Includes\Abilities\Media;

use Zoltiq\Agents\Includes\Abilities\Ability_Definition;
use Zoltiq\Agents\Includes\Abilities\Utilities\Mime_Types_Store;

defined( 'ABSPATH' ) || exit;

class Update_Upload_Mime_Types extends Ability_Definition {

	protected function ability(): array {
		return array(
			'name' => 'zoltiq/update-upload-mime-types',
			'args' => array(
				'label'               => __( 'Add or Remove Allowed Upload MIME Types', 'zoltiq-agents' ),
				'description'         => __( 'Manage the "extra allowed MIME types" the upload-media ability will accept. Pass "add" ({ext: mime} map) to add or overwrite entries. Pass "remove" (array of extensions) to drop entries this plugin previously added. Both are optional but at least one must be supplied. This ability cannot remove WordPress core defaults or entries added by other plugins/filters — only entries this plugin manages. The extras only apply during upload-media calls; regular Media Library uploads via wp-admin are unaffected.', 'zoltiq-agents' ),
				'category'            => 'zoltiq-agents-media',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'add'    => array(
							'type'                 => 'object',
							'additionalProperties' => array( 'type' => 'string' ),
							'description'          => __( 'Extension => MIME map to add or overwrite. Extensions are lowercased and validated against /^[a-z0-9]+$/. MIME strings must match "type/subtype".', 'zoltiq-agents' ),
						),
						'extras' => array(
							'type'                 => 'object',
							'additionalProperties' => array( 'type' => 'string' ),
							'description'          => __( 'Alias for "add". If both are provided, "add" wins.', 'zoltiq-agents' ),
						),
						'remove' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => __( 'Array of extensions to drop from the extras store (e.g. ["svg", "webp"]). Only removes entries this plugin manages — core defaults and other plugins\' filters are untouched.', 'zoltiq-agents' ),
						),
					),
					'anyOf'                => array(
						array( 'required' => array( 'add' ) ),
						array( 'required' => array( 'extras' ) ),
						array( 'required' => array( 'remove' ) ),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'   => array( 'type' => 'boolean' ),
						'allowed'   => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'ext'    => array( 'type' => 'string' ),
									'mime'   => array( 'type' => 'string' ),
									'source' => array(
										'type' => 'string',
										'enum' => array( 'core', 'this-plugin', 'other-filter' ),
									),
								),
							),
						),
						'added'     => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'ext'  => array( 'type' => 'string' ),
									'mime' => array( 'type' => 'string' ),
								),
							),
						),
						'removed'   => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'not_found' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => __( 'Entries requested for removal that were not in this plugin\'s store (either never added, or added by core/another plugin).', 'zoltiq-agents' ),
						),
						'skipped'   => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'ext'    => array( 'type' => 'string' ),
									'mime'   => array( 'type' => 'string' ),
									'reason' => array( 'type' => 'string' ),
								),
							),
						),
						'message'   => array( 'type' => 'string' ),
					),
					'required'             => array( 'success', 'allowed', 'added', 'removed', 'not_found', 'skipped' ),
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
						'idempotent'  => true,
					),
				),
			),
		);
	}

	public function execute( array $input = array() ): array {
		$adds        = ! empty( $input['add'] ) ? $input['add'] : ( $input['extras'] ?? array() );
		$removes_in  = $input['remove'] ?? array();
		$has_adds    = is_array( $adds ) && ! empty( $adds );
		$has_removes = is_array( $removes_in ) && ! empty( $removes_in );

		if ( ! $has_adds && ! $has_removes ) {
			return array(
				'success'   => false,
				'allowed'   => array(),
				'added'     => array(),
				'removed'   => array(),
				'not_found' => array(),
				'skipped'   => array(),
				'message'   => __( 'Pass at least one of "add" (ext => mime), "extras" (alias for add), or "remove" (array of extensions).', 'zoltiq-agents' ),
			);
		}

		$added     = array();
		$skipped   = array();
		$removed   = array();
		$not_found = array();

		if ( $has_adds ) {
			$merge_result = Mime_Types_Store::merge( $adds );
			$added        = $merge_result['added'];
			$skipped      = $merge_result['skipped'];
		}

		if ( $has_removes ) {
			$remove_result = Mime_Types_Store::remove( array_map( 'strval', $removes_in ) );
			$removed       = $remove_result['removed'];
			$not_found     = $remove_result['not_found'];
		}

		$core   = wp_get_mime_types();
		$extras = Mime_Types_Store::get();
		$rows = Mime_Types_Store::filter_upload_mimes( get_allowed_mime_types() );
		ksort( $rows );

		$allowed = array();
		foreach ( $rows as $ext_key => $mime ) {
			$allowed[] = array(
				'ext'    => (string) $ext_key,
				'mime'   => (string) $mime,
				'source' => Mime_Types_Store::attribute( (string) $ext_key, (string) $mime, $core, $extras ),
			);
		}

		return array(
			'success'   => true,
			'allowed'   => $allowed,
			'added'     => $added,
			'removed'   => $removed,
			'not_found' => $not_found,
			'skipped'   => $skipped,
			'message'   => sprintf(
				__( 'Added %1$d; removed %2$d; skipped %3$d; not-found %4$d.', 'zoltiq-agents' ),
				count( $added ),
				count( $removed ),
				count( $skipped ),
				count( $not_found )
			),
		);
	}
}
