<?php
/*
 * Plugin Name: Contact Form to WP posts
 * Version: 0.1
 * Plugin URI: http://mosaika.fr
 * Description: This simple plugin lets you save Contact Form 7 submissions into WordPress custom posts.
 * Author: Pierre Saikali
 * Author URI: http://www.mosaika.fr
 * Requires at least: 4.0
 * Tested up to: 4.0
 *
 * Text Domain: cf7_to_wp
 * Domain Path: /lang/
 *
 * @package WordPress
 * @author Pierre Saikali
 * @since 0.1
 */

__('Contact Form to WP posts', 'cf7_to_wp');
__('This simple plugin lets you save Contact Form 7 submissions into WordPress custom posts.', 'cf7_to_wp');

if ( ! defined( 'ABSPATH' ) ) exit;

// Load plugin class files
require_once( 'includes/class-cf7_to_wp.php' );
require_once( 'includes/class-cf7_to_wp-listtable.php' );
//require_once( 'includes/class-cf7_to_wp-settings.php' );

// Load plugin libraries
/*require_once( 'includes/lib/class-cf7_to_wp-admin-api.php' );
require_once( 'includes/lib/class-cf7_to_wp-post-type.php' );
require_once( 'includes/lib/class-cf7_to_wp-taxonomy.php' );*/

/**
 * Returns the main instance of cf7_to_wp to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object cf7_to_wp
 */
function cf7_to_wp () {
	$instance = cf7_to_wp::instance( __FILE__, '1.0.0' );

	/*if ( is_null( $instance->settings ) ) {
		$instance->settings = cf7_to_wp_Settings::instance( $instance );
	}*/

	return $instance;
}

cf7_to_wp();
