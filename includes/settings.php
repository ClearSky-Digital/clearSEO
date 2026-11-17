<?php
/**
 * Settings: one option, one page. Nothing is output until a module is switched on.
 *
 * @package CSD_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The schema.org types offered for the business (all are subtypes of Organization or LocalBusiness). */
function csd_seo_business_types() {
	return array(
		'Organization'         => 'Organization (no street address)',
		'LocalBusiness'        => 'Local business',
		'RealEstateAgent'      => 'Real estate agent',
		'ProfessionalService'  => 'Professional service',
		'LegalService'         => 'Legal service',
		'AccountingService'    => 'Accounting service',
		'HomeAndConstructionBusiness' => 'Home and construction business',
		'Store'                => 'Store',
		'Restaurant'           => 'Restaurant',
		'HealthAndBeautyBusiness' => 'Health and beauty business',
		'MedicalBusiness'      => 'Medical business',
	);
}

/** Default values. Every module is off. */
function csd_seo_defaults() {
	return array(
		'mod_head'          => 0, // Description, robots and canonical output for pages.
		'mod_social'        => 0, // Open Graph and Twitter tags.
		'mod_schema'        => 0, // JSON-LD.
		'mod_sitemap'       => 0, // Trimmed core sitemap.
		'mod_robots'        => 0, // robots.txt text.
		'mod_llms'          => 0, // /llms.txt.
		'default_desc'      => '',
		'default_image'     => '',
		'sitemap_taxonomies' => array(),
		'biz_type'          => 'Organization',
		'biz_name'          => '',
		'biz_logo'          => '',
		'biz_phone'         => '',
		'biz_street'        => '',
		'biz_locality'      => '',
		'biz_region'        => '',
		'biz_postal'        => '',
		'biz_country'       => '',
		'biz_area'          => '',
		'biz_same_as'       => '',
		'robots_signal'     => 'search=yes, ai-input=yes, ai-train=no',
		'robots_block'      => '',
	);
}

/**
 * One setting by name.
 *
 * @param string $key Setting name.
 * @return mixed
 */
function csd_seo_opt( $key ) {
	$all = wp_parse_args( (array) get_option( 'csd_seo', array() ), csd_seo_defaults() );
	return isset( $all[ $key ] ) ? $all[ $key ] : null;
}

/** Keep only known, clean values. */
function csd_seo_sanitize( $in ) {
	$in   = is_array( $in ) ? $in : array();
	$def  = csd_seo_defaults();
	$out  = array();
	$urls = array( 'default_image', 'biz_logo' );
	foreach ( $def as $key => $default ) {
		if ( isset( $in[ $key ] ) && is_array( $in[ $key ] ) && 'sitemap_taxonomies' !== $key ) {
			$in[ $key ] = '';
		}
		if ( 0 === strpos( $key, 'mod_' ) ) {
			$out[ $key ] = empty( $in[ $key ] ) ? 0 : 1;
		} elseif ( 'sitemap_taxonomies' === $key ) {
			$valid       = array_keys( get_taxonomies( array( 'public' => true ) ) );
			$out[ $key ] = array_values( array_intersect( isset( $in[ $key ] ) ? (array) $in[ $key ] : array(), $valid ) );
		} elseif ( 'biz_type' === $key ) {
			$out[ $key ] = isset( $in[ $key ], csd_seo_business_types()[ $in[ $key ] ] ) ? $in[ $key ] : 'Organization';
		} elseif ( in_array( $key, $urls, true ) ) {
			$out[ $key ] = isset( $in[ $key ] ) ? esc_url_raw( trim( $in[ $key ] ) ) : '';
		} elseif ( 'biz_same_as' === $key ) {
			$lines       = isset( $in[ $key ] ) ? preg_split( '/\r\n|\r|\n/', (string) $in[ $key ] ) : array();
			$lines       = array_filter( array_map( 'esc_url_raw', array_map( 'trim', $lines ) ) );
			$out[ $key ] = implode( "\n", $lines );
		} elseif ( 'robots_block' === $key ) {
			$lines       = isset( $in[ $key ] ) ? preg_split( '/\r\n|\r|\n/', (string) $in[ $key ] ) : array();
			$lines       = array_filter( array_map( 'csd_seo_clean_agent', $lines ) );
			$out[ $key ] = implode( "\n", $lines );
		} else {
			$out[ $key ] = isset( $in[ $key ] ) ? sanitize_text_field( $in[ $key ] ) : '';
		}
	}
	return $out;
}

/** A crawler name: letters, digits, dot, dash and underscore only. */
function csd_seo_clean_agent( $line ) {
	return preg_replace( '/[^A-Za-z0-9._-]/', '', trim( $line ) );
}

add_action(
	'admin_init',
	function () {
		register_setting(
			'csd_seo',
			'csd_seo',
			array(
				'type'              => 'array',
				'sanitize_callback' => 'csd_seo_sanitize',
				'default'           => csd_seo_defaults(),
			)
		);
	}
);

add_action(
	'admin_menu',
	function () {
		add_options_page( __( 'ClearSEO', 'clearseo' ), __( 'ClearSEO', 'clearseo' ), 'manage_options', 'clearseo', 'csd_seo_render_page' );
	}
);

/** One text row. */
function csd_seo_row_text( $key, $label, $hint = '', $type = 'text' ) {
	printf(
		'<tr><th scope="row"><label for="csd-%1$s">%2$s</label></th><td><input type="%3$s" class="regular-text" id="csd-%1$s" name="csd_seo[%1$s]" value="%4$s">%5$s</td></tr>',
		esc_attr( $key ),
		esc_html( $label ),
		esc_attr( $type ),
		esc_attr( csd_seo_opt( $key ) ),
		$hint ? '<p class="description">' . esc_html( $hint ) . '</p>' : ''
	);
}

/** One checkbox row. */
function csd_seo_row_check( $key, $label, $hint = '' ) {
	printf(
		'<tr><th scope="row">%2$s</th><td><label><input type="checkbox" name="csd_seo[%1$s]" value="1"%3$s> %4$s</label></td></tr>',
		esc_attr( $key ),
		esc_html( $label ),
		checked( (bool) csd_seo_opt( $key ), true, false ),
		esc_html( $hint )
	);
}

/** The settings page. */
function csd_seo_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	echo '<div class="wrap"><h1>' . esc_html__( 'ClearSEO', 'clearseo' ) . '</h1>';
	echo '<form method="post" action="options.php">';
	settings_fields( 'csd_seo' );

	echo '<h2>' . esc_html__( 'Modules', 'clearseo' ) . '</h2><p>' . esc_html__( 'Each module is off until you switch it on. Switching one off removes its output at once; nothing is left behind.', 'clearseo' ) . '</p><table class="form-table" role="presentation">';
	csd_seo_row_check( 'mod_head', __( 'Page head', 'clearseo' ), __( 'Meta description, title override and noindex per page', 'clearseo' ) );
	csd_seo_row_check( 'mod_social', __( 'Social tags', 'clearseo' ), __( 'Open Graph and Twitter tags', 'clearseo' ) );
	csd_seo_row_check( 'mod_schema', __( 'Structured data', 'clearseo' ), __( 'JSON-LD: business, website, page and breadcrumb', 'clearseo' ) );
	csd_seo_row_check( 'mod_sitemap', __( 'Sitemap trim', 'clearseo' ), __( 'Drop the users sitemap, unused taxonomies and noindex pages', 'clearseo' ) );
	csd_seo_row_check( 'mod_robots', __( 'robots.txt', 'clearseo' ), __( 'Write the robots.txt text (a real robots.txt file on the server wins over this)', 'clearseo' ) );
	csd_seo_row_check( 'mod_llms', __( 'llms.txt', 'clearseo' ), __( 'Serve /llms.txt from the site\'s indexable pages', 'clearseo' ) );
	echo '</table>';

	echo '<h2>' . esc_html__( 'Defaults', 'clearseo' ) . '</h2><table class="form-table" role="presentation">';
	csd_seo_row_text( 'default_desc', __( 'Home page description', 'clearseo' ), __( 'Used on the home page and as the llms.txt summary. Leave empty to use the tagline.', 'clearseo' ) );
	csd_seo_row_text( 'default_image', __( 'Default social image URL', 'clearseo' ), __( 'Used when a page has no featured image.', 'clearseo' ), 'url' );
	echo '<tr><th scope="row">' . esc_html__( 'Taxonomies in the sitemap', 'clearseo' ) . '</th><td>';
	foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tax ) {
		printf(
			'<label><input type="checkbox" name="csd_seo[sitemap_taxonomies][]" value="%1$s"%2$s> %3$s</label><br>',
			esc_attr( $tax->name ),
			checked( in_array( $tax->name, (array) csd_seo_opt( 'sitemap_taxonomies' ), true ), true, false ),
			esc_html( $tax->labels->name )
		);
	}
	echo '<p class="description">' . esc_html__( 'Checked ones stay in the sitemap when the sitemap trim is on. The rest are dropped.', 'clearseo' ) . '</p></td></tr>';
	echo '</table>';

	echo '<h2>' . esc_html__( 'Business facts', 'clearseo' ) . '</h2><p>' . esc_html__( 'Enter only facts that are true and shown on the site. Empty fields are left out of the structured data.', 'clearseo' ) . '</p><table class="form-table" role="presentation">';
	echo '<tr><th scope="row"><label for="csd-biz_type">' . esc_html__( 'Type', 'clearseo' ) . '</label></th><td><select id="csd-biz_type" name="csd_seo[biz_type]">';
	foreach ( csd_seo_business_types() as $value => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( csd_seo_opt( 'biz_type' ), $value, false ), esc_html( $label ) );
	}
	echo '</select></td></tr>';
	csd_seo_row_text( 'biz_name', __( 'Name', 'clearseo' ), __( 'Empty uses the site title.', 'clearseo' ) );
	csd_seo_row_text( 'biz_logo', __( 'Logo URL', 'clearseo' ), '', 'url' );
	csd_seo_row_text( 'biz_phone', __( 'Phone', 'clearseo' ) );
	csd_seo_row_text( 'biz_street', __( 'Street address', 'clearseo' ) );
	csd_seo_row_text( 'biz_locality', __( 'City', 'clearseo' ) );
	csd_seo_row_text( 'biz_region', __( 'State or region', 'clearseo' ) );
	csd_seo_row_text( 'biz_postal', __( 'Postal code', 'clearseo' ) );
	csd_seo_row_text( 'biz_country', __( 'Country (two-letter code)', 'clearseo' ) );
	csd_seo_row_text( 'biz_area', __( 'Area served', 'clearseo' ), __( 'For example a city or island.', 'clearseo' ) );
	printf(
		'<tr><th scope="row"><label for="csd-biz_same_as">%s</label></th><td><textarea id="csd-biz_same_as" class="large-text" rows="4" name="csd_seo[biz_same_as]">%s</textarea><p class="description">%s</p></td></tr>',
		esc_html__( 'Profile links', 'clearseo' ),
		esc_textarea( csd_seo_opt( 'biz_same_as' ) ),
		esc_html__( 'One web address per line (social and directory profiles).', 'clearseo' )
	);
	echo '</table>';

	echo '<h2>' . esc_html__( 'robots.txt', 'clearseo' ) . '</h2><table class="form-table" role="presentation">';
	csd_seo_row_text( 'robots_signal', __( 'Content-Signal', 'clearseo' ), __( 'Preference signal for crawlers. Empty leaves the line out.', 'clearseo' ) );
	printf(
		'<tr><th scope="row"><label for="csd-robots_block">%s</label></th><td><textarea id="csd-robots_block" class="large-text" rows="6" name="csd_seo[robots_block]">%s</textarea><p class="description">%s</p></td></tr>',
		esc_html__( 'Crawlers to block', 'clearseo' ),
		esc_textarea( csd_seo_opt( 'robots_block' ) ),
		esc_html__( 'One crawler name per line (for example GPTBot or CCBot). Search crawlers stay allowed.', 'clearseo' )
	);
	echo '</table>';

	submit_button();
	echo '</form></div>';
}
