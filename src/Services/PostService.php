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

		$termPermission = $this->checkTermAssignmentPermissions( $input );
		if ( is_wp_error( $termPermission ) ) {
			return $termPermission;
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

		$resolvedTerms = $this->resolveOptionalTerms( $input );
		if ( is_wp_error( $resolvedTerms ) ) {
			return $resolvedTerms;
		}

		$id = $this->posts->insert(
			wp_slash(
				array(
					'post_type'    => $postType,
					'post_title'   => $title,
					'post_content' => $content,
					'post_excerpt' => $excerpt,
					'post_status'  => $status,
					'post_author'  => get_current_user_id(),
				)
			)
		);

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$termResult = $this->applyResolvedTerms( $id, $resolvedTerms );
		if ( is_wp_error( $termResult ) ) {
			return $termResult;
		}

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

		$termPermission = $this->checkTermAssignmentPermissions( $input );
		if ( is_wp_error( $termPermission ) ) {
			return $termPermission;
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

		$resolvedTerms = $this->resolveOptionalTerms( $input );
		if ( is_wp_error( $resolvedTerms ) ) {
			return $resolvedTerms;
		}

		$result = $this->posts->update( wp_slash( $data ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$termResult = $this->applyResolvedTerms( $id, $resolvedTerms );
		if ( is_wp_error( $termResult ) ) {
			return $termResult;
		}

		return $this->getPost( $id );
	}

	/**
	 * Replaces exact text in one editable post field.
	 *
	 * @param array<string, mixed> $input Validated ability input.
	 * @return array<string, mixed>|WP_Error
	 */
	public function patchPost( array $input ): array|WP_Error {
		$id       = (int) $input['id'];
		$field    = (string) $input['field'];
		$oldText  = (string) $input['old_text'];
		$newText  = (string) $input['new_text'];
		$existing = $this->posts->findById( $id );

		if ( null === $existing ) {
			return $this->error( 'content_post_not_found', "Post {$id} does not exist." );
		}

		if ( ! $this->caps->canEditPost( $id ) ) {
			return $this->error( 'content_forbidden', 'You are not allowed to edit this post.' );
		}

		if ( isset( $input['expected_modified_gmt'] )
			&& (string) $input['expected_modified_gmt'] !== $existing['modified_gmt'] ) {
			return $this->error( 'content_post_modified', 'The post was modified after the expected timestamp.' );
		}

		if ( '' === $oldText ) {
			return $this->error( 'content_patch_empty_text', 'old_text must not be empty.' );
		}

		$current          = (string) $existing[ $field ];
		$replacementCount = substr_count( $current, $oldText );
		if ( 0 === $replacementCount ) {
			return $this->error( 'content_patch_text_not_found', 'old_text was not found in the selected field.' );
		}
		if ( $replacementCount > 1 && empty( $input['replace_all'] ) ) {
			return $this->error( 'content_patch_ambiguous', 'old_text occurs more than once; set replace_all to replace every match.' );
		}

		$limit = ! empty( $input['replace_all'] ) ? $replacementCount : 1;
		if ( 'content' === $field ) {
			$patched = wp_kses_post( $this->replaceExact( $current, $oldText, $newText, $limit ) );
		} else {
			$patched = $this->replaceExact( $current, $oldText, sanitize_text_field( $newText ), $limit );
		}
		$result = $this->posts->update(
			wp_slash(
				array(
					'ID'                   => $id,
					$this->postField( $field ) => $patched,
				)
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$post = $this->getPost( $id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return array(
			'replacement_count' => $limit,
			'post'              => $post,
		);
	}

	/* ---------------------------------------------------------------------
	 * Helpers.
	 * ------------------------------------------------------------------ */

	/**
	 * Resolves categories/tags from input before persistence.
	 *
	 * @param array<string, mixed> $input
	 * @return array<string, list<int>>|WP_Error
	 */
	private function resolveOptionalTerms( array $input ): array|WP_Error {
		$resolved = array();
		if ( isset( $input['categories'] ) ) {
			$result = $this->resolveTerms( (array) $input['categories'], 'category' );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$resolved['category'] = $result;
		}
		if ( isset( $input['tags'] ) ) {
			$result = $this->resolveTerms( (array) $input['tags'], 'post_tag' );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$resolved['post_tag'] = $result;
		}

		return $resolved;
	}

	/**
	 * @param array<int, mixed> $terms
	 * @return list<int>|WP_Error
	 */
	private function resolveTerms( array $terms, string $taxonomy ): array|WP_Error {
		$resolved = array();
		foreach ( $terms as $term ) {
			if ( is_int( $term ) ) {
				$termId = $this->posts->findTermId( $term, $taxonomy );
			} elseif ( is_string( $term ) ) {
				$name   = sanitize_text_field( $term );
				$termId = $this->posts->findTermIdByName( $name, $taxonomy );
				if ( null === $termId ) {
					if ( ! $this->caps->canEditTerms( $taxonomy ) ) {
						return $this->error( 'content_forbidden', "Creating a new {$taxonomy} term requires taxonomy edit permission." );
					}
					$termId = $this->posts->insertTerm( $name, $taxonomy );
				}
			} else {
				return $this->error( 'content_invalid_term', 'Term assignments must be integers or strings.' );
			}

			if ( is_wp_error( $termId ) ) {
				return $termId;
			}
			if ( null === $termId ) {
				return $this->error( 'invalid_term', 'Term does not exist in the requested taxonomy.' );
			}
			$resolved[] = $termId;
		}

		return $resolved;
	}

	/**
	 * Applies already resolved integer term IDs after post persistence.
	 *
	 * @param array<string, list<int>> $resolved
	 */
	private function applyResolvedTerms( int $id, array $resolved ): true|WP_Error {
		foreach ( $resolved as $taxonomy => $terms ) {
			$result = $this->posts->setTerms( $id, $terms, $taxonomy );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $input
	 */
	private function checkTermAssignmentPermissions( array $input ): true|WP_Error {
		foreach ( array( 'categories' => 'category', 'tags' => 'post_tag' ) as $field => $taxonomy ) {
			if ( ! isset( $input[ $field ] ) ) {
				continue;
			}
			if ( ! $this->caps->canAssignTerms( $taxonomy ) ) {
				return $this->error( 'content_forbidden', "You are not allowed to assign {$taxonomy} terms." );
			}
			foreach ( (array) $input[ $field ] as $term ) {
				if ( is_int( $term ) ) {
					continue;
				}
				if ( ! is_string( $term ) ) {
					return $this->error( 'content_invalid_term', 'Term assignments must be integers or strings.' );
				}
				$name = sanitize_text_field( (string) $term );
				if ( '' !== $name
					&& ! $this->posts->termExistsByName( $name, $taxonomy )
					&& ! $this->caps->canEditTerms( $taxonomy ) ) {
					return $this->error( 'content_forbidden', "Creating a new {$taxonomy} term requires taxonomy edit permission." );
				}
			}
		}

		return true;
	}

	private function replaceExact( string $value, string $oldText, string $newText, int $limit ): string {
		$offset = 0;
		for ( $replaced = 0; $replaced < $limit; ++$replaced ) {
			$position = strpos( $value, $oldText, $offset );
			if ( false === $position ) {
				break;
			}
			$value  = substr_replace( $value, $newText, $position, strlen( $oldText ) );
			$offset = $position + strlen( $newText );
		}

		return $value;
	}

	private function postField( string $field ): string {
		return array(
			'content' => 'post_content',
			'title'   => 'post_title',
			'excerpt' => 'post_excerpt',
		)[ $field ];
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
