<?php

class Evaluate_Users_List_Table extends WP_List_Table {
	
	public $columns = array();
	public $sortable_columns = array();
	public $metric_id;
  
	function __construct() {
		parent::__construct(array(
			'singular' => 'metric', //used when passing actions etc.
			'plural'   => 'metrics',
			'ajax'     => false,
		));
		
		$this->metric_id = $_GET['metric_id'];
	}
  
	function get_columns() {
		return $columns = array(
			'title' => __('Title'),
			'voter' => __('Voter'),
			'vote'  => __('Vote Value'),
			'date'  => __('Date')
		);
	}
  
	function get_sortable_columns() {
		return $sortable_columns = array(
			'title' => array( 'title', false ),
			'voter' => array( 'voter', false ),
			'vote'  => array( 'vote', false ),
			'date'  => array( 'date', true ),
		);
	}
  
	function column_default( $item, $column ) {
		switch ( $column ):
		case 'title':
			return sprintf( '<a href="%s"><strong>%s</strong></a>', $item->permalink, $item->title );
		case 'date':
			return sprintf( '<abbr title="%s">%s</abbr>', $item->date, date( 'D, M d', strtotime( $item->date ) ) );
		default:
			return $item->{$column};
		endswitch;
	}
  
	function prepare_items() {
		global $wpdb;
		
		//set up columns
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );
		
		$query = $wpdb->prepare( 'SELECT * FROM '.EVAL_DB_VOTES.' WHERE metric_id=%s', $this->metric_id );
		$results = $wpdb->get_results($query);
		
		$items = array();
		foreach ( $results as $result ):
			$item = new stdClass();
			$item->title = get_the_title($result->content_id);
			$item->permalink = get_permalink($result->content_id);
			$author = get_user_by('id', $result->user_id);
			$item->voter = ( $author ? $author->data->user_nicename : $result->user_id );
			$item->vote = $result->vote;
			$item->date = $result->date;
			
			$items[] = $item;
			unset($item);
		endforeach;
		
		//sort it by the given order
		usort( $items, function( $a, $b ) {
			$orderby = ( isset( $_GET['orderby'] ) ? $_GET['orderby'] : 'date' );
			$order = ( isset( $_GET['order'] ) && $_GET['order'] == 'asc' ? 1 : -1 ); //multiplier to enable reverse sorting
			return $order * strcmp( $a->{$orderby}, $b->{$orderby} ); //strcmp returns {-1,0,1} so multiplying this by -1 just reverses the order
		} );
		
		if ( isset( $_GET['title_filter'] ) && $_GET['title_filter'] ):
			$title_filter = $wpdb->escape( $_GET['title_filter'] );
			$items = array_filter( $items, function( $item ) {
				return strpos( $item->title, $title_filter ) !== false;
			});
		endif;
		
		if (isset($_GET['filter_users']) && $_GET['filter_users']):
			$user_filter = $wpdb->escape($_GET['filter_users']);
			$items = array_filter( $items, function( $item ) {
				return $item->voter == $user_filter;
			} );
		endif;
		
		//pagination arguments
		$per_page = 10;
		$current_page = $this->get_pagenum();
		$total_items = count( $items );
		
		$this->set_pagination_args(array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
		));
			
		$start = ($current_page - 1) * $per_page; //slice start index
		$this->items = array_slice($items, $start, $per_page);
	}
	
	public function render() {
		// Need to wrap a form around the table for bulk actions
		?>
		<form id="user-list-table" method="get" action="">
			<?php
				$this->prepare_items();
				$this->display();
			?>
			<input type="hidden" name="page" value="evaluate" />
			<input type="hidden" name="view" value="metric" />
			<input type="hidden" name="section" value="user" />
			<input type="hidden" name="metric_id" value="<?php echo $this->metric_id; ?>" />
		</form>
		<?php
	}
  
	function extra_tablenav( $which ) {
		if ( $which == 'top' ):
			?>
			<div class="alignleft actions">
				<label>
					Filter by Title
					<input type="text" name="title_filter" value="<?php if ( isset( $_GET['title_filter'] ) ) echo $_GET['title_filter']; ?>" />
				</label>
				<?php
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
					<option value="" <?php selected( $filter_users == '' ); ?>>Show all users</option>
					<?php foreach ( $userlist as $user ): ?>
						<option value="<?php echo $user->user_nicename; ?>" <?php selected( $filter_users == $user->user_nicename ); ?>><?php echo $user->user_nicename; ?></option>
					<?php endforeach; ?>
				</select>
				<input id="filter_submit" class="button-secondary" type="submit" value="Filter" name="filter">
			</div>
			<?php
		endif;
	}
}
