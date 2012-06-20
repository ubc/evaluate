<?php 


class Evaluate {

  
	public static function init() {
	
	
	  
	}
  	
	public static function install() {
		  
	}
	
	public static function the_content( $content ) {
		return $content. $vote;
	
	}
	
	public static function display_evaluation_metic( $metric ) {
		
		
		switch( $metric['type'] ) {
		
			case 'one-way':
				Evaluate::one_way( $metric['one-way'] );
			break;
			case 'two-way':
				Evaluate::one_way();
			break;
			case 'range':
				Evaluate::range();
			break;
			case 'poll':
				Evaluate::poll();
			break;
		}
	}
	
	public static function one_way( $type = 'thumb', $post_id = null ) {
		
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
		return $votes.' <a href="'.$url.'" class="'.$class_attr.'" title="'.$title.'"><span>'.$title.'</span></a>';
	}
	
	public static function two_way( $type = 'thumb', $post_id = null) {
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
		$html 	= $up_votes .' <a href="'.$url.'" class="'.$class_attr.' up" title="'.$up.'"><span>'.$up.'</span></a> ';
		return  $html . $down_votes.' <a href="'.$url.'" class="'.$class_attr.' down" title="'.$down.'"><span>'.$down.'</span></a> ';
	}
	public static function range() {
		?>
		<span class="inline-rating">
			<ul class="star-rating small-star">
				<li class="current-rating" style="width:30%;">Currently 1.5/5 Stars.</li>
				<li><a href="#" title="1 star out of 5" class="one-star">1</a></li>
				<li><a href="#" title="2 stars out of 5" class="two-stars">2</a></li>
				<li><a href="#" title="3 stars out of 5" class="three-stars">3</a></li>
				<li><a href="#" title="4 stars out of 5" class="four-stars">4</a></li>
				<li><a href="#" title="5 stars out of 5" class="five-stars">5</a></li>
			</ul>
		</span>
		<?php
	}
	public static function poll() {
		?>
		<form action="">
			 <label><input type="radio"  name="poll-1" /> </label>
			 <label><input type="radio"  name="poll-1" /> </label>
			 <label><input type="radio"  name="poll-1" /> </label>
			 <label><input type="radio"  name="poll-1" /> </label>
		</form>
		<?php
	}
}