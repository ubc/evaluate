<?php 



class Evaluate {

	static $options = array();
	static $settings = array();
	static $user = null;
  	
	public static function init() {
		
		self::$options = get_option( 'evaluate_metrics' ); 
	    self::$settings = get_option( 'evaluate_settings' );
	    self::$user = Evaluate::get_user();
	    
	    if( isset( $_GET['metric'] ) )
	    	Evaluate::deal_with_metric();
	    	
	    if( isset( $_GET['d'] ) ) {
	    	echo "delete everything";
	    	Evaluate::delete_everything();
	    }
      
      // check if the table is created, if not run install script
      // this is needed as Evaluate::install() does not get run on
      // multi-site even if network activation is on
      Evaluate::install(); //will check for db version first

	}
	
	public static function enqueue_style() {
	
		wp_register_style( 'evaluate', EVALUATE_DIR_URL . '/css/evaluate.css' );
		
 		// enqueing:
 		wp_enqueue_style( 'evaluate' );
	}
	
	public static function the_content( $content ) { 
		
		
		
		if( !is_singular() )
			return $content; // don't do anything
		$vote = "";
		
		if( is_array( self::$options ) ):
			foreach( self::$options as $id => $metric ):
				$metric['id'] = $id;
				if( Evaluate::show_metric( $metric ) )
					$vote .= Evaluate::display_metic( $metric );
			
			endforeach;
		endif;
		
		
		// $vote = "<div>vote for me</div>";
		
		return $content. $vote;
	
	}
	
	public static function show_metric( $metric ) {
		
		if( !empty( $metric['loggedin'] ) && !current_user_can('read') )
			return false;
		
		if( !empty( $metric['admin_only'] ) && !current_user_can('administrator'))
			return false;
		
		foreach( $metric['post_type'] as $post_type ):
			if( is_singular( $post_type ) )
				return true;
		endforeach;
		
		return false;
	}
	
	public static function deal_with_metric( $redirect=true ) {
		
		$verify	= $_GET['verify'];
		$post_id= $_GET['post'];
		$type	= $_GET['type'];
		$count	= $_GET['count'];
		$action	= $_GET['metric'];
		
		// don't do anything if we don't pass the varification stage
		if( !Evaluate::verify( $verify, $post_id, $type, $count, $action ) )
			return null;
		
		switch( $action ) {
			case 'add':
				Evaluate::add_count( $post_id, $count, $type );
			break;
			
			case 'remove':
				Evaluate::remove_count( $post_id, $count, $type );
			break;
			
			case 'update':
				Evaluate::update_count( $post_id, $count, $type );
			break;
			
			case 'poll':
				
				Evaluate::poll_count( $post_id, $count, $type );
			break;
		
		}	
    
		if( $redirect ):
			//wp_redirect( Evaluate::current_URL() );
			die();
		endif;
		
		return null;
	}
	
	public static function display_metic( $metric ) {
		
		$html = "";
		
		if( isset( $metric['display_name'] ) ):
			$html .= '<span class="metric-name">'.$metric['name'].'</span>';	
		endif;
		
		switch( $metric['type'] ) {
		
			case 'one-way':
				$html .=	Evaluate::one_way( $metric );
			break;
			case 'two-way':
				$html .=	Evaluate::two_way( $metric );
			break;
			case 'range':
				$html .=	Evaluate::range( $metric );
			break;
			case 'poll':
				$html .=	Evaluate::poll( $metric );
			break;
		}
		
		return '<div class="metric-shell">'.$html.'</div>';
	}
	
	public static function one_way( $metric ) {
		global $post;
		
		if( isset( $post ) )
			$post_id = $post->ID;
		
		$type = $metric['one-way'];
		
		$votes = Evaluate::metric_sum( $post_id, $metric['id'] );
		
		$votes =  ( $votes ? $votes : 0 );
		
		if( $post_id )
			$id_attr = "rate-".$type."-".$post_id;
		
		$class_attr = 'rate one-way '.$type;
		
		switch( $type ) {
			case 'thumb': 
				$title =  'Like'; 
			break;
			
			case 'arrow': 
				$title = 'Vote Up'; 
			break;
			
			case 'heart': 
				$title = 'Heart'; 
			break;
		}
		
		if( Evaluate::count_exists( $post_id, $metric['id'] ) ):
			$class_attr .= " selected-rating ";
			$url = Evaluate::url( $metric['id'], 1 , 'remove' );
		else:
			$url = Evaluate::url( $metric['id'], 1 , 'add' );
		endif;
		
		return '<div class="rate-shell"><span class="rate-count">'.$votes .'</span> <a href="'.$url.'" class="'.$class_attr.'" title="'.$title.'">'.$title.'</a></div>';
	}
	
	
	public static function two_way( $metric ) {
		global $post;
		
		if( isset( $post ) )
			$post_id = $post->ID;
			
		$type = $metric['two-way'];
		
		$class_attr_up 		= $type.'-up ';
		$class_attr_down 	= $type.'-down ';
		
		if( Evaluate::count_exists( $post_id, $metric['id'] ) > 0 ):
			// the user has voted up
			$class_attr_up .= " selected-rating ";
			$url_up = Evaluate::url( $metric['id'], 1 , 'remove' );
			$url_down = Evaluate::url( $metric['id'], -1 , 'update' );
			
		elseif( Evaluate::count_exists( $post_id, $metric['id'] ) < 0 ) :
			// the user has voted down
			$class_attr_down .= " selected-rating ";
			$url_up = Evaluate::url( $metric['id'], 1 , 'update' );
			$url_down = Evaluate::url( $metric['id'], -1 , 'remove' );
		else:
			// the user still needs to vote
			$url_up = Evaluate::url( $metric['id'], 1 , 'add' );
			$url_down = Evaluate::url( $metric['id'], -1 , 'add' );
		endif;
		
		$up_votes = Evaluate::metric_count( $post_id, $metric['id'] , 1 );
		$down_votes = Evaluate::metric_count( $post_id, $metric['id'] , -1 );
		
		
		if($post_id)
			$id_attr = "rate-".$type."-".$post_id;
		
		$class_attr = 'rate two-way '.$type.' ';
		
		
		switch( $type ) {
			case 'thumb': 
				$up 	= 'Thumbs Up'; 
				$down 	= 'Thumbs Down'; 
			break;
			
			case 'arrow': 
				$up 	= 'Vote Up';
				$down 	= 'Vote Down'; 
			break;
			
		}
		
		$html 	= '<span class="rate-count">'.$up_votes .'</span> <a href="'.$url_up.'" class="'.$class_attr.$class_attr_up.'" title="'.$up.'">'.$up.'</a> ';
		return  '<div class="rate-shell">'.$html .'<span class="rate-count">'. $down_votes.'</span> <a href="'.$url_down.'" class="'.$class_attr.$class_attr_down.'" title="'.$down.'">'.$down.'</a></div> ';
	}
	
	public static function range( $metric=null ) {
		global $post;
		$stars = 5;
		if( isset( $post ) )
			$post_id = $post->ID;
			
		$current_i = Evaluate::count_exists( $post_id, $metric['id'] );
		
		$action = ($current_i ? 'update' : 'add' );
			
		$sum = Evaluate::metric_sum( $post_id, $metric['id'] );
		$number = Evaluate::metric_count( $post_id, $metric['id'] );
		
		$average = ( $number > 0 ?  $sum / $number : 0 ); 
		$average = ( is_float( $average ) ? number_format( $average, 1 ) : $average );
		
		$html = '<div class="rate-shell"><span class="inline-rating">
			<ul class="star-rating small-star">
				<li class="current-rating" style="width:'.( $current_i / $stars * 100 ).'%;">Your Rating '.$current_i.'/'.$stars.' Stars.</li>';
				$i = 1;
				while( $i <= $stars ):
					$s = ( $i > 1 ? "s" : "" );
					$url 		= ( $current_i == $i ?  Evaluate::url( $metric['id'], $i , 'remove' ) : Evaluate::url( $metric['id'], $i , $action ) );
					$current	= ( $current_i == $i ? " selected-rating "  : '' );
					$html .= '<li><a href="'.$url.'" title="'.$i.' star'.$s.' out of '.$stars.'" class="star-'.$i.$current.' ">'.$i.'</a></li>';
					
					$i++;
					
				endwhile;
		$html .='
					
			</ul> 
		</span><span> Rating '.$average.'/'.$stars.' Stars</span></div>';
		return $html;
	}
	public static function poll( $metric = null ) {
		global $post;
		
		if( isset( $post ) )
			$post_id = $post->ID;
		else
			return " poll";
		
		$user_count = Evaluate::count_exists( $post_id, $metric['id'] );
		 
		if( ( !$user_count && ! isset(  $_GET['result-'.$metric['id']] ) ) || isset( $_GET['poll-'.$metric['id']] )  ):
			$show_poll 		= 'display:block;';
			$show_result 	= 'display:none;';
		else:
			$show_poll 		= 'display:none;';
			$show_result 	= 'display:block;';
		endif;
		
		$verify = wp_create_nonce ( 'metric-'.$post_id.'-'.$metric['id'].'-poll' );

		$html  = '<div class="rate-shell" style="'.$show_poll.'">';
		$html .= '<form action="" method="get" class="poll-form" >';
		$html .= '<input type="hidden" name="type" value="'. esc_attr( $metric['id'] ) .'" />';
		$html .= '<input type="hidden" name="metric" value="poll" />';
		$html .= '<input type="hidden" name="post" value="'. esc_attr( $post_id ) .'" />';
		$html .= '<input type="hidden" name="verify" value="'. esc_attr( $verify ) .'" />';
		$html .= '<strong>'.$metric['poll']['question'].'</strong>';
		$html .= "<ul>";
		$i = 1;
		
		foreach( $metric['poll']['name'] as $item ):
			if( !empty( $item ) )
				$html .=  '<li><label><input type="radio" '.checked( $user_count, $i, false ).'  name="count" value="'.$i.'" /> '.$item.'</label></li>'; 
			
			$i++;
		endforeach;
		$html .= "</ul>";
		$html .= '<input type="submit" value="Vote" /> <a href="?result-'.$metric['id'].'=1">view results</a>'; 
		$html .= '</form></div>'; // end of rate-shell
		
		// the results 
		$total_count = Evaluate::metric_count( $post_id, $metric['id'] );
		$html .= '<div class="rate-shell" style="'.$show_result.'">';
		$html .= '<strong>'.$metric['poll']['question'].'</strong> Total votes:<span class="total-count"> '.$total_count.'</span>';
		$html .= '<ul class="poll-results">';
		
		$i = 1;
		foreach( $metric['poll']['name'] as $item ):
			if( !empty( $item ) ):
				$count = Evaluate::metric_count( $post_id, $metric['id'], $i );
					$html .=  '<li>'.$item;
				
				$percent = ( $total_count > 0 ? $count / $total_count * 100 : 0 );
				
				if( $user_count == $i )
					$html .= ' <strong>*</strong> ';
				$html .=  ' <small>'.$count.' Votes ( '.$percent.'% )</small>';
				$html .=  '<div class="poll-result-line" title="'.esc_attr($item).'" style="width: '.$percent.'%;"></div>
				</li>'; 
			endif;
			$i++;
		endforeach;
		$html  .= '</ul> <a href="?poll-'.$metric['id'].'=1">back to the poll</a></div>'; // end of rate shell
		
		return $html;
	}
	
	/* Helper Functions */
	public static function url( $type, $count=null, $action ) {
		global $post;
		if( !isset( $post ) )	
			return '#';
		
		$post_id = $post->ID;
		$once = wp_create_nonce ( 'metric-'.$post_id.'-'.$type.'-'.$count.'-'.$action );
		if( isset( $count ) ):
			$once = wp_create_nonce ( 'metric-'.$post_id.'-'.$type.'-'.$count.'-'.$action );
			$url = "?metric=".$action."&type=".$type."&post=".$post_id."&count=".$count."&verify=".$once;
		
		else:
			$once = wp_create_nonce ( 'metric-'.$post_id.'-'.$type.'-'.$action );
			$url = "?metric=".$action."&type=".$type."&post=".$post_id."&verify=".$once;
		
		endif;
		
		return $url;
		
	}
	
	public static function verify( $verify, $post_id, $type, $count, $action ) {
		
		$skip_next = false;
		
		if( $action == 'poll' && wp_verify_nonce( $verify , 'metric-'.$post_id.'-'.$type.'-'.$action ) )
			$skip_next = true;
		
		// the non once passes
		if( !$skip_next ):
			if( !wp_verify_nonce( $verify, 'metric-'.$post_id.'-'.$type.'-'.$count.'-'.$action ) )
				return false;
		endif;
		
		// if you are not logged in and somehow guess the url
		if( !empty( self::$options[$type]['loggedin'] ) && !current_user_can('read') )
			return false;
		
		// if you are not an admin but somehow guess the url
		if( !empty( self::$options[$type]['admin_only'] ) && !current_user_can('administrator'))
			return false;
		
		
		return true;
	}
	
	public static function current_URL() {
		$pageURL = 'http';
		if ( isset( $_SERVER["HTTPS"] ) ) { $pageURL .= "s"; }
			$pageURL .= "://";
		if ( $_SERVER["SERVER_PORT"] != "80" ) {
			$pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].parse_url($_SERVER["REQUEST_URI"],PHP_URL_PATH);
		} else {
			$pageURL .= $_SERVER["SERVER_NAME"].parse_url($_SERVER["REQUEST_URI"],PHP_URL_PATH);
		}
 
		return $pageURL;
	}
  
	/* DB Helper functions */
	public static function install() {
	  	global $wpdb;
	  	if( EVALUATE_DB_VERSION > Evaluate::get_option( 'db_version' ) ):
			
			$sql = "CREATE TABLE " . EVALUATE_DB_TABLE . " (
					id mediumint(9) NOT NULL AUTO_INCREMENT,
					post_id bigint(11) DEFAULT '0' NOT NULL,
					user_id VARCHAR(11) DEFAULT '0' NOT NULL,
					counter bigint(11) DEFAULT '1' NOT NULL,
					date TIMESTAMP NOT NULL,
					date_gmt DATETIME  DEFAULT '0000-00-00 00:00:00' NOT NULL,
					type VARCHAR(64) NOT NULL,
					UNIQUE KEY id (id)
					);";
      
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
			
			Evaluate::update_option( 'db_version', EVALUATE_DB_VERSION );
			
			
		endif;
		
	// this is just for testing shoule be take out later
	}
	
	public static function add_count( $post_id, $count, $type ) {
		global $wpdb;
		
		$date 		= Evaluate::gmt_time();
		$user_id 	= Evaluate::get_user();;
		$data 		= array( 
						'post_id' 	=> $post_id, 
						'type' 		=> $type,
						'counter'	=> $count,
						'user_id'	=> $user_id,
						'date_gmt'  => $date,
					);
	
		$result = $wpdb->insert( EVALUATE_DB_TABLE , $data , array( '%d', '%s', '%d', '%s', '%s') );
		$metric_count 	= Evaluate::metric_count( $post_id, $type );
		$metric_sum 	= Evaluate::metric_sum( $post_id, $type );
		
		// save the number of votes to better get popular votes 
		add_post_meta( $post_id, 'count_'.$type, $metric_count, true ) or update_post_meta( $post_id, 'count_'.$type, $metric_count );
		add_post_meta( $post_id, 'sum_'.$type, $metric_sum , true ) or update_post_meta( $post_id, 'sum_'.$type, $metric_sum  );
		
		// for knowing when to update this 
		$date = Evaluate::gmt_time();
		
		update_option( 'updated', $date );
		
	}
	
	
	public static function remove_count( $post_id, $count, $type ) {
		global $wpdb;
		
		$user_id = Evaluate::get_user();;
		
		$wpdb->query( $wpdb->prepare( "DELETE FROM ".EVALUATE_DB_TABLE." WHERE post_id = %d AND user_id = '%s' AND type ='%s';", $post_id, $user_id, $type ) );
		
		$metric_count 	= Evaluate::metric_count( $post_id, $type );
		$metric_sum 		= Evaluate::metric_sum( $post_id, $type );
		
		// save the number of votes to better get popular votes 
		update_post_meta( $post_id, 'count_'.$type, $metric_count );
		update_post_meta( $post_id, 'sum_'.$type, $metric_sum );
		
		// for knowing when to update this 
		$date = Evaluate::gmt_time();
		
		update_option( 'updated', $date );
	}
	
	public static function update_count( $post_id, $count, $type ) {
		global $wpdb;
		
		$date 		= Evaluate::gmt_time();
		$user_id 	= Evaluate::get_user();;
		
		$data 		= array( 
						'post_id' 	=> $post_id, 
						'type' 		=> $type,
						'counter'	=> $count,
						'user_id'	=> $user_id,
						'date_gmt'  => $date,
					);
					
		$where = array( 
						'post_id' 	=> $post_id,
						'type' 		=> $type,
						'user_id'	=> $user_id,
					);
		
		$result = $wpdb->update(  EVALUATE_DB_TABLE, $data, $where, array( '%d', '%s', '%d', '%s', '%s') , array( '%d', '%s', '%s') );
		
		$metric_count 	= Evaluate::metric_count( $post_id, $type );
		$metric_sum 	= Evaluate::metric_sum( $post_id, $type );
		
		// save the number of votes to better get popular votes 
		update_post_meta( $post_id, 'count_'.$type, $metric_count );
		update_post_meta( $post_id, 'sum_'.$type, $metric_sum );
		
		// for knowing when to update this 
		$date = Evaluate::gmt_time();
		
		update_option( 'updated', $date );
	
	}
	
	public static function poll_count( $post_id, $count, $type ) {
	
		global $wpdb;
		
		$date 		= Evaluate::gmt_time();
		$user_id 	= Evaluate::get_user();; 
		$data 		= array( 
						'post_id' 	=> $post_id, 
						'type' 		=> $type,
						'counter'	=> $count,
						'user_id'	=> $user_id,
						'date_gmt'  => $date,
					);
					
		$where = array( 
						'post_id' 	=> $post_id,
						'type' 		=> $type,
						'user_id'	=> $user_id,
					);
		
				
		if( Evaluate::count_exists( $post_id, $type ) ):
			$result = $wpdb->update(  EVALUATE_DB_TABLE, $data, $where, array( '%d', '%s', '%d', '%s', '%s') , array( '%d', '%s', '%s') );
		
		else:
			$result = $wpdb->insert( EVALUATE_DB_TABLE , $data , array( '%d', '%s', '%d', '%s', '%s') );

		endif;
	}
	
	public static function count_exists( $post_id, $type ) {
		global $wpdb;
		
		$user_id = Evaluate::get_user();;
		
		return $wpdb->get_var($wpdb->prepare("SELECT counter FROM ".EVALUATE_DB_TABLE." WHERE post_id = %d AND user_id = '%s' AND type ='%s';", $post_id, $user_id, $type ) );
	
	}
	
	
	public static function delete_metric( $type ) {
		global $wpdb;
		
		return $wpdb->query( $wpdb->prepare( "DELETE FROM ".EVALUATE_DB_TABLE." WHERE type ='%s'", $type ) );
	}
	
	// count the number of votes that something has both up and down votes
	public static function metric_count( $post_id, $type, $count = null ) {
		global $wpdb;
		
		if( isset( $count ) )
			return $wpdb->get_var($wpdb->prepare("SELECT COUNT(*)  FROM ".EVALUATE_DB_TABLE." WHERE post_id = %d AND type ='%s' AND counter =%d;",$post_id, $type, $count ) );
		else
			return $wpdb->get_var($wpdb->prepare("SELECT COUNT(*)  FROM ".EVALUATE_DB_TABLE." WHERE post_id = %d AND type ='%s';",$post_id, $type ) );

	}
	
	
	public static function metric_sum( $post_id, $type ) {
		global $wpdb;
	
		return $wpdb->get_var( $wpdb->prepare("SELECT SUM(counter) FROM ".EVALUATE_DB_TABLE." WHERE post_id = %d AND type ='%s';",$post_id, $type ) );
		
	}
	
	public static function list_metric( $type, $group_by, $order_by = NULL ) {
		global $wpdb;
		return $wpdb->get_results($wpdb->prepare( 
		"SELECT id, post_id, GROUP_CONCAT( user_id ) as ids, SUM( counter ) as sum, COUNT( id ) as count, date FROM ".EVALUATE_DB_TABLE." WHERE type ='%s' GROUP BY ".$group_by." ORDER BY ".$order_by, $type )  );
	
	}
	
	public static function gmt_time() {

		$default_timezone = date_default_timezone_get();
		
		date_default_timezone_set( 'GMT' );
			
		$date = date( 'Y-m-d H:i:s' );
		
		date_default_timezone_set( $default_timezone );
		
		return $date;
	}
	
	public static function update_option( $option, $value ) {
		
		self::$settings[$option] = $value;
		
		update_option( 'evaluate_settings', self::$settings );
		
	}
	public static function get_option( $option ) {
		
		if( isset( self::$settings[$option]) )
			return self::$settings[$option];
		
		return null; 
		
	}
	
	public static function get_user() {
		global $current_user;
		if( self::$user )
			return self::$user;
		
		if( isset( $current_user ) && isset( $current_user->ID ) && $current_user->ID > 0 ):
			return $current_user->ID;
		
		else:
			
			$cookie_id = 'evaluate_user_'.md5( LOGGED_IN_KEY);
			
			if( $_COOKIE[$cookie_id] ):
				return $_COOKIE[$cookie_id];
			else:
				$time = time(); 
				setcookie( $cookie_id, 'u'.$time, $time+60*60*24*30 );
				return 'u'.$time;
			endif;
			return $_SERVER[ 'REMOTE_ADDR' ];
		endif;
	}
	
	public static function delete_everything() {
		global $wpdb;
		
		// delete_option( 'evaluate_metrics' );
		delete_option( 'evaluate_settings' );
		self::$settings = array();
		
	   	$wpdb->query("DROP TABLE IF EXISTS ".EVALUATE_DB_TABLE);
		// delete the different option
	}
  
  /*
   * removes evaluate table when a blog is being deleted
   * EVALUATE_DB_TABLE does not seem to get initialized if
   * removing blog from network admin panel
   */
  public static function remove_custom_table($tables) {
   global $wpdb;
   $id = isset( $_REQUEST['id'] ) ? intval( $_REQUEST['id'] ) : 0;
   $tables[] = $wpdb->get_blog_prefix($id).'evaluate';
   return $tables;
}


}