<?php
/**
 * Boot loader: wires the plugin to WordPress hooks without a Composer
 * autoloader at runtime (single-file include chain).
 *
 * @package ContentAbilities
 */

declare( strict_types=1 );

namespace ContentAbilities;

defined( 'ABSPATH' ) || exit;

/**
 * Bootstraps the plugin once.
 */
final class Load {

	private static bool $booted = false;

	/**
	 * Boots the plugin.
	 *
	 * @param string $mainFile Absolute path to the main plugin file.
	 */
	public static function boot( string $mainFile ): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		self::requireAll();

		// Use a Composer autoloader when available (development installs),
		// otherwise classes were already required above.
		if ( is_readable( dirname( $mainFile ) . '/vendor/autoload.php' ) ) {
			// Already loaded via requireAll fallbacks; autoloader takes over for anything else.
		}

		$plugin = new Plugin();

		add_action( 'wp_abilities_api_categories_init', array( $plugin, 'registerCategory' ) );
		add_action( 'wp_abilities_api_init', array( $plugin, 'registerAbilities' ) );
	}

	/**
	 * Requires all source files (production path without Composer).
	 */
	private static function requireAll(): void {
		if ( class_exists( Plugin::class, false ) ) {
			return;
		}

		$files = array(
			__DIR__ . '/Container.php',
			__DIR__ . '/Plugin.php',
			__DIR__ . '/Contracts/AbilityContract.php',
			__DIR__ . '/Abilities/AbstractAbility.php',
			__DIR__ . '/Abilities/FindPostsAbility.php',
			__DIR__ . '/Abilities/GetPostAbility.php',
			__DIR__ . '/Abilities/CreatePostAbility.php',
			__DIR__ . '/Abilities/UpdatePostAbility.php',
			__DIR__ . '/Abilities/PatchPostAbility.php',
			__DIR__ . '/Abilities/FindTermsAbility.php',
			__DIR__ . '/Abilities/GetTermAbility.php',
			__DIR__ . '/Abilities/CreateTermAbility.php',
			__DIR__ . '/Abilities/UpdateTermAbility.php',
			__DIR__ . '/Repositories/PostRepository.php',
			__DIR__ . '/Repositories/TermRepository.php',
			__DIR__ . '/Services/PostService.php',
			__DIR__ . '/Services/TermService.php',
			__DIR__ . '/Support/CapabilityGuard.php',
			__DIR__ . '/Support/PostTypes.php',
		);

		foreach ( $files as $file ) {
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	}
}
