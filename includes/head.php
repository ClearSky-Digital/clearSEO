<?php
/**
 * Head output: title override, noindex, meta description, Open Graph and Twitter tags.
 * Canonical stays core's rel_canonical; this plugin adds none.
 *
 * @package CSD_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'document_title_parts',
	function ( $parts ) {
		if ( csd_seo_opt( 'mod_head' ) && is_singular() ) {
			$title = get_post_meta( get_queried_object_id(), '_csd_seo_title', true );
			if ( '' !== (string) $title ) {
				$parts['title'] = $title;
			}
		}
		return $parts;
	},
	20
);

add_filter(
	'wp_robots',
	function ( $robots ) {
		if ( csd_seo_opt( 'mod_head' ) && is_singular() && get_post_meta( get_queried_object_id(), '_csd_seo_noindex', true ) ) {
			$robots['noindex'] = true;
			unset( $robots['max-image-preview'] );
		}
		return $robots;
	},
	20
);

/** The image for social tags: featured image, else the site default. */
function csd_seo_current_image() {
	if ( is_singular() && has_post_thumbnail() ) {
		$url = get_the_post_thumbnail_url( get_queried_object_id(), 'large' );
		if ( $url ) {
			return $url;
		}
	}
	return (string) csd_seo_opt( 'default_image' );
}

add_action(
	'wp_head',
	function () {
		$desc = csd_seo_current_description();
		if ( csd_seo_opt( 'mod_head' ) && '' !== $desc ) {
			echo '<meta name="description" content="' . esc_attr( $desc ) . "\">\n";
		}
		if ( ! csd_seo_opt( 'mod_social' ) || ! ( is_singular() || is_front_page() ) ) {
			return;
		}
		// A page marked noindex, or protected by a password, gets no social tags either, so its text is not advertised.
		if ( is_singular() && ( get_post_meta( get_queried_object_id(), '_csd_seo_noindex', true ) || post_password_required( get_queried_object_id() ) ) ) {
			return;
		}
		$title = wp_get_document_title();
		$url   = is_singular() ? get_permalink( get_queried_object_id() ) : home_url( '/' );
		$image = esc_url( csd_seo_current_image() ); // Empty when the address is not a safe URL: then no image tags at all.
		$tags  = array(
			'og:locale'    => get_locale(),
			'og:type'      => is_singular( 'post' ) ? 'article' : 'website',
			'og:title'     => $title,
			'og:url'       => $url,
			'og:site_name' => get_bloginfo( 'name' ),
		);
		if ( '' !== $desc ) {
			$tags['og:description'] = $desc;
		}
		if ( '' !== $image ) {
			$tags['og:image'] = $image;
		}
		foreach ( $tags as $prop => $value ) {
			if ( 'og:image' === $prop ) {
				echo '<meta property="og:image" content="' . esc_url( $value ) . "\">\n";
				continue;
			}
			echo '<meta property="' . esc_attr( $prop ) . '" content="' . esc_attr( $value ) . "\">\n";
		}
		echo '<meta name="twitter:card" content="' . ( '' !== $image ? 'summary_large_image' : 'summary' ) . "\">\n";
		echo '<meta name="twitter:title" content="' . esc_attr( $title ) . "\">\n";
		if ( '' !== $desc ) {
			echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . "\">\n";
		}
		if ( '' !== $image ) {
			echo '<meta name="twitter:image" content="' . esc_url( $image ) . "\">\n";
		}
	},
	1
);
