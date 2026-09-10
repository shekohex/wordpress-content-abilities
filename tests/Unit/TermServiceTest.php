<?php
/**
 * TermService behavior tests.
 *
 * @package ContentAbilities\Tests
 */

declare( strict_types=1 );

namespace ContentAbilities\Tests;

use ContentAbilities\Repositories\TermRepository;
use ContentAbilities\Services\TermService;
use ContentAbilities\Support\CapabilityGuard;
use PHPUnit\Framework\TestCase;
use WP_Test_Fixtures;

final class TermServiceTest extends TestCase {

	private TermService $service;

	protected function setUp(): void {
		parent::setUp();
		WP_Test_Fixtures::reset();
		$this->service = new TermService(
			new TermRepository(),
			new CapabilityGuard(),
		);
	}

	public function testFindTermsListsAndSearchesRequestedTaxonomy(): void {
		wp_insert_term( 'News', 'category' );
		wp_insert_term( 'Releases', 'category' );
		wp_insert_term( 'Newsworthy', 'post_tag' );

		$result = $this->service->findTerms(
			array( 'taxonomy' => 'category', 'search' => 'news' )
		);

		self::assertIsArray( $result );
		self::assertCount( 1, $result['items'] );
		self::assertSame( 'News', $result['items'][0]['name'] );
		self::assertSame( 'category', $result['items'][0]['taxonomy'] );
	}

	public function testGetTermReturnsOneTerm(): void {
		$created = wp_insert_term( 'WordPress', 'post_tag' );
		self::assertNotInstanceOf( \WP_Error::class, $created );

		$result = $this->service->getTerm(
			array( 'taxonomy' => 'post_tag', 'id' => (int) $created->term_id )
		);

		self::assertIsArray( $result );
		self::assertSame( 'WordPress', $result['name'] );
		self::assertSame( 'post_tag', $result['taxonomy'] );
	}

	public function testCreateTermUsesTaxonomyEditCapability(): void {
		WP_Test_Fixtures::$user_caps = array( 'manage_categories' => true );

		$category = $this->service->createTerm(
			array( 'taxonomy' => 'category', 'name' => 'Guides' )
		);
		$tag = $this->service->createTerm(
			array( 'taxonomy' => 'post_tag', 'name' => 'agent' )
		);

		self::assertIsArray( $category );
		self::assertTrue( is_wp_error( $tag ) );
		self::assertSame( 'content_forbidden', $tag->get_error_code() );
	}

	public function testCreateTermPropagatesWordPressError(): void {
		WP_Test_Fixtures::$user_caps = array( 'manage_categories' => true );
		wp_insert_term( 'Existing', 'category' );

		$result = $this->service->createTerm(
			array( 'taxonomy' => 'category', 'name' => 'Existing' )
		);

		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'term_exists', $result->get_error_code() );
	}

	public function testCreateTermPreservesLiteralBackslashesInNameAndDescription(): void {
		WP_Test_Fixtures::$user_caps = array( 'manage_categories' => true );
		$name        = 'C:\\WordPress \\LaTeX';
		$description = 'Path: C:\\Program Files; formula: \\frac{x}{y}';

		$result = $this->service->createTerm(
			array(
				'taxonomy'    => 'category',
				'name'        => $name,
				'description' => $description,
			)
		);

		self::assertIsArray( $result );
		self::assertSame( $name, $result['name'] );
		self::assertSame( $description, $result['description'] );
	}

	public function testUpdateTermUsesTaxonomyEditCapabilityAndSanitizesFields(): void {
		WP_Test_Fixtures::$user_caps = array( 'manage_post_tags' => true );
		$created = wp_insert_term( 'Old', 'post_tag' );
		self::assertNotInstanceOf( \WP_Error::class, $created );

		$result = $this->service->updateTerm(
			array(
				'taxonomy'  => 'post_tag',
				'id'        => (int) $created->term_id,
				'name'      => 'New <b>Name</b>',
				'description' => '<script>bad()</script><p>Safe</p>',
			)
		);

		self::assertIsArray( $result );
		self::assertSame( 'New Name', $result['name'] );
		self::assertSame( '<p>Safe</p>', $result['description'] );
	}

	public function testUpdateTermPreservesLiteralBackslashesInNameAndDescription(): void {
		WP_Test_Fixtures::$user_caps = array( 'manage_post_tags' => true );
		$created = wp_insert_term( 'Old', 'post_tag' );
		self::assertNotInstanceOf( \WP_Error::class, $created );
		$name        = 'C:\\WordPress \\LaTeX';
		$description = 'Path: C:\\Program Files; formula: \\frac{x}{y}';

		$result = $this->service->updateTerm(
			array(
				'taxonomy'    => 'post_tag',
				'id'          => (int) $created->term_id,
				'name'        => $name,
				'description' => $description,
			)
		);

		self::assertIsArray( $result );
		self::assertSame( $name, $result['name'] );
		self::assertSame( $description, $result['description'] );
	}

	public function testGetUnknownTermReturnsStructuredError(): void {
		$result = $this->service->getTerm(
			array( 'taxonomy' => 'category', 'id' => 999 )
		);

		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'content_term_not_found', $result->get_error_code() );
	}
}
