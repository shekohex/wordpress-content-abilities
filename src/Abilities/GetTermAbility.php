<?php
/**
 * content/get-term ability.
 *
 * @package ContentAbilities
 */

declare( strict_types=1 );

namespace ContentAbilities\Abilities;

use ContentAbilities\Contracts\AbilityContract;
use ContentAbilities\Services\TermService;
use WP_Error;

/**
 * Gets one category or tag.
 */
final class GetTermAbility extends AbstractAbility implements AbilityContract {

	public function __construct(
		private readonly TermService $terms,
	) {}

	public static function name(): string {
		return 'content/get-term';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function definition(): array {
		return $this->build(
			label: __( 'Get Term', 'content-abilities' ),
			description: __( 'Retrieve one category or tag by taxonomy and term ID.', 'content-abilities' ),
			inputSchema: array(
				'type'       => 'object',
				'properties' => array(
					'taxonomy' => $this->taxonomySchema(),
					'id'       => array( 'type' => 'integer', 'minimum' => 1, 'description' => 'Term ID.' ),
				),
				'required'   => array( 'taxonomy', 'id' ),
			),
			outputSchema: $this->termSchema(),
			isReadOnly: true,
			destructive: false,
			idempotent: true,
		);
	}

	public function checkPermissions( mixed $input ): bool|WP_Error {
		return true;
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>|WP_Error
	 */
	public function execute( mixed $input ): array|WP_Error {
		return $this->terms->getTerm( is_array( $input ) ? $input : array() );
	}
}
