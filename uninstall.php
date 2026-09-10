<?php
/**
 * Uninstall cleanup for Content Abilities.
 *
 * The plugin stores no options, meta, or custom tables; there is nothing
 * to clean up. This file exists to make that explicit and to guard against
 * accidental data removal on uninstall.
 *
 * @package ContentAbilities
 */

declare( strict_types=1 );

// The plugin is stateless: no options, transients, or post meta are stored,
// so uninstall requires no cleanup.
