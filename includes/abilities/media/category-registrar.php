<?php

namespace Zoltiq\Agents\Includes\Abilities\Media;

defined( 'ABSPATH' ) || exit;

final class Category_Registrar {

	protected static $instance = null;

	private function __construct() {}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register(): void {
		wp_register_ability_category(
			'zoltiq-agents-media',
			array(
				'label'       => __( 'Zoltiq Agents - Media', 'zoltiq-agents' ),
				'description' => __( 'Abilities for managing the Media Library: upload, list, read, update, delete, and meta access.', 'zoltiq-agents' ),
			)
		);
	}
}
