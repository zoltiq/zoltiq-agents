<?php

namespace Zoltiq\Agents\Includes\Abilities\Taxonomies;

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
			'zoltiq-agents-taxonomies',
			array(
				'label'       => __( 'Zoltiq Agents - Taxonomies', 'zoltiq-agents' ),
				'description' => __( 'Abilities for inspecting taxonomies and managing terms across any taxonomy.', 'zoltiq-agents' ),
			)
		);
	}
}
