<?php
/**
 * Post type helpers.
 *
 * @package ContentAbilities
 */

declare( strict_types=1 );

namespace ContentAbilities\Support;

/**
 * Public post type discovery/validation.
 */
final class PostTypes {

	/**
	 * Public post type names (e.g. post, page, and any registered public CPTs).
	 *
	 * @return string[]
	 */
	public static function publicNames(): array {
		return array_values( get_post_types( array( 'public' => true ), 'names' ) );
	}

	/**
	 * Whether a post type exists and is public.
	 */
	public static function isPublic( string $postType ): bool {
		if ( '' === $postType ) {
			return false;
		}
		$obj = get_post_type_object( $postType );
		return null !== $obj && (bool) $obj->public;
	}
}
