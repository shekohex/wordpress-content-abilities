<?php
/**
 * content/insert-media ability.
 *
 * @package ContentAbilities
 */

declare( strict_types=1 );

namespace ContentAbilities\Abilities;

use ContentAbilities\Contracts\AbilityContract;
use ContentAbilities\Services\MediaService;
use WP_Error;

final class InsertMediaAbility extends AbstractAbility implements AbilityContract {

	public function __construct( private readonly MediaService $media ) {}

	public static function name(): string {
		return 'content/insert-media';
	}

	/** @return array<string, mixed> */
	public function definition(): array {
		return $this->build(
			label: __( 'Insert Media', 'content-abilities' ),
			description: __( 'Insert a Gutenberg image block into an editable post with exact anchor and concurrency protection.', 'content-abilities' ),
			inputSchema: array(
				'type'                 => 'object',
				'properties'           => array(
					'post_id'               => array( 'type' => 'integer', 'minimum' => 1 ),
					'media_id'              => array( 'type' => 'integer', 'minimum' => 1 ),
					'expected_modified_gmt' => array( 'type' => 'string', 'description' => 'Optional exact post modified_gmt value.' ),
					'placement'             => array( 'type' => 'string', 'enum' => array( 'append', 'prepend', 'before', 'after', 'replace' ), 'default' => 'append' ),
					'anchor_text'           => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 10000, 'description' => 'Required for before, after, and replace.' ),
					'size_slug'             => array( 'type' => 'string', 'enum' => array( 'thumbnail', 'medium', 'medium_large', 'large', 'full' ), 'default' => 'large' ),
					'caption'               => array( 'type' => 'string', 'maxLength' => 10000 ),
					'link_destination'      => array( 'type' => 'string', 'enum' => array( 'none', 'media', 'attachment' ), 'default' => 'none' ),
				),
				'required'             => array( 'post_id', 'media_id' ),
				'additionalProperties' => false,
			),
			outputSchema: array(
				'type'       => 'object',
				'properties' => array(
					'replacement_count' => array( 'type' => 'integer', 'minimum' => 0 ),
					'post'              => $this->postSchema(),
					'media'             => $this->mediaSchema(),
					'block_html'        => array( 'type' => 'string' ),
				),
				'required'   => array( 'replacement_count', 'post', 'media', 'block_html' ),
			),
			isReadOnly: false,
			destructive: false,
			idempotent: false,
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
		return $this->media->insertMedia( is_array( $input ) ? $input : array() );
	}
}
