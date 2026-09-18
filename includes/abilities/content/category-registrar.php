<?php

namespace Zoltiq\Agents\Includes\Abilities\Content;

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
			'zoltiq-agents-content',
			array(
				'label'       => __( 'Zoltiq Agents - Content', 'zoltiq-agents' ),
				'description' => __( 'Abilities for managing posts, pages, custom post types, multilingual translations, and Jet Engine options pages.', 'zoltiq-agents' ),
			)
		);
	}
}
