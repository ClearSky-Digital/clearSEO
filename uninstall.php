<?php
/**
 * Uninstall: remove the option and the per-page fields. Nothing else was ever written.
 *
 * @package CSD_SEO
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'csd_seo' );
foreach ( array( '_csd_seo_title', '_csd_seo_desc', '_csd_seo_noindex' ) as $key ) {
	delete_post_meta_by_key( $key );
}
