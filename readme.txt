=== Content Abilities ===
Contributors: shekohex
Tags: abilities, mcp, ai, rest-api, content
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 1.2.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Expose WordPress post, taxonomy, and media abilities through the Abilities API and the official MCP Adapter.

== Description ==

Content Abilities registers fifteen abilities in the `content` category on the WordPress Abilities API:

* `content/find-posts` — search and list posts of public post types (read-only)
* `content/get-post` — retrieve a single post with categories and tags (read-only)
* `content/create-post` — create a post; draft by default (write)
* `content/update-post` — update title, content, excerpt, status, categories, tags (write)
* `content/patch-post` — replace exact text in content, title, or excerpt with ambiguity and concurrency protection (write)
* `content/find-terms` — list or search categories and tags (read-only)
* `content/get-term` — retrieve one category or tag (read-only)
* `content/create-term` — create a category or tag (write)
* `content/update-term` — update a category or tag (write)
* `content/find-media` — search and list readable attachments with pagination and MIME filters (read-only)
* `content/get-media` — retrieve stable attachment metadata and image sizes (read-only)
* `content/import-media` — securely import media from a public HTTPS URL (write)
* `content/update-media` — update attachment title, alt text, caption, or description (write)
* `content/set-featured-image` — set a readable image attachment as a post's featured image (write)
* `content/insert-media` — insert a Gutenberg image block with exact placement and concurrency protection (write)

Every ability declares `meta.public = true`, JSON Schema input/output definitions, and MCP annotations (`readonly`, `destructive`, `idempotent`), so the official [MCP Adapter](https://wordpress.org/plugins/mcp-adapter/) exposes them automatically as MCP tools.

There is deliberately no delete ability.

Permissions mirror WordPress exactly:

* Reads require the `read` capability; drafts and private posts additionally require the relevant edit/read private capabilities (object-level `read_post` checks).
* Creation requires the post type's create capability.
* Publishing requires `publish_posts` (or the type-specific equivalent).
* Updates require the object-level `edit_post` capability; publishing a draft additionally requires `publish_post`.
* Patches require the object-level `edit_post` capability and can require an exact `modified_gmt` value.
* Term creation and updates use the selected taxonomy's real `edit_terms` capability.
* Post category/tag assignment uses each taxonomy's `assign_terms` capability and propagates WordPress errors.
* Media reads use object-level `read_post`; media metadata updates use object-level `edit_post`.
* Media imports require `upload_files` and object-level `edit_post` for an optional parent.
* Featured-image and media insertion operations require object-level `edit_post` on the target plus `read_post` on an image attachment.

Post category and tag inputs accept integer term IDs or string term names. JSON types keep numeric names distinct from IDs. Missing names are created only with the taxonomy's `edit_terms` capability; invalid IDs and WordPress assignment errors are returned to the caller.

`content/patch-post` performs case-sensitive exact matching. A missing match returns `content_patch_text_not_found`; multiple matches return `content_patch_ambiguous` unless `replace_all` is true; stale `expected_modified_gmt` returns `content_post_modified`. Replacement output is sanitized and untouched surrounding formatting is preserved.

`content/import-media` accepts no base64 data or local paths. It requires a public HTTPS URL, uses WordPress SSRF-safe HTTP handling across redirects, streams into a temporary file with a 15-second timeout, enforces a plugin-level 20 MiB maximum, validates the actual allowed MIME type and extension through WordPress, propagates `WP_Error`, and removes temporary files on every path.

`content/insert-media` supports append, prepend, before, after, and replace placement. Anchored modes require one exact byte match and fail without changing the post when the anchor is missing or ambiguous. Optional `expected_modified_gmt` prevents stale writes. Generated blocks use WordPress attachment URLs and metadata with the thumbnail, medium, medium_large, large, or full size.

== Installation ==

1. Upload the `wordpress-content-abilities` folder to `/wp-content/plugins/`.
2. Make sure WordPress 6.9+ (7.1 recommended) and PHP 8.3+ are running.
3. Activate the plugin through the **Plugins** screen in WordPress.
4. Install and activate the [MCP Adapter](https://wordpress.org/plugins/mcp-adapter/) plugin to expose the abilities over MCP.

== Frequently Asked Questions ==

= Do I need the MCP Adapter plugin? =

The abilities are registered regardless. The MCP Adapter exposes them to MCP clients such as Claude Desktop, Cursor, or any MCP-compatible agent.

= Can AI agents delete my content? =

No. This plugin intentionally ships no delete or trash ability.

= Are drafts exposed? =

Only when the caller has the `edit_posts` capability and passes `include_drafts: true` to `content/find-posts`.

== Changelog ==

= 1.2.0 =
* Add find, get, secure HTTPS import, metadata update, featured image, and Gutenberg insertion media abilities.
* Add object-level media permissions, exact insertion anchors, optimistic concurrency, and a fixed 20 MiB import limit.

= 1.1.1 =
* Explicitly expose content abilities through the MCP adapter discovery tools.

= 1.1.0 =
* Add category and tag list/search/get, create, and update abilities with taxonomy-specific capabilities.
* Add exact, formatting-preserving `content/patch-post` with ambiguity and optimistic concurrency checks.
* Support category/tag assignment by integer ID or string name and propagate assignment errors.

= 1.0.0 =
* Initial release: find-posts, get-post, create-post, update-post abilities.
