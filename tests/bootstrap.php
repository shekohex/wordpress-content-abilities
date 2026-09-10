<?php
/**
 * PHPUnit bootstrap: loads Composer autoloader and WordPress function stubs.
 *
 * @package ContentAbilities
 */

declare( strict_types=1 );

require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/stubs/WP_Stubs.php';
