<?php 


class Evaluate {

	static $options = array();
	static $settings = array();
  	
	public static function init() {
		
		self::$options = get_option( 'evaluate_metrics' ); 
	    self::$settings = get_option( 'evaluate_settings' );
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
			foreach( self::$options as $metric ):
				
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
	
	public static function display_metic( $metric ) {
		
		$html = "";
		switch( $metric['type'] ) {
		
			case 'one-way':
				$html =	Evaluate::one_way( $metric );
			break;
			case 'two-way':
				$html =	Evaluate::two_way( $metric );
			break;
			case 'range':
				$html =	Evaluate::range( $metric );
			break;
			case 'poll':
				$html =	Evaluate::poll( $metric );
			break;
		}
		
		return $html;
	}
	
	public static function one_way( $metric, $post_id = null ) {
		
		$type = $metric['one-way'];
		$url = "#";
		$votes = 0;
		
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
		$url = Evaluate::url( $type, 1 );
		return '<div class="rate-shell"><span class="rate-count">'.$votes .'</span> <a href="'.$url.'" class="'.$class_attr.'" title="'.$title.'">'.$title.'</a></div>';
	}
	
	public static function url( $type, $count, $action) {
		global $post;
		if( isset($post) ) :
			
			$post_id = $post->ID;
			// do we add it or remove it?
			// we can only do that if we know the user eather though cookie or though user id? 
			
		endif;
		$once = wp_create_nonce  ( 'metric-'.$post_id.'-'.$type.'-'.$count);
		
		$url = "?metric=".$action."&type=".$type."&post=".$post_id."&count=".$count."&verify=".$once;
		
	}
	
	public static function two_way( $metric, $post_id = null) {
		$type = $metric['two-way'];
		$url = "#";
		$up_votes = 0;
		$down_votes = 0;
		if($post_id)
			$id_attr = "rate-".$type."-".$post_id;
		
		$class_attr = 'rate two-way '.$type;
		
		
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
		$html 	= '<span class="rate-count">'.$up_votes .'</span> <a href="'.$url.'" class="'.$class_attr. ' ' .$type.'-up" title="'.$up.'">'.$up.'</a> ';
		return  '<div class="rate-shell">'.$html .'<span class="rate-count">'. $down_votes.'</span> <a href="'.$url.'" class="'.$class_attr. ' '.$type.'-down" title="'.$down.'">'.$down.'</a></div> ';
	}
	
	public static function range() {
		
		$html = '<span class="inline-rating">
			<ul class="star-rating small-star">
				<li class="current-rating" style="width:50%;">Currently 1.5/5 Stars.</li>
				<li><a href="#" title="1 star out of 5" class="one-star">1</a></li>
				<li><a href="#" title="2 stars out of 5" class="two-stars">2</a></li>
				<li><a href="#" title="3 stars out of 5" class="three-stars">3</a></li>
				<li><a href="#" title="4 stars out of 5" class="four-stars">4</a></li>
				<li><a href="#" title="5 stars out of 5" class="five-stars">5</a></li>
			</ul>
		</span>';
		return $html;
	}
	public static function poll() {
		return "poll";
		
		?>
		<form action="">
			 <label><input type="radio"  name="poll-1" /> </label>
			 <label><input type="radio"  name="poll-1" /> </label>
			 <label><input type="radio"  name="poll-1" /> </label>
			 <label><input type="radio"  name="poll-1" /> </label>
		</form>
		<?php
	}
	
	
	
	/* DB Helper functions */
	public static function install() {
	  	global $wpdb;
	  	if( EVALUATE_DB_VERSION > Evaluate::get_option( 'db_version' ) ):
			
			$sql = "CREATE TABLE " . EVALUATE_DB_TABLE . " (
					id mediumint(9) NOT NULL AUTO_INCREMENT,
					post_id bigint(11) DEFAULT '0' NOT NULL,
					user_id bigint(11) DEFAULT '0' NOT NULL,
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
	
	public static function add_count( $post_id, $user_id, $count, $type ) {
		
		$post_count 	= post_count( $post_id, $type ) + 1;
		$post_sum 		= post_sum( $post_id, $type ) + $count;
		
		// save the number of votes to better get popular votes 
		add_post_meta( $post_id, 'count_'.$type, $votes, true ) or update_post_meta( $post_id, 'count_'.$type, $votes );
		add_post_meta( $post_id, 'sum_'.$type, $total, true ) or update_post_meta( $post_id, 'sum_'.$type, $total );
		
		// for knowing when to update this 
		$date = gmt_time();
		
		update_option( 'updated', $date );
		
	}
	public static function delete_count( $post_id, $user_id, $count, $type ) {
		
		$post_count 	= post_count( $post_id, $type ) - 1;
		$post_sum 		= post_sum( $post_id, $type ) + $count;
		
		// save the number of votes to better get popular votes 
		update_post_meta( $post_id, 'count_'.$type, $votes );
		update_post_meta( $post_id, 'sum_'.$type, $total );
		
		// for knowing when to update this 
		$date = gmt_time();
		
		update_option( 'updated', $date );
	}
	
	
	public static function gmt_time(){

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
	public static function get_option( $option  ) {
		
		if( isset( self::$settings[$option]) )
			return self::$settings[$option];
		
		return null; 
		
	}
	

}