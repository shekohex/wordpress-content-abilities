<?php
/**
 * content/find-media ability.
 *
 * @package ContentAbilities
 */

declare( strict_types=1 );

namespace ContentAbilities\Abilities;

use ContentAbilities\Contracts\AbilityContract;
use ContentAbilities\Services\MediaService;
use WP_Error;

final class FindMediaAbility extends AbstractAbility implements AbilityContract {

	public function __construct( private readonly MediaService $media ) {}

	public static function name(): string {
		return 'content/find-media';
	}

	/** @return array<string, mixed> */
	public function definition(): array {
		return $this->build(
			label: __( 'Find Media', 'content-abilities' ),
			description: __( 'Search and list readable media attachments with bounded pagination and optional MIME filtering.', 'content-abilities' ),
			inputSchema: array(
				'type'                 => 'object',
				'properties'           => array(
					'search'    => array( 'type' => 'string', 'maxLength' => 200, 'description' => 'Search attachment title and description.' ),
					'mime_type' => array( 'type' => 'string', 'maxLength' => 100, 'description' => 'Allowed exact MIME type or image, audio, video, or application family.' ),
					'page'      => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
					'per_page'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 10 ),
				),
				'additionalProperties' => false,
			),
			outputSchema: array(
				'type'       => 'object',
				'properties' => array(
					'items'    => array( 'type' => 'array', 'items' => $this->mediaSchema() ),
					'total'    => array( 'type' => 'integer' ),
					'page'     => array( 'type' => 'integer' ),
					'per_page' => array( 'type' => 'integer' ),
				),
				'required'   => array( 'items', 'total', 'page', 'per_page' ),
			),
			isReadOnly: true,
			destructive: false,
			idempotent: true,
		);
	}

	public function checkPermissions( mixed $input ): bool|WP_Error {
		return current_user_can( 'read' ) ? true : new WP_Error( 'content_forbidden', __( 'You are not allowed to read media.', 'content-abilities' ) );
	}

	/** @return array<string, mixed>|WP_Error */
	public function execute( mixed $input ): array|WP_Error {
		return $this->media->findMedia( is_array( $input ) ? $input : array() );
	}
}
