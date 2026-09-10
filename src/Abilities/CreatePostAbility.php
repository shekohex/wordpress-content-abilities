<?php
/**
 * content/create-post ability.
 *
 * @package ContentAbilities
 */

declare( strict_types=1 );

namespace ContentAbilities\Abilities;

use ContentAbilities\Contracts\AbilityContract;
use ContentAbilities\Services\PostService;
use WP_Error;

/**
 * Creates a new post (draft by default; publish requires capability).
 */
final class CreatePostAbility extends AbstractAbility implements AbilityContract {

	public function __construct(
		private readonly PostService $posts,
	) {}

	public static function name(): string {
		return 'content/create-post';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function definition(): array {
		return $this->build(
			label: __( 'Create Post', 'content-abilities' ),
			description: __( 'Create a new post of a public post type. Defaults to draft; publishing requires the publish capability. Categories and tags are assigned by name.', 'content-abilities' ),
			inputSchema: array(
				'type'       => 'object',
				'properties' => array(
					'title'      => array( 'type' => 'string', 'description' => 'Post title.' ),
					'content'    => array( 'type' => 'string', 'description' => 'Post content (HTML allowed, scripts stripped).' ),
					'excerpt'    => array( 'type' => 'string', 'description' => 'Post excerpt.' ),
					'status'     => array(
						'type'        => 'string',
						'enum'        => array( 'draft', 'publish' ),
						'default'     => 'draft',
						'description' => 'Initial status.',
					),
					'post_type'  => array(
						'type'        => 'string',
						'default'     => 'post',
						'description' => 'Public post type slug.',
					),
					'categories' => array(
						'type'        => 'array',
						'items'       => array(
							'oneOf' => array(
								array( 'type' => 'integer', 'minimum' => 1 ),
								array( 'type' => 'string', 'minLength' => 1 ),
							),
						),
						'description' => 'Category IDs or names to assign. Names are created if missing.',
					),
					'tags'       => array(
						'type'        => 'array',
						'items'       => array(
							'oneOf' => array(
								array( 'type' => 'integer', 'minimum' => 1 ),
								array( 'type' => 'string', 'minLength' => 1 ),
							),
						),
						'description' => 'Tag IDs or names to assign. Names are created if missing.',
					),
				),
			),
			outputSchema: $this->postSchema(),
			isReadOnly: false,
			destructive: false,
			idempotent: false,
		);
	}

	public function checkPermissions( mixed $input ): bool|WP_Error {
		return true; // Enforced in service with post-type-specific caps.
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>|WP_Error
	 */
	public function execute( mixed $input ): array|WP_Error {
		return $this->posts->createPost( is_array( $input ) ? $input : array() );
	}
}
