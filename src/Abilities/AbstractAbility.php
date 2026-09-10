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
				'mcp'         => array(
					'public' => true,
					'type'   => 'tool',
				),
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

	/**
	 * Builds the shared attachment output schema.
	 *
	 * @return array<string, mixed>
	 */
	protected function mediaSchema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'           => array( 'type' => 'integer', 'description' => 'Attachment ID.' ),
				'title'        => array( 'type' => 'string', 'description' => 'Attachment title.' ),
				'alt_text'     => array( 'type' => 'string', 'description' => 'Image alternative text.' ),
				'caption'      => array( 'type' => 'string', 'description' => 'Attachment caption.' ),
				'description'  => array( 'type' => 'string', 'description' => 'Attachment description.' ),
				'mime_type'    => array( 'type' => 'string', 'description' => 'Attachment MIME type.' ),
				'media_type'   => array( 'type' => 'string', 'description' => 'Top-level media family.' ),
				'url'          => array( 'type' => 'string', 'format' => 'uri', 'description' => 'Original attachment URL.' ),
				'width'        => array( 'type' => 'integer', 'minimum' => 0, 'description' => 'Original image width, or zero.' ),
				'height'       => array( 'type' => 'integer', 'minimum' => 0, 'description' => 'Original image height, or zero.' ),
				'filesize'     => array( 'type' => 'integer', 'minimum' => 0, 'description' => 'File size in bytes, or zero.' ),
				'parent_id'    => array( 'type' => 'integer', 'minimum' => 0, 'description' => 'Parent post ID, or zero.' ),
				'date_gmt'     => array( 'type' => 'string', 'description' => 'Creation date in GMT.' ),
				'modified_gmt' => array( 'type' => 'string', 'description' => 'Modification date in GMT.' ),
				'sizes'        => array(
					'type'                 => 'object',
					'description'          => 'Generated image sizes keyed by size slug.',
					'additionalProperties' => array(
						'type'       => 'object',
						'properties' => array(
							'url'       => array( 'type' => 'string', 'format' => 'uri' ),
							'width'     => array( 'type' => 'integer', 'minimum' => 0 ),
							'height'    => array( 'type' => 'integer', 'minimum' => 0 ),
							'mime_type' => array( 'type' => 'string' ),
							'filesize'  => array( 'type' => 'integer', 'minimum' => 0 ),
						),
						'required'   => array( 'url', 'width', 'height', 'mime_type', 'filesize' ),
					),
				),
			),
			'required'   => array( 'id', 'title', 'alt_text', 'caption', 'description', 'mime_type', 'media_type', 'url', 'width', 'height', 'filesize', 'parent_id', 'date_gmt', 'modified_gmt', 'sizes' ),
		);
	}
}
