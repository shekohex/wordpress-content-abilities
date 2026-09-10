=== Content Abilities ===
Contributors: shekohex
Tags: abilities, mcp, ai, rest-api, content
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Expose WordPress content abilities (find, get, create, update posts) through the Abilities API and the official MCP Adapter.

== Description ==

Content Abilities registers four abilities in the `content` category on the WordPress Abilities API:

* `content/find-posts` — search and list posts of public post types (read-only)
* `content/get-post` — retrieve a single post with categories and tags (read-only)
* `content/create-post` — create a post; draft by default (write)
* `content/update-post` — update title, content, excerpt, status, categories, tags (write)

Every ability declares `meta.public = true`, JSON Schema input/output definitions, and MCP annotations (`readonly`, `destructive`, `idempotent`), so the official [MCP Adapter](https://wordpress.org/plugins/mcp-adapter/) exposes them automatically as MCP tools.

There is deliberately no delete ability.

Permissions mirror WordPress exactly:

* Reads require the `read` capability; drafts and private posts additionally require the relevant edit/read private capabilities (object-level `read_post` checks).
* Creation requires the post type's create capability.
* Publishing requires `publish_posts` (or the type-specific equivalent).
* Updates require the object-level `edit_post` capability; publishing a draft additionally requires `publish_post`.

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

= 1.0.0 =
* Initial release: find-posts, get-post, create-post, update-post abilities.
