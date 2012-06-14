<?php 


class Evaluate_Admin {
	
  	
  	public static function init(){
  		
		register_setting( 'evaluate_settings_group', 'evaluate_settings', array( 'Evaluate_Admin', 'sanitize_evaluate_settings' ) );
		
		// register scripts and styles
		wp_register_script( 'evaluate-admin-add', EVALUATE_DIR_URL.'/js/admin-add.js' , array( 'jquery' ), '1.0', true ); 
		wp_register_style( 'evaluate-admin', EVALUATE_DIR_URL. '/css/admin.css' );
		
 	}
 	
 	public static function admin_menu() {
		$page = add_options_page('Evaluate Options', 'Evaluate', 'manage_options', 'evaluate', array( 'Evaluate_Admin', 'page') );
		
		add_action( 'admin_print_styles-' . $page, array( 'Evaluate_Admin', 'admin_styles' ) );
		
		add_action( 'admin_footer-' . $page,  array( 'Evaluate_Admin', 'print_script_admin_add' ) );
	}
 	
 	public static function admin_styles() {
 		wp_enqueue_style( 'evaluate-admin' );
 		
 	}
 	public static function print_script_admin_add() {
    	
		wp_print_scripts( 'evaluate-admin-add' );
    }
 	
	public static function page() { 
	?>
		<div class="wrap">
			<div id="icon-options-general" class="icon32"></div>
				<h2>Evaluation <a class="add-new-h2" href="#add">Add New</a></h2>
				<?php settings_errors(); 
				
				switch( $_GET['do'] ) {
					/*
					case 'add': 
						if( !isset($_GET['settings-updated']) )
							Evaluate_Admin::add_page();
					break;
					*/
					case 'delete': 
						
							Evaluate_Admin::delete_evaluation();
					break;
					
						
					
					default:
						Evaluate_Admin::display_page();
						Evaluate_Admin::add_page();
					break;
				
				}
				 ?>
		</div><!-- /.wrap -->
	  <?php
	}
	
	public static function delete_evaluation() {
		
		// are you allowed to delete it? 
		
		// what do you want to delete?
		
		delete_option( 'evaluate_settings' );
	
	}
	
	public static function display_page() {
	
		$options = get_option( 'evaluate_settings' ); 
		
		if( $options ):
		
		?>
		<table class="widefat">
			<thead>
				<tr>
					<th class="row-title"><?php _e( 'Name' ); ?></th>
					<th><?php _e( 'Type' ); ?></th>
					<th><?php _e( 'Post Type Included' ); ?></th>
				</tr>
			</thead>
			<tbody>
		<?php foreach( $options as $option ): 
		var_dump($option['post_type']);
		?>
		<tr>
			<td class="row-title"><label for="tablecell"><?php echo $option['name']; ?></label></td>
			<td><?php echo $option['type']; ?></td>
			<td><?php 
				if( is_array( $option['post_type'] ) ):
				foreach( $option['post_type'] as $post_type )
					implode(  ', ', $option['post_type']); 
				endif;
			?>
			</td>
		</tr>	
		<?php endforeach;  ?>
			</tbody>
			<tfoot>
				<tr>
					<th class="row-title"><?php _e( 'Name' ); ?></th>
					<th><?php _e( 'Type' ); ?></th>
					<th><?php _e( 'Post Type Included' ); ?></th>
				</tr>
			</tfoot>
		</table>
		<?php 
		
		else: ?>
		<div class="updated settings-error" id="setting-error-settings_updated"> 
<p><strong>You don't have any way for people to evaluate your content yet. Create a new metric.</strong></p></div>
		
		<?php 
		
		endif;
		

	}
	
	public static function add_page() {
		
		?>
		<h3>Add New Evaluation Criteria</h3>
		
		<form method="post" action="options.php" id="add">
			<?php settings_fields('evaluate_settings_group'); ?>
			<?php $options = get_option('evaluate_settings'); ?>
			<table class="form-table">
				<tr valign="top"><th scope="row"><label for="name">Name</label></th>
					<td><input name="evaluate_settings[name]" type="text" value="" class="regular-text" />
					<label><input type="checkbox" name="evaluate_settings[display_name]" value="1" /> display name</label> </td>
				</tr>
				<tr valign="top">
					<th scope="row">Type</th>
					<td>
						<div>
							<label><input type="radio" name="evaluate_settings[type]" value="one-way" class="evaluate-type-selection" /> One way voting</label>
							<div class="hide evaluate-type-shell">
								<div><label><input type="radio" name="evaluate_settings[one-way][]" value="thumb" /> Thumb</label></div>
								<div><label><input type="radio" name="evaluate_settings[one-way][]" value="arrow" /> Arrow</label></div>
								<div><label><input type="radio" name="evaluate_settings[one-way][]" value="heart" /> Heart</label></div>
							</div>
						</div>
						<div>
							<label><input type="radio" name="evaluate_settings[type]" value="two-way" class="evaluate-type-selection" /> Two way voting, Up and Down</label>
							<div class="hide evaluate-type-shell">
								<div><label><input type="radio" name="evaluate_settings[two-way][]" value="thumb" /> Thumbs</label></div>
								<div><label><input type="radio" name="evaluate_settings[two-way][]" value="arrow" /> Arrows</label></div>
							</div>
						</div>
						<div>
							<label><input type="radio" name="evaluate_settings[type]" value="range" class="evaluate-type-selection" /> Range, Star Voting</label>
						</div>
						<div>
							<label><input type="radio" name="evaluate_settings[type]" value="poll" class="evaluate-type-selection" /> Poll</label>
							<div class="hide evaluate-type-shell">
								<label>Question</label><br />
								<input type="text" name="evaluate_settings[poll][question]"  class="regular-text" />
								<ul>
									<?php 
										$j = 0;  while( $j < 5 ):
										?>
									<li>
										<label>name</label><br />
										<input type="text" name="evaluate_settings[poll][name][]"   class="all-options" />
										
										<select name="evaluate_settings[poll][value][]">
										<?php 
										$i = 0; while( $i < 21) { ?>
											<option value="<?php echo $i; ?>"> &nbsp;  <?php echo $i; ?>  &nbsp; </option>
									 		<?php $i++; } ?> 
									 	</select>
									 </li>
									<?php 
									$j++;
									endwhile; ?>
								</ul>
							</div>
						</div>
					</td>
				</tr>
				<tr valign="top" id="evaluation_post_type">
					<th scope="row">Add to post type</th>
					<td>
						<?php $types = get_post_types( array( 'public'=>true ) , 'objects' );
							foreach($types as $type ): ?>
						<div><label><input type="checkbox" name="evaluate_settings[post_type][]" value="<?php echo $type->name; ?>" /> <?php echo  $type->label; ?></label></div>
						<?php endforeach;?>
					</td>
				</tr>
				
				<tr valign="top">
					<th scope="row">Display Options</th>
					<td>
						<div><label><input type="checkbox" name="evaluate_settings[loggedin]" value="1" /> Users have to be logged in to vote.</label></div>
						<div><label><input type="checkbox" name="evaluate_settings[admin_only]" value="1" /> Only Admins can see this criteria.</label></div>
					</td>
				</tr>
				<tr valign="top" id="preview">
					<th scope="row">Preview</th>
					<td>
						<!-- // todo: make preview work -->
					</td>
				</tr>
			</table>
			<?php submit_button(); ?> <a href="?page=evaluate">cancel</a>
		</form>
	
		<?php
	}
	
	public static function sanitize_evaluate_settings( $settings ) {
		// var_dump( $settings );
		
		if( is_array( $settings['post_type'] ) ):
			$post_types = array();
			foreach($settings['post_type'] as $post_type ):
				$post_types[] = $post_type;
			endforeach;
			$settings['post_type'] = $post_types;
		else:
			$setting = 'evaluate_settings';
			$code 	 = 'evaluation_post_type';
			$message = "You didn't select any post type, your evaluation metric will not appear any where.";
			$type 	 = "error";
			add_settings_error( $setting, $code, $message, $type );
		endif;
		$options = get_option( 'evaluate_settings' );
		// todo: make sure that the user entered appropriate stuff in here
		if( $options ):
			$options[] = $settings;
		else:
			$options = array();  
			$options[] = $settings;
		endif;
		return $options;
	}
	
	
	
}