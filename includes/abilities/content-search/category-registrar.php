<?php

namespace Zoltiq\Agents\Includes\Abilities\ContentSearch;

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
			'zoltiq-agents-content-search',
			array(
				'label'       => __( 'Zoltiq Agents - Content Search', 'zoltiq-agents' ),
				'description' => __( 'Abilities for content indexing, search, related-content discovery, and internal-link suggestion management (option-backed suggestion queue).', 'zoltiq-agents' ),
			)
		);
	}
}
