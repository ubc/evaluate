<?php

class Evaluate_Content_List_Table extends WP_List_Table {

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
        /* 'cb' => '<input type="checkbox" />', Disabled unless bulk actions are required */
        'post_title' => __('Title'),
        'score' => __('Score'),
        'total_votes' => __('Total Votes'),
        'post_type' => __('Content Type'),
        'user_nicename' => __('Author'),
        'taxonomy_ids' => __('Categories'),
        'post_date' => __('Date')
    );
  }

  public function get_sortable_columns() {
    return $sortable_columns = array(
        'post_title' => array('post_title', false),
        'score' => array('score', true),
        'total_votes' => array('total_votes', false),
        'post_type' => array('post_type', false),
        'user_nicename' => array('user_nicename', false),
        'post_date' => array('post_date', false)
    );
  }

  function column_default($item, $column) {
    switch ($column) {
      case 'post_date':
        return sprintf('<abbr title="%s">%s</abbr>', date('Y/m/d H:i:s', strtotime($item->post_date)), date('Y/m/d', strtotime($item->post_date)));
        break;

      case 'taxonomy_ids':
        $categories = explode(',', $item->taxonomy_ids);
        $categories_readable = array();
        foreach ($categories as $category) {
          $category_name = get_the_category_by_ID($category);
          if ($category_name) {
            $categories_readable[] = $category_name;
          }
        }
        $categories_string = implode(', ', $categories_readable);
        return $categories_string;
        break;

      default:
        return sprintf('%s', $item->{$column});
        break;
    }
  }

  /* Disabled unless required
    function column_cb($item) {
    return sprintf('<input type="checkbox" name="selected[]" value="%s" />', $item->post_id);
    }
   */

  function column_post_title($item) {
    $row_actions = array(
        'vote_breakdown' => sprintf('<a href="?page=evaluate&do=votes&post_id=%s">%s</a>', $item->post_id, 'View Vote Breakdown')
    );
    return sprintf('<a class="row-title" href="%s">%s</a>%s', get_permalink($item->post_id), $item->post_title, $this->row_actions($row_actions));
  }

  /* Disabled unless required
    function get_bulk_actions() {
    return $bulk_actions = array(
    'vote_breakdown' => __('Vote Breakdown')
    );
    }
   */

  function prepare_items() {
    global $wpdb;

    //set table columns
    $columns = $this->get_columns();
    $hidden = array();
    $sortable = $this->get_sortable_columns();
    $this->_column_headers = array($columns, $hidden, $sortable);

    //build query
    $type = $_GET['metric'];
    $orderby = ($_GET['orderby'] ? $_GET['orderby'] : 'score');
    $order = ($_GET['order'] ? $_GET['order'] : 'DESC');

    if ($_GET['cat']) { //filter by category string
      $cat = $_GET['cat'];
      $wpdb->escape($cat);
      $filter_category = " AND Relationships.term_taxonomy_id=$cat";
    } else {
      $filter_category = '';
    }

    if ($_GET['filter_content_type']) { //filter by content type string
      $content = $_GET['filter_content_type'];
      $wpdb->escape($content);
      $filter_content_type = " AND Post.post_type='$content'";
    } else {
      $filter_content_type = '';
    }

    if ($_GET['filter_users']) { //filter by user string
      $user = $_GET['filter_users'];
      $wpdb->escape($user);
      $filter_users = " AND User.user_nicename='$user'";
    } else {
      $filter_users = '';
    }

    if ($_GET['m']) {
      $first_day = $_GET['m']; //gets as YYYYMM
      $wpdb->escape($first_day);
      $first_day .= '01'; //turns it into YYYYMM01
      $month = new DateTime($first_day);
      $start_date = $month->format('Y-m-d H:i:s');
      $month->add(new DateInterval('P1M')); //get start of next month
      $end_date = $month->format('Y-m-d H:i:s');
      $filter_date = " AND Post.post_date >= '$start_date' AND Post.post_date < '$end_date'";
    } else {
      $filter_date = '';
    }

    $wpdb->escape($orderby); //prepare puts ' which messes the query
    $wpdb->escape($order);  //so escape these two
    $query = $wpdb->prepare(
            "SELECT Eval.post_id, Post.post_title, SUM( Eval.counter ) as score, COUNT( Eval.id ) as total_votes, Post.post_type, User.user_nicename, Post.post_date, GROUP_CONCAT( DISTINCT Relationships.term_taxonomy_id) as taxonomy_ids"
            . " FROM " . EVALUATE_DB_TABLE . " Eval"
            . " INNER JOIN $wpdb->posts Post on (Eval.post_id = Post.id)"
            . " INNER JOIN $wpdb->users User on (Post.post_author = User.id)"
            . " INNER JOIN $wpdb->term_relationships Relationships on (Eval.post_id = Relationships.object_id)"
            . " WHERE Eval.type=%s $filter_category $filter_content_type $filter_users $filter_date"
            . " GROUP BY Eval.post_id"
            . " ORDER BY " . $orderby . " " . $order . ", score DESC"
            , $type);
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
    //var_dump($this->items);
    //echo "<br><br>";
    //var_dump($query);
  }

  function extra_tablenav($which) {
    if ($which == 'top') {
      $filter_content_type = ($_GET['filter_content_type'] ? $_GET['filter_content_type'] : '');
      ?>
      <div class="alignleft actions">
        <select id="filter_content_type" name="filter_content_type">
          <option value="" <?php echo ($filter_content_type == '' ? 'selected="selected"' : ''); ?>>Show all content types</option>
          <?php
          $post_types = get_post_types('', 'names');
          foreach ($post_types as $post_type) {
            ?>
            <option value="<?php echo $post_type; ?>" <?php echo ($filter_content_type == $post_type ? 'selected="selected"' : ''); ?>><?php echo $post_type; ?></option>
            <?php
          }
          ?>
        </select>
        <?php
        $cat = ($_GET['cat'] ? $_GET['cat'] : '0');
        $dropdown_options = array(
            'show_option_all' => __('Show all categories'),
            'hide_empty' => 0,
            'hierarchical' => 1,
            'show_count' => 0,
            'orderby' => 'name',
            'selected' => $cat
        );
        wp_dropdown_categories($dropdown_options);

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

        <select id="filter_users" name="filter_users">
          <option value="" <?php echo ($filter_users == '' ? 'selected="selected"' : ''); ?>>Show all users</option>
          <?php
          foreach ($userlist as $user) {
            ?>
            <option value="<?php echo $user->user_nicename; ?>" <?php echo ($filter_users == $user->user_nicename ? 'selected="selected"' : ''); ?>><?php echo $user->user_nicename; ?></option>
          <?php } ?>
        </select>
        <?php
        echo $this->months_dropdown('post');
        ?>
        <input id="filter_submit" class="button-secondary" type="submit" value="Filter" name="filter">
      </div>
      <?php
    }
    if ($which == 'bottom') {
      //echo 'after table';
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
      <input type="hidden" name="section" value="content" />
      <?php
      $this->prepare_items();
      $this->display();
      ?>
    </form>
    <?php
  }

}
?>
