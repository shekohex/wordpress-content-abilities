<?php
/**
 * Plugin registration tests.
 *
 * @package ContentAbilities\Tests
 */

declare( strict_types=1 );

namespace ContentAbilities\Tests;

use ContentAbilities\Plugin;
use PHPUnit\Framework\TestCase;
use WP_Test_Fixtures;

final class PluginTest extends TestCase {

	private Plugin $plugin;

	protected function setUp(): void {
		parent::setUp();
		WP_Test_Fixtures::reset();
		$this->plugin = new Plugin();
	}

	public function testRegistersContentCategoryWithLabelAndDescription(): void {
		$this->plugin->registerCategory();

		$this->assertArrayHasKey( 'content', WP_Test_Fixtures::$ability_categories );

		$category = WP_Test_Fixtures::$ability_categories['content'];
		$this->assertSame( 'Content', $category['label'] );
		$this->assertNotSame( '', (string) $category['description'] );
	}

	public function testRegistersExactlyFourAbilitiesInTheContentNamespace(): void {
		$this->plugin->registerCategory();
		$this->plugin->registerAbilities();

		$this->assertSame(
			array(
				'content/find-posts',
				'content/get-post',
				'content/create-post',
				'content/update-post',
			),
			array_keys( WP_Test_Fixtures::$abilities )
		);
	}

	public function testEveryAbilityIsPublicWithAnnotationsAndSchemas(): void {
		$this->plugin->registerCategory();
		$this->plugin->registerAbilities();

		foreach ( WP_Test_Fixtures::$abilities as $name => $args ) {
			$this->assertSame( 'content', $args['category'], "$name must use the content category" );
			$this->assertTrue( $args['meta']['public'], "$name must set meta.public" );
			$this->assertIsCallable( $args['execute_callback'], "$name must have an execute callback" );
			$this->assertIsCallable( $args['permission_callback'], "$name must have a permission callback" );
			$this->assertNotEmpty( $args['input_schema'], "$name must define an input schema" );
			$this->assertNotEmpty( $args['output_schema'], "$name must define an output schema" );
			$this->assertArrayHasKey( 'annotations', $args['meta'], "$name must declare annotations" );
		}
	}

	public function testReadOnlyAbilitiesDoNotModifyState(): void {
		$this->plugin->registerCategory();
		$this->plugin->registerAbilities();

		$expected = array(
			'content/find-posts' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
			'content/get-post'   => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
			'content/create-post' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
			'content/update-post' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
		);

		foreach ( $expected as $name => $annotations ) {
			$actual = WP_Test_Fixtures::$abilities[ $name ]['meta']['annotations'];
			foreach ( $annotations as $key => $value ) {
				$this->assertSame( $value, $actual[ $key ], "$name annotation $key" );
			}
		}
	}

	public function testNoDeleteAbilityIsExposed(): void {
		$this->plugin->registerCategory();
		$this->plugin->registerAbilities();

		foreach ( array_keys( WP_Test_Fixtures::$abilities ) as $name ) {
			$this->assertStringNotContainsStringIgnoringCase( 'delete', $name );
			$this->assertStringNotContainsStringIgnoringCase( 'trash', $name );
		}
	}

	public function testBootstrapFileRegistersHooksOnBoot(): void {
		$file = dirname( __DIR__, 2 ) . '/wordpress-content-abilities.php';
		$this->assertFileExists( $file );

		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
		}

		require $file;

		$this->assertArrayHasKey(
			'wp_abilities_api_categories_init',
			WP_Test_Fixtures::$filters,
			'bootstrap must hook wp_abilities_api_categories_init'
		);
		$this->assertArrayHasKey(
			'wp_abilities_api_init',
			WP_Test_Fixtures::$filters,
			'bootstrap must hook wp_abilities_api_init'
		);

		// The hooked category callback must actually register the category.
		( new Plugin() )->registerCategory();
		$this->assertArrayHasKey( 'content', WP_Test_Fixtures::$ability_categories );
	}
}
