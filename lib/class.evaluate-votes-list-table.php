<?php

class Evaluate_Votes_List_Table extends WP_List_Table {

  public $columns = array();
  public $sortable_columns = array();
  
  function __construct() {
    parent::__construct(array(
        'singular' => 'vote',
        'plural' => 'votes',
        'ajax' => false
    ));
  }
  
  function get_columns() {
    return $columns = array(
        'name' => __('Voter Name'),
        'title' => __('Content Title'),
        'vote' => __('Vote'),
        'date' => __('Date')
    );
  }
  
  function get_sortable_columns() {
    return $sortable_columns = array(
        'name' => array('name', false),
        'title' => array('title', false),
        'date' => array('date', true)
    );
  }
  
  function column_default($item, $column) {
    return $item->{$column};
  }
  
  function prepare_items() {
    global $wpdb;
    
    $columns = $this->get_columns();
    $hidden = array();
    $sortable = $this->get_sortable_columns();
    
    $metric_type = $_GET['metric'];
    $id = $_GET['id'];
    $section = $_GET['section'];
    
    if($section == 'content') {
      $query = $wpdb->prepare('SELECT * FROM ' . EVALUATE_DB_TABLE . ' WHERE post_id=%s AND type=%s', $id, $metric_type);
      $hidden[] = 'title';
    } else if ($section == 'user') {
      $query = $wpdb->prepare('SELECT * FROM ' . EVALUATE_DB_TABLE . ' WHERE user_id=%s AND type=%s', $id, $metric_type);
      $hidden[] = 'name';
    }
    
    $this->_column_headers = array($columns, $hidden, $sortable);
    
    $this->items = array();
    $items = $wpdb->get_results($query);
    foreach($items as $item) {
      $obj = new stdClass();
      $user = get_user_by('id', $item->user_id);
      $post = get_post($item->post_id);
      $obj->name = $user->user_nicename;
      $obj->title = $post->post_title;
      $obj->vote = $item->counter;
      $obj->date = $item->date;
      $this->items[] = $obj;
    }
  }
  
  function extra_tablenav($which) {
    
  }
  
  public function render() {
    $this->prepare_items();
    $this->display();
  }
  
}

?>
