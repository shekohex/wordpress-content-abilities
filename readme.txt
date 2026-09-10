=== Content Abilities ===
Contributors: shekohex
Tags: abilities, mcp, ai, rest-api, content
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 1.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Expose WordPress post, category, and tag abilities through the Abilities API and the official MCP Adapter.

== Description ==

Content Abilities registers nine abilities in the `content` category on the WordPress Abilities API:

* `content/find-posts` — search and list posts of public post types (read-only)
* `content/get-post` — retrieve a single post with categories and tags (read-only)
* `content/create-post` — create a post; draft by default (write)
* `content/update-post` — update title, content, excerpt, status, categories, tags (write)
* `content/patch-post` — replace exact text in content, title, or excerpt with ambiguity and concurrency protection (write)
* `content/find-terms` — list or search categories and tags (read-only)
* `content/get-term` — retrieve one category or tag (read-only)
* `content/create-term` — create a category or tag (write)
* `content/update-term` — update a category or tag (write)

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

Post category and tag inputs accept integer term IDs or string term names. JSON types keep numeric names distinct from IDs. Missing names are created only with the taxonomy's `edit_terms` capability; invalid IDs and WordPress assignment errors are returned to the caller.

`content/patch-post` performs case-sensitive exact matching. A missing match returns `content_patch_text_not_found`; multiple matches return `content_patch_ambiguous` unless `replace_all` is true; stale `expected_modified_gmt` returns `content_post_modified`. Replacement output is sanitized and untouched surrounding formatting is preserved.

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

= 1.1.0 =
* Add category and tag list/search/get, create, and update abilities with taxonomy-specific capabilities.
* Add exact, formatting-preserving `content/patch-post` with ambiguity and optimistic concurrency checks.
* Support category/tag assignment by integer ID or string name and propagate assignment errors.

= 1.0.0 =
* Initial release: find-posts, get-post, create-post, update-post abilities.
