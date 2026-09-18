<?php

namespace Zoltiq\Agents\Includes\Abilities\Block;

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
			'zoltiq-agents-block',
			array(
				'label'       => __( 'Zoltiq Agents - Block Patterns', 'zoltiq-agents' ),
				'description' => __( 'Abilities for managing theme block patterns: list, read, create, update, and delete pattern PHP files inside a theme\'s /patterns directory.', 'zoltiq-agents' ),
			)
		);
	}
}
