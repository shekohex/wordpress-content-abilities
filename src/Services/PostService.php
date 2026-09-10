<?php
/**
 * Post service: validation, sanitization, and capability enforcement.
 *
 * @package ContentAbilities
 */

declare( strict_types=1 );

namespace ContentAbilities\Services;

use ContentAbilities\Repositories\PostRepository;
use ContentAbilities\Support\CapabilityGuard;
use ContentAbilities\Support\PostTypes;
use WP_Error;

/**
 * Business logic for content abilities.
 */
final readonly class PostService {

	public function __construct(
		private PostRepository $posts,
		private CapabilityGuard $caps,
	) {}

	/* ---------------------------------------------------------------------
	 * Read.
	 * ------------------------------------------------------------------ */

	/**
	 * Finds posts by query.
	 *
	 * @param array<string, mixed> $input Validated ability input.
	 * @return array<string, mixed>|WP_Error
	 */
	public function findPosts( array $input ): array|WP_Error {
		$perPage       = (int) ( $input['per_page'] ?? 10 );
		$page          = (int) ( $input['page'] ?? 1 );
		$postType      = (string) ( $input['post_type'] ?? 'post' );
		$includeDrafts = ! empty( $input['include_drafts'] );

		if ( ! PostTypes::isPublic( $postType ) ) {
			return $this->error( 'content_invalid_post_type', "Post type \"{$postType}\" is not public." );
		}

		if ( $includeDrafts && ! $this->caps->canEditOthersPosts( $postType ) ) {
			return $this->error( 'content_forbidden', 'Viewing drafts requires permission to edit other users\' posts.' );
		}

		$query = array(
			'post_type'   => $postType,
			'post_status' => $this->visibleStatuses( $includeDrafts ),
			's'           => (string) ( $input['search'] ?? '' ),
			'numberposts' => $perPage,
			'offset'      => ( $page - 1 ) * $perPage,
		);

		$items = $this->posts->find( $query );

		return array(
			'items'    => $items,
			'total'    => count( $items ),
			'page'     => $page,
			'per_page' => $perPage,
		);
	}

	/**
	 * Gets a single post by ID.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function getPost( int $id ): array|WP_Error {
		$post = $this->posts->findById( $id );
		if ( null === $post ) {
			return $this->error( 'content_post_not_found', "Post {$id} does not exist." );
		}

		if ( ! $this->caps->canReadPost( $id ) ) {
			return $this->error( 'content_forbidden', 'You are not allowed to read this post.' );
		}

		$post['categories'] = $this->posts->getTerms( $id, 'category' );
		$post['tags']       = $this->posts->getTerms( $id, 'post_tag' );

		return $post;
	}

	/* ---------------------------------------------------------------------
	 * Write.
	 * ------------------------------------------------------------------ */

	/**
	 * Creates a post.
	 *
	 * @param array<string, mixed> $input Validated ability input.
	 * @return array<string, mixed>|WP_Error
	 */
	public function createPost( array $input ): array|WP_Error {
		$postType = (string) ( $input['post_type'] ?? 'post' );

		if ( ! PostTypes::isPublic( $postType ) ) {
			return $this->error( 'content_invalid_post_type', "Post type \"{$postType}\" is not public." );
		}

		if ( ! $this->caps->canCreatePosts( $postType ) ) {
			return $this->error( 'content_forbidden', 'You are not allowed to create posts of this type.' );
		}

		$title   = isset( $input['title'] ) ? sanitize_text_field( (string) $input['title'] ) : '';
		$content = isset( $input['content'] ) ? wp_kses_post( (string) $input['content'] ) : '';
		$excerpt = isset( $input['excerpt'] ) ? sanitize_text_field( (string) $input['excerpt'] ) : '';

		if ( '' === $title && '' === $content && '' === $excerpt ) {
			return $this->error( 'content_empty_content', 'At least one of title, content, or excerpt is required.' );
		}

		$status = (string) ( $input['status'] ?? 'draft' );
		if ( 'publish' === $status && ! $this->caps->canPublishPosts( $postType ) ) {
			return $this->error( 'content_forbidden', 'You are not allowed to publish this post type.' );
		}

		$id = $this->posts->insert(
			array(
				'post_type'    => $postType,
				'post_title'   => $title,
				'post_content' => $content,
				'post_excerpt' => $excerpt,
				'post_status'  => $status,
				'post_author'  => get_current_user_id(),
			)
		);

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$this->applyOptionalTerms( $id, $input );

		return $this->getPost( $id );
	}

	/**
	 * Updates a post.
	 *
	 * @param array<string, mixed> $input Validated ability input.
	 * @return array<string, mixed>|WP_Error
	 */
	public function updatePost( array $input ): array|WP_Error {
		$id = (int) $input['id'];

		$existing = $this->posts->findById( $id );
		if ( null === $existing ) {
			return $this->error( 'content_post_not_found', "Post {$id} does not exist." );
		}

		if ( ! $this->caps->canEditPost( $id ) ) {
			return $this->error( 'content_forbidden', 'You are not allowed to edit this post.' );
		}

		$data = array( 'ID' => $id );

		$status = (string) ( $input['status'] ?? $existing['status'] );
		if ( 'publish' === $status && 'publish' !== $existing['status'] ) {
			if ( ! $this->caps->canPublishPost( $id ) ) {
				return $this->error( 'content_forbidden', 'You are not allowed to publish this post.' );
			}
		}
		$data['post_status'] = $status;

		if ( isset( $input['title'] ) ) {
			$data['post_title'] = sanitize_text_field( (string) $input['title'] );
		}
		if ( isset( $input['content'] ) ) {
			$data['post_content'] = wp_kses_post( (string) $input['content'] );
		}
		if ( isset( $input['excerpt'] ) ) {
			$data['post_excerpt'] = sanitize_text_field( (string) $input['excerpt'] );
		}

		$result = $this->posts->update( $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->applyOptionalTerms( $id, $input );

		return $this->getPost( $id );
	}

	/* ---------------------------------------------------------------------
	 * Helpers.
	 * ------------------------------------------------------------------ */

	/**
	 * Applies categories/tags from input when present.
	 *
	 * @param array<string, mixed> $input
	 */
	private function applyOptionalTerms( int $id, array $input ): void {
		if ( isset( $input['categories'] ) ) {
			$this->applyTerms( $id, (array) $input['categories'], 'category' );
		}
		if ( isset( $input['tags'] ) ) {
			$this->applyTerms( $id, (array) $input['tags'], 'post_tag' );
		}
	}

	/**
	 * @param string[] $terms
	 */
	private function applyTerms( int $id, array $terms, string $taxonomy ): void {
		$clean = array_values(
			array_filter(
				array_map( static fn( $t ) => sanitize_text_field( (string) $t ), $terms ),
				static fn( string $t ) => '' !== $t
			)
		);
		$this->posts->setTerms( $id, $clean, $taxonomy );
	}

	/**
	 * @return string[] Statuses the current query should match.
	 */
	private function visibleStatuses( bool $includeDrafts ): array {
		return $includeDrafts ? array( 'publish', 'draft' ) : array( 'publish' );
	}

	private function error( string $code, string $message ): WP_Error {
		return new WP_Error( $code, $message );
	}
}
