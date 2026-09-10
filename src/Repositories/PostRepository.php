<?php
/**
 * Post repository: the only layer that touches WordPress post functions.
 *
 * @package ContentAbilities
 */

declare( strict_types=1 );

namespace ContentAbilities\Repositories;

use WP_Error;

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
	 * @param string[] $terms
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
}
