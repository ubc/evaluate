<?php

/*
 * main class that takes care of everything that has to do with metrics, voting, display and setup
 */

class Evaluate {

  static $options = array(); //plugin options
  static $user = null; //current user id as known by the plugin
  static $stream_active = null; //is CTLT_Stream active
  static $titles = array(//titles for vote links
      'thumb' => array(
          'up' => 'Thumbs Up!',
          'down' => 'Thumbs Down!'
      ),
      'arrow' => array(
          'up' => 'Vote Up!',
          'down' => 'Vote Down!'
      ),
      'heart' => array(
          'up' => 'Heart!'
      ),
      'range' => '/5 Stars'
  );

  //******************************************************//
  // functions related to plugin setup and initialization //
  //******************************************************//

  function __construct($case = false) {
    if (!$case) {
      wp_die('Cannot call this class directly!', 'Error!');
    }
  }

  /* first thing that runs before any code */
  public static function init() {
    self::$options['EVAL_DB_METRICS_VER'] = get_option('EVAL_DB_METRICS_VER');
    self::$options['EVAL_DB_VOTES_VER'] = get_option('EVAL_DB_VOTES_VER');
    // check to see if we have the required tables created, if not, create them
    if (self::$options['EVAL_DB_METRICS_VER'] < EVAL_DB_METRICS_VER || self::$options['EVAL_DB_VOTES_VER'] < EVAL_DB_VOTES_VER) {
      self::activate();
    }

    //check if CTLT_Stream plugin exists to use with node
    if (!function_exists('is_plugin_active')) {
      //include plugins.php to check for other plugins from the frontend
      include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    self::$stream_active = is_plugin_active('stream/stream.php');
    self::$options['EVAL_AJAX'] = (!self::$stream_active ? false : get_option('EVAL_AJAX'));

    //js and css script hook
    add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
    //add a hook to footer to print out metric templates for doT
    add_action('wp_footer', array(__CLASS__, 'print_templates'));

    self::$user = self::get_user(); //get user, because we won't be able to set a cookie later in the file
    //handle any evaluate event that occurs
    if (isset($_REQUEST['evaluate'])) {
      self::event_handler();
    }
  }

  /*
   * create tables required for functioning upon activation
   * this should run only once
   */
  public static function activate() {
    global $wpdb;
    $metrics_table = EVAL_DB_METRICS;
    $sql = "CREATE TABLE $metrics_table (
    id bigint(11) NOT NULL AUTO_INCREMENT,
    slug varchar(64) NOT NULL,
    nicename varchar(64) NOT NULL,
    type varchar(10) NOT NULL DEFAULT 'one-way',
    style varchar(10) NOT NULL DEFAULT 'thumb',
    require_login tinyint(1) NOT NULL DEFAULT '1',
    admin_only tinyint(1) NOT NULL DEFAULT '0',
    display_name tinyint(1) NOT NULL DEFAULT '1',
    params longtext,
    created datetime,
    modified datetime,
    PRIMARY KEY  (id) );";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    add_option('EVAL_DB_METRICS_VER', EVAL_DB_METRICS_VER);

    $votes_table = EVAL_DB_VOTES;
    $sql = "CREATE TABLE $votes_table (
    id bigint(11) NOT NULL AUTO_INCREMENT,
    metric_id bigint(11) NOT NULL,
    content_id bigint(11) NOT NULL,
    user_id varchar(20) NOT NULL,
    vote int(11) NOT NULL,
    date datetime NOT NULL,
    PRIMARY KEY (id) );";
    dbDelta($sql);
    add_option('EVAL_DB_VOTES_VER', EVAL_DB_VOTES_VER);
  }

  /*
   * do nothing in deactivation, we don't want to remove
   * the tables in the database in case the user re-activates it
   */
  public static function deactivate() {
    
  }

  /* remove the database tables created by Eval */
  public static function uninstall() {
    $sql = "DROP TABLE " . EVAL_DB_METRICS;
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    remove_option('EVAL_DB_METRICS_VER');

    $sql = "DROP TABLE " . EVAL_DB_VOTES;
    dbDelta($sql);
    remove_option('EVAL_DB_VOTES_VER');
  }

  /* put the scripts and styles needed */
  public static function enqueue_scripts() {
    wp_register_style('evaluate', EVAL_DIR_URL . '/css/evaluate.css');
    wp_enqueue_style('evaluate');

    //put js
    wp_register_script('doT', EVAL_DIR_URL . '/js/doT.js', false, false, true);
    wp_register_script('evaluate-js', EVAL_DIR_URL . '/js/evaluate.js', array('jquery', 'doT'), false, true);
    wp_enqueue_script('doT');
    wp_enqueue_script('evaluate-js');

    //wp localize trick to pass params into js without direct printing
    $use_ajax = (self::$options['EVAL_AJAX'] ? true : false);
    wp_localize_script('evaluate-js', 'evaluate_ajax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'use_ajax' => $use_ajax,
        'user' => self::$user
    ));
  }

  /* get id of the current user */
  public static function get_user() {
    global $current_user;

    if (isset($current_user->ID) && $current_user->ID > 0) { //are we logged in from a legit account?
      return $current_user->ID;
    }

    $cookie_id = 'evaluate_user-' . md5(LOGGED_IN_KEY);

    if (isset($_COOKIE[$cookie_id])) { //check cookie
      return $_COOKIE[$cookie_id];
    } else { //user not logged in, set a cookie to keep track of the guest (for multiple voting)
      $time = time();
      setcookie($cookie_id, 'u' . $time, $time + 60 * 60 * 24 * 30 * 12 * 10); //10 years
      return 'u' . $time;
    }
    return $_SERVER['REMOTE_ADDR']; //last resort
  }

  //*************************************//
  // event and request handler functions //
  //*************************************//

  /* handles events requested from anywhere within wp */
  public static function event_handler() {
    switch ($_REQUEST['evaluate']) {
      case 'vote':
        return self::vote($_REQUEST['metric_id'], $_REQUEST['content_id'], $_REQUEST['vote'], $_REQUEST['_wpnonce']);
        break;

      case 'sort':
        //hook for changing post query
        add_action('pre_get_posts', array('Evaluate', 'pre_query'));
        break;
    }
  }

  /* handle ajax voting events */
  public static function ajax_handler() {
    $data = (isset($_POST['data']) ? $_POST['data'] : false);
    if ($data) {
      //handle poll view requests first
      if (isset($data['evaluate']) && $data['evaluate'] == 'poll') {
        $metric_data = self::get_data_by_id($data['metric_id'], $data['content_id']);
        echo self::display_poll($metric_data);
        die();
      }
      //now handle vote requests
      echo self::vote($data['metric_id'], $data['content_id'], $data['vote'], $data['_wpnonce']);
    }
    die();
  }

  /* content hook for main wordpress loop */
  public static function content_display($content) {
    global $wpdb, $post;

    //get all metrics, then filter out excluded ones
    $metrics = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . EVAL_DB_METRICS));
    $excluded = get_post_meta($post->ID, 'metric');

    foreach ($metrics as $metric) {
      $params = unserialize($metric->params);
      $content_types = $params['content_types'];
      if (!in_array($metric->id, $excluded) && in_array($post->post_type, $content_types)) { //not excluded
        $data = self::get_metric_data($metric);
        $content .= self::display_metric($data);
      }
    }

    return $content;
  }

  //**************************//
  // voting related functions //
  //**************************//

  /* process incoming votes for deletion, update or insert */
  public static function vote($metric_id, $content_id, $vote, $nonce) {
    global $wpdb;

    if (!wp_verify_nonce($nonce, 'evaluate-vote-' . $metric_id . '-' . $content_id . '-' . $vote . '-' . self::$user)
            && !wp_verify_nonce($nonce, 'evaluate-vote-poll-' . $metric_id . '-' . $content_id . '-' . self::$user)) {
      throw new Exception('Nonce check failed. Did you mean to do this action?');
    }

    $data = array(); //to hold vote data for db
    $data['metric_id'] = $metric_id;
    $data['content_id'] = $content_id;
    $data['user_id'] = self::$user;
    $data['vote'] = $vote;
    $data['date'] = date('Y-m-d H:i:s');

    //check if vote exists first
    $query = $wpdb->prepare('SELECT * FROM ' . EVAL_DB_VOTES . ' WHERE metric_id=%s AND content_id=%s AND user_id=%s', $metric_id, $content_id, $data['user_id']);
    $prev_vote = $wpdb->get_row($query);
    if ($prev_vote) {
      if ($vote == $prev_vote->vote) { //same vote twice constitutes a 'toggle', remove vote
        $query = $wpdb->prepare('DELETE FROM ' . EVAL_DB_VOTES . ' WHERE id=%d', $prev_vote->id);
        $result = $wpdb->query($query);
      } else { //update vote from previous value
        $result = $wpdb->update(EVAL_DB_VOTES, array('vote' => $vote), array('metric_id' => $metric_id, 'content_id' => $content_id, 'user_id' => $data['user_id']));
      }
    } else { //add new vote
      $result = $wpdb->insert(EVAL_DB_VOTES, $data, array('%d', '%d', '%s', '%d', '%s'));
    }

    if ($result) {
      $metric_data = self::get_data_by_id($data['metric_id'], $data['content_id']);
      $metric_data->user = self::$user;
      if (self::$stream_active) {
        CTLT_Stream::send('evaluate', $metric_data, 'vote');
      } else {
        self::display_metric($metric_data);
      }
    }

    $total_votes = $wpdb->get_var(
            $wpdb->prepare('SELECT COUNT(*) FROM ' . EVAL_DB_VOTES . ' WHERE metric_id=%s AND content_id=%s'
                    , $metric_id, $content_id));

    $score = Evaluate::get_score($metric_id, $content_id);

    update_post_meta($content_id, 'metric-' . $metric_id . '-votes', $total_votes);
    update_post_meta($content_id, 'metric-' . $metric_id . '-score', $score);

    return $result;
  }

  /* create a url to vote */
  public static function vote_url($metric, $content_id, $vote) {
    if ($metric->admin_only && !current_user_can('manage_options')) {
      return 'javascript:void(0);';
    }
    if ($metric->require_login && !is_user_logged_in()) {
      return 'wp-login.php?action=register';
    }

    return sprintf("?evaluate=vote&metric_id=%s&content_id=%s&vote=%s", $metric->id, $content_id, $vote);
  }

  //********************************************************//
  // data functions to get all required data about a metric //
  //********************************************************//

  public static function get_data_by_id($metric_id, $content_id) {
    global $wpdb, $post;

    $metric = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . EVAL_DB_METRICS
                    . ' WHERE id=%s'
                    , $metric_id));
    if ($metric) {
      //force specific post data
      $post = wp_get_single_post($content_id);
      if (isset($post)) {
        setup_postdata($post);
      } else { //no post id set, avoid warnings
        $post->ID = 0;
      }
      //get metric data
      return self::get_metric_data($metric);
    }
  }

  /* convenience function to handle any metric */
  public static function get_metric_data($metric) {
    switch ($metric->type) {
      case 'one-way':
        $data = self::one_way_data($metric);
        break;

      case 'two-way':
        $data = self::two_way_data($metric);
        break;

      case 'range':
        $data = self::range_data($metric);
        break;

      case 'poll':
        $data = self::poll_data($metric);
        break;

      default:
        $data = null;
        break;
    }

    return $data;
  }

  public static function one_way_data($metric) {
    global $wpdb, $post;

    $data = new stdClass(); //data declaration
    $data->metric_id = $metric->id;
    $data->content_id = $post->ID;
    $data->display_name = ($metric->display_name ? $metric->nicename : null); //check if display name is enabled
    $data->type = $metric->type;
    $data->admin_only = $metric->admin_only;
    //count the number of votes
    $data->counter = $wpdb->get_var(
            $wpdb->prepare('SELECT COUNT(*) FROM ' . EVAL_DB_VOTES
                    . ' WHERE metric_id=%s AND content_id=%s'
                    , $metric->id, $post->ID));
    //if there are votes check to see if our user is one of them
    if ($data->counter > 0) {
      $data->user_vote = $wpdb->get_var(
              $wpdb->prepare('SELECT vote FROM ' . EVAL_DB_VOTES
                      . ' WHERE metric_id=%s AND content_id=%s AND user_id=%s'
                      , $metric->id, $post->ID, self::$user));
    } else {
      $data->user_vote = false;
    }
    //set state of the link
    $data->state = ($data->user_vote && $data->user_vote == 1 ? '-selected' : '');
    $data->link = self::vote_url($metric, $post->ID, 1); //upvote link
    $data->style = $metric->style;
    $data->title = self::$titles[$metric->style]['up'];
    $data->nonce = wp_create_nonce('evaluate-vote-' . $data->metric_id . '-' . $data->content_id . '-1-' . self::$user);

    return $data;
  }

  public static function two_way_data($metric) {
    global $wpdb, $post;

    $data = new stdClass();
    $data->metric_id = $metric->id;
    $data->content_id = $post->ID;
    $data->display_name = ($metric->display_name ? $metric->nicename : null); //name display
    $data->type = $metric->type;
    $data->admin_only = $metric->admin_only;
    //get votes
    $data->counter = $wpdb->get_results(
            $wpdb->prepare('SELECT vote, COUNT(vote) as count FROM ' . EVAL_DB_VOTES
                    . ' WHERE metric_id=%s AND content_id=%s GROUP BY vote'
                    , $metric->id, $post->ID)
            , OBJECT_K); //key gets the value of vote column for easy access below
    //assign votes
    $data->counter_up = (isset($data->counter[1]) ? $data->counter[1]->count : 0);
    $data->counter_down = (isset($data->counter[-1]) ? $data->counter[-1]->count : 0);

    $data->counter_total = $data->counter_up - $data->counter_down; //total
    //get user's vote if exists
    if ($data->counter_up + $data->counter_down > 0) {
      $data->user_vote = $wpdb->get_var(
              $wpdb->prepare('SELECT vote FROM ' . EVAL_DB_VOTES
                      . ' WHERE metric_id=%s AND content_id=%s AND user_id=%s'
                      , $metric->id, $post->ID, self::$user));
    } else {
      $data->user_vote = false;
    }
    //set link state if user has voted
    $data->state_up = ($data->user_vote && $data->user_vote == 1 ? '-selected' : '');
    $data->state_down = ($data->user_vote && $data->user_vote == -1 ? '-selected' : '');
    //vote links
    $data->link_up = self::vote_url($metric, $post->ID, 1);
    $data->link_down = self::vote_url($metric, $post->ID, -1);
    //titles
    $data->title_up = self::$titles[$metric->style]['up'];
    $data->title_down = self::$titles[$metric->style]['down'];
    $data->style = $metric->style;
    $data->nonce_up = wp_create_nonce('evaluate-vote-' . $data->metric_id . '-' . $data->content_id . '-1-' . self::$user);
    $data->nonce_down = wp_create_nonce('evaluate-vote-' . $data->metric_id . '-' . $data->content_id . '--1-' . self::$user);

    return $data;
  }

  public static function range_data($metric) {
    global $wpdb, $post;

    $data = new stdClass(); //data object
    $data->metric_id = $metric->id;
    $data->content_id = $post->ID;
    $data->display_name = ($metric->display_name ? $metric->nicename : null); //display name available?
    $data->type = $metric->type;
    $data->admin_only = $metric->admin_only;
    //get sum of votes and the total number of votes
    $data->votes = $wpdb->get_results(
            $wpdb->prepare('SELECT vote, COUNT(vote) as count FROM ' . EVAL_DB_VOTES
                    . ' WHERE metric_id=%s AND content_id=%s GROUP BY vote'
                    , $metric->id, $post->ID)
            , OBJECT_K);
    //calculate the average
    $data->average = 0;
    $total_votes = 0;
    foreach ($data->votes as $vote) {
      $data->average += $vote->vote * $vote->count;
      $total_votes += $vote->count;
    }
    if ($total_votes > 0) {
      $data->average /= $total_votes;
    } else {
      $data->average = 0;
    }
    $data->total_votes = $total_votes;
    $data->average = round($data->average, 1);

    //if there are votes, check to see if our user voted
    if (count($data->votes) > 0) {
      $data->user_vote = $wpdb->get_var(
              $wpdb->prepare('SELECT vote FROM ' . EVAL_DB_VOTES
                      . ' WHERE metric_id=%s AND content_id=%s and user_id=%s'
                      , $metric->id, $post->ID, self::$user));
    } else {
      $data->user_vote = false;
    }

    //state and width
    $data->state = ($data->user_vote ? '-selected' : '');
    $data->width = ($data->user_vote ? $data->user_vote : $data->average) / 5.0 * 100;

    for ($i = 1; $i <= 5; $i++) {
      $data->link[$i] = self::vote_url($metric, $post->ID, $i);
      $data->nonce[$i] = wp_create_nonce('evaluate-vote-' . $data->metric_id . '-' . $data->content_id . '-' . $i . '-' . self::$user);
    }
    return $data;
  }

  public static function poll_data($metric) {
    global $wpdb, $post;

    $data = new stdClass();
    $data->metric_id = $metric->id;
    $data->content_id = $post->ID;
    $data->display_name = ($metric->display_name ? $metric->nicename : null); //display name available?
    $data->type = $metric->type;
    $data->admin_only = $metric->admin_only;
    //get poll question and answers
    $params = unserialize($metric->params);
    $data->question = $params['poll']['question'];
    $data->answers = $params['poll']['answer'];
    $data->votes = $wpdb->get_results(
            $wpdb->prepare('SELECT vote, COUNT(vote) as count FROM ' . EVAL_DB_VOTES
                    . ' WHERE metric_id=%s AND content_id=%s GROUP BY vote'
                    , $metric->id, $post->ID)
            , OBJECT_K); //returned array will have vote value for keys
    //count total votes
    $data->total_votes = 0;
    foreach ($data->votes as $vote) {
      $data->total_votes += $vote->count;
    }
    //if there are votes, check if our user voted
    if (count($data->votes) > 0) {
      $data->user_vote = $wpdb->get_var(
              $wpdb->prepare('SELECT vote FROM ' . EVAL_DB_VOTES
                      . ' WHERE metric_id=%s AND content_id=%s AND user_id=%s'
                      , $metric->id, $post->ID, self::$user));
    } else {
      $data->user_vote = false;
    }
    //loop through answers and calculate percentage vote
    $data->averages = array();
    $data->answer_votes = array();
    foreach ($data->answers as $key => $answer) {
      $data->averages[$key] = ($data->total_votes > 0 && isset($data->votes[$key]) ? round($data->votes[$key]->count / $data->total_votes * 100, 1) : 0);
      $data->answer_votes[$key] = (isset($data->votes[$key]) ? $data->votes[$key]->count : 0);
    }
    //create nonce and other hidden form field values
    $data->nonce = wp_create_nonce('evaluate-vote-poll-' . $metric->id . '-' . $post->ID . '-' . self::$user);
    $data->metric_id = $metric->id;
    $data->content_id = $post->ID;

    return $data;
  }

  //*********************************************************//
  // functions to display any metric, needs $data from above //
  //*********************************************************//

  /* convenience function to handle any metric display */
  public static function display_metric($data) {
    if ($data->admin_only) {
      if(!current_user_can('administrator')) {
        return;
      }
    }
//    $html = '<div class="evaluate-shell" id="evaluate-shell-' . $data->metric_id . '-' . $data->content_id . '">';
    $html = <<<HTML
<div class="evaluate-shell" id="evaluate-shell-$data->metric_id-$data->content_id" data-user-vote="$data->user_vote">
HTML;
    switch ($data->type) {
      case 'one-way':
        $html .= self::display_one_way($data);
        break;

      case 'two-way':
        $html .= self::display_two_way($data);
        break;

      case 'range':
        $html .= self::display_range($data);
        break;

      case 'poll':
        $html .= self::display_poll($data);
        break;

      default:
        break;
    }
    $html .= '</div>';

    return $html;
  }

  public static function display_one_way($data) {
    $html = <<<HTML
<span class="rate-name">$data->display_name</span>
  <div class="rate-div">
    <span class="up-counter">$data->counter</span>
    <a href="$data->link" class="rate $data->style$data->state eval-link" title="$data->title" data-nonce="$data->nonce">$data->title</a>
  </div>
</span>
HTML;

    return $html;
  }

  public static function display_two_way($data) {
    $html = <<<HTML
<span class="rate-name">$data->display_name</span>
<div class="rate-div">
    <span class="up-counter">$data->counter_up</span>
    <a href="$data->link_up" class="rate $data->style$data->state_up eval-link link-up" title="$data->title_up" data-nonce="$data->nonce_up">&nbsp;</a>
      
    <span class="up-counter">$data->counter_down</span>
    <a href="$data->link_down" class="rate $data->style-down$data->state_down eval-link link-down" title="$data->title_down" data-nonce="$data->nonce_down">&nbsp;</a>
</div>
HTML;

    return $html;
  }

  public static function display_range($data) {
    $html = <<<HTML
<span class="rate-name">$data->display_name</span>
<div class="rate-range">
  <div class="rating-text">Average Vote: $data->average/5 Stars</div>
  <div class="stars">
    <div class="rating$data->state" style="width:$data->width%"></div>
HTML;
    //nested divs for star links
    for ($i = 1; $i <= 5; $i++) {
      $link = $data->link[$i];
      $nonce = $data->nonce[$i];
      $title = $i . self::$titles['range'];
      $html .= <<<HTML
    <div class="starr"><a href="$link" title="$title" class="eval-link link-$i" data-nonce="$nonce">&nbsp;</a>
HTML;
    }
    //close nested divs
    for ($i = 1; $i <= 5; $i++) {
      $html .= <<<HTML
    </div>
HTML;
    }

    $html .= <<<HTML
 </div>
</div>
<div class="clear"></div>
HTML;

    return $html;
  }

  /* chooses between form and results according to request */
  public static function display_poll($data) {
    global $post;
    //if a specific view is set, it takes precedence over default behavior
    //assign $_POST['data'], $_REQUEST['evaluate'] or FALSE, whichever is available
    $request = (isset($_POST['data']) ? $_POST['data'] : (isset($_REQUEST['evaluate']) ? $_REQUEST['evaluate'] : false));

    $request_condition = ($request['evaluate'] == 'poll'
            && $request['metric_id'] == $data->metric_id
            && $request['content_id'] == $post->ID);

    if ($request_condition) {
      switch ($request['display']) {
        case 'results':
          return self::display_poll_results($data);
          break;

        case 'vote':
        default:
          return self::display_poll_form($data);
          break;
      }
    }
    if ($data->user_vote) {
      return self::display_poll_results($data);
    } else {
      return self::display_poll_form($data);
    }
  }

  public static function display_poll_form($data) {
    $html = <<<HTML
<span class="rate-name">$data->display_name</span>
<div class="poll-div poll-form">
  <form method="post" action="" name="poll-form">
    <ul class="poll-list">
      <li class="poll-question">$data->question</li>
HTML;
    //loop through & print answers
    foreach ($data->answers as $key => $answer) {
      $selected = ($data->user_vote == $key ? 'checked="checked"' : null);
      $html .= <<<HTML
      <li class="poll-answer">
        <label>
          <input type="radio" name="vote" value="$key" $selected /> 
          $answer
        </label>
      </li>
HTML;
    }
    //put hidden fields for verification & other form elements
    $html .= <<<HTML
    </ul>
    <input type="hidden" value="$data->nonce" name="_wpnonce" />
    <input type="hidden" value="$data->metric_id" name="metric_id" />
    <input type="hidden" value="$data->content_id" name="content_id" />
    <input type="hidden" value="vote" name="evaluate" />
    <input type="submit" value="Cast Vote" />
    <a href="?evaluate=poll&metric_id=$data->metric_id&content_id=$data->content_id&display=results" title="See vote results!">Show Results</a>
  </form>
</div>
HTML;

    return $html;
  }

  public static function display_poll_results($data) {
    $html = <<<HTML
<span class="rate-name">$data->display_name</span>
<div class="poll-div poll-results">
  <ul class="poll-list">
    <li class="poll-question">$data->question</li>
HTML;
    //loop through answers and calculate percentage vote
    foreach ($data->answers as $key => $answer) {
      $selected = ($data->user_vote == $key ? '-selected' : null);
      $average = $data->averages[$key];
      $answer_votes = $data->answer_votes[$key];
      $html .= <<<HTML
     <li>
       <strong>$answer:</strong> $average% ($answer_votes votes)
       <div class="poll-result">
        <div class="poll-bar$selected" style="width:$average%"></div>
       </div>
     </li>
HTML;
    }
    //vote form link
    $html .= <<<HTML
  </ul>
  <a href="?evaluate=poll&metric_id=$data->metric_id&content_id=$data->content_id&display=vote" title="Back to vote">Back to vote</a>
</div>
HTML;

    return $html;
  }

  /* prints out metric templates for ajax viewing */
  public static function print_templates() {
    ?>
    <script id="evaluate-one-way" type="text/x-dot-template">
      <span class="rate-name">{{=it.display_name}}</span>
      <div class="rate-div">
        <span class="up-counter">{{=it.counter}}</span>
        <a href="{{=it.link}}" class="rate {{=it.style}}{{=it.state}} eval-link" title="{{=it.title}}" data-nonce="{{=it.nonce}}">{{=it.title}}</a>
      </div>
    </span>
    </script>

    <script id="evaluate-two-way" type="text/x-dot-template">
      <span class="rate-name">{{=it.display_name}}</span>
      <div class="rate-div">
        <span class="up-counter">{{=it.counter_up}}</span>
        <a href="{{=it.link_up}}" class="rate {{=it.style}}{{=it.state_up}} eval-link link-up" title="{{=it.title_up}}" data-nonce="{{=it.nonce_up}}">&nbsp;</a>

        <span class="up-counter">{{=it.counter_down}}</span>
        <a href="{{=it.link_down}}" class="rate {{=it.style}}-down{{=it.state_down}} eval-link link-down" title="{{=it.title_down}}" data-nonce="{{=it.nonce_down}}">&nbsp;</a>
      </div>
    </script>

    <script id="evaluate-range" type="text/x-dot-template">
      <span class="rate-name">{{=it.display_name}}</span>
      <div class="rate-range">
        <div class="rating-text">Average Vote: {{=it.average}}/5 Stars</div>
        <div class="stars">
          <div class="rating{{=it.state}}" style="width:{{=it.width}}%"></div>
          {{ for(var prop in it.link) { }}
          <div class="starr"><a href="{{=it.link[prop]}}" title="" class="eval-link link-{{=prop}}" data-nonce="{{=it.nonce[prop]}}">&nbsp;</a>
            {{ } }}
            {{ for(var prop in it.link) { }}
          </div>
          {{ } }}
        </div>
      </div>
      <div class="clear"></div>
    </script>

    <script id="evaluate-poll-form" type="text/x-dot-template">
      <span class="rate-name">{{=it.display_name}}</span>
      <div class="poll-div poll-form">
        <form method="post" action="" name="poll-form">
          <ul class="poll-list">
            <li class="poll-question">{{=it.question}}</li>
            {{ for(var prop in it.answers) { }}
            {{? it.user_vote == prop }}
            <li class="poll-answer">
              <label>
                <input type="radio" name="vote" value="{{=prop}}" checked="checked" /> 
                {{=it.answers[prop]}}
              </label>
            </li>
            {{??}}
            <li class="poll-answer">
              <label>
                <input type="radio" name="vote" value="{{=prop}}" /> 
                {{=it.answers[prop]}}
              </label>
            </li>
            {{?}}
            {{ } }}
          </ul>
          <input type="hidden" value="{{=it.nonce}}" name="_wpnonce" />
          <input type="hidden" value="{{=it.metric_id}}" name="metric_id" />
          <input type="hidden" value="{{=it.content_id}}" name="content_id" />
          <input type="hidden" value="vote" name="evaluate" />
          <input type="submit" value="Cast Vote" />
          <a href="?evaluate=poll&metric_id={{=it.metric_id}}&content_id={{=it.content_id}}&display=results" title="See vote results!">Show Results</a>
        </form>
      </div>
    </script>

    <script id="evaluate-poll-results" type="text/x-dot-template">
      <div class="poll-div poll-results">
        <ul class="poll-list">
          <li class="poll-question">{{=it.question}}</li>
          {{ for(prop in it.answers) { }}
          <li>
            <strong>{{=it.answers[prop]}}</strong> {{=it.averages[prop]}} ({{=it.answer_votes[prop]}} votes)
            <div class="poll-result">
              {{? it.user_vote == prop }}
              <div class="poll-bar-selected" style="width:{{=it.averages[prop]}}%"></div>
              {{??}}
              <div class="poll-bar" style="width:{{=it.averages[prop]}}%"></div>
              {{?}}
            </div>
          </li>
          {{ } }}
        </ul>
        <a href="?evaluate=poll&metric_id={{=it.metric_id}}&content_id={{=it.content_id}}&display=vote" title="Back to vote">Back to vote</a>
      </div>
    </script>
    <?php

  }

  public static function pre_query($query) {
    if ($query->is_home() && $query->is_main_query()) {
      global $wpdb;
      /*
       * score asc/desc : score
       * total votes asc/desc : popularity (most/least)
       * has user votes
       */
      if(!isset($_REQUEST['sort'])) {
        return;
      }
      
      switch ($_REQUEST['sort']) {
        case 'score':
          $metric_id = (isset($_REQUEST['metric_id']) ? $_REQUEST['metric_id'] : false);
          $order = (isset($_REQUEST['order']) ? $_REQUEST['order'] : 'desc');
          $query->set('meta_key', 'metric-' . $metric_id . '-score');
          $query->set('orderby', 'meta_value_num');
          $query->set('order', $order);
          break;
        
        case 'total_votes':
          $metric_id = (isset($_REQUEST['metric_id']) ? $_REQUEST['metric_id'] : false);
          $order = (isset($_REQUEST['order']) ? $_REQUEST['order'] : 'desc');
          $query->set('meta_key', 'metric-' . $metric_id . '-votes');
          $query->set('orderby', 'meta_value_num');
          $query->set('order', $order);
          break;
        
        case 'user_votes':
          $posts = $wpdb->get_col(
                  $wpdb->prepare('SELECT content_id FROM ' . EVAL_DB_VOTES . ' WHERE user_id=%s'
                          , self::$user));
          $query->set('post__in', $posts);
          break;
      }
    }
  }

  /* get score for any metric-post pair */
  public static function get_score($metric_id, $content_id) {
    $data = self::get_data_by_id($metric_id, $content_id);
    switch ($data->type) {
      case 'one-way':
        return $data->counter;
        break;

      case 'two-way':
        return self::calculate_wilson_score($data->counter_up, $data->counter_total);
        break;

      case 'range':
        return self::calculate_bayesian_score($data->average, $data->total_votes);
        break;
    }
  }

  /* assumes score inherently tends to 3 out of 5 i.e. bayesian prior is 3 */
  public static function calculate_bayesian_score($average, $total) {
    return (3 + $average * $total) / (1 + $total);
  }

  /* taken from http://derivante.com/2009/09/01/php-content-rating-confidence/ 
   * calculates the wilson score: a lower bound on the "true" value of
   * the ratio of positive votes and total votes, given a confidence level
   */
  public static function calculate_wilson_score($positive, $total, $power = '0.05') {
    if ($total == 0)
      return 0;
    $z = self::pnormaldist(1 - $power / 2);
    $p = 1.0 * $positive / $total;
    $s = ($p + $z * $z / (2 * $total) - $z * sqrt(($p * (1 - $p) + $z * $z / (4 * $total)) / $total)) / (1 + $z * $z / $total);
    return $s;
  }

  /* taken from http://derivante.com/2009/09/01/php-content-rating-confidence/ 
   * calculates z value for a given pct point $qn in the standard normal distribution
   * with sigma=1 and mean=0
   */
  public static function pnormaldist($qn) {
    $b = array(
        1.570796288, 0.03706987906, -0.8364353589e-3,
        -0.2250947176e-3, 0.6841218299e-5, 0.5824238515e-5,
        -0.104527497e-5, 0.8360937017e-7, -0.3231081277e-8,
        0.3657763036e-10, 0.6936233982e-12);
    if ($qn < 0.0 || 1.0 < $qn)
      return 0.0;
    if ($qn == 0.5)
      return 0.0;
    $w1 = $qn;
    if ($qn > 0.5)
      $w1 = 1.0 - $w1;
    $w3 = - log(4.0 * $w1 * (1.0 - $w1));
    $w1 = $b[0];
    for ($i = 1; $i <= 10; $i++)
      $w1 += $b[$i] * pow($w3, $i);
    if ($qn > 0.5)
      return sqrt($w1 * $w3);
    return - sqrt($w1 * $w3);
  }

}
?>
