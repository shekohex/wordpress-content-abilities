<?php
/**
 * Ability contract: an ability definition plus execute/permission behavior.
 *
 * @package ContentAbilities
 */

declare( strict_types=1 );

namespace ContentAbilities\Contracts;

/**
 * A unit of plugin functionality exposed through the Abilities API.
 */
interface AbilityContract {

	/**
	 * Fully-qualified ability name, e.g. "content/find-posts".
	 *
	 * @return non-empty-lowercase-string
	 */
	public static function name(): string;

	/**
	 * The wp_register_ability() arguments array.
	 *
	 * @return array<string, mixed>
	 */
	public function definition(): array;

	/**
	 * Permission check invoked by the Abilities API.
	 *
	 * @param mixed $input Input matching the ability's input schema.
	 * @return bool|\WP_Error
	 */
	public function checkPermissions( mixed $input ): bool|\WP_Error;

	/**
	 * Executes the ability with validated input.
	 *
	 * @param mixed $input Input matching the ability's input schema.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute( mixed $input ): array|\WP_Error;
}
