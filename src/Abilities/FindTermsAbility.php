<?php
/**
 * content/find-terms ability.
 *
 * @package ContentAbilities
 */

declare( strict_types=1 );

namespace ContentAbilities\Abilities;

use ContentAbilities\Contracts\AbilityContract;
use ContentAbilities\Services\TermService;
use WP_Error;

/**
 * Lists or searches categories and tags.
 */
final class FindTermsAbility extends AbstractAbility implements AbilityContract {

	public function __construct(
		private readonly TermService $terms,
	) {}

	public static function name(): string {
		return 'content/find-terms';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function definition(): array {
		return $this->build(
			label: __( 'Find Terms', 'content-abilities' ),
			description: __( 'List or search WordPress categories or tags.', 'content-abilities' ),
			inputSchema: array(
				'type'       => 'object',
				'properties' => array(
					'taxonomy' => $this->taxonomySchema(),
					'search'   => array( 'type' => 'string', 'description' => 'Optional name search.' ),
					'page'     => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
					'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ),
				),
				'required'   => array( 'taxonomy' ),
			),
			outputSchema: array(
				'type'       => 'object',
				'properties' => array(
					'items'    => array( 'type' => 'array', 'items' => $this->termSchema() ),
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
		return true;
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>|WP_Error
	 */
	public function execute( mixed $input ): array|WP_Error {
		return $this->terms->findTerms( is_array( $input ) ? $input : array() );
	}
}
