<?php
/**
 * Plugin Name: !GoBackpackGo Core
 * Description: Small site helpers for GoBackpackGo: noindex for an approved list of posts, an automatic affiliate disclosure, Travelpayouts SubIDs and Yoast fields of pages in the REST API, an optional lock on the public MCP endpoint, optional Redirection access for Claude's account, updates through the normal WordPress Updates screen, a security health report, optional hardening switches, lightweight A/B tests and a growth module that collects tagged email sign-ups for lead magnets. Every output feature is off until switched on.
 * Version: 0.3.33
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

const GBG_CORE_VERSION = '0.3.33';
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
	foreach ( array( 'noindex_enabled', 'disclosure_enabled', 'subid_enabled', 'subid_lang', 'subid_whole_page', 'mcp_lock_enabled', 'redirection_access', 'xmlrpc_off', 'author_scan_block', 'users_api_private', 'hide_generator', 'leads_enabled', 'pin_enabled', 'share_enabled', 'lightbox_fix', 'track_enabled' ) as $key ) {
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
	return 0 === strpos( $route, '/mcp/mcp-adapter-default-server' );
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
// once instead of after the 6-hour cache (Paul 21-10: live did not show 0.3.13).
add_action(
	'load-update-core.php',
	static function () {
		if ( isset( $_GET['force-check'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			delete_transient( 'gbg_core_release' );
		}
	}
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
						// Front-end features (A/B tests, share bar, opt-in) live in the page HTML: empty the page cache so visitors get them.
						do_action( 'breeze_clear_all_cache' );
						return array(
							'version'  => GBG_CORE_VERSION,
							'settings' => gbg_core_settings( true ),
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

/** Insert, or merge tags into the existing row (re-subscribes an unsubscribed address: a new, explicit sign-up). */
function gbg_core_lead_save( array $d, string $ip_hash ): void {
	global $wpdb;
	gbg_core_leads_install();
	$table = gbg_core_leads_table();
	$now   = gmdate( 'Y-m-d H:i:s' );
	$row   = $wpdb->get_row( $wpdb->prepare( "SELECT id, tags, first_name FROM $table WHERE email = %s", $d['email'] ), ARRAY_A ); // phpcs:ignore WordPress.DB
	$base  = array( 'status' => 'active', 'consent_version' => gbg_core_settings()['consent_version'], 'consent_at' => $now, 'updated_at' => $now, 'ip_hash' => $ip_hash );
	if ( $row ) {
		$tags = gbg_core_tags_clean( array_merge( array_filter( explode( ',', (string) $row['tags'] ) ), $d['tags'] ) );
		$wpdb->update( $table, $base + array( 'tags' => implode( ',', $tags ), 'first_name' => '' !== $d['name'] ? $d['name'] : $row['first_name'] ), array( 'id' => (int) $row['id'] ) ); // phpcs:ignore WordPress.DB
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
		ob_start( static fn( $html ) => ( is_string( $html ) && '' !== $html ) ? gbg_core_go_page_links( $html, $post_id, $slugs ) : $html );
	},
	20
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
		. 'if(slug){document.cookie="gbg_pl="+encodeURIComponent([p.placement,p.ab_test,p.link_text,location.href].join("|"))+";path=/;max-age=60;SameSite=Lax";'
		. 'var o={event:"gbg_affiliate_click"};for(var k in p)o[k]=p[k];(window.dataLayer=window.dataLayer||[]).push(o)}else{gbgEv("affiliate_click",p)}},true);'
		// Cookie banner choice, counted anonymously (no cookie, no GA4): shown once per page view, then the button pressed.
		. 'var cs=function(st){try{navigator.sendBeacon(c.u,JSON.stringify({n:"consent",p:{status:st}}))}catch(x){}};'
		. 'setTimeout(function(){var b=document.querySelector(".cmplz-cookiebanner");if(b&&b.offsetParent!==null&&!/cmplz-dismissed|cmplz-hidden/.test(b.className))cs("shown")},1500);'
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
	$cf = (string) ( $server['HTTP_CF_CONNECTING_IP'] ?? '' );
	return filter_var( $cf, FILTER_VALIDATE_IP ) ? $cf : (string) ( $server['REMOTE_ADDR'] ?? '' );
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
	$pl = explode( '|', (string) wp_unslash( $_COOKIE['gbg_pl'] ?? '' ), 4 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	gbg_core_go_count( $slug, ( $pl[0] ?? '' ) ?: 'unknown', (string) ( $_GET['s'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
	gbg_core_mp_send(
		'affiliate_click',
		array(
			'link_slug'     => $slug,
			'link_domain'   => preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( $target, PHP_URL_HOST ) ) ),
			'placement'     => $pl[0] ?? '' ?: 'unknown',
			'ab_test'       => $pl[1] ?? '',
			'link_text'     => $pl[2] ?? '',
			'page_location' => ( $pl[3] ?? '' ) ?: (string) ( $_SERVER['HTTP_REFERER'] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
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
					$p                  = array_intersect_key( (array) ( $b['p'] ?? array() ), array_flip( array( 'link_slug', 'link_domain', 'placement', 'ab_test', 'link_text', 'method', 'item_id', 'promo_code' ) ) );
					if ( 'consent' === $n ) { // anonymous count of the banner choice (shown / allow / deny), never sent to GA4
						gbg_core_go_count( 'consent', in_array( $b['p']['status'] ?? '', array( 'shown', 'allow', 'deny', 'custom' ), true ) ? (string) $b['p']['status'] : 'unknown' );
						return new WP_REST_Response( null, 204 );
					}
					if ( 'affiliate_click' === $n ) {
						gbg_core_go_count( (string) ( $p['link_slug'] ?? '' ), (string) ( $p['placement'] ?? '' ) ?: 'unknown' );
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
					foreach ( array_slice( (array) ( $request->get_json_params()['urls'] ?? array() ), 0, 20 ) as $url ) {
						$url  = (string) $url;
						$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
						if ( ! str_starts_with( $url, 'https://' ) || ! gbg_core_host_allowed( preg_replace( '/^www\./', '', $host ), $hosts ) ) {
							$out[] = array( 'url' => $url, 'status' => 'host not allowed' );
							continue;
						}
						$r     = wp_remote_get( $url, array( 'timeout' => 10, 'redirection' => 5, 'limit_response_size' => 200000, 'user-agent' => 'Mozilla/5.0 (compatible; GoBackpackGo link check)' ) );
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

function gbg_core_go_count( string $slug, string $placement, string $page = '' ): void {
	if ( is_user_logged_in() || gbg_core_is_bot( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return;
	}
	$counts = get_option( 'gbg_core_go_counts', array() );
	update_option( 'gbg_core_go_counts', gbg_core_go_count_add( is_array( $counts ) ? $counts : array(), gmdate( 'Y-m-d' ), $slug, $placement, 120, $page ), false );
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
					$counts = (array) get_option( 'gbg_core_go_counts', array() );
					return array( 'days' => $days, 'counts' => array_filter( $counts, static fn( $d ) => $d > $cut, ARRAY_FILTER_USE_KEY ) );
				},
			)
		);
	}
);
