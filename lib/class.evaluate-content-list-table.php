<?php

class Evaluate_Content_List_Table extends WP_List_Table {

  public $columns = array();
  public $sortable_columns = array();
  public $metric_id;

  function __construct() {
    parent::__construct(array(
        'singular' => 'metric', //used when passing actions etc.
        'plural' => 'metrics',
        'ajax' => false
    ));
    $this->metric_id = $_GET['metric'];
  }

  function get_columns() {
    return $columns = array(
        'title' => __('Title'),
        'author' => __('Author'),
        'type' => __('Content Type'),
        'categories' => __('Categories'),
        'score' => __('Total Score'),
        'date' => __('Date')
    );
  }

  function get_sortable_columns() {
    return $sortable_columns = array(
        'title' => array('title', false),
        'author' => array('author', false),
        'type' => array('type', false),
        'score' => array('score', false),
        'date' => array('date', true)
    );
  }

  function column_default($item, $column) {
    switch ($column) {
      case 'title':
        return sprintf('<a href="%s"><strong>%s</strong></a>', $item->permalink, $item->title);
        break;

      case 'author':
        return $item->author;
        break;

      case 'type':
        return $item->type;
        break;

      case 'categories':
        return $item->categories;
        break;

      case 'score':
        return $item->score;
        break;

      case 'date':
        return sprintf('<abbr title="%s">%s</abbr>', $item->date, date('D, M d', strtotime($item->date)));
        break;

      default:
        return $item->{$column};
    }
  }

  function prepare_items() {
    global $wpdb;

    //set up columns
    $columns = $this->get_columns();
    $hidden = array();
    $sortable = $this->get_sortable_columns();
    $this->_column_headers = array($columns, $hidden, $sortable);

    $posts = get_posts(array(
        'meta_key' => 'metrics',
        'post_status' => 'publish',
        'post_type' => array(
            'post',
            'page'
        )
            ));

    $items = array();
    foreach ($posts as $post) {
      $post_meta = get_post_meta($post->ID, 'metrics', true);
      $post_meta = unserialize($post_meta);
      if (array_key_exists($this->metric_id, $post_meta)) {
        $item->title = $post->post_title;

        $author = get_user_by('id', $post->post_author);
        $item->author = $author->data->user_nicename;

        $item->type = $post->post_type;

        $categories = wp_get_post_categories($post->ID);
        $cats = array();
        foreach ($categories as $c) {
          $cat = get_category($c);
          $cats[] = $cat->name;
        }
        $item->categories = implode(', ', $cats);

        $score = $wpdb->get_var($wpdb->prepare('SELECT SUM(vote) FROM ' . EVAL_DB_VOTES . ' WHERE metric_id=%s AND content_id=%s', $this->metric_id, $post->ID));
        if ($score) {
          $item->score = $score;
        } else {
          $item->score = 0;
        }

        $item->date = $post->post_date;

        $item->permalink = get_permalink($post->ID);

        $items[] = $item;
        unset($item);
      }
    }

    //get order if exists or set default
    $orderby = (isset($_GET['orderby']) ? $_GET['orderby'] : 'date');
    $order = (isset($_GET['order']) ? $_GET['order'] : 'desc');

    usort($items, function($a, $b) {
              $orderby = (isset($_GET['orderby']) ? $_GET['orderby'] : 'date');
              $order = (isset($_GET['order']) && $_GET['order'] == 'asc' ? 1 : -1);
              return $order * strcmp($a->{$orderby}, $b->{$orderby});
            });

    //pagination arguments
    $per_page = 10;
    $current_page = $this->get_pagenum();
    $total_items = count($items);
    
    $this->set_pagination_args(array(
        'total_items' => $total_items,
        'per_page' => $per_page
    ));
    
    $start = ($current_page-1)*$per_page;
    $end = $start + $per_page;
    
    $items = array_slice($items, $start, $per_page);

    $this->items = $items;
  }

  public function render() {
    //need to wrap a form around the table for bulk actions
    ?>
    <form id="content-list-table" method="post" action="options-general.php?page=evaluate&view=metric">
      <?php
      $this->prepare_items();
      $this->display();
      ?>
    </form>
    <?php
  }

}
?>
