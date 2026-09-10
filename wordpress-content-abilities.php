<?php
/**
 * Plugin bootstrap.
 *
 * @package ContentAbilities
 *
 * @wordpress-plugin
 * Plugin Name: Content Abilities
 * Description: Exposes content abilities (find, get, create, update posts) through the WordPress Abilities API and the official MCP Adapter.
 * Version: 1.0.0
 * Requires at least: 6.9
 * Requires PHP: 8.3
 * Author: shekohex
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: content-abilities
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/src/Load.php';

\ContentAbilities\Load::boot( __FILE__ );
