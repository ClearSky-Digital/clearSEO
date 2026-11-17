<?php
/**
 * JSON-LD: one @graph with the business, the website, the page and its breadcrumb.
 * Only facts entered in the settings are included; nothing is invented.
 *
 * @package CSD_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The business node, or null when the settings hold no name. */
function csd_seo_business_node() {
	$name = (string) csd_seo_opt( 'biz_name' );
	if ( '' === $name ) {
		$name = (string) get_bloginfo( 'name' );
	}
	if ( '' === $name ) {
		return null;
	}
	$node = array(
		'@type' => csd_seo_opt( 'biz_type' ),
		'@id'   => home_url( '/#organization' ),
		'name'  => $name,
		'url'   => home_url( '/' ),
	);
	if ( csd_seo_opt( 'biz_logo' ) ) {
		$node['logo'] = array(
			'@type'      => 'ImageObject',
			'url'        => csd_seo_opt( 'biz_logo' ),
		);
	}
	if ( csd_seo_opt( 'biz_phone' ) ) {
		$node['telephone'] = csd_seo_opt( 'biz_phone' );
	}
	$addr = array_filter(
		array(
			'streetAddress'   => csd_seo_opt( 'biz_street' ),
			'addressLocality' => csd_seo_opt( 'biz_locality' ),
			'addressRegion'   => csd_seo_opt( 'biz_region' ),
			'postalCode'      => csd_seo_opt( 'biz_postal' ),
			'addressCountry'  => csd_seo_opt( 'biz_country' ),
		)
	);
	// A postal address is only valid for a local business type and when the street and city are given.
	if ( 'Organization' !== csd_seo_opt( 'biz_type' ) && ! empty( $addr['streetAddress'] ) && ! empty( $addr['addressLocality'] ) ) {
		$node['address'] = array( '@type' => 'PostalAddress' ) + $addr;
	}
	if ( csd_seo_opt( 'biz_area' ) ) {
		$node['areaServed'] = csd_seo_opt( 'biz_area' );
	}
	$same = array_values( array_filter( preg_split( '/\r\n|\r|\n/', (string) csd_seo_opt( 'biz_same_as' ) ) ) );
	if ( $same ) {
		$node['sameAs'] = $same;
	}
	return $node;
}

/** The breadcrumb for a singular page: Home, ancestors (pages) and the page itself. */
function csd_seo_breadcrumb_node( $post_id ) {
	$items = array( array( __( 'Home', 'csd-seo' ), home_url( '/' ) ) );
	foreach ( array_reverse( get_post_ancestors( $post_id ) ) as $anc ) {
		$items[] = array( get_the_title( $anc ), get_permalink( $anc ) );
	}
	$items[] = array( get_the_title( $post_id ), get_permalink( $post_id ) );
	$list    = array();
	foreach ( $items as $i => $item ) {
		$entry = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'name'     => wp_strip_all_tags( $item[0] ),
		);
		// Google allows the last item to omit the address; keep it for clarity.
		$entry['item'] = $item[1];
		$list[]        = $entry;
	}
	return array(
		'@type'           => 'BreadcrumbList',
		'@id'             => get_permalink( $post_id ) . '#breadcrumb',
		'itemListElement' => $list,
	);
}

/** The whole graph for the page being shown. */
function csd_seo_build_graph() {
	$graph = array();
	$biz   = csd_seo_business_node();
	if ( $biz ) {
		$graph[] = $biz;
	}
	$graph[] = array(
		'@type'     => 'WebSite',
		'@id'       => home_url( '/#website' ),
		'url'       => home_url( '/' ),
		'name'      => get_bloginfo( 'name' ),
		'publisher' => $biz ? array( '@id' => home_url( '/#organization' ) ) : null,
		'inLanguage' => get_bloginfo( 'language' ),
	);
	if ( is_singular() ) {
		$id   = get_queried_object_id();
		$url  = get_permalink( $id );
		$page = array(
			'@type'         => 'WebPage',
			'@id'           => $url . '#webpage',
			'url'           => $url,
			'name'          => wp_strip_all_tags( get_the_title( $id ) ),
			'isPartOf'      => array( '@id' => home_url( '/#website' ) ),
			'datePublished' => get_the_date( 'c', $id ),
			'dateModified'  => get_the_modified_date( 'c', $id ),
			'inLanguage'    => get_bloginfo( 'language' ),
		);
		$desc = csd_seo_current_description();
		if ( '' !== $desc ) {
			$page['description'] = $desc;
		}
		if ( ! is_front_page() ) {
			$page['breadcrumb'] = array( '@id' => $url . '#breadcrumb' );
		}
		$graph[] = $page;
		if ( ! is_front_page() ) {
			$graph[] = csd_seo_breadcrumb_node( $id );
		}
	}
	$graph = array_map(
		function ( $node ) {
			return array_filter(
				$node,
				function ( $v ) {
					return null !== $v && '' !== $v;
				}
			);
		},
		$graph
	);
	return array(
		'@context' => 'https://schema.org',
		'@graph'   => $graph,
	);
}

add_action(
	'wp_head',
	function () {
		if ( ! csd_seo_opt( 'mod_schema' ) || is_404() || is_search() || is_feed() ) {
			return;
		}
		$json = wp_json_encode( csd_seo_build_graph(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );
		if ( $json ) {
			echo '<script type="application/ld+json">' . $json . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with HEX_TAG and HEX_AMP.
		}
	},
	2
);
