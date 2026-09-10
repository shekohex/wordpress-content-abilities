<?php
/**
 * content/patch-post ability.
 *
 * @package ContentAbilities
 */

declare( strict_types=1 );

namespace ContentAbilities\Abilities;

use ContentAbilities\Contracts\AbilityContract;
use ContentAbilities\Services\PostService;
use WP_Error;

/**
 * Replaces exact text in one post field.
 */
final class PatchPostAbility extends AbstractAbility implements AbilityContract {

	public function __construct(
		private readonly PostService $posts,
	) {}

	public static function name(): string {
		return 'content/patch-post';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function definition(): array {
		return $this->build(
			label: __( 'Patch Post', 'content-abilities' ),
			description: __( 'Replace exact text in post content, title, or excerpt without rewriting the surrounding field. Rejects missing or ambiguous matches and supports optimistic concurrency.', 'content-abilities' ),
			inputSchema: array(
				'type'                 => 'object',
				'properties'           => array(
					'id'                    => array( 'type' => 'integer', 'minimum' => 1, 'description' => 'Post ID.' ),
					'field'                 => array( 'type' => 'string', 'enum' => array( 'content', 'title', 'excerpt' ), 'description' => 'Field to patch.' ),
					'old_text'              => array( 'type' => 'string', 'minLength' => 1, 'description' => 'Exact text to replace.' ),
					'new_text'              => array( 'type' => 'string', 'description' => 'Replacement text; output is sanitized for the selected field.' ),
					'replace_all'            => array( 'type' => 'boolean', 'default' => false, 'description' => 'Replace every exact match. False rejects multiple matches.' ),
					'expected_modified_gmt' => array( 'type' => 'string', 'description' => 'Optional exact modified_gmt value for optimistic concurrency.' ),
				),
				'required'             => array( 'id', 'field', 'old_text', 'new_text' ),
				'additionalProperties' => false,
			),
			outputSchema: array(
				'type'       => 'object',
				'properties' => array(
					'replacement_count' => array( 'type' => 'integer', 'minimum' => 1 ),
					'post'              => $this->postSchema(),
				),
				'required'   => array( 'replacement_count', 'post' ),
			),
			isReadOnly: false,
			destructive: false,
			idempotent: false,
		);
	}

	public function checkPermissions( mixed $input ): bool|WP_Error {
		$id = is_array( $input ) && isset( $input['id'] ) ? (int) $input['id'] : 0;
		if ( $id < 1 ) {
			return true;
		}
		if ( ! current_user_can( 'edit_post', $id ) ) {
			return new WP_Error( 'content_forbidden', __( 'You are not allowed to edit this post.', 'content-abilities' ) );
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>|WP_Error
	 */
	public function execute( mixed $input ): array|WP_Error {
		return $this->posts->patchPost( is_array( $input ) ? $input : array() );
	}
}
