<?php
/**
 * MediaService behavior tests.
 *
 * @package ContentAbilities\Tests
 */

declare( strict_types=1 );

namespace ContentAbilities\Tests;

use ContentAbilities\Repositories\MediaRepository;
use ContentAbilities\Repositories\PostRepository;
use ContentAbilities\Services\MediaService;
use ContentAbilities\Support\CapabilityGuard;
use PHPUnit\Framework\TestCase;
use WP_Error;
use WP_Test_Fixtures;

final class MediaServiceTest extends TestCase {

	private MediaService $service;

	protected function setUp(): void {
		parent::setUp();
		WP_Test_Fixtures::reset();
		WP_Test_Fixtures::$current_user_id = 7;
		WP_Test_Fixtures::$user_caps        = array(
			'read'              => true,
			'edit_posts'        => true,
			'edit_others_posts' => true,
			'upload_files'      => true,
		);
		$this->service = new MediaService(
			new MediaRepository(),
			new PostRepository(),
			new CapabilityGuard(),
		);
	}

	private function makePost( string $content = 'Post body' ): int {
		$id = wp_insert_post(
			array(
				'post_title'   => 'Target post',
				'post_content' => $content,
				'post_status'  => 'draft',
				'post_author'  => 7,
			)
		);
		self::assertIsInt( $id );
		return $id;
	}

	private function makeImage( string $title = 'Test image', int $parentId = 0 ): int {
		$id = wp_test_insert_attachment(
			array(
				'post_title'     => $title,
				'post_excerpt'   => 'Caption',
				'post_content'   => 'Description',
				'post_mime_type' => 'image/jpeg',
				'post_parent'    => $parentId,
			),
			array(
				'width'    => 1200,
				'height'   => 800,
				'filesize' => 123456,
				'sizes'    => array(
					'medium' => array(
						'file'      => 'test-300x200.jpg',
						'width'     => 300,
						'height'    => 200,
						'mime-type' => 'image/jpeg',
						'filesize'  => 12345,
					),
				),
			),
			'https://example.com/uploads/test.jpg',
			'/tmp/test.jpg'
		);
		update_post_meta( $id, '_wp_attachment_image_alt', 'Useful alt' );
		return $id;
	}

	public function testFindMediaReturnsStableMetadataAndFiltersUnreadableAttachments(): void {
		$visible = $this->makeImage( 'Visible' );
		$hidden  = $this->makeImage( 'Hidden' );
		WP_Test_Fixtures::$object_caps['read_post'][ $hidden ] = false;

		$result = $this->service->findMedia(
			array(
				'search'    => 'i',
				'mime_type' => 'image',
				'page'      => 1,
				'per_page'  => 20,
			)
		);

		self::assertIsArray( $result );
		self::assertSame( array( $visible ), array_column( $result['items'], 'id' ) );
		self::assertSame( 1, $result['total'] );
		self::assertSame( 'image/jpeg', $result['items'][0]['mime_type'] );
		self::assertSame( 'image', $result['items'][0]['media_type'] );
		self::assertSame( 1200, $result['items'][0]['width'] );
		self::assertSame( 800, $result['items'][0]['height'] );
		self::assertSame( 123456, $result['items'][0]['filesize'] );
		self::assertSame( 300, $result['items'][0]['sizes']['medium']['width'] );
	}

	public function testFindMediaBoundsPaginationAndRejectsInvalidMimeFilter(): void {
		$result = $this->service->findMedia( array( 'page' => 0, 'per_page' => 51 ) );
		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'content_invalid_pagination', $result->get_error_code() );

		$result = $this->service->findMedia( array( 'mime_type' => 'text/html; charset=utf-8' ) );
		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'content_invalid_mime_type', $result->get_error_code() );
	}

	public function testFindMediaTotalAndPageCountOnlyReadableResults(): void {
		$first  = $this->makeImage( 'First' );
		$hidden = $this->makeImage( 'Hidden' );
		$last   = $this->makeImage( 'Last' );
		WP_Test_Fixtures::$object_caps['read_post'][ $hidden ] = false;

		$result = $this->service->findMedia( array( 'page' => 2, 'per_page' => 1 ) );

		self::assertIsArray( $result );
		self::assertSame( array( $last ), array_column( $result['items'], 'id' ) );
		self::assertSame( 2, $result['total'] );
	}

	public function testFindMediaOmitsAttachmentWithoutUrl(): void {
		$id = $this->makeImage();
		WP_Test_Fixtures::$attachment_urls[ $id ] = '';

		$result = $this->service->findMedia( array( 'per_page' => 20 ) );

		self::assertIsArray( $result );
		self::assertSame( array(), $result['items'] );
		self::assertSame( 0, $result['total'] );
	}

	public function testGetMediaRequiresObjectReadPermission(): void {
		$id = $this->makeImage();
		WP_Test_Fixtures::$object_caps['read_post'][ $id ] = false;

		$result = $this->service->getMedia( $id );

		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'content_forbidden', $result->get_error_code() );
	}

	public function testGetMediaRejectsNonAttachment(): void {
		$result = $this->service->getMedia( $this->makePost() );

		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'content_media_not_found', $result->get_error_code() );
	}

	public function testGetMediaRejectsAttachmentWithoutPublicUrl(): void {
		$id = $this->makeImage();
		WP_Test_Fixtures::$attachment_urls[ $id ] = '';

		$result = $this->service->getMedia( $id );

		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'content_media_unavailable', $result->get_error_code() );
	}

	public function testImportMediaRequiresUploadFilesAndParentEditPermission(): void {
		WP_Test_Fixtures::$user_caps['upload_files'] = false;
		$result = $this->service->importMedia( array( 'source_url' => 'https://cdn.example.com/image.jpg' ) );
		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'content_forbidden', $result->get_error_code() );

		WP_Test_Fixtures::$user_caps['upload_files'] = true;
		$postId = $this->makePost();
		WP_Test_Fixtures::$object_caps['edit_post'][ $postId ] = false;
		$result = $this->service->importMedia(
			array(
				'source_url'       => 'https://cdn.example.com/image.jpg',
				'attach_to_post_id' => $postId,
			)
		);
		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'content_forbidden', $result->get_error_code() );
	}

	public function testImportMediaRejectsNonHttpsAndUnsafeUrlsBeforeHttp(): void {
		$result = $this->service->importMedia( array( 'source_url' => 'http://cdn.example.com/image.jpg' ) );
		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'content_invalid_source_url', $result->get_error_code() );

		$result = $this->service->importMedia( array( 'source_url' => 'https://127.0.0.1/image.jpg' ) );
		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'content_invalid_source_url', $result->get_error_code() );
		self::assertSame( 0, WP_Test_Fixtures::$http_request_count );
	}

	public function testImportMediaUsesSafeStreamingHttpAndPropagatesHttpErrors(): void {
		WP_Test_Fixtures::$remote_response = new WP_Error( 'http_request_failed', 'Network failed.' );

		$result = $this->service->importMedia( array( 'source_url' => 'https://cdn.example.com/image.jpg' ) );

		self::assertSame( WP_Test_Fixtures::$remote_response, $result );
		self::assertTrue( WP_Test_Fixtures::$last_http_args['stream'] );
		self::assertTrue( WP_Test_Fixtures::$last_http_args['reject_unsafe_urls'] );
		self::assertSame( 3, WP_Test_Fixtures::$last_http_args['redirection'] );
		self::assertLessThanOrEqual( 15, WP_Test_Fixtures::$last_http_args['timeout'] );
		self::assertSame( MediaRepository::MAX_IMPORT_BYTES + 1, WP_Test_Fixtures::$last_http_args['limit_response_size'] );
		self::assertSame( array(), WP_Test_Fixtures::$temporary_files );
	}

	public function testImportMediaRejectsHttpsToHttpRedirectAndCleansUpRedirectGuard(): void {
		WP_Test_Fixtures::$remote_response = array(
			'redirects' => array( 'http://cdn.example.com/insecure.jpg' ),
			'response'  => array( 'code' => 200 ),
			'headers'   => array(),
			'body'      => "\xFF\xD8\xFFjpeg",
		);

		$result = $this->service->importMedia( array( 'source_url' => 'https://cdn.example.com/image.jpg' ) );

		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'http_request_failed', $result->get_error_code() );
		self::assertArrayNotHasKey( 'requests-before_redirect', WP_Test_Fixtures::$filters );
	}

	public function testImportMediaAllowsAllHttpsRedirectsAndCleansUpRedirectGuard(): void {
		WP_Test_Fixtures::$remote_response = array(
			'redirects' => array(
				'https://cdn.example.com/step-two.jpg',
				'https://cdn.example.com/final.jpg',
			),
			'response'  => array( 'code' => 200 ),
			'headers'   => array(),
			'body'      => "\xFF\xD8\xFFjpeg",
		);

		$result = $this->service->importMedia( array( 'source_url' => 'https://cdn.example.com/image.jpg' ) );

		self::assertIsArray( $result );
		self::assertArrayNotHasKey( 'requests-before_redirect', WP_Test_Fixtures::$filters );
	}

	public function testImportMediaRejectsHttpStatusOversizeTruncationAndMimeSpoofingWithCleanup(): void {
		$cases = array(
			'http status' => array(
				'response' => array( 'response' => array( 'code' => 404 ), 'headers' => array(), 'body' => 'not found' ),
				'code'     => 'content_media_http_error',
			),
			'declared oversize' => array(
				'response' => array( 'response' => array( 'code' => 200 ), 'headers' => array( 'content-length' => (string) ( MediaRepository::MAX_IMPORT_BYTES + 1 ) ), 'body' => '' ),
				'code'     => 'content_media_too_large',
			),
			'actual oversize' => array(
				'response' => array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => str_repeat( 'x', MediaRepository::MAX_IMPORT_BYTES + 1 ) ),
				'code'     => 'content_media_too_large',
			),
			'truncated' => array(
				'response' => array( 'response' => array( 'code' => 200 ), 'headers' => array( 'content-length' => '100' ), 'body' => 'short' ),
				'code'     => 'content_media_incomplete',
			),
			'mime spoof' => array(
				'response' => array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => 'plain text pretending to be an image' ),
				'code'     => 'content_invalid_media_type',
			),
		);

		foreach ( $cases as $label => $case ) {
			WP_Test_Fixtures::$remote_response = $case['response'];
			$result = $this->service->importMedia( array( 'source_url' => 'https://cdn.example.com/image.jpg' ) );
			self::assertTrue( is_wp_error( $result ), $label );
			self::assertSame( $case['code'], $result->get_error_code(), $label );
			self::assertSame( array(), WP_Test_Fixtures::$temporary_files, $label );
		}
	}

	public function testImportMediaCleansUpAndPropagatesSideloadError(): void {
		WP_Test_Fixtures::$remote_response      = array(
			'response' => array( 'code' => 200 ),
			'headers'  => array(),
			'body'     => "\xFF\xD8\xFFjpeg",
		);
		WP_Test_Fixtures::$media_sideload_error = new WP_Error( 'upload_error', 'Upload failed.' );

		$result = $this->service->importMedia( array( 'source_url' => 'https://cdn.example.com/image.jpg' ) );

		self::assertSame( WP_Test_Fixtures::$media_sideload_error, $result );
		self::assertSame( array(), WP_Test_Fixtures::$temporary_files );
	}

	public function testImportMediaPersistsMetadataAndOptionalParent(): void {
		$postId = $this->makePost();
		WP_Test_Fixtures::$remote_response = array(
			'response' => array( 'code' => 200 ),
			'headers'  => array(),
			'body'     => "\xFF\xD8\xFFjpeg",
		);

		$result = $this->service->importMedia(
			array(
				'source_url'       => 'https://cdn.example.com/photo.jpg',
				'title'            => 'Imported photo',
				'alt_text'         => 'Alt text',
				'caption'          => 'Caption',
				'description'      => 'Description',
				'attach_to_post_id' => $postId,
			)
		);

		self::assertIsArray( $result );
		self::assertSame( 'Imported photo', $result['title'] );
		self::assertSame( 'Alt text', $result['alt_text'] );
		self::assertSame( 'Caption', $result['caption'] );
		self::assertSame( 'Description', $result['description'] );
		self::assertSame( $postId, $result['parent_id'] );
		self::assertSame( array(), WP_Test_Fixtures::$temporary_files );
	}

	public function testUpdateMediaOnlyUpdatesMetadataAndPreservesBackslashes(): void {
		$id    = $this->makeImage();
		$value = 'Code \n LaTeX \frac{x}{y} C:\\Media';

		$result = $this->service->updateMedia(
			array(
				'id'          => $id,
				'title'       => $value,
				'alt_text'    => $value,
				'caption'     => $value,
				'description' => $value,
			)
		);

		self::assertIsArray( $result );
		self::assertSame( $value, $result['title'] );
		self::assertSame( $value, $result['alt_text'] );
		self::assertSame( $value, $result['caption'] );
		self::assertSame( $value, $result['description'] );
		self::assertSame( 'https://example.com/uploads/test.jpg', $result['url'] );
	}

	public function testUpdateMediaRequiresObjectEditPermission(): void {
		$id = $this->makeImage();
		WP_Test_Fixtures::$object_caps['edit_post'][ $id ] = false;

		$result = $this->service->updateMedia( array( 'id' => $id, 'title' => 'Denied' ) );

		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'content_forbidden', $result->get_error_code() );
	}

	public function testSetFeaturedImageValidatesTargetImageReadabilityAndUsesCoreSetter(): void {
		$postId  = $this->makePost();
		$mediaId = $this->makeImage();

		$result = $this->service->setFeaturedImage( $postId, $mediaId );

		self::assertIsArray( $result );
		self::assertSame( $mediaId, WP_Test_Fixtures::$featured_images[ $postId ] );
		self::assertSame( $postId, $result['post']['id'] );
		self::assertSame( $mediaId, $result['media']['id'] );
		self::assertSame( 1, WP_Test_Fixtures::$set_post_thumbnail_calls );

		$repeat = $this->service->setFeaturedImage( $postId, $mediaId );
		self::assertIsArray( $repeat );
		self::assertSame( $mediaId, $repeat['media']['id'] );
		self::assertSame( 2, WP_Test_Fixtures::$set_post_thumbnail_calls );
	}

	public function testSetFeaturedImageRejectsNonImageUnreadableMediaAndUneditableTarget(): void {
		$postId = $this->makePost();
		$imageId = $this->makeImage();
		$documentId = wp_test_insert_attachment( array( 'post_mime_type' => 'application/pdf' ) );

		WP_Test_Fixtures::$object_caps['edit_post'][ $postId ] = false;
		$result = $this->service->setFeaturedImage( $postId, $imageId );
		self::assertSame( 'content_forbidden', $result->get_error_code() );

		WP_Test_Fixtures::$object_caps['edit_post'][ $postId ] = true;
		$result = $this->service->setFeaturedImage( $postId, $documentId );
		self::assertSame( 'content_media_not_image', $result->get_error_code() );

		WP_Test_Fixtures::$object_caps['read_post'][ $imageId ] = false;
		$result = $this->service->setFeaturedImage( $postId, $imageId );
		self::assertSame( 'content_forbidden', $result->get_error_code() );
		self::assertSame( 0, WP_Test_Fixtures::$set_post_thumbnail_calls );
	}

	public function testSetFeaturedImagePreservesPreviousThumbnailWhenImageIsNotRenderable(): void {
		$postId       = $this->makePost();
		$previousId   = $this->makeImage( 'Previous' );
		$unrenderable = $this->makeImage( 'Unrenderable' );
		WP_Test_Fixtures::$featured_images[ $postId ] = $previousId;
		WP_Test_Fixtures::$attachment_metadata[ $unrenderable ] = array();
		WP_Test_Fixtures::$attachment_urls[ $unrenderable ] = '';

		$result = $this->service->setFeaturedImage( $postId, $unrenderable );

		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'content_media_image_unavailable', $result->get_error_code() );
		self::assertSame( $previousId, get_post_thumbnail_id( $postId ) );
		self::assertSame( 0, WP_Test_Fixtures::$set_post_thumbnail_calls );
	}

	public function testInsertMediaGeneratesValidGutenbergFigureWithCaptionAndMediaLink(): void {
		$postId  = $this->makePost( 'Opening paragraph.' );
		$mediaId = $this->makeImage();

		$result = $this->service->insertMedia(
			array(
				'post_id'          => $postId,
				'media_id'         => $mediaId,
				'placement'        => 'append',
				'size_slug'        => 'medium',
				'caption'          => 'Shown caption',
				'link_destination' => 'media',
			)
		);

		self::assertIsArray( $result );
		self::assertSame( 0, $result['replacement_count'] );
		self::assertStringContainsString( '<!-- wp:image {"id":' . $mediaId . ',"sizeSlug":"medium","linkDestination":"media"} -->', $result['block_html'] );
		self::assertStringContainsString( '<figure class="wp-block-image size-medium">', $result['block_html'] );
		self::assertStringContainsString( '<a href="https://example.com/uploads/test.jpg">', $result['block_html'] );
		self::assertStringContainsString( 'src="https://example.com/uploads/test-300x200.jpg"', $result['block_html'] );
		self::assertStringContainsString( 'alt="Useful alt"', $result['block_html'] );
		self::assertStringContainsString( 'class="wp-image-' . $mediaId . '"', $result['block_html'] );
		self::assertStringContainsString( '<figcaption class="wp-element-caption">Shown caption</figcaption>', $result['block_html'] );
		self::assertStringEndsWith( $result['block_html'], $result['post']['content'] );
	}

	public function testInsertMediaSupportsExactAnchoredPlacements(): void {
		$mediaId = $this->makeImage();
		foreach ( array( 'before', 'after', 'replace' ) as $placement ) {
			$postId = $this->makePost( 'Alpha ANCHOR Omega' );
			$result = $this->service->insertMedia(
				array(
					'post_id'    => $postId,
					'media_id'   => $mediaId,
					'placement'  => $placement,
					'anchor_text' => 'ANCHOR',
				)
			);
			self::assertIsArray( $result, $placement );
			self::assertSame( 1, $result['replacement_count'], $placement );
			if ( 'replace' === $placement ) {
				self::assertStringNotContainsString( 'ANCHOR', $result['post']['content'] );
			} else {
				self::assertStringContainsString( 'ANCHOR', $result['post']['content'] );
			}
		}
	}

	public function testInsertMediaRejectsMissingAmbiguousAnchorAndStaleConcurrencyWithoutMutation(): void {
		$mediaId = $this->makeImage();
		$cases = array(
			array( 'content' => 'No match', 'input' => array( 'placement' => 'before' ), 'code' => 'content_media_anchor_required' ),
			array( 'content' => 'No match', 'input' => array( 'placement' => 'before', 'anchor_text' => 'missing' ), 'code' => 'content_media_anchor_not_found' ),
			array( 'content' => 'same same', 'input' => array( 'placement' => 'replace', 'anchor_text' => 'same' ), 'code' => 'content_media_anchor_ambiguous' ),
			array( 'content' => 'unchanged', 'input' => array( 'placement' => 'append', 'expected_modified_gmt' => '2000-01-01 00:00:00' ), 'code' => 'content_post_modified' ),
		);

		foreach ( $cases as $case ) {
			$postId = $this->makePost( $case['content'] );
			$result = $this->service->insertMedia(
				array_merge(
					array( 'post_id' => $postId, 'media_id' => $mediaId ),
					$case['input']
				)
			);
			self::assertTrue( is_wp_error( $result ) );
			self::assertSame( $case['code'], $result->get_error_code() );
			self::assertSame( $case['content'], WP_Test_Fixtures::$posts[ $postId ]->post_content );
		}
	}

	public function testInsertMediaPropagatesWriteFailureWithoutChangingPost(): void {
		$postId  = $this->makePost( 'Original content' );
		$mediaId = $this->makeImage();
		WP_Test_Fixtures::$wp_update_post_error = new WP_Error( 'database_error', 'Write failed.' );

		$result = $this->service->insertMedia( array( 'post_id' => $postId, 'media_id' => $mediaId ) );

		self::assertSame( WP_Test_Fixtures::$wp_update_post_error, $result );
		self::assertSame( 'Original content', WP_Test_Fixtures::$posts[ $postId ]->post_content );
	}

	public function testInsertMediaRejectsUneditablePostUnreadableOrNonImageMediaAndInvalidOptions(): void {
		$postId     = $this->makePost();
		$imageId    = $this->makeImage();
		$documentId = wp_test_insert_attachment( array( 'post_mime_type' => 'application/pdf' ) );

		WP_Test_Fixtures::$object_caps['edit_post'][ $postId ] = false;
		$result = $this->service->insertMedia( array( 'post_id' => $postId, 'media_id' => $imageId ) );
		self::assertSame( 'content_forbidden', $result->get_error_code() );

		WP_Test_Fixtures::$object_caps['edit_post'][ $postId ] = true;
		WP_Test_Fixtures::$object_caps['read_post'][ $imageId ] = false;
		$result = $this->service->insertMedia( array( 'post_id' => $postId, 'media_id' => $imageId ) );
		self::assertSame( 'content_forbidden', $result->get_error_code() );

		$result = $this->service->insertMedia( array( 'post_id' => $postId, 'media_id' => $documentId ) );
		self::assertSame( 'content_media_not_image', $result->get_error_code() );

		WP_Test_Fixtures::$object_caps['read_post'][ $imageId ] = true;
		$result = $this->service->insertMedia( array( 'post_id' => $postId, 'media_id' => $imageId, 'size_slug' => 'custom' ) );
		self::assertSame( 'content_invalid_media_size', $result->get_error_code() );
	}
}
