<?php
/**
 * Trim core's sitemap: no users, only the taxonomies chosen in the settings, no noindex pages.
 *
 * @package CSD_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'wp_sitemaps_add_provider',
	function ( $provider, $name ) {
		return ( csd_seo_opt( 'mod_sitemap' ) && 'users' === $name ) ? false : $provider;
	},
	10,
	2
);

add_filter(
	'wp_sitemaps_taxonomies',
	function ( $taxonomies ) {
		if ( ! csd_seo_opt( 'mod_sitemap' ) ) {
			return $taxonomies;
		}
		$keep = (array) csd_seo_opt( 'sitemap_taxonomies' );
		return array_intersect_key( $taxonomies, array_flip( $keep ) );
	}
);

add_filter(
	'wp_sitemaps_posts_query_args',
	function ( $args ) {
		if ( csd_seo_opt( 'mod_sitemap' ) ) {
			$existing           = isset( $args['meta_query'] ) ? (array) $args['meta_query'] : array();
			$args['has_password'] = false;
			$args['meta_query'] = array_merge( $existing, array( csd_seo_indexable_meta_query() ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small, public-only query.
		}
		return $args;
	}
);
