<?php

namespace Zoltiq\Agents\Includes\Abilities\FileManager;

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
			'zoltiq-agents-file-manager',
			array(
				'label'       => __( 'Zoltiq Agents - File Manager', 'zoltiq-agents' ),
				'description' => __( 'Abilities for reading, creating, editing, and deleting files across WordPress plugins, themes, configuration, and logs.', 'zoltiq-agents' ),
			)
		);
	}
}
