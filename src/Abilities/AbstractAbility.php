<?php
/**
 * Shared ability plumbing: definition assembly.
 *
 * @package ContentAbilities
 */

declare( strict_types=1 );

namespace ContentAbilities\Abilities;

/**
 * Base class for content abilities.
 *
 * Assembles wp_register_ability() arguments with `meta.public` and
 * WordPress-format MCP annotations.
 */
abstract class AbstractAbility {

	/**
	 * Assembles the wp_register_ability() args.
	 *
	 * @param string $label        Human-readable label.
	 * @param string $description  Detailed description.
	 * @param array<string, mixed> $inputSchema  JSON Schema for input.
	 * @param array<string, mixed> $outputSchema JSON Schema for output.
	 * @param bool   $isReadOnly   Annotation: does not modify state.
	 * @param bool   $destructive  Annotation: may destroy data.
	 * @param bool   $idempotent   Annotation: same input, same effect.
	 *
	 * @return array<string, mixed>
	 */
	protected function build(
		string $label,
		string $description,
		array $inputSchema,
		array $outputSchema,
		bool $isReadOnly,
		bool $destructive,
		bool $idempotent
	): array {
		return array(
			'label'               => $label,
			'description'         => $description,
			'category'            => \ContentAbilities\Plugin::CATEGORY_SLUG,
			'input_schema'        => $inputSchema,
			'output_schema'       => $outputSchema,
			'execute_callback'    => array( $this, 'execute' ),
			'permission_callback' => array( $this, 'checkPermissions' ),
			'meta'                => array(
				'public'      => true,
				'annotations' => array(
					'readonly'    => $isReadOnly,
					'destructive' => $destructive,
					'idempotent'  => $idempotent,
				),
			),
		);
	}

	/**
	 * Builds the shared post-summary schema used by outputs.
	 *
	 * @return array<string, mixed>
	 */
	protected function postSchema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'            => array( 'type' => 'integer', 'description' => 'Post ID.' ),
				'type'          => array( 'type' => 'string', 'description' => 'Post type.' ),
				'title'         => array( 'type' => 'string', 'description' => 'Post title.' ),
				'status'        => array( 'type' => 'string', 'description' => 'Post status.' ),
				'slug'          => array( 'type' => 'string', 'description' => 'Post slug.' ),
				'excerpt'       => array( 'type' => 'string', 'description' => 'Post excerpt.' ),
				'content'       => array( 'type' => 'string', 'description' => 'Post content (HTML).' ),
				'link'          => array( 'type' => 'string', 'description' => 'Post permalink.' ),
				'date_gmt'      => array( 'type' => 'string', 'description' => 'Creation date (GMT).' ),
				'modified_gmt'  => array( 'type' => 'string', 'description' => 'Last modification date (GMT).' ),
				'categories'    => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => 'Assigned category names.',
				),
				'tags'          => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => 'Assigned tag names.',
				),
			),
			'required'   => array( 'id', 'type', 'title', 'status', 'slug', 'link' ),
		);
	}

	/**
	 * Builds the shared taxonomy selector schema.
	 *
	 * @return array<string, mixed>
	 */
	protected function taxonomySchema(): array {
		return array(
			'type'        => 'string',
			'enum'        => array( 'category', 'post_tag' ),
			'description' => 'WordPress taxonomy: category or post_tag.',
		);
	}

	/**
	 * Builds the shared term output schema.
	 *
	 * @return array<string, mixed>
	 */
	protected function termSchema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'          => array( 'type' => 'integer', 'description' => 'Term ID.' ),
				'taxonomy'    => $this->taxonomySchema(),
				'name'        => array( 'type' => 'string', 'description' => 'Term name.' ),
				'slug'        => array( 'type' => 'string', 'description' => 'Term slug.' ),
				'description' => array( 'type' => 'string', 'description' => 'Term description.' ),
				'parent'      => array( 'type' => 'integer', 'description' => 'Parent term ID for categories; zero otherwise.' ),
				'count'       => array( 'type' => 'integer', 'description' => 'Assigned object count.' ),
			),
			'required'   => array( 'id', 'taxonomy', 'name', 'slug', 'description', 'parent', 'count' ),
		);
	}
}
