# GoBackpackGo Core (v0.3.0)

Small own plugin that takes over a few jobs from paid plugins. **Every feature is off after installing**; nothing on the site changes until a setting is switched on.

| Feature | Setting | What it does |
|---|---|---|
| Noindex list | `noindex_enabled`, `noindex_ids`, `protected_ids` | `noindex, follow` via Yoast (`wpseo_robots`) and WordPress core (`wp_robots`), and out of the Yoast sitemaps. IDs in `protected_ids` (pages with backlinks) are never noindexed. |
| Affiliate disclosure | `disclosure_enabled`, `disclosure_text`, `disclosure_url` | One short line above the content on single posts/pages that contain affiliate links (known hosts or `rel="sponsored"`), unless the page already has a disclosure. |
| Travelpayouts SubIDs | `subid_enabled`, `subid_hosts`, `subid_lang`, `subid_whole_page` | Adds `sub_id=<subid>` to `tp.media` links that have none. SubID = post meta `_gbg_subid`, else `p<ID>`; with `subid_lang` also `-<lang>` when GTranslate sends `X-GT-Lang`. |
| Yoast fields of pages in REST | always on | `_yoast_wpseo_title`, `_yoast_wpseo_metadesc`, `_yoast_wpseo_focuskw` readable/writable via `/wp/v2/pages/<id>` for users who may edit that page (posts already have this). Changes nothing on the site by itself. |

Undo: switch the setting off, or deactivate the plugin. The plugin deletes nothing and edits no posts; it only filters output.

## Settings (administrators + the `claude-editor` account; filter `gbg_core_settings_logins`)

```
GET  /wp-json/gbg/v1/settings
POST /wp-json/gbg/v1/settings   {"subid_enabled": true}
```

Noindex payload from an approved prune list: `scripts/build_noindex_payload.py <prune-list.csv> [--cluster X] [--limit N]`.

## MCP lock (0.1.6)

`mcp_lock_enabled` (default off): anonymous and non-admin requests to `/wp-json/mcp/mcp-adapter-default-server` get 401; administrators and the OAuth MCP server (`/wp-json/mcp/mcp-oauth-server`) are not affected. Switch on/off with `POST /wp-json/gbg/v1/settings {"mcp_lock_enabled": true}`.

## Redirection access (0.1.7)

`redirection_access` (default off): the settings logins (`claude-editor`) may use the Redirection plugin (redirects and 404 log); other editors may not. Switch with `POST /wp-json/gbg/v1/settings {"redirection_access": true}`.

## Updates (0.1.8)

New versions are published in the public repo github.com/prnl85/gbg-core (`update.json` with `version` + `package` = archive of branch `release-<version>`). WordPress shows them under Dashboard → Updates (Update URI header); the archive folder is renamed to `gbg-core/` on install. Staging (cloudwaysapps.com) auto-updates; live needs one click by an administrator.

Release: `scripts/release_gbg_core.sh` (branch `release-<version>` first, then `update.json` on main). 0.3.2 (21-10) was the first end-to-end test of the channel: no functional change.

Staging self-update (0.3.8): `POST /wp-json/gbg/v1/self-update` (settings logins, staging host only) installs the newest release immediately; `scripts/release_gbg_core.sh` calls it after publishing. Live never updates itself.

## Health and hardening (0.1.9)

`GET /wp-json/gbg/v1/health` (settings logins only): WordPress/PHP version, pending core and plugin updates, plugins with versions, DISALLOW_FILE_EDIT, debug display, number of administrators, XML-RPC. Read by `scripts/security_audit.py`.
Switches (default off): `xmlrpc_off`, `author_scan_block` (?author=N → 404 for visitors), `users_api_private` (/wp/v2/users only when logged in), `hide_generator`.

## A/B tests (0.2.0)

Setting `ab_tests` (list of tests: id, status running|paused, path regex, CSS selector, 2-4 variants with optional text/class, sample 1-20). On matching pages, visitors who are not logged in get a ~1 KB inline script: random variant per page view (no cookies or storage), button text/class changed, affiliate `sub_id` tagged `-<test><variant>`, view (sampled) and click beacons to `POST /wp-json/gbg/v1/ab/hit` (open only while a test runs; only known test/variant/event; daily totals, capped). `GET /wp-json/gbg/v1/ab` returns tests and counts (settings logins). Managed by `scripts/ab.py`.

## Growth: lead magnet sign-ups (0.3.0)

Kill switch `leads_enabled` (default off). `lead_magnets`: id, title, pitch, button, thanks, bar, files (label + https URL), tags (e.g. `country:thailand`). `growth_rules`: id, status running|paused, magnet, `category` slug (incl. sub-categories) or `path` regex, `after` (paragraph N, 0 = end), `mobile_bar` + `bar_scroll` (%). Shortcode `[gbg_optin magnet="id"]`.
- Sign-ups: `POST /wp-json/gbg/v1/lead` (public while on): honeypot, min. 2.5 s, 5 per hour per hashed IP. Stored in `wp_gbg_leads` (one row per address, tags merged): country/continent/topic from the page's categories, magnet, rule, page, lang (from /xx/ path), src (utm_source or referrer host), consent version + time. The gift is shown right away (single opt-in).
- `GET /gbg/v1/leads/stats` (settings logins): counts per tag group and week, no addresses. Export: Tools → GoBackpackGo leads (CSV, administrators) or `GET /gbg/v1/leads/export` (administrators). Erasure: `POST /gbg/v1/leads/delete {"email": ...}` (administrators). Unsubscribe link: `/wp-json/gbg/v1/lead/unsubscribe?e=<email>&k=<key from export>`.
- Front end: no cookies; localStorage flags only (signed up, bar dismissed for 14 days). GA4 event `generate_lead` when gtag exists. No overlay pop-ups.

## eSIM pages 2.0 (0.3.46)
- `GET/POST /gbg/v1/page-block` reads or writes a shared Impreza block (`us_page_block`, `wp_block`): content, `used_by` (published pages that include it), backups. A write keeps the previous content (last 10); `expect_modified` refuses a write when the block changed in between. `POST /gbg/v1/page-block/restore {id, at}` puts a backup back.
- `GET/POST /gbg/v1/esim-data`: from-prices per eSIM page slug and the compatible-device list, sent by the weekly Yesim check. Shortcodes `[gbg_esim_price]`, `[gbg_esim_devices]`, `[gbg_esim_checked format="day|month"]` show them; nothing is shown without data.
- 0.3.47: devices as one closed `<details>` per brand (with the model count), small stylesheet printed once with the first eSIM shortcode.
- 0.3.48: install steps as three short numbered cards (`ol.gbg-esim-steps`), stylesheet also printed with the checked date.
- Setting `esim_faq_fix`: replaces the Afghan city list in the generated coverage FAQ (not on the Afghanistan page).

## Nearby links (0.3.49)
- `GET/POST /gbg/v1/nearby` stores per page slug a heading and up to 8 links (own relative paths only), built by `scripts/nearby_links.py` from `config/car-rental-regions.json`. Setting `nearby_enabled` appends them as a short section at the end of the main content; off removes them everywhere. 0.3.50: own 16px gutter (full-width rows on cluster pages).

## Impreza layout meta (0.3.51)
- `us_header_id`, `us_titlebar_id`, `us_content_id`, `us_footer_id` on pages are readable and writable via the REST API (editors of that page only; values `__defaults__`, empty or an ID), so a new page can copy the layout of its siblings.

## Build, test, install

- Test: `php wordpress/tests/gbg-core-test.php <dir with WordPress wp-includes/html-api files + class-wp-token-map.php>`
- Zip: `scripts/build_gbg_core_zip.sh` → `dist/gbg-core-<version>.zip`
- Install on **staging**: Plugins → Add New → Upload Plugin → zip → Activate. Live only after Paul's separate approval.

## To verify on staging

1. With everything off: pages identical (visual QA before/after activation).
2. SubID: a test click on a `tp.media` link shows the SubID in Travelpayouts statistics (format check).
3. Whether GTranslate sends `X-GT-Lang` to the origin and whether Varnish varies on it. Only then switch on `subid_lang`, otherwise cached HTML could carry the wrong language.
4. Disclosure position inside Impreza/WPBakery layouts.
