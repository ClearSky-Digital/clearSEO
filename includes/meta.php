<?php
/**
 * Per-page fields: title override, meta description and noindex. Plain post meta, registered for the REST API.
 *
 * @package CSD_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A meta query that keeps only pages that are not marked noindex (key missing, empty or 0).
 *
 * @return array
 */
function csd_seo_indexable_meta_query() {
	return array(
		'relation' => 'OR',
		array(
			'key'     => '_csd_seo_noindex',
			'compare' => 'NOT EXISTS',
		),
		array(
			'key'     => '_csd_seo_noindex',
			'value'   => array( '', '0' ),
			'compare' => 'IN',
		),
	);
}

/** A title as plain text: tags removed, entities decoded. */
function csd_seo_plain( $text ) {
	return html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' );
}

/** Post types that get the fields: every public type with an editor. */
function csd_seo_post_types() {
	$types = get_post_types( array( 'public' => true ), 'names' );
	unset( $types['attachment'] );
	return array_values( $types );
}

add_action(
	'init',
	function () {
		$fields = array(
			'_csd_seo_title' => array( 'string', 'sanitize_text_field' ),
			'_csd_seo_desc'  => array( 'string', 'sanitize_text_field' ),
			'_csd_seo_noindex' => array( 'boolean', 'rest_sanitize_boolean' ),
		);
		foreach ( csd_seo_post_types() as $type ) {
			foreach ( $fields as $key => $def ) {
				register_post_meta(
					$type,
					$key,
					array(
						'type'              => $def[0],
						'single'            => true,
						'show_in_rest'      => true,
						'sanitize_callback' => $def[1],
						'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
							return current_user_can( 'edit_post', $post_id );
						},
					)
				);
			}
		}
	}
);

add_action(
	'add_meta_boxes',
	function () {
		foreach ( csd_seo_post_types() as $type ) {
			add_meta_box( 'csd-seo', __( 'SEO', 'csd-seo' ), 'csd_seo_render_box', $type, 'normal', 'default', array( '__back_compat_meta_box' => true ) );
		}
	}
);

/** The meta box. */
function csd_seo_render_box( $post ) {
	wp_nonce_field( 'csd_seo_save', 'csd_seo_nonce' );
	$title = get_post_meta( $post->ID, '_csd_seo_title', true );
	$desc  = get_post_meta( $post->ID, '_csd_seo_desc', true );
	$no    = get_post_meta( $post->ID, '_csd_seo_noindex', true );
	echo '<p><label for="csd-seo-title"><strong>' . esc_html__( 'Title (empty uses the page title)', 'csd-seo' ) . '</strong></label><br><input type="text" class="widefat" id="csd-seo-title" name="csd_seo_title" value="' . esc_attr( $title ) . '"></p>';
	echo '<p><label for="csd-seo-desc"><strong>' . esc_html__( 'Meta description (about 150 to 160 characters)', 'csd-seo' ) . '</strong></label><br><textarea class="widefat" rows="3" id="csd-seo-desc" name="csd_seo_desc">' . esc_textarea( $desc ) . '</textarea></p>';
	echo '<p><label><input type="checkbox" name="csd_seo_noindex" value="1"' . checked( (bool) $no, true, false ) . '> ' . esc_html__( 'Ask search engines not to index this page (noindex)', 'csd-seo' ) . '</label></p>';
}

add_action(
	'save_post',
	function ( $post_id ) {
		if ( ! isset( $_POST['csd_seo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['csd_seo_nonce'] ) ), 'csd_seo_save' ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$map = array(
			'_csd_seo_title' => isset( $_POST['csd_seo_title'] ) ? sanitize_text_field( wp_unslash( $_POST['csd_seo_title'] ) ) : '',
			'_csd_seo_desc'  => isset( $_POST['csd_seo_desc'] ) ? sanitize_text_field( wp_unslash( $_POST['csd_seo_desc'] ) ) : '',
		);
		foreach ( $map as $key => $value ) {
			if ( '' === $value ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}
		if ( empty( $_POST['csd_seo_noindex'] ) ) {
			delete_post_meta( $post_id, '_csd_seo_noindex' );
		} else {
			update_post_meta( $post_id, '_csd_seo_noindex', true );
		}
	}
);

/**
 * The description for the page being shown: the page's own, else the home page default or tagline.
 *
 * @return string
 */
function csd_seo_current_description() {
	if ( is_singular() ) {
		if ( post_password_required( get_queried_object_id() ) ) {
			return '';
		}
		$desc = get_post_meta( get_queried_object_id(), '_csd_seo_desc', true );
		if ( '' !== (string) $desc ) {
			return (string) $desc;
		}
	}
	if ( is_front_page() ) {
		$desc = (string) csd_seo_opt( 'default_desc' );
		return '' !== $desc ? $desc : (string) get_bloginfo( 'description' );
	}
	return '';
}
