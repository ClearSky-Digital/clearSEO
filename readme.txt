=== CSD SEO ===
Contributors: clearskydigital
Tags: seo, schema, sitemap, robots, llms
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Small SEO outputs on core WordPress APIs: titles, descriptions, JSON-LD, sitemap, robots.txt and llms.txt. Every module is off until you turn it on.

== Description ==

CSD SEO controls the SEO outputs of a site on core APIs only. No dependencies, nothing runs until a module is switched on in Settings, CSD SEO.

* Page head: meta description, title override and noindex per page.
* Social tags: Open Graph and Twitter tags.
* Structured data: one JSON-LD graph with the business, WebSite, WebPage and BreadcrumbList.
* Sitemap trim: no users sitemap, chosen taxonomies only, no noindex pages.
* robots.txt: standard text with search and answer crawlers allowed, listed crawlers blocked, and a sitemap line.
* llms.txt: a /llms.txt file from indexable pages.

Built and maintained by [ClearSky Digital](https://cscloud.digital).

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate it, then open Settings, CSD SEO and switch on the modules you want.

== Privacy ==

The plugin collects no user data and makes no requests to other sites. Uninstalling removes its option and its three per-page fields.

== Changelog ==

= 1.0.0 =
* First release. Pages marked noindex or protected by a password get no Open Graph or Twitter tags, so they show no preview when shared.
