<?php
/**
  Plugin Name: Evaluate
  Plugin URI: https://github.com/ubc/evaluate
  Version: 1.0
  Description: An evaluation plugin which can handle one-way (Like), two-way (Up/Down), star rating and polls.
  Author: Bugra Firat, Devindra Payment, CTLT, UBC
  Author URI: http://ctlt.ubc.ca
  License: GPLv2
 */

if ( ! defined('ABSPATH') )
	die('-1');

require( ABSPATH.'wp-includes/pluggable.php' );
if ( ! empty($_GET['_wp_http_referer']) && $_REQUEST['page'] == 'evaluate' ) {
	wp_redirect( remove_query_arg( array('_wp_http_referer'), stripslashes( $_SERVER['REQUEST_URI'] ) ) );
	exit;
}

global $wpdb; //reference to wpdb object
define( 'EVAL_DIR_PATH',       plugin_dir_path( __FILE__ )      );
define( 'EVAL_BASENAME',       plugin_basename( __FILE__ )      );
define( 'EVAL_DIR_URL',        plugins_url( '', EVAL_BASENAME ) );
define( 'EVAL_BASE_FILE',      __FILE__                         );

define( 'EVAL_DB_METRICS',     $wpdb->prefix.'evaluate_metrics' );
define( 'EVAL_DB_METRICS_VER', 1.0                              );
define( 'EVAL_DB_VOTES',       $wpdb->prefix.'evaluate_votes'   );
define( 'EVAL_DB_VOTES_VER',   1.0                              );
define( 'EVAL_OPTION',         'evaluate'                       );

define( 'EVAL_AJAX_FREQUENCY', 15                               );

//needed for WP_List_Table displays
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH.'wp-admin/includes/class-wp-list-table.php' );
}

require( 'lib/class.evaluate.php' );
require( 'lib/class.evaluate_admin.php' );
require( 'lib/class.evaluate_settings.php' );
require( 'lib/class.evaluate_metrics-list-table.php' );
require( 'lib/class.evaluate_content-list-table.php' );
require( 'lib/class.evaluate_users-list-table.php' );

//register the three activation hooks for the plugin
register_activation_hook(   'Evaluate', 'activate'   );
register_deactivation_hook( 'Evaluate', 'deactivate' );
register_uninstall_hook(    'Evaluate', 'uninstall'  );

