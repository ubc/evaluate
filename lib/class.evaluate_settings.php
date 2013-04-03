<?php
/**
 * Settings Screen for the Evaluate Metrics
 */
class Evaluate_Settings {
	static $options = array();
	static $frequency_options = array( 10, 15, 20, 30, 60 );
	
	public static function init() {
		if ( ! function_exists( 'is_plugin_active' ) ):
			// Include plugins.php to check for other plugins from the frontend
			include_once( ABSPATH.'wp-admin/includes/plugin.php' );
		endif;
		
		self::$options['EVAL_STREAM'] = is_plugin_active('stream/stream.php');
		
		add_action( 'admin_init', array( __CLASS__, 'load' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
	}
	
	public static function admin_menu() {
		add_submenu_page( 'evaluate', 'Settings', 'Settings', 'manage_options', 'evaluate_settings', array( __CLASS__, 'admin_page' ) );
	}
	
	public static function load() {
		// Register settings
		register_setting( 'evaluate_options', 'ajax_frequency', array( __CLASS__, 'sanitize_ajax_frequency' ) );
		
		// Main settings
		add_settings_section( 'evaluate_settings_main', 'Evaluate Settings', array( __CLASS__, 'setting_section_main' ), 'evaluate_settings' );
		add_settings_field( 'ajax_frequency', 'Ajax Update Frequency', array( __CLASS__, 'setting_ajax_frequency' ), 'evaluate_settings', 'evaluate_settings_main' );
		
		// Plugin integration
		add_settings_section( 'evaluate_settings_plugins', 'Plugin Integration Status', array( __CLASS__, 'setting_section_plugins' ), 'evaluate_settings' );
		add_settings_field( 'ctlt_stream_found',    'CTLT_Stream plugin', array( __CLASS__, 'setting_stream_plugin' ), 'evaluate_settings', 'evaluate_settings_plugins' );
		add_settings_field( 'nodejs_server_status', 'NodeJS Server',      array( __CLASS__, 'setting_nodejs_server' ), 'evaluate_settings', 'evaluate_settings_plugins' );
	}
	
	public static function setting_section_main() {
		?>
		Main Settings
		<?php
	}
	
	public static function setting_section_plugins() {
		?>
		Integration for the CTLT Stream plugin.
		<?php
	}
	
	public static function setting_ajax_frequency() {
		$selected = get_option( 'ajax_frequency', EVAL_AJAX_FREQUENCY );
		?>
		<select name="ajax_frequency">
			<?php foreach( self::$frequency_options as $i => $value ): ?>
				<option value="<?php echo $i; ?>" <?php selected( $selected == $i ); ?>><?php echo $value; ?></option>
			<?php endforeach; ?>
		</select> seconds
		<br />
		<small>If the NodeJS server is not connected, this is the frequency with which the plugin will poll for metric updates. Higher numbers will reduce server load.</small>
		<?php
	}
	
	public static function sanitize_ajax_frequency( $input ) {
		if ( ! empty( $input ) && 0 <= $input && $input < count( self::$frequency_options ) ):
			return $input;
		else:
			return EVAL_AJAX_FREQUENCY;
		endif;
	}
	
	public static function setting_stream_plugin() {
		?>
		<input id="ctlt_stream_status" name="ctlt_stream_status" type="checkbox" disabled="disabled" style="display: none" <?php checked( self::$options['EVAL_STREAM'] ); ?>/>
		
		<?php if ( self::$options['EVAL_STREAM'] == true ): ?>
			<div style="color: green">Enabled</div>
		<?php else: ?>
			<div style="color: red">Not Found</div>
		<?php endif;
	}
	
	public static function setting_nodejs_server() {
		?>
		<input id="nodejs_server_status" name="nodejs_server_status" type="checkbox" disabled="disabled" style="display: none" <?php checked( CTLT_Stream::is_node_active() ); ?> />
		
		<?php if ( self::$options['EVAL_STREAM'] != true ): ?>
			<div style="color: red">Stream Plugin Not Found</div>
		<?php elseif ( CTLT_Stream::is_node_active() ): ?>
			<div style="color: green">Connected</div>
		<?php else: ?>
			<div style="color: red">Server Not Found</div>
		<?php endif;
	}
	
	public static function admin_page() {
		?>
		<form id="evaluate-options" method="post" action="options.php">
			<?php
				do_settings_sections('evaluate_settings');
				settings_fields('evaluate_options');
			?>
			<br />
			<input type="submit" class="button-primary" value="Save Changes" />
		</form>
		<?php
	}
}

add_action( 'init', array( 'Evaluate_Settings', 'init' ) );