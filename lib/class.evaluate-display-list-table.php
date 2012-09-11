<?php

class Evaluate_Display_List_Table extends WP_List_Table {

  public $columns = array();
  public $sortable_columns = array();
  public $display_options = array();

  function __construct() {
    parent::__construct(array(
        'singular' => 'metric',
        'plural' => 'metrics',
        'ajax' => false
    ));
  }

  function get_columns() {
    return $columns = array(
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
    $wpdb->escape($orderby); //prepare puts ' which messed the query
    $wpdb->escape($order);  //so escape these two
    $query = $wpdb->prepare(
            "SELECT Eval.post_id, Post.post_title, SUM( Eval.counter ) as score, COUNT( Eval.id ) as total_votes, Post.post_type, User.user_nicename, Post.post_date, GROUP_CONCAT( DISTINCT Relationships.term_taxonomy_id) as taxonomy_ids"
            . " FROM " . EVALUATE_DB_TABLE . " Eval"
            . " INNER JOIN $wpdb->posts Post on (Eval.post_id = Post.id)"
            . " INNER JOIN $wpdb->users User on (Post.post_author = User.id)"
            . " INNER JOIN $wpdb->term_relationships Relationships on (Eval.post_id = Relationships.object_id)"
            . " WHERE Eval.type=%s"
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
    $this->items = $wpdb->get_results($query); //fetch results with pagination this time

    var_dump($this->items);
    echo "<br><br>";
    var_dump($query);
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

  function column_post_title($item) {
    $actions = array(
        'vote_breakdown' => sprintf('<a href="?wat=pls&lol=keke">%s</a>', 'View Vote Breakdown')
    );
    return sprintf('<a class="row-title" href="%s">%s</a>%s', get_permalink($item->post_id), $item->post_title, $this->row_actions($actions));
  }

  function extra_tablenav($which) {
    if ($which == 'top') {
      ?>
      <div class="alignleft actions">
        <label class="screen-reader-text" for="filter_category">View all categories…</label>
        <select id="filter_category" name="filter_category">
          <option value="">View all categories…</option>
          <?php
          $categories = get_categories();
          foreach ($categories as $category) {
            ?>
            <option value="<?php echo $category->term_taxonomy_id; ?>"><?php echo $category->category_nicename; ?></option>
            <?php
          }
          ?>
        </select>
        <label class="screen-reader-text" for="filter_content_type">View all content types…</label>
        <select id="filter_content_type" name="filter_content_type">
          <option value="">View all content types…</option>
          <?php
          $post_types = get_post_types('', 'names');
          foreach ($post_types as $post_type) {
            ?>
            <option value="<?php echo $post_type; ?>"><?php echo $post_type; ?></option>
            <?php
          }
          ?>
        </select>
        <?php $dropdown_options = array(
					'show_option_all' => __( 'View all categories' ),
					'hide_empty' => 0,
					'hierarchical' => 1,
					'show_count' => 0,
					'orderby' => 'name',
					'selected' => $cat
				);
				wp_dropdown_categories( $dropdown_options );
        ?>
        <input id="changeit" class="button-secondary" type="submit" value="Filter" name="filter">
      </div>
      <?php
    }
    if ($which == 'bottom') {
      //echo 'after table';
    }
  }

  public function render() {
    ?>
    <form id="metrics-filter" method="get">
      <input type="hidden" name="page" value="<?php echo $_GET['page']; ?>" />
      <input type="hidden" name="do" value="<?php echo $_GET['do']; ?>" />
      <input type="hidden" name="metric" value="<?php echo $_GET['metric']; ?>" />
      <?php
      $this->prepare_items();
      $this->display();
      ?>
    </form>
    <?php var_dump($_GET);
  }

}
?>
