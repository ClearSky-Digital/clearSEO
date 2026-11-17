# CSD SEO

A small WordPress plugin that controls the SEO outputs of a site on core APIs only. No dependencies, no update cycle to follow, nothing runs until a module is switched on in Settings, CSD SEO.

Built and maintained by [ClearSky Digital](https://cscloud.digital).

| Module | What it does | Core API |
|---|---|---|
| Page head | Meta description, title override and noindex per page | `document_title_parts`, `wp_robots`, `wp_head` |
| Social tags | Open Graph and Twitter tags from the description and featured image, with a site default image | `wp_head` |
| Structured data | One JSON-LD graph: business (any listed subtype), WebSite, WebPage, BreadcrumbList | `wp_head` |
| Sitemap trim | No users sitemap, only chosen taxonomies, no noindex pages | `wp_sitemaps_*` |
| robots.txt | Standard text: search and AI answer crawlers allowed, listed crawlers blocked, Content-Signal, sitemap line | `robots_txt` |
| llms.txt | `/llms.txt` in the llmstxt.org layout from indexable pages | `parse_request` (no rewrite rules) |

With the sitemap trim on, only taxonomies ticked in the settings stay in the sitemap (none by default). The robots.txt module writes the whole text, so another plugin's additions to robots.txt are not kept.

The canonical link stays with core (`rel_canonical`).

Per-page fields are plain post meta (`_csd_seo_title`, `_csd_seo_desc`, `_csd_seo_noindex`) registered with `show_in_rest`, edited in a small native meta box. No other plugin is needed.

Business facts are entered by hand in the settings. Empty fields are left out of the structured data; a postal address is output only for a local business type with a street and city.

Uninstalling deletes the `csd_seo` option and the three meta keys. The plugin adds no rewrite rules, tables or files.

Tests: `tests/run-tests.py` (see the file header).
