<?php

namespace Zoltiq\Agents\Includes\Abilities;

defined( 'ABSPATH' ) || exit;

abstract class Ability_Definition {

	public function __construct() {
		add_filter( 'zoltiq_abilities_api_init', array( $this, 'push_definition' ) );
	}

	abstract protected function ability(): array;

	public function push_definition( array $definitions ): array {
		$spec = $this->ability();
		$name = $spec['name'] ?? '';
		$args = $spec['args'] ?? array();

		$category = $args['category'] ?? '';

		$row = array(
			'category'       => $category,
			'category_label' => ucwords( str_replace( '-', ' ', $category ) ),
			'slug'           => $name,
			'slug_label'     => $args['label'] ?? $name,
			'name'           => $name,
			'args'           => $args,
		);


		$definitions[] = $row;

		return $definitions;
	}

}
