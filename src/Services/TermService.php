<?php
/**
 * Term service.
 *
 * @package ContentAbilities
 */

declare( strict_types=1 );

namespace ContentAbilities\Services;

use ContentAbilities\Repositories\TermRepository;
use ContentAbilities\Support\CapabilityGuard;
use WP_Error;

/**
 * Business logic for category and tag abilities.
 */
final readonly class TermService {

	public function __construct(
		private TermRepository $terms,
		private CapabilityGuard $caps,
	) {}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>|WP_Error
	 */
	public function findTerms( array $input ): array|WP_Error {
		$taxonomy = (string) $input['taxonomy'];
		$invalid  = $this->validateTaxonomy( $taxonomy );
		if ( is_wp_error( $invalid ) ) {
			return $invalid;
		}

		$page    = (int) ( $input['page'] ?? 1 );
		$perPage = (int) ( $input['per_page'] ?? 20 );
		$items   = $this->terms->find(
			array(
				'taxonomy'  => $taxonomy,
				'hide_empty' => false,
				'search'     => (string) ( $input['search'] ?? '' ),
				'number'     => $perPage,
				'offset'     => ( $page - 1 ) * $perPage,
			)
		);
		if ( is_wp_error( $items ) ) {
			return $items;
		}

		return array(
			'items'    => $items,
			'total'    => count( $items ),
			'page'     => $page,
			'per_page' => $perPage,
		);
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>|WP_Error
	 */
	public function getTerm( array $input ): array|WP_Error {
		$taxonomy = (string) $input['taxonomy'];
		$invalid  = $this->validateTaxonomy( $taxonomy );
		if ( is_wp_error( $invalid ) ) {
			return $invalid;
		}

		$term = $this->terms->findById( (int) $input['id'], $taxonomy );
		if ( is_wp_error( $term ) ) {
			return $term;
		}
		if ( null === $term ) {
			return $this->error( 'content_term_not_found', 'The requested term does not exist in this taxonomy.' );
		}

		return $term;
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>|WP_Error
	 */
	public function createTerm( array $input ): array|WP_Error {
		$taxonomy = (string) $input['taxonomy'];
		$invalid  = $this->validateWritableTaxonomy( $taxonomy );
		if ( is_wp_error( $invalid ) ) {
			return $invalid;
		}

		$name = sanitize_text_field( (string) $input['name'] );
		if ( '' === $name ) {
			return $this->error( 'content_empty_term_name', 'Term name must not be empty.' );
		}

		$id = $this->terms->insert( $name, $taxonomy, $this->termData( $input, $taxonomy ) );
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		return $this->getTerm( array( 'taxonomy' => $taxonomy, 'id' => $id ) );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>|WP_Error
	 */
	public function updateTerm( array $input ): array|WP_Error {
		$taxonomy = (string) $input['taxonomy'];
		$invalid  = $this->validateWritableTaxonomy( $taxonomy );
		if ( is_wp_error( $invalid ) ) {
			return $invalid;
		}

		$id       = (int) $input['id'];
		$existing = $this->terms->findById( $id, $taxonomy );
		if ( is_wp_error( $existing ) ) {
			return $existing;
		}
		if ( null === $existing ) {
			return $this->error( 'content_term_not_found', 'The requested term does not exist in this taxonomy.' );
		}

		$data = $this->termData( $input, $taxonomy );
		if ( isset( $input['name'] ) ) {
			$data['name'] = sanitize_text_field( (string) $input['name'] );
		}
		$result = $this->terms->update( $id, $taxonomy, $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->getTerm( array( 'taxonomy' => $taxonomy, 'id' => $id ) );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	private function termData( array $input, string $taxonomy ): array {
		$data = array();
		if ( isset( $input['slug'] ) ) {
			$data['slug'] = sanitize_title( (string) $input['slug'] );
		}
		if ( isset( $input['description'] ) ) {
			$data['description'] = wp_kses_post( (string) $input['description'] );
		}
		if ( 'category' === $taxonomy && isset( $input['parent'] ) ) {
			$data['parent'] = (int) $input['parent'];
		}

		return $data;
	}

	private function validateWritableTaxonomy( string $taxonomy ): true|WP_Error {
		$invalid = $this->validateTaxonomy( $taxonomy );
		if ( is_wp_error( $invalid ) ) {
			return $invalid;
		}
		if ( ! $this->caps->canEditTerms( $taxonomy ) ) {
			return $this->error( 'content_forbidden', 'You are not allowed to create or edit terms in this taxonomy.' );
		}

		return true;
	}

	private function validateTaxonomy( string $taxonomy ): true|WP_Error {
		if ( ! in_array( $taxonomy, array( 'category', 'post_tag' ), true ) || ! taxonomy_exists( $taxonomy ) ) {
			return $this->error( 'content_invalid_taxonomy', 'Only category and post_tag taxonomies are supported.' );
		}

		return true;
	}

	private function error( string $code, string $message ): WP_Error {
		return new WP_Error( $code, $message );
	}
}
