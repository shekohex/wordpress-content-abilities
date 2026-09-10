<?php
/**
 * content/find-posts ability.
 *
 * @package ContentAbilities
 */

declare( strict_types=1 );

namespace ContentAbilities\Abilities;

use ContentAbilities\Contracts\AbilityContract;
use ContentAbilities\Services\PostService;
use WP_Error;

/**
 * Searches/lists public posts.
 */
final class FindPostsAbility extends AbstractAbility implements AbilityContract {

	public function __construct(
		private readonly PostService $posts,
	) {}

	public static function name(): string {
		return 'content/find-posts';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function definition(): array {
		return $this->build(
			label: __( 'Find Posts', 'content-abilities' ),
			description: __( 'Search and list published posts of public post types, optionally including drafts when allowed.', 'content-abilities' ),
			inputSchema: array(
				'type'       => 'object',
				'properties' => array(
					'search'    => array(
						'type'        => 'string',
						'description' => 'Search term matched against title and content.',
					),
					'post_type' => array(
						'type'        => 'string',
						'description' => 'Post type slug. Defaults to "post".',
						'default'     => 'post',
					),
					'per_page'  => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'maximum'     => 50,
						'default'     => 10,
						'description' => 'Results per page.',
					),
					'page'      => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'default'     => 1,
						'description' => '1-based page number.',
					),
					'include_drafts' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Include draft posts. Requires edit permission.',
					),
				),
			),
			outputSchema: $this->pagedPostsSchema(),
			isReadOnly: true,
			destructive: false,
			idempotent: true,
		);
	}

	public function checkPermissions( mixed $input ): bool|WP_Error {
		$includeDrafts        = is_array( $input ) && ! empty( $input['include_drafts'] );
		$postType             = is_array( $input ) ? (string) ( $input['post_type'] ?? 'post' ) : 'post';
		$postTypeObject       = get_post_type_object( $postType );
		$editOthersCapability = null !== $postTypeObject && isset( $postTypeObject->cap->edit_others_posts )
			? (string) $postTypeObject->cap->edit_others_posts
			: 'edit_others_posts';

		if ( $includeDrafts && ! current_user_can( $editOthersCapability ) ) {
			return new WP_Error(
				'content_forbidden',
				__( 'You are not allowed to include draft posts.', 'content-abilities' )
			);
		}
		return true;
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>|WP_Error
	 */
	public function execute( mixed $input ): array|WP_Error {
		return $this->posts->findPosts( is_array( $input ) ? $input : array() );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function pagedPostsSchema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'items'    => array(
					'type'  => 'array',
					'items' => array( '$ref' => '#/$defs/post' ),
				),
				'total'    => array( 'type' => 'integer' ),
				'page'     => array( 'type' => 'integer' ),
				'per_page' => array( 'type' => 'integer' ),
			),
			'required'   => array( 'items', 'total', 'page', 'per_page' ),
			'$defs'      => array( 'post' => $this->postSchema() ),
		);
	}
}
