# Content Abilities

Production-quality WordPress plugin that exposes content abilities — **find**, **get**, **create**, and **update posts** — through the [WordPress Abilities API](https://developer.wordpress.org/apis/abilities/) (WordPress 6.9+) and the official [MCP Adapter](https://wordpress.org/plugins/mcp-adapter/), so AI agents and automation can work with site content safely.

* No delete ability — content cannot be destroyed through this plugin.
* WordPress capability checks on every ability, including object-level `read_post` / `edit_post` / `publish_post`.
* JSON Schema input/output definitions and structured `WP_Error` outputs.
* Modern PHP 8.3+ architecture: PSR-4, `final`/`readonly` classes, constructor injection, service/repository separation, `strict_types`.

## Requirements

| Component | Version |
|-----------|---------|
| PHP | ≥ 8.3 (tested on 8.3 and 8.4) |
| WordPress | ≥ 6.9 (7.1 recommended) |
| MCP Adapter (optional, for MCP exposure) | ≥ 0.6.1 |

## Abilities

All abilities live in the `content` category (registered on `wp_abilities_api_categories_init`; abilities on `wp_abilities_api_init`). Every ability sets `meta.public = true` and carries MCP annotations in WordPress format (`readonly`, `destructive`, `idempotent`), which the MCP Adapter converts to `readOnlyHint` / `destructiveHint` / `idempotentHint`.

| Ability | Purpose | Annotations |
|---------|---------|-------------|
| `content/find-posts` | Search/list posts of public post types; pagination; optional drafts | `readonly: true`, `idempotent: true` |
| `content/get-post` | Fetch one post incl. categories & tags | `readonly: true`, `idempotent: true` |
| `content/create-post` | Create a post (draft by default) | `readonly: false`, `idempotent: false` |
| `content/update-post` | Update fields of one post | `readonly: false`, `idempotent: true` |

### Example inputs

`content/find-posts`

```json
{ "search": "release notes", "post_type": "post", "page": 1, "per_page": 10, "include_drafts": false }
```

`content/get-post`

```json
{ "id": 42 }
```

`content/create-post`

```json
{
  "title": "Hello from an agent",
  "content": "<p>Written via MCP.</p>",
  "status": "draft",
  "post_type": "post",
  "categories": ["News"],
  "tags": ["ai", "mcp"]
}
```

`content/update-post`

```json
{ "id": 42, "title": "Updated title", "status": "publish", "tags": ["mcp"] }
```

## Permissions

Every ability enforces WordPress capabilities; failures return a structured `WP_Error` with code `content_forbidden`, `content_post_not_found`, `content_invalid_post_type`, or `content_empty_content`.

| Ability | Required capability |
|---------|--------------------|
| `content/find-posts` | `read` for published posts; `edit_posts` when `include_drafts` is true |
| `content/get-post` | Object-level `read_post` — private posts need `read_private_posts` or ownership |
| `content/create-post` | Post-type create capability (`edit_posts`); `publish_posts` when `status: publish` |
| `content/update-post` | Object-level `edit_post`; `publish_post` when publishing a draft |

Sanitization: titles/excerpts via `sanitize_text_field()`, content via `wp_kses_post()` (scripts and event handlers stripped), term names sanitized before assignment.

## Installation

### From source (Git)

```bash
git clone https://github.com/shekohex/wordpress-content-abilities.git \
  wp-content/plugins/wordpress-content-abilities
composer install --no-dev
```

Activate **Content Abilities** in wp-admin, then activate the **MCP Adapter** plugin.

### Release package

Build a distributable zip (excludes dev dependencies):

```bash
composer install --no-dev --optimize-autoloader
zip -r wordpress-content-abilities.zip \
  wordpress-content-abilities.php src readme.txt uninstall.php LICENSE
```

## Connecting MCP clients

With the [MCP Adapter](https://wordpress.org/plugins/mcp-adapter/) active, abilities are exposed through the default MCP server at `/wp-json/mcp/mcp-adapter-default-server` and via WP-CLI STDIO transport. Authentication is application-passwords/OAuth 2.0 as provided by the MCP Adapter; nothing is exposed anonymously.

Claude Desktop example (HTTP):

```json
{
  "mcpServers": {
    "wordpress": {
      "url": "https://your-site.example.com/wp-json/mcp/mcp-adapter-default-server"
    }
  }
}
```

See the [MCP Adapter docs](https://github.com/WordPress/mcp-adapter/tree/trunk/docs) for STDIO and proxy setups.

## Development

```bash
composer install
composer test        # PHPUnit
composer analyse     # PHPStan (level 8)
composer sniff       # PHPCS with WordPress Coding Standards
composer check       # all of the above
```

The test suite runs against in-memory WordPress stubs (no database required).

### Architecture

```
wordpress-content-abilities.php   Bootstrap: plugin header, hooks registration
src/
  Load.php                        Boot loader (no Composer needed in production)
  Plugin.php                      Category + ability registration (thin WP layer)
  Container.php                   Minimal auto-wiring DI container
  Contracts/AbilityContract.php   Ability interface
  Abilities/                      One final class per ability + shared base
  Services/PostService.php        Business logic: validation, caps, sanitization
  Repositories/PostRepository.php The only layer touching WP post/term functions
  Support/                        CapabilityGuard, PostTypes helpers
tests/                            PHPUnit tests + WP stubs
```

## License

[GPL-2.0-or-later](LICENSE)
