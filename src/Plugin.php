<?php
/**
 * Main plugin service provider: category + ability registration.
 *
 * @package ContentAbilities
 */

declare( strict_types=1 );

namespace ContentAbilities;

use ContentAbilities\Abilities\FindPostsAbility;
use ContentAbilities\Abilities\GetPostAbility;
use ContentAbilities\Abilities\CreatePostAbility;
use ContentAbilities\Abilities\UpdatePostAbility;
use ContentAbilities\Abilities\PatchPostAbility;
use ContentAbilities\Abilities\FindTermsAbility;
use ContentAbilities\Abilities\GetTermAbility;
use ContentAbilities\Abilities\CreateTermAbility;
use ContentAbilities\Abilities\UpdateTermAbility;
use ContentAbilities\Abilities\FindMediaAbility;
use ContentAbilities\Abilities\GetMediaAbility;
use ContentAbilities\Abilities\ImportMediaAbility;
use ContentAbilities\Abilities\UpdateMediaAbility;
use ContentAbilities\Abilities\SetFeaturedImageAbility;
use ContentAbilities\Abilities\InsertMediaAbility;

/**
 * Plugin bootstrap orchestrator.
 *
 * Thin WordPress integration layer: converts Abilities API hooks into
 * service-layer registrations.
 */
final class Plugin {

	public const CATEGORY_SLUG = 'content';

	public const VERSION = '1.2.0';

	/**
	 * Registers the ability category (wp_abilities_api_categories_init).
	 */
	public function registerCategory(): void {
		wp_register_ability_category(
			self::CATEGORY_SLUG,
			array(
				'label'       => __( 'Content', 'content-abilities' ),
				'description' => __( 'Abilities for finding, reading, creating, patching, and updating WordPress posts, taxonomies, and media.', 'content-abilities' ),
			)
		);
	}

	/**
	 * Registers all content abilities (wp_abilities_api_init).
	 */
	public function registerAbilities(): void {
		$container = new Container();
		wp_register_ability( 'content/find-posts', $container->make( FindPostsAbility::class )->definition() );
		wp_register_ability( 'content/get-post', $container->make( GetPostAbility::class )->definition() );
		wp_register_ability( 'content/create-post', $container->make( CreatePostAbility::class )->definition() );
		wp_register_ability( 'content/update-post', $container->make( UpdatePostAbility::class )->definition() );
		wp_register_ability( 'content/patch-post', $container->make( PatchPostAbility::class )->definition() );
		wp_register_ability( 'content/find-terms', $container->make( FindTermsAbility::class )->definition() );
		wp_register_ability( 'content/get-term', $container->make( GetTermAbility::class )->definition() );
		wp_register_ability( 'content/create-term', $container->make( CreateTermAbility::class )->definition() );
		wp_register_ability( 'content/update-term', $container->make( UpdateTermAbility::class )->definition() );
		wp_register_ability( 'content/find-media', $container->make( FindMediaAbility::class )->definition() );
		wp_register_ability( 'content/get-media', $container->make( GetMediaAbility::class )->definition() );
		wp_register_ability( 'content/import-media', $container->make( ImportMediaAbility::class )->definition() );
		wp_register_ability( 'content/update-media', $container->make( UpdateMediaAbility::class )->definition() );
		wp_register_ability( 'content/set-featured-image', $container->make( SetFeaturedImageAbility::class )->definition() );
		wp_register_ability( 'content/insert-media', $container->make( InsertMediaAbility::class )->definition() );
	}
}
