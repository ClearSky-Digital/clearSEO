"""Output tests for CSD SEO on a running local WordPress (Docker compose with a `cli` service running WP-CLI).

Set CSD_SEO_TEST_COMPOSE to the compose folder and CSD_SEO_TEST_URL to the site address (default http://127.0.0.1:8095).
The plugin must be active. The script changes the plugin option and test pages, and restores the option at the end.
Run: python tests/run-tests.py
"""
import json
import os
import re
import subprocess
import sys
import urllib.request

COMPOSE = os.environ.get("CSD_SEO_TEST_COMPOSE")
BASE = os.environ.get("CSD_SEO_TEST_URL", "http://127.0.0.1:8095")
if not COMPOSE:
    sys.exit("Set CSD_SEO_TEST_COMPOSE to the folder that holds the compose file.")

ENV = dict(os.environ, MSYS_NO_PATHCONV="1")
fails = []


def wp(*args):
    r = subprocess.run(["docker", "compose", "exec", "-T", "cli", "wp", "--path=/var/www/html", *args],
                       cwd=COMPOSE, capture_output=True, text=True, env=ENV)
    return r.stdout.strip()


def get(path):
    try:
        with urllib.request.urlopen(BASE + path) as r:
            return r.status, r.read().decode("utf8", "replace"), dict(r.headers)
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode("utf8", "replace"), dict(e.headers)


def check(name, ok, detail=""):
    print(("PASS " if ok else "FAIL ") + name + ("" if ok else "  " + detail))
    if not ok:
        fails.append(name)


def set_opt(**kw):
    base = {"mod_head": 0, "mod_social": 0, "mod_schema": 0, "mod_sitemap": 0, "mod_robots": 0, "mod_llms": 0}
    base.update(kw)
    wp("option", "update", "csd_seo", json.dumps(base), "--format=json")


saved = wp("option", "get", "csd_seo", "--format=json")
page = wp("post", "create", "--post_type=page", "--post_status=publish", "--post_title=CSD Test Page",
          "--post_content=Hello", "--porcelain")
hidden = wp("post", "create", "--post_type=page", "--post_status=publish", "--post_title=CSD Hidden Page",
            "--post_content=Hidden", "--porcelain")
wp("post", "meta", "update", page, "_csd_seo_desc", "A test description for the page.")
wp("post", "meta", "update", page, "_csd_seo_title", "Custom Title Here")
wp("post", "meta", "update", hidden, "_csd_seo_noindex", "1")

try:
    # 1. Everything off: no plugin output at all.
    set_opt()
    _, html, _ = get("/?page_id=" + page)
    check("off: no description", 'name="description"' not in html)
    check("off: no og tags", "og:title" not in html)
    check("off: no json-ld", "ld+json" not in html)
    check("off: title not overridden", "Custom Title Here" not in html)
    s, txt, _ = get("/llms.txt")
    check("off: llms.txt is 404", s == 404, str(s))
    _, robots, _ = get("/robots.txt")
    check("off: robots.txt is core's", "Content-Signal" not in robots)

    # 2. Head module.
    set_opt(mod_head=1)
    _, html, _ = get("/?page_id=" + page)
    check("head: description", '<meta name="description" content="A test description for the page.">' in html)
    check("head: title override", "<title>Custom Title Here" in html)
    _, h2, _ = get("/?page_id=" + hidden)
    check("head: noindex", "noindex" in h2)
    check("head: indexable page has no noindex", "noindex" not in html)
    check("head: core canonical kept", 'rel="canonical"' in html)

    # 3. Social.
    set_opt(mod_social=1, default_image="https://example.com/default.png")
    _, html, _ = get("/?page_id=" + page)
    check("social: og:title", 'property="og:title"' in html)
    check("social: og:description", 'property="og:description" content="A test description' in html)
    check("social: default image", 'property="og:image" content="https://example.com/default.png"' in html)
    check("social: twitter card", 'name="twitter:card" content="summary_large_image"' in html)

    _, srch, _ = get("/?s=test")
    check("social: none on search pages", "og:title" not in srch)

    # 4. Schema.
    set_opt(mod_schema=1, biz_type="RealEstateAgent", biz_name="Test Realty", biz_street="1 Main St",
            biz_locality="Honolulu", biz_region="HI", biz_postal="96814", biz_country="US",
            biz_same_as="https://example.com/profile")
    _, html, _ = get("/?page_id=" + page)
    start = html.find('<script type="application/ld+json">')
    data = None
    if start >= 0:
        body = html[start + len('<script type="application/ld+json">'):html.find("</script>", start)]
        try:
            data = json.loads(body)
        except ValueError:
            data = None
    check("schema: valid JSON", data is not None)
    types = [n.get("@type") for n in (data or {}).get("@graph", [])]
    check("schema: RealEstateAgent, WebSite, WebPage, BreadcrumbList", types == ["RealEstateAgent", "WebSite", "WebPage", "BreadcrumbList"], str(types))
    biz = (data or {}).get("@graph", [{}])[0]
    check("schema: address only from entered facts", biz.get("address", {}).get("streetAddress") == "1 Main St" and "telephone" not in biz)
    _, home, _ = get("/")
    check("schema: home has no breadcrumb", "BreadcrumbList" not in home)

    # 5. Sitemap.
    set_opt(mod_sitemap=1)
    _, idx, _ = get("/wp-sitemap.xml")
    check("sitemap: no users", "wp-sitemap-users" not in idx)
    check("sitemap: no taxonomies by default", "wp-sitemap-taxonomies" not in idx)
    check("sitemap: pages present", "wp-sitemap-posts-page-1.xml" in idx)
    _, pages, _ = get("/wp-sitemap-posts-page-1.xml")
    check("sitemap: noindex page excluded", "CSD+Hidden" not in pages and "page_id=" + hidden not in pages and "csd-hidden-page" not in pages)
    check("sitemap: test page included", "csd-test-page" in pages or "page_id=" + page in pages)
    wp("post", "meta", "update", page, "_csd_seo_noindex", "0")
    _, pages, _ = get("/wp-sitemap-posts-page-1.xml")
    check("sitemap: noindex stored as 0 stays listed", "csd-test-page" in pages)
    wp("post", "meta", "delete", page, "_csd_seo_noindex")
    set_opt()
    _, idx, _ = get("/wp-sitemap.xml")
    check("sitemap off: users back", "wp-sitemap-users" in idx)

    # 6. robots.txt.
    set_opt(mod_robots=1, robots_signal="search=yes, ai-input=yes, ai-train=no", robots_block="GPTBot\nCCBot")
    _, robots, _ = get("/robots.txt")
    check("robots: signal", "Content-Signal: search=yes, ai-input=yes, ai-train=no" in robots)
    check("robots: Googlebot group", "User-agent: Googlebot" in robots)
    check("robots: blocked agent", "User-agent: GPTBot\nUser-agent: CCBot\nDisallow: /" in robots)
    check("robots: sitemap line", "Sitemap: " in robots and "wp-sitemap.xml" in robots)
    check("robots: no admin blocked css", "Disallow: /wp-content" not in robots and "Disallow: /wp-includes" not in robots)

    # 7. llms.txt.
    set_opt(mod_llms=1, default_desc="A test business.", biz_phone="555-0100")
    s, txt, hdr = get("/llms.txt")
    check("llms: 200 plain text", s == 200 and hdr.get("Content-Type", "").startswith("text/plain"), str(s))
    check("llms: heading and summary", txt.startswith("# Demo Site") and "> A test business." in txt)
    check("llms: indexable page listed", "CSD Test Page" in txt)
    check("llms: noindex page absent", "CSD Hidden Page" not in txt)
    check("llms: no email", re.search(r"[\w.+-]+@[\w-]+\.[\w.-]+", txt) is None)
    # 8. Security hardening (review 2026-11-17).
    pw = wp("post", "create", "--post_type=page", "--post_status=publish", "--post_title=CSD Locked Page",
            "--post_content=Locked", "--post_password=secret1", "--porcelain")
    wp("post", "meta", "update", pw, "_csd_seo_desc", "PWSECRETDESC")
    wp("post", "meta", "update", pw, "_csd_seo_title", "PWSECRETTITLE")
    _, rest, _ = get("/wp-json/wp/v2/pages/" + pw)
    check("rest: protected page SEO text not shown to anonymous readers", "PWSECRETDESC" not in rest and "PWSECRETTITLE" not in rest)
    _, rest, _ = get("/wp-json/wp/v2/pages/" + page)
    check("rest: no SEO meta for anonymous readers on any page", "A test description" not in rest and "Custom Title Here" not in rest)

    messy = wp("post", "create", "--post_type=page", "--post_status=publish",
               "--post_title=Line one\n## Injected\n- [x](http://evil)", "--post_content=x", "--porcelain")
    set_opt(mod_llms=1)
    _, txt, _ = get("/llms.txt")
    check("llms: title with newlines stays on one line", not re.search(r"^## Injected", txt, re.M) and "Line one ## Injected" in txt, txt)
    check("llms: brackets in titles are escaped", "\\[x\\]" in txt, txt)
    wp("post", "delete", messy, "--force")

    set_opt(mod_social=1, default_image="javascript:alert(1)")
    _, html, _ = get("/?page_id=" + page)
    check("social: og:image never carries a javascript: value", "javascript:" not in html)
    set_opt(mod_social=1, mod_head=1, default_image="https://example.com/default.png")
    _, h2, _ = get("/?page_id=" + hidden)
    check("social: no social tags on a noindex page", "og:title" not in h2 and "twitter:card" not in h2)
    _, html, _ = get("/?page_id=" + page)
    check("social: indexable page still has them", "og:title" in html)
    wp("post", "delete", pw, "--force")

    bs = wp("post", "create", "--post_type=page", "--post_status=publish",
            "--post_title=Back \\[x\\](http://evil.example) end\\", "--post_content=x", "--porcelain")
    set_opt(mod_llms=1)
    _, txt, _ = get("/llms.txt")
    check("llms: backslashes in titles are escaped first", "[Back \\\\\\[x\\\\\\](http://evil.example) end\\\\]" in txt, txt)
    wp("post", "delete", bs, "--force")

    set_opt(mod_social=1, default_image="javascript:alert(1)")
    _, html, _ = get("/?page_id=" + page)
    check("social: no image tags when the address is unsafe", "og:image" not in html and "twitter:image" not in html and "twitter:card" in html)
    pw2 = wp("post", "create", "--post_type=page", "--post_status=publish", "--post_title=CSD Locked Two",
             "--post_content=x", "--post_password=pw", "--porcelain")
    set_opt(mod_social=1, default_image="https://example.com/default.png")
    _, h3, _ = get("/?page_id=" + pw2)
    check("social: no social tags on a password page", "og:title" not in h3 and "twitter:card" not in h3)
    wp("post", "delete", pw2, "--force")
    set_opt(mod_social=1, mod_head=1, default_image="https://example.com/default.png")

    here = os.path.dirname(os.path.abspath(__file__))
    root = os.path.dirname(here)
    unins = open(os.path.join(root, "uninstall.php"), encoding="utf-8").read()
    check("uninstall: multisite lists every site", "'number' => 0" in unins)
    metaphp = open(os.path.join(root, "includes", "meta.php"), encoding="utf-8").read()
    check("meta box nonce is tied to the post", "'csd_seo_save_' . $post->ID" in metaphp and "'csd_seo_save_' . $post_id" in metaphp)
finally:
    wp("post", "delete", page, hidden, "--force")
    if saved:
        wp("option", "update", "csd_seo", saved, "--format=json")
    else:
        wp("option", "delete", "csd_seo")

print("\n%d failure(s)" % len(fails))
sys.exit(1 if fails else 0)
