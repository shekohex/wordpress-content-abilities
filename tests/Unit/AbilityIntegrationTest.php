<?php
/**
 * Ability-level integration tests: definition wiring, permission callbacks,
 * and execute round-trips through the service layer.
 *
 * @package ContentAbilities\Tests
 */

declare( strict_types=1 );

namespace ContentAbilities\Tests;

use ContentAbilities\Abilities\CreatePostAbility;
use ContentAbilities\Abilities\FindPostsAbility;
use ContentAbilities\Abilities\GetPostAbility;
use ContentAbilities\Abilities\UpdatePostAbility;
use ContentAbilities\Container;
use ContentAbilities\Plugin;
use PHPUnit\Framework\TestCase;
use WP_Test_Fixtures;

final class AbilityIntegrationTest extends TestCase {

	private Container $container;

	protected function setUp(): void {
		parent::setUp();
		WP_Test_Fixtures::reset();
		WP_Test_Fixtures::$current_user_id = 7;
		$this->container                   = new Container();
		( new Plugin() )->registerCategory();
	}

	private function grantEditor(): void {
		WP_Test_Fixtures::$user_caps = array(
			'read'               => true,
			'edit_posts'         => true,
			'edit_others_posts'  => true,
			'publish_posts'      => true,
			'read_private_posts' => true,
		);
	}

	public function testContainerResolvesAbilitiesWithDependencies(): void {
		$ability = $this->container->make( CreatePostAbility::class );

		self::assertInstanceOf( CreatePostAbility::class, $ability );
	}

	public function testRegisteredDefinitionsPointAtCallableCallbacks(): void {
		( new Plugin() )->registerAbilities();

		foreach ( WP_Test_Fixtures::$abilities as $name => $args ) {
			self::assertIsCallable( $args['execute_callback'], $name );
			self::assertIsCallable( $args['permission_callback'], $name );
		}
	}

	public function testFindPostsAbilityExecutesThroughService(): void {
		$this->grantEditor();
		wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_title'   => 'Integration Post',
				'post_content' => 'body',
				'post_status'  => 'publish',
				'post_author'  => 7,
			)
		);

		/** @var FindPostsAbility $ability */
		$ability = $this->container->make( FindPostsAbility::class );
		$result  = $ability->execute( array( 'search' => 'integration' ) );

		self::assertIsArray( $result );
		self::assertCount( 1, $result['items'] );
		self::assertSame( 'Integration Post', $result['items'][0]['title'] );
	}

	public function testFindPostsPermissionDeniesDraftsForUnprivilegedUsers(): void {
		WP_Test_Fixtures::$user_caps = array( 'read' => true );

		/** @var FindPostsAbility $ability */
		$ability = $this->container->make( FindPostsAbility::class );

		self::assertTrue( $ability->checkPermissions( array( 'include_drafts' => false ) ) );
		$denied = $ability->checkPermissions( array( 'include_drafts' => true ) );
		self::assertTrue( is_wp_error( $denied ) );
		self::assertSame( 'content_forbidden', $denied->get_error_code() );
	}

	public function testFindPostsPermissionDeniesDraftsForAuthorsWithoutEditOthers(): void {
		WP_Test_Fixtures::$user_caps = array(
			'read'       => true,
			'edit_posts' => true,
		);

		/** @var FindPostsAbility $ability */
		$ability = $this->container->make( FindPostsAbility::class );
		$denied  = $ability->checkPermissions( array( 'include_drafts' => true ) );

		self::assertTrue( is_wp_error( $denied ) );
		self::assertSame( 'content_forbidden', $denied->get_error_code() );
	}

	public function testGetPostAbilityPermissionChecksObjectRead(): void {
		$this->grantEditor();
		$id = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_title'   => 'Private',
				'post_content' => 'x',
				'post_status'  => 'private',
				'post_author'  => 42,
			)
		);
		self::assertNotInstanceOf( \WP_Error::class, $id );

		/** @var GetPostAbility $ability */
		$ability = $this->container->make( GetPostAbility::class );

		// Editor can read others' private posts.
		self::assertTrue( $ability->checkPermissions( array( 'id' => $id ) ) );

		// Subscriber cannot.
		WP_Test_Fixtures::$user_caps = array( 'read' => true );
		$denied = $ability->checkPermissions( array( 'id' => $id ) );
		self::assertTrue( is_wp_error( $denied ) );
	}

	public function testCreatePostAbilityRoundTrip(): void {
		$this->grantEditor();

		/** @var CreatePostAbility $ability */
		$ability = $this->container->make( CreatePostAbility::class );
		self::assertTrue( $ability->checkPermissions( array() ) );

		$result = $ability->execute(
			array(
				'title'   => 'Via Ability',
				'content' => 'Created through the ability layer.',
				'status'  => 'publish',
			)
		);

		self::assertIsArray( $result );
		self::assertSame( 'publish', $result['status'] );
		self::assertGreaterThan( 0, $result['id'] );
	}

	public function testUpdatePostAbilityPermissionRejectsForeignPostForAuthor(): void {
		WP_Test_Fixtures::$user_caps = array(
			'read'       => true,
			'edit_posts' => true,
		);
		$id = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_title'   => 'Foreign',
				'post_content' => 'x',
				'post_status'  => 'publish',
				'post_author'  => 42,
			)
		);

		/** @var UpdatePostAbility $ability */
		$ability = $this->container->make( UpdatePostAbility::class );

		$denied = $ability->checkPermissions( array( 'id' => $id ) );
		self::assertTrue( is_wp_error( $denied ) );
		self::assertSame( 'content_forbidden', $denied->get_error_code() );
	}

	public function testAbilitySchemasAreValidJsonSchemaObjects(): void {
		( new Plugin() )->registerAbilities();

		foreach ( WP_Test_Fixtures::$abilities as $name => $args ) {
			foreach ( array( 'input_schema', 'output_schema' ) as $key ) {
				$schema = $args[ $key ];
				self::assertIsArray( $schema, "$name $key" );
				self::assertArrayHasKey( 'type', $schema, "$name $key must declare a type" );
				self::assertSame( 'object', $schema['type'], "$name $key must be an object schema" );
				self::assertArrayHasKey( 'properties', $schema, "$name $key must declare properties" );
			}
		}
	}
}
