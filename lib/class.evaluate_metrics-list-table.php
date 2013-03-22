<?php

/**
 * list table that extends WP_List_Table to display metrics on the main page
 * this keeps admin tables consistent with wp defaults
 */
class Evaluate_Metrics_List_Table extends WP_List_Table {
	
	public $columns = array();
	public $sortable_columns = array();
  
	function __construct() {
		parent::__construct( array(
			'singular' => 'metric', //used when passing actions etc.
			'plural'   => 'metrics',
			'ajax'     => false,
		) );
	}
  
	function get_columns() {
		return $columns = array(
			'cb'        => '<input type="checkbox" />',
			'nicename'  => __('Name'),
			'type'      => __('Type'),
			'style'     => __('Style'),
			'post_type' => __('Post Types'),
			'created'   => __('Created')
		);
	}
  
	function get_sortable_columns() {
		return $sortable_columns = array(
			'nicename' => array( 'nicename', false ),
			'type'     => array( 'type', false ),
			'style'    => array( 'style', false ),
			'created'  => array( 'created', true ),
		);
	}
  
	function get_bulk_actions() {
		return $actions = array(
			'delete' => 'Delete',
		);
	}
  
	/**
	 * extra column for the checkbox next to each row
	 */
	function column_cb($item) {
		return sprintf( '<input type="checkbox" name="metric[]" value="%s" />', $item->id );
	}
  
	function column_default($item, $column) {
		switch ( $column ):
			case 'nicename':
				$name_link = sprintf( '<a href="?page=evaluate&view=metric&metric=%s"><b>%s</b></a>', $item->id, $item->nicename );
				$row_actions = array(
					'view'   => sprintf( '<a href="?page=evaluate&view=metric&metric_id=%s">View Details</a>', $item->id ),
					'edit'   => sprintf( '<a href="?page=evaluate&view=form&metric_id=%s">Edit</a>', $item->id ),
					'delete' => sprintf( '<span class="trash"><a href="?page=evaluate&view=main&action=delete&metric_id=%s&_wpnonce=%s">Delete</a></span>', $item->id, wp_create_nonce( 'evaluate-delete-' . $item->id ) ),
				);
				
				return sprintf( '%s %s', $name_link, $this->row_actions( $row_actions ) );
			case 'post_type':
				$params = unserialize( $item->params );
				if ( isset( $params['content_types'] ) ):
					return sprintf( '%s', implode( ', ', $params['content_types'] ) );
				else:
					return sprintf( 'No content type.' );
				endif;
			case 'created':
				return sprintf( '<abbr title="%s">%s</abbr>', $item->created, date( 'D, M d', strtotime( $item->created ) ) );
			default:
				return sprintf( '%s', $item->{$column} );
		endswitch;
	}
  
	function extra_tablenav( $which ) {
		if ( $which == 'top' ):
			// echo 'before navigation';
		endif;
		
		if ( $which == 'bottom' ):
			// echo 'after navigation';
		endif;
	}
  
	function prepare_items() {
		global $wpdb;
		
		//set up columns
		$columns = $this->get_columns();
		$hidden = array(); //we don't want any hidden columns
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );
		
		//get order if exists or set default
		$orderby = ( isset( $_GET['orderby'] ) ? $_GET['orderby'] : 'created' );
		$order = ( isset( $_GET['order'] ) ? $_GET['order'] : 'desc' );
		
		//pagination arguments
		$per_page = 10;
		$current_page = $this->get_pagenum();
		$total_items = $wpdb->get_var( 'SELECT COUNT(*) FROM '.EVAL_DB_METRICS );
		
		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
		) );
		
		//prepare query
		$start = ($current_page - 1) * $per_page;
		$end = $start + $per_page;
		$this->items = $wpdb->get_results( 'SELECT * FROM '.EVAL_DB_METRICS.' ORDER BY '.$wpdb->escape($orderby).' '.$wpdb->escape($order).' LIMIT '.$start.', '.$end );
	}
  
	public function render() {
		// Need to wrap a form around the table for bulk actions
		?>
		<form id="metrics-list-table" method="post" action="options-general.php?page=evaluate&view=main">
			<?php
				$this->prepare_items();
				$this->display();
			?>
		</form>
		<?php
	}
}
