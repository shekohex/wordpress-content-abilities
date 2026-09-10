<?php
/**
 * Media repository: WordPress attachment and sideload boundary.
 *
 * @package ContentAbilities
 */

declare( strict_types=1 );

namespace ContentAbilities\Repositories;

use WP_Error;

/**
 * Data-access boundary for attachments.
 */
final class MediaRepository {

	public const MAX_IMPORT_BYTES = 20 * 1024 * 1024;

	/**
	 * @param array<string, mixed> $query
	 * @return list<array<string, mixed>>
	 */
	public function find( array $query ): array {
		$attachments = get_posts( $query );
		$items       = array();
		foreach ( $attachments as $attachment ) {
			if ( $attachment instanceof \WP_Post && current_user_can( 'read_post', (int) $attachment->ID ) ) {
				$item = $this->serialize( $attachment );
				if ( '' !== $item['url'] ) {
					$items[] = $item;
				}
			}
		}
		return $items;
	}

	/**
	 * @param array<string, mixed> $query
	 * @return array{items:list<array<string, mixed>>,total:int}
	 */
	public function findPage( array $query, int $page, int $perPage ): array {
		$allItems = $this->find(
			array_merge(
				$query,
				array(
					'numberposts' => -1,
					'offset'      => 0,
				)
			)
		);

		return array(
			'items' => array_values( array_slice( $allItems, ( $page - 1 ) * $perPage, $perPage ) ),
			'total' => count( $allItems ),
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function findById( int $id ): ?array {
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || 'attachment' !== $post->post_type ) {
			return null;
		}
		return $this->serialize( $post );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function serialize( \WP_Post $attachment ): array {
		$id            = (int) $attachment->ID;
		$metadata      = wp_get_attachment_metadata( $id );
		$metadata      = is_array( $metadata ) ? $metadata : array();
		$mimeType      = (string) $attachment->post_mime_type;
		$file          = get_attached_file( $id );
		$filesize      = isset( $metadata['filesize'] ) ? (int) $metadata['filesize'] : 0;
		$attachmentUrl = wp_get_attachment_url( $id );
		if ( 0 === $filesize && is_string( $file ) && is_file( $file ) ) {
			$size     = filesize( $file );
			$filesize = false === $size ? 0 : $size;
		}

		return array(
			'id'           => $id,
			'title'        => (string) $attachment->post_title,
			'alt_text'     => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
			'caption'      => (string) $attachment->post_excerpt,
			'description'  => (string) $attachment->post_content,
			'mime_type'    => $mimeType,
			'media_type'   => (string) strtok( $mimeType, '/' ),
			'url'          => false === $attachmentUrl ? '' : $attachmentUrl,
			'width'        => (int) ( $metadata['width'] ?? 0 ),
			'height'       => (int) ( $metadata['height'] ?? 0 ),
			'filesize'     => $filesize,
			'parent_id'    => (int) $attachment->post_parent,
			'date_gmt'     => (string) $attachment->post_date_gmt,
			'modified_gmt' => (string) $attachment->post_modified_gmt,
			'sizes'        => $this->serializeImageSizes( $id, $metadata ),
		);
	}

	/**
	 * @param array<string, mixed> $postData
	 * @return int|WP_Error
	 */
	public function importFromUrl( string $sourceUrl, int $parentId, string $filename, array $postData ): int|WP_Error {
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			$wordpressRoot = (string) constant( 'ABSPATH' );
			require_once $wordpressRoot . 'wp-admin/includes/file.php';
			require_once $wordpressRoot . 'wp-admin/includes/image.php';
			require_once $wordpressRoot . 'wp-admin/includes/media.php';
		}

		$temporaryFile = wp_tempnam( $filename );
		if ( '' === $temporaryFile ) {
			return new WP_Error( 'content_media_temp_file_failed', __( 'Unable to create a temporary file.', 'content-abilities' ) );
		}

		$httpsRedirectGuard = static function (
			string $redirectUrl,
			mixed &$headers,
			mixed &$data,
			mixed &$type,
			mixed &$options
		): void {
			$parts = wp_parse_url( $redirectUrl );
			if ( is_array( $parts ) && 'https' === strtolower( (string) ( $parts['scheme'] ?? '' ) ) ) {
				return;
			}

			$message = __( 'Remote media redirects must remain HTTPS.', 'content-abilities' );
			if ( class_exists( '\\WpOrg\\Requests\\Exception' ) ) {
				throw new \WpOrg\Requests\Exception( $message, 'nonhttpsredirect' );
			}
			throw new \RuntimeException( $message );
		};
		add_action( 'requests-before_redirect', $httpsRedirectGuard, 10, 5 );

		try {
			try {
				$response = wp_safe_remote_get(
					$sourceUrl,
					array(
						'timeout'             => 15,
						'redirection'         => 3,
						'reject_unsafe_urls'  => true,
						'stream'              => true,
						'filename'            => $temporaryFile,
						'limit_response_size' => self::MAX_IMPORT_BYTES + 1,
					)
				);
			} finally {
				remove_action( 'requests-before_redirect', $httpsRedirectGuard, 10 );
			}
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$statusCode = (int) wp_remote_retrieve_response_code( $response );
			if ( $statusCode < 200 || $statusCode >= 300 ) {
				return new WP_Error( 'content_media_http_error', __( 'Remote server returned an unsuccessful HTTP status.', 'content-abilities' ), array( 'status' => $statusCode ) );
			}

			$declaredLength = wp_remote_retrieve_header( $response, 'content-length' );
			$declaredLength = is_numeric( $declaredLength ) ? (int) $declaredLength : null;
			if ( null !== $declaredLength && $declaredLength > self::MAX_IMPORT_BYTES ) {
				return new WP_Error( 'content_media_too_large', __( 'Remote media exceeds the 20 MiB import limit.', 'content-abilities' ) );
			}

			$actualLength = filesize( $temporaryFile );
			if ( false === $actualLength ) {
				return new WP_Error( 'content_media_download_failed', __( 'Unable to read the downloaded media.', 'content-abilities' ) );
			}
			if ( $actualLength > self::MAX_IMPORT_BYTES ) {
				return new WP_Error( 'content_media_too_large', __( 'Remote media exceeds the 20 MiB import limit.', 'content-abilities' ) );
			}
			if ( null !== $declaredLength && $actualLength < $declaredLength ) {
				return new WP_Error( 'content_media_incomplete', __( 'Remote media download was incomplete.', 'content-abilities' ) );
			}
			if ( 0 === $actualLength ) {
				return new WP_Error( 'content_media_download_failed', __( 'Remote media file is empty.', 'content-abilities' ) );
			}

			$fileType = wp_check_filetype_and_ext( $temporaryFile, $filename, get_allowed_mime_types() );
			if ( empty( $fileType['ext'] ) || empty( $fileType['type'] ) ) {
				return new WP_Error( 'content_invalid_media_type', __( 'Downloaded file type or extension is not allowed.', 'content-abilities' ) );
			}
			if ( ! empty( $fileType['proper_filename'] ) ) {
				$filename = (string) $fileType['proper_filename'];
			}

			return media_handle_sideload(
				array(
					'name'     => $filename,
					'tmp_name' => $temporaryFile,
				),
				$parentId,
				null,
				wp_slash( $postData )
			);
		} finally {
			wp_delete_file( $temporaryFile );
		}
	}

	/**
	 * @param array<string, mixed> $postData
	 * @return int|WP_Error
	 */
	public function update( int $id, array $postData, ?string $altText ): int|WP_Error {
		$result = wp_update_post( wp_slash( array_merge( array( 'ID' => $id ), $postData ) ), true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( null !== $altText ) {
			update_post_meta( $id, '_wp_attachment_image_alt', wp_slash( $altText ) );
		}
		return (int) $result;
	}

	public function setFeaturedImage( int $postId, int $mediaId ): bool {
		$result = set_post_thumbnail( $postId, $mediaId );
		return false !== $result || (int) get_post_thumbnail_id( $postId ) === $mediaId;
	}

	/**
	 * @param array<string, mixed> $metadata
	 * @return array<string, array<string, int|string>>
	 */
	private function serializeImageSizes( int $id, array $metadata ): array {
		if ( ! isset( $metadata['sizes'] ) || ! is_array( $metadata['sizes'] ) ) {
			return array();
		}
		$sizes = array();
		foreach ( $metadata['sizes'] as $slug => $details ) {
			if ( ! is_array( $details ) ) {
				continue;
			}
			$source = wp_get_attachment_image_src( $id, (string) $slug );
			if ( false === $source ) {
				continue;
			}
			$sizes[ (string) $slug ] = array(
				'url'       => (string) $source[0],
				'width'     => (int) $source[1],
				'height'    => (int) $source[2],
				'mime_type' => (string) ( $details['mime-type'] ?? '' ),
				'filesize'  => (int) ( $details['filesize'] ?? 0 ),
			);
		}
		return $sizes;
	}
}
