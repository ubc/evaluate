<?php
/*
  Plugin Name: Evaluate
  Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
  Description: An evaluation plugin which can handle one-way (Like), two-way (Up/Down), star rating and polls.
  Version: 1.0
  Author: Bugra Firat, CTLT_Dev
  Author URI: http://URI_Of_The_Plugin_Author
  License: GPLv2 or later.
 */

if ( ! defined('ABSPATH') ) {
	die('-1');
}

//remove _wp_http_referer from requests
require( ABSPATH . 'wp-includes/pluggable.php' );
if ( ! empty($_GET['_wp_http_referer']) && $_REQUEST['page'] == 'evaluate' ) {
	wp_redirect( remove_query_arg( array('_wp_http_referer'), stripslashes($_SERVER['REQUEST_URI']) ) );
	exit;
}

global $wpdb; //reference to wpdb object
//define plugin-specific globals
define( 'EVAL_DIR_PATH',       plugin_dir_path( __FILE__ )      );
define( 'EVAL_BASENAME',       plugin_basename( __FILE__ )      );
define( 'EVAL_DIR_URL',        plugins_url( '', EVAL_BASENAME ) );
define( 'EVAL_DB_METRICS',     $wpdb->prefix.'evaluate_metrics' );
define( 'EVAL_DB_METRICS_VER', 1                                );
define( 'EVAL_DB_VOTES',       $wpdb->prefix.'evaluate_votes'   );
define( 'EVAL_DB_VOTES_VER',   1                                );
define( 'EVAL_OPTION',         'evaluate'                       );

//require the plugin scripts
require( 'lib/class.evaluate.php' );
require( 'lib/class.evaluate_admin.php' );

//needed for WP_List_Table displays
if ( ! class_exists('WP_List_Table') ) {
	require_once( ABSPATH.'wp-admin/includes/class-wp-list-table.php' );
}

//various list table displays for admin side
require( 'lib/class.evaluate_metrics-list-table.php' );
require( 'lib/class.evaluate_content-list-table.php' );
require( 'lib/class.evaluate_users-list-table.php' );

//register the three activation hooks for the plugin
register_activation_hook(   'Evaluate', 'activate'   );
register_deactivation_hook( 'Evaluate', 'deactivate' );
register_uninstall_hook(    'Evaluate', 'uninstall'  );

//add action hooks for the plugin
add_action( 'init',       array( 'Evaluate', 'init' ), 15 ); //priority parameters MUST be > than CTLT_Stream priority otherwise post requests don't work
add_action( 'admin_init', array( 'Evaluate_Admin', 'init' ) );
add_action( 'admin_menu', array( 'Evaluate_Admin', 'admin_menu' ) );

//hooks to display meta box in post editor
add_action( 'load-post.php',     array( 'Evaluate_Admin', 'meta_box_setup' ) );
add_action( 'load-post-new.php', array( 'Evaluate_Admin', 'meta_box_setup' ) );
add_action( 'save_post',         array( 'Evaluate_Admin', 'save_post_meta' ), 10, 2 );

//filter for displaying metrics below content
add_filter( 'the_content',       array( 'Evaluate', 'content_display' ) );

//hook for ajax voting
add_action( 'wp_ajax_evaluate-vote',        array( 'Evaluate', 'ajax_handler' ) );
add_action( 'wp_ajax_nopriv_evaluate-vote', array( 'Evaluate', 'ajax_handler' ) );
?>
