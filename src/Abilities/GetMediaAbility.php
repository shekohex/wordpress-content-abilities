<?php
/**
 * content/get-media ability.
 *
 * @package ContentAbilities
 */

declare( strict_types=1 );

namespace ContentAbilities\Abilities;

use ContentAbilities\Contracts\AbilityContract;
use ContentAbilities\Services\MediaService;
use WP_Error;

final class GetMediaAbility extends AbstractAbility implements AbilityContract {

	public function __construct( private readonly MediaService $media ) {}

	public static function name(): string {
		return 'content/get-media';
	}

	/** @return array<string, mixed> */
	public function definition(): array {
		return $this->build(
			label: __( 'Get Media', 'content-abilities' ),
			description: __( 'Retrieve stable metadata for one readable media attachment.', 'content-abilities' ),
			inputSchema: array(
				'type'                 => 'object',
				'properties'           => array( 'id' => array( 'type' => 'integer', 'minimum' => 1, 'description' => 'Attachment ID.' ) ),
				'required'             => array( 'id' ),
				'additionalProperties' => false,
			),
			outputSchema: $this->mediaSchema(),
			isReadOnly: true,
			destructive: false,
			idempotent: true,
		);
	}

	public function checkPermissions( mixed $input ): bool|WP_Error {
		$id = is_array( $input ) ? (int) ( $input['id'] ?? 0 ) : 0;
		return $id < 1 || current_user_can( 'read_post', $id ) ? true : new WP_Error( 'content_forbidden', __( 'You are not allowed to read this media item.', 'content-abilities' ) );
	}

	/** @return array<string, mixed>|WP_Error */
	public function execute( mixed $input ): array|WP_Error {
		return $this->media->getMedia( is_array( $input ) ? (int) ( $input['id'] ?? 0 ) : 0 );
	}
}
