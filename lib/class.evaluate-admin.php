<?php

class Evaluate_Admin {

  static $options = array();

  /* first code block that runs */
  public static function init() {
    //check if CTLT_Stream plugin exists to use with node
    if (!function_exists('is_plugin_active')) {
      //include plugins.php to check for other plugins from the frontend
      include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    //ajax use option
    self::$options['EVAL_AJAX'] = get_option('EVAL_AJAX');
    self::$options['EVAL_STREAM'] = is_plugin_active('stream/stream.php');

    //register plugin settings
    self::register_settings();

    add_action('admin_enqueue_scripts', array('Evaluate_Admin', 'enqueue_scripts'));
  }

  /* displays the admin menu link in wp-admin */
  public static function admin_menu() {
    //params: page title, menu title, capability, ?page=, function name
    add_options_page("Evaluate", "Evaluate", 'manage_options', "evaluate", array('Evaluate_Admin', 'page'));
  }

  /* queue the css styles and js scripts */
  public static function enqueue_scripts() {
    //needs site-wide unique identifiers for first param
    wp_register_style('evaluate', EVAL_DIR_URL . '/css/evaluate.css');
    wp_register_style('evaluate-admin', EVAL_DIR_URL . '/css/evaluate-admin.css');

    wp_enqueue_style('evaluate');
    wp_enqueue_style('evaluate-admin');

    wp_register_script('doT', EVAL_DIR_URL . '/js/doT.js', false, false, true);
    wp_register_script('evaluate-admin-js', EVAL_DIR_URL . '/js/evaluate-admin.js', array('jquery', 'doT'), false, true);

    wp_enqueue_script('doT');
    wp_enqueue_script('evaluate-admin-js');
  }

  /* register and initialize settings for the plugin */
  public static function register_settings() {
    register_setting('evaluate_options', 'EVAL_AJAX');
    add_settings_section('evaluate_settings', 'Evaluate Settings', function() {
	      echo 'Settings and CTLT_Stream/NodeJS Status';
	    }, 'evaluate');

    add_settings_field('use_ajax_voting', 'Use AJAX voting', function() {
	      echo '<input id="EVAL_AJAX" name="EVAL_AJAX" value="1" type="checkbox" ' .
	      checked(1, get_option('EVAL_AJAX'), false) . '/>';
	    }
	    , 'evaluate', 'evaluate_settings');

    add_settings_field('ctlt_stream_found', 'CTLT_Stream plugin found', function() {
	      echo '<input id="ctlt_stream_status" name="ctlt_stream_status" type="checkbox" disabled="disabled" ' .
	      checked(1, Evaluate_Admin::$options['EVAL_STREAM'], false) . '/>';
	    }, 'evaluate', 'evaluate_settings');

    if (self::$options['EVAL_STREAM']) {
      add_settings_field('nodejs_server_status', 'NodeJS Server status', function() {
		echo '<input id="nodejs_server_status" name="nodejs_server_status" type="checkbox" disabled="disabled"' .
		checked(1, CTLT_Stream::is_node_active(), false) . '/>';
	      }, 'evaluate', 'evaluate_settings');
    }
  }

  /* this is the 'controller' to display the correct page in the admin view */
  public static function page() {
    $view = (isset($_REQUEST['view']) ? $_REQUEST['view'] : false);
    $action = (isset($_REQUEST['action']) ? $_REQUEST['action'] : false);
//    vd($_POST);
    switch ($view) {
      case 'main':
	$link = '<a href="options-general.php?page=evaluate&view=form" class="add-new-h2" title="Add New Metric">Add New</a>';
	break;

      case 'form':
	$link = '<a href="options-general.php?page=evaluate&view=main" class="add-new-h2" title="Back to Main Page">Main Page</a>';
	break;

      case 'metric':
	$link = '<a href="options-general.php?page=evaluate&view=main" class="add-new-h2" title="Back to Main Page">Main Page</a>';
	break;

      default:
	$link = '<a href="options-general.php?page=evaluate&view=form" class="add-new-h2" title="Add New Metric">Add New</a>';
    }
    ?>

    <div class="wrap">
      <div id="icon-options-general" class="icon32"></div>
      <h2>
        Evaluate <?php echo $link; ?>
      </h2>
    </div>

    <?php
    switch ($action) {
      case 'new':

      case 'edit':
	try {
	  self::add_metric();
	  self::alert('Metric saved!', 'updated');
	} catch (Exception $e) {
	  self::alert($e->getMessage(), 'error');
	  self::metric_form();
	}

	break;

      case 'delete':
	$metrics_for_deletion = array();
	if (isset($_REQUEST['metric_id'])) {
	  $metrics_for_deletion[] = $_REQUEST['metric_id'];
	} else if (isset($_REQUEST['metric'])) {
	  $metrics_for_deletion = $_REQUEST['metric'];
	}
	foreach ($metrics_for_deletion as $metric_for_deletion) {
	  try {
	    self::delete_metric($metric_for_deletion);
	    self::alert('Metric deleted.', 'updated');
	  } catch (Exception $e) {
	    self::alert($e->getMessage(), 'error');
	  }
	}
	break;
    }

    switch ($view) {
      case 'form':
	self::metric_form();
	break;

      case 'metric':
	try {
	  self::details_table();
	} catch (Exception $e) {
	  self::alert($e->getMessage(), 'error');
	}
	break;

      case 'main':

      default:
	self::metrics_table();
	self::plugin_options();
	break;
    }
  }

  /* plugin options section for admin panel */
  public static function plugin_options() {
    ?>
    <form id="evaluate-options" method="post" action="options.php">
      <?php
      do_settings_sections('evaluate');
      ?>
      <input type="submit" class="button-primary" value="Save Changes" />
    </form>
    <?php
  }

  /* outputs main metrics list table */
  public static function metrics_table() {
    $metrics_table = new Evaluate_Metrics_List_Table();
    $metrics_table->render();
  }

  /* outputs the metric details table depending on selection */
  public static function details_table() {
    global $wpdb;
    $metric_id = (isset($_GET['metric_id']) ? $_GET['metric_id'] : false);
    if (!$metric_id) {
      throw new Exception("You haven't supplied a metric!");
    }
    $metric_data = Evaluate::get_data_by_id($metric_id, 0);
    ?>
    <div class="postbox metric-details">
      <h3>Metric Details</h3>
      <div class="metric-details-inner">
        <p># Votes across all contents: <?php echo $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . EVAL_DB_VOTES . ' WHERE metric_id=%s', $metric_id)); ?></p>
        <div> 
	  <?php echo Evaluate::display_metric($metric_data); ?>
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
      <a class = "nav-tab <?php echo ($content_is_active ? 'nav-tab-active' : ''); ?>" href = "?page=evaluate&view=metric&metric_id=<?php echo $metric_id; ?>&section=content">Content</a>
      <a class = "nav-tab <?php echo ($content_is_active ? '' : 'nav-tab-active'); ?>" href = "?page=evaluate&view=metric&metric_id=<?php echo $metric_id; ?>&section=user">Users</a>
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

  /* enable db error reporting for $wpdb */
  public static function enable_db_errors() {
    define('DIEONDBERROR', true);
    global $wpdb;
    $wpdb->show_errors();
  }

  /* deletes metric if found & valid */
  public static function delete_metric($metric_id) {
    global $wpdb;

    if (!$metric_id) {
      throw new Exception('You have not specified a metric to delete.');
    }

    $nonce = (isset($_REQUEST['_wpnonce']) ? $_REQUEST['_wpnonce'] : false);

    if (!$nonce || (!wp_verify_nonce($nonce, "evaluate-delete-$metric_id") && !wp_verify_nonce($nonce, 'bulk-metrics'))) {
      throw new Exception('Nonce check failed. Did you mean to visit this page?');
    }

    //delete the metric itself
    $result = $wpdb->query(
	    $wpdb->prepare('DELETE FROM ' . EVAL_DB_METRICS . ' WHERE id=%s', $metric_id));

    if ($result === FALSE) { //identity check because $wpdb->query can also return 0 which casts to FALSE on == comparison
      throw new Exception('Database error during delete operation.');
    } elseif ($result == 0) {
      throw new Exception('Database unchanged after delete operation (metric already deleted?).');
    }

    //delete its votes
    $result = $wpdb->query(
	    $wpdb->prepare('DELETE FROM ' . EVAL_DB_VOTES . ' WHERE metric_id=%s', $metric_id));

    if ($result === FALSE) { //identity check because $wpdb->query can also return 0 which casts to FALSE on == comparison
      throw new Exception('Database error during delete operation.');
    }

    return true;
  }

  /* add or update metric after form entry */
  public static function add_metric() {
    global $wpdb;
    //try to get form data from the request
    $formdata = (isset($_REQUEST['evalu_form']) ? $_REQUEST['evalu_form'] : false);
    if (!$formdata) {
      throw new Exception('No form data found!');
    }
    //verify nonce
    if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'evaluate-' . $_REQUEST['action'])) {
      throw new Exception('Nonce check failed!');
    }

    $is_update = isset($_REQUEST['metric_id']);
    //get current record if this is an update
    if ($is_update) {
      $metric_id = $_REQUEST['metric_id'];
      $current_data = $wpdb->get_row(
	      $wpdb->prepare('SELECT * FROM ' . EVAL_DB_METRICS
		      . ' WHERE id=%s', $metric_id));
    }

    $metric = array(); //to hold the data
    //name
    if (!$formdata['name']) {
      throw new Exception('You must enter a name.');
    }
    $metric['nicename'] = $formdata['name'];
    $wpdb->escape($metric['nicename']);
    $metric['slug'] = sanitize_title($metric['nicename']);
    //check if name is unique
    if ($is_update) {
      $check_name_query = $wpdb->prepare('SELECT COUNT(*) FROM ' . EVAL_DB_METRICS
	      . ' WHERE slug=%s AND id<>%s', $metric['slug'], $current_data->id);
    } else {
      $check_name_query = $wpdb->prepare('SELECT COUNT(*) FROM ' . EVAL_DB_METRICS
	      . ' WHERE slug=%s', $metric['slug']);
    }
    $count = $wpdb->get_var($check_name_query);
    if ($count > 0) {
      throw new Exception('This metric name already exists.');
    }

    $metric['display_name'] = isset($formdata['display_name']);
    //type
    if (!isset($formdata['type'])) {
      throw new Exception('You must choose a type!');
    }
    $metric['type'] = $formdata['type'];
    $wpdb->escape($metric['type']);
    //style
    if ($metric['type'] == 'range') {
      $metric['style'] = 'star';
    } else if ($metric['type'] == 'poll') {
      $metric['style'] = 'poll';
    } else {
      if (!isset($formdata['style'])) {
	throw new Exception('You must choose a style!');
      } else {
	$metric['style'] = $formdata['style'];
      }
    }
    $wpdb->escape($metric['style']);
    //require_login & admin_only booleans
    $metric['require_login'] = isset($formdata['require_login']) && $formdata['require_login'];
    $metric['admin_only'] = isset($formdata['admin_only']) && $formdata['admin_only'];
    //params
    $metric['params'] = array();
    //poll params
    //firstly get rid of any answer fields that are empty
    $poll_answers = array_filter($formdata['poll']['answer']);
    if ($metric['type'] == 'poll') {
      if (!$formdata['poll']['question']) {
	throw new Exception('You must provide a question for the poll.');
      }
      if (count($poll_answers) < 2) {
	throw new Exception('You must provide at least 2 answers for the poll.');
      }
      $metric['params']['poll']['question'] = $formdata['poll']['question']; //question
      $metric['params']['poll']['answer'] = $poll_answers; //filtered answers
    }
    //content types
    $content_types = get_post_types(array('public' => true));
    foreach ($content_types as $content_type) {
      if (isset($formdata['content_type'][$content_type])) {
	$metric['params']['content_types'][] = $content_type;
      }
    }
    //serialize params
    $metric['params'] = serialize($metric['params']);
    //created and modified timestamps
    if (!$is_update) {
      $metric['created'] = date('Y/m/d H:i:s');
    }
    $metric['modified'] = date('Y/m/d H:i:s');
    //attempt to save
    if ($is_update) {
      $num_votes = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . EVAL_DB_VOTES . ' WHERE metric_id=%s', $current_data->id));
      if ($num_votes > 0 && $formdata['type'] != $current_data->type) {
	throw new Exception('You cannot change the type of this metric because there are votes registered.');
      }
      if ($wpdb->update(EVAL_DB_METRICS, $metric, array('id' => $current_data->id))) {
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

  /* form for adding new metrics or editing existing ones */
  public static function metric_form() {
    $metric_id = (isset($_REQUEST['metric_id']) ? $_REQUEST['metric_id'] : null);
    $content_types = get_post_types(array('public' => true));
    if (isset($metric_id)) {
      global $wpdb;

      $metric = $wpdb->get_row(
	      $wpdb->prepare('SELECT * FROM ' . EVAL_DB_METRICS
		      . ' WHERE id=%s'
		      , $metric_id));

      if (!$metric) {
	throw new Exception('The metric you are trying to edit does not exist!');
      }

      $formdata['metric_id'] = $metric->id;
      $formdata['name'] = $metric->nicename;
      $formdata['display_name'] = $metric->display_name;
      $formdata['type'] = $metric->type;
      $formdata['style'] = $metric->style;
      $params = unserialize($metric->params);
      $formdata['poll'] = (isset($params['poll']) ? $params['poll'] : null);

      if (isset($params['content_types'])) {
	foreach ($params['content_types'] as $content_type) {
	  $formdata['content_type'][$content_type] = true;
	}
      }

      $formdata['admin_only'] = $metric->admin_only;
      $formdata['require_login'] = $metric->require_login;

      $formdata['action'] = 'edit';
      $formdata['view'] = 'main';
    } else {
      if (isset($_POST['evalu_form'])) {
	$postdata = $_POST['evalu_form'];
	$formdata['name'] = (isset($postdata['name']) ? $postdata['name'] : null);
	$formdata['display_name'] = (isset($postdata['display_name']) ? $postdata['display_name'] : null);
	$formdata['type'] = (isset($postdata['type']) ? $postdata['type'] : null);
	$formdata['style'] = (isset($postdata['style']) ? $postdata['style'] : null);
	$formdata['poll'] = (isset($postdata['poll']) ? $postdata['poll'] : null);
	$formdata['admin_only'] = (isset($postdata['admin_only']) ? $postdata['admin_only'] : null);
	$formdata['require_login'] = (isset($postdata['require_login']) ? $postdata['require_login'] : null);

	if (isset($postdata['content_type'])) {
	  foreach ($postdata['content_type'] as $content_type => $bool) {
	    $formdata['content_type'][$content_type] = true;
	  }
	}

	$formdata['action'] = 'edit';
	$formdata['view'] = 'main';
      } else {
	$formdata['metric_id'] = null;
	$formdata['name'] = null;
	$formdata['display_name'] = null;
	$formdata['type'] = null;
	$formdata['style'] = null;
	$formdata['poll'] = null;
	$formdata['admin_only'] = null;
	$formdata['require_login'] = null;

	foreach ($content_types as $content_type) {
	  $formdata['content_type'][$content_type] = true;
	}

	$formdata['action'] = 'new';
	$formdata['view'] = 'main';
      }
    }
    $html_title = ($metric_id != null ? "Edit Metric" : "Add New Metric");
    ?>
    <h3><?php echo $html_title; ?></h3>
    <form method="post" action="?page=evaluate&view=form" id="metric_form">
      <table class="form-table">
        <tr>
          <th><label for="evalu_form[name]">Name</label></th>
          <td>
    	<input name="evalu_form[name]" type="text" class="regular-text" value="<?php echo $formdata['name']; ?>" /><br/>
    	<label><input type="checkbox" name="evalu_form[display_name]" value="true" <?php echo ($formdata['display_name'] ? 'checked="checked"' : null); ?> /> Display metric name above evaluation</label>
          </td>
        </tr>

        <tr>
          <th>Metric Type</th>
          <td>

    	<ul class="type_options">
    	  <li> <!-- one-way options -->
    	    <label class="type_label">
    	      <input type="radio" name="evalu_form[type]" value="one-way" <?php echo ($formdata['type'] == 'one-way' ? 'checked="checked"' : null); ?> />
    	      One-way Voting
    	    </label>
    	    <ul class="indent"> <!-- one way style -->
    	      <li>
    		<label>
    		  <input type="radio" name="evalu_form[style]" value="thumb"<?php echo ($formdata['type'] == 'one-way' && $formdata['style'] == 'thumb' ? 'checked="checked"' : null); ?> />
    		  0 <a class="rate thumb" title="<?php echo Evaluate::$titles['thumb']['up']; ?>"><?php echo Evaluate::$titles['thumb']['up']; ?></a>
    		</label>
    	      </li>
    	      <li>
    		<label>
    		  <input type="radio" name="evalu_form[style]" value="arrow" <?php echo ($formdata['type'] == 'one-way' && $formdata['style'] == 'arrow' ? 'checked="checked"' : null); ?> />
    		  0 <a class="rate arrow" title="<?php echo Evaluate::$titles['arrow']['up']; ?>"><?php echo Evaluate::$titles['arrow']['up']; ?></a>
    		</label>
    	      </li>
    	      <li>
    		<label>
    		  <input type="radio" name="evalu_form[style]" value="heart" <?php echo ($formdata['type'] == 'one-way' && $formdata['style'] == 'heart' ? 'checked="checked"' : null); ?> />
    		  0 <a class="rate heart" title="<?php echo Evaluate::$titles['heart']['up']; ?>"><?php echo Evaluate::$titles['heart']['up']; ?></a>
    		</label>
    	      </li>
    	    </ul>
    	  </li>

    	  <li> <!-- two-way options -->
    	    <label class="type_label">
    	      <input type="radio" name="evalu_form[type]" value="two-way" <?php echo ($formdata['type'] == 'two-way' ? 'checked="checked"' : null); ?> />
    	      Two-way Voting
    	    </label>
    	    <ul class="indent"> <!-- two-way style selection -->
    	      <li>
    		<label>
    		  <input type="radio" name="evalu_form[style]" value="thumb" <?php echo ($formdata['type'] == 'two-way' && $formdata['style'] == 'thumb' ? 'checked="checked"' : null); ?>/>
    		  0 <a class="rate thumb" title="<?php echo Evaluate::$titles['thumb']['up']; ?>">&nbsp;</a>
    		  0 <a class="rate thumb-down" title="<?php echo Evaluate::$titles['thumb']['down']; ?>">&nbsp;</a>
    		</label>
    	      </li>
    	      <li>
    		<label>
    		  <input type="radio" name="evalu_form[style]" value="arrow" <?php echo ($formdata['type'] == 'two-way' && $formdata['style'] == 'arrow' ? 'checked="checked"' : null); ?>/>
    		  0 <a class="rate arrow" title="<?php echo Evaluate::$titles['arrow']['up']; ?>">&nbsp;</a>
    		  0 <a class="rate arrow-down" title="<?php echo Evaluate::$titles['arrow']['down']; ?>">&nbsp;</a>
    		</label>
    	      </li>
    	    </ul>                
    	  </li>

    	  <li> <!-- range options -->
    	    <label class="type_label">
    	      <input type="radio" name="evalu_form[type]" value="range" <?php echo ($formdata['type'] == 'range' ? 'checked="checked"' : null); ?> />
    	      Stars
    	      <div class="rate-range">
    		<div class="stars">
    		  <div class="rating" style="width:50%"></div>
    		  <div class="starr"><a title="1<?php echo Evaluate::$titles['range']; ?>" class="eval-link">&nbsp;</a>
    		    <div class="starr"><a title="2<?php echo Evaluate::$titles['range']; ?>" class="eval-link">&nbsp;</a>
    		      <div class="starr"><a title="3<?php echo Evaluate::$titles['range']; ?>" class="eval-link">&nbsp;</a>
    			<div class="starr"><a title="4<?php echo Evaluate::$titles['range']; ?>" class="eval-link">&nbsp;</a>
    			  <div class="starr"><a title="5<?php echo Evaluate::$titles['range']; ?>" class="eval-link">&nbsp;</a>
    			  </div>
    			</div>
    		      </div>
    		    </div>
    		  </div>
    		</div>
    	      </div>
    	    </label>
    	  </li>

    	  <li> <!-- poll options -->
    	    <label class="type_label">
    	      <input type="radio" name="evalu_form[type]" value="poll" <?php echo ($formdata['type'] == 'poll' ? 'checked="checked"' : null); ?> />
    	      Poll
    	    </label>
    	    <div class="indent">
    	      <label>Question: <input type="text" class="regular-text" name="evalu_form[poll][question]" value="<?php echo $formdata['poll']['question']; ?>" /></label>
    	    </div>
    	    <div class="indent">
    	      <label>Answer 1: <input type="text" class="regular-text" name="evalu_form[poll][answer][1]" value="<?php echo $formdata['poll']['answer'][1]; ?>" /></label>
    	    </div>
    	    <div class="indent">
    	      <label>Answer 2: <input type="text" class="regular-text" name="evalu_form[poll][answer][2]" value="<?php echo $formdata['poll']['answer'][2]; ?>" /></label>
    	    </div>
		<?php
		if (count($formdata['poll']['answer']) > 2) {
		  for ($i = 3; $i <= count($formdata['poll']['answer']); $i++) {
		    ?>
		    <div class="indent">
		      <label>Answer <?php echo $i; ?>: <input type="text" class="regular-text" name="evalu_form[poll][answer][<?php echo $i; ?>]" value="<?php echo $formdata['poll']['answer'][$i]; ?>" /></label>
		    </div
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
	    foreach ($content_types as $content_type) {
	      ?><div>
      	  <label><input type="checkbox" name="evalu_form[content_type][<?php echo $content_type; ?>]" value="true" <?php echo (isset($formdata['content_type'][$content_type]) ? 'checked="checked"' : null); ?> /> <?php echo $content_type; ?></label>

      	</div>
	      <?php
	    }
	    ?>
          </td>
        </tr>

        <tr>
          <th>Display Options</th>
          <td>
    	<label><input type="checkbox" name="evalu_form[require_login]" value="true" <?php echo ($formdata['require_login'] ? 'checked="checked"' : null); ?> /> Users have to be logged in to vote.</label>
    	<br />
    	<label><input type="checkbox" name="evalu_form[admin_only]" value="true" <?php echo ($formdata['admin_only'] ? 'checked="checked"' : null); ?> /> Only Admins can see this metric.</label>
          </td>
        </tr>

        <tr>
          <th>Preview</th>
          <td>
    	<div id="preview_name" class="metric_preview"></div>
    	<div id="prev_one-way_heart" class="metric_preview"><?php //echo Evaluate::display_one_way(null, 'heart');                           ?></div>
    	<div id="prev_one-way_thumb" class="metric_preview"><?php //echo Evaluate::display_one_way(null, 'thumb');                           ?></div>
    	<div id="prev_one-way_arrow" class="metric_preview"><?php //echo Evaluate::display_one_way(null, 'arrow');                           ?></div>
    	<div id="prev_two-way_thumb" class="metric_preview"><?php //echo Evaluate::display_two_way(null, 'thumb');                           ?></div>
    	<div id="prev_two-way_arrow" class="metric_preview"><?php //echo Evaluate::display_two_way(null, 'arrow');                           ?></div>
    	<div id="prev_range_" class="metric_preview"><?php //echo Evaluate::display_range(null);                           ?></div>
    	<div id="prev_poll_" class="metric_preview"><?php //echo Evaluate::display_poll(null);                           ?></div>
          </td>
        </tr>
      </table>
      <input type="hidden" name="view" value="<?php echo $formdata['view']; ?>" />
      <input type = "hidden" name="action" value="<?php echo $formdata['action']; ?>" />
      <?php
      if (isset($formdata['metric_id'])) {
	?>
        <input type="hidden" name="metric_id" value="<?php echo $formdata['metric_id']; ?>" />
	<?php
      }
      wp_nonce_field('evaluate-' . $formdata['action']);
      submit_button();
      ?>
    </form>
    <?php
  }

  /* callback function for setting up the meta box for metric selection in admin area */
  public static function meta_box_setup() {
    add_action('add_meta_boxes', array('Evaluate_Admin', 'meta_box_add'));
  }

  /* callback to construct the meta box in post edit pages */
  public static function meta_box_add() {
    //we need one for every type of post we want the metabox to appear in
    $post_types = get_post_types(array('public' => true));
    foreach ($post_types as $post_type) {
      add_meta_box(//post
	      'evaluate-post-meta', __('Evaluate', 'Metrics'), array('Evaluate_Admin', 'evaluate_meta_box'), $post_type, 'side', 'default'
      );
    }
  }

  /* callback to construct contents of the meta box */
  public static function evaluate_meta_box($object, $box) {
    ?>
    <p>Check any available metric to <strong>not</strong> display it with this post.</p>
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
	        <input type="checkbox" name="evaluate_cb[<?php echo $metric->id; ?>]" <?php if (in_array($metric->id, $post_meta)) echo 'checked="checked"'; ?> />
		<?php echo $metric->nicename . ' - ' . $metric->type . ' - ' . $metric->style; ?>
	      </label>
	    </p>
	    <?php
	  }
	}
      }
    }
  }

  /* handle saving the post meta after any add/edit action to posts */
  public static function save_post_meta($post_id, $post_object) {
    global $meta_box, $wpdb;

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
	//we want to keep track of total votes and score for the metrics NOT in the list
	$total_votes = $wpdb->get_var(
		$wpdb->prepare('SELECT COUNT(*) FROM ' . EVAL_DB_VOTES . ' WHERE metric_id=%s AND content_id=%s'
			, $key, $post_id));

	$score = Evaluate::get_score($key, $post_id);
	delete_post_meta($post_id, 'metric', $key); //remove metric from blacklist
	update_post_meta($post_id, 'metric-' . $key . '-votes', $total_votes);
	update_post_meta($post_id, 'metric-' . $key . '-score', $score);
      } elseif ($cb == 'on' && !in_array($key, $post_meta)) {
	add_post_meta($post_id, 'metric', $key); //add metric to blacklist
	//remove unneeded metadata
	delete_post_meta($post_id, 'metric-' . $key . '-votes');
	delete_post_meta($post_id, 'metric-' . $key . '-score');
      }
    }


    return true;
  }

}
?>
