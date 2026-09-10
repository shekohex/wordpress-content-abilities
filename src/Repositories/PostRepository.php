<?php
/**
 * Post repository: the only layer that touches WordPress post functions.
 *
 * @package ContentAbilities
 */

declare( strict_types=1 );

namespace ContentAbilities\Repositories;

use WP_Error;
use WP_Term;

/**
 * Data-access boundary for posts.
 */
final class PostRepository {

	/**
	 * @param array<string, mixed> $query Resolved query (post_type, post_status, s, numberposts, offset).
	 * @return list<array<string, mixed>> Serialized posts.
	 */
	public function find( array $query ): array {
		$posts = get_posts( $query );
		$out   = array();
		foreach ( $posts as $post ) {
			if ( $post instanceof \WP_Post ) {
				$out[] = $this->serialize( $post );
			}
		}
		return $out;
	}

	/**
	 * @param int $id Post ID.
	 * @return array<string, mixed>|null
	 */
	public function findById( int $id ): ?array {
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post ) {
			return null;
		}

		return $this->serialize( $post );
	}

	/**
	 * Serializes a raw post object into the ability output shape.
	 *
	 * @param \WP_Post $post Raw post object.
	 * @return array<string, mixed>
	 */
	public function serialize( \WP_Post $post ): array {
		return array(
			'id'           => (int) $post->ID,
			'type'         => (string) $post->post_type,
			'title'        => (string) $post->post_title,
			'status'       => (string) $post->post_status,
			'slug'         => (string) $post->post_name,
			'excerpt'      => (string) $post->post_excerpt,
			'content'      => (string) $post->post_content,
			'link'         => (string) get_permalink( (int) $post->ID ),
			'date_gmt'     => (string) $post->post_date_gmt,
			'modified_gmt' => (string) $post->post_modified_gmt,
		);
	}

	/**
	 * @param array<string, mixed> $data
	 * @return int|WP_Error
	 */
	public function insert( array $data ): int|WP_Error {
		return wp_insert_post( $data, true );
	}

	/**
	 * @param array<string, mixed> $data
	 * @return int|WP_Error
	 */
	public function update( array $data ): int|WP_Error {
		return wp_update_post( $data, true );
	}

	/**
	 * Sets taxonomy terms on a post.
	 *
	 * @param array<int> $terms
	 * @return string[]|WP_Error
	 */
	public function setTerms( int $postId, array $terms, string $taxonomy ): array|WP_Error {
		$result = wp_set_post_terms( $postId, $terms, $taxonomy );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( false === $result ) {
			return new WP_Error( 'content_terms_failed', 'Unable to assign terms.' );
		}
		return array_map( 'strval', $result );
	}

	/**
	 * Finds a term ID in a taxonomy.
	 */
	public function findTermId( int $id, string $taxonomy ): int|WP_Error|null {
		$term = get_term( $id, $taxonomy );
		if ( is_wp_error( $term ) ) {
			return $term;
		}
		return $term instanceof WP_Term ? (int) $term->term_id : null;
	}

	/**
	 * Finds a term ID by exact name in a taxonomy.
	 */
	public function findTermIdByName( string $name, string $taxonomy ): int|WP_Error|null {
		$term = get_term_by( 'name', $name, $taxonomy );
		if ( false === $term ) {
			return null;
		}
		if ( ! $term instanceof WP_Term ) {
			return new WP_Error( 'content_terms_failed', 'Unable to resolve term.' );
		}
		return (int) $term->term_id;
	}

	/**
	 * Creates a term and returns its ID.
	 */
	public function insertTerm( string $name, string $taxonomy ): int|WP_Error {
		$result = wp_insert_term( (string) wp_slash( $name ), $taxonomy );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $result instanceof WP_Term ? (int) $result->term_id : (int) $result['term_id'];
	}

	/**
	 * Lists term names attached to a post.
	 *
	 * @return string[]
	 */
	public function getTerms( int $postId, string $taxonomy ): array {
		$terms = wp_get_post_terms( $postId, $taxonomy );
		if ( is_wp_error( $terms ) ) {
			return array();
		}
		return array_map( static fn( $t ) => (string) $t->name, $terms );
	}

	/**
	 * Checks whether an exact term name exists in a taxonomy.
	 */
	public function termExistsByName( string $name, string $taxonomy ): bool {
		return is_int( $this->findTermIdByName( $name, $taxonomy ) );
	}
}
