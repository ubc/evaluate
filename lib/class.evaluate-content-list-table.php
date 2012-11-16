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
    $this->metric_id = $_GET['metric_id'];
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
        return implode(', ', $item->categories);
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

    //fetch posts
    $posts = get_posts(array(
        'numberposts' => -1,
        'meta_key' => 'metric',
        'meta_value' => $this->metric_id,
        'meta_compare' => '!=',
        'post_type' => array(
            'post',
            'page'
        )
            ));

    //preprocess proper info for item objects
    $items = array();
    foreach ($posts as $post) {
      $item->title = $post->post_title;

      $author = get_user_by('id', $post->post_author);
      $item->author = $author->data->user_nicename;

      $item->type = $post->post_type;

      $categories = wp_get_post_categories($post->ID);
      $cats = array();
      foreach ($categories as $c) {
        $cat = get_category($c);
        $cats[$cat->cat_ID] = $cat->name;
      }
      $item->categories = $cats;

      $score = $wpdb->get_var($wpdb->prepare('SELECT SUM(vote) FROM ' . EVAL_DB_VOTES . ' WHERE metric_id=%s AND content_id=%s', $this->metric_id, $post->ID));
      
      if (isset($score)) {
        $item->score = $score;
      } else {
        $item->score = 0;
      }

      $item->date = $post->post_date;

      $item->permalink = get_permalink($post->ID);

      $items[] = $item;
      unset($item);
    }

    //because we have to do a little preprocessing to the list
    //sort it by the given order
    usort($items, function($a, $b) {
              $orderby = (isset($_GET['orderby']) ? $_GET['orderby'] : 'date');
              $order = (isset($_GET['order']) && $_GET['order'] == 'asc' ? 1 : -1); //multiplier to enable reverse sorting
              return $order * strcmp($a->{$orderby}, $b->{$orderby}); //strcmp returns {-1,0,1} so multiplying this by -1 just reverses the order
            });

    //apply filters to results
    if (isset($_GET['filter_content_type']) && $_GET['filter_content_type']) {
      $content_filter = $wpdb->escape($_GET['filter_content_type']);
      $items = array_filter($items, function($item) use ($content_filter) {
                return $item->type == $content_filter;
              });
    }

    if (isset($_GET['cat']) && $_GET['cat']) {
      $category_filter = $wpdb->escape($_GET['cat']);
      $items = array_filter($items, function($item) use ($category_filter) {
                return array_key_exists($category_filter, $item->categories);
              });
    }

    if (isset($_GET['filter_users']) && $_GET['filter_users']) {
      $user_filter = $wpdb->escape($_GET['filter_users']);
      $items = array_filter($items, function($item) use ($user_filter) {
                return $item->author == $user_filter;
              });
    }

    if (isset($_GET['m']) && $_GET['m']) {
      $month_filter = $wpdb->escape($_GET['m']);
      $items = array_filter($items, function($item) use ($month_filter) {
                $item_date = new DateTime($item->date);
                $month_filter .= '01';
                $month = new DateTime($month_filter);
                if (!$item_date->diff($month)->invert)
                  return false;
                $month->add(new DateInterval('P1M')); //get start of next month
                if ($item_date->diff($month)->invert)
                  return false;

                return true;
              });
    }

    //pagination arguments
    $per_page = 10;
    $current_page = $this->get_pagenum();
    $total_items = count($items);

    $this->set_pagination_args(array(
        'total_items' => $total_items,
        'per_page' => $per_page
    ));

    $start = ($current_page - 1) * $per_page; //slice start index

    $this->items = array_slice($items, $start, $per_page);
  }

  public function render() {
    //need to wrap a form around the table for bulk actions
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

  function extra_tablenav($which) {
    if ($which == 'top') {
      $filter_content_type = (isset($_GET['filter_content_type']) ? $_GET['filter_content_type'] : '');
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
        $cat = (isset($_GET['cat']) ? $_GET['cat'] : '0');
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
        $filter_users = (isset($_GET['filter_users']) ? $_GET['filter_users'] : '');
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
        echo $this->months_dropdown(array('page', 'post'));
        ?>
        <input id="filter_submit" class="button-secondary" type="submit" value="Filter" name="filter">
      </div>
      <?php
    }
    if ($which == 'bottom') {
      //echo 'after table';
    }
  }

}
?>
