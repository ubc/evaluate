<?php
class Evaluate_Content_List_Table extends WP_List_Table {
	
	public $columns = array();
	public $sortable_columns = array();
	public $metric_id;
	public $metric_data;
	
	private $filter;
	
	function __construct() {
		parent::__construct( array(
			'singular' => 'metric', //used when passing actions etc.
			'plural'   => 'metrics',
			'ajax'     => false,
		) );
		
		$this->process_bulk_action();
		
		global $wpdb;
		
		$this->metric_id = $_GET['metric_id'];
		$this->metric = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM '.EVAL_DB_METRICS.' WHERE id=%s', $this->metric_id ) );
		$this->metric_data = Evaluate::get_metric_data( $this->metric );
	}
  
	function get_columns() {
		switch ( $this->metric_data->type ):
		case 'poll':
			$score = __('Top Choice');
			break;
		case 'range':
			$score = __('Average Score');
			break;
		default:
			$score = __('Total Score');
			break;
		endswitch;
		
		return array(
            'cb'    => '<input type="checkbox" />',
			'title'      => __('Title'),
			'author'     => __('Author'),
			'type'       => __('Content Type'),
			'categories' => __('Categories'),
			'score'      => $score,
			'date'       => __('Date'),
		);
	}
  
	function get_sortable_columns() {
		return $sortable_columns = array(
			'title'  => array( 'title',  false ),
			'author' => array( 'author', false ),
			'type'   => array( 'type',   false ),
			'score'  => array( 'score',  false ),
			'date'   => array( 'date',   true  ),
		);
	}
  
	function column_default( $item, $column ) {
		switch ( $column ):
		case 'title':
			return sprintf( '<a href="%s"><strong>%s</strong></a>', $item->permalink, $item->title );
		case 'author':
			return $item->author;
		case 'type':
			return $item->type;
		case 'categories':
			return implode( ', ', $item->categories );
		case 'score':
			return wp_unslash( $item->score );
		case 'date':
			return sprintf( '<abbr title="%s">%s</abbr>', $item->date, date( 'D, M d', strtotime($item->date) ) );
		default:
			return $item->{$column};
		endswitch;
	}
	
	function column_cb( $item ){
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            /*$1%s*/ $this->_args['singular'],  //Let's simply repurpose the table's singular label ("movie")
            /*$2%s*/ $item->ID                //The value of the checkbox should be the record's id
        );
    }
  
	function prepare_items() {
		global $wpdb;
		
		//set up columns
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );
		
		//fetch posts
		$posts = @get_posts( array(
			'numberposts'  => '-1',
			'meta_key'     => 'metric-'.$this->metric_id.'-votes',
			'meta_compare' => '!=',
			'meta_value'   => array( 0 => false ),
			'post_type'    => get_post_types(),
		) );
		
		//preprocess proper info for item objects
		$items = array();
		foreach ( $posts as $post ):
			$total_votes = get_post_meta( $post->ID, 'metric-'.$this->metric_id.'-votes' );
			if ( ! empty( $total_votes[0] ) ):
				$item = new stdClass();
				$item->ID = $post->ID;
				$item->title = $post->post_title;
				
				$author = get_user_by( 'id', $post->post_author );
				$item->author = $author->data->user_nicename;
				
				$item->type = $post->post_type;
				
				$categories = wp_get_post_categories( $post->ID );
				$cats = array();
				foreach ( $categories as $category ):
					$cat = get_category( $category );
					$cats[$cat->cat_ID] = $cat->name;
				endforeach;
				$item->categories = $cats;
				
				$data = Evaluate::get_data_by_id( $this->metric_id, $post->ID );
				switch( $data->type ):
				case 'one-way':
					$item->score = $data->counter;
					break;
				case 'two-way':
					$item->score = $data->counter_total;
					break;
				case 'range':
					$item->score = round( $data->average / $data->length * 100, 1 )."%";
					break;
				case 'poll':
					foreach ( $data->votes as $vote ):
						if ( ! isset($top_vote) || $vote->count > $top_vote->count ):
							$top_vote = $vote;
						endif;
					endforeach;
					$item->score = $data->answers[$top_vote->vote];
					break;
				default:
					$item->score = 0;
					break;
				endswitch;
				
				$item->date = $post->post_date;
				$item->permalink = get_permalink( $post->ID );
				
				$items[] = $item;
			endif;
			
			unset( $item );
		endforeach;
		
		//because we have to do a little preprocessing to the list
		//sort it by the given order
		usort( $items, array( __CLASS__, 'sort' ) );
		
		//apply filters to results
		if ( ! empty( $_GET['filter_content_type'] ) ):
			self::$filter = $wpdb->escape($_GET['filter_content_type']);
			$items = array_filter( $items, array( __CLASS__, 'filter_type' ) );
		endif;
		
		if ( isset( $_GET['cat'] ) && $_GET['cat'] ):
			self::$filter = $wpdb->escape($_GET['cat']);
			$items = array_filter( $items, array( __CLASS__, 'filter_cat' ) );
		endif;
		
		if ( isset( $_GET['filter_users'] ) && $_GET['filter_users'] ):
			self::$filter = $wpdb->escape($_GET['filter_users']);
			$items = array_filter( $items, array( __CLASS__, 'filter_user' ) );
		endif;
		
		if ( isset( $_GET['m'] ) && $_GET['m'] ):
			self::$filter = $wpdb->escape($_GET['m']);
			$items = array_filter( $items, array( __CLASS__, 'filter_month' ) );
		endif;
		
		// Pagination arguments
		$per_page = 10;
		$current_page = $this->get_pagenum();
		$total_items = count( $items );
		
		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
		) );
		
		$start = ( $current_page - 1 ) * $per_page; //slice start index
		
		$this->items = array_slice( $items, $start, $per_page );
	}
	
	private static function sort( $a, $b ) {
		$orderby = ( isset( $_GET['orderby'] ) ? $_GET['orderby'] : 'date' );
		$order = ( isset( $_GET['order'] ) && $_GET['order'] == 'asc' ? 1 : -1 ); //multiplier to enable reverse sorting
		return $order * strcmp( $a->{$orderby}, $b->{$orderby} ); //strcmp returns {-1,0,1} so multiplying this by -1 just reverses the order
	}
	
	private static function filter_type( $item ) {
		return $item->type == self::$filter;
	}
	
	private static function filter_cat( $item ) {
		return array_key_exists( self::$filter, $item->categories );
	}
	
	private static function filter_user( $item ) {
		return $item->author == self::$filter;
	}
	
	private static function filter_month( $item ) {
		$item_date = new DateTime( $item->date );
		self::$filter .= '01';
		$month = new DateTime( self::$filter );
		if ( ! $item_date->diff($month)->invert ):
			return false;
		endif;
		
		$month->add( new DateInterval('P1M') ); //get start of next month
		if ( $item_date->diff($month)->invert ):
			return false;
		endif;
		
		return true;
	}
  
	public function render() {
		// Need to wrap a form around the table for bulk actions
		?>
		<form id="content-list-table" method="get" action="">
			<?php
			$this->prepare_items();
			$this->display();
			?>
			<input type="hidden" name="page" value="evaluate" />
			<input type="hidden" name="view" value="metric" />
			<input type="hidden" name="section" value="content" />
			<input type="hidden" name="metric_id" value="<?php echo $this->metric_id; ?>" />
		</form>
		<?php
	}
	
    function get_bulk_actions() {
        return array(
            'delete'   => 'Delete All Votes',
            'undelete' => 'Restore All Votes',
        );
    }
	
    function process_bulk_action() {
		global $wpdb;
		$index = $this->_args['singular'];
		
        switch ( $this->current_action() ):
		case 'undelete':
			foreach ( $_GET[$index] as $content_id ):
				$where = array( 'content_id' => $content_id );
				$data = array( 'disabled' => 0 );
				$result = $wpdb->update( EVAL_DB_VOTES, $data, $where );
			endforeach;
			break;
		case 'delete':
			foreach ( $_GET[$index] as $content_id ):
				$where = array( 'content_id' => $content_id );
				$data = array( 'disabled' => 1 );
				$result = $wpdb->update( EVAL_DB_VOTES, $data, $where );
			endforeach;
			break;
		default:
			break;
        endswitch;
    }
  
	function extra_tablenav( $which ) {
		if ( $which == 'top' ):
			$filter_content_type = ( isset( $_GET['filter_content_type'] ) ? $_GET['filter_content_type'] : '' );
			?>
			<div class="alignleft actions">
				<select id="filter_content_type" name="filter_content_type">
					<option value="" <?php selected( $filter_content_type == '' ); ?>>Show all content types</option>
					<?php
						$post_types = get_post_types( '', 'names' );
						foreach ( $post_types as $post_type ):
							?>
							<option value="<?php echo $post_type; ?>" <?php selected( $filter_content_type == $post_type ); ?>><?php echo $post_type; ?></option>
							<?php
						endforeach;
					?>
				</select>
				
				<?php
					$cat = ( isset( $_GET['cat'] ) ? $_GET['cat'] : '0' );
					$dropdown_options = array(
						'show_option_all' => __('Show all categories'),
						'hide_empty'      => 0,
						'hierarchical'    => 1,
						'show_count'      => 0,
						'orderby'         => 'name',
						'selected'        => $cat,
					);
					wp_dropdown_categories( $dropdown_options );
					
					$userlist_args = array(
						'blog_id' => get_current_blog_id(),
						'orderby' => 'nicename',
						'order'   => 'ASC',
						'search'  => '',
						'fields'  => 'all',
					);
					
					$userlist = get_users( $userlist_args );
					$filter_users = ( isset( $_GET['filter_users'] ) ? $_GET['filter_users'] : '' );
				?>
				
				<select id="filter_users" name="filter_users">
					<option value="" <?php echo ($filter_users == '' ? 'selected="selected"' : ''); ?>>Show all users</option>
					<?php foreach ( $userlist as $user ): ?>
						<option value="<?php echo $user->user_nicename; ?>" <?php echo ($filter_users == $user->user_nicename ? 'selected="selected"' : ''); ?>><?php echo $user->user_nicename; ?></option>
					<?php endforeach; ?>
				</select>
				<?php
				$params = unserialize( $this->metric->params );
				echo $this->months_dropdown( $params['content_types'] );
				?>
				<input id="filter_submit" class="button-secondary" type="submit" value="Filter" name="filter">
			</div>
			<?php
		endif;
		
		if ( $which == 'bottom' ):
			//echo 'after table';
		endif;
	}

	/**
	 * Display a monthly dropdown for filtering items
	 *
	 * @since 3.1.0
	 * @access protected
	 */
	function months_dropdown( $post_types ) {
		global $wpdb, $wp_locale;
		
		$sql = "
			SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month
			FROM $wpdb->posts
			WHERE post_type IN (".implode( ', ', array_fill( 0, count( $post_types ), '%s' ) ).")
			ORDER BY post_date DESC
		";
		
		$statement = call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $sql ), $post_types ) );
		$months = $wpdb->get_results( $statement );
		
		$month_count = count( $months );
		
		if ( ! $month_count || ( 1 == $month_count && 0 == $months[0]->month ) ):
			return;
		endif;
		
		$m = isset( $_GET['m'] ) ? (int) $_GET['m'] : 0;
		?>
		<select name='m'>
			<option<?php selected( $m, 0 ); ?> value='0'><?php _e( 'Show all dates' ); ?></option>
		<?php
		foreach ( $months as $arc_row ) {
			if ( 0 == $arc_row->year ):
				continue;
			endif;
			
			$month = zeroise( $arc_row->month, 2 );
			$year = $arc_row->year;
			
			printf( "<option %s value='%s'>%s</option>\n",
				selected( $m, $year . $month, false ),
				esc_attr( $arc_row->year . $month ),
				/* translators: 1: month name, 2: 4-digit year */
				sprintf( __( '%1$s %2$d' ), $wp_locale->get_month( $month ), $year )
			);
		}
		?>
		</select>
		<?php
	}
}
