<?php
/*
Plugin Name: Evaluate
Plugin URI: 
Description: Simple evaluation / Ratings plugin, includes voting, bookmarking etc.
Version: 0.1
Author: 
Author URI: 
License: GPLv2 or later.
*/
if ( !defined('ABSPATH') )
	die('-1');

define( 'EVALUATE_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'EVALUATE_BASENAME', plugin_basename(__FILE__) );
define( 'EVALUATE_DIR_URL',  plugins_url( ''  , EVALUATE_BASENAME ) );

require_once( 'lib/class.evaluate.php' );


add_action( 'init',        			array( 'Evaluate', 'init' ) );

// install and uninstall
register_activation_hook( __FILE__, array( 'Evaluate', 'install' ) );

