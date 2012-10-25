<?php

/*
  Plugin Name: Evaluate
  Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
  Description: An evaluation plugin which can handle one-way (Like), two-way (Up/Down), star and poll with question and answers.
  Version: 1.0
  Author: Bugra Firat
  Author URI: http://URI_Of_The_Plugin_Author
  License: GPLv2 or later.
 */

if (!defined('ABSPATH')) {
  die('-1');
}

global $wpdb; //reference to wpdb object
//define plugin-specific globals
define('EVAL_DIR_PATH', plugin_dir_path(__FILE__));
define('EVAL_BASENAME', plugin_basename(__FILE__));
define('EVAL_DIR_URL', plugins_url('', EVAL_BASENAME));
define('EVAL_DB_METRICS', $wpdb->prefix . 'evaluate_metrics');
define('EVAL_DB_METRICS_VER', 1);
define('EVAL_DB_VOTES', $wpdb->prefix . 'evaluate_votes');
define('EVAL_DB_VOTES_VER', 1);
define('EVAL_OPTION', 'evaluate');

//require the plugin scripts
require('lib/class.evaluate.php');
require('lib/class.evaluate-admin.php');

//needed for WP_List_Table displays
if (!class_exists('WP_List_Table')) {
  require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}
require('lib/class.evaluate-metrics-list-table.php');

//register the three activation hooks for the plugin
register_activation_hook(__FILE__, 'on_activation');
register_deactivation_hook(__FILE__, 'on_deactivation');
register_uninstall_hook(__FILE__, 'on_uninstall');

//functions to do with activation, deactivation and uninstallation
function on_activation() {
  Evaluate::activate();
}

function on_deactivation() {
  Evaluate::deactivate();
}

function on_uninstall() {
  Evaluate::uninstall();
}

//add action hooks for the plugin
add_action('init', array('Evaluate', 'init'));
add_action('admin_init', array('Evaluate_Admin', 'init'));
add_action('admin_menu', array('Evaluate_Admin', 'admin_menu'));
//hooks to clean_url which prints out scripts when requested, to add defer="defer" so the JS loads last
add_filter('clean_url', array('Evaluate_Admin', 'add_defer_to_script'), 11, 1);
//hooks to display meta box in post editor
add_action('load-post.php', array('Evaluate_Admin', 'meta_box_setup'));
add_action('load-post-new.php', array('Evaluate_Admin', 'meta_box_setup'));
add_action('save_post', array('Evaluate_Admin', 'save_post_meta'));
//hooks to display metrics below content
add_filter('the_content', array('Evaluate', 'content_display'));
?>
