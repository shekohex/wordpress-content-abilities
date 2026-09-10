<?php
/**
 * content/update-post ability.
 *
 * @package ContentAbilities
 */

declare( strict_types=1 );

namespace ContentAbilities\Abilities;

use ContentAbilities\Contracts\AbilityContract;
use ContentAbilities\Services\PostService;
use WP_Error;

/**
 * Updates an existing post the current user can edit.
 */
final class UpdatePostAbility extends AbstractAbility implements AbilityContract {

	public function __construct(
		private readonly PostService $posts,
	) {}

	public static function name(): string {
		return 'content/update-post';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function definition(): array {
		return $this->build(
			label: __( 'Update Post', 'content-abilities' ),
			description: __( 'Update title, content, excerpt, status, categories, or tags of an existing post. Requires object-level edit permission; publishing additionally requires the publish capability.', 'content-abilities' ),
			inputSchema: array(
				'type'       => 'object',
				'properties' => array(
					'id'         => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => 'ID of the post to update.',
					),
					'title'      => array( 'type' => 'string', 'description' => 'New title.' ),
					'content'    => array( 'type' => 'string', 'description' => 'New content (HTML allowed, scripts stripped).' ),
					'excerpt'    => array( 'type' => 'string', 'description' => 'New excerpt.' ),
					'status'     => array(
						'type'        => 'string',
						'enum'        => array( 'draft', 'publish' ),
						'description' => 'New status. Publishing a draft requires the publish capability.',
					),
					'categories' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => 'Replacement category names.',
					),
					'tags'       => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => 'Replacement tag names.',
					),
				),
				'required'   => array( 'id' ),
				'additionalProperties' => false,
			),
			outputSchema: $this->postSchema(),
			isReadOnly: false,
			destructive: false,
			idempotent: true,
		);
	}

	public function checkPermissions( mixed $input ): bool|WP_Error {
		$id = is_array( $input ) && isset( $input['id'] ) ? (int) $input['id'] : 0;
		if ( $id < 1 ) {
			return true; // Schema validation rejects malformed input.
		}
		if ( ! current_user_can( 'edit_post', $id ) ) {
			return new WP_Error(
				'content_forbidden',
				__( 'You are not allowed to edit this post.', 'content-abilities' )
			);
		}
		return true;
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>|WP_Error
	 */
	public function execute( mixed $input ): array|WP_Error {
		return $this->posts->updatePost( is_array( $input ) ? $input : array() );
	}
}
