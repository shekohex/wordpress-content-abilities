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

if ( ! class_exists( 'WP_Term' ) ) {
	/** Minimal WP_Term test double. */
	class WP_Term extends stdClass {}
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

	/** @var array<int, array<string, mixed>> Attachment ID => metadata. */
	public static array $attachment_metadata = array();

	/** @var array<int, string> Attachment ID => URL. */
	public static array $attachment_urls = array();

	/** @var array<int, string> Attachment ID => local file. */
	public static array $attached_files = array();

	/** @var array<int, array<string, mixed>> Post ID => metadata. */
	public static array $post_meta = array();

	/** @var array<int, int> Post ID => featured attachment ID. */
	public static array $featured_images = array();

	public static int $set_post_thumbnail_calls = 0;

	public static mixed $remote_response = null;

	/** @var array<string, mixed> */
	public static array $last_http_args = array();

	public static int $http_request_count = 0;

	/** @var string[] */
	public static array $temporary_files = array();

	public static ?WP_Error $media_sideload_error = null;

	public static ?WP_Error $wp_update_post_error = null;

	/** @var array<int, array<string, array<int, string>>> Post ID => taxonomy => term names. */
	public static array $post_terms = array();

	/** @var array<string, array<string, WP_Term>> Taxonomy => slug => term. */
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
		self::$attachment_metadata = array();
		self::$attachment_urls   = array();
		self::$attached_files    = array();
		self::$post_meta         = array();
		self::$featured_images   = array();
		self::$set_post_thumbnail_calls = 0;
		self::$remote_response   = null;
		self::$last_http_args    = array();
		self::$http_request_count = 0;
		self::$temporary_files   = array();
		self::$media_sideload_error = null;
		self::$wp_update_post_error = null;
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

if ( ! function_exists( 'do_action_ref_array' ) ) {
	function do_action_ref_array( string $tag, array &$args ): void {
		WP_Test_Fixtures::$actions_fired[] = array( $tag, $args );
		$callbacks = WP_Test_Fixtures::$filters[ $tag ] ?? array();
		ksort( $callbacks );
		foreach ( $callbacks as $list ) {
			foreach ( $list as $callback ) {
				$callback( ...$args );
			}
		}
	}
}

if ( ! function_exists( 'remove_filter' ) ) {
	function remove_filter( string $tag, callable $callback, int $priority = 10 ): bool {
		if ( ! isset( WP_Test_Fixtures::$filters[ $tag ][ $priority ] ) ) {
			return false;
		}
		foreach ( WP_Test_Fixtures::$filters[ $tag ][ $priority ] as $index => $registered ) {
			if ( $registered === $callback ) {
				unset( WP_Test_Fixtures::$filters[ $tag ][ $priority ][ $index ] );
				WP_Test_Fixtures::$filters[ $tag ][ $priority ] = array_values( WP_Test_Fixtures::$filters[ $tag ][ $priority ] );
				if ( array() === WP_Test_Fixtures::$filters[ $tag ][ $priority ] ) {
					unset( WP_Test_Fixtures::$filters[ $tag ][ $priority ] );
				}
				if ( array() === WP_Test_Fixtures::$filters[ $tag ] ) {
					unset( WP_Test_Fixtures::$filters[ $tag ] );
				}
				return true;
			}
		}
		return false;
	}
}

if ( ! function_exists( 'remove_action' ) ) {
	function remove_action( string $tag, callable $callback, int $priority = 10 ): bool {
		return remove_filter( $tag, $callback, $priority );
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

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8', false );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( string $url ): string {
		return filter_var( $url, FILTER_VALIDATE_URL ) ? $url : '';
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

if ( ! function_exists( 'wp_slash' ) ) {
	function wp_slash( mixed $value ): mixed {
		if ( is_array( $value ) ) {
			return array_map( 'wp_slash', $value );
		}
		if ( is_string( $value ) ) {
			return addslashes( $value );
		}
		return $value;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( mixed $value ): mixed {
		if ( is_array( $value ) ) {
			return array_map( 'wp_unslash', $value );
		}
		if ( is_string( $value ) ) {
			return stripslashes( $value );
		}
		return $value;
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( string $title ): string {
		return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( $title ) ) ?? '', '-' );
	}
}

if ( ! function_exists( 'sanitize_file_name' ) ) {
	function sanitize_file_name( string $filename ): string {
		return preg_replace( '/[^A-Za-z0-9._-]/', '-', basename( $filename ) ) ?? '';
	}
}

if ( ! function_exists( 'sanitize_mime_type' ) ) {
	function sanitize_mime_type( string $mime_type ): string {
		return preg_replace( '/[^-+\.a-zA-Z0-9\/@]/', '', $mime_type ) ?? '';
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( mixed $value, int $flags = 0, int $depth = 512 ): string|false {
		return json_encode( $value, $flags, $depth );
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
	'attachment' => (object) array(
		'name'           => 'attachment',
		'public'         => true,
		'show_ui'        => true,
		'capability_type' => 'post',
		'cap'            => (object) array(
			'edit_posts'          => 'upload_files',
			'edit_others_posts'   => 'edit_others_posts',
			'publish_posts'       => 'upload_files',
			'read_private_posts'  => 'read',
			'create_posts'        => 'upload_files',
		),
		'hierarchical'   => false,
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
		$postarr = wp_unslash( $postarr );
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
		$post->post_parent           = (int) ( $postarr['post_parent'] ?? 0 );
		$post->post_mime_type        = (string) ( $postarr['post_mime_type'] ?? '' );

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
		$postarr = wp_unslash( $postarr );
		$id = (int) ( $postarr['ID'] ?? 0 );
		if ( null !== WP_Test_Fixtures::$wp_update_post_error ) {
			return WP_Test_Fixtures::$wp_update_post_error;
		}
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
			'post_parent'  => 'post_parent',
			'post_mime_type' => 'post_mime_type',
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
		$mime   = (string) ( $args['post_mime_type'] ?? '' );
		$search = isset( $args['s'] ) ? strtolower( (string) $args['s'] ) : null;
		$number = (int) ( $args['numberposts'] ?? 5 );
		$offset = (int) ( $args['offset'] ?? 0 );

		$found = array();
		foreach ( WP_Test_Fixtures::$posts as $post ) {
			if ( is_array( $type ) ? ! in_array( $post->post_type, $type, true ) : (string) $type !== $post->post_type ) {
				continue;
			}
			if ( '' !== $mime ) {
				$post_mime = (string) ( $post->post_mime_type ?? '' );
				if ( str_contains( $mime, '/' ) ? $post_mime !== $mime : ! str_starts_with( $post_mime, $mime . '/' ) ) {
					continue;
				}
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

		return -1 === $number ? array_slice( $found, $offset ) : array_slice( $found, $offset, $number );
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
 * Attachments / media.
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'wp_test_insert_attachment' ) ) {
	/**
	 * @param array<string, mixed> $postarr
	 * @param array<string, mixed> $metadata
	 */
	function wp_test_insert_attachment( array $postarr, array $metadata = array(), string $url = '', string $file = '' ): int {
		$id  = WP_Test_Fixtures::$next_post_id++;
		$now = gmdate( 'Y-m-d H:i:s' );
		$post = new WP_Post();
		$post->ID                = $id;
		$post->post_type         = 'attachment';
		$post->post_title        = (string) ( $postarr['post_title'] ?? 'Attachment' );
		$post->post_excerpt      = (string) ( $postarr['post_excerpt'] ?? '' );
		$post->post_content      = (string) ( $postarr['post_content'] ?? '' );
		$post->post_status       = 'inherit';
		$post->post_name         = sanitize_title( $post->post_title );
		$post->post_author       = (int) ( $postarr['post_author'] ?? WP_Test_Fixtures::$current_user_id );
		$post->post_parent       = (int) ( $postarr['post_parent'] ?? 0 );
		$post->post_mime_type    = (string) ( $postarr['post_mime_type'] ?? 'application/octet-stream' );
		$post->post_date         = $now;
		$post->post_date_gmt     = $now;
		$post->post_modified     = $now;
		$post->post_modified_gmt = $now;
		WP_Test_Fixtures::$posts[ $id ]               = $post;
		WP_Test_Fixtures::$post_terms[ $id ]          = array();
		WP_Test_Fixtures::$attachment_metadata[ $id ] = $metadata;
		WP_Test_Fixtures::$attachment_urls[ $id ]     = '' !== $url ? $url : 'https://example.com/uploads/attachment-' . $id;
		WP_Test_Fixtures::$attached_files[ $id ]      = $file;
		return $id;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( int $post_id, string $key = '', bool $single = false ): mixed {
		if ( '' === $key ) {
			return WP_Test_Fixtures::$post_meta[ $post_id ] ?? array();
		}
		$value = WP_Test_Fixtures::$post_meta[ $post_id ][ $key ] ?? null;
		return $single ? ( $value ?? '' ) : ( null === $value ? array() : array( $value ) );
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( int $post_id, string $meta_key, mixed $meta_value ): int|bool {
		WP_Test_Fixtures::$post_meta[ $post_id ][ $meta_key ] = is_string( $meta_value ) ? wp_unslash( $meta_value ) : $meta_value;
		return true;
	}
}

if ( ! function_exists( 'wp_get_attachment_metadata' ) ) {
	function wp_get_attachment_metadata( int $attachment_id ): array|false {
		return WP_Test_Fixtures::$attachment_metadata[ $attachment_id ] ?? false;
	}
}

if ( ! function_exists( 'wp_get_attachment_url' ) ) {
	function wp_get_attachment_url( int $attachment_id ): string|false {
		return WP_Test_Fixtures::$attachment_urls[ $attachment_id ] ?? false;
	}
}

if ( ! function_exists( 'get_attached_file' ) ) {
	function get_attached_file( int $attachment_id ): string|false {
		return WP_Test_Fixtures::$attached_files[ $attachment_id ] ?? false;
	}
}

if ( ! function_exists( 'wp_get_attachment_image_src' ) ) {
	/** @return array{0:string,1:int,2:int,3:bool}|false */
	function wp_get_attachment_image_src( int $attachment_id, string|array $size = 'thumbnail' ): array|false {
		$post = get_post( $attachment_id );
		if ( null === $post || ! str_starts_with( (string) $post->post_mime_type, 'image/' ) ) {
			return false;
		}
		$metadata = WP_Test_Fixtures::$attachment_metadata[ $attachment_id ] ?? array();
		$url      = (string) ( WP_Test_Fixtures::$attachment_urls[ $attachment_id ] ?? '' );
		if ( 'full' === $size || ! isset( $metadata['sizes'][ $size ] ) ) {
			return array( $url, (int) ( $metadata['width'] ?? 0 ), (int) ( $metadata['height'] ?? 0 ), false );
		}
		$details = $metadata['sizes'][ $size ];
		$base    = substr( $url, 0, (int) strrpos( $url, '/' ) + 1 );
		return array( $base . $details['file'], (int) $details['width'], (int) $details['height'], true );
	}
}

if ( ! function_exists( 'set_post_thumbnail' ) ) {
	function set_post_thumbnail( int|object $post, int $thumbnail_id ): int|bool {
		++WP_Test_Fixtures::$set_post_thumbnail_calls;
		$post_id = is_object( $post ) ? (int) $post->ID : $post;
		$image   = get_post( $thumbnail_id );
		if ( null === get_post( $post_id ) || null === $image || ! str_starts_with( (string) $image->post_mime_type, 'image/' ) ) {
			return false;
		}
		if ( ( WP_Test_Fixtures::$featured_images[ $post_id ] ?? 0 ) === $thumbnail_id ) {
			return false;
		}
		WP_Test_Fixtures::$featured_images[ $post_id ] = $thumbnail_id;
		return true;
	}
}

if ( ! function_exists( 'get_post_thumbnail_id' ) ) {
	function get_post_thumbnail_id( int|object $post = null ): int|false {
		$post_id = is_object( $post ) ? (int) $post->ID : (int) $post;
		return WP_Test_Fixtures::$featured_images[ $post_id ] ?? false;
	}
}

if ( ! function_exists( 'get_allowed_mime_types' ) ) {
	/** @return array<string, string> */
	function get_allowed_mime_types(): array {
		return array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'gif'          => 'image/gif',
			'webp'         => 'image/webp',
			'pdf'          => 'application/pdf',
			'mp3|m4a'      => 'audio/mpeg',
			'mp4|m4v'      => 'video/mp4',
		);
	}
}

if ( ! function_exists( 'wp_check_filetype_and_ext' ) ) {
	/** @return array{ext:string|false,type:string|false,proper_filename:string|false} */
	function wp_check_filetype_and_ext( string $file, string $filename, ?array $mimes = null ): array {
		$mimes = $mimes ?? get_allowed_mime_types();
		$ext   = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		$type  = false;
		foreach ( $mimes as $extensions => $mime ) {
			if ( in_array( $ext, explode( '|', $extensions ), true ) ) {
				$type = $mime;
				break;
			}
		}
		$contents = is_readable( $file ) ? (string) file_get_contents( $file, false, null, 0, 16 ) : '';
		$real     = str_starts_with( $contents, "\xFF\xD8\xFF" ) ? 'image/jpeg' : ( str_starts_with( $contents, "\x89PNG\r\n\x1A\n" ) ? 'image/png' : null );
		if ( str_starts_with( (string) $type, 'image/' ) && null === $real ) {
			return array( 'ext' => false, 'type' => false, 'proper_filename' => false );
		}
		if ( null !== $real && $real !== $type ) {
			$real_ext = 'image/jpeg' === $real ? 'jpg' : 'png';
			return array(
				'ext'             => $real_ext,
				'type'            => $real,
				'proper_filename' => pathinfo( $filename, PATHINFO_FILENAME ) . '.' . $real_ext,
			);
		}
		return array( 'ext' => false === $type ? false : $ext, 'type' => $type, 'proper_filename' => false );
	}
}

if ( ! function_exists( 'wp_http_validate_url' ) ) {
	function wp_http_validate_url( string $url ): string|false {
		$parts = parse_url( $url );
		if ( false === $parts || ! isset( $parts['scheme'], $parts['host'] ) ) {
			return false;
		}
		$host = strtolower( (string) $parts['host'] );
		if ( in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) || str_starts_with( $host, '10.' ) || str_starts_with( $host, '192.168.' ) ) {
			return false;
		}
		return $url;
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/** @return array<string, int|string>|false */
	function wp_parse_url( string $url, int $component = -1 ): array|int|string|false|null {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'wp_tempnam' ) ) {
	function wp_tempnam( string $filename = '', string $dir = '' ): string {
		$file = tempnam( '' !== $dir ? $dir : sys_get_temp_dir(), 'wp-' );
		if ( false === $file ) {
			return '';
		}
		WP_Test_Fixtures::$temporary_files[] = $file;
		return $file;
	}
}

if ( ! function_exists( 'wp_delete_file' ) ) {
	function wp_delete_file( string $file ): void {
		if ( is_file( $file ) ) {
			unlink( $file );
		}
		WP_Test_Fixtures::$temporary_files = array_values( array_filter( WP_Test_Fixtures::$temporary_files, static fn( string $item ): bool => $item !== $file ) );
	}
}

if ( ! function_exists( 'wp_safe_remote_get' ) ) {
	/** @param array<string, mixed> $args */
	function wp_safe_remote_get( string $url, array $args = array() ): array|WP_Error {
		++WP_Test_Fixtures::$http_request_count;
		WP_Test_Fixtures::$last_http_args = $args;
		if ( is_wp_error( WP_Test_Fixtures::$remote_response ) ) {
			return WP_Test_Fixtures::$remote_response;
		}
		$response = is_array( WP_Test_Fixtures::$remote_response ) ? WP_Test_Fixtures::$remote_response : array(
			'response' => array( 'code' => 200 ),
			'headers'  => array(),
			'body'     => '',
		);
		try {
			foreach ( (array) ( $response['redirects'] ?? array() ) as $redirectUrl ) {
				$hookArgs = array( (string) $redirectUrl, array(), array(), array(), array() );
				do_action_ref_array( 'requests-before_redirect', $hookArgs );
			}
		} catch ( \Throwable $error ) {
			return new WP_Error( 'http_request_failed', $error->getMessage() );
		}
		if ( ! empty( $args['stream'] ) && isset( $args['filename'] ) ) {
			$body  = (string) ( $response['body'] ?? '' );
			$limit = (int) ( $args['limit_response_size'] ?? strlen( $body ) );
			file_put_contents( (string) $args['filename'], substr( $body, 0, $limit ) );
			$response['body'] = '';
		}
		return $response;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( array|WP_Error $response ): int|string {
		return is_array( $response ) ? ( $response['response']['code'] ?? '' ) : '';
	}
}

if ( ! function_exists( 'wp_remote_retrieve_header' ) ) {
	function wp_remote_retrieve_header( array|WP_Error $response, string $header ): string|array {
		if ( ! is_array( $response ) ) {
			return '';
		}
		$headers = array_change_key_case( (array) ( $response['headers'] ?? array() ), CASE_LOWER );
		return $headers[ strtolower( $header ) ] ?? '';
	}
}

if ( ! function_exists( 'media_handle_sideload' ) ) {
	/**
	 * @param array{name:string,tmp_name:string} $file_array
	 * @param array<string, mixed>               $post_data
	 */
	function media_handle_sideload( array $file_array, int $post_id = 0, ?string $desc = null, array $post_data = array() ): int|WP_Error {
		if ( null !== WP_Test_Fixtures::$media_sideload_error ) {
			return WP_Test_Fixtures::$media_sideload_error;
		}
		$type = wp_check_filetype_and_ext( $file_array['tmp_name'], $file_array['name'], get_allowed_mime_types() );
		if ( false === $type['type'] ) {
			return new WP_Error( 'upload_error', 'File type is not allowed.' );
		}
		$metadata = str_starts_with( (string) $type['type'], 'image/' ) ? array( 'width' => 1, 'height' => 1, 'filesize' => filesize( $file_array['tmp_name'] ) ) : array( 'filesize' => filesize( $file_array['tmp_name'] ) );
		return wp_test_insert_attachment(
			array(
				'post_title'     => (string) ( $post_data['post_title'] ?? pathinfo( $file_array['name'], PATHINFO_FILENAME ) ),
				'post_excerpt'   => (string) ( $post_data['post_excerpt'] ?? '' ),
				'post_content'   => (string) ( $post_data['post_content'] ?? '' ),
				'post_mime_type' => (string) $type['type'],
				'post_parent'    => $post_id,
			),
			$metadata,
			'https://example.com/uploads/' . $file_array['name'],
			$file_array['tmp_name']
		);
	}
}

/* -------------------------------------------------------------------------
 * Taxonomies / terms.
 * ---------------------------------------------------------------------- */

$GLOBALS['__wp_test_taxonomies'] = array(
	'category' => (object) array(
		'name'         => 'category',
		'public'       => true,
		'hierarchical' => true,
		'cap'          => (object) array(
			'manage_terms' => 'manage_categories',
			'edit_terms'   => 'manage_categories',
			'delete_terms' => 'delete_categories',
			'assign_terms' => 'edit_posts',
		),
	),
	'post_tag' => (object) array(
		'name'         => 'post_tag',
		'public'       => true,
		'hierarchical' => false,
		'cap'          => (object) array(
			'manage_terms' => 'manage_post_tags',
			'edit_terms'   => 'manage_post_tags',
			'delete_terms' => 'delete_post_tags',
			'assign_terms' => 'edit_posts',
		),
	),
);

if ( ! function_exists( 'taxonomy_exists' ) ) {
	function taxonomy_exists( string $taxonomy ): bool {
		return isset( $GLOBALS['__wp_test_taxonomies'][ $taxonomy ] );
	}
}

if ( ! function_exists( 'get_taxonomy' ) ) {
	function get_taxonomy( string $taxonomy ): object|false {
		return $GLOBALS['__wp_test_taxonomies'][ $taxonomy ] ?? false;
	}
}

if ( ! function_exists( 'wp_insert_term' ) ) {
	/**
	 * @param array<string, mixed> $args
	 */
	function wp_insert_term( string $name, string $taxonomy, array $args = array() ): WP_Term|WP_Error {
		$name = (string) wp_unslash( $name );
		$args = wp_unslash( $args );
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new WP_Error( 'invalid_taxonomy', 'Invalid taxonomy.' );
		}
		$slug = isset( $args['slug'] ) ? sanitize_title( (string) $args['slug'] ) : sanitize_title( $name );
		foreach ( WP_Test_Fixtures::$terms[ $taxonomy ] ?? array() as $term ) {
			if ( $term->slug === $slug ) {
				return new WP_Error( 'term_exists', 'Term already exists.' );
			}
		}
		$term              = new WP_Term();
		$term->term_id     = WP_Test_Fixtures::$next_term_id++;
		$term->taxonomy    = $taxonomy;
		$term->name        = $name;
		$term->slug        = $slug;
		$term->description = (string) ( $args['description'] ?? '' );
		$term->parent      = (int) ( $args['parent'] ?? 0 );
		$term->count       = 0;
		WP_Test_Fixtures::$terms[ $taxonomy ][ $slug ] = $term;
		return $term;
	}
}

if ( ! function_exists( 'wp_update_term' ) ) {
	/**
	 * @param array<string, mixed> $args
	 */
	function wp_update_term( int $term_id, string $taxonomy, array $args = array() ): WP_Term|WP_Error {
		$args = wp_unslash( $args );
		$term = get_term( $term_id, $taxonomy );
		if ( is_wp_error( $term ) ) {
			return $term;
		}
		if ( null === $term ) {
			return new WP_Error( 'invalid_term', 'Term does not exist.' );
		}

		$old_slug = $term->slug;
		if ( array_key_exists( 'name', $args ) ) {
			$term->name = (string) $args['name'];
		}
		if ( array_key_exists( 'slug', $args ) ) {
			$term->slug = sanitize_title( (string) $args['slug'] );
		}
		if ( array_key_exists( 'description', $args ) ) {
			$term->description = (string) $args['description'];
		}
		if ( array_key_exists( 'parent', $args ) ) {
			$term->parent = (int) $args['parent'];
		}

		unset( WP_Test_Fixtures::$terms[ $taxonomy ][ $old_slug ] );
		WP_Test_Fixtures::$terms[ $taxonomy ][ $term->slug ] = $term;
		return $term;
	}
}

if ( ! function_exists( 'get_term' ) ) {
	function get_term( int $term_id, string $taxonomy ): WP_Term|WP_Error|null {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new WP_Error( 'invalid_taxonomy', 'Invalid taxonomy.' );
		}
		foreach ( WP_Test_Fixtures::$terms[ $taxonomy ] as $term ) {
			if ( (int) $term->term_id === $term_id ) {
				return clone $term;
			}
		}
		return null;
	}
}

if ( ! function_exists( 'get_terms' ) ) {
	/**
	 * @param array<string, mixed> $args
	 * @return WP_Term[]|WP_Error
	 */
	function get_terms( array $args ): array|WP_Error {
		$taxonomy = (string) ( $args['taxonomy'] ?? '' );
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new WP_Error( 'invalid_taxonomy', 'Invalid taxonomy.' );
		}
		$search = strtolower( (string) ( $args['search'] ?? '' ) );
		$terms  = array_values( WP_Test_Fixtures::$terms[ $taxonomy ] );
		if ( '' !== $search ) {
			$terms = array_values(
				array_filter(
					$terms,
					static fn( WP_Term $term ): bool => str_contains( strtolower( $term->name ), $search )
				)
			);
		}
		usort( $terms, static fn( WP_Term $a, WP_Term $b ): int => strcasecmp( $a->name, $b->name ) );
		$offset = (int) ( $args['offset'] ?? 0 );
		$number = (int) ( $args['number'] ?? 0 );
		$slice  = $number > 0 ? array_slice( $terms, $offset, $number ) : array_slice( $terms, $offset );
		return array_map( static fn( WP_Term $term ): WP_Term => clone $term, $slice );
	}
}

if ( ! function_exists( 'get_term_by' ) ) {
	function get_term_by( string $field, string|int $value, string $taxonomy ): object|false {
		$terms = WP_Test_Fixtures::$terms[ $taxonomy ] ?? array();
		foreach ( $terms as $term ) {
			if ( 'slug' === $field && $term->slug === (string) $value ) {
				return $term;
			}
			if ( 'name' === $field && strcasecmp( $term->name, (string) $value ) === 0 ) {
				return $term;
			}
			if ( 'id' === $field && (int) $term->term_id === (int) $value ) {
				return clone $term;
			}
		}
		return false;
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
		if ( ( get_taxonomy( $taxonomy )->hierarchical ?? false ) ) {
			$terms = array_values( array_unique( array_map( 'intval', $terms ) ) );
		}

		$names = array();
		foreach ( $terms as $term ) {
			if ( is_int( $term ) ) {
				$found = get_term_by( 'id', $term, $taxonomy );
				if ( false === $found ) {
					continue;
				}
				$names[] = $found->name;
				continue;
			}
			$name = sanitize_text_field( (string) $term );
			if ( '' === $name ) {
				continue;
			}
			$existing = get_term_by( 'name', $name, $taxonomy );
			if ( false === $existing ) {
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
			if ( false !== $term ) {
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
