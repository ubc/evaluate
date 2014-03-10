<?php
class Evaluate_Admin {
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'load' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
	}
	
	public static function load() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		
		// Hooks to display meta box in post editor
		add_action( 'load-post.php',     array( __CLASS__, 'meta_box_setup' ) );
		add_action( 'load-post-new.php', array( __CLASS__, 'meta_box_setup' ) );
		add_action( 'save_post',         array( __CLASS__, 'save_post_meta' ), 10, 2 );
		
		add_action( 'wp_ajax_eval_metric_preview',        array( __CLASS__, 'ajax_metric_preview' ) );
		add_action( 'wp_ajax_nopriv_eval_metric_preview', array( __CLASS__, 'ajax_metric_preview' ) );
	}
	
	/**
	 * Displays the admin menu link in wp-admin.
	 */
	public static function admin_menu() {
		add_menu_page( "Evaluate", "Evaluate", 'manage_options', "evaluate", array( __CLASS__, 'page' ), 'dashicons-chart-line', '58.9' );
		add_submenu_page( 'evaluate', 'All Metrics', 'All Metrics', 'manage_options', 'evaluate', array( __CLASS__, 'page' ) );
		add_submenu_page( 'evaluate', 'Add New', 'Add New', 'manage_options', 'evaluate-new', array( __CLASS__, 'metric_form' ) );
	}
	
	/**
	 * Queue the css styles and js scripts.
	 */
	public static function enqueue_scripts() {
		// Needs site-wide unique identifiers for first param
		wp_register_style( 'evaluate', EVAL_DIR_URL.'/css/evaluate.css');
		wp_register_style( 'evaluate-admin', EVAL_DIR_URL.'/css/evaluate-admin.css');
		
		wp_register_script( 'doT', EVAL_DIR_URL.'/js/doT.js', false, false, true );
		wp_register_script( 'evaluate-admin-js', EVAL_DIR_URL.'/js/evaluate-admin.js', array( 'jquery', 'doT' ), false, true );
		
		wp_enqueue_script( 'doT' );
		wp_enqueue_script( 'evaluate-admin-js' );
		wp_enqueue_style( 'evaluate' );
		wp_enqueue_style( 'evaluate-admin' );
	}
	
	/**
	 * This is the 'controller' to display the correct page in the admin view.
	 */
	public static function page() {
		$view = ( isset( $_REQUEST['view'] ) ? $_REQUEST['view'] : false );
		$action = ( isset( $_REQUEST['eval_action'] ) ? $_REQUEST['eval_action'] : false );
		
		switch ( $action ):
		case 'new':
		case 'edit':
			try {
				self::add_metric();
				self::alert( 'Metric saved!', 'updated' );
			} catch ( Exception $e ) {
				self::alert( "Failed to save metric; ".$e->getMessage()."<br />".print_r( $e->getMessage(), TRUE ), 'error' );
				self::metric_form();
				return;
			}
			break;
		case 'delete':
			$metrics_for_deletion = array();
			if ( isset( $_REQUEST['metric_id'] ) ):
				$metrics_for_deletion[] = $_REQUEST['metric_id'];
			elseif ( isset( $_REQUEST['metric'] ) ):
				$metrics_for_deletion = $_REQUEST['metric'];
			endif;
			
			foreach ( $metrics_for_deletion as $metric_for_deletion ):
				try {
					self::delete_metric( $metric_for_deletion );
					self::alert( "Metric deleted.", 'updated' );
				} catch ( Exception $e ) {
					self::alert( "Failed to delete metric; ".$e->getMessage(), 'error' );
				}
			endforeach;
			break;
		endswitch;
	  
		switch ( $view ):
		case 'form':
			self::metric_form();
			break;
		case 'metric':
			try {
				self::details_table();
			} catch ( Exception $e ) {
				self::alert( "Failed to view metric; ".$e->getMessage(), 'error' );
			}
			break;
		case 'main':
		default:
			self::metrics_table();
			break;
		endswitch;
	}
	
	/**
	 * Outputs main metrics list table
	 */
	public static function metrics_table() {
		?>
		<div class="wrap">
			<div id="icon-generic" class="icon32"></div>
			<h2>Evaluate <a class="add-new-h2" href="<?php echo esc_url( admin_url('admin.php?page=evaluate-new') );?>">Add New</a></h2>
			<h3>
				All Metrics 
			</h3>
		
		<?php
		$metrics_table = new Evaluate_Metrics_List_Table();
		$metrics_table->render();
		?>
		</div>
		<?php
	}
	
	/**
	 * Outputs the metric details table depending on selection.
	 */
	public static function details_table() {
		global $wpdb;
		$metric_id = ( isset( $_GET['metric_id'] ) ? $_GET['metric_id'] : false );
		
		if ( ! $metric_id ):
			throw new Exception( "You haven't supplied a metric!" );
		endif;
		
		$section = ( isset( $_GET['section'] ) ? $_GET['section'] : 'content' );
		$content_is_active = true;
		switch ( $section ):
		case 'user':
			$content_is_active = false;
			$details_table = new Evaluate_Users_List_Table();
			break;
		case 'content':
		default:
			$content_is_active = true;
			$details_table = new Evaluate_Content_List_Table();
			break;
		endswitch;
		
		$metric_data = Evaluate::get_data_by_id( $metric_id, 0 );
		?>
		<div id="metric-details-page">
			<div class="wrap">
				<div id="icon-generic" class="icon32"></div>
				<h2>
					Metric Details <a href="admin.php?page=evaluate&view=form&metric_id=<?php echo $metric_id; ?>" class="add-new-h2" title="Edit Metric">Edit</a>
				</h2>
			</div>
			<div class="postbox metric-details">
				<table class="metric-details-inner">
					<tbody>
						<tr>
							<td><strong>Metric ID:</strong> </td>
							<td><?php echo $metric_data->metric_id; ?></td>
						</tr>
						<tr>
							<td><strong>Display Name:</strong> </td>
							<td><?php echo wp_unslash( $metric_data->display_name ); ?></td>
						</tr>
						<tr>
							<td><strong>Total Votes:</strong> </td>
							<td><?php echo $metric_data->total_votes; ?></td>
						</tr>
						<?php self::print_metric_details( $metric_data ); ?>
					</tbody>
				</table>
			</div>
			<h3 class="nav-tab-wrapper">
				<?php
					$sections = array(
						'content' => "Content",
						'user'    => "Votes",
					);
					foreach ( $sections as $slug => $title ):
						$active = ( $section == $slug ? 'nav-tab-active' : '' );
						$url = "?page=evaluate&view=metric&metric_id=$metric_id&section=$slug";
						?>
						<a class="nav-tab <?php echo $active; ?>" href="<?php echo $url; ?>"><?php echo $title; ?></a>
						<?php
					endforeach;
				?>
			</h3>
			<?php $details_table->render(); ?>
		</div>
		<?php
	}
	
	public static function print_metric_details( $metric_data ) {
		switch ( $metric_data->type ):
		case 'one-way':
			break;
		case 'two-way':
			?>
			<tr>
				<td><strong>Positive Votes:</strong> </td>
				<td><?php echo $metric_data->counter_up; ?></td>
			</tr>
			<tr>
				<td><strong>Negative Votes:</strong> </td>
				<td><?php echo $metric_data->counter_down; ?></td>
			</tr>
			<?php
			break;
		case 'range':
			?>
			<tr>
				<td><strong>Average:</strong> </td>
				<td><?php echo round( $metric_data->average / $metric_data->length * 100, 1 )."%"; ?></td>
			</tr>
			<?php
			break;
		case 'poll':
			?>
			<tr>
				<td><strong>Vote Spread:</strong> </td>
			</tr>
			<?php foreach( $metric_data->answers as $index => $answer ): ?>
			<tr>
				<td><?php echo wp_unslash( $answer ); ?>: </td>
				<td><?php echo  $metric_data->answer_votes[$index]; ?> (<?php echo $metric_data->averages[$index]; ?>%)</td>
			</tr>
			<?php endforeach;
			break;
		default:
			break;
		endswitch;
	}
	
	/**
	 * Returns a div containing the message and styled according to the $type
	 * used for displaying feedback to the user
	 * $type can be 'error' or 'updated' or any other css class
	 */
	public static function alert( $message, $type ) {
		?>
		<div class="<?php echo $type; ?>"><p><?php echo $message; ?></p></div>
		<?php
	}
	
	/**
	 * Add or update metric after form entry
	 */
	public static function add_metric() {
		global $wpdb;
		
		// Try to get form data from the request
		$formdata = ( isset( $_REQUEST['evaluate_form'] ) ? $_REQUEST['evaluate_form'] : false );
		if ( ! $formdata ):
			throw new Exception( 'No form data found!' );
		endif;
		
		// Verify nonce
		if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'evaluate-'.$_REQUEST['eval_action'] ) ):
			throw new Exception( 'Nonce check failed!' );
		endif;
	  
		$is_update = isset( $_REQUEST['metric_id'] );
		// Get current record if this is an update
		if ( $is_update ):
			$metric_id = $_REQUEST['metric_id'];
			$current_data = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM '.EVAL_DB_METRICS.' WHERE id=%s', $metric_id ) );
		endif;
	  
		$metric = array(); //to hold the data
		// Name
		if ( ! $formdata['name'] ):
			throw new Exception( "You must enter a name." );
		endif;
		
		$metric['nicename'] = $formdata['name'];
		$wpdb->escape( $metric['nicename'] );
		$metric['slug'] = sanitize_title( $metric['nicename'] );
		
		// Check if name is unique
		if ( $is_update ):
			$check_name_query = $wpdb->prepare( 'SELECT COUNT(*) FROM '.EVAL_DB_METRICS.' WHERE slug=%s AND id<>%s', $metric['slug'], $current_data->id );
		else:
			$check_name_query = $wpdb->prepare( 'SELECT COUNT(*) FROM '.EVAL_DB_METRICS.' WHERE slug=%s', $metric['slug'] );
		endif;
		
		$count = $wpdb->get_var($check_name_query);
		if ( $count > 0 ):
			throw new Exception( 'This metric name already exists.' );
		endif;
	  
		$metric['display_name'] = isset( $formdata['display_name'] );
		
		// Type
		if ( ! isset( $formdata['type'] ) ):
		  throw new Exception( 'You must choose a type!' );
		endif;
		$metric['type'] = $formdata['type'];
		$wpdb->escape($metric['type']);
		
		// Style
		if ( $metric['type'] == 'range' ):
			$metric['style'] = 'star';
		elseif ( $metric['type'] == 'poll' ):
			$metric['style'] = 'poll';
		else:
			if ( ! isset( $formdata['style'] )):
				throw new Exception('You must choose a style!');
			else:
				$metric['style'] = $formdata['style'];
			endif;
		endif;
		$wpdb->escape($metric['style']);
		
		// Booleans
		$metric['require_login'] = isset( $formdata['require_login'] ) && $formdata['require_login'];
		$metric['admin_only'] = isset( $formdata['admin_only'] ) && $formdata['admin_only'];
		$metric['excerpt'] = isset( $formdata['excerpt'] ) && $formdata['excerpt'];
		
		// Params
		$metric['params'] = array();
		
		// Type params
		$metric['params'][$metric['type']] = $formdata[$metric['type']];
		
		// Poll params
		// Firstly get rid of any answer fields that are empty
		if ( $formdata['poll']['answer'] != null ):
			$poll_answers = array_filter( $formdata['poll']['answer'] );
			if ( $metric['type'] == 'poll' ):
				if ( ! $formdata['poll']['question'] ):
					throw new Exception('You must provide a question for the poll.');
				endif;
				
				if ( count( $poll_answers ) < 2 ):
					throw new Exception('You must provide at least 2 answers for the poll.');
				endif;
				
				$metric['params']['poll']['question'] = $formdata['poll']['question']; //question
				$metric['params']['poll']['answer'] = $poll_answers; //filtered answers
			endif;
		endif;
		
		// Content types
		$content_types = get_post_types( array( 'public' => true ) );
		foreach ( $content_types as $content_type ):
			if ( isset( $formdata['content_type'][$content_type] ) ):
				$metric['params']['content_types'][] = $content_type;
			endif;
		endforeach;
		
		// Serialize params
		$metric['params'] = serialize( $metric['params'] );
		
		// Created and modified timestamps
		if ( ! $is_update ):
			$metric['created'] = date( 'Y/m/d H:i:s' );
		endif;
		$metric['modified'] = date( 'Y/m/d H:i:s' );
		
		// Attempt to save
		if ( $is_update ):
			$num_votes = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM '.EVAL_DB_VOTES.' WHERE metric_id=%s', $current_data->id ) );
			if ( $num_votes > 0 && $formdata['type'] != $current_data->type ):
				throw new Exception( "You cannot change the type of this metric because there are votes registered." );
			endif;
			
			if ( $wpdb->update( EVAL_DB_METRICS, $metric, array( 'id' => $current_data->id ) ) ):
				return true;
			else:
				throw new Exception( $wpdb->print_error() );
			endif;
		else:
			if ( $wpdb->insert( EVAL_DB_METRICS, $metric ) ): //attempt to insert into DB
				return true;
			else:
				throw new Exception( $wpdb->print_error() );
			endif;
		endif;
	}
	
	/**
	 * Deletes metric if found & valid.
	 */
	public static function delete_metric( $metric_id ) {
		global $wpdb;
		
		if ( ! $metric_id):
			throw new Exception( 'You have not specified a metric to delete.' );
		endif;
		
		$nonce = ( isset( $_REQUEST['_wpnonce'] ) ? $_REQUEST['_wpnonce'] : false );
		
		if ( ! $nonce || ( ! wp_verify_nonce( $nonce, "evaluate-delete-$metric_id" ) && ! wp_verify_nonce( $nonce, 'bulk-metrics' ) ) ):
			throw new Exception( 'Nonce check failed. Did you mean to visit this page?' );
		endif;
		
		// Delete the metric itself
		$result = $wpdb->query( $wpdb->prepare( 'DELETE FROM '.EVAL_DB_METRICS.' WHERE id=%s', $metric_id ) );
	  
		if ( $result === FALSE ): //identity check because $wpdb->query can also return 0 which casts to FALSE on == comparison
			throw new Exception( 'Database error during delete operation.');
		elseif ( $result == 0 ):
			throw new Exception( 'Database unchanged after delete operation (metric already deleted?).' );
		endif;
	  
		// Delete its votes
		$result = $wpdb->query( $wpdb->prepare( 'DELETE FROM '.EVAL_DB_VOTES.' WHERE metric_id=%s', $metric_id ) );
	  
		if ( $result === FALSE ): //identity check because $wpdb->query can also return 0 which casts to FALSE on == comparison
			throw new Exception( 'Database error during delete operation.' );
		endif;
	  
		return true;
	}
	
	/**
	 * Display a metric to be used on the new/edit metric page.
	 */
	public static function ajax_metric_preview() {
		$metric = new stdClass();
		$metric->nicename 		= $_REQUEST['evaluate_form']['name'];
		$metric->display_name 	= $_REQUEST['evaluate_form']['display_name'];
		$metric->type 			= $_REQUEST['evaluate_form']['type'];
		$metric->style 			= ( $metric->type == 'poll' ? 'poll' : $_REQUEST['evaluate_form']['style'] );
		$metric->params 		= serialize( array( $metric->type => $_REQUEST['evaluate_form'][$metric->type] ) );
		$metric->preview 		= TRUE;
		
		echo Evaluate::display_metric( Evaluate::get_metric_data( $metric ) );
		die();
	}
	
	/**
	 * Form for adding new metrics or editing existing ones.
	 */
	public static function metric_form() {
		$metric_id = ( isset( $_REQUEST['metric_id'] ) ? $_REQUEST['metric_id'] : null );
		$content_types = get_post_types( array( 'public' => true ) );
		
		if ( isset( $metric_id ) ):
			global $wpdb;
			$metric = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM '.EVAL_DB_METRICS.' WHERE id=%s', $metric_id ) );
			
			if ( ! $metric ):
				throw new Exception( "The metric you are trying to edit does not exist!" );
			endif;
			
			$formdata['metric_id']     = $metric->id;
			$formdata['name']          = wp_unslash( $metric->nicename );
			$formdata['display_name']  = wp_unslash( $metric->display_name) ;
			$formdata['type']          = $metric->type;
			$formdata['style']         = $metric->style;
			$formdata['admin_only']    = $metric->admin_only;
			$formdata['require_login'] = $metric->require_login;
			$formdata['excerpt']       = $metric->excerpt;
			$formdata['action']        = 'edit';
			$formdata['view']          = 'main';
			
			$params = wp_unslash( unserialize( $metric->params ) );
			
			if ( isset( $params['content_types'] ) ):
				foreach ($params['content_types'] as $content_type):
					$formdata['content_type'][$content_type] = true;
				endforeach;
			endif;
			
			$formdata[$formdata['type']] = ( isset( $params[$formdata['type']] ) ? $params[$formdata['type']] : null );
		else:
			if ( isset( $_POST['evaluate_form'] ) ):
				$postdata = $_POST['evaluate_form'];
				$type = $formdata['type'];
				$formdata['name']          = ( isset( $postdata['name'] )          ? $postdata['name']          : null );
				$formdata['display_name']  = ( isset( $postdata['display_name'] )  ? $postdata['display_name']  : null );
				$formdata['type']          = ( isset( $postdata['type'] )          ? $postdata['type']          : null );
				$formdata['style']         = ( isset( $postdata['style'] )         ? $postdata['style']         : null );
				$formdata[$type]           = ( isset( $postdata[$type] )           ? $postdata[$type]           : null );
				$formdata['admin_only']    = ( isset( $postdata['admin_only'] )    ? $postdata['admin_only']    : null );
				$formdata['require_login'] = ( isset( $postdata['require_login'] ) ? $postdata['require_login'] : null );
				$formdata['excerpt']       = ( isset( $postdata['excerpt'] )       ? $postdata['excerpt']       : null );
				$formdata['action']        = 'edit';
				$formdata['view']          = 'main';
				
				if ( isset( $postdata['content_type'] ) ):
					foreach ( $postdata['content_type'] as $content_type => $bool ):
						$formdata['content_type'][$content_type] = true;
					endforeach;
				endif;
			else:
				$formdata['metric_id'] = null;
				$formdata['name'] = null;
				$formdata['display_name'] = null;
				$formdata['type'] = null;
				$formdata['style'] = null;
				$formdata['poll'] = null;
				$formdata['admin_only'] = null;
				$formdata['require_login'] = null;
				$formdata['excerpt'] = null;
				$formdata['action'] = 'new';
				$formdata['view'] = 'main';
				
				foreach ( $content_types as $content_type ):
					$formdata['content_type'][$content_type] = true;
				endforeach;
			endif;
		endif;
		
		$editing = $metric_id != null;
		$html_title = ( $editing ? "Edit Metric" : "Add New Metric" );
		
		if ( $editing ):
			$num_votes = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM '.EVAL_DB_VOTES.' WHERE metric_id=%s', $metric_id ) );
			$no_type_change = $num_votes > 0;
		else:
			$no_type_change = FALSE;
		endif;
		?>
		<div class="wrap">
			<div id="icon-generic" class="icon32"></div>
			<h2>
				<?php echo $html_title; ?>
			</h2>
		</div>
		<form method="post" action="?page=evaluate&view=form" id="metric_form">
			<table class="form-table">
				<tr>
					<th><label for="evaluate_form[name]">Name</label></th>
					<td>
						<input name="evaluate_form[name]" type="text" class="regular-text preview-trigger" value="<?php echo $formdata['name']; ?>" /><br/>
						<label><input type="checkbox" name="evaluate_form[display_name]" value="true" <?php checked( $formdata['display_name'] ); ?> /> Display metric name above evaluation</label>
					</td>
				</tr>
				
				<tr>
					<th>Metric Type</th>
					<td>
						<?php if ( $no_type_change ): ?>
							<div class="error"> <!-- no type change -->
								<p>You cannot change the type of this metric because there are votes registered.</p>
							</div>
						<?php endif; ?>
						<ul class="type_options">
							<?php
								$selected = $formdata['type'] == 'one-way';
							?>
							<?php if ( ! $no_type_change || $selected ): ?>
							<li class="options-one-way">
								<label class="type_label">
									<input type="radio" name="evaluate_form[type]" value="one-way" <?php checked( $selected ); ?> <?php hidden( $no_type_change ); ?> />
									One-way Voting
								</label>
								<div class="context-options indent">
									<ul> <!-- one way style -->
										<?php $styles = array( "thumb", "vote", "heart", "star", "bookmark" ); ?>
										<?php foreach ( $styles as $style ): ?>
											<li>
												<label>
													<input type="radio" name="evaluate_form[style]" value="<?php echo $style; ?>" <?php checked( $selected && $formdata['style'] == $style ); ?> />
													<?php if( "bookmark" != $style) { ?>0 <?php } ?> <a class="rate <?php echo $style; ?>" title="<?php echo Evaluate::$titles[$style]['up']; ?>"><?php echo Evaluate::$titles[$style]['up']; ?></a>
												</label>
											</li>
										<?php endforeach; ?>
									</ul>
									<label>
										Title
										<br />
										<input type="text" name="evaluate_form[one-way][title]" value="<?php echo $formdata['one-way']['title']; ?>" />
										<br />
										<small>The text to display next to this metric. Leave blank to use the default.</small>
									</label>
								</div>
							</li>
							<?php endif; ?>
							
							<?php
								$selected = $formdata['type'] == 'two-way';
							?>
							<?php if ( ! $no_type_change || $selected ): ?>
							<li class="options-two-way">
								<label class="type_label">
									<input type="radio" name="evaluate_form[type]" value="two-way" <?php checked( $selected ); ?> <?php hidden( $no_type_change ); ?> />
									Two-way Voting
								</label>
								<div class="context-options indent">
									<ul> <!-- two way style -->
										<?php $styles = array( "thumb", "vote" ); ?>
										<?php foreach ( $styles as $style ): ?>
											<li>
												<label>
													<input type="radio" name="evaluate_form[style]" value="<?php echo $style; ?>" <?php checked( $selected && $formdata['style'] == $style ); ?>/>
													0 <a class="rate <?php echo $style; ?>" title="<?php echo Evaluate::$titles[$style]['up']; ?>">&nbsp;</a>
													0 <a class="rate <?php echo $style; ?>-down" title="<?php echo Evaluate::$titles[$style]['down']; ?>">&nbsp;</a>
												</label>
											</li>
										<?php endforeach; ?>
									</ul>
									<label>
										Title Up/Down
										<br />
										<input type="text" name="evaluate_form[two-way][title_up]" value="<?php echo $formdata['two-way']['title_up']; ?>" />
										<input type="text" name="evaluate_form[two-way][title_down]" value="<?php echo $formdata['two-way']['title_down']; ?>" />
										<br />
										<small>The text to display when the user hover's over the voting buttons. Leave blank to use the defaults.</small>
									</label>
								</div>         
							</li>
							<?php endif; ?>
							
							<?php
								$selected = $formdata['type'] == 'range';
							?>
							<?php if ( ! $no_type_change || $selected ): ?>
							<li class="options-range">
								<label class="type_label">
									<input type="radio" name="evaluate_form[type]" value="range" <?php checked( $formdata['type'] == 'range' ); ?> <?php hidden( $no_type_change ); ?> />
									Range
								</label>
								<div class="context-options indent">
									<ul> <!-- range style selection -->
										<?php for ( $i = 1; $i <= 5; $i++ ): ?>
											<a class="rate star"></a>
										<?php endfor; ?>
										<!-- This code would add options for other styles of ranges. However those styles are not currently implemented.
										<?php $styles = array( "star", "thumb", "heart" ); ?>
										<?php foreach ( $styles as $style ): ?>
											<li>
												<label>
													<input type="radio" name="evaluate_form[style]" value="<?php echo $style; ?>" <?php checked( $selected && $formdata['style'] == $style ); ?>/>
													<?php for ( $i = 1; $i <= 5; $i++ ): ?>
														<a class="rate <?php echo $style; ?>"></a>
													<?php endfor; ?>
												</label>
											</li>
										<?php endforeach; ?>
										-->
									</ul>
									<label>
										Length
										<br />
										<?php
											if ( $no_type_change ):
												$title = 'title="Cannot be changed because there are votes registered."';
											endif;
										?>
										<input type="number" name="evaluate_form[range][length]" type="number" min="3" max="10" value="<?php echo ( empty( $formdata['range']['length'] ) ? 5 : $formdata['range']['length'] ); ?>" <?php echo $title; ?> <?php readonly( $no_type_change ); ?>/>
										<br />
										<small>The number of stars in this range.</small>
									</label>
									<br />
									<label>
										<input type="checkbox" name="evaluate_form[range][percentage]" <?php checked( $formdata['range']['percentage'] == "on" ); ?> />
										 Display average as percentage.
									</label>
								</div>
							</li>
							<?php endif; ?>
							
							<?php
								$selected = $formdata['type'] == 'poll';
							?>
							<?php if ( ! $no_type_change || $selected ): ?>
							<li class="options-poll">
								<label class="type_label">
									<input type="radio" name="evaluate_form[type]" value="poll" <?php checked( $selected ); ?> <?php hidden( $no_type_change ); ?> />
									Poll
								</label>
								<div class="context-options indent">
									<p class="">
										<a href="javascript:Evaluate_Admin.addNewAnswer()" class="button" title="Add New Answer"> Add New Answer</a>
										
									</p>
									<label>Question: <input type="text" class="regular-text" name="evaluate_form[poll][question]" value="<?php echo esc_attr( $formdata['poll']['question'] ); ?>" /></label>
									<label>Answer 1: <input type="text" class="regular-text" name="evaluate_form[poll][answer][1]" value="<?php echo esc_attr( $formdata['poll']['answer'][1] ); ?>" /></label>
									<label>Answer 2: <input type="text" class="regular-text" name="evaluate_form[poll][answer][2]" value="<?php echo esc_attr( $formdata['poll']['answer'][2] ); ?>" /></label>
									<?php
									if ( count( $formdata['poll']['answer'] ) > 2 ):
										for ( $i = 3; $i <= count( $formdata['poll']['answer'] ); $i++ ):
											?>
											<label>
												Answer <?php echo $i; ?>:
												<input type="text" class="regular-text" name="evaluate_form[poll][answer][<?php echo $i; ?>]" value="<?php echo esc_attr( $formdata['poll']['answer'][$i] ); ?>" />
											</label>
											<?php
										endfor;
									endif;
									?>
									<a href="javascript:Evaluate_Admin.removeLastAnswer()" class="button remove-last" title="Remove Last Answer">Remove Last Answer</a>
									<label>
										<input type="checkbox" name="evaluate_form[poll][hide_results]" <?php checked( $formdata['poll']['hide_results'] == "on" ); ?> />
										 Hide results before voting.
									</label>
									<label>
										<input type="checkbox" name="evaluate_form[poll][display_warning]" <?php checked( ! $editing || $formdata['poll']['display_warning'] == "on" ); ?> />
										 Display a warning if the user hasn't voted. This option has no effect if results are hidden before voting.
									</label>
								</div>
							</li>
							<?php endif; ?>
						</ul>
					</td>
				</tr>
				<tr class="metric-preview">
					<th>Preview</th>
					<td>
						<div id="metric_preview"></div>
					</td>
				</tr>
				<tr>
					<th>Content Types</th>
					<td class="">
						<p>Select the Content Type where you want the metric to appear by default</p>
						<?php foreach ( $content_types as $content_type ): ?>
							<div>
								<label>
									<input type="checkbox" name="evaluate_form[content_type][<?php echo $content_type; ?>]" value="true" <?php checked( isset( $formdata['content_type'][$content_type] ) ); ?> />
									 <?php echo str_replace("-", " ", ucfirst( $content_type ) ); ?>
								</label>
							</div>
						<?php endforeach; ?>
					</td>
				</tr>
				
				<tr>
					<th>Display Options</th>
					<td>
						<label>
							<input type="checkbox" name="evaluate_form[require_login]" value="true" <?php checked( $formdata['require_login'] ); ?> />
							 Users have to be logged in to vote.
						</label>
						<br />
						<label>
							<input type="checkbox" name="evaluate_form[admin_only]" value="true" <?php checked( $formdata['admin_only'] ); ?> />
							 Only Admins can see this metric.
						</label>
						<br />
						<label>
							<input type="checkbox" name="evaluate_form[excerpt]" value="true" <?php checked( $formdata['excerpt'] ); ?> />
							 Show this metric on excerpts
						</label>
					</td>
				</tr>
				
				
			</table>
			<input type="hidden" name="view" value="<?php echo $formdata['view']; ?>" />
			<input type="hidden" name="eval_action" value="<?php echo $formdata['action']; ?>" />
			<input type="hidden" name="action" value="eval_metric_preview" /> <!-- For Ajax -->
			<?php
			if ( isset( $formdata['metric_id'] ) ):
				?>
				<input type="hidden" name="metric_id" value="<?php echo $formdata['metric_id']; ?>" />
				<?php
			endif;
			
			wp_nonce_field( 'evaluate-'.$formdata['action'] );
			submit_button();
			?>
		</form>
		<?php
	}
	
	/** Callback function for setting up the meta box for metric selection in admin area */
	public static function meta_box_setup() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box_add' ) );
	}
	
	/** Callback to construct the meta box in post edit pages */
	public static function meta_box_add() {
		// We need one for every type of post we want the metabox to appear in
		$post_types = get_post_types( array( 'public' => true ) );
		foreach ( $post_types as $post_type ):
			add_meta_box( 'evaluate-post-meta', __( 'Evaluate', 'Metrics' ), array( __CLASS__, 'evaluate_meta_box' ), $post_type, 'side', 'default' );
		endforeach;
	}
	
	/** Callback to construct contents of the meta box */
	public static function evaluate_meta_box( $object, $box ) {
		?>
		<p>Check any available metric to <strong>not</strong> display it with this post.</p>
		<?php
		global $wpdb;
	  
		$metrics = $wpdb->get_results( 'SELECT * FROM '.EVAL_DB_METRICS) ;
		$post_type = get_post_type( $object->ID );
		wp_nonce_field( 'evaluate_post-meta', 'evaluate_nonce' );
	  
		$post_meta = get_post_meta( $object->ID, 'metric' );
		foreach ( $metrics as $metric ): // Shift through metrics and try to find ones that match the current $post_type
			$params = unserialize( $metric->params );
			if ( isset( $params['content_types'] ) ):
				foreach ( $params['content_types'] as $content_type ):
					if ( $content_type == $post_type ):
						?>
						<div>
							<input type="hidden" name="evaluate_cb[<?php echo $metric->id; ?>]" value="0" />
							<label>
								<input type="checkbox" name="evaluate_cb[<?php echo $metric->id; ?>]" <?php checked( in_array( $metric->id, $post_meta ) ); ?> />
								<?php echo $metric->nicename.' ('.$metric->type.', '.$metric->style.')'; ?>
							</label>
						</div>
						<?php
					endif;
				endforeach;
			endif;
		endforeach;
	}
	
	/** Handle saving the post meta after any add/edit action to posts */
	public static function save_post_meta( $post_id, $post_object ) {
		global $meta_box, $wpdb;
		
		// Check autosave
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ):
			return;
		endif;
	  
		// Validate nonce - also check for pulse
		if ( ! isset( $_POST['evaluate_nonce'] ) || ( ! wp_verify_nonce( $_POST['evaluate_nonce'], 'evaluate_post-meta' ) && ! wp_verify_nonce( $_POST['evaluate_nonce'], 'evaluate_pulse-meta' ) ) ):
			return $post_id;
		endif;
	  
		// Check user permissions
		if ( isset( $_REQUEST['post_type'] ) && $_REQUEST['post_type'] == 'page' ):
			if ( ! current_user_can( 'edit_page', $post_id ) ):
				return $post_id;
			endif;
		elseif ( ! current_user_can( 'edit_post', $post_id ) ):
			return $post_id;
		endif;
		
		// We're only interested in the parent post
		if ( $post_object->post_type == 'revision' ):
			return;
		endif;
		
		$post_meta = get_post_meta( $post_id, 'metric' );
	  
		// Pulses are handled differently because of the custom form
		$pulse_metrics = array();
	  
		if ( wp_verify_nonce( $_POST['evaluate_nonce'], 'evaluate_pulse-meta' ) ):
			$metrics = $wpdb->get_results( 'SELECT * FROM '.EVAL_DB_METRICS );
			foreach ( $metrics as $metric ): // Shift through metrics and try to find ones that match the current $post_type
				$params = unserialize( $metric->params );
				if ( isset( $params['content_types'] ) ):
					foreach ( $params['content_types'] as $content_type):
						if ( $content_type == 'pulse-cpt'):
							$pulse_metrics[$metric->id] = false;
						endif;
					endforeach;
				endif;
			endforeach;
		endif;
		
		$metric_list = ( isset( $_POST['evaluate_cb'] ) ? $_POST['evaluate_cb'] : $pulse_metrics );
		foreach ( $metric_list as $key => $cb ):
			if ( ! $cb ):
				// We want to keep track of total votes and score for the metrics NOT in the list
				$total_votes = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM '.EVAL_DB_VOTES.' WHERE metric_id=%s AND content_id=%s', $key, $post_id ) );
			  
				$score = Evaluate::get_score( $key, $post_id );
				$controversy = Evaluate::get_controversy_score( $metric_id, $content_id );
				delete_post_meta( $post_id, 'metric', $key); // Remove metric from blacklist
				update_post_meta( $post_id, 'metric-'.$key.'-votes', $total_votes);
				update_post_meta( $post_id, 'metric-'.$key.'-score', $score);
				
				if ( $total_votes > 0 ):
					update_post_meta( $post_id, 'metric-'.$key.'-controversy', $controversy);
				endif;
			elseif ( $cb == 'on' && ! in_array( $key, $post_meta ) ):
				add_post_meta( $post_id, 'metric', $key ); // Add metric to blacklist
				
				// Remove unneeded metadata
				delete_post_meta( $post_id, 'metric-'.$key.'-votes' );
				delete_post_meta( $post_id, 'metric-'.$key.'-score' );
				delete_post_meta( $post_id, 'metric-'.$key.'-controversy' );
			endif;
		endforeach;
		
		return true;
	}
}

function hidden( $hidden, $current = true, $echo = true ) {
	return __checked_selected_helper( $hidden, $current, $echo, 'hidden' );
}

function readonly( $readonly, $current = true, $echo = true ) {
	return __checked_selected_helper( $readonly, $current, $echo, 'readonly' );
}

add_action( 'init', array( 'Evaluate_Admin', 'init' ) );