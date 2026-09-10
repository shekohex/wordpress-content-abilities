<?php
/**
 * Minimal in-memory WordPress emulations for unit testing.
 *
 * This file is TEST INFRASTRUCTURE only. It provides just enough of the
 * WordPress runtime (WP_Error, hooks, posts, terms, capabilities) to unit
 * test the plugin's services without a full WordPress install.
 *
 * @package ContentAbilities\Tests
 */

declare( strict_types=1 );

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Emulated WP_Error.
	 */
	final class WP_Error {
		/** @var string[] */
		public array $errors = array();

		/** @var array<string, mixed> */
		public array $error_data = array();

		public function __construct( string $code = '', string $message = '', mixed $data = null ) {
			if ( '' !== $code ) {
				$this->errors[ $code ] = $message;
				if ( null !== $data ) {
					$this->error_data[ $code ] = $data;
				}
			}
		}

		public function get_error_code(): string {
			return (string) ( array_key_first( $this->errors ) ?? '' );
		}

		public function get_error_message( string $code = '' ): string {
			if ( '' === $code ) {
				$code = $this->get_error_code();
			}
			return $this->errors[ $code ] ?? '';
		}

		public function get_error_data( string $code = '' ): mixed {
			if ( '' === $code ) {
				$code = $this->get_error_code();
			}
			return $this->error_data[ $code ] ?? null;
		}

		public function add( string $code, string $message, mixed $data = null ): void {
			$this->errors[ $code ] = $message;
			if ( null !== $data ) {
				$this->error_data[ $code ] = $data;
			}
		}

		public function has_errors(): bool {
			return array() !== $this->errors;
		}
	}
}

if ( ! class_exists( 'WP_Post' ) ) {
	/** Minimal WP_Post test double. */
	class WP_Post extends stdClass {}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * Emulated is_wp_error().
	 */
	function is_wp_error( mixed $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

/**
 * Test fixtures container: reset between tests.
 */
final class WP_Test_Fixtures {
	/** @var array<int, object> Post ID => post object. */
	public static array $posts = array();

	/** @var array<int, array<string, array<int, string>>> Post ID => taxonomy => term names. */
	public static array $post_terms = array();

	/** @var array<string, array<string, array{term_id:int,name:string,slug:string}>> Taxonomy => slug => term. */
	public static array $terms = array();

	public static int $next_post_id = 1;
	public static int $next_term_id = 1;

	/** @var array<string, bool> Primitive cap => allowed. */
	public static array $user_caps = array();

	/** @var array<string, array<int, bool>> Meta cap (e.g. "edit_post") => post ID => allowed. */
	public static array $object_caps = array();

	/** @var array<int> IDs the user "owns" (author). */
	public static array $authored_post_ids = array();

	public static int $current_user_id = 0;

	/** @var array<string, array<int, callable[]>> */
	public static array $filters = array();

	/** @var array<array{0:string,1:mixed[]}> */
	public static array $actions_fired = array();

	/** @var array<string, mixed> */
	public static array $options = array();

	/** @var array<string, callable> Registrations captured from ability API emulations. */
	public static array $ability_categories = array();

	/** @var array<string, array> */
	public static array $abilities = array();

	public static function reset(): void {
		self::$posts             = array();
		self::$post_terms        = array();
		self::$terms             = array(
			'category'  => array(),
			'post_tag'  => array(),
		);
		self::$next_post_id      = 1;
		self::$next_term_id      = 1;
		self::$user_caps         = array();
		self::$object_caps       = array();
		self::$authored_post_ids = array();
		self::$current_user_id   = 0;
		self::$filters           = array();
		self::$actions_fired     = array();
		self::$options           = array();
		self::$ability_categories = array();
		self::$abilities         = array();
	}
}

WP_Test_Fixtures::reset();

/* -------------------------------------------------------------------------
 * Hooks (filters / actions).
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $tag, callable $callback, int $priority = 10, int $accepted_args = 1 ): true {
		WP_Test_Fixtures::$filters[ $tag ][ $priority ][] = $callback;
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $tag, callable $callback, int $priority = 10, int $accepted_args = 1 ): true {
		return add_filter( $tag, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $tag, mixed $value, mixed ...$args ): mixed {
		$callbacks = WP_Test_Fixtures::$filters[ $tag ] ?? array();
		ksort( $callbacks );
		foreach ( $callbacks as $list ) {
			foreach ( $list as $callback ) {
				$value = $callback( $value, ...$args );
			}
		}
		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( string $tag, mixed ...$args ): void {
		WP_Test_Fixtures::$actions_fired[] = array( $tag, $args );
		apply_filters( $tag, null, ...$args );
	}
}

if ( ! function_exists( 'remove_all_filters' ) ) {
	function remove_all_filters( string $tag ): true {
		unset( WP_Test_Fixtures::$filters[ $tag ] );
		return true;
	}
}

/* -------------------------------------------------------------------------
 * i18n + escaping (identity / minimal).
 * ---------------------------------------------------------------------- */

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8', false );
	}
}

/* -------------------------------------------------------------------------
 * Sanitization.
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $str ): string {
		$str = strip_tags( $str );
		$str = preg_replace( '/[\r\n\t ]+/', ' ', $str ) ?? '';
		return trim( $str );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( string $str ): string {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( string $content ): string {
		// Rough emulation: strip <script> and on* attributes.
		$content = preg_replace( '#<script\b[^>]*>.*?</script>#is', '', $content ) ?? $content;
		return preg_replace( '/\son\w+="[^"]*"/i', '', $content ) ?? $content;
	}
}

/* -------------------------------------------------------------------------
 * Capabilities.
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'wp_test_map_meta_cap' ) ) {
	/**
	 * Emulates core map_meta_cap() for the meta caps used by the plugin.
	 *
	 * @return string[] Primitive capabilities required.
	 */
	function wp_test_map_meta_cap( string $cap, ?object $post ): array {
		$user_id = WP_Test_Fixtures::$current_user_id;
		$is_own  = null !== $post && (int) $post->post_author === $user_id;

		switch ( $cap ) {
			case 'edit_post':
				if ( null === $post ) {
					return array( 'edit_others_posts' );
				}
				return $is_own ? array( 'edit_posts' ) : array( 'edit_others_posts' );

			case 'read_post':
				if ( null === $post ) {
					return array( 'read' );
				}
				if ( 'publish' === $post->post_status ) {
					return array( 'read' );
				}
				if ( 'private' === $post->post_status ) {
					return $is_own ? array( 'read' ) : array( 'read_private_posts' );
				}
				return $is_own ? array( 'read' ) : array( 'edit_others_posts' );

			case 'publish_post':
				if ( null !== $post && 'publish' === $post->post_status ) {
					// Already published: keeping it published while editing is allowed.
					return array( 'edit_posts' );
				}
				return array( 'publish_posts' );

			case 'delete_post':
				return array( 'delete_posts' );
		}

		return array( $cap );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $capability, mixed ...$args ): bool {
		if ( array() !== $args && isset( $args[0] ) && is_int( $args[0] ) ) {
			// Object-level meta capability, e.g. edit_post / read_post / publish_post.
			$per_object = WP_Test_Fixtures::$object_caps[ $capability ] ?? array();
			if ( array_key_exists( $args[0], $per_object ) ) {
				return $per_object[ $args[0] ];
			}
			$post = WP_Test_Fixtures::$posts[ $args[0] ] ?? null;
			$caps = wp_test_map_meta_cap( $capability, $post );
		} else {
			$caps = array( $capability );
		}

		foreach ( $caps as $required ) {
			if ( ! ( WP_Test_Fixtures::$user_caps[ $required ] ?? false ) ) {
				return false;
			}
		}
		return true;
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id(): int {
		return WP_Test_Fixtures::$current_user_id;
	}
}

/* -------------------------------------------------------------------------
 * Post types.
 * ---------------------------------------------------------------------- */

if ( ! ! defined( 'WP_TEST_POST_TYPES' ) ) {
	// no-op placeholder to keep phpcs calm about side effects.
}

/**
 * Registered post type objects in the emulated environment.
 *
 * @var array<string, object>
 */
$GLOBALS['__wp_test_post_types'] = array(
	'post' => (object) array(
		'name'           => 'post',
		'public'         => true,
		'show_ui'        => true,
		'capability_type' => 'post',
		'cap'            => (object) array(
			'edit_posts'          => 'edit_posts',
			'edit_others_posts'   => 'edit_others_posts',
			'publish_posts'       => 'publish_posts',
			'read_private_posts'  => 'read_private_posts',
			'create_posts'        => 'edit_posts',
		),
		'hierarchical'   => false,
	),
	'page' => (object) array(
		'name'           => 'page',
		'public'         => true,
		'show_ui'        => true,
		'capability_type' => 'page',
		'cap'            => (object) array(
			'edit_posts'          => 'edit_pages',
			'edit_others_posts'   => 'edit_others_pages',
			'publish_posts'       => 'publish_pages',
			'read_private_posts'  => 'read_private_pages',
			'create_posts'        => 'edit_pages',
		),
		'hierarchical'   => true,
	),
);

if ( ! function_exists( 'get_post_types' ) ) {
	/**
	 * @param array<string, mixed> $args Filter args (supports 'public' and 'show_ui').
	 * @param string               $output 'names' or 'objects'.
	 * @return string[]|object[]
	 */
	function get_post_types( array $args = array(), string $output = 'names' ): array {
		$types = array();
		foreach ( $GLOBALS['__wp_test_post_types'] as $name => $obj ) {
			if ( isset( $args['public'] ) && (bool) $args['public'] !== (bool) $obj->public ) {
				continue;
			}
			$types[ $name ] = ( 'names' === $output ) ? $name : $obj;
		}
		return $types;
	}
}

if ( ! function_exists( 'post_type_exists' ) ) {
	function post_type_exists( string $post_type ): bool {
		return isset( $GLOBALS['__wp_test_post_types'][ $post_type ] );
	}
}

if ( ! function_exists( 'get_post_type_object' ) ) {
	function get_post_type_object( string $post_type ): ?object {
		return $GLOBALS['__wp_test_post_types'][ $post_type ] ?? null;
	}
}

/* -------------------------------------------------------------------------
 * Posts.
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'get_post' ) ) {
	function get_post( int|object $post = null ): ?object {
		if ( is_object( $post ) ) {
			return $post;
		}
		$post = (int) $post;
		if ( isset( WP_Test_Fixtures::$posts[ $post ] ) ) {
			return clone WP_Test_Fixtures::$posts[ $post ];
		}
		return null;
	}
}

if ( ! function_exists( 'wp_is_post_revision' ) ) {
	function wp_is_post_revision( int|object $post ): bool {
		return false;
	}
}

if ( ! function_exists( 'wp_insert_post' ) ) {
	/**
	 * @param array<string, mixed> $postarr
	 * @return int|WP_Error
	 */
	function wp_insert_post( array $postarr ) {
		$type = $postarr['post_type'] ?? 'post';
		if ( ! post_type_exists( $type ) ) {
			return new WP_Error( 'invalid_post_type', 'Invalid post type.' );
		}
		$title   = (string) ( $postarr['post_title'] ?? '' );
		$content = (string) ( $postarr['post_content'] ?? '' );
		$excerpt = (string) ( $postarr['post_excerpt'] ?? '' );
		if ( '' === $title && '' === $content && '' === $excerpt ) {
			return new WP_Error( 'empty_content', 'Content, title, and excerpt are empty.' );
		}

		$id       = WP_Test_Fixtures::$next_post_id++;
		$now      = gmdate( 'Y-m-d H:i:s' );
		$slug     = $postarr['post_name'] ?? '';
		if ( '' === $slug ) {
			$slug = trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( $title ) ) ?? '', '-' );
		}

		$post                        = new WP_Post();
		$post->ID                    = $id;
		$post->post_type             = $type;
		$post->post_title            = $title;
		$post->post_content          = $content;
		$post->post_excerpt          = $excerpt;
		$post->post_status           = $postarr['post_status'] ?? 'draft';
		$post->post_name             = $slug;
		$post->post_author           = (int) ( $postarr['post_author'] ?? 0 );
		$post->post_date             = $postarr['post_date'] ?? $now;
		$post->post_date_gmt         = $post->post_date;
		$post->post_modified         = $now;
		$post->post_modified_gmt     = $now;

		WP_Test_Fixtures::$posts[ $id ]   = $post;
		WP_Test_Fixtures::$post_terms[ $id ] = array();

		return $id;
	}
}

if ( ! function_exists( 'wp_update_post' ) ) {
	/**
	 * @param array<string, mixed> $postarr
	 * @return int|WP_Error
	 */
	function wp_update_post( array $postarr ) {
		$id = (int) ( $postarr['ID'] ?? 0 );
		if ( ! isset( WP_Test_Fixtures::$posts[ $id ] ) ) {
			return new WP_Error( 'invalid_post_id', 'Invalid post ID.' );
		}
		$post = WP_Test_Fixtures::$posts[ $id ];

		$map = array(
			'post_title'   => 'post_title',
			'post_content' => 'post_content',
			'post_excerpt' => 'post_excerpt',
			'post_status'  => 'post_status',
			'post_name'    => 'post_name',
		);
		foreach ( $map as $key => $prop ) {
			if ( array_key_exists( $key, $postarr ) ) {
				$post->$prop = $postarr[ $key ];
			}
		}
		$post->post_modified     = gmdate( 'Y-m-d H:i:s' );
		$post->post_modified_gmt = $post->post_modified;

		WP_Test_Fixtures::$posts[ $id ] = $post;
		return $id;
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	/**
	 * @param array<string, mixed> $args Query args (post_type, post_status, s, numberposts, offset, orderby).
	 * @return object[]
	 */
	function get_posts( array $args ): array {
		$type   = $args['post_type'] ?? 'post';
		$status = $args['post_status'] ?? 'publish';
		$search = isset( $args['s'] ) ? strtolower( (string) $args['s'] ) : null;
		$number = (int) ( $args['numberposts'] ?? 5 );
		$offset = (int) ( $args['offset'] ?? 0 );

		$found = array();
		foreach ( WP_Test_Fixtures::$posts as $post ) {
			if ( (string) $type !== $post->post_type ) {
				continue;
			}
			if ( 'any' !== $status ) {
				$statuses = is_array( $status ) ? $status : array( $status );
				if ( ! in_array( $post->post_status, $statuses, true ) ) {
					continue;
				}
			}
			if ( null !== $search && '' !== $search ) {
				$haystack = strtolower( $post->post_title . ' ' . $post->post_content );
				if ( ! str_contains( $haystack, $search ) ) {
					continue;
				}
			}
			$found[] = clone $post;
		}

		// Default-ish order: newest first.
		usort(
			$found,
			static function ( object $a, object $b ): int {
				return strcmp( (string) $b->post_date, (string) $a->post_date );
			}
		);

		return array_slice( $found, $offset, $number );
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( int|object $post ): string {
		$id = is_object( $post ) ? (int) $post->ID : (int) $post;
		$p  = WP_Test_Fixtures::$posts[ $id ] ?? null;
		if ( null === $p ) {
			return '';
		}
		return 'https://example.com/' . ( '' !== $p->post_name ? $p->post_name . '/' : '?p=' . $id );
	}
}

if ( ! function_exists( 'get_post_status' ) ) {
	function get_post_status( int|object $post ): string {
		$id = is_object( $post ) ? (int) $post->ID : (int) $post;
		return (string) ( WP_Test_Fixtures::$posts[ $id ]->post_status ?? '' );
	}
}

/* -------------------------------------------------------------------------
 * Taxonomies / terms.
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'taxonomy_exists' ) ) {
	function taxonomy_exists( string $taxonomy ): bool {
		return in_array( $taxonomy, array( 'category', 'post_tag' ), true );
	}
}

if ( ! function_exists( 'wp_insert_term' ) ) {
	function wp_insert_term( string $name, string $taxonomy ): stdClass|WP_Error {
		$slug = trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( $name ) ) ?? '', '-' );
		foreach ( WP_Test_Fixtures::$terms[ $taxonomy ] ?? array() as $term ) {
			if ( $term->slug === $slug ) {
				return new WP_Error( 'term_exists', 'Term already exists.' );
			}
		}
		$term = (object) array(
			'term_id' => WP_Test_Fixtures::$next_term_id++,
			'name'    => $name,
			'slug'    => $slug,
		);
		WP_Test_Fixtures::$terms[ $taxonomy ][ $slug ] = $term;
		return $term;
	}
}

if ( ! function_exists( 'get_term_by' ) ) {
	function get_term_by( string $field, string|int $value, string $taxonomy ): ?object {
		$terms = WP_Test_Fixtures::$terms[ $taxonomy ] ?? array();
		foreach ( $terms as $term ) {
			if ( 'slug' === $field && $term->slug === (string) $value ) {
				return $term;
			}
			if ( 'name' === $field && strcasecmp( $term->name, (string) $value ) === 0 ) {
				return $term;
			}
			if ( 'id' === $field && (int) $term->term_id === (int) $value ) {
				return $term;
			}
		}
		return null;
	}
}

if ( ! function_exists( 'wp_set_post_terms' ) ) {
	/**
	 * @param int|false $append
	 */
	function wp_set_post_terms( int $post_id, array|string $terms, string $taxonomy, bool $append = false ): array|WP_Error {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new WP_Error( 'invalid_taxonomy', 'Invalid taxonomy.' );
		}
		if ( is_string( $terms ) ) {
			$terms = ( '' === $terms ) ? array() : array( $terms );
		}

		$names = array();
		foreach ( $terms as $term ) {
			if ( is_int( $term ) ) {
				$found = get_term_by( 'id', $term, $taxonomy );
				if ( null === $found ) {
					return new WP_Error( 'invalid_term', 'Term does not exist.' );
				}
				$names[] = $found->name;
				continue;
			}
			$name = sanitize_text_field( (string) $term );
			if ( '' === $name ) {
				continue;
			}
			$existing = get_term_by( 'name', $name, $taxonomy );
			if ( null === $existing ) {
				$created = wp_insert_term( $name, $taxonomy );
				if ( is_wp_error( $created ) ) {
					return $created;
				}
				$name = $created->name;
			}
			$names[] = $name;
		}

		$names = array_values( array_unique( $names ) );
		if ( ! $append ) {
			WP_Test_Fixtures::$post_terms[ $post_id ][ $taxonomy ] = $names;
		} else {
			$merged = array_merge( WP_Test_Fixtures::$post_terms[ $post_id ][ $taxonomy ] ?? array(), $names );
			WP_Test_Fixtures::$post_terms[ $post_id ][ $taxonomy ] = array_values( array_unique( $merged ) );
		}

		return WP_Test_Fixtures::$post_terms[ $post_id ][ $taxonomy ];
	}
}

if ( ! function_exists( 'wp_get_post_terms' ) ) {
	/**
	 * @return object[]|WP_Error
	 */
	function wp_get_post_terms( int $post_id, string $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new WP_Error( 'invalid_taxonomy', 'Invalid taxonomy.' );
		}
		$names = WP_Test_Fixtures::$post_terms[ $post_id ][ $taxonomy ] ?? array();
		$out   = array();
		foreach ( $names as $name ) {
			$term = get_term_by( 'name', $name, $taxonomy );
			if ( null !== $term ) {
				$out[] = clone $term;
			}
		}
		return $out;
	}
}

/* -------------------------------------------------------------------------
 * Abilities API emulation (captures registrations).
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'wp_register_ability_category' ) ) {
	/**
	 * @param array<string, mixed> $args
	 */
	function wp_register_ability_category( string $slug, array $args ): ?object {
		WP_Test_Fixtures::$ability_categories[ $slug ] = $args;
		return (object) array( 'slug' => $slug, 'args' => $args );
	}
}

if ( ! function_exists( 'wp_register_ability' ) ) {
	/**
	 * @param array<string, mixed> $args
	 */
	function wp_register_ability( string $name, array $args ): ?object {
		if ( ! isset( WP_Test_Fixtures::$ability_categories[ $args['category'] ?? '' ] ) ) {
			// Mirrors core: unregistered category => registration fails (null).
			return null;
		}
		WP_Test_Fixtures::$abilities[ $name ] = $args;
		return (object) array( 'name' => $name );
	}
}
