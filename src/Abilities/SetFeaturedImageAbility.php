<?php
/**
 * content/set-featured-image ability.
 *
 * @package ContentAbilities
 */

declare( strict_types=1 );

namespace ContentAbilities\Abilities;

use ContentAbilities\Contracts\AbilityContract;
use ContentAbilities\Services\MediaService;
use WP_Error;

final class SetFeaturedImageAbility extends AbstractAbility implements AbilityContract {

	public function __construct( private readonly MediaService $media ) {}

	public static function name(): string {
		return 'content/set-featured-image';
	}

	/** @return array<string, mixed> */
	public function definition(): array {
		return $this->build(
			label: __( 'Set Featured Image', 'content-abilities' ),
			description: __( 'Set a readable image attachment as the featured image of an editable post.', 'content-abilities' ),
			inputSchema: array(
				'type'                 => 'object',
				'properties'           => array(
					'post_id'  => array( 'type' => 'integer', 'minimum' => 1 ),
					'media_id' => array( 'type' => 'integer', 'minimum' => 1 ),
				),
				'required'             => array( 'post_id', 'media_id' ),
				'additionalProperties' => false,
			),
			outputSchema: array(
				'type'       => 'object',
				'properties' => array( 'post' => $this->postSchema(), 'media' => $this->mediaSchema() ),
				'required'   => array( 'post', 'media' ),
			),
			isReadOnly: false,
			destructive: false,
			idempotent: true,
		);
	}

	public function checkPermissions( mixed $input ): bool|WP_Error {
		$postId  = is_array( $input ) ? (int) ( $input['post_id'] ?? 0 ) : 0;
		$mediaId = is_array( $input ) ? (int) ( $input['media_id'] ?? 0 ) : 0;
		if ( $postId > 0 && ! current_user_can( 'edit_post', $postId ) ) {
			return new WP_Error( 'content_forbidden', __( 'You are not allowed to edit this post.', 'content-abilities' ) );
		}
		return $mediaId < 1 || current_user_can( 'read_post', $mediaId ) ? true : new WP_Error( 'content_forbidden', __( 'You are not allowed to read this media item.', 'content-abilities' ) );
	}

	/** @return array<string, mixed>|WP_Error */
	public function execute( mixed $input ): array|WP_Error {
		$input = is_array( $input ) ? $input : array();
		return $this->media->setFeaturedImage( (int) ( $input['post_id'] ?? 0 ), (int) ( $input['media_id'] ?? 0 ) );
	}
}
