<?php
/**
 * Capability guard: centralizes WordPress capability checks.
 *
 * @package ContentAbilities
 */

declare( strict_types=1 );

namespace ContentAbilities\Support;

/**
 * Thin, testable wrapper over current_user_can() incl. object-level meta caps.
 */
final class CapabilityGuard {

	/**
	 * Whether the current user can read a specific post (object-level).
	 */
	public function canReadPost( int $id ): bool {
		return current_user_can( 'read_post', $id );
	}

	/**
	 * Whether the current user can edit a specific post (object-level).
	 */
	public function canEditPost( int $id ): bool {
		return current_user_can( 'edit_post', $id );
	}

	/**
	 * Whether the current user can publish a specific post (object-level).
	 */
	public function canPublishPost( int $id ): bool {
		return current_user_can( 'publish_post', $id );
	}

	/**
	 * Whether the current user can create posts of a given type.
	 */
	public function canCreatePosts( string $postType ): bool {
		$cap = 'edit_posts';
		$obj = get_post_type_object( $postType );
		if ( null !== $obj && isset( $obj->cap->create_posts ) ) {
			$cap = (string) $obj->cap->create_posts;
		}
		return current_user_can( $cap );
	}

	/**
	 * Whether the current user can publish posts of a given type (primitive cap).
	 */
	public function canPublishPosts( string $postType ): bool {
		$cap = 'publish_posts';
		$obj = get_post_type_object( $postType );
		if ( null !== $obj && isset( $obj->cap->publish_posts ) ) {
			$cap = (string) $obj->cap->publish_posts;
		}
		return current_user_can( $cap );
	}

	/**
	 * Whether the current user can edit other users' posts of a given type.
	 */
	public function canEditOthersPosts( string $postType ): bool {
		$cap = 'edit_others_posts';
		$obj = get_post_type_object( $postType );
		if ( null !== $obj && isset( $obj->cap->edit_others_posts ) ) {
			$cap = (string) $obj->cap->edit_others_posts;
		}

		return current_user_can( $cap );
	}
}
