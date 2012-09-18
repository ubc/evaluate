<?php

class Evaluate_Users_List_Table extends WP_List_Table {

  public $columns = array();
  public $sortable_columns = array();

  function __construct() {
    parent::__construct(array(
        'singular' => 'user',
        'plural' => 'users',
        'ajax' => false
    ));
  }

  function get_columns() {
    return $columns = array(
        'user_nicename' => __('Author'),
        'user_total_votes' => __('Total Votes from Author'),
        'date' => __('Date')
    );
  }

  function get_sortable_columns() {
    return $sortable_columns = array(
        'user_nicename' => array('user_nicename', false),
        'user_total_votes' => array('user_total_votes', false),
        'date' => array('date', true)
    );
  }

  function column_default($item, $column) {
    switch ($column) {
      case 'date':
        return sprintf('<abbr title="%s">%s</abbr>', date('Y/m/d H:i:s', strtotime($item->date)), date('Y/m/d', strtotime($item->date)));
        break;

      default:
        return sprintf('%s', $item->{$column});
        break;
    }
  }
  
  function column_user_nicename($item) {
    $row_actions = array(
        'user_vote_details' => sprintf('<a href="?page=evaluate&do=votes&section=user&metric=%s&id=%s">%s</a>', $_GET['metric'], $item->user_id, 'View User Votes')
    );
    return sprintf('<strong>%s</strong>%s', $item->user_nicename, $this->row_actions($row_actions));
  }

  function prepare_items() {
    global $wpdb;

    //set table columns
    $columns = $this->get_columns();
    $hidden = array();
    $sortable = $this->get_sortable_columns();
    $this->_column_headers = array($columns, $hidden, $sortable);

    //get metric type
    $type = $_GET['metric'];
    
    //get sorting preference
    $orderby = ($_GET['orderby'] ? $_GET['orderby'] : 'Eval.date');
    $order = ($_GET['order'] ? $_GET['order'] : 'DESC');
    
    $wpdb->escape($orderby);
    $wpdb->escape($order);
    
    if ($_GET['filter_users']) { //filter by user string
      $user = $_GET['filter_users'];
      $wpdb->escape($user);
      $filter_users = " AND User.user_nicename='$user'";
    } else {
      $filter_users = '';
    }

    $query = $wpdb->prepare("SELECT User.user_nicename, Eval.user_id,"
            . " (SELECT COUNT( C.user_id ) FROM " . EVALUATE_DB_TABLE . " C WHERE C.user_id = Eval.user_id AND C.type = '%s') as user_total_votes, Eval.date"
            . " FROM " . EVALUATE_DB_TABLE . " Eval"
            . " INNER JOIN $wpdb->users User ON (Eval.user_id = User.id)"
            . " WHERE Eval.type = '%s' $filter_users"
            . " GROUP BY Eval.user_id"
            . " ORDER BY $orderby $order"
            , $type, $type);

    $this->items = $wpdb->get_results($query);
    
    //set pagination properties
    $per_page = '10';
    $cur_page = $this->get_pagenum();
    $total_items = $wpdb->num_rows;

    $this->set_pagination_args(array(
        'per_page' => $per_page,
        'total_items' => $total_items
    ));

    $start = ($cur_page - 1) * $per_page;
    $end = $start + $per_page;
    $query .= " LIMIT $start, $end";
    $this->items = $wpdb->get_results($query); //fetch results with pagination limits this time
  }

  function extra_tablenav($which) {
    if ($which == 'top') {
      $userlist_args = array(
          'blog_id' => get_current_blog_id(),
          'orderby' => 'nicename',
          'order' => 'ASC',
          'search' => '',
          'fields' => 'all'
      );
      $userlist = get_users($userlist_args);
      $filter_users = ($_GET['filter_users'] ? $_GET['filter_users'] : '');
      ?>
      <div class="alignleft actions">
        <select id="filter_users" name="filter_users">
          <option value="" <?php echo ($filter_users == '' ? 'selected="selected"' : ''); ?>>Show all users</option>
          <?php
          foreach ($userlist as $user) {
            ?>
            <option value="<?php echo $user->user_nicename; ?>" <?php echo ($filter_users == $user->user_nicename ? 'selected="selected"' : ''); ?>><?php echo $user->user_nicename; ?></option>
          <?php } ?>
        </select>
        <input id="filter_submit" class="button-secondary" type="submit" value="Filter" name="filter">
      </div>
      <?php
    }
  }

  public function render() {
    ?>
    <script>
      //how to remove these from the url?
      function removeRefAndNonce() {
        jQuery('input[name=_wp_http_referer]').prop('disabled', true);
        jQuery('input[name=_wpnonce]').prop('disabled', true)
      }
    </script>
    <form id="metrics-filter" method="get" action="" onSubmit="removeRefAndNonce();">
      <input type="hidden" name="page" value="<?php echo $_GET['page']; ?>" />
      <input type="hidden" name="do" value="<?php echo $_GET['do']; ?>" />
      <input type="hidden" name="metric" value="<?php echo $_GET['metric']; ?>" />
      <input type="hidden" name="section" value="user" />
      <?php
      $this->prepare_items();
      $this->display();
      ?>
    </form>
    <?php
  }

}
?>
