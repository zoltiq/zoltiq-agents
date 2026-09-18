<?php

namespace Zoltiq\Agents\Includes\Abilities\Core;

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
			'zoltiq-agents-core',
			array(
				'label'       => __( 'Zoltiq Agents - Core', 'zoltiq-agents' ),
				'description' => __( 'Abilities for managing the WordPress core itself: check for available core updates and apply them via Core_Upgrader.', 'zoltiq-agents' ),
			)
		);
	}
}
