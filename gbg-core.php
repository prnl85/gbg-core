<?php
/**
 * Plugin Name: !GoBackpackGo Core
 * Description: Small site helpers for GoBackpackGo: noindex for an approved list of posts, an automatic affiliate disclosure, Travelpayouts SubIDs and Yoast fields of pages in the REST API, an optional lock on the public MCP endpoint, optional Redirection access for Claude's account, updates through the normal WordPress Updates screen, a security health report, optional hardening switches, lightweight A/B tests and a growth module that collects tagged email sign-ups for lead magnets. Every output feature is off until switched on.
 * Version: 0.3.48
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Author: GoBackpackGo
 * License: GPL-2.0-or-later
 * Text Domain: gbg-core
 * Update URI: https://github.com/prnl85/gbg-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const GBG_CORE_VERSION = '0.3.48';
const GBG_CORE_OPTION  = 'gbg_core_settings';

/**
 * Settings with safe defaults: nothing changes on the site until a feature is enabled.
 */
function gbg_core_defaults(): array {
	return array(
		'noindex_enabled'    => false,
		'noindex_ids'        => array(),
		// Posts that must never be noindexed (e.g. pages with backlinks), even if they are on the list.
		'protected_ids'      => array(),
		'disclosure_enabled' => false,
		'disclosure_text'    => 'This page contains affiliate links. If you book through one of them, GoBackpackGo may earn a small commission at no extra cost to you.',
		'disclosure_link_text' => 'How we make money',
		'disclosure_url'     => '',
		'affiliate_hosts'    => array(
			'tp.media', 'tp.st', 'tpk.lv', 'travelpayouts.com', 'aviasales.com', '12go.asia', '12go.co',
			'getyourguide.com', 'klook.com', 'airhelp.com', 'kiwitaxi.com', 'booking.com', 'agoda.com',
			'airalo.com', 'discovercars.com', 'safetywing.com',
		),
		'subid_enabled'      => false,
		// Append "-<lang>" from GTranslate's X-GT-Lang header. Only switch on once the cache varies on that header.
		'subid_lang'         => false,
		// Hosts whose links get a sub_id query parameter. Short links (tp.st, tpk.lv) are redirects and cannot carry one.
		'subid_hosts'        => array( 'tp.media' ),
		// Also tag links outside the main content (Impreza footer blocks, sticky mobile eSIM button) on single views.
		'subid_whole_page'   => true,
		// Only administrators may use the default MCP server (/wp-json/mcp/mcp-adapter-default-server); others get 401.
		'mcp_lock_enabled'   => false,
		// Let the settings logins (Claude's editor account) use the Redirection plugin (redirects + 404 log), nothing else.
		'redirection_access' => false,
		// Hardening switches (security dashboard). Each one is off until switched on after a staging test.
		'xmlrpc_off'         => false, // XML-RPC off (brute force and pingback abuse)
		'author_scan_block'  => false, // ?author=N without login gives 404 instead of revealing the login name
		'users_api_private'  => false, // /wp/v2/users only for logged-in users
		'hide_generator'     => false, // no WordPress version in the HTML and feeds
		// A/B tests (see gbg_core_ab_sanitize). Empty = no script on any page.
		'ab_tests'           => array(),
		// Growth: email sign-ups for lead magnets, stored in the site's own table (see section "Growth"). Off = no box,
		// no bar, and the public sign-up route answers 404.
		'leads_enabled'      => false,
		'lead_magnets'       => array(),
		'growth_rules'       => array(),
		'consent_version'    => 'v1',
		'privacy_url'        => '',
		// Save & share bar for readers (Pinterest, WhatsApp, e-mail, copy link, PDF) + Pinterest buttons on images.
		'share_enabled'      => false,
		'share_position'     => 'top', // top (after the first paragraph) | bottom | both
		// Identity + future-self framing (Paul 21-10: 'compliment them for saving'). A/B-testable via this setting.
		'share_label'        => 'Smart move: save this guide now, so it is there when you need it on the road.',
		'pin_enabled'        => false,
		'pin_min_width'      => 300,
		'lightbox_fix'       => true,
		'person_same_as'     => array(),
		// Pages without the save bar (legal, contact, link pages).
		'share_skip_slugs'   => array( 'privacy-policy', 'contact', 'links', 'about-me', 'how-gobackpackgo-makes-money', 'sitemap' ),
		// Generated affiliate links (scripts/affiliate_links.py): slug => platform URL, served as /go/<slug>/. Empty = Lasso.
		'go_links'           => array(),
		'go_hosts'           => array( 'tpx.gr', 'tp.st', 'tpk.lv', 'tp.media', 'pxf.io', 'sjv.io', '7eer.net', 'ojrq.net', 'discovercars.com' ),
		// Per-country targets (scripts/go_country.py): go slug => country => URL, and page slug => country. On such a page
		// /go/<slug>/ links get ?c=<country>&s=p<ID>; the redirect then uses that country's link with the page as sub_id.
		'go_country'         => array(),
		'go_pages'           => array(),
		// Promo codes from the platforms: merchant, code, text, until (Y-m-d), note. Shown by [gbg_promo] until their end date.
		'promos'             => array(),
		// GA4 events (Paul 21-10: measure everything to optimise conversions). GA4 is loaded through GTM, so window.gtag
		// does not exist; gbgEv() falls back to the gtag command queue in dataLayer (read by the GTM Google tag) and also
		// pushes a GTM event object (gbg_<name>) for GTM triggers.
		'track_enabled'      => false,
		// GA4 Measurement Protocol (0.3.26): events are sent by the server, so they arrive even when GTM ignores page
		// commands, an ad blocker is on or the page leaves for the partner at once. The API secret is kept in its own
		// option (Tools > GBG Core GA4), never in these settings or the REST API.
		'ga4_measurement_id' => '',
		// Speed (0.3.37, mobile LCP): serve the WebP copies Imagify already made (<file>.jpg.webp) for own uploads in
		// <img src/srcset> and CSS backgrounds, and preload the first background image (the hero) with high priority.
		'webp_enabled'       => false,
		// 0.3.39: theme images (Impreza us_image, class attachment-<size>) get the attachment's srcset with sizes=auto, so
		// a phone loads a smaller file; Google Fonts served from our own server once POST /gbg/v1/local-fonts stored them.
		'srcset_enabled'     => false,
		'local_fonts'        => false,
		// 0.3.41: small PNG/ICO site icons instead of the 181 KB traced SVG (requested ~7,500 times a day).
		'light_icons'        => false,
		// 0.3.43: robots.txt keeps Baiduspider to the English and Chinese pages (Paul 22-10: it crawled all GTranslate
		// languages, ~260 real visits a year come from Baidu, all of them kept).
		'baidu_limit'        => false,
		// 0.3.46: replace the Afghan city list in the coverage FAQ of the generated eSIM country pages.
		'esim_faq_fix'       => false,
	);
}

function gbg_core_settings( bool $refresh = false ): array {
	static $settings = null;
	if ( null === $settings || $refresh ) {
		$stored   = get_option( GBG_CORE_OPTION, array() );
		$settings = array_merge( gbg_core_defaults(), is_array( $stored ) ? $stored : array() );
	}
	return $settings;
}

function gbg_core_sanitize( array $input ): array {
	$clean = array();
	foreach ( array( 'noindex_enabled', 'disclosure_enabled', 'subid_enabled', 'subid_lang', 'subid_whole_page', 'mcp_lock_enabled', 'redirection_access', 'xmlrpc_off', 'author_scan_block', 'users_api_private', 'hide_generator', 'leads_enabled', 'pin_enabled', 'share_enabled', 'lightbox_fix', 'track_enabled', 'webp_enabled', 'srcset_enabled', 'local_fonts', 'light_icons', 'baidu_limit', 'esim_faq_fix' ) as $key ) {
		if ( array_key_exists( $key, $input ) ) {
			$clean[ $key ] = (bool) $input[ $key ];
		}
	}
	foreach ( array( 'noindex_ids', 'protected_ids' ) as $key ) {
		if ( array_key_exists( $key, $input ) ) {
			$clean[ $key ] = array_values( array_unique( array_filter( array_map( 'absint', (array) $input[ $key ] ) ) ) );
		}
	}
	foreach ( array( 'affiliate_hosts', 'subid_hosts' ) as $key ) {
		if ( array_key_exists( $key, $input ) ) {
			$hosts         = array_map( static fn( $h ) => strtolower( trim( (string) $h ) ), (array) $input[ $key ] );
			$clean[ $key ] = array_values( array_unique( array_filter( $hosts, static fn( $h ) => (bool) preg_match( '/^[a-z0-9.-]+$/', $h ) ) ) );
		}
	}
	foreach ( array( 'disclosure_text', 'disclosure_link_text' ) as $key ) {
		if ( array_key_exists( $key, $input ) ) {
			$clean[ $key ] = sanitize_text_field( (string) $input[ $key ] );
		}
	}
	if ( array_key_exists( 'person_same_as', $input ) ) {
		$clean['person_same_as'] = array_values( array_filter( array_map( 'esc_url_raw', (array) $input['person_same_as'] ), static fn( $u ) => str_starts_with( $u, 'https://' ) ) );
	}
	if ( array_key_exists( 'share_skip_slugs', $input ) ) {
		$clean['share_skip_slugs'] = array_values( array_filter( array_map( 'sanitize_title', (array) $input['share_skip_slugs'] ) ) );
	}
	if ( array_key_exists( 'share_label', $input ) ) {
		$clean['share_label'] = sanitize_text_field( (string) $input['share_label'] );
	}
	if ( array_key_exists( 'share_position', $input ) && in_array( $input['share_position'], array( 'top', 'bottom', 'both' ), true ) ) {
		$clean['share_position'] = $input['share_position'];
	}
	if ( array_key_exists( 'pin_min_width', $input ) ) {
		$clean['pin_min_width'] = max( 100, min( 2000, absint( $input['pin_min_width'] ) ) );
	}
	if ( array_key_exists( 'disclosure_url', $input ) ) {
		$clean['disclosure_url'] = esc_url_raw( (string) $input['disclosure_url'] );
	}
	if ( array_key_exists( 'ab_tests', $input ) ) {
		$clean['ab_tests'] = gbg_core_ab_sanitize( (array) $input['ab_tests'] );
	}
	if ( array_key_exists( 'lead_magnets', $input ) ) {
		$clean['lead_magnets'] = gbg_core_magnets_sanitize( (array) $input['lead_magnets'] );
	}
	if ( array_key_exists( 'growth_rules', $input ) ) {
		$clean['growth_rules'] = gbg_core_rules_sanitize( (array) $input['growth_rules'] );
	}
	if ( array_key_exists( 'consent_version', $input ) ) {
		$clean['consent_version'] = substr( preg_replace( '/[^a-z0-9._-]/i', '', (string) $input['consent_version'] ), 0, 20 ) ?: 'v1';
	}
	if ( array_key_exists( 'privacy_url', $input ) ) {
		$clean['privacy_url'] = esc_url_raw( (string) $input['privacy_url'] );
	}
	if ( array_key_exists( 'ga4_measurement_id', $input ) ) {
		$clean['ga4_measurement_id'] = preg_match( '/^G-[A-Z0-9]{4,15}$/', strtoupper( trim( (string) $input['ga4_measurement_id'] ) ) ) ? strtoupper( trim( (string) $input['ga4_measurement_id'] ) ) : '';
	}
	if ( array_key_exists( 'go_hosts', $input ) ) {
		$hosts             = array_map( static fn( $h ) => strtolower( trim( (string) $h ) ), (array) $input['go_hosts'] );
		$clean['go_hosts'] = array_values( array_unique( array_filter( $hosts, static fn( $h ) => (bool) preg_match( '/^[a-z0-9.-]+$/', $h ) ) ) );
	}
	if ( array_key_exists( 'go_links', $input ) ) {
		$clean['go_links'] = gbg_core_go_sanitize( (array) $input['go_links'], $clean['go_hosts'] ?? gbg_core_settings()['go_hosts'] );
	}
	if ( array_key_exists( 'promos', $input ) ) {
		$clean['promos'] = gbg_core_promos_sanitize( (array) $input['promos'] );
	}
	if ( array_key_exists( 'go_country', $input ) ) {
		$hosts               = $clean['go_hosts'] ?? gbg_core_settings()['go_hosts'];
		$clean['go_country'] = array();
		foreach ( (array) $input['go_country'] as $slug => $map ) {
			$map = gbg_core_go_sanitize( (array) $map, $hosts ); // same rules: key [a-z0-9-], https, allowlisted host
			if ( $map && preg_match( '/^[a-z0-9-]{1,60}$/', (string) $slug ) ) {
				$clean['go_country'][ (string) $slug ] = $map;
			}
		}
	}
	if ( array_key_exists( 'go_pages', $input ) ) {
		$clean['go_pages'] = array();
		foreach ( (array) $input['go_pages'] as $page => $country ) {
			if ( preg_match( '/^[a-z0-9-]{1,60}$/', (string) $country ) && sanitize_title( (string) $page ) === (string) $page ) {
				$clean['go_pages'][ (string) $page ] = (string) $country;
			}
		}
	}
	return $clean;
}

/* -------------------------------------------------------------------------
 * 1. Noindex for the approved prune list (via Yoast, with a core fallback).
 * ---------------------------------------------------------------------- */

function gbg_core_is_noindexed( int $post_id ): bool {
	$s = gbg_core_settings();
	return $s['noindex_enabled']
		&& in_array( $post_id, $s['noindex_ids'], true )
		&& ! in_array( $post_id, $s['protected_ids'], true );
}

function gbg_core_current_noindexed(): bool {
	return is_singular() && gbg_core_is_noindexed( (int) get_queried_object_id() );
}

// Yoast SEO: robots meta. "follow" keeps internal links passing value.
add_filter(
	'wpseo_robots',
	static function ( $robots ) {
		return gbg_core_current_noindexed() ? 'noindex, follow' : $robots;
	},
	20
);

// Yoast SEO: keep noindexed posts out of the XML sitemaps.
add_filter(
	'wpseo_exclude_from_sitemap_by_post_ids',
	static function ( $ids ) {
		$s = gbg_core_settings();
		if ( ! $s['noindex_enabled'] ) {
			return $ids;
		}
		$list = array_diff( $s['noindex_ids'], $s['protected_ids'] );
		return array_values( array_unique( array_merge( (array) $ids, $list ) ) );
	}
);

// WordPress core robots (used when Yoast does not print robots itself).
add_filter(
	'wp_robots',
	static function ( array $robots ) {
		if ( gbg_core_current_noindexed() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['index'] );
		}
		return $robots;
	}
);

/* -------------------------------------------------------------------------
 * 2 + 3. Affiliate disclosure and Travelpayouts SubIDs on the main content.
 * ---------------------------------------------------------------------- */

function gbg_core_host_matches( string $host, array $hosts ): bool {
	$host = strtolower( $host );
	foreach ( $hosts as $h ) {
		if ( $host === $h || str_ends_with( $host, '.' . $h ) ) {
			return true;
		}
	}
	return false;
}

/**
 * SubID for a post: post meta `_gbg_subid` if set, otherwise "p<ID>", plus "-<lang>" when `subid_lang` is on
 * and GTranslate passes the language in the X-GT-Lang request header. Only [a-z0-9_-], max 40 characters.
 */
function gbg_core_subid( int $post_id ): string {
	$subid = (string) get_post_meta( $post_id, '_gbg_subid', true );
	if ( '' === $subid ) {
		$subid = 'p' . $post_id;
	}
	$lang = gbg_core_settings()['subid_lang'] && isset( $_SERVER['HTTP_X_GT_LANG'] ) ? (string) $_SERVER['HTTP_X_GT_LANG'] : '';
	if ( '' !== $lang ) {
		$subid .= '-' . $lang;
	}
	return substr( preg_replace( '/[^a-z0-9_-]/', '', strtolower( $subid ) ), 0, 40 );
}

/**
 * Walks every <a> in $html once. Returns [html, has_affiliate_links].
 * Adds sub_id to links on subid hosts that do not have one yet; never changes other links.
 */
function gbg_core_process_links( string $html, array $settings, string $subid ): array {
	$has_affiliate = false;
	$tags          = new WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag( 'a' ) ) {
		$href = $tags->get_attribute( 'href' );
		$rel  = strtolower( (string) $tags->get_attribute( 'rel' ) );
		if ( str_contains( $rel, 'sponsored' ) ) {
			$has_affiliate = true;
		}
		if ( ! is_string( $href ) || '' === $href ) {
			continue;
		}
		$parts = wp_parse_url( $href );
		$host  = $parts['host'] ?? '';
		if ( '' === $host ) {
			continue;
		}
		if ( gbg_core_host_matches( $host, $settings['affiliate_hosts'] ) ) {
			$has_affiliate = true;
		}
		if ( $settings['subid_enabled'] && '' !== $subid && gbg_core_host_matches( $host, $settings['subid_hosts'] ) ) {
			parse_str( $parts['query'] ?? '', $query );
			if ( ! isset( $query['sub_id'] ) || '' === $query['sub_id'] ) {
				$tags->set_attribute( 'href', add_query_arg( 'sub_id', $subid, $href ) );
			}
		}
	}
	return array( $tags->get_updated_html(), $has_affiliate );
}

function gbg_core_has_disclosure( string $html ): bool {
	return str_contains( $html, 'gbg-disclosure' )
		|| (bool) preg_match( '/affiliate (link|links|disclosure|commission)|earn (a|small)?\s*commission/i', $html );
}

function gbg_core_disclosure_html( array $s ): string {
	$link = '';
	if ( '' !== $s['disclosure_url'] ) {
		$link = ' <a href="' . esc_url( $s['disclosure_url'] ) . '">' . esc_html( $s['disclosure_link_text'] ) . '</a>';
	}
	return '<p class="gbg-disclosure" style="font-size:.875em;opacity:.8;margin:0 0 1.25em">'
		. esc_html( $s['disclosure_text'] ) . $link . '</p>';
}

/**
 * Impreza/WPBakery pages start with a full-width hero section; a line before it ends up under the sticky
 * header without a gutter. There the disclosure gets its own section right after the first one, using the
 * theme's content container. Other content gets it at the top.
 */
function gbg_core_add_disclosure( string $content, array $s ): string {
	$html = gbg_core_disclosure_html( $s );
	if ( str_starts_with( ltrim( $content ), '<section class="l-section' ) ) {
		$end = strpos( $content, '</section>' );
		if ( false !== $end ) {
			$end    += strlen( '</section>' );
			$section = '<section class="l-section wpb_row height_auto gbg-disclosure-section" style="padding-top:1.25em;padding-bottom:0"><div class="l-section-h i-cf">'
				. $html . '</div></section>';
			return substr( $content, 0, $end ) . $section . substr( $content, $end );
		}
	}
	return $html . $content;
}

add_filter(
	'the_content',
	static function ( $content ) {
		static $disclosed = false;
		$s = gbg_core_settings();
		if ( ! $s['disclosure_enabled'] && ! $s['subid_enabled'] ) {
			return $content;
		}
		// Only the main post of a single view; not feeds, archives, admin or REST.
		if ( ! is_string( $content ) || '' === $content || is_admin() || is_feed() || ! is_singular()
			|| ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $content;
		}
		$post_id = (int) get_the_ID();
		if ( $post_id !== (int) get_queried_object_id() ) {
			return $content;
		}
		[ $content, $has_affiliate ] = gbg_core_process_links( $content, $s, gbg_core_subid( $post_id ) );
		if ( $s['disclosure_enabled'] && $has_affiliate && ! $disclosed && ! gbg_core_has_disclosure( $content ) ) {
			$disclosed = true;
			$content   = gbg_core_add_disclosure( $content, $s );
		}
		return $content;
	},
	20 // after shortcodes (11), so links inside WPBakery/Impreza elements are seen too
);

/*
 * SubIDs outside the main content: footer page blocks and the sticky mobile button are not part of
 * the_content, so the finished HTML of a single view gets the same link walk once. Links that already
 * carry a sub_id (the main content) are left alone; only subid hosts are touched.
 */
add_action(
	'template_redirect',
	static function () {
		$s = gbg_core_settings();
		if ( ! $s['subid_enabled'] || ! $s['subid_whole_page'] || is_admin() || is_feed() || ! is_singular() ) {
			return;
		}
		$subid = gbg_core_subid( (int) get_queried_object_id() );
		ob_start(
			static fn( $html ) => ( is_string( $html ) && '' !== $html ) ? gbg_core_process_links( $html, $s, $subid )[0] : $html
		);
	}
);

/* -------------------------------------------------------------------------
 * 4. Yoast title, meta description and focus keyphrase of pages editable via the REST API
 *    (posts already are on this site). Only for users who may edit that page.
 * ---------------------------------------------------------------------- */

add_action(
	'init',
	static function () {
		foreach ( array( '_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_yoast_wpseo_focuskw' ) as $key ) {
			register_post_meta(
				'page',
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => static fn( $allowed, $meta_key, $post_id ) => current_user_can( 'edit_post', $post_id ),
				)
			);
		}
	}
);

/* -------------------------------------------------------------------------
 * MCP lock: the default MCP server answers anonymous requests and lists the site's abilities.
 * When switched on, only administrators reach it. The OAuth MCP server is not touched.
 * ---------------------------------------------------------------------- */

function gbg_core_mcp_locked_route( string $route ): bool {
	// WordPress matches REST routes case-insensitively, so the lock must too (security review 22-10: /MCP/... bypassed it).
	return 0 === stripos( '/' . ltrim( rawurldecode( $route ), '/' ), '/mcp/mcp-adapter-default-server' );
}

add_filter(
	'rest_pre_dispatch',
	static function ( $result, $server, $request ) {
		if ( null !== $result || ! gbg_core_settings()['mcp_lock_enabled'] ) {
			return $result;
		}
		if ( gbg_core_mcp_locked_route( (string) $request->get_route() ) && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'gbg_core_mcp_locked', 'Not allowed.', array( 'status' => 401 ) );
		}
		return $result;
	},
	10,
	3
);

/* -------------------------------------------------------------------------
 * Redirection access: the Redirection plugin requires `manage_options`. When switched on, the settings logins
 * (Claude's editor account) may use it too, so redirects and the 404 log can be managed by script.
 * ---------------------------------------------------------------------- */

add_filter(
	'redirection_role',
	static function ( $role ) {
		if ( ! gbg_core_settings()['redirection_access'] || current_user_can( 'manage_options' ) ) {
			return $role;
		}
		$user   = wp_get_current_user();
		$logins = (array) apply_filters( 'gbg_core_settings_logins', array( 'claude-editor' ) );
		return ( $user->exists() && in_array( $user->user_login, $logins, true ) ) ? 'edit_others_pages' : $role;
	}
);

/* -------------------------------------------------------------------------
 * Updates: new versions are published in the public repo github.com/prnl85/gbg-core (update.json + a branch per
 * release). They appear under Dashboard → Updates; on live an administrator clicks Update. Only the staging site
 * (cloudwaysapps.com host) updates itself automatically.
 * ---------------------------------------------------------------------- */

const GBG_CORE_UPDATE_JSON = 'https://raw.githubusercontent.com/prnl85/gbg-core/main/update.json';
const GBG_CORE_UPDATE_API  = 'https://api.github.com/repos/prnl85/gbg-core/contents/update.json?ref=main';

/** Update entry for WordPress when the published version is newer, else null. */
function gbg_core_update_item( array $remote ): ?object {
	if ( empty( $remote['version'] ) || empty( $remote['package'] ) || ! version_compare( $remote['version'], GBG_CORE_VERSION, '>' ) ) {
		return null;
	}
	if ( 0 !== strpos( $remote['package'], 'https://github.com/prnl85/gbg-core/' ) ) {
		return null; // only packages from our own repo
	}
	return (object) array(
		'id'          => 'gbg-core/gbg-core.php',
		'slug'        => 'gbg-core',
		'plugin'      => 'gbg-core/gbg-core.php',
		'version'     => $remote['version'], // WordPress reads 'version' from update_plugins_{host}
		'new_version' => $remote['version'],
		'package'     => $remote['package'],
		'url'         => 'https://github.com/prnl85/gbg-core',
		'tested'      => (string) ( $remote['tested'] ?? '' ), // WordPress version the release was checked on (staging)
		'requires_php' => (string) ( $remote['requires_php'] ?? '' ),
	);
}

// 'Check again' on Dashboard → Updates (force-check) also refreshes our own cached release, so a new version shows at
// once instead of after the 6-hour cache (Paul 21-10: live did not show 0.3.13). Priority 1: core's own plugin check
// (_maybe_update_plugins) also runs on this hook at 10 and must see the emptied cache (Paul 22-10: 0.3.42 did not show).
add_action(
	'load-update-core.php',
	static function () {
		if ( isset( $_GET['force-check'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			delete_transient( 'gbg_core_release' );
			delete_site_transient( 'update_plugins' );
		}
	},
	1
);

function gbg_core_remote_release(): array {
	$cached = get_transient( 'gbg_core_release' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	// GitHub's API serves the file without the raw CDN cache (raw.githubusercontent.com served an old update.json for
	// minutes after a release, 21-10). Fall back to the raw URL if the API is unreachable or rate limited.
	$response = wp_remote_get( GBG_CORE_UPDATE_API, array( 'timeout' => 10, 'headers' => array( 'Accept' => 'application/vnd.github.raw+json', 'User-Agent' => 'gbg-core' ) ) );
	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		$response = wp_remote_get( GBG_CORE_UPDATE_JSON . '?t=' . time(), array( 'timeout' => 10 ) );
	}
	$code     = is_wp_error( $response ) ? $response->get_error_message() : (int) wp_remote_retrieve_response_code( $response );
	$data     = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
	$ok       = is_array( $data ) && ! empty( $data['version'] );
	// A failed fetch is cached for 5 minutes only, so one hiccup does not hide a new version for an hour.
	set_transient( 'gbg_core_release', $ok ? $data : array(), $ok ? HOUR_IN_SECONDS : 5 * MINUTE_IN_SECONDS );
	update_option( 'gbg_core_release_check', array( 'at' => gmdate( 'c' ), 'status' => $code, 'version' => $ok ? $data['version'] : '' ), false );
	return $ok ? $data : array();
}

add_filter(
	'update_plugins_github.com',
	static function ( $update, $plugin_data, $plugin_file ) {
		if ( 'gbg-core/gbg-core.php' !== $plugin_file ) {
			return $update;
		}
		return gbg_core_update_item( gbg_core_remote_release() ) ?? $update;
	},
	10,
	3
);

// GitHub branch archives unpack to "gbg-core-<branch>/": rename to "gbg-core/" so the plugin path stays the same.
add_filter(
	'upgrader_source_selection',
	static function ( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		if ( ( $hook_extra['plugin'] ?? '' ) !== 'gbg-core/gbg-core.php' || 'gbg-core' === basename( $source ) ) {
			return $source;
		}
		$target = trailingslashit( dirname( $source ) ) . 'gbg-core/';
		return rename( $source, $target ) ? $target : $source;
	},
	10,
	4
);

add_filter(
	'auto_update_plugin',
	static function ( $auto, $item ) {
		$is_ours = isset( $item->plugin ) && 'gbg-core/gbg-core.php' === $item->plugin;
		return ( $is_ours && gbg_core_is_staging() ) ? true : $auto;
	},
	10,
	2
);

/** Staging only: the site this plugin may update itself on without a click. */
function gbg_core_is_staging(): bool {
	return false !== strpos( (string) home_url(), 'cloudwaysapps.com' );
}

// POST /gbg/v1/refresh-update (settings logins, staging and live): fetch the release info now and let WordPress
// re-check plugin updates, so a new version appears under Dashboard → Updates right away. Installs nothing.
add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'gbg/v1',
			'/refresh-update',
			array(
				'methods'             => 'POST',
				'permission_callback' => 'gbg_core_can_manage',
				'callback'            => static function () {
					delete_transient( 'gbg_core_release' );
					delete_site_transient( 'update_plugins' );
					if ( ! function_exists( 'wp_update_plugins' ) ) {
						require_once ABSPATH . 'wp-includes/update.php';
					}
					wp_update_plugins();
					$t = get_site_transient( 'update_plugins' );
					$r = is_object( $t ) && isset( $t->response['gbg-core/gbg-core.php'] ) ? $t->response['gbg-core/gbg-core.php'] : null;
					return array( 'installed' => GBG_CORE_VERSION, 'available' => $r ? $r->new_version : null, 'check' => get_option( 'gbg_core_release_check', array() ) );
				},
			)
		);
	}
);

// Staging only: POST /gbg/v1/self-update installs the newest release from the update channel right away (instead of
// waiting for WordPress' twice-daily check). Same package check as the normal update (only our own repo). Live never:
// there an administrator clicks Update.
add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'gbg/v1',
			'/self-update',
			array(
				'methods'             => 'POST',
				'permission_callback' => static fn() => gbg_core_can_manage() && gbg_core_is_staging(),
				'callback'            => static function () {
					delete_transient( 'gbg_core_release' );
					$item = gbg_core_update_item( gbg_core_remote_release() );
					if ( ! $item ) {
						return array( 'updated' => false, 'version' => GBG_CORE_VERSION, 'reason' => 'no newer release' );
					}
					require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
					require_once ABSPATH . 'wp-admin/includes/file.php';
					require_once ABSPATH . 'wp-admin/includes/misc.php';
					$updates = get_site_transient( 'update_plugins' );
					$updates = is_object( $updates ) ? $updates : new stdClass();
					$updates->response                           = (array) ( $updates->response ?? array() );
					$updates->response['gbg-core/gbg-core.php'] = $item;
					set_site_transient( 'update_plugins', $updates );
					$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
					$result   = $upgrader->upgrade( 'gbg-core/gbg-core.php' );
					if ( true === $result || null === $result ) {
						activate_plugin( 'gbg-core/gbg-core.php' );
					}
					return array( 'updated' => true === $result, 'from' => GBG_CORE_VERSION, 'to' => $item->new_version,
						'messages' => array_slice( (array) $upgrader->skin->get_upgrade_messages(), -5 ) );
				},
			)
		);
	}
);

/* -------------------------------------------------------------------------
 * Hardening switches (all off by default) and a health report for the nightly security audit.
 * ---------------------------------------------------------------------- */

add_filter( 'xmlrpc_enabled', static fn( $on ) => gbg_core_settings()['xmlrpc_off'] ? false : $on );
// 'xmlrpc_enabled' only stops logins over XML-RPC; pingbacks and system.multicall still answer. With xmlrpc_off the
// whole endpoint answers 403 (nothing on this site uses it: no Jetpack, no mobile app).
add_action(
	'init',
	static function () {
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST && gbg_core_settings()['xmlrpc_off'] ) {
			status_header( 403 );
			header( 'Content-Type: text/plain; charset=utf-8' );
			exit( 'XML-RPC is disabled on this site.' );
		}
	},
	0
);
add_filter(
	'wp_headers',
	static function ( $headers ) {
		if ( gbg_core_settings()['xmlrpc_off'] ) {
			unset( $headers['X-Pingback'] );
		}
		return $headers;
	}
);

add_action(
	'template_redirect',
	static function () {
		if ( gbg_core_settings()['author_scan_block'] && isset( $_GET['author'] ) && ! is_user_logged_in() ) { // phpcs:ignore WordPress.Security.NonceVerification
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			// Stop here: otherwise redirect_canonical (later on this hook) still redirects ?author=1 to /author/<name>/.
			$tpl = get_404_template();
			if ( $tpl ) {
				include $tpl;
			}
			exit;
		}
	},
	0
);

add_filter(
	'rest_endpoints',
	static function ( $endpoints ) {
		if ( gbg_core_settings()['users_api_private'] && ! is_user_logged_in() ) {
			foreach ( array_keys( $endpoints ) as $route ) {
				if ( 0 === strpos( $route, '/wp/v2/users' ) ) {
					unset( $endpoints[ $route ] );
				}
			}
		}
		return $endpoints;
	}
);

add_filter( 'the_generator', static fn( $gen ) => gbg_core_settings()['hide_generator'] ? '' : $gen );

/** Facts for the security audit; only for the settings logins (same check as the settings route). */
/** What an anonymous visitor gets (request from the server to itself, without login cookies): proves the hardening
 * switches work, which cannot be tested through the authenticated API. Cached 10 minutes. */
function gbg_core_anon_selftest(): array {
	$cached = get_transient( 'gbg_core_anon_selftest' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$home = home_url( '/' );
	$code = static function ( string $url, array $args = array() ) {
		$r = wp_remote_request( $url, array_merge( array( 'timeout' => 10, 'redirection' => 0, 'cookies' => array(), 'headers' => array( 'User-Agent' => 'gbg-core-selftest' ) ), $args ) );
		return is_wp_error( $r ) ? $r->get_error_code() : (int) wp_remote_retrieve_response_code( $r );
	};
	$out = array(
		'users_api' => $code( $home . 'wp-json/wp/v2/users' ),
		'author_1'  => $code( $home . '?author=1' ),
		'xmlrpc'    => $code( $home . 'xmlrpc.php', array( 'method' => 'POST', 'body' => '<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName></methodCall>' ) ),
		'at'        => gmdate( 'c' ),
	);
	set_transient( 'gbg_core_anon_selftest', $out, 10 * MINUTE_IN_SECONDS );
	return $out;
}

function gbg_core_health(): array {
	$plugins = function_exists( 'get_plugins' ) ? get_plugins() : array();
	$updates = get_site_transient( 'update_plugins' );
	$pending = array();
	foreach ( (array) ( $updates->response ?? array() ) as $file => $u ) {
		$pending[] = array( 'plugin' => $file, 'installed' => $plugins[ $file ]['Version'] ?? '', 'available' => $u->new_version ?? '' );
	}
	$core   = get_site_transient( 'update_core' );
	$latest = '';
	foreach ( (array) ( $core->updates ?? array() ) as $u ) {
		if ( 'upgrade' === ( $u->response ?? '' ) ) {
			$latest = $u->current ?? '';
		}
	}
	$active = (array) get_option( 'active_plugins', array() );
	return array(
		'wp_version'         => get_bloginfo( 'version' ),
		'wp_update'          => $latest,
		'php_version'        => PHP_VERSION,
		'file_edit_disabled' => defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT,
		'debug'              => defined( 'WP_DEBUG' ) && WP_DEBUG,
		'debug_display'      => defined( 'WP_DEBUG_DISPLAY' ) ? (bool) WP_DEBUG_DISPLAY : ( defined( 'WP_DEBUG' ) && WP_DEBUG ),
		'administrators'     => count( get_users( array( 'role' => 'administrator', 'fields' => 'ID' ) ) ),
		'users'              => (int) ( count_users()['total_users'] ?? 0 ),
		'xmlrpc_enabled'     => (bool) apply_filters( 'xmlrpc_enabled', true ),
		'plugins'            => array_map(
			static fn( $file ) => array( 'plugin' => $file, 'version' => $plugins[ $file ]['Version'] ?? '', 'active' => in_array( $file, $active, true ) ),
			array_keys( $plugins )
		),
		'plugin_updates'     => $pending,
		'update_channel'     => get_option( 'gbg_core_release_check', array() ),
		'go_links'           => array_keys( (array) gbg_core_settings()['go_links'] ),
		'go_country'         => array_map( 'count', (array) gbg_core_settings()['go_country'] ),
		'go_pages'           => count( (array) gbg_core_settings()['go_pages'] ),
		'ga4_mp'             => array( 'measurement_id' => gbg_core_settings()['ga4_measurement_id'], 'secret_set' => '' !== (string) get_option( 'gbg_core_ga4_secret', '' ), 'track_enabled' => gbg_core_settings()['track_enabled'], 'today' => get_transient( 'gbg_core_mp_stats' ) ?: array() ),
		'promos'             => array_map( static fn( $p ) => $p['merchant'] . ' ' . $p['code'] . ' ' . $p['until'], (array) gbg_core_settings()['promos'] ),
		'anonymous_selftest' => gbg_core_anon_selftest(),
		'theme'              => wp_get_theme()->get_template() . ' ' . wp_get_theme( get_template() )->get( 'Version' ),
	);
}

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'gbg/v1',
			'/health',
			array(
				'methods'             => 'GET',
				'permission_callback' => 'gbg_core_can_manage',
				'callback'            => static function () {
					if ( ! function_exists( 'get_plugins' ) ) {
						require_once ABSPATH . 'wp-admin/includes/plugin.php';
					}
					return array( 'version' => GBG_CORE_VERSION, 'health' => gbg_core_health() );
				},
			)
		);
	}
);

/* -------------------------------------------------------------------------
 * A/B tests: a small inline script on matching pages picks a variant per page view (no cookies, no storage, so no
 * consent banner needed), changes the button text/class, tags affiliate links' sub_id with the variant (Travelpayouts
 * then reports bookings per variant) and counts views and clicks. Counts are kept as daily totals per test and variant,
 * nothing about the visitor. Tests are set with POST /gbg/v1/settings {"ab_tests": [...]}; read with GET /gbg/v1/ab.
 * ---------------------------------------------------------------------- */

const GBG_CORE_AB_OPTION = 'gbg_core_ab_counts';

/** Keep only well-formed tests: id a-z0-9 (max 12), status running|paused, path regex, CSS selector, 2-4 variants. */
function gbg_core_ab_sanitize( array $tests ): array {
	$clean = array();
	foreach ( $tests as $t ) {
		$t  = (array) $t;
		$id = strtolower( (string) ( $t['id'] ?? '' ) );
		if ( ! preg_match( '/^[a-z0-9]{1,12}$/', $id ) || ! is_array( $t['variants'] ?? null ) ) {
			continue;
		}
		$variants = array();
		foreach ( array_slice( $t['variants'], 0, 4 ) as $v ) {
			$v   = (array) $v;
			$key = strtolower( (string) ( $v['key'] ?? '' ) );
			if ( preg_match( '/^[a-d]$/', $key ) ) {
				$variants[] = array(
					'key'   => $key,
					'text'  => isset( $v['text'] ) ? sanitize_text_field( (string) $v['text'] ) : '',
					'class' => isset( $v['class'] ) ? preg_replace( '/[^a-z0-9_\- ]/i', '', (string) $v['class'] ) : '',
				);
			}
		}
		$path = (string) ( $t['path'] ?? '' );
		if ( count( $variants ) < 2 || '' === $path || false === @preg_match( '#' . $path . '#', '' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			continue;
		}
		$clean[] = array(
			'id'       => $id,
			'status'   => 'running' === ( $t['status'] ?? '' ) ? 'running' : 'paused',
			'path'     => $path,
			'selector' => preg_replace( '/[<>{};]/', '', (string) ( $t['selector'] ?? '' ) ),
			'variants' => $variants,
			'sample'   => max( 1, min( 20, (int) ( $t['sample'] ?? 1 ) ) ), // count 1 in N views (clicks always)
		);
	}
	return $clean;
}

function gbg_core_ab_running(): array {
	return array_values( array_filter( (array) gbg_core_settings()['ab_tests'], static fn( $t ) => 'running' === ( $t['status'] ?? '' ) ) );
}

/** Tests that run on this path. */
function gbg_core_ab_for_path( string $path ): array {
	return array_values( array_filter( gbg_core_ab_running(), static fn( $t ) => (bool) preg_match( '#' . $t['path'] . '#', $path ) ) );
}

add_action(
	'wp_footer',
	static function () {
		if ( is_admin() || is_user_logged_in() ) {
			return; // editors and Claude's checks never count
		}
		$path  = (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$tests = gbg_core_ab_for_path( $path );
		if ( ! $tests ) {
			return;
		}
		$cfg = wp_json_encode( array( 'u' => esc_url_raw( rest_url( 'gbg/v1/ab/hit' ) ), 't' => $tests ) );
		// ~1 KB, runs once after the page; sendBeacon does not delay clicks.
		echo "<script id=\"gbg-ab\">(function(c){var send=function(t,v,e){try{navigator.sendBeacon(c.u,JSON.stringify({t:t,v:v,e:e}))}catch(x){}};"
			. "c.t.forEach(function(t){var els=[].slice.call(document.querySelectorAll(t.selector));if(!els.length)return;"
			. "var v=t.variants[Math.floor(Math.random()*t.variants.length)];"
			. "els.forEach(function(el){el.setAttribute('data-gbg-ab',t.id+':'+v.key);if(v.text){var l=el.querySelector('.w-btn-label')||el;l.textContent=v.text}"
			. "if(v['class'])el.className+=' '+v['class'];"
			. "[].slice.call(el.tagName==='A'?[el]:el.querySelectorAll('a')).forEach(function(a){try{var u=new URL(a.href);"
			. "if(u.searchParams.has('sub_id')){u.searchParams.set('sub_id',u.searchParams.get('sub_id')+'-'+t.id+v.key);a.href=u.toString()}}catch(x){}});"
			. "el.addEventListener('click',function(){send(t.id,v.key,'click')})});"
			. "if(Math.random()*t.sample<1)send(t.id,v.key,'view')})})(" . $cfg . ');</script>'; // phpcs:ignore WordPress.Security.EscapeOutput
	},
	99
);

/** A hit is accepted only for a running test and one of its variants; daily totals, capped against flooding. */
function gbg_core_ab_record( string $test, string $variant, string $event, string $day ): bool {
	$t = current( array_filter( gbg_core_ab_running(), static fn( $x ) => $x['id'] === $test ) );
	if ( ! $t || ! in_array( $event, array( 'view', 'click' ), true ) || ! in_array( $variant, array_column( $t['variants'], 'key' ), true ) ) {
		return false;
	}
	$counts = get_option( GBG_CORE_AB_OPTION, array() );
	$n      = (int) ( $counts[ $test ][ $variant ][ $day ][ $event ] ?? 0 );
	if ( $n >= 20000 ) {
		return false;
	}
	$counts[ $test ][ $variant ][ $day ][ $event ] = $n + ( 'view' === $event ? $t['sample'] : 1 );
	update_option( GBG_CORE_AB_OPTION, $counts, false );
	return true;
}

/** Public on purpose (visitors' browsers report views and clicks); only accepts hits for running tests. */
function gbg_core_ab_can_hit(): bool {
	return (bool) gbg_core_ab_running();
}

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'gbg/v1',
			'/ab/hit',
			array(
				'methods'             => 'POST',
				'permission_callback' => 'gbg_core_ab_can_hit',
				'callback'            => static function ( WP_REST_Request $request ) {
					if ( gbg_core_is_bot_request( (string) $request->get_header( 'user_agent' ) ) ) {
						return new WP_REST_Response( null, 204 ); // access logs 22-10: render bots and the translation proxy inflated A/B views
					}
					$b = json_decode( $request->get_body(), true );
					$ok = is_array( $b ) && gbg_core_ab_record( (string) ( $b['t'] ?? '' ), (string) ( $b['v'] ?? '' ), (string) ( $b['e'] ?? '' ), gmdate( 'Y-m-d' ) );
					return new WP_REST_Response( null, $ok ? 204 : 400 );
				},
			)
		);
		register_rest_route(
			'gbg/v1',
			'/ab',
			array(
				'methods'             => 'GET',
				'permission_callback' => 'gbg_core_can_manage',
				'callback'            => static fn() => array( 'tests' => gbg_core_settings()['ab_tests'], 'counts' => get_option( GBG_CORE_AB_OPTION, array() ) ),
			)
		);
	}
);

/* -------------------------------------------------------------------------
 * REST: read and change the settings, so changes are scripted and logged.
 * Administrators, plus Claude's own live account (an editor; approved by Paul on 14-10-2026).
 * Other editors cannot. Change the list with the `gbg_core_settings_logins` filter.
 * ---------------------------------------------------------------------- */

function gbg_core_can_manage(): bool {
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}
	$user   = wp_get_current_user();
	$logins = (array) apply_filters( 'gbg_core_settings_logins', array( 'claude-editor' ) );
	return $user->exists() && in_array( $user->user_login, $logins, true ) && current_user_can( 'edit_others_pages' );
}

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'gbg/v1',
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => 'gbg_core_can_manage',
					'callback'            => static fn() => array(
						'version'  => GBG_CORE_VERSION,
						'settings' => gbg_core_settings(),
					),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => 'gbg_core_can_manage',
					'callback'            => static function ( WP_REST_Request $request ) {
						$input = $request->get_json_params();
						if ( ! is_array( $input ) ) {
							return new WP_Error( 'gbg_core_bad_body', 'Send a JSON object.', array( 'status' => 400 ) );
						}
						$stored = get_option( GBG_CORE_OPTION, array() );
						$merged = array_merge( is_array( $stored ) ? $stored : array(), gbg_core_sanitize( $input ) );
						update_option( GBG_CORE_OPTION, $merged, false );
						gbg_core_settings( true ); // the sync below must see the values just saved (22-10: first save on live kept the old file)
						$robots = gbg_core_sync_robots_file();
						// Front-end features (A/B tests, share bar, opt-in) live in the page HTML: empty the page cache so visitors get them.
						do_action( 'breeze_clear_all_cache' );
						return array(
							'version'  => GBG_CORE_VERSION,
							'settings' => gbg_core_settings( true ),
							'robots'   => $robots,
						);
					},
				),
			)
		);
	}
);

/* -------------------------------------------------------------------------
 * Growth: email sign-ups for lead magnets (0.3.0). Paul 20-10: collect and tag addresses per country on the site
 * itself first; a newsletter tool comes later (CSV export with the tags). Single opt-in with the gift shown right
 * away; consent text version + time are stored as proof. Emails never leave the site except through the
 * administrator-only export; the stats route has counts only.
 *
 * Settings: leads_enabled (kill switch), lead_magnets (id, title, pitch, button, thanks, files, tags),
 * growth_rules (where a magnet appears: category or path, after paragraph N, mobile bar after X% scroll),
 * consent_version, privacy_url. Shortcode: [gbg_optin magnet="<id>"].
 * ---------------------------------------------------------------------- */

const GBG_CORE_LEADS_DB = 'gbg_core_leads_db';

/** Tags are short "group:value" strings; only a-z0-9 and -_/. after the colon. */
function gbg_core_tag( string $group, string $value ): string {
	$value = strtolower( trim( $value ) );
	$value = preg_replace( '/[^a-z0-9\/_.-]/', '-', $value );
	$value = trim( substr( (string) $value, 0, 80 ), '-' );
	return '' === $value ? '' : $group . ':' . $value;
}

function gbg_core_tags_clean( array $tags ): array {
	$out = array();
	foreach ( $tags as $tag ) {
		$tag = strtolower( (string) $tag );
		if ( preg_match( '/^[a-z]{2,10}:[a-z0-9\/_.-]{1,80}$/', $tag ) ) {
			$out[] = $tag;
		}
	}
	return array_values( array_unique( $out ) );
}

/** Lead magnets: id a-z0-9- (max 40), texts, files (label + https URL), extra tags (e.g. country:thailand). */
function gbg_core_magnets_sanitize( array $magnets ): array {
	$clean = array();
	foreach ( $magnets as $m ) {
		$m  = (array) $m;
		$id = strtolower( (string) ( $m['id'] ?? '' ) );
		if ( ! preg_match( '/^[a-z0-9-]{1,40}$/', $id ) ) {
			continue;
		}
		$files = array();
		foreach ( array_slice( (array) ( $m['files'] ?? array() ), 0, 6 ) as $f ) {
			$f   = (array) $f;
			$url = esc_url_raw( (string) ( $f['url'] ?? '' ) );
			if ( 0 === strpos( $url, 'https://' ) ) {
				$files[] = array( 'label' => sanitize_text_field( (string) ( $f['label'] ?? 'Download' ) ), 'url' => $url );
			}
		}
		$clean[] = array(
			'id'     => $id,
			'title'  => sanitize_text_field( (string) ( $m['title'] ?? '' ) ),
			'pitch'  => sanitize_text_field( (string) ( $m['pitch'] ?? '' ) ),
			'button' => sanitize_text_field( (string) ( $m['button'] ?? 'Send it to me' ) ),
			'thanks' => sanitize_text_field( (string) ( $m['thanks'] ?? 'Thanks! Here is your download:' ) ),
			'bar'    => sanitize_text_field( (string) ( $m['bar'] ?? '' ) ),
			'files'  => $files,
			'tags'   => gbg_core_tags_clean( (array) ( $m['tags'] ?? array() ) ),
		);
	}
	return $clean;
}

/** Rules: id, running|paused, magnet, match on a category slug (incl. sub-categories) or a path regex, place. */
function gbg_core_rules_sanitize( array $rules ): array {
	$clean = array();
	foreach ( $rules as $r ) {
		$r  = (array) $r;
		$id = strtolower( (string) ( $r['id'] ?? '' ) );
		$magnet = strtolower( (string) ( $r['magnet'] ?? '' ) );
		$path   = (string) ( $r['path'] ?? '' );
		$cat    = strtolower( preg_replace( '/[^a-z0-9-]/i', '', (string) ( $r['category'] ?? '' ) ) );
		if ( ! preg_match( '/^[a-z0-9-]{1,40}$/', $id ) || ! preg_match( '/^[a-z0-9-]{1,40}$/', $magnet )
			|| ( '' === $cat && '' === $path ) || ( '' !== $path && false === @preg_match( '#' . $path . '#', '' ) ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			continue;
		}
		$clean[] = array(
			'id'         => $id,
			'status'     => 'running' === ( $r['status'] ?? '' ) ? 'running' : 'paused',
			'magnet'     => $magnet,
			'category'   => $cat,
			'path'       => $path,
			'after'      => max( 0, min( 30, (int) ( $r['after'] ?? 3 ) ) ), // after paragraph N; 0 = end of the content
			'mobile_bar' => ! empty( $r['mobile_bar'] ),
			'bar_scroll' => max( 10, min( 90, (int) ( $r['bar_scroll'] ?? 50 ) ) ),
			'tags'       => gbg_core_tags_clean( (array) ( $r['tags'] ?? array() ) ),
		);
	}
	return $clean;
}

function gbg_core_magnet( string $id ): ?array {
	foreach ( (array) gbg_core_settings()['lead_magnets'] as $m ) {
		if ( $m['id'] === $id ) {
			return $m;
		}
	}
	return null;
}

/**
 * Tags from the page's categories: children of "countries" are continents, deeper ones countries
 * (Countries > Asia > Thailand); other categories become topic tags.
 */
function gbg_core_post_tags( int $post_id ): array {
	$tags = array();
	foreach ( (array) get_the_category( $post_id ) as $term ) {
		$chain = array_map( static fn( $id ) => get_category( $id )->slug ?? '', array_reverse( get_ancestors( $term->term_id, 'category' ) ) );
		if ( 'countries' === ( $chain[0] ?? '' ) ) {
			$tags[] = gbg_core_tag( count( $chain ) >= 2 ? 'country' : 'continent', $term->slug );
			if ( count( $chain ) >= 2 ) {
				$tags[] = gbg_core_tag( 'continent', $chain[1] );
			}
		} elseif ( 'countries' !== $term->slug && 'uncategorized' !== $term->slug ) {
			$tags[] = gbg_core_tag( 'topic', $term->slug );
		}
	}
	return gbg_core_tags_clean( $tags );
}

/** Running rule for the current single view, if any. */
function gbg_core_rule_for( int $post_id, string $path ): ?array {
	foreach ( (array) gbg_core_settings()['growth_rules'] as $r ) {
		if ( 'running' !== $r['status'] || ! gbg_core_magnet( $r['magnet'] ) ) {
			continue;
		}
		if ( '' !== $r['path'] && preg_match( '#' . $r['path'] . '#', $path ) ) {
			return $r;
		}
		if ( '' !== $r['category'] ) {
			foreach ( (array) get_the_category( $post_id ) as $term ) {
				$slugs = array_merge( array( $term->slug ), array_map( static fn( $id ) => get_category( $id )->slug ?? '', get_ancestors( $term->term_id, 'category' ) ) );
				if ( in_array( $r['category'], $slugs, true ) ) {
					return $r;
				}
			}
		}
	}
	return null;
}

/** Inserts $box after the Nth closing </p> (0 or fewer paragraphs: at the end). */
function gbg_core_insert_after_paragraph( string $content, string $box, int $n ): string {
	if ( $n > 0 ) {
		$offset = 0;
		for ( $i = 0; $i < $n; $i++ ) {
			$pos = stripos( $content, '</p>', $offset );
			if ( false === $pos ) {
				return $content . $box;
			}
			$offset = $pos + 4;
		}
		return substr( $content, 0, $offset ) . $box . substr( $content, $offset );
	}
	return $content . $box;
}

/** The sign-up box (static HTML: pages are cached, so no nonce; the script adds the page context on submit). */
function gbg_core_optin_html( array $m, int $post_id, array $extra_tags = array(), ?array $bar = null ): string {
	$s       = gbg_core_settings();
	$privacy = '' !== $s['privacy_url'] ? ' <a href="' . esc_url( $s['privacy_url'] ) . '">Privacy</a>' : '';
	$data    = esc_attr( wp_json_encode( array( 'm' => $m['id'], 'p' => $post_id, 't' => $extra_tags ) ) );
	$html    = '<div class="gbg-optin" data-gbg="' . $data . '">'
		. '<p class="gbg-optin-title"><strong>' . esc_html( $m['title'] ) . '</strong></p>'
		. ( '' !== $m['pitch'] ? '<p class="gbg-optin-pitch">' . esc_html( $m['pitch'] ) . '</p>' : '' )
		. '<form class="gbg-optin-form" novalidate>'
		. '<input type="text" name="name" placeholder="First name (optional)" autocomplete="given-name" aria-label="First name">'
		. '<input type="email" name="email" placeholder="Your email" required autocomplete="email" aria-label="Email">'
		. '<input type="text" name="website" tabindex="-1" autocomplete="off" class="gbg-hp" aria-hidden="true">'
		. '<button type="submit">' . esc_html( $m['button'] ) . '</button></form>'
		. '<p class="gbg-optin-consent">You get this free download and our travel newsletter (about once a month). Unsubscribe any time.' . $privacy . '</p>'
		. '<div class="gbg-optin-done" hidden></div></div>';
	if ( $bar ) {
		$html .= '<div class="gbg-bar" hidden data-scroll="' . (int) $bar['bar_scroll'] . '"><span>' . esc_html( '' !== $m['bar'] ? $m['bar'] : $m['title'] ) . '</span>'
			. '<button type="button" class="gbg-bar-go">Get it</button><button type="button" class="gbg-bar-x" aria-label="Close">×</button></div>';
	}
	return $html;
}

function gbg_core_optin_assets(): string {
	static $done = false;
	if ( $done ) {
		return '';
	}
	$done = true;
	$cfg  = wp_json_encode( array( 'u' => esc_url_raw( rest_url( 'gbg/v1/lead' ) ) ) );
	return '<style id="gbg-optin-css">.gbg-optin{border:2px solid var(--color-content-primary,#7cb342);border-radius:10px;padding:1.2em 1.4em;margin:1.8em 0;background:var(--color-content-bg-alt,#f6f8f3)}'
		. '.gbg-optin p{margin:0 0 .6em}.gbg-optin-form{display:flex;flex-wrap:wrap;gap:.5em}.gbg-optin-form input{flex:1 1 12em;min-height:44px;padding:0 .8em;border:1px solid #b9c4b2;border-radius:6px;background:#fff}'
		. '.gbg-optin-form button,.gbg-bar button{min-height:44px;padding:0 1.2em;border:0;border-radius:6px;background:var(--color-content-primary,#7cb342);color:#fff;font-weight:600;cursor:pointer}'
		. '.gbg-optin-consent{font-size:.8em;opacity:.8;margin:.6em 0 0}.gbg-hp{position:absolute!important;left:-9999px!important}'
		. '.gbg-optin-err{color:#b3261e;font-size:.9em}.gbg-optin [hidden],.gbg-bar[hidden]{display:none!important}'
		. '.gbg-bar{position:fixed;left:0;right:0;bottom:0;z-index:999;display:flex;align-items:center;gap:.6em;padding:.6em max(12px,env(safe-area-inset-right)) calc(.6em + env(safe-area-inset-bottom)) max(12px,env(safe-area-inset-left));background:#1d2b1d;color:#fff;font-size:.95em}'
		. '.gbg-bar span{flex:1}.gbg-bar .gbg-bar-x{background:transparent;padding:0 .6em;font-size:1.4em}@media(min-width:768px){.gbg-bar{display:none!important}}@media(max-width:767px){html.gbg-bar-on #gt_float_wrapper{bottom:calc(68px + env(safe-area-inset-bottom,0px))!important}}</style>'
		. '<script id="gbg-optin-js">(function(c){var ls={get:function(k){try{return localStorage.getItem(k)}catch(e){return null}},set:function(k,v){try{localStorage.setItem(k,v)}catch(e){}}};'
		. 'var bar=document.querySelector(".gbg-bar"),barOff=function(){if(bar)bar.hidden=true;document.documentElement.classList.remove("gbg-bar-on")};'
		. 'var t0=Date.now(),q=new URLSearchParams(location.search),seg=location.pathname.split("/")[1]||"",lang=/^[a-z]{2}(-[A-Za-z]{2})?$/.test(seg)?seg.toLowerCase():"en";'
		. 'var src=q.get("utm_source")||(document.referrer&&document.referrer.indexOf(location.host)<0?(function(){try{return new URL(document.referrer).hostname}catch(e){return""}})():"");'
		. 'document.querySelectorAll(".gbg-optin").forEach(function(box){var d=JSON.parse(box.getAttribute("data-gbg")),f=box.querySelector("form"),done=box.querySelector(".gbg-optin-done");'
		. 'f.addEventListener("submit",function(e){e.preventDefault();var b=f.querySelector("button"),old=f.querySelector(".gbg-optin-err");if(old)old.remove();b.disabled=true;'
		. 'fetch(c.u,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({email:f.email.value,name:f.name.value,hp:f.website.value,ms:Date.now()-t0,m:d.m,p:d.p,t:d.t,lang:lang,src:src,page:location.pathname})})'
		. '.then(function(r){return r.json().then(function(j){return{ok:r.ok,j:j}})}).then(function(r){if(!r.ok)throw new Error(r.j&&r.j.message||"error");'
		. 'f.hidden=true;box.querySelector(".gbg-optin-consent").hidden=true;done.innerHTML=r.j.html;done.hidden=false;ls.set("gbg_lead","1");'
		. 'barOff();if(window.gbgEv)gbgEv("generate_lead",{lead_magnet:d.m,lead_country:(r.j.country||"")});})'
		. '.catch(function(err){var p=document.createElement("p");p.className="gbg-optin-err";p.textContent=/email/i.test(err.message)?"Please check your email address.":"Something went wrong, please try again.";f.after(p);b.disabled=false});});});'
		. 'if(bar&&!ls.get("gbg_lead")&&!(+ls.get("gbg_bar_x")>Date.now())){var pct=+bar.getAttribute("data-scroll"),on=function(){var h=document.documentElement;'
		. 'if((h.scrollTop+innerHeight)/h.scrollHeight*100>=pct){bar.hidden=false;document.documentElement.classList.add("gbg-bar-on");removeEventListener("scroll",on)}};addEventListener("scroll",on,{passive:true});'
		. 'bar.querySelector(".gbg-bar-x").onclick=function(){barOff();ls.set("gbg_bar_x",Date.now()+14*864e5)};'
		. 'bar.querySelector(".gbg-bar-go").onclick=function(){barOff();var b=document.querySelector(".gbg-optin");if(b){b.scrollIntoView({behavior:"smooth",block:"center"});var i=b.querySelector("input[type=email]");if(i)setTimeout(function(){i.focus()},500)}}}'
		. '})(' . $cfg . ');</script>'; // phpcs:ignore WordPress.Security.EscapeOutput
}

add_shortcode(
	'gbg_optin',
	static function ( $atts ) {
		$atts = shortcode_atts( array( 'magnet' => '' ), $atts );
		$m    = gbg_core_settings()['leads_enabled'] ? gbg_core_magnet( strtolower( (string) $atts['magnet'] ) ) : null;
		if ( ! $m ) {
			return '';
		}
		return gbg_core_optin_html( $m, (int) get_the_ID() ) . gbg_core_optin_assets();
	}
);

// Rules: put the box into matching single views (after shortcodes, after the disclosure/SubID filter).
add_filter(
	'the_content',
	static function ( $content ) {
		static $placed = false;
		$s = gbg_core_settings();
		if ( $placed || ! $s['leads_enabled'] || ! $s['growth_rules'] || ! is_string( $content ) || is_admin() || is_feed() || ! is_singular()
			|| ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || str_contains( $content, 'class="gbg-optin"' ) ) {
			return $content;
		}
		$post_id = (int) get_the_ID();
		if ( $post_id !== (int) get_queried_object_id() ) {
			return $content;
		}
		$rule = gbg_core_rule_for( $post_id, (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! $rule ) {
			return $content;
		}
		$placed = true;
		$box    = gbg_core_optin_html( gbg_core_magnet( $rule['magnet'] ), $post_id, array_merge( $rule['tags'], array( 'rule:' . $rule['id'] ) ), $rule['mobile_bar'] ? $rule : null );
		return gbg_core_insert_after_paragraph( $content, $box . gbg_core_optin_assets(), $rule['after'] );
	},
	25
);

/* --- storage ----------------------------------------------------------- */

function gbg_core_leads_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'gbg_leads';
}

/** Creates/updates the table once per schema version (activation hooks do not run on updates). */
function gbg_core_leads_install(): void {
	if ( '1' === get_option( GBG_CORE_LEADS_DB ) ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table = gbg_core_leads_table();
	dbDelta(
		"CREATE TABLE $table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		email varchar(190) NOT NULL,
		first_name varchar(60) NOT NULL DEFAULT '',
		tags text NOT NULL,
		status varchar(20) NOT NULL DEFAULT 'active',
		consent_version varchar(20) NOT NULL DEFAULT '',
		consent_at datetime NOT NULL,
		signups int(10) unsigned NOT NULL DEFAULT 1,
		ip_hash char(16) NOT NULL DEFAULT '',
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY email (email),
		KEY status (status)
		) {$wpdb->get_charset_collate()};"
	);
	update_option( GBG_CORE_LEADS_DB, '1', false );
}
add_action( 'admin_init', 'gbg_core_leads_install' );

/** Rate limit per (hashed) IP: max 5 sign-ups per hour. */
function gbg_core_lead_rate_ok( string $ip_hash ): bool {
	$key = 'gbg_lead_rl_' . $ip_hash;
	$n   = (int) get_transient( $key );
	if ( $n >= 5 ) {
		return false;
	}
	set_transient( $key, $n + 1, HOUR_IN_SECONDS );
	return true;
}

/** Validates a sign-up; returns [ok, error code, clean data]. Pure apart from the settings and category lookups. */
function gbg_core_lead_validate( array $b ): array {
	if ( '' !== (string) ( $b['hp'] ?? '' ) || (int) ( $b['ms'] ?? 0 ) < 2500 ) {
		return array( false, 'spam', array() );
	}
	$email = strtolower( trim( (string) ( $b['email'] ?? '' ) ) );
	if ( strlen( $email ) > 190 || ! is_email( $email ) ) {
		return array( false, 'email', array() );
	}
	$m = gbg_core_magnet( strtolower( (string) ( $b['m'] ?? '' ) ) );
	if ( ! $m ) {
		return array( false, 'magnet', array() );
	}
	$post_id = absint( $b['p'] ?? 0 );
	$lang    = strtolower( (string) ( $b['lang'] ?? 'en' ) );
	$tags    = array_merge(
		$post_id ? gbg_core_post_tags( $post_id ) : array(),
		$m['tags'],
		gbg_core_tags_clean( array_slice( (array) ( $b['t'] ?? array() ), 0, 6 ) ),
		array(
			gbg_core_tag( 'magnet', $m['id'] ),
			gbg_core_tag( 'page', (string) ( $b['page'] ?? '' ) ),
			preg_match( '/^[a-z]{2}(-[a-z]{2})?$/', $lang ) ? gbg_core_tag( 'lang', $lang ) : '',
			gbg_core_tag( 'src', (string) ( $b['src'] ?? '' ) ),
		)
	);
	return array(
		true,
		'',
		array(
			'email'  => $email,
			'name'   => substr( sanitize_text_field( (string) ( $b['name'] ?? '' ) ), 0, 60 ),
			'tags'   => gbg_core_tags_clean( $tags ),
			'magnet' => $m,
		),
	);
}

/**
 * Insert a new sign-up, or merge tags into an existing row. An anonymous request never re-activates an unsubscribed
 * address, never renews its consent and never overwrites a stored first name (security review 22-10): without a
 * confirmation mail the sender is not proven to own the address. A repeat sign-up only adds tags and counts.
 */
function gbg_core_lead_save( array $d, string $ip_hash ): void {
	global $wpdb;
	gbg_core_leads_install();
	$table = gbg_core_leads_table();
	$now   = gmdate( 'Y-m-d H:i:s' );
	$row   = $wpdb->get_row( $wpdb->prepare( "SELECT id, tags, first_name, status FROM $table WHERE email = %s", $d['email'] ), ARRAY_A ); // phpcs:ignore WordPress.DB
	$base  = array( 'status' => 'active', 'consent_version' => gbg_core_settings()['consent_version'], 'consent_at' => $now, 'updated_at' => $now, 'ip_hash' => $ip_hash );
	if ( $row ) {
		$tags = gbg_core_tags_clean( array_merge( array_filter( explode( ',', (string) $row['tags'] ) ), $d['tags'] ) );
		if ( 'unsubscribed' === $row['status'] ) {
			return; // stays unsubscribed; the visitor still sees the thank-you page
		}
		$wpdb->update( $table, array( 'tags' => implode( ',', $tags ), 'updated_at' => $now, 'first_name' => '' !== (string) $row['first_name'] ? $row['first_name'] : $d['name'] ), array( 'id' => (int) $row['id'] ) ); // phpcs:ignore WordPress.DB
		$wpdb->query( $wpdb->prepare( "UPDATE $table SET signups = signups + 1 WHERE id = %d", (int) $row['id'] ) ); // phpcs:ignore WordPress.DB
	} else {
		$wpdb->insert( $table, $base + array( 'email' => $d['email'], 'first_name' => $d['name'], 'tags' => implode( ',', $d['tags'] ), 'created_at' => $now, 'signups' => 1 ) ); // phpcs:ignore WordPress.DB
	}
}

function gbg_core_lead_thanks_html( array $m ): string {
	$links = '';
	foreach ( $m['files'] as $f ) {
		$links .= '<li><a href="' . esc_url( $f['url'] ) . '" download>' . esc_html( $f['label'] ) . '</a></li>';
	}
	return '<p><strong>' . esc_html( $m['thanks'] ) . '</strong></p>' . ( $links ? '<ul>' . $links . '</ul>' : '' );
}

/** Counts per tag group and week; no addresses. */
function gbg_core_leads_stats(): array {
	global $wpdb;
	gbg_core_leads_install();
	$table = gbg_core_leads_table();
	$rows  = $wpdb->get_results( "SELECT tags, status, created_at FROM $table", ARRAY_A ); // phpcs:ignore WordPress.DB
	$out   = array( 'total' => 0, 'active' => 0, 'unsubscribed' => 0, 'by_tag' => array(), 'by_week' => array() );
	foreach ( (array) $rows as $r ) {
		$out['total']++;
		$out[ 'unsubscribed' === $r['status'] ? 'unsubscribed' : 'active' ]++;
		$week                     = gmdate( 'o-\WW', strtotime( $r['created_at'] ) );
		$out['by_week'][ $week ]  = ( $out['by_week'][ $week ] ?? 0 ) + 1;
		foreach ( array_filter( explode( ',', (string) $r['tags'] ) ) as $tag ) {
			[ $group, $value ]                    = array_pad( explode( ':', $tag, 2 ), 2, '' );
			$out['by_tag'][ $group ][ $value ] = ( $out['by_tag'][ $group ][ $value ] ?? 0 ) + 1;
		}
	}
	ksort( $out['by_week'] );
	return $out;
}

/** Unsubscribe links carry an HMAC of the address, so nobody can unsubscribe someone else. */
function gbg_core_lead_key( string $email ): string {
	return substr( hash_hmac( 'sha256', strtolower( $email ), wp_salt( 'auth' ) ), 0, 20 );
}

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'gbg/v1',
			'/lead',
			array(
				'methods'             => 'POST',
				// Public on purpose (visitors sign up from cached pages); closed while the module is off.
				'permission_callback' => static fn() => (bool) gbg_core_settings()['leads_enabled'],
				'callback'            => static function ( WP_REST_Request $request ) {
					$b = json_decode( $request->get_body(), true );
					[ $ok, $err, $d ] = gbg_core_lead_validate( is_array( $b ) ? $b : array() );
					$ip_hash = substr( hash( 'sha256', gbg_core_client_ip( $_SERVER ) . wp_salt( 'nonce' ) ), 0, 16 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
					if ( ! $ok ) {
						// Bots get the same success shape, so the honeypot is not revealed.
						return 'spam' === $err ? array( 'ok' => true, 'html' => '<p><strong>Thanks!</strong></p>' )
							: new WP_Error( 'gbg_lead_' . $err, 'Invalid ' . $err, array( 'status' => 400 ) );
					}
					if ( ! gbg_core_lead_rate_ok( $ip_hash ) ) {
						return new WP_Error( 'gbg_lead_rate', 'Too many sign-ups, try again later.', array( 'status' => 429 ) );
					}
					gbg_core_lead_save( $d, $ip_hash );
					gbg_core_mp_send( 'generate_lead', array( 'lead_magnet' => (string) ( $d['magnet']['id'] ?? '' ), 'page_location' => (string) ( $_SERVER['HTTP_REFERER'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
					$country = current( array_filter( $d['tags'], static fn( $t ) => 0 === strpos( $t, 'country:' ) ) );
					return array( 'ok' => true, 'html' => gbg_core_lead_thanks_html( $d['magnet'] ), 'country' => $country ? substr( $country, 8 ) : '' );
				},
			)
		);
		register_rest_route(
			'gbg/v1',
			'/lead/unsubscribe',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => static function ( WP_REST_Request $request ) {
					global $wpdb;
					$email = strtolower( (string) $request->get_param( 'e' ) );
					if ( ! is_email( $email ) || ! hash_equals( gbg_core_lead_key( $email ), (string) $request->get_param( 'k' ) ) ) {
						return new WP_Error( 'gbg_lead_key', 'Invalid link.', array( 'status' => 400 ) );
					}
					$wpdb->update( gbg_core_leads_table(), array( 'status' => 'unsubscribed', 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'email' => $email ) ); // phpcs:ignore WordPress.DB
					return array( 'ok' => true, 'message' => 'You are unsubscribed.' );
				},
			)
		);
		register_rest_route(
			'gbg/v1',
			'/leads/stats',
			array(
				'methods'             => 'GET',
				'permission_callback' => 'gbg_core_can_manage',
				'callback'            => static fn() => gbg_core_leads_stats(),
			)
		);
		register_rest_route(
			'gbg/v1',
			'/leads/export',
			array(
				'methods'             => 'GET',
				// Addresses only for administrators (not for Claude's editor account).
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'callback'            => static function () {
					global $wpdb;
					gbg_core_leads_install();
					$rows = $wpdb->get_results( 'SELECT email, first_name, tags, status, consent_version, consent_at, created_at FROM ' . gbg_core_leads_table() . ' ORDER BY id', ARRAY_A ); // phpcs:ignore WordPress.DB
					foreach ( $rows as &$r ) {
						$r['unsubscribe_key'] = gbg_core_lead_key( $r['email'] );
					}
					return $rows;
				},
			)
		);
		register_rest_route(
			'gbg/v1',
			'/leads/delete',
			array(
				'methods'             => 'POST',
				// AVG erasure request: administrators only.
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'callback'            => static function ( WP_REST_Request $request ) {
					global $wpdb;
					$email = strtolower( (string) ( $request->get_json_params()['email'] ?? '' ) );
					$n     = is_email( $email ) ? (int) $wpdb->delete( gbg_core_leads_table(), array( 'email' => $email ) ) : 0; // phpcs:ignore WordPress.DB
					return array( 'deleted' => $n );
				},
			)
		);
	}
);

/* Tools → GoBackpackGo leads: counts and a CSV download for administrators (export for a newsletter tool later). */
add_action(
	'admin_menu',
	static function () {
		add_management_page(
			'GoBackpackGo leads',
			'GoBackpackGo leads',
			'manage_options',
			'gbg-leads',
			static function () {
				$s = gbg_core_leads_stats();
				echo '<div class="wrap"><h1>GoBackpackGo leads</h1><p>' . esc_html( sprintf( '%d sign-ups (%d active, %d unsubscribed).', $s['total'], $s['active'], $s['unsubscribed'] ) ) . '</p>';
				foreach ( $s['by_tag'] as $group => $values ) {
					arsort( $values );
					echo '<h2>' . esc_html( $group ) . '</h2><p>' . esc_html( implode( ' · ', array_map( static fn( $k, $v ) => "$k: $v", array_keys( $values ), $values ) ) ) . '</p>';
				}
				echo '<p><a class="button button-primary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gbg_leads_csv' ), 'gbg_leads_csv' ) ) . '">Download CSV</a></p></div>';
			}
		);
	}
);

add_action(
	'admin_post_gbg_leads_csv',
	static function () {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'gbg_leads_csv' ) ) {
			wp_die( 'Not allowed.' );
		}
		global $wpdb;
		gbg_core_leads_install();
		$rows = $wpdb->get_results( 'SELECT email, first_name, tags, status, consent_version, consent_at, created_at FROM ' . gbg_core_leads_table() . ' ORDER BY id', ARRAY_A ); // phpcs:ignore WordPress.DB
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="gobackpackgo-leads-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'email', 'first_name', 'tags', 'status', 'consent_version', 'consent_at', 'created_at', 'unsubscribe_key' ) );
		foreach ( $rows as $r ) {
			$r[] = gbg_core_lead_key( $r['email'] );
			fputcsv( $out, array_values( $r ) );
		}
		exit;
	}
);

/* -------------------------------------------------------------------------
 * Save & share bar (Paul 21-10): readers save or share an article where they decide to keep it, near the top (after the
 * first paragraph, so the direct answer stays first). Buttons: the phone's own share sheet (mobile), Pinterest,
 * WhatsApp, e-mail, copy link and 'Save as PDF' (print view without header, footer and buttons: handy offline, which
 * is what Pinterest's 'Design the Exit' audience wants). All plain links: no third-party scripts, no cookies until a
 * reader clicks. Shared links carry utm_source=<channel>&utm_medium=share so GA4 shows which channel brings readers back.
 * Pin image: post meta _gbg_pin_image (a designed 2:3 pin) if set, else the featured image.
 * Image buttons (pin_enabled): a floating 'Save' on article images on desktop hover; data-pin-nopin and images narrower
 * than pin_min_width get none. Both off until switched on.
 * ---------------------------------------------------------------------- */

/** Pinterest share URL for a page, an image and a description. */
function gbg_core_pin_url( string $url, string $media, string $description ): string {
	return 'https://www.pinterest.com/pin/create/button/?url=' . rawurlencode( $url ) . '&media=' . rawurlencode( $media )
		. '&description=' . rawurlencode( wp_strip_all_tags( $description ) );
}

/** The page URL with share UTM tags for one channel. */
function gbg_core_share_link( string $url, string $channel ): string {
	return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . 'utm_source=' . rawurlencode( $channel ) . '&utm_medium=share';
}

/** Save & share bar for a post (empty when there is no image to pin and sharing is off). */
function gbg_core_share_bar( int $post_id ): string {
	$url   = (string) get_permalink( $post_id );
	$title = wp_strip_all_tags( (string) get_the_title( $post_id ) );
	$media = (string) get_post_meta( $post_id, '_gbg_pin_image', true );
	if ( '' === $media ) {
		$media = (string) get_the_post_thumbnail_url( $post_id, 'full' );
	}
	// Icon + label: full label on desktop, a one-word caption under the icon on mobile. Both are real text (SEO, screen
	// readers); the icons are decorative (aria-hidden).
	$icon  = static fn( string $d, bool $fill = false ) => '<svg aria-hidden="true" focusable="false" width="22" height="22" viewBox="0 0 24 24" '
		. ( $fill ? 'fill="currentColor"' : 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"' ) . '><path d="' . $d . '"/></svg>';
	$icons = array(
		'native'    => $icon( 'M12 3v12M7 8l5-5 5 5M5 13v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6' ),
		'pinterest' => $icon( 'M12 2a10 10 0 0 0-3.6 19.3c-.1-.8-.2-2 0-2.9l1.2-5s-.3-.6-.3-1.5c0-1.4.8-2.4 1.8-2.4.9 0 1.3.6 1.3 1.4 0 .9-.5 2.1-.8 3.3-.2 1 .5 1.8 1.5 1.8 1.8 0 3.1-1.9 3.1-4.6 0-2.4-1.7-4.1-4.2-4.1-2.9 0-4.5 2.1-4.5 4.4 0 .9.3 1.8.8 2.3l.1.4-.3 1.2c0 .2-.2.3-.4.2-1.3-.6-2.1-2.5-2.1-4 0-3.3 2.4-6.3 6.9-6.3 3.6 0 6.4 2.6 6.4 6 0 3.6-2.3 6.5-5.4 6.5-1.1 0-2.1-.6-2.4-1.2l-.7 2.5c-.2.9-.9 2.1-1.3 2.8A10 10 0 1 0 12 2z', true ),
		'email'     => $icon( 'M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zM3 7l9 6 9-6' ),
		'copy'      => $icon( 'M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1' ),
		'pdf'       => $icon( 'M12 3v12M7 10l5 5 5-5M5 21h14' ),
		'whatsapp'  => $icon( 'M20 12a8 8 0 0 1-11.8 7L4 20l1-4.1A8 8 0 1 1 20 12z' ),
	);
	$inner = static fn( string $key, string $label, string $short ) => $icons[ $key ] . '<span class="gbg-share-txt">' . esc_html( $label ) . '</span><span class="gbg-share-short" aria-hidden="true">' . esc_html( $short ) . '</span>';
	$btn   = static fn( string $key, string $href, string $label, string $short, string $extra = '' ) => '<a class="gbg-share-btn gbg-share-' . $key . '" href="'
		. esc_url( $href ) . '" data-gbg-share="' . $key . '"' . $extra . '>' . $inner( $key, $label, $short ) . '</a>';
	$items = array( '<button type="button" class="gbg-share-btn gbg-share-native" data-gbg-share="native" hidden>' . $inner( 'native', 'Save or send…', 'Save' ) . '</button>' );
	if ( '' !== $media ) {
		$items[] = $btn( 'pinterest', gbg_core_pin_url( gbg_core_share_link( $url, 'pinterest' ), $media, $title ), 'Save to Pinterest', 'Pinterest', ' target="_blank" rel="noopener nofollow"' );
	}
	$items[] = $btn( 'email', 'mailto:?subject=' . rawurlencode( $title ) . '&body=' . rawurlencode( "Saved for my trip:\n" . gbg_core_share_link( $url, 'email' ) ), 'Email it to myself', 'Email', ' rel="nofollow"' );
	$items[] = '<button type="button" class="gbg-share-btn gbg-share-copy" data-gbg-share="copy" data-url="' . esc_attr( gbg_core_share_link( $url, 'copy' ) ) . '" hidden>' . $inner( 'copy', 'Copy the link', 'Link' ) . '</button>';
	$items[] = '<button type="button" class="gbg-share-btn gbg-share-pdf" data-gbg-share="pdf" hidden>' . $inner( 'pdf', 'Save as PDF for offline', 'PDF' ) . '</button>';
	$items[] = $btn( 'whatsapp', 'https://wa.me/?text=' . rawurlencode( $title . ' ' . gbg_core_share_link( $url, 'whatsapp' ) ), 'Send to a travel buddy', 'WhatsApp', ' target="_blank" rel="noopener nofollow"' );
	return '<div class="gbg-share" role="group" aria-label="Save this guide"><span class="gbg-share-label">' . esc_html( (string) gbg_core_settings()['share_label'] ) . '</span>'
		. implode( '', $items ) . '<span class="gbg-share-thanks" role="status" aria-live="polite"></span></div>';
}

/** End offset of the hero section (id="intro", else the first section), matching nested sections; -1 when none. */
function gbg_core_hero_end( string $html ): int {
	$start = preg_match( '~<section\b[^>]*\bid="intro"~i', $html, $m, PREG_OFFSET_CAPTURE ) ? $m[0][1] : stripos( $html, '<section' );
	if ( false === $start ) {
		return -1;
	}
	preg_match_all( '~<section\b|</section>~i', $html, $tags, PREG_OFFSET_CAPTURE, (int) $start );
	$depth = 0;
	foreach ( $tags[0] as $t ) {
		$depth += '<' === $t[0][0] && '/' !== $t[0][1] ? 1 : -1;
		if ( 0 === $depth ) {
			return $t[1] + strlen( $t[0] );
		}
	}
	return -1;
}

/** Insert the bar after the first real paragraph (the direct answer stays first); fall back to the bottom. */
function gbg_core_share_insert( string $content, string $bar, string $position ): string {
	if ( 'bottom' === $position ) {
		return $content . $bar;
	}
	// The first paragraph with at least 20 words: skips subtitles and short intro lines in page-builder headers.
	// None found: under the article, never inside a header block.
	$top = $content . $bar;
	if ( preg_match_all( '~<p[\s>].*?</p>~is', $content, $all, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $all[0] as $p ) {
			if ( str_word_count( wp_strip_all_tags( $p[0] ) ) >= 20 && ! str_contains( $p[0], 'gbg-disclosure' ) ) {
				$end = $p[1] + strlen( $p[0] );
				$top = substr( $content, 0, $end ) . $bar . substr( $content, $end );
				break;
			}
		}
	}
	return 'both' === $position ? $top . $bar : $top;
}

add_filter(
	'the_content',
	static function ( $content ) {
		$s = gbg_core_settings();
		if ( ! $s['share_enabled'] || ! is_string( $content ) || is_admin() || is_feed() || ! is_singular( array( 'post', 'page' ) ) || is_front_page()
			|| ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || str_contains( $content, 'class="gbg-share"' ) ) {
			return $content;
		}
		$post_id = (int) get_the_ID();
		if ( $post_id !== (int) get_queried_object_id() || in_array( (string) get_post_field( 'post_name', $post_id ), (array) $s['share_skip_slugs'], true ) ) {
			return $content;
		}
		$bar = gbg_core_share_bar( $post_id );
		// Pages built with sections (eSIM, flight compensation, car rental, hubs): right under the first section (the
		// hero with the answer and the main button), inside the theme's content width.
		$end = 'page' === get_post_type( $post_id ) ? gbg_core_hero_end( $content ) : -1;
		if ( $end > 0 ) {
			$wrap = '<section class="l-section wpb_row height_auto gbg-share-section" style="padding-top:0;padding-bottom:0"><div class="l-section-h i-cf">' . $bar . '</div></section>';
			return substr( $content, 0, $end ) . $wrap . substr( $content, $end );
		}
		return gbg_core_share_insert( $content, $bar, (string) $s['share_position'] );
	},
	30
);

add_action(
	'wp_footer',
	static function () {
		$s = gbg_core_settings();
		if ( ( ! $s['share_enabled'] && ! $s['pin_enabled'] ) || is_admin() || ! is_singular() || is_front_page() ) {
			return;
		}
		$id  = get_queried_object_id();
		$cfg = wp_json_encode( array( 'pin' => (bool) $s['pin_enabled'], 'min' => (int) $s['pin_min_width'], 'url' => gbg_core_share_link( (string) get_permalink( $id ), 'pinterest' ), 'title' => wp_strip_all_tags( (string) get_the_title( $id ) ) ) );
		echo '<style id="gbg-share-css">.gbg-share{display:flex;flex-wrap:wrap;align-items:center;gap:.5em;margin:1.2em 0 1.6em;padding:.8em 0;border-top:1px solid rgba(0,0,0,.08);border-bottom:1px solid rgba(0,0,0,.08)}'
			. '.gbg-share-label{flex-basis:100%;font-weight:600;font-size:.95em}.gbg-share-btn{display:inline-flex;align-items:center;min-height:40px;padding:0 1em;border:1px solid rgba(0,0,0,.18);border-radius:20px;background:transparent;color:inherit!important;font:inherit;font-size:.9em;text-decoration:none!important;cursor:pointer}'
			. '.gbg-share-btn{gap:.45em}.gbg-share-btn svg{flex:none}.gbg-share-short{display:none}.gbg-share-btn:hover{border-color:currentColor}.gbg-share-pinterest{background:#e60023;border-color:#e60023;color:#fff!important}.gbg-share-whatsapp{border-color:#25d366}.gbg-share [hidden]{display:none!important}.gbg-share-thanks{flex-basis:100%;font-size:.9em;font-weight:600;color:#2e7d32}.gbg-share-thanks:empty{display:none}@media(max-width:767px){.gbg-share{gap:.6em}.gbg-share-native{order:1}.gbg-share-whatsapp{order:2}.gbg-share-pinterest{order:3}.gbg-share-pdf{order:4}.gbg-share-email,.gbg-share-copy{order:5}.gbg-share-label,.gbg-share-thanks{order:0}.gbg-share-thanks{order:9}.gbg-share-btn{flex-direction:column;justify-content:center;gap:.25em;width:72px;min-height:64px;padding:.5em 0;border-radius:14px;font-size:.75em}.gbg-share-txt{display:none}.gbg-share-short{display:block;line-height:1.1}}.gbg-has-native .gbg-share-email,.gbg-has-native .gbg-share-copy{display:none!important}'
			. '.gbg-pin-img{position:absolute;z-index:50;display:none;padding:.4em .9em;border-radius:20px;background:#e60023;color:#fff!important;font:600 14px/1.4 sans-serif;text-decoration:none!important;box-shadow:0 2px 6px rgba(0,0,0,.25)}'
			. '@media print{.l-header,header,.l-footer,footer,.gbg-share,.gbg-bar,.gbg-optin,.gbg-pin-img,#gt_float_wrapper,.w-toplink,.w-btn{display:none!important}body{background:#fff}}</style>'
			. '<script id="gbg-share-js">(function(c){'
			. 'function ev(m){if(window.gbgEv)gbgEv("share",{method:m,item_id:location.pathname});var t=document.querySelector(".gbg-share-thanks");if(t&&m!=="whatsapp"&&m!=="native")t.textContent="Good call. Future you will thank you on the road."}'
			. 'document.querySelectorAll(".gbg-share").forEach(function(bar){'
			. 'var n=bar.querySelector(".gbg-share-native"),cp=bar.querySelector(".gbg-share-copy"),pdf=bar.querySelector(".gbg-share-pdf");'
			. 'if(navigator.share&&matchMedia("(pointer:coarse)").matches){n.hidden=false;bar.classList.add("gbg-has-native");n.onclick=function(){navigator.share({title:c.title,url:cp.getAttribute("data-url").replace("utm_source=copy","utm_source=native")}).then(function(){ev("native")},function(){})}}'
			. 'if(navigator.clipboard){cp.hidden=false;cp.onclick=function(){navigator.clipboard.writeText(cp.getAttribute("data-url")).then(function(){var tx=cp.querySelector(".gbg-share-txt");tx.textContent="Link copied";ev("copy");setTimeout(function(){tx.textContent="Copy the link"},2000)})}}'
			. 'pdf.hidden=false;pdf.onclick=function(){ev("pdf");window.print()};'
			. 'bar.querySelectorAll("a.gbg-share-btn").forEach(function(a){a.addEventListener("click",function(){ev(a.getAttribute("data-gbg-share"))})})});'
			. 'if(!c.pin||!matchMedia("(hover:hover) and (pointer:fine)").matches)return;'
			. 'var root=document.querySelector(".post_content")||document.querySelector("main")||document.body,b=document.createElement("a"),cur=null,t;'
			. 'b.className="gbg-pin-img";b.textContent="Save";b.target="_blank";b.rel="noopener nofollow";document.body.appendChild(b);b.addEventListener("click",function(){ev("pinterest-image")});'
			. 'function src(i){var best=i.currentSrc||i.src,w=0;(i.getAttribute("srcset")||"").split(",").forEach(function(p){var x=p.trim().split(/\s+/);var k=parseInt(x[1]);if(k>w){w=k;best=x[0]}});return best}'
			. 'function show(i){cur=i;var r=i.getBoundingClientRect();b.style.top=(r.top+scrollY+10)+"px";b.style.left=(r.left+scrollX+10)+"px";'
			. 'b.href="https://www.pinterest.com/pin/create/button/?url="+encodeURIComponent(c.url)+"&media="+encodeURIComponent(i.getAttribute("data-pin-media")||src(i))+"&description="+encodeURIComponent(i.alt||c.title);b.style.display="block"}'
			. 'root.addEventListener("mouseover",function(e){var i=e.target;if(i.tagName!=="IMG"||i.hasAttribute("data-pin-nopin")||i.getBoundingClientRect().width<c.min)return;clearTimeout(t);show(i)});'
			. 'root.addEventListener("mouseout",function(e){if(e.target===cur&&e.relatedTarget!==b)t=setTimeout(function(){b.style.display="none"},300)});'
			. 'b.addEventListener("mouseleave",function(){b.style.display="none"});'
			. '})(' . $cfg . ');</script>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
);

/* -------------------------------------------------------------------------
 * Gallery lightbox (Paul 21-10: 'popup is echt belachelijk slecht'): Impreza opens gallery images with Magnific Popup,
 * but its stylesheet is not in the theme's bundled CSS, so the popup showed a bare image under a grey bar (no dark
 * backdrop, no arrows, no counter, no caption). Load the theme's own magnific-popup.css on single views that have a
 * gallery or popup image, and keep the popup above the floating language flag. Setting lightbox_fix (default on).
 * ---------------------------------------------------------------------- */
add_action(
	'wp_enqueue_scripts',
	static function () {
		$s = gbg_core_settings();
		if ( empty( $s['lightbox_fix'] ) || ! is_singular() || 'Impreza' !== get_template() ) {
			return;
		}
		$post = get_post();
		if ( ! $post || ! preg_match( '~\[gallery|\[us_gallery|action_popup|onclick="popup_image"|link_type="popup_image"~', (string) $post->post_content ) ) {
			return;
		}
		$file = '/common/css/base/magnific-popup.css';
		if ( ! file_exists( get_template_directory() . $file ) ) {
			return;
		}
		wp_enqueue_style( 'gbg-magnific-popup', get_template_directory_uri() . $file, array(), (string) wp_get_theme( 'Impreza' )->get( 'Version' ) );
		wp_add_inline_style( 'gbg-magnific-popup', '.mfp-bg{z-index:1000000!important;background:#0b0b0b}.mfp-bg.mfp-ready{opacity:.94!important}.mfp-wrap{z-index:1000001!important}.mfp-title{font-size:15px;line-height:1.4;padding-right:60px}.mfp-counter{font-size:13px}' );
	},
	20
);

/* -------------------------------------------------------------------------
 * Author profiles Yoast has no field for (Paul 21-10: Strava): extra sameAs links for the Person in the schema, set
 * with POST /gbg/v1/settings {"person_same_as": ["https://www.strava.com/athletes/..."]}. Only for real users' Person
 * pieces (not the Organization).
 * ---------------------------------------------------------------------- */
add_filter(
	'wpseo_schema_person',
	static function ( $data ) {
		$extra = (array) ( gbg_core_settings()['person_same_as'] ?? array() );
		if ( ! $extra || ! is_array( $data ) ) {
			return $data;
		}
		$data['sameAs'] = array_values( array_unique( array_merge( (array) ( $data['sameAs'] ?? array() ), $extra ) ) );
		return $data;
	}
);

/* -------------------------------------------------------------------------
 * Generated affiliate links and promo codes (Paul 21-10: no hand-made links, everything from the platforms' APIs).
 * scripts/affiliate_links.py creates the links (Travelpayouts links API, impact.com TrackingLinks) and posts
 * {"go_links": {slug: url}, "promos": [...]} to /gbg/v1/settings. /go/<slug>/ then answers with a 302 to that URL
 * (noindex, not cached) before Lasso sees the request; slugs not in the map stay with Lasso. Only https targets on
 * go_hosts (or a subdomain) are accepted. [gbg_promo merchant="airhelp"] shows a valid code until its end date.
 * ---------------------------------------------------------------------- */

function gbg_core_host_allowed( string $host, array $hosts ): bool {
	foreach ( $hosts as $h ) {
		if ( $host === $h || str_ends_with( $host, '.' . $h ) ) {
			return true;
		}
	}
	return false;
}

function gbg_core_go_sanitize( array $links, array $hosts ): array {
	$out = array();
	foreach ( $links as $slug => $url ) {
		$slug = strtolower( (string) $slug );
		$url  = trim( (string) $url );
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( preg_match( '/^[a-z0-9-]{1,60}$/', $slug ) && str_starts_with( $url, 'https://' ) && gbg_core_host_allowed( $host, $hosts ) ) {
			$out[ $slug ] = esc_url_raw( $url );
		}
	}
	return $out;
}

// Target for a request path (with or without a language prefix such as /nl/), or null when it is not ours.
function gbg_core_go_target( string $path, array $links ): ?string {
	if ( ! $links || ! preg_match( '#^/(?:[a-z]{2}(?:-[a-z]{2})?/)?go/([a-z0-9-]+)/?$#', strtolower( $path ), $m ) ) {
		return null;
	}
	return $links[ $m[1] ] ?? null;
}

// Country link (?c=) with the page's sub_id (?s=p123) when known; otherwise the generic target.
function gbg_core_go_country_target( string $path, array $query, array $s ): ?string {
	$target = gbg_core_go_target( $path, (array) $s['go_links'] );
	if ( ! $target || ! preg_match( '#/go/([a-z0-9-]+)#', strtolower( $path ), $m ) ) {
		return $target;
	}
	$c   = strtolower( (string) ( $query['c'] ?? '' ) );
	$url = (string) ( $s['go_country'][ $m[1] ][ $c ] ?? '' ) ?: $target;
	$sub = (string) ( $query['s'] ?? '' );
	// The page ID (and the link) become the Travelpayouts sub_id, so sales show per page. Short links have no sub_id to set.
	return preg_match( '/^p\d{1,9}$/', $sub ) ? (string) preg_replace( '/([?&]sub_id=)[^&]*/', '${1}' . $sub . '-' . $m[1], $url ) : $url;
}

// Every /go/<slug>/ link on a page without a query yet carries the page ID (?s=p123): sub_id and counter per page.
function gbg_core_go_page_links( string $html, int $post_id, array $slugs ): string {
	if ( ! $slugs || $post_id < 1 ) {
		return $html;
	}
	$rx = '#href="((?:https?://' . preg_quote( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '#' ) . ')?/go/(?:' . implode( '|', array_map( 'preg_quote', $slugs ) ) . ')/)"#';
	return (string) preg_replace( $rx, 'href="$1?s=p' . $post_id . '"', $html );
}

// On a mapped page: /go/<slug>/ links (own host or relative) carry the page's country and ID.
function gbg_core_go_country_links( string $html, string $country, int $post_id, array $slugs ): string {
	if ( ! $slugs ) {
		return $html;
	}
	$rx = '#href="((?:https?://' . preg_quote( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '#' ) . ')?/go/(' . implode( '|', array_map( 'preg_quote', $slugs ) ) . ')/)"#';
	return (string) preg_replace( $rx, 'href="$1?c=' . $country . '&amp;s=p' . $post_id . '"', $html );
}

add_action(
	'template_redirect',
	static function () {
		$s = gbg_core_settings();
		if ( ! $s['go_links'] || is_admin() || is_feed() || ! is_singular() ) {
			return;
		}
		$post_id = (int) get_queried_object_id();
		$slugs   = array_keys( (array) $s['go_links'] );
		$country = (string) ( $s['go_pages'][ (string) get_post_field( 'post_name', $post_id ) ] ?? '' );
		$cslugs  = array_keys( (array) $s['go_country'] );
		ob_start(
			static function ( $html ) use ( $post_id, $slugs, $country, $cslugs ) {
				if ( ! is_string( $html ) || '' === $html ) {
					return $html;
				}
				// Links outside the content (sticky button, footer) get the page's country too, then every /go/ link the page ID.
				$html = $country ? gbg_core_go_country_links( $html, $country, $post_id, $cslugs ) : $html;
				return gbg_core_go_page_links( $html, $post_id, $slugs );
			}
		);
	},
	20
);

// WebP copies (Imagify writes <file>.<ext>.webp next to the original) for own uploads; $exists decides per file.
function gbg_core_webp_html( string $html, string $base, callable $exists ): string {
	$rx  = '#' . preg_quote( rtrim( $base, '/' ), '#' ) . '/[^"\'\s(),]+?\.(?:jpe?g|png)(?=["\'\s),])#i';
	$map = array();
	$html = (string) preg_replace_callback(
		$rx,
		static function ( $m ) use ( $exists, &$map ) {
			if ( ! isset( $map[ $m[0] ] ) ) {
				$map[ $m[0] ] = $exists( $m[0] . '.webp' ) ? $m[0] . '.webp' : $m[0];
			}
			return $map[ $m[0] ];
		},
		$html
	);
	// The hero is a CSS background, which the browser finds late: preload the first one with high priority.
	if ( preg_match( '#background-image:\s*url\(\s*["\']?(' . preg_quote( rtrim( $base, '/' ), '#' ) . '/[^"\')]+)#i', $html, $m ) && ! str_contains( $html, 'rel="preload" as="image"' ) ) {
		$link = '<link rel="preload" as="image" href="' . esc_url( $m[1] ) . '" fetchpriority="high">';
		$html = (string) preg_replace( '#</title>#i', '</title>' . $link, $html, 1 );
	}
	return $html;
}

// Lazy theme images without a srcset get the attachment's srcset; sizes=auto lets the browser use the laid-out width.
function gbg_core_img_srcset( string $html, callable $srcset_for ): string {
	return (string) preg_replace_callback(
		'#<img\b[^>]*\bclass="[^"]*\battachment-[a-z0-9_-]+[^"]*"[^>]*>#i',
		static function ( $m ) use ( $srcset_for ) {
			$tag = $m[0];
			if ( stripos( $tag, 'srcset=' ) !== false || stripos( $tag, 'loading="lazy"' ) === false || ! preg_match( '#\bsrc="([^"]+)"#', $tag, $src ) ) {
				return $tag;
			}
			$set = (string) $srcset_for( html_entity_decode( $src[1] ) );
			if ( '' === $set ) {
				return $tag;
			}
			return (string) preg_replace( '#\bsrc="#', 'srcset="' . esc_attr( $set ) . '" sizes="auto, 100vw" src="', $tag, 1 );
		},
		$html
	);
}

// The Google Fonts stylesheet link points to our stored copy when one exists for exactly that URL.
function gbg_core_local_fonts_html( string $html, array $map ): string {
	if ( ! $map ) {
		return $html;
	}
	return (string) preg_replace_callback(
		'#(href=[\'"])(https://fonts\.googleapis\.com/css2\?[^\'"]+)([\'"])#',
		static fn( $m ) => isset( $map[ html_entity_decode( $m[2] ) ] ) ? $m[1] . esc_url( $map[ html_entity_decode( $m[2] ) ] ) . $m[3] : $m[0],
		$html
	);
}

add_action(
	'template_redirect',
	static function () {
		$s = gbg_core_settings();
		if ( ! ( $s['webp_enabled'] || $s['srcset_enabled'] || $s['local_fonts'] ) || is_admin() || is_feed() || wp_doing_ajax() ) {
			return;
		}
		$up = wp_get_upload_dir();
		ob_start(
			static function ( $html ) use ( $up, $s ) {
				if ( ! is_string( $html ) || '' === $html || ! str_contains( $html, '</title>' ) ) {
					return $html;
				}
				$base = (string) $up['baseurl'];
				$dir  = (string) $up['basedir'];
				if ( $s['srcset_enabled'] ) {
					$html = gbg_core_img_srcset(
						$html,
						static function ( $url ) use ( $base ) {
							if ( ! str_starts_with( $url, $base ) ) {
								return '';
							}
							$id = attachment_url_to_postid( (string) preg_replace( '#-\d+x\d+(\.[a-z]+)$#i', '$1', $url ) ) ?: attachment_url_to_postid( $url );
							return $id ? (string) wp_get_attachment_image_srcset( $id, 'large' ) : '';
						}
					);
				}
				if ( $s['local_fonts'] ) {
					$html = gbg_core_local_fonts_html( $html, (array) get_option( 'gbg_core_local_fonts', array() ) );
				}
				if ( $s['webp_enabled'] ) {
					$html = gbg_core_webp_html( $html, $base, static fn( $url ) => is_file( $dir . substr( $url, strlen( $base ) ) ) );
				}
				return $html;
			}
		);
	},
	21
);

// Store a Google Fonts stylesheet and its font files under uploads/gbg-fonts/ (admin only; Google hosts only).
add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'gbg/v1',
			'/local-fonts',
			array(
				'methods'             => 'POST',
				'permission_callback' => 'gbg_core_can_manage',
				'callback'            => static function ( WP_REST_Request $request ) {
					$url = html_entity_decode( (string) $request->get_param( 'url' ) );
					if ( ! preg_match( '#^https://fonts\.googleapis\.com/css2\?[\w=&:;,.@+%-]+$#', $url ) ) {
						return new WP_Error( 'gbg_url', 'Only a fonts.googleapis.com/css2 URL', array( 'status' => 400 ) );
					}
					$ua  = array( 'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36' );
					$res = wp_safe_remote_get( $url, array( 'timeout' => 20, 'headers' => $ua ) );
					$css = (string) wp_remote_retrieve_body( $res );
					if ( 200 !== wp_remote_retrieve_response_code( $res ) || ! str_contains( $css, '@font-face' ) ) {
						return new WP_Error( 'gbg_fetch', 'Google Fonts did not answer', array( 'status' => 502 ) );
					}
					$up   = wp_get_upload_dir();
					$sub  = '/gbg-fonts/' . substr( md5( $url ), 0, 10 );
					$dir  = $up['basedir'] . $sub;
					wp_mkdir_p( $dir );
					$n = 0;
					$css = (string) preg_replace_callback(
						'#url\((https://fonts\.gstatic\.com/[\w/.-]+\.woff2)\)#',
						static function ( $m ) use ( $dir, $up, $sub, &$n ) {
							$name = basename( $m[1] );
							if ( ! is_file( "$dir/$name" ) ) {
								$f = wp_safe_remote_get( $m[1], array( 'timeout' => 20 ) );
								if ( 200 !== wp_remote_retrieve_response_code( $f ) ) {
									return $m[0];
								}
								file_put_contents( "$dir/$name", wp_remote_retrieve_body( $f ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
								++$n;
							}
							return 'url(' . $up['baseurl'] . "$sub/$name)";
						},
						$css
					);
					if ( str_contains( $css, 'fonts.gstatic.com' ) ) {
						return new WP_Error( 'gbg_fetch', 'Not every font file could be stored; nothing switched', array( 'status' => 502 ) );
					}
					file_put_contents( "$dir/fonts.css", $css ); // phpcs:ignore WordPress.WP.AlternativeFunctions
					$map         = (array) get_option( 'gbg_core_local_fonts', array() );
					$map[ $url ] = $up['baseurl'] . "$sub/fonts.css?v=" . substr( md5( $css ), 0, 8 );
					update_option( 'gbg_core_local_fonts', $map, false );
					return array( 'url' => $url, 'local' => $map[ $url ], 'files_stored' => $n );
				},
			)
		);
	}
);

// Theme speed settings (0.3.38): read and change only the Impreza options about assets, icons, fonts and scripts, so
// they can be tested on staging without the admin screen. Every change keeps the previous values for a rollback.
function gbg_core_theme_perf_key( string $key ): bool {
	return (bool) preg_match( '/optimi[sz]e|assets|^icons_|jquery|gfont|font_display|lazy|preload|defer|ajax_load|block_editor|emoji|dashicons|migrate/i', $key );
}

function gbg_core_theme_perf_changes( array $current, array $input ): array {
	$out = array();
	foreach ( $input as $k => $v ) {
		$k = (string) $k;
		if ( gbg_core_theme_perf_key( $k ) && array_key_exists( $k, $current ) && is_scalar( $v ) && is_scalar( $current[ $k ] ) ) {
			$out[ $k ] = is_int( $current[ $k ] ) ? (int) $v : ( is_bool( $current[ $k ] ) ? (bool) $v : sanitize_text_field( (string) $v ) );
		}
	}
	return $out;
}

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'gbg/v1',
			'/theme-perf',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => 'gbg_core_can_manage',
					'callback'            => static function () {
						$opt = (array) get_option( 'usof_options_' . get_template(), array() );
						$fn  = array_values( array_filter( get_defined_functions()['user'], static fn( $f ) => (bool) preg_match( '/^us.*(asset|optimi[sz]|css_file|generate)/', $f ) ) );
						return array(
							'theme'     => get_template(),
							'options'   => array_filter( $opt, static fn( $v, $k ) => gbg_core_theme_perf_key( (string) $k ) && is_scalar( $v ), ARRAY_FILTER_USE_BOTH ),
							'functions' => $fn,
							'backup'    => get_option( 'gbg_core_theme_backup', array() ),
						);
					},
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => 'gbg_core_can_manage',
					'callback'            => static function ( WP_REST_Request $request ) {
						$name    = 'usof_options_' . get_template();
						$opt     = (array) get_option( $name, array() );
						$changes = gbg_core_theme_perf_changes( $opt, (array) $request->get_json_params() );
						if ( ! $changes ) {
							return new WP_Error( 'gbg_nothing', 'No known theme speed option in the request', array( 'status' => 400 ) );
						}
						$backup = (array) get_option( 'gbg_core_theme_backup', array() );
						foreach ( $changes as $k => $v ) {
							$backup[ $k ] = $backup[ $k ] ?? $opt[ $k ]; // the value before the first change, so a rollback goes all the way back
						}
						update_option( 'gbg_core_theme_backup', $backup, false );
						update_option( $name, array_merge( $opt, $changes ) );
						// Impreza writes its combined asset files when options are saved in the admin; do the same here.
						foreach ( array( 'us_generate_asset_files', 'usof_save_assets', 'us_update_asset_files' ) as $fn ) {
							if ( function_exists( $fn ) ) {
								$fn();
							}
						}
						do_action( 'breeze_clear_all_cache' ); // theme CSS sits inline in cached pages
						return array( 'changed' => $changes, 'backup' => $backup );
					},
				),
			)
		);
	}
);

// Generated pages that still carry the template placeholder host (https://yourwebsite.com/go/...) point to our own /go/.
function gbg_core_fix_placeholder_go( string $html ): string {
	return str_replace( array( 'https://yourwebsite.com/go/', 'http://yourwebsite.com/go/' ), home_url( '/go/' ), $html );
}

add_filter(
	'the_content',
	static function ( $content ) {
		$s = gbg_core_settings();
		if ( ! $s['go_links'] || ! is_string( $content ) || is_admin() || is_feed() || ! is_singular() ) {
			return $content;
		}
		$content = gbg_core_fix_placeholder_go( $content );
		$post_id = (int) get_the_ID();
		$country = $s['go_pages'][ (string) get_post_field( 'post_name', $post_id ) ] ?? '';
		return $country ? gbg_core_go_country_links( $content, $country, $post_id, array_keys( (array) $s['go_country'] ) ) : $content;
	},
	25
);

function gbg_core_promos_sanitize( array $promos ): array {
	$out = array();
	foreach ( $promos as $p ) {
		$p     = (array) $p;
		$code  = strtoupper( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $p['code'] ?? '' ) ) );
		$until = (string) ( $p['until'] ?? '' );
		$m     = sanitize_title( (string) ( $p['merchant'] ?? '' ) );
		if ( $code && $m && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $until ) ) {
			$out[] = array(
				'merchant' => $m,
				'code'     => substr( $code, 0, 30 ),
				'text'     => sanitize_text_field( (string) ( $p['text'] ?? '' ) ),
				'until'    => $until,
				'note'     => sanitize_text_field( (string) ( $p['note'] ?? '' ) ),
				'go'       => sanitize_title( (string) ( $p['go'] ?? $m ) ),
			);
		}
	}
	return $out;
}

function gbg_core_promo( string $merchant, string $today ): ?array {
	foreach ( (array) gbg_core_settings()['promos'] as $p ) {
		if ( $p['merchant'] === $merchant && $p['until'] >= $today ) {
			return $p;
		}
	}
	return null;
}

function gbg_core_promo_html( array $p ): string {
	static $assets = false;
	$until = gmdate( 'j F Y', (int) strtotime( $p['until'] . ' 12:00:00 UTC' ) );
	$go    = isset( gbg_core_settings()['go_links'][ $p['go'] ] ) ? '/go/' . $p['go'] . '/' : '';
	$html  = '<div class="gbg-promo" data-merchant="' . esc_attr( $p['merchant'] ) . '"><p class="gbg-promo-t">'
		. '<span class="gbg-promo-k">Promo code</span> <strong class="gbg-promo-c">' . esc_html( $p['code'] ) . '</strong> '
		. '<button type="button" class="gbg-promo-copy" data-code="' . esc_attr( $p['code'] ) . '">Copy code</button></p>'
		. ( $p['text'] ? '<p class="gbg-promo-w">' . esc_html( $p['text'] ) . '</p>' : '' )
		. '<p class="gbg-promo-v">Valid until ' . esc_html( $until ) . ( $p['note'] ? '. ' . esc_html( $p['note'] ) : '' ) . '.</p>'
		. ( $go ? '<p class="gbg-promo-a"><a href="' . esc_url( $go ) . '" rel="sponsored nofollow" target="_blank">Use the code at ' . esc_html( ucfirst( $p['merchant'] ) ) . '</a></p>' : '' )
		. '</div>';
	if ( ! $assets ) {
		$assets = true;
		$html  .= '<style>.gbg-promo{border:2px dashed #f2a33a;border-radius:10px;padding:14px 16px;margin:1.2em 0;background:rgba(242,163,58,.08)}'
			. '.gbg-promo p{margin:0 0 6px}.gbg-promo p:last-child{margin:0}.gbg-promo-k{font-size:13px;text-transform:uppercase;letter-spacing:.06em;opacity:.75}'
			. '.gbg-promo-c{font-size:1.25em;letter-spacing:.08em}.gbg-promo-copy{margin-left:8px;padding:6px 12px;border:0;border-radius:6px;background:#f2a33a;color:#1b1b1b;font-weight:600;cursor:pointer;min-height:36px}'
			. '.gbg-promo-v{font-size:13px;opacity:.8}.gbg-promo-a a{font-weight:600}</style>'
			. '<script>document.addEventListener("click",function(e){var b=e.target.closest(".gbg-promo-copy");if(!b)return;var c=b.getAttribute("data-code"),ok=function(){b.textContent="Copied"};'
			. 'if(navigator.clipboard)navigator.clipboard.writeText(c).then(ok,function(){});if(window.gbgEv)gbgEv("promo_copy",{promo_code:c})});</script>';
	}
	return $html;
}

add_shortcode(
	'gbg_promo',
	static function ( $atts ) {
		$atts = shortcode_atts( array( 'merchant' => '' ), $atts );
		$p    = gbg_core_promo( sanitize_title( (string) $atts['merchant'] ), gmdate( 'Y-m-d' ) );
		return $p ? gbg_core_promo_html( $p ) : '';
	}
);

add_action(
	'init',
	static function () {
		$target = gbg_core_go_country_target( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ), $_GET, gbg_core_settings() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput,WordPress.Security.NonceVerification
		if ( ! $target || is_admin() ) {
			return;
		}
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Cache-Control: no-store, private' );
		header( 'X-GBG-Go: 1' );
		gbg_core_go_track( $target );
		wp_redirect( $target, 302, 'GoBackpackGo' ); // phpcs:ignore WordPress.Security.SafeRedirect -- targets are allowlisted
		exit;
	},
	1
);

/* -------------------------------------------------------------------------
 * GA4 click tracking (0.3.25). Every click on a /go/<slug>/ link or a link to an affiliate host becomes the GA4 event
 * affiliate_click with link_slug, link_domain, placement (promo, ab, button, sticky, header, footer, content), ab_test
 * and link_text. GA4's own outbound tracking misses /go/ links because they are on our domain. Same helper sends
 * share, promo_copy and generate_lead. Off until track_enabled.
 * ---------------------------------------------------------------------- */

function gbg_core_track_config(): array {
	$s     = gbg_core_settings();
	$hosts = array_values( array_unique( array_merge( (array) $s['affiliate_hosts'], (array) $s['go_hosts'] ) ) );
	$go    = array();
	foreach ( (array) $s['go_links'] as $slug => $url ) {
		$go[ $slug ] = preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) );
	}
	return array( 'h' => $hosts, 'g' => $go );
}

function gbg_core_track_script(): string {
	$cfg = gbg_core_track_config() + array( 'u' => esc_url_raw( rest_url( 'gbg/v1/ev' ) ) );
	return '<script id="gbg-track">(function(c){window.gbgEv=function(n,p){p=p||{};window.dataLayer=window.dataLayer||[];var o={event:"gbg_"+n};for(var k in p)o[k]=p[k];dataLayer.push(o);'
		. 'try{navigator.sendBeacon(c.u,JSON.stringify({n:n,p:p,l:location.href}))}catch(x){}};'
		. 'document.addEventListener("click",function(e){var a=e.target.closest&&e.target.closest("a[href]");if(!a)return;var u;try{u=new URL(a.href,location.href)}catch(x){return}'
		. 'var m=u.pathname.match(/^\/(?:[a-z]{2}(?:-[a-z]{2,4})?\/)?go\/([a-z0-9-]+)/i),slug=m?m[1].toLowerCase():"",host=u.hostname.replace(/^www\./,"").toLowerCase();'
		. 'if(slug){if(u.hostname!==location.hostname)return;host=c.g[slug]||"go"}else if(!c.h.some(function(h){return host===h||host.slice(-h.length-1)==="."+h}))return;'
		. 'var ab=a.closest("[data-gbg-ab]"),pl=a.closest(".gbg-promo")?"promo":ab?"ab":a.closest("footer,.l-footer")?"footer":a.closest("header,.l-header")?"header":'
		. 'a.closest(".gbg-sticky,.w-sticky,[class*=sticky]")?"sticky":/\bw-btn\b|button/.test(a.className)?"button":"content",'
		. 'p={link_slug:slug||host,link_domain:host,placement:pl,ab_test:ab?ab.getAttribute("data-gbg-ab"):"",link_text:(a.textContent||a.getAttribute("aria-label")||"").replace(/\s+/g," ").trim().slice(0,80)};'
		// /go/ links are counted by the server on the redirect (reliable); the page only hands over where the click was.
		. 'if(slug){document.cookie="gbg_pl="+encodeURIComponent([p.placement,p.ab_test,p.link_text,location.href].map(encodeURIComponent).join("|"))+";path=/;max-age=60;SameSite=Lax";'
		. 'var o={event:"gbg_affiliate_click"};for(var k in p)o[k]=p[k];(window.dataLayer=window.dataLayer||[]).push(o)}else{gbgEv("affiliate_click",p)}},true);'
		// Cookie banner choice, counted anonymously (no cookie, no GA4): shown once per page view, then the button pressed.
		. 'var cs=function(st){try{navigator.sendBeacon(c.u,JSON.stringify({n:"consent",p:{status:st}}))}catch(x){}};'
		. 'setTimeout(function(){var b=document.querySelector(".cmplz-cookiebanner"),r=b&&b.getBoundingClientRect(),st=b&&getComputedStyle(b);'
		. 'if(b&&r.height>0&&st.display!=="none"&&st.visibility!=="hidden"&&!/cmplz-dismissed|cmplz-hidden/.test(b.className))cs("shown")},1500);'
		. 'document.addEventListener("click",function(e){var t=e.target.closest&&e.target.closest(".cmplz-accept,.cmplz-deny,.cmplz-save-preferences");if(t)cs(/accept/.test(t.className)?"allow":/deny/.test(t.className)?"deny":"custom")},true)})('
		. wp_json_encode( $cfg ) . ');</script>';
}

/** GA4 client and session id from the visitor's own GA cookies; null without consent/cookie (bots, opt-out). */
function gbg_core_ga_ids( array $cookies, string $measurement_id ): ?array {
	if ( ! preg_match( '/^GA\d\.\d\.(\d+\.\d+)$/', (string) ( $cookies['_ga'] ?? '' ), $m ) ) {
		return null;
	}
	$session = '';
	$sc      = (string) ( $cookies[ '_ga_' . substr( $measurement_id, 2 ) ] ?? '' );
	if ( preg_match( '/^GS2\.\d\.s(\d+)/', $sc, $s ) || preg_match( '/^GS1\.\d\.(\d+)\./', $sc, $s ) ) {
		$session = $s[1];
	}
	return array( 'client_id' => $m[1], 'session_id' => $session );
}

function gbg_core_mp_payload( array $ids, string $name, array $params ): array {
	$clean = array();
	foreach ( $params as $k => $v ) {
		if ( preg_match( '/^[a-z_]{1,40}$/', (string) $k ) && is_scalar( $v ) ) {
			$clean[ $k ] = substr( sanitize_text_field( (string) $v ), 0, 100 );
		}
	}
	$clean['engagement_time_msec'] = 1;
	if ( '' !== $ids['session_id'] ) {
		$clean['session_id'] = $ids['session_id'];
	}
	return array( 'client_id' => $ids['client_id'], 'events' => array( array( 'name' => $name, 'params' => $clean ) ) );
}

// Per-day counters (event + outcome) in the health report, so a missing event can be traced without server logs.
function gbg_core_mp_stat( string $name, string $outcome ): void {
	$day = gmdate( 'Y-m-d' );
	$st  = get_transient( 'gbg_core_mp_stats' );
	$st  = is_array( $st ) && ( $st['day'] ?? '' ) === $day ? $st : array( 'day' => $day, 'n' => array() );
	$key = $name . ':' . $outcome;
	$st['n'][ $key ] = ( $st['n'][ $key ] ?? 0 ) + 1;
	set_transient( 'gbg_core_mp_stats', $st, 2 * DAY_IN_SECONDS );
}

/**
 * The visitor's IP for rate limits. Behind Cloudflare REMOTE_ADDR is a Cloudflare edge shared by many visitors, so the
 * CF-Connecting-IP header wins when it holds a valid IP. Spoofing it (by calling the origin directly) only gets a caller
 * its own bucket; it never lifts a limit for others. Pure.
 */
function gbg_core_client_ip( array $server ): string {
	$cf     = (string) ( $server['HTTP_CF_CONNECTING_IP'] ?? '' );
	$remote = (string) ( $server['REMOTE_ADDR'] ?? '' );
	// Only a request that really comes from a Cloudflare edge may name the visitor (code review 22-10: a direct call to
	// the origin with a made-up header got a fresh rate-limit bucket per request).
	return filter_var( $cf, FILTER_VALIDATE_IP ) && gbg_core_ip_in( $remote, gbg_core_cloudflare_ranges() ) ? $cf : $remote;
}

/** Cloudflare's published edge ranges (https://www.cloudflare.com/ips/, checked 22-10-2026). */
function gbg_core_cloudflare_ranges(): array {
	return array( '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18', '108.162.192.0/18',
		'190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14',
		'172.64.0.0/13', '131.0.72.0/22', '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
		'2a06:98c0::/29', '2c0f:f248::/32' );
}

/** True when $ip lies in one of the CIDR ranges (IPv4 and IPv6). Pure. */
function gbg_core_ip_in( string $ip, array $cidrs ): bool {
	$bin = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	if ( false === $bin ) {
		return false;
	}
	foreach ( $cidrs as $c ) {
		[ $net, $bits ] = explode( '/', $c ) + array( '', '0' );
		$nb             = @inet_pton( $net ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( false === $nb || strlen( $nb ) !== strlen( $bin ) ) {
			continue;
		}
		$bits  = (int) $bits;
		$bytes = intdiv( $bits, 8 );
		$rest  = $bits % 8;
		if ( substr( $bin, 0, $bytes ) !== substr( $nb, 0, $bytes ) ) {
			continue;
		}
		if ( 0 === $rest || ( ( ord( $bin[ $bytes ] ) ^ ord( $nb[ $bytes ] ) ) & ( 0xFF << ( 8 - $rest ) ) & 0xFF ) === 0 ) {
			return true;
		}
	}
	return false;
}

/** Text from the visitor, sanitised and cut on characters (not bytes, so 'ç' or '—' at the edge survive). Pure. */
function gbg_core_short_text( string $t, int $n ): string {
	$t = sanitize_text_field( $t );
	return function_exists( 'mb_substr' ) ? mb_substr( $t, 0, $n ) : substr( $t, 0, $n );
}

/** Beacon fields from the browser, each checked against what the page script can send (code review 22-10). Pure. */
function gbg_core_ev_params( array $in ): array {
	$out  = array();
	$host = static fn( $v ) => preg_match( '/^[a-z0-9.-]{1,80}$/', strtolower( (string) $v ) ) ? strtolower( (string) $v ) : '';
	if ( isset( $in['link_slug'] ) ) {
		$out['link_slug'] = $host( $in['link_slug'] );
	}
	if ( isset( $in['link_domain'] ) ) {
		$out['link_domain'] = $host( $in['link_domain'] );
	}
	if ( isset( $in['placement'] ) || isset( $in['link_slug'] ) ) {
		$out['placement'] = gbg_core_placement( (string) ( $in['placement'] ?? '' ) );
	}
	if ( isset( $in['ab_test'] ) ) {
		$out['ab_test'] = preg_match( '/^[a-z0-9]{1,20}:[a-z0-9]{1,5}$/', (string) $in['ab_test'] ) ? (string) $in['ab_test'] : '';
	}
	if ( isset( $in['link_text'] ) ) {
		$out['link_text'] = gbg_core_short_text( (string) $in['link_text'], 80 );
	}
	if ( isset( $in['method'] ) ) {
		$out['method'] = in_array( $in['method'], array( 'copy', 'native', 'pdf', 'pinterest', 'whatsapp', 'email', 'facebook', 'x' ), true ) ? (string) $in['method'] : 'other';
	}
	if ( isset( $in['item_id'] ) ) {
		$out['item_id'] = preg_match( '#^/[a-z0-9/_.-]{0,150}$#i', (string) $in['item_id'] ) ? (string) $in['item_id'] : '';
	}
	if ( isset( $in['promo_code'] ) ) {
		$out['promo_code'] = preg_match( '/^[A-Z0-9_-]{1,30}$/', (string) $in['promo_code'] ) ? (string) $in['promo_code'] : '';
	}
	return $out;
}

/** A link the site really has: a /go/ slug or an affiliate host (or its subdomain). */
function gbg_core_known_link( string $slug ): bool {
	$s = gbg_core_settings();
	return '' !== $slug && 'consent' !== $slug && ( isset( $s['go_links'][ $slug ] )
		|| gbg_core_host_allowed( $slug, array_merge( (array) $s['affiliate_hosts'], (array) $s['go_hosts'] ) ) );
}

/** Placement of a click: one of the names the page script sets, else 'unknown'. Pure. */
function gbg_core_placement( string $p ): string {
	return in_array( $p, array( 'content', 'button', 'promo', 'ab', 'sticky', 'header', 'footer' ), true ) ? $p : 'unknown';
}

/** Keeps a page URL only when it is on our own host (GA4 page_location from the browser or a Referer); else ''. Pure. */
function gbg_core_own_url( string $url, string $own_host ): string {
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	return '' !== $own_host && $host === strtolower( $own_host ) && in_array( wp_parse_url( $url, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ? $url : '';
}

function gbg_core_mp_send( string $name, array $params ): bool {
	$s      = gbg_core_settings();
	$secret = (string) get_option( 'gbg_core_ga4_secret', '' );
	$ids    = $s['track_enabled'] && $s['ga4_measurement_id'] && $secret ? gbg_core_ga_ids( $_COOKIE, $s['ga4_measurement_id'] ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( ! $ids || is_user_logged_in() ) {
		gbg_core_mp_stat( $name, ! $ids ? 'skip_no_ga_cookie' : 'skip_logged_in' );
		return false;
	}
	gbg_core_mp_stat( $name, 'sent' );
	wp_remote_post(
		'https://www.google-analytics.com/mp/collect?measurement_id=' . rawurlencode( $s['ga4_measurement_id'] ) . '&api_secret=' . rawurlencode( $secret ),
		array( 'body' => wp_json_encode( gbg_core_mp_payload( $ids, $name, $params ) ), 'headers' => array( 'Content-Type' => 'application/json' ), 'timeout' => 2, 'blocking' => false )
	);
	return true;
}

// /go/<slug>/: the click the page described in the gbg_pl cookie (placement|ab_test|text|page), or the referrer.
function gbg_core_go_track( string $target ): void {
	$slug = '';
	if ( preg_match( '#/go/([a-z0-9-]+)#', strtolower( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) ), $m ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$slug = $m[1];
	}
	// Fields are URI-encoded one by one before the join, so a '|' in a link text cannot shift them (code review 22-10).
	$pl = array_map( 'rawurldecode', explode( '|', (string) wp_unslash( $_COOKIE['gbg_pl'] ?? '' ), 4 ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	gbg_core_go_count( $slug, gbg_core_placement( (string) ( $pl[0] ?? '' ) ), (string) ( $_GET['s'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
	gbg_core_mp_send(
		'affiliate_click',
		array(
			'link_slug'     => $slug,
			'link_domain'   => preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( $target, PHP_URL_HOST ) ) ),
			// Cookie and Referer are the visitor's: only known values go to GA4 (code review 22-10).
			'placement'     => gbg_core_placement( (string) ( $pl[0] ?? '' ) ),
			'ab_test'       => preg_match( '/^[a-z0-9]{1,20}:[a-z0-9]{1,5}$/', (string) ( $pl[1] ?? '' ) ) ? (string) $pl[1] : '',
			'link_text'     => gbg_core_short_text( (string) ( $pl[2] ?? '' ), 80 ),
			'page_location' => gbg_core_own_url( (string) ( ( $pl[3] ?? '' ) ?: ( $_SERVER['HTTP_REFERER'] ?? '' ) ), (string) wp_parse_url( home_url(), PHP_URL_HOST ) ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'link_url'      => $target,
		)
	);
}

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'gbg/v1',
			'/ev',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => static function ( WP_REST_Request $request ) {
					// Public on purpose; bounded (code review 22-10): small bodies only, 30 events per IP per 10 min, no write for junk.
					if ( gbg_core_is_bot_request( (string) $request->get_header( 'user_agent' ) ) ) {
						return new WP_REST_Response( null, 204 );
					}
					$raw = (string) $request->get_body();
					$b   = strlen( $raw ) <= 2048 ? json_decode( $raw, true ) : null;
					$n   = is_array( $b ) ? (string) ( $b['n'] ?? '' ) : '';
					if ( ! in_array( $n, array( 'affiliate_click', 'share', 'promo_copy', 'consent' ), true ) ) {
						return new WP_REST_Response( null, 204 );
					}
					$rl = 'gbg_ev_rl_' . substr( hash( 'sha256', gbg_core_client_ip( $_SERVER ) . wp_salt( 'nonce' ) ), 0, 16 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
					$rn = (int) get_transient( $rl );
					if ( $rn >= 30 ) {
						return new WP_REST_Response( null, 429 );
					}
					set_transient( $rl, $rn + 1, 10 * MINUTE_IN_SECONDS );
					$p = gbg_core_ev_params( (array) ( $b['p'] ?? array() ) );
					if ( 'consent' === $n ) { // anonymous count of the banner choice (shown / allow / deny), never sent to GA4
						gbg_core_go_count( 'consent', in_array( $b['p']['status'] ?? '', array( 'shown', 'allow', 'deny', 'custom' ), true ) ? (string) $b['p']['status'] : 'unknown' );
						return new WP_REST_Response( null, 204 );
					}
					if ( 'affiliate_click' === $n ) {
						if ( ! gbg_core_known_link( (string) ( $p['link_slug'] ?? '' ) ) ) {
							return new WP_REST_Response( null, 204 ); // only links the site really has are counted and sent
						}
						gbg_core_go_count( (string) $p['link_slug'], (string) $p['placement'] );
					}
					$p['page_location'] = gbg_core_own_url( (string) ( $b['l'] ?? '' ), (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
					gbg_core_mp_send( $n, $p );
					return new WP_REST_Response( null, 204 );
				},
			)
		);
	}
);

// Tools > GBG Core GA4: the Measurement Protocol API secret, typed in by an administrator (never shown again).
add_action(
	'admin_menu',
	static function () {
		add_management_page(
			'GBG Core GA4',
			'GBG Core GA4',
			'manage_options',
			'gbg-core-ga4',
			static function () {
				if ( isset( $_POST['gbg_ga4_secret'] ) && check_admin_referer( 'gbg_ga4' ) ) {
					update_option( 'gbg_core_ga4_secret', sanitize_text_field( wp_unslash( $_POST['gbg_ga4_secret'] ) ), false ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
					echo '<div class="notice notice-success"><p>Saved.</p></div>';
				}
				$s = gbg_core_settings();
				echo '<div class="wrap"><h1>GBG Core GA4</h1><p>Measurement ID: <code>' . esc_html( $s['ga4_measurement_id'] ?: 'not set' ) . '</code> &middot; tracking '
					. ( $s['track_enabled'] ? 'on' : 'off' ) . ' &middot; API secret ' . ( get_option( 'gbg_core_ga4_secret' ) ? 'saved' : 'not set' ) . '</p>'
					. '<form method="post">' . wp_nonce_field( 'gbg_ga4', '_wpnonce', true, false )
					. '<p><label>Measurement Protocol API secret<br><input type="password" name="gbg_ga4_secret" class="regular-text" autocomplete="off"></label></p>'
					. '<p><button class="button button-primary">Save</button></p></form></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
		);
	}
);

add_action(
	'wp_head',
	static function () {
		if ( ! gbg_core_settings()['track_enabled'] || is_admin() || is_user_logged_in() ) {
			return; // editors and Claude's checks never count
		}
		echo gbg_core_track_script(); // phpcs:ignore WordPress.Security.EscapeOutput
	},
	1
);

/* -------------------------------------------------------------------------
 * Landing check (0.3.28): before a generated deep link goes on the site, the server checks that the partner page exists
 * (affiliate_links.py cannot reach partner sites itself). Settings logins only, only hosts on go_hosts + the merchant
 * landing hosts below, max 20 URLs per call, one at a time.
 * ---------------------------------------------------------------------- */
add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'gbg/v1',
			'/check-urls',
			array(
				'methods'             => 'POST',
				'permission_callback' => 'gbg_core_can_manage',
				'callback'            => static function ( WP_REST_Request $request ) {
					$hosts = array_merge( (array) gbg_core_settings()['go_hosts'], array( 'discovercars.com', 'yesim.app', 'ye-sim.app', 'yesim.tech', 'airalo.com', 'airhelp.com', 'klook.com', 'getyourguide.com' ) );
					$out   = array();
					$stop  = microtime( true ) + 30; // never hold a PHP worker longer than ~30 s (shared server)
					foreach ( array_slice( (array) ( $request->get_json_params()['urls'] ?? array() ), 0, 20 ) as $url ) {
						if ( microtime( true ) > $stop ) {
							$out[] = array( 'url' => (string) $url, 'status' => 'skipped: time budget' );
							continue;
						}
						$url  = (string) $url;
						$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
						if ( ! str_starts_with( $url, 'https://' ) || ! gbg_core_host_allowed( preg_replace( '/^www\./', '', $host ), $hosts ) ) {
							$out[] = array( 'url' => $url, 'status' => 'host not allowed' );
							continue;
						}
						// wp_safe_remote_get refuses private/internal addresses on every hop (no SSRF via a redirect).
						$left  = max( 1, (int) floor( $stop - microtime( true ) ) );
						$r     = wp_safe_remote_get( $url, array( 'timeout' => min( 5, $left ), 'redirection' => 5, 'limit_response_size' => 200000, 'user-agent' => 'Mozilla/5.0 (compatible; GoBackpackGo link check)' ) );
						$body  = is_wp_error( $r ) ? '' : (string) wp_remote_retrieve_body( $r );
						$title = preg_match( '#<title[^>]*>(.*?)</title>#is', $body, $m ) ? trim( wp_strip_all_tags( $m[1] ) ) : '';
						$out[] = array( 'url' => $url, 'status' => is_wp_error( $r ) ? $r->get_error_message() : wp_remote_retrieve_response_code( $r ), 'title' => substr( $title, 0, 120 ) );
						usleep( 400000 );
					}
					return array( 'results' => $out );
				},
			)
		);
	}
);

/* -------------------------------------------------------------------------
 * Anonymous click counter (0.3.32, Paul 22-10): every affiliate click per day, link and placement, also from visitors
 * who decline cookies. No cookies, no IP, no page, no user: only "2026-10-22 / rentalcars / button: 3". Bots (by user
 * agent) are left out. Kept 120 days. GET /gbg/v1/go-stats?days=30 for the dashboard.
 * ---------------------------------------------------------------------- */

function gbg_core_is_bot( string $ua ): bool {
	return '' === trim( $ua ) || (bool) preg_match( '/bot|crawl|spider|slurp|preview|fetch|curl|wget|python|java\/|go-http|headless|phantom|lighthouse|monitor|scanner|facebookexternalhit|embedly|whatsapp/i', $ua );
}

// Beacons from crawlers that run JavaScript (Baiduspider-render, meta-webindexer) and from the GTranslate proxy, which
// replays the visit from its own servers, are not readers: they must not count in A/B tests or events.
function gbg_core_is_bot_request( string $ua ): bool {
	return gbg_core_is_bot( $ua ) || false !== stripos( $ua, 'GTranslate' ) || false !== stripos( $ua, 'meta-web' );
}

// Light site icons (made from the logo SVG with scripts at 32, 180 and 192 px; favicon.ico holds 16/32/48). Written once
// to uploads/gbg-icons/ so they are plain static files Cloudflare caches.
function gbg_core_icons(): array {
	return array(
		'favicon.ico'  => 'AAABAAMAEBAAAAAAIADVAgAANgAAACAgAAAAACAA8AYAAAsDAAAwMAAAAAAgAA0MAAD7CQAAiVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAACnElEQVR4nH1SS0hUYRT+zr3jnRnfjaNTaWpiaU9c2KKFUCtxFQljqNTCcNGqICKixb03EKKgIAoKWqho0TWkhMCgcNokhobmY3REKgMl89XovOe/p4VpvvJb/Qf+c77HOYT/gJlp5aURoKHde7PCkZoewXJ2/+ni2llmEBFWP22EqkLSdZh/S2r+fOlqxDLTEI/HLDY5vSfDWlTXVxT1QQMsW5lBRDC7J1679hUciOdSyVxELAkkhpWQX0T35DhPclAU6qSPGmzI0vpmw3DLROD2wRuVI4Hnvs7BW96WL/VNe5OPhThqDThTczC/8GvBFDQAAMPa8D8LzEyaRnThcm/Ku4m7Y1HLnIvDiWG7kiJIMqUs2+GQmTblmJ8K3akrbbxusFuuojaxpkDznJJ1HWb3ZOMVsodc0RAjw54zHokFg0n2VPtSfDq4PEf3Sgsv3lZVVXLDMAGAVkJTJV3XzQ/fmvd7Zzt8QTFnscHh32XP/TEVGDiSluwU2cqJMxVF196AQSDwKrEEAJoGgEExsZiZkujoUSg1JsuKbSH0vYBYErI1Jv8MjB5XVUhdHlVen9s2a5TRP9Ny0DvT82A+NloeCbJwpRV2BsK/yw5lVuSX5dUu8sqqeE3B+iCf9JYklGRV+2QoX/NcR5GRmDdQvluvybDln4+b0RAAXm0GNt0BEbHKEAYb8kLfy/xITHCKtbjV6XT6AXRsVbtJAQBoYD5HNUIIzgv6BSUI63tmJmPIrWw3YAMMwy0DQNfYw5KnI5WRRz2V48wsg7fLapMFZiYCmUOTnY6Ps03PlHRWkszcBiISBrvlKrSJHdlVViVmpsb++let02f58afqFwQJKqtbbG6bgQ6dAVCClHxf8jvfOpNyPQyT4PHsOOAPe5Yvizxm2uAAAAAASUVORK5CYIKJUE5HDQoaCgAAAA1JSERSAAAAIAAAACAIBgAAAHN6evQAAAa3SURBVHicrZZtbJPXFcf/597ncWwncRwS5w0C4aVtSMvKKCAqWo1MVB2q2jINm0lo3acJTZO6SVPFNm04liahaRpTtWlMFVs/VIBqq6Frp0qgdjHSeOkYVMCcsIzXvJAYx44T27H9PM+9Zx9GJNhI4jDO93v+v3vO/55zgccY4TAEAIxOjzZWeoYeJwBAuJ45v/yL1NG4T658u8YdSGRnhlqaqut6n2vbWwQAIvBjBWBmIiI+e/NYx+3839/LlcafKsmJgMeoA0GgptaDJzyvv7ShZeenzGFBFNH3nxePIsgcFsxMABCP90gAaKhtcSaLw1sLPBZwSlBTuYzO5lJq+M4t+0LyyLHjif29QI/J/OClFwUQ5rAgIiaKaCJiMKi7O+IAQFfTjhGX9CQEmQyAJJmCmWXAs0a2+job3UbNCQBWLBZ9QLNigDCHRYQiOplPttwpXexiZj/I5DvTg11RhrRV0SOFWcusiUBQbKHG1Qi/ZynnrLuOy6huJSIGYg/kNSoRj0aDMkQRdWYo+rVTIz8/Ol1I11UZvnRs4M3+C2PvPxms5S4iyr578TtJo0qssJXQWmux1LcOo9P/ADx5o8lcPwoAiUDXAy1Y0IRhDgv0RPDWW/nmj27tu5QqXQ1YRWbDFGS4COwYaKtZe7nZ1/mO31ytL6WP/mJkMlHb4u1CratJDRXPiCZz/YVvPXto08/CtohEsDgTPo1+ikSE/mzo4C/zNBRQJWmb0kWsJOuSi+tdHZnx4tUv/St38reXx3v3tbmfG13X9EqyubpzZjR3meu9S2nVks0HNGxs2xb+H715AaLRqAxRTF1Ofbwx41zbMz1Z0CSEyQyWpiavq36k3b/hpCChJ9NZq6DvrphyhjrrzBXOWL6/SN4Zw6vaL25ZtufDMIfFrGErBkgEE8zM1D/2l4MFlYQUJgMMQLNhGlizZOv525nzLxTtnPB7lpltng08kunngWJ0qTLyDe3ezal2/7ofEpF+Otb/0HbPacIoR2WIQurro+tfLhvJF0vTtpLCJQGwMFmYjv+upUuFGTG+DLahZzgjiMDNtU/QEk/7gaaGVSfWuV9NkI8m7g0rtSiARE+IDeHCYPrM/pxKsRQmAAYYLE2i5dUbz03kr28t2zMQwkWWmlETzoD0ovVv2zt+8JPZPLOTci6dh7aAOSojEej4zT+8lsPw86WCbQNCAMSQSni4NV3nbilNqdurVJkY0EQQbNEUvIYvGoxCfjL4dtVC4nMChGIxAIRcefy7Nf4qcntdLo0yMSsHQqmAe+XE0NQXm8uqAEESAEBEsMoOT5VTOz7YbajPj2TshcSBeeYAM9O1zPllU/aNl0dz/a9NF8e6bWOqxnKKUCUDii04jg0iAjPDMA3YThkNdW14xvf6Vze27+qb9dF8AHN64B79MIDDgDjMrJb/dfSd7bZjbb9hf75rqjRtSnKBWbMwNBqqVo+wSyNP19puZM+9CYi+WCw2V/qFAWarEI/3yHg8ooloCMAfh7JXrozNXAlNFEoakgRBqPr6eqNJrumpgte6Rcn3yqWJnQPjJ9atbXnpysNWcMUA96rgAEAf9xmIx3Eze95sru7iarSIZGFQ59WYgbwvu3HlG71+vz8/OXDn+0Jy75Lm1pvMTIT5fVDRMgKAFFIc6o6oE4MHG4at0wZZPtVRvwlUZdmlnPPr+vr6bDAK2ftN1yalLQA9FeWteB0H4gkCACnM1cIQmJi5ri+nPpJ306PlV9f+9PcAEA0yK20hGg3KSvNWXAEgDgDIloYDSpThkh5tugkk+J8AJsAgItIAKBSKzev8+6PiCvwudYrBICZ60lEOGMxVVW5Umw3HiUj3xb8ye+sF3/4jAcSC0CDJjiovV46G1o5pOD4sr9tyEgBSqe8tSnhRAGH+zx5nduqZ7FVW2WbTZUihPde+3LrjEgAKhUJzPrX/GwDxuAAR/+nq/kM5DDeyIstb40aV9H5MRFa4LyyxyNJXDNDXFzYi3aecTwZ/9aM0Bnbnc0VHGDBMe0mpo/b5w2BQzzY80u0XBIhGg7K7O+Kcu/XBK2l15cBkNqMIBJ+vTja4On+8pSPUH40F5510C8WcyyjMYRFBhC+M/bkzkf7wVKZ4s4EdU7trheHTa06/sf7QC8fe3ykX8+QeFnNXoAcQwuBb2bPvUt1kwLEhIB1yozH5lH/btxXblAh2PVLf74951jEIYJwdPvJMsti/Nz1ze7fhKzYaubbje579za5YLLSogbNogP+OG5nTKwYm+/ZZduEbjWbHphdX7x2q5MezUPwbdXdHIRy3xAwAAAAASUVORK5CYIKJUE5HDQoaCgAAAA1JSERSAAAAMAAAADAIBgAAAFcC+YcAAAvUSURBVHiczVl9bFvXdf+de9/jN0VREiXbkiNHdmTLihPDURwnTh1pDVJs7ZwWNbkCw7o1WOasHToEawsEAUZx64xs2IotLbYFQxEEc+uFnLOkRRsnrS1lrRd/KbXlyJblOJatb0sWSZEUv969Z39QTiNH8SRKTvf7j8R7953f+T7nEn5TYBAIfONnV1eXAXQD7cBk7ByHQjH1G5PtVohGg/KFU/eZAHDq2uurj0+89AIzu8s9j1ZOtKXBFA68cv7ZV5V/6HFHsuXHzVUP7R9N9ddpe/IO5J1nxhvfPNDU86hoa9tbvNU5nxiBcDgsIpGIPnz5n5+w5MzuRHKqPmkNtiXS16xKb8DwOKtQVFkIVxbOzMYze1r3bV3MucZtlvsDnGuNEADMZMefmHH07kzkMrDyzDbDbaQzSZ1MT7MgqZHQCLin1vaMR/dKQScmVHbgM6u/nEFJ2XzzueKTIvDVQJgAwGl6Xs/PslIFFISQxKxBJIUh7FKSYQo2zGvpC1U91w7867g6806V5fgiAHR1heVC564YAWamKEclMwtmJsy5J5f+o8n2Vi4RqDot2S4ZfJNADA0FIUyscm9RpnBZhaQZa2sIRsMcFh0dEWuh766ICzGHBRFpAPNSX7grbBCFLADo6ipZwGPz+aU0wczzIpBAsLSF9dUPw2OrEXa7TTV6H3qRiHLRaHBB7a8IgTCHBVFEc5wrR+0DW1zana5014+a0jER6YhYg9PnPlssJi4kh85eAQCryAaznufLJeHzqHY2wmVWomc0CofNKeO+8ZeZ+U4ius7MREQfiYFlEWBmQUR6KNn3wBvxv305nhtstPKA3fSkfjKw76JTVhy5XPjpN7xq3TNtbXufA0B2h2tcZuzE0PhwXBIJNPl3YDjVC4bSLo9TOozKQwCmo9GgJKIFC1vZBEqaJ2bmmp+8t+9Hg+lf1M5mchaBDCHIO+lwbDNNcxulhN7kq9x2YertBzbWfOr4fau+cOgHvV8fdzhHVll5KEFS5q0MGnz3oqhzGEudY9MwyY362e0Nv/fs3Df44zJ+2UHcilZiZvz34Pf/ZTR/sjafsSybdBqGsEPAxoWspdOprJVIJnA+fih4evLAse6h598aTB/f077uq99c47s7UURKFqws2w0P13u34OL0L0Ag5fP5RZWx4SWfo/ZilINyLr4WRFmFjDkqiUKqf/Ktx8+mXn51cPS8ZQq7wfPSNEGzYr+3jmZnUzpTiJPH66Y6XxP82HSs0ffA1FDmxM7+iSP+Nd67AZa4MHWYDWnwHf5txV1r/vSeQMX6iwDTrQgs2QLMTJ3oY2Z2Xbr+P38/en1AG8ImbhZe6SL7PNW0Y9UTLwbcG5KmzaRCltWl4V717vSrO9689NznTGHH7278qzfqPfdcGp3tgYBhVVfViVpn6w9qfesHYoiKWwkPlBEDMcREhCLqt4da/iAuzm8o5rWSwpAfLpIEaNNB5OCa3lk1c36Wxv6IFRQRpNteBa+s0z73apEtpvy9Y68/MpTq4WwhY2lRgJsbsvc3funbzF8hIPiRrLMsAmEOiyBCmpnrDvZ/a9+15JCWZBfzhSdYqoA6Tz1trdx96L3rR7+SKUyTJIMUK7TUPIaizojRVB8G4+M6p1IOu8MGt9uH+rot8GSa/9FJzstRDsrQx2Sesgm0opWIoI9dPfCtFK5UsZIWEc87gxlsc0pC1nPRGfBl0/JKSzaT16bhEErnUVBpjKbOYSJ9ATXetaLaaITLrLoY8Kx/t1Y2H97YtOvfwjwsgojc0nV+rbBFgjksgAgDXHfw/DMDg4njHgk7AJ5XT5UuqkBNrVzneHTfxEz/rindtzOfUUxEoqjzqHM3Y3PgMZXkQeER9W8113Q8XWmr7Sei3GJl+TAWHcSxkvb52PD+p9PispdYKnxUASxNEiLrG6+v2FLIimsPz6ZzDCLBYEhhIp4fwfnJn9PEbB9lCzPP++11p6mTCtFoUHZ1dRnMS8uMi3IhZhYAaWZufKX/ma9NTU+wQQ7JN3W3mi1d6a2Qd7p3Hnx/6u32rL4OKQz+oNqCoFVBTxX7RUNx+8AjzU++HuVKGURQl7JNbCmyA1ikBTq7OwUR+OjV/X+WFkNugrmA9olJaulW66YbK+9PjOVOP5KeSWuCmNeIMUF73X5U2FedIqIcYjEs1OOsGAHmsIh0RKxsNrH+eu7CN6biw9CswNAWQExzPBhau1wuNPkffHvg+pGdGZ4gSXbcbCVikqnUDCfzw4+lUqnaYDCmw+Fw2R3B//liZ2epeCVyg3p1xeYfbqp5bOqOuk2Gy+swNBWoqHJKs1bERNlMgUdmeh+4Ej/5UDaTL3VoC0ArqJw5VnNq8od/TgRu7Wwte7Rd8ovMXHc5eWLXUPLM5xO54c/M0nh1Kj+BbHYWBANaKzDTAsMfwMwsJZHWrCE0NVZsj3+h5W/uIlCcwWW50qLrADNTDCFBRBMoRVuMmVcNpk91XJk++dmkbeJhu/AZA9M/X2MpC0Q0L71qtuC0+ehO34PT78d/WZXJJ9SsHK46MRT7fdyB73Z3dRoAFpy6VoTAnHZUaVyMiVgp+MYBHABwgJldR6++9LLTZa9PzVgKwK+Dl5mlSahyNI5/et1f7E7kR35W0Gd908kxfaVw4uvM/CKBMh83tNwKSw4eImKikAqFYoqZ6dSpF8woByUA43r2alsqk0RR50TpWQGAwNCqwltJ1a6m12w2OtVQcc8Lfl8NpbMZ5ajJbzg7fuh3QODu7s6PHR1XjMDNZNra9hb7sJkBpGvddx1r3/AnuLPyfg0AeSsFZs1MFjmsQH5z4NF/CnNYbK3a8x2/aJndULsdFbnN3zZU1WFmUHt755LXicueiZlBRBHdyZ3ycvzYZjWTQoN7O9VX3ItEbgjvx49rd5VH1lDzf9RXbOrv4rDh9XqvnRl9Y49dOmc21e06+iGVLPn7y97M3fBbZvbsP/O198ZzZ+u0JbjSvoqaax6By+bPz1gjo7VG85571uz+1VzvxDd8PcpBGURUl1vMVmAv1EkAkMqn1hqGWU3agMNwU7o4heMj+61zM6+ZTq5989763e/EEBREpImIb+yQQhRTy6nEy3ahGEpFaCTVUwejYDCzZtZCkgkSAlJIYXFhAAwKYPMHFg9RaEXW5yu2mUvmht0ZaxKCJN9oHxQXpa1Yo7et/vwhELgdnYvq8ZeCZRMIoK+0QiSx1TAMACV3YLByOG1ELE967dX9pWC/9XxbDpZNoLu7GwCQycfvKuo8SgWYoNlinyeA1d7WnxKR7sbCy9nlYvku1P6WBpgK1uwayyoAcxQ0K2FTftRXbD4CAJOx1rID9VZYFgFmJnSGYRNuZtLrC4UCBIgYrG02U9i0b7jJv6MHAILB4Iq7D7BMAt3olpFIRL8z+trTcXWxqVhQikECYO1yedhuul4houzcdu3/lwWYo7KDOqyL13sefDfx2t9NJC9pCZsAAEsVhV14yetoeKX0dHBlpF0AZREo3QeEFDOvOTv5n9GJ9AVDaDtATMxauT1u4dL1pz+19ssnwmGIIN0e9wHKXC3GYueImW1HLn/v4HjhdINVgAJBAMSaCqh2NFlba/f8MRFlW1uDRLg97gOUQaAbnTIUiqlfXv33742pkzsyqbwlScq51KmqK2tltdnSuSFwX0+4K2zc7gvrJRGIRoOygyLWyaH/2juYO/zk+OSQZQibwWAwtHK4DMPPm85+uump56LRoOwsoz1eKhbdC0WjQRkKxdTRwQOfey9z+PnJxIglyT631CUGWeQz1+XvqvmtPyQiNXdvdttc5wYWbYG+vhgTBFLFkWfhjtu0UobSRU0grdnSlRU1osZo+evW2vZflS73FrfbXC6WsBsFAYzBxOnGsUzvU4PJ408WjOmq5Mw0FBf0WvdDA6HWf2jZE90to8Hy+/ulouyBZjIzueb81MEvjc32783xVHPRKlgB+937Ht/4l+EwQ0QIn4gFljwPhMNh0d4OEXAHRgF8h5m/f2Hq8FPjVu83c2nLBwCtiBIQWnFhF8L/Amax1Sc4jZzPAAAAAElFTkSuQmCC',
		'icon-32.png'  => 'iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAEaElEQVR42r2XX0xbVRzHf+fe0ltaLm1pe7u2FEoFxAEFjAyDU7cJMfsTNVGn0wdj1CwxWXR7ddmjMTFmD/rgTFwgGo2Z+oBzS3QzYxIgCLICEyqUtRRaSttLL7S9t7339vrgYEAZK2vhPN18z+93fp/8zu+c87sAOzAkSTJla4vyHdwd660fm702VETox+So0EvghNhceuIEQojfzF6Wr8Aj0cv2O/NDt/rcncpQ3IMXYEQThhU0pcQ4CJh0EgC+2MwPyxdAidrAhBNuMhT34AAAfDoJSSEGkiSBK3j986uTn9A7ClCKWiKk3JCR5mrdASjT7gMdYe3ZEQD3Yt/p1cUw+bo5UmECFaEDjqcBYTJbzjXQ6/vm02UudIblGUwl10hJMRHwRp1GADgPAEBgSh4AClbsG6kX4A7TD+H4FJiK60ZzOgU+6ZZlwtMzNRb4RbFW1xXaoULXElfgSjdRoJwd9P10hEn6oVz7BJByE4wFu6DWeJhpt3+oRQhJD50Bj+evaxuDAwDoi+3pwdnvVTIkd9h1T9U1WV4KiqJEcQKD/g78CNrCUtAWmi9uFjzrGpiI/HF8gr5Zs1E3kXVpUqZhAADSSIQo58fomM8oSGw6yE4KYjoJldrW5Wbza2fut3ZWGfAuDn0dSy5k6GUax4I/5qIAAPao9oJV7YChuUuQEhO4kawFx54XJVJBndtq7QcCDAZ+/qzf21m0UTcoKyUhzbL+qBMDAPAvj4JRVQU1hjYgCcM/j+hbz+sV5b8jhLw5ATBc4CSf5jJ0U3G1bylJl4uSsKotJNygkCmY9soParOtrS1rYGThyq8z0WHVRt2qbgCFjBQmw93r/OeWnFCsME9t52hvXYQS9uwyO58hawrLuCUuaN3MhRPiVdsB2PIe4MTIaXd4+GwKccn5xduUK3IDlyANJGGERGoRRCmV4aNTVsCj1NMft5jf/Civz7E3OmJnkr4OOuFtTAhR0hXqXje/l3o+yQsJfJL+U9ZgPuY/ZDtlyX0L1oxyjWPaYTz6TKm64coSm3kklbi2X0WUjCGEIMhMm4fnL72bzbrb7gdwlP6yynDgOFVUg0aDXZCWRLCV7BMqbNaXLfAcI4gCg2HI1Wh85dsdAaDZhfZu7wWkI8rgSetbIAIvsnxsvBS1Re6a3D01pyCvW7AyRCQ6QJKATvqg13cR5mMu/JDN0fKwz/m2AZbZOS1C99zUBBVHqJXdNQAR8HXVzYuJkVwamm0D4BKuWflWyfWgU1o7dw1gOtZvjPMh9WovoLSlms1vXNg1gJmQ8zf3Yt/q5UXIVOO59pRZAwzM/fDVv/RNx71WzAZqpfHcrgA4w5ffHw9dfy+eCv/vhGRgLqrv2295pytXgAe+BbcjPbUDMx3OKOfDV7Sqkv3xo9Vnyfv1eXnNwAzde2Nt8CI5BWZt1av5CJ4VQB11rPUxfduEsqAEAAAsZJ3ncer1q/n6o8r6OR4IfFcbic91sCm6XqutrjloetuTD4D/AKjtsgHjlXZYAAAAAElFTkSuQmCC',
		'icon-180.png' => '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',
		'icon-192.png' => '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',
	);
}

function gbg_core_icon_file( string $name ): string {
	$up   = wp_get_upload_dir();
	$file = $up['basedir'] . '/gbg-icons/' . $name;
	if ( ! is_file( $file ) && isset( gbg_core_icons()[ $name ] ) ) {
		wp_mkdir_p( dirname( $file ) );
		file_put_contents( $file, base64_decode( gbg_core_icons()[ $name ] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	return is_file( $file ) ? $file : '';
}

// Size asked for -> which light icon serves it.
function gbg_core_icon_for_size( int $size ): string {
	return $size <= 32 ? 'icon-32.png' : ( $size <= 180 ? 'icon-180.png' : 'icon-192.png' );
}

add_filter(
	'get_site_icon_url',
	static function ( $url, $size ) {
		if ( ! gbg_core_settings()['light_icons'] || '' === (string) $url ) {
			return $url;
		}
		$name = gbg_core_icon_for_size( (int) $size );
		return gbg_core_icon_file( $name ) ? wp_get_upload_dir()['baseurl'] . '/gbg-icons/' . $name : $url;
	},
	10,
	2
);

/* -------------------------------------------------------------------------
 * robots.txt: Baiduspider only on the English and Chinese pages. A crawler that matches its own group ignores the
 * '*' group, so the rules every crawler gets are repeated here.
 * ---------------------------------------------------------------------- */

// GTranslate language folders Baidu does not need (all languages except English and Chinese).
const GBG_CORE_BAIDU_SKIP = array( 'af', 'sq', 'ar', 'hy', 'az', 'eu', 'be', 'bn', 'bg', 'co', 'hr', 'cs', 'da', 'nl', 'eo', 'et', 'tl', 'fi', 'fr', 'gl', 'ka', 'de', 'el', 'gu', 'haw', 'iw', 'hi', 'hu', 'id', 'it', 'ja', 'jw', 'kn', 'kk', 'ko', 'ku', 'ky', 'lo', 'la', 'lv', 'lt', 'lb', 'mk', 'mg', 'ms', 'ml', 'mr', 'my', 'ne', 'no', 'fa', 'pl', 'pt', 'pa', 'ro', 'ru', 'sr', 'sk', 'sl', 'es', 'sv', 'ta', 'te', 'th', 'tr', 'uk', 'vi' );

/** robots.txt group for Baiduspider, between markers so it can be taken out again. Pure. */
function gbg_core_baidu_robots(): string {
	$lines = array( '# GBG CORE BAIDU START (Baidu only on English and Chinese pages)', 'User-agent: Baiduspider', 'Disallow: /cdn-cgi/rum' );
	foreach ( GBG_CORE_BAIDU_SKIP as $lang ) {
		$lines[] = 'Disallow: /' . $lang . '/';
	}
	$lines[] = '# GBG CORE BAIDU END';
	return implode( "\n", $lines ) . "\n";
}

/** robots.txt text with the Baidu group added ($on) or removed; the rest stays as it was. Pure. */
function gbg_core_robots_baidu( string $text, bool $on ): string {
	$text = rtrim( (string) preg_replace( '/\n*# GBG CORE BAIDU START.*?# GBG CORE BAIDU END\n?/s', "\n", $text ) ) . "\n";
	return $on ? $text . "\n" . gbg_core_baidu_robots() : $text;
}

// WordPress builds robots.txt only when there is no file; on these sites a physical robots.txt exists (Yoast file
// editor), so the setting also rewrites that file. Called after every settings save.
function gbg_core_sync_robots_file(): string {
	$file = ABSPATH . 'robots.txt';
	if ( ! is_file( $file ) || ! is_writable( $file ) ) {
		return is_file( $file ) ? 'not writable' : 'virtual';
	}
	$old = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$new = gbg_core_robots_baidu( $old, (bool) gbg_core_settings()['baidu_limit'] );
	if ( $new === $old ) {
		return 'unchanged';
	}
	return false === file_put_contents( $file, $new ) ? 'write failed' : 'updated'; // phpcs:ignore WordPress.WP.AlternativeFunctions
}

add_filter(
	'robots_txt',
	static function ( $output ) {
		return gbg_core_settings()['baidu_limit'] ? gbg_core_robots_baidu( (string) $output, true ) : $output;
	},
	PHP_INT_MAX // Yoast rebuilds the whole text at 99999; add after it
);

// /favicon.ico: WordPress answers every request with a 302 to the site icon (5k a day, each a PHP run). Serve a small
// .ico with a long cache lifetime, so Cloudflare keeps it at the edge.
add_action(
	'do_faviconico',
	static function () {
		$file = gbg_core_settings()['light_icons'] ? gbg_core_icon_file( 'favicon.ico' ) : '';
		if ( '' === $file ) {
			return;
		}
		header( 'Content-Type: image/x-icon' );
		header( 'Cache-Control: public, max-age=604800' );
		header( 'Content-Length: ' . filesize( $file ) );
		readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	},
	5
);

/** Adds one click to the counts array ({day: {slug|placement: n}}), dropping days older than $keep_days. Pure. */
function gbg_core_go_count_add( array $counts, string $day, string $slug, string $placement, int $keep_days = 120, string $page = '' ): array {
	$slug      = preg_replace( '/[^a-z0-9.-]/', '', strtolower( $slug ) );
	$placement = preg_replace( '/[^a-z]/', '', strtolower( $placement ) );
	if ( '' === $slug ) {
		return $counts;
	}
	$key                    = substr( $slug, 0, 60 ) . '|' . ( substr( $placement, 0, 20 ) ?: 'unknown' ) . ( preg_match( '/^p\d{1,9}$/', $page ) ? '|' . $page : '' );
	$counts[ $day ][ $key ] = ( $counts[ $day ][ $key ] ?? 0 ) + 1;
	$cut                    = gmdate( 'Y-m-d', strtotime( $day . ' 12:00:00 UTC' ) - $keep_days * 86400 );
	foreach ( array_keys( $counts ) as $d ) {
		if ( $d < $cut ) {
			unset( $counts[ $d ] );
		}
	}
	return $counts;
}

function gbg_core_clicks_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'gbg_clicks';
}

// Own table with one row per day and key (code review 22-10): an atomic +1 instead of rewriting one growing option.
function gbg_core_clicks_install(): void {
	if ( '1' === get_option( 'gbg_core_clicks_db' ) ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table = gbg_core_clicks_table();
	dbDelta(
		"CREATE TABLE $table (
		day date NOT NULL,
		k varchar(120) NOT NULL,
		n int(10) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (day,k)
		) {$wpdb->get_charset_collate()};"
	);
	update_option( 'gbg_core_clicks_db', '1', false );
}

/** The counter key: link|placement[|page]; '' when the link is empty. Pure. */
function gbg_core_go_count_key( string $slug, string $placement, string $page = '' ): string {
	$k = array_keys( gbg_core_go_count_add( array(), '2000-01-01', $slug, $placement, 120, $page )['2000-01-01'] ?? array() );
	return $k[0] ?? '';
}

function gbg_core_go_count( string $slug, string $placement, string $page = '' ): void {
	if ( is_user_logged_in() || gbg_core_is_bot( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return;
	}
	$key = gbg_core_go_count_key( $slug, $placement, $page );
	if ( '' === $key ) {
		return;
	}
	global $wpdb;
	gbg_core_clicks_install();
	$table = gbg_core_clicks_table();
	$wpdb->query( $wpdb->prepare( "INSERT INTO $table (day, k, n) VALUES (%s, %s, 1) ON DUPLICATE KEY UPDATE n = n + 1", gmdate( 'Y-m-d' ), $key ) ); // phpcs:ignore WordPress.DB
	if ( 1 === wp_rand( 1, 500 ) ) { // now and then: drop days older than 120
		$wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE day < %s", gmdate( 'Y-m-d', time() - 120 * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB
	}
}

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'gbg/v1',
			'/go-stats',
			array(
				'methods'             => 'GET',
				'permission_callback' => 'gbg_core_can_manage',
				'callback'            => static function ( WP_REST_Request $request ) {
					$days   = max( 1, min( 120, (int) ( $request->get_param( 'days' ) ?: 30 ) ) );
					$cut    = gmdate( 'Y-m-d', time() - $days * 86400 );
					$counts = array_filter( (array) get_option( 'gbg_core_go_counts', array() ), static fn( $d ) => $d > $cut, ARRAY_FILTER_USE_KEY ); // before 0.3.36
					global $wpdb;
					if ( '1' === get_option( 'gbg_core_clicks_db' ) ) {
						$table = gbg_core_clicks_table();
						foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT day, k, n FROM $table WHERE day > %s", $cut ), ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB
							$counts[ $r['day'] ][ $r['k'] ] = ( $counts[ $r['day'] ][ $r['k'] ] ?? 0 ) + (int) $r['n'];
						}
					}
					ksort( $counts );
					return array( 'days' => $days, 'counts' => $counts );
				},
			)
		);
	}
);

/* -------------------------------------------------------------------------
 * eSIM pages 2.0 (0.3.46, Paul 22-10). Data from the weekly Yesim check (scripts/yesim_check.py) instead of text typed
 * into ~800 pages: the Monday run sends from-prices per page and the compatible-device list with POST /gbg/v1/esim-data,
 * and the shared Impreza block shows them through [gbg_esim_price], [gbg_esim_devices] and [gbg_esim_checked].
 * Shared blocks (us_page_block) are not in the WordPress REST API, so /gbg/v1/page-block reads and writes one, keeping
 * the last 10 versions as post meta (restore with /page-block/restore). esim_faq_fix replaces the Afghan city list that
 * the page generator put into the coverage FAQ of every country page.
 * ---------------------------------------------------------------------- */

const GBG_CORE_ESIM_OPTION    = 'gbg_core_esim_data';
const GBG_CORE_BLOCK_TYPES    = array( 'us_page_block', 'wp_block' );
const GBG_CORE_BLOCK_BACKUPS  = 10;
const GBG_CORE_ESIM_FAQ_WRONG = 'in urban areas like Kabul, Kandahar, Herat, Mazar-i-Sharif, Jalalabad, Kunduz, Ghazni, as well as popular tourist destinations';

function gbg_core_esim_data(): array {
	$d = get_option( GBG_CORE_ESIM_OPTION, array() );
	return is_array( $d ) ? $d : array();
}

function gbg_core_esim_sanitize( array $in ): array {
	$out = array(
		'checked' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $in['checked'] ?? '' ) ) ? (string) $in['checked'] : '',
		'source'  => esc_url_raw( (string) ( $in['source'] ?? '' ) ),
		'pages'   => array(),
		'devices' => array(),
	);
	$money = static fn( $v ) => is_numeric( $v ) && $v > 0 && $v < 1000 ? round( (float) $v, 2 ) : null;
	foreach ( array_slice( (array) ( $in['pages'] ?? array() ), 0, 3000, true ) as $slug => $p ) {
		$p = (array) $p;
		if ( sanitize_title( (string) $slug ) !== (string) $slug || '' === (string) $slug ) {
			continue;
		}
		$row = array(
			'country' => substr( sanitize_text_field( (string) ( $p['country'] ?? '' ) ), 0, 60 ),
			'day'     => $money( $p['day'] ?? null ),
			'gb'      => $money( $p['gb'] ?? null ),
		);
		if ( $row['day'] || $row['gb'] ) {
			$out['pages'][ (string) $slug ] = $row;
		}
	}
	foreach ( array_slice( (array) ( $in['devices'] ?? array() ), 0, 40 ) as $g ) {
		$g      = (array) $g;
		$name   = substr( sanitize_text_field( (string) ( $g['group'] ?? '' ) ), 0, 60 );
		$models = array_values( array_filter( array_map( static fn( $m ) => substr( sanitize_text_field( (string) $m ), 0, 80 ), array_slice( (array) ( $g['models'] ?? array() ), 0, 400 ) ) ) );
		if ( '' !== $name && $models ) {
			$out['devices'][] = array( 'group' => $name, 'models' => $models );
		}
	}
	return $out;
}

/** "22 October 2026" for a Y-m-d date (English: the pages are English; GTranslate translates the rest). */
function gbg_core_esim_date( string $ymd ): string {
	$t = strtotime( $ymd . ' 12:00:00 UTC' );
	return $t ? gmdate( 'j F Y', $t ) : '';
}

/** Small stylesheet for the eSIM blocks, printed once with the first eSIM shortcode on the page (0.3.47). */
function gbg_core_esim_css(): string {
	static $done = false;
	if ( $done ) {
		return '';
	}
	$done = true;
	return '<style id="gbg-esim-css">.gbg-esim-price{background:#f3f8ea;border-left:4px solid #8cc63f;border-radius:6px;padding:.8em 1em;font-size:1.1em}'
		. '.gbg-esim-checked{display:block;font-size:.8em;opacity:.75}'
		. '.gbg-esim-steps{list-style:none;counter-reset:gbgs;padding:0;margin:1em 0;display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(190px,1fr))}'
		. '.gbg-esim-steps li{counter-increment:gbgs;position:relative;margin:0;padding:14px 14px 14px 58px;border-radius:10px;background:rgba(140,198,63,.12)}'
		. '.gbg-esim-steps li:before{content:counter(gbgs);position:absolute;left:14px;top:14px;width:32px;height:32px;line-height:32px;border-radius:50%;background:#8cc63f;color:#fff;font-weight:700;text-align:center}'
		. '.gbg-esim-steps strong{display:block}'
		. '.gbg-esim-devices{margin:1em 0}.gbg-esim-devices details{border-bottom:1px solid rgba(0,0,0,.12);padding:.55em 0}'
		. '.gbg-esim-devices summary{cursor:pointer;font-weight:600}.gbg-esim-devices summary span{font-weight:400;opacity:.7}'
		. '.gbg-esim-devices details p{margin:.5em 0 .2em}</style>';
}

function gbg_core_euro( float $v ): string {
	return '€' . number_format( $v, 2, '.', ',' );
}

add_shortcode(
	'gbg_esim_price',
	static function () {
		$d    = gbg_core_esim_data();
		$slug = (string) get_post_field( 'post_name', (int) get_the_ID() );
		$p    = $d['pages'][ $slug ] ?? null;
		if ( ! $p || ! $d['checked'] ) {
			return '';
		}
		$main = $p['day'] ? gbg_core_euro( $p['day'] ) . ' per day' : gbg_core_euro( $p['gb'] ) . ' per GB';
		$alt  = $p['day'] && $p['gb'] ? ' (or ' . gbg_core_euro( $p['gb'] ) . ' per GB)' : '';
		$for  = $p['country'] ? ' for ' . esc_html( $p['country'] ) : '';
		return gbg_core_esim_css() . '<p class="gbg-esim-price">eSIM' . $for . ' from <strong>' . esc_html( $main ) . '</strong>' . esc_html( $alt )
			. ' at Yesim <span class="gbg-esim-checked">(price checked ' . esc_html( gbg_core_esim_date( $d['checked'] ) ) . ')</span></p>';
	}
);

add_shortcode(
	'gbg_esim_checked',
	static function ( $atts ) {
		$atts = shortcode_atts( array( 'format' => 'day' ), $atts );
		$d    = gbg_core_esim_data();
		if ( empty( $d['checked'] ) ) {
			return '';
		}
		$t = strtotime( $d['checked'] . ' 12:00:00 UTC' );
		return gbg_core_esim_css() . esc_html( 'month' === $atts['format'] ? gmdate( 'F Y', $t ) : gbg_core_esim_date( $d['checked'] ) );
	}
);

add_shortcode(
	'gbg_esim_devices',
	static function () {
		$d = gbg_core_esim_data();
		if ( empty( $d['devices'] ) ) {
			return '';
		}
		// One closed <details> per brand: short on a phone, and the full list stays in the HTML for search engines.
		$html = gbg_core_esim_css() . '<div class="gbg-esim-devices">';
		foreach ( $d['devices'] as $g ) {
			$html .= '<details><summary>' . esc_html( $g['group'] ) . ' <span>(' . count( $g['models'] ) . ' models)</span></summary><p>'
				. esc_html( implode( ', ', $g['models'] ) ) . '</p></details>';
		}
		return $html . '</div>';
	}
);

add_filter(
	'the_content',
	static function ( $content ) {
		if ( ! is_string( $content ) || ! str_contains( $content, 'Kabul, Kandahar' ) || empty( gbg_core_settings()['esim_faq_fix'] ) ) {
			return $content;
		}
		if ( str_starts_with( (string) get_post_field( 'post_name', (int) get_the_ID() ), 'esim-afghanistan' ) ) {
			return $content; // right on the Afghanistan page itself
		}
		return str_replace( GBG_CORE_ESIM_FAQ_WRONG, 'in cities and towns as well as popular tourist destinations', $content );
	},
	9
);

function gbg_core_block_post( int $id ) {
	$post = $id ? get_post( $id ) : null;
	if ( ! $post || ! in_array( $post->post_type, GBG_CORE_BLOCK_TYPES, true ) ) {
		return new WP_Error( 'gbg_core_no_block', 'Not a shared block (us_page_block or wp_block).', array( 'status' => 404 ) );
	}
	return $post;
}

function gbg_core_block_info( WP_Post $post ): array {
	global $wpdb;
	$like = '%' . $wpdb->esc_like( '[us_page_block id="' . $post->ID . '"' ) . '%';
	$used = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE %s", $like ) ); // phpcs:ignore WordPress.DB
	$back = array_map( static fn( $b ) => $b['at'] ?? '', (array) get_post_meta( $post->ID, '_gbg_core_backup', false ) );
	return array(
		'id'       => $post->ID,
		'type'     => $post->post_type,
		'title'    => $post->post_title,
		'modified' => $post->post_modified_gmt,
		'used_by'  => $used,
		'backups'  => $back,
		'content'  => $post->post_content,
	);
}

/** Writes a block after keeping its current content as a backup (newest GBG_CORE_BLOCK_BACKUPS kept). */
function gbg_core_block_write( WP_Post $post, string $content ) {
	add_post_meta( $post->ID, '_gbg_core_backup', array( 'at' => gmdate( 'c' ), 'content' => $post->post_content ) );
	$all = (array) get_post_meta( $post->ID, '_gbg_core_backup', false );
	foreach ( array_slice( $all, 0, max( 0, count( $all ) - GBG_CORE_BLOCK_BACKUPS ) ) as $old ) {
		delete_post_meta( $post->ID, '_gbg_core_backup', $old );
	}
	$res = wp_update_post( array( 'ID' => $post->ID, 'post_content' => wp_slash( $content ) ), true );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	clean_post_cache( $post->ID );
	do_action( 'breeze_clear_all_cache' ); // the block is inlined in cached pages
	return gbg_core_block_info( get_post( $post->ID ) );
}

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'gbg/v1',
			'/page-block',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => 'gbg_core_can_manage',
					'callback'            => static function ( WP_REST_Request $request ) {
						$post = gbg_core_block_post( absint( $request->get_param( 'id' ) ) );
						return is_wp_error( $post ) ? $post : gbg_core_block_info( $post );
					},
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => 'gbg_core_can_manage',
					'callback'            => static function ( WP_REST_Request $request ) {
						$in   = (array) $request->get_json_params();
						$post = gbg_core_block_post( absint( $in['id'] ?? 0 ) );
						if ( is_wp_error( $post ) ) {
							return $post;
						}
						if ( ! isset( $in['content'] ) || ! is_string( $in['content'] ) || '' === trim( $in['content'] ) ) {
							return new WP_Error( 'gbg_core_bad_body', 'Send id and a non-empty content.', array( 'status' => 400 ) );
						}
						if ( ! empty( $in['expect_modified'] ) && $in['expect_modified'] !== $post->post_modified_gmt ) {
							return new WP_Error( 'gbg_core_changed', 'The block changed since you read it; read it again.', array( 'status' => 409 ) );
						}
						return gbg_core_block_write( $post, $in['content'] );
					},
				),
			)
		);
		register_rest_route(
			'gbg/v1',
			'/page-block/restore',
			array(
				'methods'             => 'POST',
				'permission_callback' => 'gbg_core_can_manage',
				'callback'            => static function ( WP_REST_Request $request ) {
					$in   = (array) $request->get_json_params();
					$post = gbg_core_block_post( absint( $in['id'] ?? 0 ) );
					if ( is_wp_error( $post ) ) {
						return $post;
					}
					foreach ( (array) get_post_meta( $post->ID, '_gbg_core_backup', false ) as $b ) {
						if ( ( $b['at'] ?? '' ) === ( $in['at'] ?? null ) ) {
							return gbg_core_block_write( $post, (string) $b['content'] );
						}
					}
					return new WP_Error( 'gbg_core_no_backup', 'No backup with that time; see backups in GET /page-block.', array( 'status' => 404 ) );
				},
			)
		);
		register_rest_route(
			'gbg/v1',
			'/esim-data',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => 'gbg_core_can_manage',
					'callback'            => static function () {
						$d = gbg_core_esim_data();
						return array(
							'checked' => $d['checked'] ?? '',
							'pages'   => count( (array) ( $d['pages'] ?? array() ) ),
							'devices' => array_sum( array_map( static fn( $g ) => count( $g['models'] ), (array) ( $d['devices'] ?? array() ) ) ),
						);
					},
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => 'gbg_core_can_manage',
					'callback'            => static function ( WP_REST_Request $request ) {
						$in = $request->get_json_params();
						if ( ! is_array( $in ) ) {
							return new WP_Error( 'gbg_core_bad_body', 'Send a JSON object.', array( 'status' => 400 ) );
						}
						$d = gbg_core_esim_sanitize( $in );
						if ( ! $d['checked'] ) {
							return new WP_Error( 'gbg_core_bad_body', 'checked (Y-m-d) is required.', array( 'status' => 400 ) );
						}
						update_option( GBG_CORE_ESIM_OPTION, $d, false );
						do_action( 'breeze_clear_all_cache' );
						return array(
							'checked' => $d['checked'],
							'pages'   => count( $d['pages'] ),
							'devices' => array_sum( array_map( static fn( $g ) => count( $g['models'] ), $d['devices'] ) ),
						);
					},
				),
			)
		);
	}
);
