<?php
/**
 * content/update-term ability.
 *
 * @package ContentAbilities
 */

declare( strict_types=1 );

namespace ContentAbilities\Abilities;

use ContentAbilities\Contracts\AbilityContract;
use ContentAbilities\Services\TermService;
use WP_Error;

/**
 * Updates a category or tag.
 */
final class UpdateTermAbility extends AbstractAbility implements AbilityContract {

	public function __construct(
		private readonly TermService $terms,
	) {}

	public static function name(): string {
		return 'content/update-term';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function definition(): array {
		return $this->build(
			label: __( 'Update Term', 'content-abilities' ),
			description: __( 'Update a category or tag using the taxonomy edit_terms capability.', 'content-abilities' ),
			inputSchema: array(
				'type'                 => 'object',
				'properties'           => array(
					'taxonomy'  => $this->taxonomySchema(),
					'id'        => array( 'type' => 'integer', 'minimum' => 1, 'description' => 'Term ID.' ),
					'name'      => array( 'type' => 'string', 'minLength' => 1, 'description' => 'New term name.' ),
					'slug'      => array( 'type' => 'string', 'description' => 'New term slug.' ),
					'description' => array( 'type' => 'string', 'description' => 'New term description; safe HTML allowed.' ),
					'parent'    => array( 'type' => 'integer', 'minimum' => 0, 'description' => 'New parent category ID; ignored for tags.' ),
				),
				'required'             => array( 'taxonomy', 'id' ),
				'additionalProperties' => false,
			),
			outputSchema: $this->termSchema(),
			isReadOnly: false,
			destructive: false,
			idempotent: true,
		);
	}

	public function checkPermissions( mixed $input ): bool|WP_Error {
		$taxonomy = is_array( $input ) ? (string) ( $input['taxonomy'] ?? '' ) : '';
		if ( ! in_array( $taxonomy, array( 'category', 'post_tag' ), true ) || ! taxonomy_exists( $taxonomy ) ) {
			return new WP_Error( 'content_invalid_taxonomy', __( 'Only category and post_tag taxonomies are supported.', 'content-abilities' ) );
		}
		$taxonomyObject = get_taxonomy( $taxonomy );
		if ( false === $taxonomyObject || ! isset( $taxonomyObject->cap->edit_terms ) ) {
			return new WP_Error( 'content_invalid_taxonomy', __( 'Only category and post_tag taxonomies are supported.', 'content-abilities' ) );
		}
		if ( ! current_user_can( (string) $taxonomyObject->cap->edit_terms ) ) {
			return new WP_Error( 'content_forbidden', __( 'You are not allowed to create or edit terms in this taxonomy.', 'content-abilities' ) );
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>|WP_Error
	 */
	public function execute( mixed $input ): array|WP_Error {
		return $this->terms->updateTerm( is_array( $input ) ? $input : array() );
	}
}
