<?php

namespace Zoltiq\Agents\Includes\Abilities\Themes;

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
			'zoltiq-agents-themes',
			array(
				'label'       => __( 'Zoltiq Agents - Theme Management', 'zoltiq-agents' ),
				'description' => __( 'Abilities for managing WordPress themes: install, activate, list, and delete.', 'zoltiq-agents' ),
			)
		);
	}
}
