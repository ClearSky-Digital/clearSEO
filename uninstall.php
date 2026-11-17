<?php
/**
 * Uninstall: remove the option and the per-page fields. Nothing else was ever written.
 *
 * @package CSD_SEO
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/** Remove this plugin's data from the current site. */
function csd_seo_uninstall_site() {
	delete_option( 'csd_seo' );
	foreach ( array( '_csd_seo_title', '_csd_seo_desc', '_csd_seo_noindex' ) as $key ) {
		delete_post_meta_by_key( $key );
	}
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $csd_seo_blog_id ) {
		switch_to_blog( $csd_seo_blog_id );
		csd_seo_uninstall_site();
		restore_current_blog();
	}
} else {
	csd_seo_uninstall_site();
}
