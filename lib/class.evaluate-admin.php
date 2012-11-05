<?php

class Evaluate_Admin {

  static $options = array();

  /*
   * first code block that runs
   */
  public static function init() {
    self::$options['EVAL_AJAX'] = get_option('EVAL_AJAX');

    //js and css script hook
    add_action('admin_enqueue_scripts', array('Evaluate_Admin', 'enqueue_scripts'));
  }

  /*
   * displays the admin menu link in wp-admin
   */
  public static function admin_menu() {
    //params: page title, menu title, capability, ?page=, function name
    add_options_page("Evaluate", "Evaluate", 'manage_options', "evaluate", array('Evaluate_Admin', 'page'));
  }

  /*
   * queue the css styles and js scripts
   */
  public static function enqueue_scripts() {
    //needs site-wide unique identifiers for first param
    wp_register_style('evaluate', plugins_url('/css/evaluate.css', dirname(__FILE__)));
    wp_register_style('evaluate-admin', plugins_url('/css/evaluate-admin.css', dirname(__FILE__)));

    wp_enqueue_style('evaluate');
    wp_enqueue_style('evaluate-admin');

    wp_enqueue_script('evaluate-admin-js', plugins_url('/js/evaluate-admin.js', dirname(__FILE__)), array('jquery'));
  }

  /*
   * adds a defer tag to the specific script so it loads after dom load
   * taken from http://wordpress.stackexchange.com/questions/38319/how-to-add-defer-defer-tag-in-plugin-javascripts
   */
  public static function add_defer_to_script($url) {
    if (FALSE === strpos($url, 'evaluate-admin')
            or FALSE === strpos($url, '.js')
    ) { // not our file
      return $url;
    }
    // Must be a ', not "!
    return "$url' defer='defer";
  }

  /*
   * this will be the 'controller' to display the correct page
   * in the admin view
   */
  public static function page() {
    global $wpdb;
    $view = (isset($_REQUEST['view']) ? $_REQUEST['view'] : ''); //avoids warning when wp_debug = true
    switch ($view) { //sort of redundant, only required for the title link, otherwise its handled properly after the html block
      case 'main':
        $link = '<a href="options-general.php?page=evaluate&view=add" class="add-new-h2" title="Add New Metric">Add New</a>';
        break;

      case 'add':
        $link = '<a href="options-general.php?page=evaluate&view=main" class="add-new-h2" title="Back to Main Page">Main Page</a>';
        break;

      case 'edit':
        $link = '<a href="options-general.php?page=evaluate&view=main" class="add-new-h2" title="Back to Main Page">Main Page</a>';
        break;

      case 'metric':
        $link = '<a href="options-general.php?page=evaluate&view=main" class="add-new-h2" title="Back to Main Page">Main Page</a>';
        break;

      default:
        $link = '<a href="options-general.php?page=evaluate&view=add" class="add-new-h2" title="Add New Metric">Add New</a>';
    }
    ?>
    <div class="wrap">
      <div id="icon-options-general" class="icon32"></div>
      <h2>
        Evaluate <?php echo $link; ?>
      </h2>
    </div>
    <?php
    //handle actions request with POST
    $action = (isset($_POST['action']) ? $_POST['action'] : '');
    switch ($action) {
      case 'new': //add new metric
        try { //try to add the new metric
          $formdata = (isset($_POST['evalu_form']) ? $_POST['evalu_form'] : null);
          Evaluate_Admin::add_metric($formdata);
          Evaluate_Admin::alert('Metric saved successfully.', 'updated');
        } catch (Exception $e) { //fail
          Evaluate_Admin::alert($e->getMessage(), 'error');
          Evaluate_Admin::metric_form();
          return; //error must have happened adding/editing
        }
        break;

      case 'delete': //delete metric (bulk action)
        $metrics = (isset($_POST['metric']) ? $_POST['metric'] : false);
        if ($metrics) {
          foreach ($metrics as $slug) {
            try {
              Evaluate_Admin::delete_metric($slug);
              Evaluate_Admin::alert("Metric '$slug' deleted successfully.", 'updated');
            } catch (Exception $e) {
              Evaluate_Admin::alert($e->getMessage(), 'error');
            }
          }
        }
        //Evaluate_Admin::delete_metric();
        break;

      case 'options':
        $use_ajax = isset($_POST['use_ajax']);
        update_option('EVAL_AJAX', $use_ajax);
        self::$options['EVAL_AJAX'] = $use_ajax;
        break;
    }

    //handle actions requested with GET
    $get_action = (isset($_GET['action']) ? $_GET['action'] : '');
    switch ($get_action) {
      case 'delete': //single metric delete event from link
        $slug = (isset($_GET['metric']) ? $_GET['metric'] : '');
        try {
          Evaluate_Admin::delete_metric($slug);
          Evaluate_Admin::alert("Metric '$slug' deleted successfully.", 'updated');
        } catch (Exception $e) {
          Evaluate_Admin::alert($e->getMessage(), 'error');
        }
        break;
    }

    //after actions, handle which view to render
    switch ($view) {
      case 'main':
        self::plugin_options();
        Evaluate_Admin::metrics_table();
        break;

      case 'add':
        Evaluate_Admin::metric_form();
        break;

      case 'edit':
        if (!isset($_GET['metric'])) {
          Evaluate_Admin::alert('You must supply a metric name to edit.', 'error');
          Evaluate_Admin::metrics_table();
        } else {
          $query = $wpdb->prepare("SELECT * FROM " . EVAL_DB_METRICS . " WHERE slug=%s", $_GET['metric']);
          $row = $wpdb->get_row($query);
          if (!$row) {
            Evaluate_Admin::alert("This metric doesn't exist!", 'error');
            Evaluate_Admin::metrics_table();
          } else {
            Evaluate_Admin::metric_form((array) $row);
          }
        }
        break;

      case 'metric':
        try {
          self::details_table();
        } catch (Exception $e) {
          self::alert($e->getMessage(), 'error');
        }
        break;

      default:
        Evaluate_Admin::metrics_table();
        break;
    }
    ?>
    <?php
  }

  public static function plugin_options() {
    $checked = (self::$options['EVAL_AJAX'] ? 'checked="checked"' : '');
    $html = <<<HTML
<form id="evaluate-options" method="post" action="">
  <label>
  <input type="checkbox" $checked name="use_ajax" />
  Use AJAX voting
  </label>
  <input type="hidden" name="action" value="options" />
  <input type="submit" value="Save Changes" />
</form>
HTML;
    echo $html;
  }

  /*
   * outputs main metrics list table
   */
  public static function metrics_table() {
    $metrics_table = new Evaluate_Metrics_List_Table();
    $metrics_table->render();
  }

  /*
   * outputs the metric details table depending on selection
   */
  public static function details_table() {
    global $wpdb;
    $metric_id = (isset($_GET['metric']) ? $_GET['metric'] : false);
    if (!$metric_id) {
      throw new Exception("You haven't supplied a metric!");
    }
    ?>
    <div class="postbox metric-details">
      <h3>Metric Details</h3>
      <div class="metric-details-inner">
        <p># Votes across all contents: <?php echo $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . EVAL_DB_VOTES . ' WHERE metric_id=%s', $metric_id)); ?></p>
        <div>
          <?php echo Evaluate::display_metric($metric_id, 0); ?>
        </div>
      </div>
    </div>
    <?php
    $section = (isset($_GET['section']) ? $_GET['section'] : 'content');
    $content_is_active = true;
    switch ($section) {
      case 'user':
        $content_is_active = false;
        $details_table = new Evaluate_Users_List_Table();
        break;

      case 'content':

      default:
        $content_is_active = true;
        $details_table = new Evaluate_Content_List_Table();
        break;
    }
    ?>
    <h3 class = "nav-tab-wrapper">
      <a class = "nav-tab <?php echo ($content_is_active ? 'nav-tab-active' : ''); ?>" href = "?page=evaluate&view=metric&metric=<?php echo $metric_id; ?>&section=content">Content</a>
      <a class = "nav-tab <?php echo ($content_is_active ? '' : 'nav-tab-active'); ?>" href = "?page=evaluate&view=metric&metric=<?php echo $metric_id; ?>&section=user">Users</a>
    </h3>
    <?php
    $details_table->render();
  }

  /*
   * returns a div containing the message and styled according to the $type
   * used for displaying feedback to the user
   * $type can be 'error' or 'updated' or any other css class
   */
  public static function alert($message, $type) {
    $html = <<<HTML
<div class="$type">$message</div>
HTML;
    echo $html;
  }

  /*
   * enable db error reporting for $wpdb
   * just for debugging
   */
  public static function enable_db_errors() {
    define('DIEONDBERROR', true);
    global $wpdb;
    $wpdb->show_errors();
  }

  /*
   * deletes metric if found & valid
   * throws Exception
   */
  public static function delete_metric($slug) {
    global $wpdb;

    if (!$slug) {
      throw new Exception('You have not specified a metric to delete.');
    }

    $nonce = (isset($_REQUEST['_wpnonce']) ? $_REQUEST['_wpnonce'] : false);

    if (!$nonce || (!wp_verify_nonce($nonce, "evaluate-delete-$slug") && !wp_verify_nonce($nonce, 'bulk-metrics'))) {
      throw new Exception('Nonce check failed. Did you mean to visit this page?');
    }

    //get metric
    $metric = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . EVAL_DB_METRICS . ' WHERE slug=%s', $slug));

    //delete the metric itself
    $query = $wpdb->prepare('DELETE FROM ' . EVAL_DB_METRICS . ' WHERE slug=%s', $slug);
    $result = $wpdb->query($query);

    if ($result === FALSE) { //identity check because $wpdb->query can also return 0 which casts to FALSE on == comparison
      throw new Exception('Database error during delete operation.');
    } elseif ($result == 0) {
      throw new Exception('Database unchanged after delete operation (metric already deleted?).');
    }

    //delete its votes
    $query = $wpdb->prepare('DELETE FROM ' . EVAL_DB_VOTES . ' WHERE metric_id=%s', $metric->id);
    $result = $wpdb->query($query);

    if ($result === FALSE) { //identity check because $wpdb->query can also return 0 which casts to FALSE on == comparison
      throw new Exception('Database error during delete operation.');
    } elseif ($result == 0) {
      throw new Exception('Database unchanged after delete operation (metric already deleted?).');
    }

    return true;
  }

  /*
   * add metric to the database
   * throws Exception if there are any errors, so make sure to try..catch when calling this
   */
  public static function add_metric($data) {
    global $wpdb;

    //check nonce first
    if (!wp_verify_nonce($_POST['_wpnonce'], 'evaluate-new')) {
      throw new Exception('Nonce check failed. Were you meant to do this?');
    }

    //assign if we're updating
    $update = (isset($data['update']) ? true : false);
    $oldname = ($update ? $data['update'] : '');

    //name
    if (!$data['name']) {
      throw new Exception('You must enter a name.');
    }
    $metric['nicename'] = $data['name'];
    $wpdb->escape($metric['nicename']);
    $metric['slug'] = sanitize_title($metric['nicename']);

    if ($update) {
      $metric = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . EVAL_DB_METRICS . ' WHERE slug=%s', $metric['slug']));
      $num_votes = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . EVAL_DB_VOTES . ' WHERE metric_id=%s', $metric->id));
      if ($num_votes > 0 && $data['type'] != $metric->type) {
        throw new Exception('You cannot change the type of this metric because there are votes registered.');
      }
    }
    //check if name is unique
    $query = $wpdb->prepare("SELECT id FROM " . EVAL_DB_METRICS . " WHERE slug=%s", $metric['slug']);
    $check = $wpdb->get_results($query);
    if (count($check) > 0 && $oldname != $metric['slug']) { //metric name exists and not updating
      throw new Exception('This metric name already exists.');
    }

    $metric['display_name'] = (isset($data['display_name']) && $data['display_name'] ? true : false);

    //type
    if (!isset($data['type']) || !$data['type']) {
      throw new Exception('You must choose a metric type.');
    }
    $metric['type'] = $data['type'];
    $wpdb->escape($metric['type']);

    //style
    //for type 'range', we know it must be star,
    //for type 'poll', it must be poll
    if ($metric['type'] == 'range') {
      $metric['style'] = 'star';
    } else if ($metric['type'] == 'poll') {
      $metric['style'] = 'poll';
    } else {
      if (!isset($data['style']) || !$data['style']) {
        throw new Exception('You must choose a style.');
      } else {
        //we want this at the end because if JS fails then there is a chance
        //that $data['style'] might contain data from the wrong $data['type']
        $metric['style'] = $data['style'];
      }
    }
    $wpdb->escape($metric['style']);

    //for require_login and admin_only, I do it this way because otherwise
    //it may cast it to an empty string, instead of bool, which wil force mysql
    //to cast the empty string to false
    //require_login
    if (isset($data['require_login']) && $data['require_login']) {
      $metric['require_login'] = true;
    } else {
      $metric['require_login'] = false;
    }

    //admin_only
    if (isset($data['admin_only']) && $data['admin_only']) {
      $metric['admin_only'] = true;
    } else {
      $metric['admin_only'] = false;
    }

    //params
    $metric['params'] = array();
    //handle poll data
    //firstly get rid of any answer fields that are empty
    $poll_answers = array_filter($data['poll']['answer']);
    if ($metric['type'] == 'poll') {
      if (!$data['poll']['question']) {
        throw new Exception('You must provide a question for the poll.');
      }
      if (count($poll_answers) < 2) {
        throw new Exception('You must provide at least 2 answers for the poll.');
      }
      $metric['params']['poll']['question'] = $data['poll']['question']; //question
      $metric['params']['poll']['answer'] = $poll_answers; //filtered answers
    }
    //now get content types options
    if (isset($data['content_post']) && $data['content_post']) {
      $metric['params']['content_types'][] = 'post';
    }
    if (isset($data['content_page']) && $data['content_page']) {
      $metric['params']['content_types'][] = 'page';
    }
    if (isset($data['content_media']) && $data['content_media']) {
      $metric['params']['content_types'][] = 'media';
    }

    $metric['params'] = serialize($metric['params']);

    if (!$update) {
      $metric['created'] = date('Y/m/d H:i:s');
    }

    $metric['modified'] = date('Y/m/d H:i:s'); //initial modified is the creation date

    if ($update) {
      if ($wpdb->update(EVAL_DB_METRICS, $metric, array('slug' => $oldname))) {
        return true;
      } else {
        throw new Exception($wpdb->print_error());
      }
    } else {
      if ($wpdb->insert(EVAL_DB_METRICS, $metric)) { //attempt to insert into DB
        return true;
      } else {
        throw new Exception($wpdb->print_error());
      }
    }
  }

  /*
   * form for adding new metrics or editing existing ones
   */
  public static function metric_form($metric = null) {
    if (isset($metric)) {
      $formdata['name'] = $metric['nicename'];
      $formdata['slug'] = $metric['slug'];
      $formdata['display_name'] = $metric['display_name'];
      $formdata['type'] = $metric['type'];
      $formdata['style'] = $metric['style'];
      $params = unserialize($metric['params']);
      $formdata['poll'] = (isset($params['poll']) ? $params['poll'] : false);

      if (isset($params['content_types'])) {
        foreach ($params['content_types'] as $content_type) {
          $formdata['content_' . $content_type] = true;
        }
      }

      if ($metric['admin_only']) {
        $formdata['admin_only'] = $metric['admin_only'];
      }

      if ($metric['require_login']) {
        $formdata['require_login'] = $metric['require_login'];
      }

      $update = true; //flag to edit
    } else {
      //get data from previous attempt if exists
      $formdata = (isset($_POST['evalu_form']) ? $_POST['evalu_form'] : '');
      //check if an update flag is set
      //this is required if the first attempt at editing fails because of any reason
      //so that we are still editing the right metric instead of losing edit data
      if (isset($formdata['update'])) {
        $update = true;
        $formdata['slug'] = $formdata['update'];
      } else {
        $update = false;
      }
    }
    $html_title = ($update ? "Edit Metric" : "Add New Metric");
    ?>
    <h3><?php echo $html_title; ?></h3>
    <form method="post" action="?page=evaluate" id="metric_form">
      <?php wp_nonce_field('evaluate-new'); ?>
      <table class="form-table">
        <tr>
          <th><label for="evalu_form[name]">Name</label></th>
          <td>
            <input name="evalu_form[name]" type="text" class="regular-text" <?php echo (isset($formdata['name']) ? 'value="' . $formdata['name'] . '"' : ''); ?> /><br/>
            <label><input type="checkbox" name="evalu_form[display_name]" value="true" <?php echo (isset($formdata['display_name']) ? 'checked="checked"' : ''); ?> /> Display metric name above evaluation</label>
          </td>
        </tr>

        <tr>
          <th>Metric Type</th>
          <td>

            <ul class="type_options">
              <li> <!-- one-way options -->
                <label class="type_label">
                  <input type="radio" name="evalu_form[type]" value="one-way" class="" <?php echo (isset($formdata['type']) && $formdata['type'] == 'one-way' ? 'checked="checked"' : ''); ?> />
                  One-way Voting
                </label>
                <ul class="indent"> <!-- one way style -->
                  <li>
                    <label>
                      <input type="radio" name="evalu_form[style]" value="thumb" class="" <?php echo (isset($formdata['style']) && $formdata['style'] == 'thumb' ? 'checked="checked"' : ''); ?> />
                      <a class="rate thumb">Like</a>
                    </label>
                  </li>
                  <li>
                    <label>
                      <input type="radio" name="evalu_form[style]" value="arrow" class="" <?php echo (isset($formdata['style']) && $formdata['style'] == 'arrow' ? 'checked="checked"' : ''); ?> />
                      <a class="rate arrow">Vote Up</a>
                    </label>
                  </li>
                  <li>
                    <label>
                      <input type="radio" name="evalu_form[style]" value="heart" class="" <?php echo (isset($formdata['style']) && $formdata['style'] == 'heart' ? 'checked="checked"' : ''); ?> />
                      <a class="rate heart">Heart</a>
                    </label>
                  </li>
                </ul>
              </li>

              <li> <!-- two-way options -->
                <label class="type_label">
                  <input type="radio" name="evalu_form[type]" value="two-way" class="" <?php echo (isset($formdata['type']) && $formdata['type'] == 'two-way' ? 'checked="checked"' : ''); ?> />
                  Two-way Voting
                </label>
                <ul class="indent"> <!-- two-way style selection -->
                  <li>
                    <label>
                      <input type="radio" name="evalu_form[style]" value="thumb" class=""  <?php echo (isset($formdata['style']) && $formdata['style'] == 'thumb' ? 'checked="checked"' : ''); ?>/>
                      Thumbs <?php echo Evaluate::display_two_way(null, 'thumb'); ?>
                    </label>
                  </li>
                  <li>
                    <label>
                      <input type="radio" name="evalu_form[style]" value="arrow" class="" />
                      Arrows <?php echo Evaluate::display_two_way(null, 'arrow'); ?>
                    </label>
                  </li>
                </ul>                
              </li>

              <li> <!-- range options -->
                <label class="type_label">
                  <input type="radio" name="evalu_form[type]" value="range" class="" <?php echo (isset($formdata['type']) && $formdata['type'] == 'range' ? 'checked="checked"' : ''); ?> />
                  Stars <?php echo Evaluate::display_range(null); ?>
                </label>
              </li>

              <li> <!-- poll options -->
                <label class="type_label">
                  <input type="radio" name="evalu_form[type]" value="poll" class="" <?php echo (isset($formdata['type']) && $formdata['type'] == 'poll' ? 'checked="checked"' : ''); ?> />
                  Poll
                </label>
                <div class="indent">
                  <label>Question: <input type="text" class="regular-text" name="evalu_form[poll][question]" <?php echo (isset($formdata['poll']['question']) ? 'value="' . $formdata['poll']['question'] . '"' : ''); ?> /></label>
                </div>
                <div class="indent">
                  <label>Answer 1: <input type="text" class="regular-text" name="evalu_form[poll][answer][1]" <?php echo (isset($formdata['poll']['answer']) ? 'value="' . $formdata['poll']['answer'][1] . '"' : ''); ?> /></label>
                </div>
                <div class="indent">
                  <label>Answer 2: <input type="text" class="regular-text" name="evalu_form[poll][answer][2]" <?php echo (isset($formdata['poll']['answer']) ? 'value="' . $formdata['poll']['answer'][2] . '"' : ''); ?> /></label>
                </div>
                <?php
                if (isset($formdata['poll']['answer'])) {
                  for ($i = 3; $i <= count($formdata['poll']['answer']); $i++) {
                    ?>
                    <div class="indent">
                      <label>Answer <?php echo $i; ?>: <input type="text" class="regular-text" name="evalu_form[poll][answer][<?php echo $i; ?>]" value="<?php echo $formdata['poll']['answer'][$i]; ?>" /></label>
                    </div>
                    <?php
                  }
                }
                ?>
                <div class="indent">
                  <a href="javascript:Evaluate_Admin.addNewAnswer()" title="Add New Answer">[+] Add New Answer</a>
                  <a href="javascript:Evaluate_Admin.removeLastAnswer()" title="Remove Last Answer">[-] Remove Last Answer</a>
                </div>
              </li>

            </ul>

          </td>
        </tr>

        <tr>
          <th>Content Types</th>
          <td>
            <?php
            if ($update) {
              $cb_post_state = (isset($formdata['content_post']) ? 'checked="checked"' : '');
              $cb_page_state = (isset($formdata['content_page']) ? 'checked="checked"' : '');
              $cb_media_state = (isset($formdata['content_media']) ? 'checked="checked"' : '');
            } else {
              $cb_post_state = 'checked="checked"';
              $cb_page_state = 'checked="checked"';
              $cb_media_state = 'checked="checked"';
            }
            ?>
            <label><input type="checkbox" name="evalu_form[content_post]" value="true" <?php echo $cb_post_state; ?> /> Posts</label>
            <label><input type="checkbox" name="evalu_form[content_page]" value="true" <?php echo $cb_page_state; ?> /> Pages</label>
            <label><input type="checkbox" name="evalu_form[content_media]" value="true" <?php echo $cb_media_state; ?> /> Media</label>
          </td>
        </tr>

        <tr>
          <th>Display Options</th>
          <td>
            <label><input type="checkbox" name="evalu_form[require_login]" value="true" <?php echo (isset($formdata['require_login']) ? 'checked="checked"' : ''); ?> /> Users have to be logged in to vote.</label>
            <br />
            <label><input type="checkbox" name="evalu_form[admin_only]" value="true" <?php echo (isset($formdata['admin_only']) ? 'checked="checked"' : ''); ?> /> Only Admins can see this metric.</label>
          </td>
        </tr>

        <tr>
          <th>Preview</th>
          <td>
            <div id="preview_name" class="metric_preview"></div>
            <div id="prev_one-way_heart" class="metric_preview"><?php echo Evaluate::display_one_way(null, 'heart'); ?></div>
            <div id="prev_one-way_thumb" class="metric_preview"><?php echo Evaluate::display_one_way(null, 'thumb'); ?></div>
            <div id="prev_one-way_arrow" class="metric_preview"><?php echo Evaluate::display_one_way(null, 'arrow'); ?></div>
            <div id="prev_two-way_thumb" class="metric_preview"><?php echo Evaluate::display_two_way(null, 'thumb'); ?></div>
            <div id="prev_two-way_arrow" class="metric_preview"><?php echo Evaluate::display_two_way(null, 'arrow'); ?></div>
            <div id="prev_range_" class="metric_preview"><?php echo Evaluate::display_range(null); ?></div>
            <div id="prev_poll_" class="metric_preview"><?php echo Evaluate::display_poll(null); ?></div>
          </td>
        </tr>
      </table>
      <input type="hidden" name="action" value="new" />
      <?php if ($update) { ?>
        <input type="hidden" name="evalu_form[update]" value="<?php echo $formdata['slug']; ?>" />
        <input type="hidden" name="view" value="edit" />
      <?php } else { ?>
        <input type="hidden" name="view" value="main" />
      <?php } ?>
      <?php submit_button(); ?>
    </form>
    <?php
  }

  /*
   * callback function for setting up the meta box for metric selection in admin area
   */
  public static function meta_box_setup() {
    add_action('add_meta_boxes', array('Evaluate_Admin', 'meta_box_add'));
  }

  /*
   * callback to construct the meta box in post edit pages
   */
  public static function meta_box_add() {
    //we need one for every type of post we want the metabox to appear in
    add_meta_box(//post
            'evaluate-post-meta', __('Evaluate', 'lol'), array('Evaluate_Admin', 'evaluate_meta_box'), 'post', 'side', 'default'
    );

    add_meta_box(//page
            'evaluate-post-meta', __('Evaluate', 'lol'), array('Evaluate_Admin', 'evaluate_meta_box'), 'page', 'side', 'default'
    );
  }

  /*
   * callback to construct contents of the meta box
   */
  public static function evaluate_meta_box($object, $box) {
    ?>
    <p><small>Check any available metric to associate it with this post.</small></p>
    <?php
    global $wpdb;

    $query = $wpdb->prepare('SELECT * FROM ' . EVAL_DB_METRICS . ''); //get all metrics
    $metrics = $wpdb->get_results($query);
    $post_type = get_post_type($object->ID);
    wp_nonce_field('evaluate_post-meta', 'evaluate_nonce');

    $post_meta = get_post_meta($object->ID, 'metric');
//    vd($post_meta);
    foreach ($metrics as $metric) { //sift through metrics and try to find ones that match the current $post_type
      $params = unserialize($metric->params);
      if (isset($params['content_types'])) {
        foreach ($params['content_types'] as $content_type) {
          if ($content_type == $post_type) {
            ?>
            <p>
              <input type="hidden" name="evaluate_cb[<?php echo $metric->id; ?>]" value="0" />
              <label>
                <input type="checkbox" name="evaluate_cb[<?php echo $metric->id; ?>]" <?php if (in_array($metric->id, $post_meta) || !$post_meta) echo 'checked="checked"'; ?> />
                <?php echo $metric->nicename . ' - ' . $metric->type . ' - ' . $metric->style; ?>
              </label>
            </p>
            <?php
          }
        }
      }
    }
  }

  /*
   * handle saving the post meta after any add/edit action to posts
   */
  public static function save_post_meta($post_id, $post_object) {
    global $meta_box;

    //validate nonce
    if (!isset($_POST['evaluate_nonce']) || !wp_verify_nonce($_POST['evaluate_nonce'], 'evaluate_post-meta')) {
      return $post_id;
    }

    //check user permissions
    if ($_POST['post_type'] == 'page') {
      if (!current_user_can('edit_page', $post_id)) {
        return $post_id;
      }
    } elseif (!current_user_can('edit_post', $post_id)) {
      return $post_id;
    }

    //we're only interested in the parent post
    if ($post_object->post_type == 'revision')
      return;

    $post_meta = get_post_meta($post_id, 'metric');
    foreach ($_POST['evaluate_cb'] as $key => $cb) {
      if (!$cb) {
        delete_post_meta($post_id, 'metric', $key);
      } elseif ($cb == 'on' && !in_array($key, $post_meta)) {
        add_post_meta($post_id, 'metric', $key);
      }
    }

    return true;
  }

}
?>
