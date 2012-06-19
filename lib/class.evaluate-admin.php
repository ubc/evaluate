<?php 

$evaluation_setting_varification_count = 0;
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
		
		$options = get_option( 'evaluate_mettrics' ); 
	?>
		<div class="wrap">
			<div id="icon-options-general" class="icon32"></div>
				<h2>Evaluation <a class="add-new-h2" href="#add">Add New</a></h2>
				<?php 
				settings_errors(); 
				
				switch( $_GET['do'] ) {
					/*
					case 'add': 
						if( !isset($_GET['settings-updated']) )
							Evaluate_Admin::add_page();
					break;
					*/
					case 'delete': 
						
						if( isset( $_GET['metric'] ) ):
							if( isset( $options[ $_GET['metric'] ] ) ):
								Evaluate_Admin::delete_evaluation( $options[ $_GET['metric'] ] );
							else:
								Evaluate_Admin::disply_error( 'Sorry but this metric doesn\'t exits' );
							endif;
						else:
							Evaluate_Admin::disply_error( 'Sorry but this metric you didn\'t include a metric'  );
						endif;
					break;
					
					case 'edit':
						
						if( isset( $_GET['metric'] ) ):
							if( isset( $options[ $_GET['metric'] ] ) ):
								Evaluate_Admin::edit_metric( $options[ $_GET['metric'] ] );
							else:
								Evaluate_Admin::disply_error( 'Sorry but this metric doesn\'t exits' );
							endif;
						else:
							Evaluate_Admin::disply_error( 'Sorry but this metric you didn\'t include a metric'  );
						endif;
						
					
					break;
					
						
					
					default:
						Evaluate_Admin::display_page( $options );
						Evaluate_Admin::edit_metric();
					break;
				
				}
				 ?>
		</div><!-- /.wrap -->
	  <?php
	}
	
	public static function delete_evaluation( $metric ) {
		
		// are you allowed to delete it? 
		if( $metric )
		// what do you want to delete?
		
		delete_option( 'evaluate_settings' );
		delete_option( 'evaluate_mettrics' );
	
	}
	
	public static function display_page( $options ) {
		
		if( !empty( $options ) ):
		
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
		<?php 
		$i = 0;
		foreach( $options as $id => $option ): 
			$alternate = 'class="alternate"'; $i++;
		?>
		<tr <?php echo ( $i%2 ? $alternate: '' ); ?> >
			<td class="post-title page-title column-title">
				<label for="tablecell"><strong><a href="?page=evaluate&do=edit&metric=<?php echo $id; ?>"><?php echo $option['name']; ?></a></strong></label>
				<div class="row-actions"><span class="edit">
				<a href="?page=evaluate&do=edit&metric=<?php echo $id; ?>">Edit</a> | </span><span class="trash"><a href="?_wpnonce=<?php echo wp_create_nonce( 'delete-'.$id ); ?>&page=evaluate&do=delete&metric=<?php echo $id;  ?>">Delete</a></span>
				</div>
			</td>
			
			<td><?php echo $option['type']; ?></td>
			<td><?php 
			
				if( is_array( $option['post_type'] ) ):
					echo implode(  ', ', $option['post_type']); 
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
		
		else: 
			
			Evaluate_Admin::disply_error("You don't have any way for people to evaluate your content yet. Create a new metric.");
			
		endif;
	
	}
	public static function disply_error( $error ) {
		?>
		<div class="updated settings-error" > 
			<p><strong><?php echo $error; ?></strong></p>
		</div>
		
		<?php
	}
	
	public static function edit_metric( $metric=array() ) {
		var_dump( $metric );
		?>
		<h3>Add New Evaluation Criteria</h3>
		
		<form method="post" action="options.php" id="add">
			<?php settings_fields('evaluate_settings_group'); ?>
			
			<?php $options = get_option( 'evaluate_mettrics' ); ?>
			<table class="form-table">
				<tr valign="top"><th scope="row"><label for="name">Name</label></th>
					<td><input name="evaluate_settings[name]" type="text" value="<?php echo $metric['name']; ?>" class="regular-text" />
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
		global $evaluation_setting_varification_count; 
		$evaluation_setting_varification_count++;
		// var_dump( is_array( $settings['post_type'] ),$settings['post_type']  );
		
		if( empty( $settings['name'] ) ):
			$setting = 'evaluate_settings';
			$code 	 = 'evaluation_name';
			$message = "You didn't type in a name for your evaluation metric.";
			$type 	 = "error";
			add_settings_error( $setting, $code, $message, $type );
		endif;
		// var_dump($settings['post_type']);
		if( !is_array( $settings['post_type'] ) ):
			
			$setting = 'evaluate_settings';
			$code 	 = 'evaluation_post_type';
			$message = "You didn't select any post type, your evaluation metric will not appear any where.";
			$type 	 = "error";
			add_settings_error( $setting, $code, $message, $type );
			
		endif;
		
		if ($evaluation_setting_varification_count < 2):
			$options = get_option( 'evaluate_mettrics' );
			
			
			$id = Evaluate_Admin::get_id( $settings['name'], $options );
			$options[$id] = $settings;
			
			update_option( 'evaluate_mettrics', $options );
		endif;
		// todo: make sure that the user entered appropriate stuff in here
				
		return $settings;
	}
	
	public static function get_id( $title, $options ) {
		$id = sanitize_title_with_dashes( strtolower( $title ) );
		
		if( is_array($options) ):
		
			$counter = 1;
			while( Evaluate_Admin::id_exists( $id, $options ) )
			{
				$id = $id."-".$counter;
				$counter += 1;
			}
		endif;
		return $id; 
		
	}
	public static function id_exists( $new_id, $options ) {
		
		if( is_array($options) ):
			foreach( $options as $id  => $option):
					if( $id == $new_id )
						return true;
			endforeach;
		endif;
		
		return false;

	}
	
	
}