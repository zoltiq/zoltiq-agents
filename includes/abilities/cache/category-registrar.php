<?php

namespace Zoltiq\Agents\Includes\Abilities\Cache;

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
			'zoltiq-agents-cache',
			array(
				'label'       => __( 'Zoltiq Agents - Cache Management', 'zoltiq-agents' ),
				'description' => __( 'Abilities for managing WordPress caches: flush object cache and related cache stores.', 'zoltiq-agents' ),
			)
		);
	}
}
