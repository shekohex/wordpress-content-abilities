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
use ContentAbilities\Abilities\CreateTermAbility;
use ContentAbilities\Abilities\FindPostsAbility;
use ContentAbilities\Abilities\GetPostAbility;
use ContentAbilities\Abilities\ImportMediaAbility;
use ContentAbilities\Abilities\PatchPostAbility;
use ContentAbilities\Abilities\UpdatePostAbility;
use ContentAbilities\Abilities\UpdateTermAbility;
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
		self::assertInstanceOf( ImportMediaAbility::class, $this->container->make( ImportMediaAbility::class ) );
	}

	public function testRegisteredDefinitionsPointAtCallableCallbacks(): void {
		( new Plugin() )->registerAbilities();

		foreach ( WP_Test_Fixtures::$abilities as $name => $args ) {
			self::assertIsCallable( $args['execute_callback'], $name );
			self::assertIsCallable( $args['permission_callback'], $name );
		}
	}

	public function testRegisteredDefinitionsExplicitlyExposeAbilitiesThroughMcp(): void {
		( new Plugin() )->registerAbilities();

		foreach ( WP_Test_Fixtures::$abilities as $name => $args ) {
			self::assertTrue( $args['meta']['mcp']['public'] ?? false, $name );
			self::assertSame( 'tool', $args['meta']['mcp']['type'] ?? null, $name );
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

	public function testPatchPostAbilityPermissionRequiresEditPost(): void {
		WP_Test_Fixtures::$user_caps = array( 'read' => true );
		$id = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_title'   => 'Foreign',
				'post_content' => 'x',
				'post_status'  => 'publish',
				'post_author'  => 42,
			)
		);

		/** @var PatchPostAbility $ability */
		$ability = $this->container->make( PatchPostAbility::class );
		$denied  = $ability->checkPermissions( array( 'id' => $id ) );

		self::assertTrue( is_wp_error( $denied ) );
		self::assertSame( 'content_forbidden', $denied->get_error_code() );
	}

	public function testCreateTermPermissionRejectsInvalidTaxonomy(): void {
		$ability = $this->container->make( CreateTermAbility::class );

		$result = $ability->checkPermissions( array( 'taxonomy' => 'not-a-taxonomy' ) );

		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'content_invalid_taxonomy', $result->get_error_code() );
	}

	public function testUpdateTermPermissionRejectsInvalidTaxonomy(): void {
		$ability = $this->container->make( UpdateTermAbility::class );

		$result = $ability->checkPermissions( array( 'taxonomy' => 'not-a-taxonomy' ) );

		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'content_invalid_taxonomy', $result->get_error_code() );
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

	public function testMediaAbilitiesExposeStrictMcpPublicMetadataAndSchemas(): void {
		( new Plugin() )->registerAbilities();

		$mediaAbilities = array(
			'content/find-media',
			'content/get-media',
			'content/import-media',
			'content/update-media',
			'content/set-featured-image',
			'content/insert-media',
		);
		foreach ( $mediaAbilities as $name ) {
			self::assertArrayHasKey( $name, WP_Test_Fixtures::$abilities );
			$definition = WP_Test_Fixtures::$abilities[ $name ];
			self::assertTrue( $definition['meta']['public'] );
			self::assertTrue( $definition['meta']['mcp']['public'] );
			self::assertSame( 'tool', $definition['meta']['mcp']['type'] );
			self::assertFalse( $definition['input_schema']['additionalProperties'] ?? true );
		}

		$import = WP_Test_Fixtures::$abilities['content/import-media']['input_schema'];
		self::assertSame( 'uri', $import['properties']['source_url']['format'] );
		self::assertArrayNotHasKey( 'base64', $import['properties'] );
		self::assertArrayNotHasKey( 'path', $import['properties'] );

		$insert = WP_Test_Fixtures::$abilities['content/insert-media']['input_schema'];
		self::assertSame( array( 'append', 'prepend', 'before', 'after', 'replace' ), $insert['properties']['placement']['enum'] );
		self::assertSame( 10000, $insert['properties']['anchor_text']['maxLength'] );
		self::assertSame( array( 'thumbnail', 'medium', 'medium_large', 'large', 'full' ), $insert['properties']['size_slug']['enum'] );
		self::assertSame( array( 'none', 'media', 'attachment' ), $insert['properties']['link_destination']['enum'] );
	}
}
