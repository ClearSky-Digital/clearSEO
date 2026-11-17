<?php
/**
 * Plugin Name: ClearSEO
 * Plugin URI: https://github.com/ClearSky-Digital/clearseo
 * Description: Small SEO outputs on core WordPress APIs: titles, descriptions, JSON-LD, sitemap, robots.txt and llms.txt. Every module is off until you turn it on.
 * Version: 1.1.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: ClearSky Digital
 * Author URI: https://cscloud.digital/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: clearseo
 *
 * @package CSD_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CSD_SEO_VERSION', '1.1.0' );
define( 'CSD_SEO_DIR', __DIR__ . '/' );

require_once CSD_SEO_DIR . 'includes/settings.php';
require_once CSD_SEO_DIR . 'includes/meta.php';
require_once CSD_SEO_DIR . 'includes/head.php';
require_once CSD_SEO_DIR . 'includes/schema.php';
require_once CSD_SEO_DIR . 'includes/sitemaps.php';
require_once CSD_SEO_DIR . 'includes/crawler-files.php';
