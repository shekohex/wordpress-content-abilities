<?php
/**
 * content/import-media ability.
 *
 * @package ContentAbilities
 */

declare( strict_types=1 );

namespace ContentAbilities\Abilities;

use ContentAbilities\Contracts\AbilityContract;
use ContentAbilities\Services\MediaService;
use WP_Error;

final class ImportMediaAbility extends AbstractAbility implements AbilityContract {

	public function __construct( private readonly MediaService $media ) {}

	public static function name(): string {
		return 'content/import-media';
	}

	/** @return array<string, mixed> */
	public function definition(): array {
		return $this->build(
			label: __( 'Import Media', 'content-abilities' ),
			description: __( 'Securely import media from a public HTTPS URL with a plugin-level 20 MiB limit.', 'content-abilities' ),
			inputSchema: array(
				'type'                 => 'object',
				'properties'           => array(
					'source_url'        => array( 'type' => 'string', 'format' => 'uri', 'maxLength' => 2048, 'description' => 'Public HTTPS source URL.' ),
					'title'             => array( 'type' => 'string', 'maxLength' => 500 ),
					'alt_text'          => array( 'type' => 'string', 'maxLength' => 2000 ),
					'caption'           => array( 'type' => 'string', 'maxLength' => 10000 ),
					'description'       => array( 'type' => 'string', 'maxLength' => 100000 ),
					'attach_to_post_id' => array( 'type' => 'integer', 'minimum' => 1, 'description' => 'Optional editable parent post ID.' ),
				),
				'required'             => array( 'source_url' ),
				'additionalProperties' => false,
			),
			outputSchema: $this->mediaSchema(),
			isReadOnly: false,
			destructive: false,
			idempotent: false,
		);
	}

	public function checkPermissions( mixed $input ): bool|WP_Error {
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'content_forbidden', __( 'You are not allowed to upload files.', 'content-abilities' ) );
		}
		$parentId = is_array( $input ) ? (int) ( $input['attach_to_post_id'] ?? 0 ) : 0;
		return $parentId < 1 || current_user_can( 'edit_post', $parentId ) ? true : new WP_Error( 'content_forbidden', __( 'You are not allowed to attach media to this post.', 'content-abilities' ) );
	}

	/** @return array<string, mixed>|WP_Error */
	public function execute( mixed $input ): array|WP_Error {
		return $this->media->importMedia( is_array( $input ) ? $input : array() );
	}
}
