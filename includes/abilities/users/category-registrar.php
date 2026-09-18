<?php

namespace Zoltiq\Agents\Includes\Abilities\Users;

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
			'zoltiq-agents-users',
			array(
				'label'       => __( 'Zoltiq Agents - User Management', 'zoltiq-agents' ),
				'description' => __( 'Abilities for reading, creating, updating, and deleting WordPress users, plus user meta and password resets.', 'zoltiq-agents' ),
			)
		);
	}
}
