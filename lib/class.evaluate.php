<?php
/**
 * Main class that takes care of everything that has to do with metrics, voting, display and setup
 */
class Evaluate {
	static $options = array(); //Plugin options
	static $titles = array( //Titles for vote links
		'thumb' => array(
			'up'   => 'Thumbs Up!',
			'down' => 'Thumbs Down!',
		),
		'vote' => array(
			'up'   => 'Vote Up!',
			'down' => 'Vote Down!',
		),
		'heart' => array(
			'up' => 'Heart!',
		),
		'star'  => array(
			'up' => 'Star!',
		),
		'bookmark'  => array(
			'up' => 'Bookmark',
		),
		'range' => ' Stars'
	);
  
	//******************************************************//
	// Functions related to plugin setup and initialization //
	//******************************************************//
	
	function __construct( $case = false ) {
		if ( ! $case ) {
			wp_die( 'Cannot call this class directly!', 'Error!' );
		}
	}
  
	/** First thing that runs before any code */
	public static function init() {
		self::$options['EVAL_DB_METRICS_VER'] = get_option('EVAL_DB_METRICS_VER');
		self::$options['EVAL_DB_VOTES_VER'] = get_option('EVAL_DB_VOTES_VER');
		
		// Check to see if we have the required tables created, if not, create them
		if ( self::$options['EVAL_DB_METRICS_VER'] < EVAL_DB_METRICS_VER || self::$options['EVAL_DB_VOTES_VER'] < EVAL_DB_VOTES_VER ):
			self::activate();
		endif;
		
		// Check if CTLT_Stream plugin exists to use with node
		if ( ! function_exists( 'is_plugin_active' ) ):
			// Include plugins.php to check for other plugins from the frontend
			include_once( ABSPATH.'wp-admin/includes/plugin.php' );
		endif;
		
		self::$options['EVAL_STREAM'] =  class_exists('CTLT_Stream') && is_plugin_active( 'stream/stream.php' );
		
		if ( self::$options['EVAL_STREAM'] ):
			CTLT_Stream::$add_script = true;
		endif;
		
		// js and css script hook
		add_action( 'wp_enqueue_scripts',           array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'wp_footer',                    array( __CLASS__, 'print_templates' ) ); // Prints out metric templates for doT
		add_filter( 'the_content',                  array( __CLASS__, 'append_metrics' ) ); // Filter for displaying metrics below content
		add_filter( 'the_excerpt',                  array( __CLASS__, 'prepend_metrics' ) ); // Filter for displaying metrics below content
		add_action( 'wp_ajax_evaluate-vote',        array( __CLASS__, 'ajax_handler' ) );
		add_action( 'wp_ajax_nopriv_evaluate-vote', array( __CLASS__, 'ajax_handler' ) );
		
		self::set_cookie();
		// Handle any evaluate event that occurs
		if ( isset( $_REQUEST['evaluate'] ) ):
			self::event_handler();
		endif;
		
		
		if ( get_option( 'EVAL_DB_VOTES_VER' ) < 1.2 ):
			dbDelta( "ALTER TABLE ".EVAL_DB_VOTES." ADD excerpt tinyint(1) NOT NULL DEFAULT '0'" );
		endif;
		
		update_option( 'EVAL_DB_METRICS_VER', EVAL_DB_METRICS_VER );
		update_option( 'EVAL_DB_VOTES_VER', EVAL_DB_VOTES_VER );
	}
  
	/**
	 * Create tables required for functioning upon activation this should run only once.
	 */
	public static function activate() {
		global $wpdb;
		require_once( ABSPATH.'wp-admin/includes/upgrade.php' );
		
		$metrics_table = EVAL_DB_METRICS;
		$sql = "CREATE TABLE $metrics_table (
			id bigint(11) NOT NULL AUTO_INCREMENT,
			slug varchar(64) NOT NULL,
			nicename varchar(64) NOT NULL,
			type varchar(10) NOT NULL DEFAULT 'one-way',
			style varchar(10) NOT NULL DEFAULT 'thumb',
			require_login tinyint(1) NOT NULL DEFAULT '1',
			admin_only tinyint(1) NOT NULL DEFAULT '0',
			display_name tinyint(1) NOT NULL DEFAULT '1',
			excerpt tinyint(1) NOT NULL DEFAULT '0',
			params longtext,
			created datetime,
			modified datetime,
			PRIMARY KEY (id) );";
		
		dbDelta( $sql );
		add_option( 'EVAL_DB_METRICS_VER', EVAL_DB_METRICS_VER );
		
		$votes_table = EVAL_DB_VOTES;
		$sql = "CREATE TABLE $votes_table (
			id bigint(11) NOT NULL AUTO_INCREMENT,
			metric_id bigint(11) NOT NULL,
			content_id bigint(11) NOT NULL,
			user_id varchar(40) NOT NULL,
			vote int(11) NOT NULL,
			disabled tinyint(1) NOT NULL DEFAULT '0',
			comment tinytext NOT NULL,
			date datetime NOT NULL,
			PRIMARY KEY (id) );";
		
		dbDelta( $sql );
		add_option( 'EVAL_DB_VOTES_VER', EVAL_DB_VOTES_VER );
	}
  
	/**
	 * Do nothing in deactivation, we don't want to remove
	 * the tables in the database in case the user re-activates it
	 */
	public static function deactivate() {
		// Do Nothing
	}
  
	/** Remove the database tables created by Eval */
	public static function uninstall() {
		require_once( ABSPATH.'wp-admin/includes/upgrade.php' );
		dbDelta( "DROP TABLE ".EVAL_DB_METRICS );
		dbDelta( "DROP TABLE ".EVAL_DB_VOTES );
		remove_option( 'EVAL_DB_METRICS_VER' );
		remove_option( 'EVAL_DB_VOTES_VER' );
	}
  
	/* Put the scripts and styles needed */
	public static function enqueue_scripts() {
		wp_register_style( 'evaluate', EVAL_DIR_URL.'/css/evaluate.css' );
		wp_enqueue_style( 'evaluate' );
		
		wp_register_script( 'doT', EVAL_DIR_URL.'/js/doT.min.js', false, false, true );
		wp_register_script( 'evaluate-js', EVAL_DIR_URL.'/js/evaluate.js', array( 'jquery', 'doT' ), false, true );
		wp_enqueue_script( 'doT' );
		wp_enqueue_script( 'evaluate-js' );
		
		// WP localize trick to pass params into js without direct printing
		wp_localize_script( 'evaluate-js', 'evaluate_ajax', array(
			'ajaxurl'       => admin_url('admin-ajax.php'),
			'stream_active' => self::$options['EVAL_STREAM'] && CTLT_Stream::is_node_active(),
			'user'          => self::get_user(),
			'frequency'     => Evaluate_Settings::get_ajax_frequency(),
		) );
		
	}
  
	/** Get id of the current user */
	public static function get_user() {
		global $current_user;
		
		if ( is_user_logged_in() && isset( $current_user->ID ) ): // Are we logged in from a legit account?
			return $current_user->ID;
		else:
			return 'anon_'.md5( $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT'] );
		endif;
	}
  
	public static function set_cookie() {
		$cookie_id = 'evaluate_user-'.md5( LOGGED_IN_KEY );
		
		if ( isset( $_COOKIE[$cookie_id] ) ): // Check cookie
			return $_COOKIE[$cookie_id];
		else: // User not logged in, set a cookie to keep track of the guest (for multiple voting)
			$time = time();
			setcookie( $cookie_id, 'u'.$time, $time + 10 * YEAR_IN_SECONDS ); //10 years
			return 'u'.$time;
		endif;
		
		return $_SERVER['REMOTE_ADDR']; // Last resort
	}
  
	//*************************************//
	// Event and request handler functions //
	//*************************************//
	
	/** Clear evaluate arguments from the url, mainly after voting. */
	public static function clear_url() {
		$redirect = remove_query_arg( array( 'evaluate', 'metric_id', 'content_id', 'vote', '_wpnonce' ) );
		wp_redirect( $redirect );
		exit();
	}
  
	/** Handles events requested from anywhere within wp. */
	public static function event_handler() {
		switch ( $_REQUEST['evaluate'] ):
		case 'vote':
			self::vote( $_REQUEST['metric_id'], $_REQUEST['content_id'], $_REQUEST['vote'], $_REQUEST['_wpnonce'], $_REQUEST['comment'] );
			self::clear_url();
			break;
		case 'sort':
			// Hook for changing post query
			add_action( 'pre_get_posts', array( __CLASS__, 'modify_query' ) );
			break;
		endswitch;
	}
  
	/** Handle ajax voting events. */
	public static function ajax_handler() {
		$data = ( isset( $_POST['data'] ) ? $_POST['data'] : false );
		if ( $data ):
			// Handle poll view requests first
			if ( isset( $data['view'] ) ):
				$modified = get_post_meta( $data['content_id'], 'metric-'.$data['metric_id'].'-modified', true );
				if ( $modified != $data['modified'] ):
					echo self::display_metric( self::get_data_by_id( $data['metric_id'], $data['content_id'] ) );
				else:
					echo "false";
				endif;
				die();
			endif;
			
			// Now handle vote requests
			if ( isset( $data['vote'] ) ):
				self::vote( $data['metric_id'], $data['content_id'], $data['vote'], $data['_wpnonce'], $data['comment'] );
			endif;
			die();
		endif;
	}
  
	/** Content hook for main wordpress loop */
	public static function append_metrics( $content ) {
		global $wpdb, $post;
		
		if ( is_singular() ) {
			// Get all metrics, then filter out excluded ones
			$metrics = $wpdb->get_results( 'SELECT * FROM '.EVAL_DB_METRICS );
			$excluded = get_post_meta( $post->ID, 'metric' );
			
			foreach ( $metrics as $index => $metric ):
				$params = unserialize( $metric->params );
				
				if ( array_key_exists( 'content_types', $params )
					&& ! in_array( $metric->id, $excluded )
					&& in_array( $post->post_type, $params['content_types'] ) ): //not excluded
					continue;
				else:
					unset( $metrics[$index] );
				endif;
			endforeach;
			
			if ( ! empty( $metrics ) ):
				ob_start();
				?>
				<div class="evaluate-metrics-wrapper">
					<?php
					foreach ( $metrics as $metric ):
						echo self::display_metric( self::get_metric_data( $metric ) );
					endforeach;
					?>
				</div>
				<?php
				
				$content .= ob_get_clean();
			endif;
		}
		
		return $content;
	}
	
	public static function prepend_metrics( $excerpt ) {
		global $wpdb, $post;
		
		// Get all metrics, then filter out excluded ones
		$metrics = $wpdb->get_results( 'SELECT * FROM '.EVAL_DB_METRICS.' WHERE excerpt = "1"' );
		$excluded = get_post_meta( $post->ID, 'metric' );
		
		foreach ( $metrics as $index => $metric ):
			$params = unserialize( $metric->params );
			
			if ( array_key_exists( 'content_types', $params )
				&& ! in_array( $metric->id, $excluded )
				&& in_array( $post->post_type, $params['content_types'] ) ): //not excluded
				continue;
			else:
				unset( $metrics[$index] );
			endif;
		endforeach;
		
		if ( ! empty( $metrics ) ):
			ob_start();
			?>
			<div class="evaluate-metrics-wrapper">
				<?php
				foreach ( $metrics as $metric ):
					$metric->preview = true;
					$metric->show_user_vote = false;
					
					$data = self::get_metric_data( $metric, false );
					$data->display_name = "";
					$data->average_display = "";
					
					echo self::display_metric( $data );
				endforeach;
				?>
			</div>
			<?php
			$excerpt = ob_get_clean() . $excerpt;
		endif;
		
		return $excerpt;
	}
  
	//**************************//
	// Voting related functions //
	//**************************//
  
	/** Process incoming votes for deletion, update or insert */
	public static function vote( $metric_id, $content_id, $vote, $nonce, $comment = null ) {

		// Sanitize the args
		$metric_id 	= sanitize_text_field( $metric_id );
		$content_id = sanitize_text_field( $content_id );
		$vote 		= sanitize_text_field( $vote );
		
		if ( ! wp_verify_nonce( $nonce, 'evaluate-vote-'.$metric_id.'-'.$content_id.'-'.$vote.'-'.self::get_user() ) ):
			if ( ! wp_verify_nonce( $nonce, 'evaluate-vote-poll-'.$metric_id.'-'.$content_id.'-'.self::get_user() ) ):
				// throw new Exception( "Nonce check failed. Did you mean to do this action?" );
				return false;
			endif;
		endif;

		global $wpdb;
		$user_id = self::get_user();
		
		// Check if vote exists first
		$query = $wpdb->prepare( 'SELECT * FROM ' . EVAL_DB_VOTES . ' WHERE metric_id=%s AND content_id=%s AND user_id=%s', $metric_id, $content_id, $user_id );
		$prev_vote = $wpdb->get_row( $query );
		
		if ( $prev_vote ):
			if ( $vote == $prev_vote->vote && $prev_vote->disabled == 0 ): // Same vote twice constitutes a 'toggle', remove vote
				$query = $wpdb->prepare( 'DELETE FROM ' . EVAL_DB_VOTES . ' WHERE id=%d', $prev_vote->id );
				$result = $wpdb->query( $query );
			else: // Update vote from previous value
				$where = array(
					'id' => $prev_vote->id,
				);
				
				$data = array();
				
				if ( ! empty( $vote ) ):
					$data['vote'] = $vote;
				endif;
				
				$data['disabled'] = 0;
				
				if ( ! empty( $comment ) ):
					$data['comment'] = $comment;
				endif;

				$result = $wpdb->update( EVAL_DB_VOTES, $data, $where );
			endif;
		else: // Add new vote
			$data = array(
				'metric_id'  => $metric_id,
				'content_id' => $content_id,
				'user_id'    => $user_id,
				'vote'       => $vote,
				'disabled'   => 0,
				'comment'    => ( empty( $comment ) ? "" : $comment ),
				'date'       => date('Y-m-d H:i:s'),
			);

			$result = $wpdb->insert( EVAL_DB_VOTES, $data, array( '%d', '%d', '%s', '%d', '%d', '%s', '%s' ) );
		endif;
		
		if ( $result ):
			$metric_data = self::get_data_by_id( $metric_id, $content_id );
			$metric_data->user = self::get_user();

			do_action( 'evaluate_set_metric_vote', $metric_data );

			if ( self::$options['EVAL_STREAM'] && CTLT_Stream::is_node_active() ):
				CTLT_Stream::send( 'evaluate', $metric_data, 'vote' );
			endif;
			
			echo self::display_metric( $metric_data );
		endif;
		
		$total_votes = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM '.EVAL_DB_VOTES.' WHERE metric_id=%s AND content_id=%s', $metric_id, $content_id ) );
		
		$score = Evaluate::get_score( $metric_id, $content_id );
		$controversy = Evaluate::get_controversy_score( $metric_id, $content_id );
		
		update_post_meta( $content_id, 'metric-'.$metric_id.'-modified', time() );
		update_post_meta( $content_id, 'metric-'.$metric_id.'-score', $score );
		update_post_meta( $content_id, 'metric-'.$metric_id.'-votes', $total_votes );
		
		if ( $total_votes > 0 ):
			update_post_meta( $content_id, 'metric-'.$metric_id.'-controversy', $controversy );
		else:
			delete_post_meta( $content_id, 'metric-'.$metric_id.'-controversy' );
		endif;
	}
  
	/* Create a url to vote */
	public static function get_vote_url( $metric, $content_id, $vote, $nonce ) {
		if ( $metric->admin_only && ! current_user_can( 'manage_options' ) ):
			return 'javascript:void(0);';
		endif;
		
		if ( $metric->require_login && ! is_user_logged_in() ):
			return 'wp-login.php?action=register';
		endif;
		
		return '?evaluate=vote&metric_id='.$metric->id.'&content_id='.$content_id.'&vote='.$vote.'&_wpnonce='.$nonce;
	}

	/**
	 * 
	 */
	public static function show_metric_data_stats( $metric_id, $type ) { ?>

			<div class="stats-wrap">
				<div class="third-shell">
				
					<h3>Top 5<br /><small>The content with the highest score.</small></h3>
					<?php 
					
					$top_posts = Evaluate::get_posts( $metric_id ); 
					Evaluate::display_posts( $top_posts ); 
					
					?>
					<h3 class="inner">Bottom 5<br /><small>The content with the lowest score.</small></h3>
					<?php

					$bottom_posts = Evaluate::get_posts( $metric_id, 5, 'ASC' ); 
					Evaluate::display_posts( $bottom_posts );
					
					?>
				</div>
				
				<div class="third-shell">
				<?php 
				$number_of_controversial_posts = 10;
				$class = '';
				if($type != 'one-way'){  ?>
					<h3>Most Votes<br /><small>Most votes with score close to zero</small></h3>
					<?php 
					
					$most_votes_posts = Evaluate::get_posts( $metric_id, 5, 'DESC', 'votes' ); 
					Evaluate::display_posts( $most_votes_posts );
					$number_of_controversial_posts = 5;
					$class = 'class="inner"'; 
				} ?>

					<h3 <?php echo $class; ?>>Most Controversial <br /><small>Most votes with score close to zero</small></h3>
					<?php 
					
					$most_controversial_posts = Evaluate::get_posts( $metric_id, $number_of_controversial_posts, 'DESC', 'controversy' ); 
					Evaluate::display_posts( $most_controversial_posts );
					
					?>
					
				</div>
				<?php /* @todo
				<div class="third-shell third-shell-last">
					<h3>User that have voted the most</h3>

					<ol >
						<li>Posr 1</li>
						<li>Posr 1</li>
						<li>Posr 1</li>
						<li>Posr 1</li>
						<li>Posr 1</li>
						<li>Posr 1</li>
						<li>Posr 1</li>
						<li>Posr 1</li>
						<li>Posr 1</li>
						<li>Posr 1</li>
					</ol>

				</div>
				*/ ?>

			</div>
			
		</div>
		<?php
	}
  
	//********************************************************//
	// Data functions to get all required data about a metric //
	//********************************************************//
	
	public static function get_data_by_slug( $metric_slug, $content_id = 0 ) {
		global $wpdb, $post;
		
		$metric = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM '.EVAL_DB_METRICS.' WHERE slug=%s', $metric_slug ) );
		if ( $metric ):
			// Force specific post data
			$post = get_post( $content_id );
			if ( isset( $post ) ):
				setup_postdata( $post );
			else: // No post id set, avoid warnings
				$post = new stdClass();
				$post->ID = 0;
			endif;
			
			return self::get_metric_data( $metric );
		endif;
	}
	
	public static function get_data_by_id( $metric_id, $content_id, $user_id = null ) {
		global $wpdb, $post;
		
		$metric = self::get_metric_data_by_id( $metric_id );
		if ( $metric ):
			// Force specific post data
			$post = get_post( $content_id );
			if ( isset( $post ) ):
				setup_postdata( $post );
			else: // No post id set, avoid warnings
				$post = new stdClass();
				$post->ID = 0;
			endif;
			
			return self::get_metric_data( $metric, $user_id );
		endif;
	}

	public static function get_metric_data_by_id( $metric_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM '.EVAL_DB_METRICS.' WHERE id=%s', $metric_id ) );
	}
  
	/** Convenience function to handle any metric */
	public static function get_metric_data( $metric, $user_id = null ) {
		global $wpdb, $post;
		
		if ( $user_id === null ):
			$user_id = self::get_user();
		endif;
		
		$data = new stdClass();
		$data->template       = false;
		$data->user           = $user_id;
		$data->metric_id      = $metric->id;
		$data->content_id     = $post->ID;
		$data->display_name   = wp_unslash( ( $metric->display_name ? $metric->nicename : '' ) ); // Check if display name is enabled
		$data->type           = $metric->type;
		$data->admin_only     = $metric->admin_only;
		$data->require_login  = $metric->require_login;
		$data->style          = $metric->style;
		$data->modified       = get_post_meta( $post->ID, 'metric-'.$metric->id.'-modified', true );
		$data->preview        = $metric->preview || ( $data->require_login && ! is_user_logged_in() );
		$data->show_user_vote = isset( $metric->show_user_vote ) ? $metric->show_user_vote : false;
		$data->shell_classes  = "";
		
		switch ( $metric->type ):
		case 'one-way':
			$data = self::one_way_data( $metric, $data );
			break;
		case 'two-way':
			$data = self::two_way_data( $metric, $data );
			break;
		case 'range':
			$data = self::range_data( $metric, $data );
			break;
		case 'poll':
			$data = self::poll_data( $metric, $data );
			break;
		default:
			return null;
		endswitch;
		
		if ( ! empty( $data->link ) ):
			if ( is_array( $data->link ) ):
				$data->href_link = array_map( array( __CLASS__, 'wrap_link' ), $data->link );
			else:
				$data->href_link = 'href="'.$data->link.'"';
			endif;
		endif;
		
		if ( ! empty( $data->link_up ) ):
			$data->href_link_up = 'href="'.$data->link_up.'"';
		endif;
		
		if ( ! empty( $data->link_down ) ):
			$data->href_link_down = 'href="'.$data->link_down.'"';
		endif;
		
		if ( $data->preview == false && $data->show_user_vote == false ):
			$data->onclick = "return Evaluate.onLinkClick(this);";
		endif;
		
		return $data;
	}
	
	private static function wrap_link( $link ) {
		return 'href="'.$link.'"';
	}
	
	public static function get_metric_data_js() {
		$data = new stdClass();
		$data->template = true;
		$data->preview = false;
		$data->admin_only = false;
		$data->show_user_vote = '{{=it.show_user_vote}}';
		
		$data->metric_id = '{{=it.metric_id}}';
		$data->content_id = '{{=it.content_id}}';
		$data->display_name = '{{=it.display_name}}';
		$data->type = '{{=it.type}}';
		$data->require_login = '{{=it.require_login}}';
		$data->style = '{{=it.style}}';
		$data->modified = '{{=it.modified}}';
		$data->user = '{{=it.user}}';
		
		$data->counter = '{{=it.counter}}';
		$data->counter_up = '{{=it.counter_up}}';
		$data->counter_down = '{{=it.counter_down}}';
		$data->counter_total = '{{=it.counter_total}}';
		$data->state = '{{=it.state}}';
		$data->state_up = '{{=it.state_up}}';
		$data->state_down = '{{=it.state_down}}';
		$data->nonce = '{{=it.nonce}}';
		$data->nonce_up = '{{=it.nonce_up}}';
		$data->nonce_down = '{{=it.nonce_down}}';
		$data->link = '{{=it.link}}';
		$data->link_up = '{{=it.link_up}}';
		$data->link_down = '{{=it.link_down}}';
		$data->href_link = '{{=it.href_link}}';
		$data->href_link_up = '{{=it.href_link_up}}';
		$data->href_link_down = '{{=it.href_link_down}}';
		$data->title = '{{=it.title}}';
		$data->title_up = '{{=it.title_up}}';
		$data->title_down = '{{=it.title_down}}';
		
		$data->shell_classes = '{{=it.shell_classes}}';
		$data->user_vote = '{{=it.user_vote}}';
		$data->votes = '{{=it.votes}}';
		$data->total_votes = '{{=it.total_votes}}';
		$data->average = '{{=it.average}}';
		$data->width = '{{=it.width}}';
		$data->length = '{{=it.length}}';
		$data->question = '{{=it.question}}';
		$data->answers = '{{=it.answers}}';
		$data->answer_votes = '{{=it.answer_votes}}';
		$data->hide_results = '{{=it.hide_results}}';
		$data->average_display = '{{=it.average_display}}';
		
		$data->onclick = '{{=it.onclick}}';
		$data->onsubmit = '{{=it.onsubmit}}';
		
		return $data;
	}
  
	public static function one_way_data( $metric, $data ) {
		global $wpdb, $post;
		
		// Get the type parameters
		$params = unserialize( $metric->params );
		$data->title = ( empty( $params['one-way']['title'] ) ? self::$titles[$metric->style]['up'] : $params['one-way']['title'] );
		
		// Tally the votes
		if ( isset( $post->ID ) && $post->ID != 0 ):
			$where_content = $wpdb->prepare( ' AND content_id=%s', $post->ID );
		else:
			$where_content = '';
		endif;
		$query = $wpdb->prepare( 'SELECT COUNT(*) as count FROM '.EVAL_DB_VOTES.' WHERE metric_id=%s'.$where_content.' AND disabled=0 GROUP BY vote', $metric->id, $post->ID );
		$data->counter = $wpdb->get_results( $query );
		$data->counter = $data->counter[0]->count;
		$data->counter = ( $data->counter ? $data->counter : 0 );
		$data->total_votes = print_r( $data->counter, TRUE );
		
		// Get the current user's vote, if it exists
		if ( $data->counter > 0 && ! empty( $data->user ) ):
			$data->user_vote = $wpdb->get_var( $wpdb->prepare( 'SELECT vote FROM '.EVAL_DB_VOTES.' WHERE metric_id=%s AND content_id=%s AND user_id=%s AND disabled=0', $metric->id, $post->ID, $data->user ) );
		else:
			$data->user_vote = false;
		endif;
		
		// Miscelleneous Data
		$data->state = ( $data->user_vote && $data->user_vote == 1 ? ' selected' : '' ); // Set state of the link
		
		if ( $data->preview == false ):
			$data->nonce = wp_create_nonce( 'evaluate-vote-'.$data->metric_id.'-'.$data->content_id.'-1-'.$data->user );
			$data->link = self::get_vote_url( $metric, $post->ID, 1, $data->nonce ); //upvote link
		endif;
		
		return $data;
	}
  
	public static function two_way_data( $metric, $data ) {
		global $wpdb, $post;
		
		// Get the type parameters
		$params = unserialize( $metric->params );
		$data->title_up = ( empty( $params['two-way']['title_up'] ) ? self::$titles[$metric->style]['up'] : $params['two-way']['title_up'] );
		$data->title_down = ( empty( $params['two-way']['title_down'] ) ? self::$titles[$metric->style]['down'] : $params['two-way']['title_down'] );
		
		// Tally the votes
		if ( isset( $post->ID ) && $post->ID != 0 ):
			$where_content = $wpdb->prepare( ' AND content_id=%s', $post->ID );
		else:
			$where_content = '';
		endif;
		
		$query = $wpdb->prepare( 'SELECT vote, COUNT(vote) as count FROM '.EVAL_DB_VOTES.' WHERE metric_id=%s'.$where_content.' AND disabled=0 GROUP BY vote', $metric->id, $post->ID );
		$data->counter = $wpdb->get_results( $query );
		$data->counter_up = 0;
		$data->counter_down = 0;
		foreach ( $data->counter as $vote_group ):
			if ( $vote_group->vote < 0 ):
				$data->counter_down -= $vote_group->vote * $vote_group->count;
			else:
				$data->counter_up += $vote_group->vote * $vote_group->count;
			endif;
		endforeach;
		
		$data->counter_total = $data->counter_up - $data->counter_down;
		$data->total_votes = $data->counter_up + $data->counter_down;
		
		// Get the current user's vote, if it exists
		if ( $data->total_votes > 0 && ! empty( $data->user ) ):
			$data->user_vote = $wpdb->get_var( $wpdb->prepare('SELECT vote FROM '.EVAL_DB_VOTES.' WHERE metric_id=%s AND content_id=%s AND user_id=%s AND disabled=0', $metric->id, $post->ID, $data->user ) );
		else:
			$data->user_vote = false;
		endif;
		
		// Miscelleneous Data
		$data->state_up = ( $data->user_vote && $data->user_vote == 1 ? ' selected' : '' );
		$data->state_down = ( $data->user_vote && $data->user_vote == -1 ? ' selected' : '' );
		
		if ( $data->preview == false ):
			$data->nonce_up = wp_create_nonce( 'evaluate-vote-'.$data->metric_id.'-'.$data->content_id.'-1-'.self::get_user() );
			$data->nonce_down = wp_create_nonce( 'evaluate-vote-'.$data->metric_id.'-'.$data->content_id.'--1-'.self::get_user() );
			$data->link_up = self::get_vote_url( $metric, $post->ID, 1, $data->nonce_up );
			$data->link_down = self::get_vote_url( $metric, $post->ID, -1, $data->nonce_down );
		endif;
		
		return $data;
	}
  
	public static function range_data( $metric, $data ) {
		global $wpdb, $post;
		
		// Get the type parameters
		$params = unserialize( $metric->params );
		$data->length = $params['range']['length'];
		
		// Tally the votes
		if ( isset( $post->ID ) && $post->ID != 0 ):
			$where_content = $wpdb->prepare( ' AND content_id=%s', $post->ID );
		else:
			$where_content = '';
		endif;
		
		$query = $wpdb->prepare( 'SELECT vote, COUNT(vote) as count FROM '.EVAL_DB_VOTES.' WHERE metric_id=%s'.$where_content.' AND disabled=0 GROUP BY vote', $metric->id, $post->ID );
		$data->votes = $wpdb->get_results( $query, OBJECT_K ); //returned array will have vote value for keys
		
		$data->average = 0;
		$total_votes = 0;
		foreach ( $data->votes as $vote ):
			$data->average += $vote->vote * $vote->count;
			$total_votes += $vote->count;
		endforeach;
		
		if ( $total_votes > 0 ):
			$data->average /= $total_votes;
		else:
			$data->average = 0;
		endif;
		
		$data->total_votes = $total_votes;
		$data->average = round( $data->average, 1 );
		
		if ( $params['range']['percentage'] ):
			$data->average_display = round( $data->average / $data->length * 100, 1 )."%";
		else:
			$data->average_display = $data->average."/".$data->length." Stars";
		endif;
		
		// Get the current user's vote, if it exists
		if ( $data->total_votes > 0 && ! empty( $data->user ) ):
			$data->user_vote = $wpdb->get_var( $wpdb->prepare( 'SELECT vote FROM '.EVAL_DB_VOTES.' WHERE metric_id=%s AND content_id=%s AND user_id=%s AND disabled=0', $metric->id, $post->ID, $data->user ) );
		else:
			$data->user_vote = false;
		endif;
		
		// Miscelleneous Data
		$data->state = ( $data->user_vote ? ' selected' : '' );
		if ( $data->length > 0 ):
			$data->width = ( $data->user_vote ? $data->user_vote : $data->average ) / $data->length * 100;
		else:
			$data->width = 0;
		endif;
		
		$data->stars = array();
		for ( $i = 1; $i <= $data->length; $i++ ):
			$data->stars[$i] = $i;
		endfor;
		
		if ( $data->preview == false ):
			$data->nonce = array();
			$data->link = array();
			for ( $i = 1; $i <= $data->length; $i++ ):
				$data->nonce[$i] = wp_create_nonce( 'evaluate-vote-'.$data->metric_id.'-'.$data->content_id.'-'.$i.'-'.self::get_user() );
				$data->link[$i] = self::get_vote_url( $metric, $post->ID, $i, $data->nonce[$i] );
			endfor;
		endif;
		
		return $data;
	}
  
	public static function poll_data( $metric, $data ) {
		global $wpdb, $post;
		
		// Get the type parameters
		$params = unserialize( $metric->params );
		$data->question = $params['poll']['question'];
		$data->answers = $params['poll']['answer'];
		$data->hide_results = $params['poll']['hide_results'];
		$data->display_warning = $params['poll']['display_warning'];
		
		// Tally the votes
		if ( isset( $post->ID ) && $post->ID != 0 ):
			$where_content = $wpdb->prepare( ' AND content_id=%s', $post->ID );
		else:
			$where_content = '';
		endif;
		
		$query = $wpdb->prepare( 'SELECT vote, COUNT(vote) as count FROM '.EVAL_DB_VOTES.' WHERE metric_id=%s'.$where_content.' AND disabled=0 GROUP BY vote', $metric->id, $post->ID );
		$data->votes = $wpdb->get_results( $query, OBJECT_K ); // Returned array will have vote value for keys
		$data->total_votes = 0;
		foreach ( $data->votes as $vote ):
			$data->total_votes += $vote->count;
		endforeach;
		
		// Get the current user's vote, if it exists
		if ( $data->total_votes > 0 && ! empty( $data->user ) ):
			$data->user_vote = $wpdb->get_var( $wpdb->prepare( 'SELECT vote FROM '.EVAL_DB_VOTES.' WHERE metric_id=%s AND content_id=%s AND user_id=%s AND disabled=0', $metric->id, $post->ID, $data->user ) );
		else:
			$data->user_vote = false;
		endif;
		
		// Calculate Average
		$data->average = 0;
		$data->averages = array();
		$data->answer_votes = array();
		foreach ( $data->answers as $key => $answer ):
			$answer = wp_unslash( $answer);
			$value = ( $data->total_votes > 0 && isset( $data->votes[$key] ) ? round( $data->votes[$key]->count / $data->total_votes * 100, 1 ) : 0 );
			$data->averages[$key] = $value;
			$data->answer_votes[$key] = ( isset( $data->votes[$key] ) ? $data->votes[$key]->count : 0 );
			$data->average += $value;
		endforeach;
		$data->average /= count( $data->answers );
		
		// Miscelleneous Data
		if ( $data->preview == false ):
			$data->nonce = array();
			$data->link = array();
			foreach ( $data->answers as $key => $answer ):
				$data->nonce[$key] = wp_create_nonce( 'evaluate-vote-'.$data->metric_id.'-'.$data->content_id.'-'.$key.'-'.self::get_user() );
				$data->link[$key] = self::get_vote_url( $metric, $post->ID, $key, $data->nonce[$key] );
			endforeach;
			
			if ( $data->hide_results == 'on' && $data->user_vote == false ):
				$data->shell_classes .= " hide-results";
			endif;
		endif;
		
		return $data;
	}
  
	//*********************************************************//
	// Functions to display any metric, needs $data from above //
	//*********************************************************//
	
	static $metrics = array(
		'one-way' => array(
			'all'  => array( __CLASS__, 'display_one_way' ),
			'user' => array( __CLASS__, 'display_one_way_user' ),
		),
		'two-way' => array(
			'all'  => array( __CLASS__, 'display_two_way' ),
			'user' => array( __CLASS__, 'display_two_way_user' ),
		),
		'range' => array(
			'all'  => array( __CLASS__, 'display_range' ),
			'user' => array( __CLASS__, 'display_range_user' ),
		),
		'poll' => array(
			'all'  => array( __CLASS__, 'display_poll' ),
			'user' => array( __CLASS__, 'display_poll_user' ),
		),
	);
	
	/** Convenience function to handle any metric display */
	public static function display_metric( $data ) {
		if ( $data->admin_only && ! current_user_can( 'administrator' ) ):
			return;
		endif;
		
		if ( $data->template ):
			$can_vote = "{{? it.show_user_vote == false }} can-vote{{?}}";
		elseif ( $data->preview == false && $data->show_user_vote == false ):
			$can_vote = " can-vote";
		else:
			$can_vote = "";
		endif;
		
		ob_start();
		?>
		<div class="evaluate-shell <?php echo $data->shell_classes.$can_vote; ?>" id="evaluate-shell-<?php echo $data->metric_id; ?>-<?php echo $data->content_id; ?>" data-user="<?php echo $data->user; ?>" data-user-vote="<?php echo $data->user_vote; ?>" data-show-user-vote="<?php echo $data->show_user_vote; ?>" data-metric-id="<?php echo $data->metric_id; ?>" data-content-id="<?php echo $data->content_id; ?>" data-modified="<?php echo $data->modified; ?>">
			
			<div class="rate-name"><?php echo wp_unslash( $data->display_name ); ?></div>
			<span class="rate-div rate-<?php echo $data->type; ?>">
				<?php if ( $data->template ): ?>
					{{? it.show_user_vote == true }}
						{{? it.user_vote == undefined || it.user_vote == false }}
							<span class="no-rating">NO RATING</span>
						{{??}}
							<?php call_user_func( Evaluate::$metrics[$data->type]['user'], $data ); ?>
						{{?}}
					{{??}}
						<?php call_user_func( Evaluate::$metrics[$data->type]['all'], $data ); ?>
					{{?}}
				<?php elseif ( $data->show_user_vote ): ?>
					<?php if ( empty( $data->user_vote ) ): ?>
						<span class="no-rating">NO RATING</span>
					<?php else: ?>
						<?php call_user_func( Evaluate::$metrics[$data->type]['user'], $data ); ?>
					<?php endif; ?>
				<?php else: ?>
					<?php
					if( isset( Evaluate::$metrics[$data->type]['all'] ) && isset( $data ) ){
						call_user_func( Evaluate::$metrics[$data->type]['all'], $data );

					}
				
					  ?>
				<?php endif; ?>
			</span>
		</div>
		<?php
		
		return ob_get_clean();
	}
	
	public static function display_one_way( $data ) {
		
		if( !in_array( $data->style,  array( 'bookmark' )) ) { ?> 
		<span class="up-counter"><?php echo $data->counter; ?> </span>
		<?php } ?>
		<a <?php echo $data->href_link; ?> onclick="<?php echo $data->onclick; ?>" class="rate <?php echo $data->style.$data->state; ?> eval-link" title="<?php echo $data->title ?>" data-nonce="<?php echo $data->nonce; ?>">
			<span><?php echo $data->title ?></span>
		</a>
		<?php
	}
  
	public static function display_one_way_user( $data ) {
		?>
		<a class="rate <?php echo $data->style.$data->state; ?> eval-link" title="<?php echo $data->title ?>">
			<span><?php echo $data->title ?></span>
		</a>
		<?php
	}
	
	public static function display_two_way( $data ) {
		?>
		<span class="up-counter"><?php echo $data->counter_up; ?> </span>
		<a <?php echo $data->href_link_up; ?> onclick="<?php echo $data->onclick; ?>" class="rate <?php echo $data->style.$data->state_up; ?> eval-link link-up" title="<?php echo $data->title_up; ?>" data-nonce="<?php echo $data->nonce_up; ?>">&nbsp;</a>
		<span class="down-counter"> <?php echo $data->counter_down; ?> </span>
		<a <?php echo $data->href_link_down; ?> onclick="<?php echo $data->onclick; ?>" class="rate <?php echo $data->style.'-down'.$data->state_down; ?> eval-link link-down" title="<?php echo $data->title_down; ?>" data-nonce="<?php echo $data->nonce_down; ?>">&nbsp;</a>
		<?php
	}
	
	public static function display_two_way_user( $data ) {
		?>
		<a class="rate <?php echo $data->style.$data->state_up; ?> eval-link link-up" title="<?php echo $data->title_up; ?>">&nbsp;</a> 
		<a class="rate <?php echo $data->style.'-down'.$data->state_down; ?> eval-link link-down" title="<?php echo $data->title_down; ?>">&nbsp;</a>
		<?php
	}
  
	public static function display_range( $data ) {
		?>
		<?php if ( $data->template ): ?>
			{{? it.average_display != ""}}
				<div class="rating-text">
					Average: {{=it.average_display}}
				</div>
			{{?}}
		<?php else: ?>
			<?php if ( ! empty( $data->average_display ) ): ?>
				<div class="rating-text">
					Average: <?php echo $data->average_display; ?>
				</div>
			<?php endif; ?>
		<?php endif; ?>
		<div class="stars">
			<div class="rating<?php echo $data->state; ?>" style="width: <?php echo $data->width; ?>%"></div>
			<?php if ( $data->template ): ?>
				{{ for (var prop in it.stars) { }}
					<div class="starr">
						<a {{=it.href_link[prop]}} onclick="{{=it.onclick}}" class="eval-link link-{{=prop}}" data-nonce="{{=it.nonce[prop]}}">&nbsp;</a>
				{{ } }}
				{{ for (var prop in it.stars) { }}
					</div>
				{{ } }}
			<?php else: ?>
				<?php for ( $i = 1; $i <= $data->length; $i++ ): // Nested divs for star links 
					$link = $data->href_link[$i];
					$nonce = $data->nonce[$i];
					$title = $i."/".$data->length.self::$titles['range'];
					?>
					<div class="starr">
						<a <?php echo $link; ?> onclick="<?php echo $data->onclick; ?>" title="<?php echo $title; ?>" class="eval-link link-<?php echo $i; ?>" data-nonce="<?php echo $nonce; ?>">&nbsp;</a>
				<?php endfor; ?>
				<?php for ( $i = 1; $i <= $data->length; $i++ ): // Close nested divs ?>
					</div>
				<?php endfor; ?>
			<?php endif; ?>
		</div>
		<?php
	}
	
	public static function display_range_user( $data ) {
		?>
		<div class="stars">
			<div class="rating<?php echo $data->state; ?>" style="width: <?php echo $data->width; ?>%"></div>
			<?php if ( $data->template ): ?>
				{{ for (var prop in it.stars) { }}
					<div class="starr">
						<a class="eval-link link-{{=prop}}">&nbsp;</a>
				{{ } }}
				{{ for (var prop in it.stars) { }}
					</div>
				{{ } }}
			<?php else: ?>
				<?php for ( $i = 1; $i <= $data->length; $i++ ): // Nested divs for star links
					?>
					<div class="starr">
						<a class="eval-link link-<?php echo $i; ?>">&nbsp;</a>
				<?php endfor; ?>
				<?php for ( $i = 1; $i <= $data->length; $i++ ): // Close nested divs ?>
					</div>
				<?php endfor; ?>
			<?php endif; ?>
		</div>
		<?php
	}
  
	/**
	 * Chooses between form and results according to request.
	 */
	public static function display_poll( $data ) {
		 
		?>
		<ul class="poll-list">
			<li class="poll-question"><?php echo wp_unslash( $data->question ); ?></li>
			<?php if ( $data->template ): ?>
				{{ for(prop in it.answers) { }}
				<a {{=it.href_link[prop]}} class="eval-link" onclick="<?php echo $data->onclick; ?>" data-nonce="{{=it.nonce[prop]}}">
					<li class="poll-answer">
						<input type="radio" />
						<strong>{{=it.answers[prop]}}</strong><span class="poll-average">: {{=it.averages[prop]}}% ({{=it.answer_votes[prop]}} votes)</span>
						<div class="poll-result">
							{{? it.user_vote == prop }}
								<div class="poll-bar selected" style="width:{{=it.averages[prop]}}%"></div>
							{{??}}
								<div class="poll-bar" style="width:{{=it.averages[prop]}}%"></div>
							{{?}}
						</div>
					</li>
				</a>
				{{ } }}
			<?php else: ?>
				<?php foreach ( $data->answers as $key => $answer ): //loop through answers and calculate percentage vote
					$selected = ( $data->user_vote == $key ? 'selected' : null );
					$average = $data->averages[$key];
					$answer_votes = $data->answer_votes[$key];
					?>
					<a <?php echo $data->href_link[$key]; ?> class="eval-link" onclick="<?php echo $data->onclick; ?>" data-nonce="<?php echo $data->nonce[$key]; ?>">
						<li class="poll-answer">
							<input type="radio" />
							<strong><?php echo wp_unslash( $answer ); ?></strong><span class="poll-average">: <?php echo $average; ?>% (<?php echo $answer_votes; ?> votes)</span>
							<div class="poll-result">
								<div class="poll-bar <?php echo $selected; ?>" style="width: <?php echo $average; ?>%"></div>
							</div>
						</li>
					</a>
				<?php endforeach; ?>
			<?php endif; ?>
		</ul>
		<?php if ( ! $data->template && ! $data->preview  && $data->display_warning && $data->user_vote == null ): ?>
			<span class="poll-warning">You have not voted.</span>
		<?php endif; ?>
		<?php
	}
  
	/**
	 * Chooses between form and results according to request.
	 */
	public static function display_poll_user( $data ) {
		?>
		<span class="poll-question"><?php echo $data->display_name; ?> </span>
		<?php if ( $data->template ): ?>
			<span class="poll-answer">{{=it.answers[it.user_vote]}}</span>
		<?php else: ?>
			<span class="poll-answer"><?php echo $data->answers[$data->user_vote]; ?></span>
		<?php endif; ?>
		<?php
	}
  
	/**
	 * Prints out metric templates for ajax viewing
	 */
	public static function print_templates() {
		$data = self::get_metric_data_js();
		
		self::print_template( 'one-way', $data );
		self::print_template( 'two-way', $data );
		self::print_template( 'range', $data );
		self::print_template( 'poll', $data );
	}
	
	public static function print_template( $type, $data ) {
		$data->type = $type;
		?>
		<script id="evaluate-<?php echo $type; ?>" type="text/x-dot-template">
			<?php echo self::display_metric( $data ); ?>
		</script>
		<?php
	}

	/** Modifies the wordpress query to sort pulses. */
	public static function modify_query( $query ) {
		if ( $query->is_home() && $query->is_main_query() ):
			global $wpdb;
			
			$sort = ( $_REQUEST['sort'] ? $_REQUEST['sort'] : 'score' );
			
			switch ( $sort ):
			case 'score':
				$metric_id = ( isset( $_REQUEST['metric_id'] ) ? $_REQUEST['metric_id'] : false );
				$order = ( isset( $_REQUEST['order'] ) ? $_REQUEST['order'] : 'desc' );
				$query->set( 'meta_key', 'metric-'.$metric_id.'-score' );
				$query->set( 'orderby', 'meta_value_num' );
				$query->set( 'order', $order );
				break;
			case 'total_votes':
				$metric_id = ( isset( $_REQUEST['metric_id'] ) ? $_REQUEST['metric_id'] : false );
				$order = ( isset( $_REQUEST['order'] ) ? $_REQUEST['order'] : 'desc' );
				$query->set( 'meta_key', 'metric-'.$metric_id.'-votes' );
				$query->set( 'orderby', 'meta_value_num' );
				$query->set( 'order', $order );
				break;
			case 'user_votes':
				$posts = $wpdb->get_col( $wpdb->prepare( 'SELECT content_id FROM '.EVAL_DB_VOTES.' WHERE user_id=%s', self::get_user() ) );
				$query->set( 'post__in', $posts );
				break;
			endswitch;
		endif;
	}
  
	/* Get controversy score for any metric-post pair */
	public static function get_controversy_score( $metric_id, $content_id ) {
		$data = self::get_data_by_id( $metric_id, $content_id );
		
		switch ( $data->type ):
		case 'two-way':
			$score = $data->counter_up - $data->counter_down;
			if ( $score < 0 ):
				$score = abs( $score ) - 0.1;
			endif;
			break;
		case 'range':
			$score = abs( ( $data->length / 2.0 ) - self::calculate_bayesian_score( $data->average, $data->total_votes, $data->length ) );
			break;
		case 'poll':
			$score = $data->average;
			if ( $score == 0 ):
				$score = 100;
			endif;
			break;
		endswitch;
		
		return ( empty( $score ) ? 0 : $score );
	}
  
	/* Get score for any metric-post pair */
	public static function get_score( $metric_id, $content_id, $dumb = false ) {
		$data = self::get_data_by_id( $metric_id, $content_id );
		
		switch ( $data->type ):
		case 'one-way':
			$score = $data->counter;
			break;
		case 'two-way':
			if ( $dumb ):
				$score = $data->counter_up - $data->counter_down;
			else:
				$score = self::calculate_wilson_score( $data->counter_up, $data->counter_total );
			endif;
			break;
		case 'range':
			if ( $dumb ):
				$score = $data->average;
			else:
				$score = self::calculate_bayesian_score( $data->average, $data->total_votes, $data->length );
			endif;
			break;
		endswitch;
		
		return ( empty( $score ) ? 0 : $score );
	}
	/**
	 * [get_top_posts description]
	 * 
	 * @param  [type]  $metric_id [description]
	 * @param  integer $limit     [description]
	 * @return [type]             [description]
	 */
	function get_posts( $metric_id, $limit = 5, $order = 'DESC', $score = 'score' ) {

			/**
			 * The WordPress Query class.
			 * @link http://codex.wordpress.org/Function_Reference/WP_Query
			 *
			 */
			$args = array(
				
				//Type & Status Parameters
				'post_type'   => 'any',
				'post_status' => 'any',
				'post_status' => array(
					'publish',
					),
				//Order & Orderby Parameters
				'order'               => $order,
				'orderby'             => 'meta_value',
				//Pagination Parameters
				'posts_per_page'      => $limit,
				//Custom Field Parameters
				'meta_key'       => 'metric-'.$metric_id.'-'.$score,
				
			);
			
			$posts = array();
			$the_query = new WP_Query( $args );
			
			// The Loop
			if ( $the_query->have_posts() ) {
			     
				while ( $the_query->have_posts() ) {
					$the_query->the_post();
					
					$posts[] = array(
						'title'	=> get_the_title(),
						'id'	=> get_the_id(),
						'score'	=> get_post_meta( get_the_id() , 'metric-'.$metric_id.'-score' , true ),
						'total' => get_post_meta( get_the_id() , 'metric-'.$metric_id.'-votes' , true ),
						'author'=> get_the_author( ),
						'post_type'	=> get_post_type( )
					);
				}
			} else {
				// no posts found
			}
			/* Restore original Post Data */
			wp_reset_postdata();
		return $posts;	
	}
	/**
	 * [get_bottom_posts description]
	 * 
	 * @param  [type]  $metric_id [description]
	 * @param  integer $limit     [description]
	 * @return [type]             [description]
	 */
	function get_bottom_posts( $metric_id, $limit = 5  ) {
		return array();	
	}
	/**
	 * [get_most_votes_posts description]
	 * 
	 * @param  [type]  $metric_id [description]
	 * @param  integer $limit     [description]
	 * @return [type]             [description]
	 */
	function get_most_votes_posts( $metric_id, $limit = 5  ) {
		return array();	
	}
	/**
	 * [get_most_cobtroversial_posts description]
	 * 
	 * @param  [type]  $metric_id [description]
	 * @param  integer $limit     [description]
	 * @return [type]             [description]
	 */
	function get_most_cobtroversial_posts( $metric_id, $limit = 5  ) {
		return array();	
	}

	function display_posts( $posts ){

		if( !empty( $posts ) ){ ?>
		<ol class="display-posts">
		<?php
			foreach( $posts as $post ) { 
				// var_dump($post);
				?>
				<li><a href="<?php echo admin_url( 'post.php?post='.$post['id'].'&action=edit&post_type='.$post['post_type'] ); ?>"><?php echo $post['title']; ?> <span class="score"><?php echo round( $post['score'], 1 ); ?></span></a></li>
		<?php } ?>
		</ol>
		<?php 
		}

	}
	

  
	/**
	 * Assumes score inherently tends towards 50%. ie. the bayesian prior is 50%
	 */
	public static function calculate_bayesian_score( $average, $total, $length ) {
		$prior = ( ( $length - 1 ) / 2 ) + 1;
		$constant = 1;
		return ( ( $constant * $prior ) + ( $average * $total ) ) / ( $constant + $total );
	}
  
	/**
	 * Taken from http://derivante.com/2009/09/01/php-content-rating-confidence/ 
	 * calculates the wilson score: a lower bound on the "true" value of
	 * the ratio of positive votes and total votes, given a confidence level
	 */
	public static function calculate_wilson_score( $positive, $total, $power = 0.05 ) {
		if ( $total == 0 ) return 0;
		
		$z = self::pnormaldist( 1 - $power / 2 );
		$p = 1.0 * $positive / $total;
		$s = ($p + $z * $z / (2 * $total) - $z * sqrt( ($p * (1 - $p) + $z * $z / (4 * $total)) / $total) ) / (1 + $z * $z / $total);
		return $s;
	}
  
	/**
	 * Taken from http://derivante.com/2009/09/01/php-content-rating-confidence/ 
	 * calculates z value for a given pct point $qn in the standard normal distribution
	 * with sigma=1 and mean=0
	 */
	public static function pnormaldist( $qn ) {
		$b = array(
			1.570796288, 0.03706987906, -0.8364353589e-3,
			-0.2250947176e-3, 0.6841218299e-5, 0.5824238515e-5,
			-0.104527497e-5, 0.8360937017e-7, -0.3231081277e-8,
			0.3657763036e-10, 0.6936233982e-12
		);
		
		if ( $qn < 0.0 || 1.0 < $qn ) return 0.0;
		if ( $qn == 0.5 ) return 0.0;
		
		$w1 = $qn;
		if ( $qn > 0.5 ):
			$w1 = 1.0 - $w1;
		endif;
		
		$w3 = - log( 4.0 * $w1 * (1.0 - $w1) );
		$w1 = $b[0];
		
		for ( $i = 1; $i <= 10; $i++ ):
			$w1 += $b[$i] * pow( $w3, $i );
		endfor;
		
		if ( $qn > 0.5 ):
			return sqrt( $w1 * $w3 );
		else:
			return - sqrt( $w1 * $w3 );
		endif;
	}
}

//priority parameters MUST be > than CTLT_Stream priority otherwise post requests don't work
add_action( 'init', array( 'Evaluate', 'init' ), 15 ); 
