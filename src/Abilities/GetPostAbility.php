<?php
/**
 * content/get-post ability.
 *
 * @package ContentAbilities
 */

declare( strict_types=1 );

namespace ContentAbilities\Abilities;

use ContentAbilities\Contracts\AbilityContract;
use ContentAbilities\Services\PostService;
use WP_Error;

/**
 * Retrieves a single post with categories and tags.
 */
final class GetPostAbility extends AbstractAbility implements AbilityContract {

	public function __construct(
		private readonly PostService $posts,
	) {}

	public static function name(): string {
		return 'content/get-post';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function definition(): array {
		return $this->build(
			label: __( 'Get Post', 'content-abilities' ),
			description: __( 'Retrieve a single post by ID, including content, categories, and tags. Requires permission to read the post.', 'content-abilities' ),
			inputSchema: array(
				'type'       => 'object',
				'properties' => array(
					'id' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => 'Post ID.',
					),
				),
				'required'   => array( 'id' ),
			),
			outputSchema: $this->postSchema(),
			isReadOnly: true,
			destructive: false,
			idempotent: true,
		);
	}

	public function checkPermissions( mixed $input ): bool|WP_Error {
		// Object-level check happens in the service; permission callback
		// performs the same check so it is enforced even before execution.
		$id = is_array( $input ) && isset( $input['id'] ) ? (int) $input['id'] : 0;
		if ( $id < 1 ) {
			return true; // Let schema validation reject malformed input.
		}
		if ( ! current_user_can( 'read_post', $id ) ) {
			return new WP_Error(
				'content_forbidden',
				__( 'You are not allowed to read this post.', 'content-abilities' )
			);
		}
		return true;
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>|WP_Error
	 */
	public function execute( mixed $input ): array|WP_Error {
		return $this->posts->getPost( (int) ( is_array( $input ) ? ( $input['id'] ?? 0 ) : 0 ) );
	}
}
