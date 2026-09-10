<?php
/**
 * Term repository.
 *
 * @package ContentAbilities
 */

declare( strict_types=1 );

namespace ContentAbilities\Repositories;

use WP_Error;
use WP_Term;

/**
 * Data-access boundary for taxonomy terms.
 */
final class TermRepository {

	/**
	 * @param array<string, mixed> $query
	 * @return list<array<string, mixed>>|WP_Error
	 */
	public function find( array $query ): array|WP_Error {
		$terms = get_terms( $query );
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}
		if ( ! is_array( $terms ) ) {
			return new WP_Error( 'content_terms_failed', 'Unable to list terms.' );
		}

		$out = array();
		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term ) {
				$out[] = $this->serialize( $term );
			}
		}
		return $out;
	}

	/**
	 * @return array<string, mixed>|WP_Error|null
	 */
	public function findById( int $id, string $taxonomy ): array|WP_Error|null {
		$term = get_term( $id, $taxonomy );
		if ( is_wp_error( $term ) ) {
			return $term;
		}
		if ( ! $term instanceof WP_Term ) {
			return null;
		}

		return $this->serialize( $term );
	}

	/**
	 * @param array<string, mixed> $args
	 * @return int|WP_Error
	 */
	public function insert( string $name, string $taxonomy, array $args ): int|WP_Error {
		$result = wp_insert_term( (string) wp_slash( $name ), $taxonomy, wp_slash( $args ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $result instanceof WP_Term ? (int) $result->term_id : (int) $result['term_id'];
	}

	/**
	 * @param array<string, mixed> $args
	 * @return int|WP_Error
	 */
	public function update( int $id, string $taxonomy, array $args ): int|WP_Error {
		$result = wp_update_term( $id, $taxonomy, wp_slash( $args ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $result instanceof WP_Term ? (int) $result->term_id : (int) $result['term_id'];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function serialize( WP_Term $term ): array {
		return array(
			'id'          => (int) $term->term_id,
			'taxonomy'    => (string) $term->taxonomy,
			'name'        => (string) $term->name,
			'slug'        => (string) $term->slug,
			'description' => (string) $term->description,
			'parent'      => (int) $term->parent,
			'count'       => (int) $term->count,
		);
	}
}
