<?php
/**
 * PostService behavior tests: find/get/create/update with permissions,
 * sanitization, and structured errors.
 *
 * @package ContentAbilities\Tests
 */

declare( strict_types=1 );

namespace ContentAbilities\Tests;

use ContentAbilities\Repositories\PostRepository;
use ContentAbilities\Services\PostService;
use ContentAbilities\Support\CapabilityGuard;
use PHPUnit\Framework\TestCase;
use WP_Test_Fixtures;

final class PostServiceTest extends TestCase {

	private PostService $service;

	protected function setUp(): void {
		parent::setUp();
		WP_Test_Fixtures::reset();
		WP_Test_Fixtures::$current_user_id = 7;
		$this->service                     = new PostService(
			new PostRepository(),
			new CapabilityGuard(),
		);
	}

	private function grantEditor(): void {
		WP_Test_Fixtures::$user_caps = array(
			'read'                => true,
			'edit_posts'          => true,
			'edit_others_posts'   => true,
			'publish_posts'       => true,
			'edit_pages'          => true,
			'edit_others_pages'   => true,
			'publish_pages'       => true,
			'read_private_posts'  => true,
		);
	}

	private function grantAuthor(): void {
		WP_Test_Fixtures::$user_caps = array(
			'read'              => true,
			'edit_posts'        => true,
			'publish_posts'     => false,
			'edit_others_posts' => false,
		);
	}

	private function grantSubscriber(): void {
		WP_Test_Fixtures::$user_caps = array( 'read' => true );
	}

	private function makePost( string $title, string $status = 'publish', string $type = 'post', int $author = 7 ): int {
		$id = wp_insert_post(
			array(
				'post_type'    => $type,
				'post_title'   => $title,
				'post_content' => "Content of {$title}",
				'post_status'  => $status,
				'post_author'  => $author,
			)
		);
		if ( is_wp_error( $id ) ) {
			self::fail( 'fixture: ' . $id->get_error_message() );
		}
		return $id;
	}

	/* -----------------------------------------------------------------
	 * findPosts.
	 * ------------------------------------------------------------------ */

	public function testFindReturnsOnlyPublishedPostsByDefault(): void {
		$this->grantSubscriber();
		$this->makePost( 'Published One', 'publish' );
		$this->makePost( 'Draft One', 'draft' );

		$result = $this->service->findPosts( array() );

		self::assertIsArray( $result );
		self::assertCount( 1, $result['items'] );
		self::assertSame( 'Published One', $result['items'][0]['title'] );
		self::assertSame( 1, $result['page'] );
		self::assertSame( 10, $result['per_page'] );
	}

	public function testFindSearchFiltersByTerm(): void {
		$this->grantSubscriber();
		$this->makePost( 'Hello World' );
		$this->makePost( 'Different Topic' );

		$result = $this->service->findPosts( array( 'search' => 'hello' ) );

		self::assertIsArray( $result );
		self::assertCount( 1, $result['items'] );
		self::assertSame( 'Hello World', $result['items'][0]['title'] );
	}

	public function testFindPaginates(): void {
		$this->grantSubscriber();
		$this->makePost( 'A' );
		$this->makePost( 'B' );
		$this->makePost( 'C' );

		$page2 = $this->service->findPosts( array( 'per_page' => 2, 'page' => 2 ) );

		self::assertIsArray( $page2 );
		self::assertCount( 1, $page2['items'] );
	}

	public function testFindWithDraftsIncludesDraftsForEditors(): void {
		$this->grantEditor();
		$this->makePost( 'Published', 'publish' );
		$this->makePost( 'Secret Draft', 'draft' );

		$result = $this->service->findPosts( array( 'include_drafts' => true ) );

		self::assertIsArray( $result );
		self::assertCount( 2, $result['items'] );
	}

	public function testFindWithDraftsRejectsAuthorsWithoutEditOthersCapability(): void {
		$this->grantAuthor();
		$this->makePost( 'Own Draft', 'draft', 'post', 7 );
		$this->makePost( 'Another Author Draft', 'draft', 'post', 42 );

		$result = $this->service->findPosts( array( 'include_drafts' => true ) );

		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'content_forbidden', $result->get_error_code() );
	}

	public function testFindRejectsNonPublicPostType(): void {
		$this->grantEditor();

		$result = $this->service->findPosts( array( 'post_type' => 'not-a-type' ) );

		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'content_invalid_post_type', $result->get_error_code() );
	}

	/* -----------------------------------------------------------------
	 * getPost.
	 * ------------------------------------------------------------------ */

	public function testGetPostReturnsFullPostWithTerms(): void {
		$this->grantEditor();
		$id = $this->makePost( 'Rich Post' );
		wp_set_post_terms( $id, array( 'News', 'Tech' ), 'category' );
		wp_set_post_terms( $id, array( 'ai' ), 'post_tag' );

		$result = $this->service->getPost( $id );

		self::assertIsArray( $result );
		self::assertSame( $id, $result['id'] );
		self::assertSame( 'Rich Post', $result['title'] );
		self::assertSame( array( 'News', 'Tech' ), $result['categories'] );
		self::assertSame( array( 'ai' ), $result['tags'] );
		self::assertArrayHasKey( 'link', $result );
	}

	public function testGetPostUnknownIdReturnsStructuredError(): void {
		$this->grantEditor();

		$result = $this->service->getPost( 9999 );

		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'content_post_not_found', $result->get_error_code() );
	}

	public function testGetPostPrivatePostRequiresReadPermission(): void {
		$this->grantSubscriber();
		$id = $this->makePost( 'Private Post', 'private', 'post', 42 );

		$result = $this->service->getPost( $id );

		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'content_forbidden', $result->get_error_code() );
	}

	public function testGetPostPrivatePostAllowedForEditor(): void {
		$this->grantEditor();
		$id = $this->makePost( 'Private Post', 'private', 'post', 99 );

		$result = $this->service->getPost( $id );

		self::assertIsArray( $result );
		self::assertSame( 'private', $result['status'] );
	}

	/* -----------------------------------------------------------------
	 * createPost.
	 * ------------------------------------------------------------------ */

	public function testCreatePostDefaultsToDraftAndAssignsAuthor(): void {
		$this->grantAuthor();

		$result = $this->service->createPost(
			array( 'title' => 'New Post', 'content' => 'Body text.' )
		);

		self::assertIsArray( $result );
		self::assertSame( 'draft', $result['status'] );
		self::assertSame( 7, WP_Test_Fixtures::$posts[ $result['id'] ]->post_author );
	}

	public function testCreatePostPublishRequiresPublishCapability(): void {
		$this->grantAuthor(); // No publish_posts.

		$result = $this->service->createPost(
			array( 'title' => 'Want Publish', 'status' => 'publish' )
		);

		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'content_forbidden', $result->get_error_code() );
	}

	public function testCreatePostPublishAllowedWithCapability(): void {
		$this->grantEditor();

		$result = $this->service->createPost(
			array( 'title' => 'Publish Me', 'status' => 'publish' )
		);

		self::assertIsArray( $result );
		self::assertSame( 'publish', $result['status'] );
	}

	public function testCreatePostRejectsSubscriber(): void {
		$this->grantSubscriber();

		$result = $this->service->createPost( array( 'title' => 'Nope' ) );

		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'content_forbidden', $result->get_error_code() );
	}

	public function testCreatePostRejectsNonPublicPostType(): void {
		$this->grantEditor();

		$result = $this->service->createPost(
			array( 'title' => 'Hidden', 'post_type' => 'nav_menu_item' )
		);

		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'content_invalid_post_type', $result->get_error_code() );
	}

	public function testCreatePostRequiresSomeContent(): void {
		$this->grantEditor();

		$result = $this->service->createPost( array() );

		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'content_empty_content', $result->get_error_code() );
	}

	public function testCreatePostSanitizesTitleAndStripsScripts(): void {
		$this->grantEditor();

		$result = $this->service->createPost(
			array(
				'title'   => "  Hello <b>World</b> \n",
				'content' => '<p>Safe</p><script>alert(1)</script>',
			)
		);

		self::assertIsArray( $result );
		self::assertSame( 'Hello World', $result['title'] );
		self::assertSame( '<p>Safe</p>', $result['content'] );
	}

	public function testCreatePostAssignsCategoriesAndTags(): void {
		$this->grantEditor();

		$result = $this->service->createPost(
			array(
				'title'      => 'Categorized',
				'categories' => array( 'News', 'Tech' ),
				'tags'       => array( 'ai', 'wordpress' ),
			)
		);

		self::assertIsArray( $result );
		self::assertSame( array( 'News', 'Tech' ), $result['categories'] );
		self::assertSame( array( 'ai', 'wordpress' ), $result['tags'] );
	}

	/* -----------------------------------------------------------------
	 * updatePost.
	 * ------------------------------------------------------------------ */

	public function testUpdatePostChangesFieldsForEditor(): void {
		$this->grantEditor();
		$id = $this->makePost( 'Old Title', 'draft', 'post', 99 );

		$result = $this->service->updatePost(
			array( 'id' => $id, 'title' => 'New Title' )
		);

		self::assertIsArray( $result );
		self::assertSame( 'New Title', $result['title'] );
		// Status untouched when not provided.
		self::assertSame( 'draft', $result['status'] );
	}

	public function testUpdatePostAuthorCanEditOwnDraftWithoutPublishCap(): void {
		$this->grantAuthor();
		$id = $this->makePost( 'Own Draft', 'draft', 'post', 7 ); // Own post.

		$result = $this->service->updatePost( array( 'id' => $id, 'title' => 'Edited Draft' ) );

		self::assertIsArray( $result );
		self::assertSame( 'Edited Draft', $result['title'] );
	}

	public function testUpdatePostOtherAuthorsPostRequiresEditOthers(): void {
		$this->grantAuthor(); // No edit_others_posts.
		$id = $this->makePost( 'Foreign Post', 'publish', 'post', 42 );

		$result = $this->service->updatePost( array( 'id' => $id, 'title' => 'Hijack' ) );

		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'content_forbidden', $result->get_error_code() );
	}

	public function testUpdatePostPublishingDraftRequiresPublishCapability(): void {
		$this->grantAuthor();
		$id = $this->makePost( 'Own Draft', 'draft', 'post', 7 );

		$result = $this->service->updatePost( array( 'id' => $id, 'status' => 'publish' ) );

		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'content_forbidden', $result->get_error_code() );
	}

	public function testUpdatePostPublishAllowedWithCapability(): void {
		$this->grantEditor();
		$id = $this->makePost( 'Draft', 'draft', 'post', 99 );

		$result = $this->service->updatePost( array( 'id' => $id, 'status' => 'publish' ) );

		self::assertIsArray( $result );
		self::assertSame( 'publish', $result['status'] );
	}

	public function testUpdatePostKeepsPublishedStatusWithoutRepublishCheck(): void {
		// An editor without publish cap editing an ALREADY published post
		// must not trip the publish check (status unchanged).
		WP_Test_Fixtures::$user_caps = array(
			'read'              => true,
			'edit_posts'        => true,
			'edit_others_posts' => true,
		);
		$id = $this->makePost( 'Live Post', 'publish', 'post', 42 );

		$result = $this->service->updatePost( array( 'id' => $id, 'title' => 'Still Live' ) );

		self::assertIsArray( $result );
		self::assertSame( 'publish', $result['status'] );
		self::assertSame( 'Still Live', $result['title'] );
	}

	public function testUpdatePostUnknownIdReturnsStructuredError(): void {
		$this->grantEditor();

		$result = $this->service->updatePost( array( 'id' => 404, 'title' => 'Ghost' ) );

		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'content_post_not_found', $result->get_error_code() );
	}

	public function testUpdatePostReplacesCategories(): void {
		$this->grantEditor();
		$id = $this->makePost( 'Cat Post' );
		wp_set_post_terms( $id, array( 'Old' ), 'category' );

		$result = $this->service->updatePost(
			array( 'id' => $id, 'categories' => array( 'New' ) )
		);

		self::assertIsArray( $result );
		self::assertSame( array( 'New' ), $result['categories'] );
	}

	public function testUpdatePostContentIsSanitized(): void {
		$this->grantEditor();
		$id = $this->makePost( 'Target' );

		$result = $this->service->updatePost(
			array( 'id' => $id, 'content' => '<p>ok</p><script>evil()</script><p onclick="x()">t</p>' )
		);

		self::assertIsArray( $result );
		self::assertSame( '<p>ok</p><p>t</p>', $result['content'] );
	}
}
