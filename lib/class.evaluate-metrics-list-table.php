<?php

class Evaluate_Metrics_List_Table extends WP_List_Table {

  public $columns = array();
  public $sortable_columns = array();

  function __construct() {
    parent::__construct(array(
        'singular' => 'metric',
        'plural' => 'metrics',
        'ajax' => false
    ));
  }

  function get_columns() {
    return $columns = array(
        'cb' => '<input type="checkbox" />',
        'nicename' => __('Name'),
        'type' => __('Type'),
        'style' => __('Style'),
        'post_type' => __('Post Types'),
        'created' => __('Created')
    );
  }

  function get_sortable_columns() {
    return $sortable_columns = array(
        'nicename' => array('nicename', false),
        'type' => array('type', false),
        'style' => array('style', false),
        'created' => array('created', true)
    );
  }

  function get_bulk_actions() {
    return $actions = array(
        'delete' => 'Delete'
    );
  }

  function column_cb($item) {
    return sprintf('<input type="checkbox" name="metric[]" value="%s" />', $item->slug);
  }

  function column_default($item, $column) {
    switch ($column) {
      case 'nicename':
        $name_link = sprintf('<a href="?page=evaluate&view=metric&metric=%s"><b>%s</b></a>', $item->slug, $item->nicename);
        $row_actions = array(
            'view' => sprintf('<a href="?page=evaluate&view=metric&metric=%s">View Details</a>', $item->slug),
            'edit' => sprintf('<a href="?page=evaluate&view=edit&metric=%s">Edit</a>', $item->slug),
            'delete' => sprintf('<span class="trash"><a href="?page=evaluate&view=main&action=delete&metric=%s&nonce_delete=%s">Delete</a></span>', $item->slug, wp_create_nonce('evaluate-delete-'.$item->slug))
        );
        return sprintf('%s %s', $name_link, $this->row_actions($row_actions));
        break;

      case 'post_type':
        $params = unserialize($item->params);
        if (isset($params['content_types'])) {
          return sprintf('%s', implode(', ', $params['content_types']));
        } else {
          return sprintf('No content type.');
        }
        break;

      case 'created':
        return sprintf('<abbr title="%s">%s</abbr>', $item->created, date('D, M d', strtotime($item->created)));
        break;

      default:
        return sprintf('%s', $item->{$column});
    }
  }

  function extra_tablenav($which) {
    if ($which == 'top') {
      echo 'before navigation';
    }

    if ($which == 'bottom') {
      echo 'after navigation';
    }
  }

  function prepare_items() {
    global $wpdb;

    //set up columns
    $columns = $this->get_columns();
    $hidden = array(); //we don't want any hidden columns
    $sortable = $this->get_sortable_columns();
    $this->_column_headers = array($columns, $hidden, $sortable);

    $orderby = (isset($_GET['orderby']) ? $_GET['orderby'] : 'created');
    $order = (isset($_GET['order']) ? $_GET['order'] : 'desc');
    //prepare query
    $query = $wpdb->prepare(
            "SELECT * FROM " . EVAL_DB_METRICS . ""
            . ' ORDER BY ' . $orderby . ' ' . $order
    );

    $this->items = $wpdb->get_results($query);
  }

  public function render() {
    ?>
    <form id="metrics-list-table" method="post" action="">
      <?php
      $this->prepare_items();
      $this->display();
      ?>
    </form>
    <?php
  }

}
?>
