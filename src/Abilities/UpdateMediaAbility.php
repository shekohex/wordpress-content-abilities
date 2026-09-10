<?php
/**
 * content/update-media ability.
 *
 * @package ContentAbilities
 */

declare( strict_types=1 );

namespace ContentAbilities\Abilities;

use ContentAbilities\Contracts\AbilityContract;
use ContentAbilities\Services\MediaService;
use WP_Error;

final class UpdateMediaAbility extends AbstractAbility implements AbilityContract {

	public function __construct( private readonly MediaService $media ) {}

	public static function name(): string {
		return 'content/update-media';
	}

	/** @return array<string, mixed> */
	public function definition(): array {
		return $this->build(
			label: __( 'Update Media', 'content-abilities' ),
			description: __( 'Update only title, alternative text, caption, or description for one attachment.', 'content-abilities' ),
			inputSchema: array(
				'type'                 => 'object',
				'properties'           => array(
					'id'          => array( 'type' => 'integer', 'minimum' => 1 ),
					'title'       => array( 'type' => 'string', 'maxLength' => 500 ),
					'alt_text'    => array( 'type' => 'string', 'maxLength' => 2000 ),
					'caption'     => array( 'type' => 'string', 'maxLength' => 10000 ),
					'description' => array( 'type' => 'string', 'maxLength' => 100000 ),
				),
				'required'             => array( 'id' ),
				'additionalProperties' => false,
			),
			outputSchema: $this->mediaSchema(),
			isReadOnly: false,
			destructive: false,
			idempotent: true,
		);
	}

	public function checkPermissions( mixed $input ): bool|WP_Error {
		$id = is_array( $input ) ? (int) ( $input['id'] ?? 0 ) : 0;
		return $id < 1 || current_user_can( 'edit_post', $id ) ? true : new WP_Error( 'content_forbidden', __( 'You are not allowed to edit this media item.', 'content-abilities' ) );
	}

	/** @return array<string, mixed>|WP_Error */
	public function execute( mixed $input ): array|WP_Error {
		return $this->media->updateMedia( is_array( $input ) ? $input : array() );
	}
}
