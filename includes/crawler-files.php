<?php
/**
 * robots.txt text and the /llms.txt route. Both follow the ClearSky crawler-files standard.
 * No rewrite rules are added, so nothing needs flushing and nothing is left behind.
 *
 * @package CSD_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Search and AI answer crawlers: allowed, with the rules repeated because a crawler follows only its most specific group. */
function csd_seo_search_agents() {
	return array( 'Googlebot', 'Bingbot', 'Applebot', 'OAI-SearchBot', 'ChatGPT-User', 'PerplexityBot', 'Perplexity-User', 'Claude-SearchBot', 'Claude-User', 'MistralAI-User', 'DuckAssistBot' );
}

add_filter(
	'robots_txt',
	function ( $output, $public ) {
		if ( ! csd_seo_opt( 'mod_robots' ) || ! (int) $public ) {
			return $output; // A site set to discourage search engines keeps core's disallow-all text.
		}
		$signal = trim( (string) csd_seo_opt( 'robots_signal' ) );
		$rules  = array();
		if ( '' !== $signal ) {
			$rules[] = 'Content-Signal: ' . $signal;
		}
		$rules[] = 'Allow: /';
		$admin   = wp_parse_url( site_url( 'wp-admin/' ), PHP_URL_PATH );
		$rules[] = 'Disallow: ' . $admin;
		$rules[] = 'Allow: ' . $admin . 'admin-ajax.php';

		$lines   = array( 'User-agent: *' );
		$lines   = array_merge( $lines, $rules, array( '' ) );
		foreach ( csd_seo_search_agents() as $agent ) {
			$lines[] = 'User-agent: ' . $agent;
		}
		$lines   = array_merge( $lines, $rules, array( '' ) );

		$blocked = array_filter( array_map( 'csd_seo_clean_agent', preg_split( '/\r\n|\r|\n/', (string) csd_seo_opt( 'robots_block' ) ) ) );
		foreach ( $blocked as $agent ) {
			$lines[] = 'User-agent: ' . $agent;
		}
		if ( $blocked ) {
			$lines[] = 'Disallow: /';
			$lines[] = '';
		}
		$sitemap = function_exists( 'get_sitemap_url' ) ? get_sitemap_url( 'index' ) : false;
		if ( $sitemap ) {
			$lines[] = 'Sitemap: ' . $sitemap;
		}
		return implode( "\n", $lines ) . "\n";
	},
	20,
	2
);

/** Escape a title for a markdown link label: backslash first, then the brackets. */
function csd_seo_md_escape( $text ) {
	return str_replace( array( '\\', '[', ']' ), array( '\\\\', '\\[', '\\]' ), $text );
}

/** The text of llms.txt: only indexable pages, no email address. */
function csd_seo_llms_text() {
	$name = (string) ( csd_seo_opt( 'biz_name' ) ? csd_seo_opt( 'biz_name' ) : get_bloginfo( 'name' ) );
	$desc = (string) ( csd_seo_opt( 'default_desc' ) ? csd_seo_opt( 'default_desc' ) : get_bloginfo( 'description' ) );
	$out  = '# ' . $name . "\n";
	if ( '' !== $desc ) {
		$out .= "\n> " . $desc . "\n";
	}
	$facts = array();
	$parts = array_filter( array( csd_seo_opt( 'biz_street' ), csd_seo_opt( 'biz_locality' ), csd_seo_opt( 'biz_region' ), csd_seo_opt( 'biz_postal' ) ) );
	if ( $parts ) {
		$facts[] = 'Address: ' . implode( ', ', $parts );
	}
	if ( csd_seo_opt( 'biz_area' ) ) {
		$facts[] = 'Area served: ' . csd_seo_opt( 'biz_area' );
	}
	if ( csd_seo_opt( 'biz_phone' ) ) {
		$facts[] = 'Phone: ' . csd_seo_opt( 'biz_phone' );
	}
	if ( $facts ) {
		$out .= "\n" . implode( "\n", $facts ) . "\n";
	}
	$sections = array(
		'Pages' => array( 'page', -1, 'menu_order title', 'ASC' ),
		'Posts' => array( 'post', 50, 'date', 'DESC' ),
	);
	foreach ( $sections as $heading => $q ) {
		$ids = get_posts(
			array(
				'post_type'      => $q[0],
				'post_status'    => 'publish',
				'has_password'   => false,
				'posts_per_page' => $q[1],
				'orderby'        => $q[2],
				'order'          => $q[3],
				'fields'         => 'ids',
				'meta_query'     => array( csd_seo_indexable_meta_query() ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small query.
			)
		);
		if ( ! $ids ) {
			continue;
		}
		$out .= "\n## " . $heading . "\n\n";
		foreach ( $ids as $id ) {
			$out .= '- [' . csd_seo_md_escape( csd_seo_one_line( get_the_title( $id ) ) ) . '](' . get_permalink( $id ) . ')';
			$note = (string) get_post_meta( $id, '_csd_seo_desc', true );
			$out .= '' !== $note ? ': ' . $note : '';
			$out .= "\n";
		}
	}
	return $out;
}

add_action(
	'parse_request',
	function () {
		if ( ! csd_seo_opt( 'mod_llms' ) || ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$path = wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
		$base = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		if ( $base . '/llms.txt' !== $path || ! (int) get_option( 'blog_public' ) ) {
			return;
		}
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		echo csd_seo_llms_text(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain text response.
		exit;
	}
);
