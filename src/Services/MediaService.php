<?php
/**
 * Media service: validation, permissions, importing, and insertion.
 *
 * @package ContentAbilities
 */

declare( strict_types=1 );

namespace ContentAbilities\Services;

use ContentAbilities\Repositories\MediaRepository;
use ContentAbilities\Repositories\PostRepository;
use ContentAbilities\Support\CapabilityGuard;
use WP_Error;

/**
 * Business logic for media abilities.
 */
final readonly class MediaService {

	private const SIZE_SLUGS = array( 'thumbnail', 'medium', 'medium_large', 'large', 'full' );

	private const PLACEMENTS = array( 'append', 'prepend', 'before', 'after', 'replace' );

	private const LINK_DESTINATIONS = array( 'none', 'media', 'attachment' );

	public function __construct(
		private MediaRepository $media,
		private PostRepository $posts,
		private CapabilityGuard $caps,
	) {}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>|WP_Error
	 */
	public function findMedia( array $input ): array|WP_Error {
		$page    = (int) ( $input['page'] ?? 1 );
		$perPage = (int) ( $input['per_page'] ?? 10 );
		if ( $page < 1 || $perPage < 1 || $perPage > 50 ) {
			return $this->error( 'content_invalid_pagination', 'page must be at least 1 and per_page must be between 1 and 50.' );
		}

		$mimeType = '';
		if ( isset( $input['mime_type'] ) ) {
			$requestedMimeType = (string) $input['mime_type'];
			$mimeType          = sanitize_mime_type( $requestedMimeType );
			if ( '' === $mimeType || $mimeType !== $requestedMimeType || ! $this->isAllowedMimeFilter( $mimeType ) ) {
				return $this->error( 'content_invalid_mime_type', 'mime_type must be an allowed MIME type or media family.' );
			}
		}

		$query = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => $mimeType,
			's'              => sanitize_text_field( (string) ( $input['search'] ?? '' ) ),
		);
		$found = $this->media->findPage( $query, $page, $perPage );

		return array(
			'items'    => $found['items'],
			'total'    => $found['total'],
			'page'     => $page,
			'per_page' => $perPage,
		);
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function getMedia( int $id ): array|WP_Error {
		$media = $this->media->findById( $id );
		if ( null === $media ) {
			return $this->error( 'content_media_not_found', "Media {$id} does not exist." );
		}
		if ( ! $this->caps->canReadPost( $id ) ) {
			return $this->error( 'content_forbidden', 'You are not allowed to read this media item.' );
		}
		if ( '' === (string) $media['url'] ) {
			return $this->error( 'content_media_unavailable', 'This media item does not have a public attachment URL.' );
		}
		return $media;
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>|WP_Error
	 */
	public function importMedia( array $input ): array|WP_Error {
		if ( ! current_user_can( 'upload_files' ) ) {
			return $this->error( 'content_forbidden', 'You are not allowed to upload files.' );
		}

		$sourceUrl = (string) ( $input['source_url'] ?? '' );
		$parts     = wp_parse_url( $sourceUrl );
		if ( ! is_array( $parts ) || 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) ) || false === wp_http_validate_url( $sourceUrl ) ) {
			return $this->error( 'content_invalid_source_url', 'source_url must be a public HTTPS URL.' );
		}

		$parentId = (int) ( $input['attach_to_post_id'] ?? 0 );
		if ( $parentId > 0 ) {
			if ( null === $this->posts->findById( $parentId ) ) {
				return $this->error( 'content_post_not_found', "Post {$parentId} does not exist." );
			}
			if ( ! $this->caps->canEditPost( $parentId ) ) {
				return $this->error( 'content_forbidden', 'You are not allowed to attach media to this post.' );
			}
		}

		$filename = sanitize_file_name( basename( (string) ( $parts['path'] ?? '' ) ) );
		if ( '' === $filename || ! str_contains( $filename, '.' ) ) {
			return $this->error( 'content_invalid_source_url', 'source_url path must include a valid filename extension.' );
		}

		$postData = array();
		if ( isset( $input['title'] ) ) {
			$postData['post_title'] = sanitize_text_field( (string) $input['title'] );
		}
		if ( isset( $input['caption'] ) ) {
			$postData['post_excerpt'] = wp_kses_post( (string) $input['caption'] );
		}
		if ( isset( $input['description'] ) ) {
			$postData['post_content'] = wp_kses_post( (string) $input['description'] );
		}

		$id = $this->media->importFromUrl( $sourceUrl, $parentId, $filename, $postData );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		if ( isset( $input['alt_text'] ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', wp_slash( sanitize_text_field( (string) $input['alt_text'] ) ) );
		}

		$media = $this->media->findById( $id );
		return null === $media ? $this->error( 'content_media_not_found', 'Imported media could not be read.' ) : $media;
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>|WP_Error
	 */
	public function updateMedia( array $input ): array|WP_Error {
		$id       = (int) ( $input['id'] ?? 0 );
		$existing = $this->media->findById( $id );
		if ( null === $existing ) {
			return $this->error( 'content_media_not_found', "Media {$id} does not exist." );
		}
		if ( ! $this->caps->canEditPost( $id ) ) {
			return $this->error( 'content_forbidden', 'You are not allowed to edit this media item.' );
		}

		$postData = array();
		if ( array_key_exists( 'title', $input ) ) {
			$postData['post_title'] = sanitize_text_field( (string) $input['title'] );
		}
		if ( array_key_exists( 'caption', $input ) ) {
			$postData['post_excerpt'] = wp_kses_post( (string) $input['caption'] );
		}
		if ( array_key_exists( 'description', $input ) ) {
			$postData['post_content'] = wp_kses_post( (string) $input['description'] );
		}
		$altText = array_key_exists( 'alt_text', $input ) ? sanitize_text_field( (string) $input['alt_text'] ) : null;
		$result  = $this->media->update( $id, $postData, $altText );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->getMedia( $id );
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function setFeaturedImage( int $postId, int $mediaId ): array|WP_Error {
		$post = $this->posts->findById( $postId );
		if ( null === $post ) {
			return $this->error( 'content_post_not_found', "Post {$postId} does not exist." );
		}
		if ( ! $this->caps->canEditPost( $postId ) ) {
			return $this->error( 'content_forbidden', 'You are not allowed to edit this post.' );
		}
		$media = $this->readableImage( $mediaId );
		if ( is_wp_error( $media ) ) {
			return $media;
		}
		if ( ! $this->isRenderableImage( $mediaId ) ) {
			return $this->error( 'content_media_image_unavailable', 'WordPress could not resolve the featured image source.' );
		}
		if ( ! $this->media->setFeaturedImage( $postId, $mediaId ) ) {
			return $this->error( 'content_featured_image_failed', 'WordPress could not set the featured image.' );
		}

		return array(
			'post'  => $this->posts->findById( $postId ),
			'media' => $media,
		);
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>|WP_Error
	 */
	public function insertMedia( array $input ): array|WP_Error {
		$postId  = (int) ( $input['post_id'] ?? 0 );
		$mediaId = (int) ( $input['media_id'] ?? 0 );
		$post    = $this->posts->findById( $postId );
		if ( null === $post ) {
			return $this->error( 'content_post_not_found', "Post {$postId} does not exist." );
		}
		if ( ! $this->caps->canEditPost( $postId ) ) {
			return $this->error( 'content_forbidden', 'You are not allowed to edit this post.' );
		}
		$media = $this->readableImage( $mediaId );
		if ( is_wp_error( $media ) ) {
			return $media;
		}
		if ( isset( $input['expected_modified_gmt'] ) && (string) $input['expected_modified_gmt'] !== $post['modified_gmt'] ) {
			return $this->error( 'content_post_modified', 'The post was modified after the expected timestamp.' );
		}

		$placement = (string) ( $input['placement'] ?? 'append' );
		$sizeSlug  = (string) ( $input['size_slug'] ?? 'large' );
		$link      = (string) ( $input['link_destination'] ?? 'none' );
		if ( ! in_array( $placement, self::PLACEMENTS, true ) ) {
			return $this->error( 'content_invalid_media_placement', 'placement is not supported.' );
		}
		if ( ! in_array( $sizeSlug, self::SIZE_SLUGS, true ) ) {
			return $this->error( 'content_invalid_media_size', 'size_slug is not supported.' );
		}
		if ( ! in_array( $link, self::LINK_DESTINATIONS, true ) ) {
			return $this->error( 'content_invalid_media_link', 'link_destination is not supported.' );
		}

		$anchorText       = (string) ( $input['anchor_text'] ?? '' );
		$replacementCount = 0;
		if ( in_array( $placement, array( 'before', 'after', 'replace' ), true ) ) {
			if ( '' === $anchorText ) {
				return $this->error( 'content_media_anchor_required', 'anchor_text is required for anchored placement.' );
			}
			$replacementCount = substr_count( (string) $post['content'], $anchorText );
			if ( 0 === $replacementCount ) {
				return $this->error( 'content_media_anchor_not_found', 'anchor_text was not found.' );
			}
			if ( $replacementCount > 1 ) {
				return $this->error( 'content_media_anchor_ambiguous', 'anchor_text occurs more than once.' );
			}
		}

		$caption   = array_key_exists( 'caption', $input ) ? (string) $input['caption'] : null;
		$blockHtml = $this->imageBlock( $media, $sizeSlug, $caption, $link );
		if ( is_wp_error( $blockHtml ) ) {
			return $blockHtml;
		}
		$content = (string) $post['content'];
		$updated = match ( $placement ) {
			'prepend' => '' === $content ? $blockHtml : $blockHtml . "\n\n" . $content,
			'before'  => $this->replaceOnce( $content, $anchorText, $blockHtml . "\n\n" . $anchorText ),
			'after'   => $this->replaceOnce( $content, $anchorText, $anchorText . "\n\n" . $blockHtml ),
			'replace' => $this->replaceOnce( $content, $anchorText, $blockHtml ),
			default   => '' === $content ? $blockHtml : $content . "\n\n" . $blockHtml,
		};

		$result = $this->posts->update(
			wp_slash(
				array(
					'ID'           => $postId,
					'post_content' => $updated,
				)
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'replacement_count' => $replacementCount,
			'post'              => $this->posts->findById( $postId ),
			'media'             => $media,
			'block_html'        => $blockHtml,
		);
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private function readableImage( int $id ): array|WP_Error {
		$media = $this->media->findById( $id );
		if ( null === $media ) {
			return $this->error( 'content_media_not_found', "Media {$id} does not exist." );
		}
		if ( ! $this->caps->canReadPost( $id ) ) {
			return $this->error( 'content_forbidden', 'You are not allowed to read this media item.' );
		}
		if ( 'image' !== $media['media_type'] ) {
			return $this->error( 'content_media_not_image', 'media_id must reference an image attachment.' );
		}
		return $media;
	}

	private function isRenderableImage( int $id ): bool {
		$metadata = wp_get_attachment_metadata( $id );
		$source   = wp_get_attachment_image_src( $id, 'thumbnail' );
		return is_array( $metadata )
			&& (int) ( $metadata['width'] ?? 0 ) > 0
			&& (int) ( $metadata['height'] ?? 0 ) > 0
			&& is_array( $source )
			&& '' !== (string) ( $source[0] ?? '' )
			&& (int) ( $source[1] ?? 0 ) > 0
			&& (int) ( $source[2] ?? 0 ) > 0;
	}

	/**
	 * @param array<string, mixed> $media
	 * @return string|WP_Error
	 */
	private function imageBlock( array $media, string $sizeSlug, ?string $caption, string $linkDestination ): string|WP_Error {
		$source = wp_get_attachment_image_src( (int) $media['id'], $sizeSlug );
		if ( false === $source || '' === (string) $source[0] ) {
			return $this->error( 'content_media_image_unavailable', 'WordPress could not resolve the requested image source.' );
		}
		$attributes        = array(
			'id'              => (int) $media['id'],
			'sizeSlug'        => $sizeSlug,
			'linkDestination' => $linkDestination,
		);
		$encodedAttributes = wp_json_encode( $attributes, JSON_UNESCAPED_SLASHES );
		if ( false === $encodedAttributes ) {
			return $this->error( 'content_media_block_failed', 'Unable to encode image block attributes.' );
		}

		$image = '<img src="' . esc_url( (string) $source[0] ) . '" alt="' . esc_attr( (string) $media['alt_text'] ) . '" class="wp-image-' . (int) $media['id'] . '"/>';
		if ( 'media' === $linkDestination ) {
			$image = '<a href="' . esc_url( (string) $media['url'] ) . '">' . $image . '</a>';
		} elseif ( 'attachment' === $linkDestination ) {
			$image = '<a href="' . esc_url( (string) get_permalink( (int) $media['id'] ) ) . '">' . $image . '</a>';
		}

		$captionHtml = null === $caption || '' === $caption ? '' : '<figcaption class="wp-element-caption">' . esc_html( wp_strip_all_tags( $caption ) ) . '</figcaption>';
		$block       = '<!-- wp:image ' . $encodedAttributes . ' -->' . "\n"
			. '<figure class="wp-block-image size-' . esc_attr( $sizeSlug ) . '">' . $image . $captionHtml . '</figure>' . "\n"
			. '<!-- /wp:image -->';
		return wp_kses_post( $block );
	}

	private function replaceOnce( string $content, string $search, string $replacement ): string {
		$position = strpos( $content, $search );
		return false === $position ? $content : substr_replace( $content, $replacement, $position, strlen( $search ) );
	}

	private function isAllowedMimeFilter( string $mimeType ): bool {
		if ( in_array( $mimeType, array( 'image', 'audio', 'video', 'application' ), true ) ) {
			return true;
		}
		return in_array( $mimeType, array_values( get_allowed_mime_types() ), true );
	}

	private function error( string $code, string $message ): WP_Error {
		return new WP_Error( $code, $message );
	}
}
