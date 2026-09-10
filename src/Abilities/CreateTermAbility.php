<?php
/**
 * content/create-term ability.
 *
 * @package ContentAbilities
 */

declare( strict_types=1 );

namespace ContentAbilities\Abilities;

use ContentAbilities\Contracts\AbilityContract;
use ContentAbilities\Services\TermService;
use WP_Error;

/**
 * Creates a category or tag.
 */
final class CreateTermAbility extends AbstractAbility implements AbilityContract {

	public function __construct(
		private readonly TermService $terms,
	) {}

	public static function name(): string {
		return 'content/create-term';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function definition(): array {
		return $this->build(
			label: __( 'Create Term', 'content-abilities' ),
			description: __( 'Create a category or tag using the taxonomy edit_terms capability.', 'content-abilities' ),
			inputSchema: $this->writeSchema( false ),
			outputSchema: $this->termSchema(),
			isReadOnly: false,
			destructive: false,
			idempotent: false,
		);
	}

	public function checkPermissions( mixed $input ): bool|WP_Error {
		return $this->checkTaxonomyPermission( $input );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>|WP_Error
	 */
	public function execute( mixed $input ): array|WP_Error {
		return $this->terms->createTerm( is_array( $input ) ? $input : array() );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function writeSchema( bool $updating ): array {
		$properties = array(
			'taxonomy'  => $this->taxonomySchema(),
			'name'      => array( 'type' => 'string', 'minLength' => 1, 'description' => 'Term name.' ),
			'slug'      => array( 'type' => 'string', 'description' => 'Optional term slug.' ),
			'description' => array( 'type' => 'string', 'description' => 'Optional term description; safe HTML allowed.' ),
			'parent'    => array( 'type' => 'integer', 'minimum' => 0, 'description' => 'Parent category ID; ignored for tags.' ),
		);

		return array(
			'type'                 => 'object',
			'properties'           => $properties,
			'required'             => $updating ? array( 'taxonomy', 'id' ) : array( 'taxonomy', 'name' ),
			'additionalProperties' => false,
		);
	}

	private function checkTaxonomyPermission( mixed $input ): bool|WP_Error {
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
}
