<?php

class Evaluate {

  static $options = array();
  static $user = null;

  function __construct($case = false) {
    if (!$case) {
      wp_die('Cannot call this class directly!', 'Error!');
    }
  }

  /*
   * first thing that runs before any code
   */
  public static function init() {
    self::$options['EVAL_DB_METRICS_VER'] = get_option('EVAL_DB_METRICS_VER');
    self::$options['EVAL_DB_VOTES_VER'] = get_option('EVAL_DB_VOTES_VER');
    // check to see if we have the required tables created, if not, create them
    if (self::$options['EVAL_DB_METRICS_VER'] < EVAL_DB_METRICS_VER || self::$options['EVAL_DB_VOTES_VER'] < EVAL_DB_VOTES_VER) {
      self::activate();
    }

    self::$options['EVAL_AJAX'] = get_option('EVAL_AJAX');

    //js and css script hook
    add_action('wp_enqueue_scripts', array('Evaluate', 'enqueue_scripts'));

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

  /*
   * remove the database tables created by Eval
   */
  public static function uninstall() {
    $sql = "DROP TABLE " . EVAL_DB_METRICS;
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    remove_option('EVAL_DB_METRICS_VER');

    $sql = "DROP TABLE " . EVAL_DB_VOTES;
    dbDelta($sql);
    remove_option('EVAL_DB_VOTES_VER');
  }

  /*
   * put the scripts and styles needed
   */
  public static function enqueue_scripts() {
    wp_register_style('evaluate', EVAL_DIR_URL . '/css/evaluate.css');

    wp_enqueue_style('evaluate');

    //put ajax script
    wp_enqueue_script('evaluate-js', plugins_url('/js/evaluate.js', dirname(__FILE__)), array('jquery'));

    //wp localize trick to pass params into js without direct printing
    $use_ajax = (self::$options['EVAL_AJAX'] ? 'true' : 'false');
    wp_localize_script('evaluate-js', 'evaluate_ajax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'use_ajax' => $use_ajax
    ));
  }

  /*
   * handles events requested from anywhere within wp
   */
  public static function event_handler() {
    $evaluate = $_REQUEST['evaluate'];
    switch ($evaluate) {
      case 'vote':
        try {
          self::vote($_REQUEST['metric_id'], $_REQUEST['content_id'], $_REQUEST['vote'], $_REQUEST['_wpnonce']);
//          self::alert('Vote submitted.', 'updated');
        } catch (Exception $e) {
//          self::alert($e->getMessage(), 'error');
        }
        break;
    }
  }

  /*
   * handle ajax voting events
   */
  public static function ajax_handler() {
    if (isset($_POST['data'])) {
      $data = $_POST['data'];
      if ($data['evaluate'] == 'poll') {
        if ($data['poll_display'] == 'results') {
          echo self::display_metric($data['metric_id'], $data['content_id'], true);
        } else {
          echo self::display_metric($data['metric_id'], $data['content_id'], false);
        }
        die();
      }
      try {
        self::vote($data['metric_id'], $data['content_id'], $data['vote'], $data['_wpnonce']);
        echo self::display_metric($data['metric_id'], $data['content_id']);
        die(); //prevent getting further content (specifically wp adds a 0 or 1 code which gets appended at the end of the response)
      } catch (Exception $e) {
        echo self::display_metric($data['metric_id'], $data['content_id']);
      }
    }
  }

  /*
   * get id of the current user
   */
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

  /*
   * process incoming votes for deletion, update or insert
   */
  public static function vote($metric_id, $content_id, $vote, $nonce) {
    global $wpdb;

    if (!wp_verify_nonce($nonce, 'evaluate-vote-' . $metric_id . '-' . $content_id . '-' . $vote . '-' . self::$user)
            && !wp_verify_nonce($nonce, 'evaluate-poll-' . $metric_id . '-' . $content_id . '-' . self::$user)) {
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
        return $wpdb->query($query);
      } else { //update vote from previous value
        return $wpdb->update(EVAL_DB_VOTES, array('vote' => $vote), array('metric_id' => $metric_id, 'content_id' => $content_id, 'user_id' => $data['user_id']));
      }
    } else { //add new vote
      return $wpdb->insert(EVAL_DB_VOTES, $data, array('%d', '%d', '%s', '%d', '%s'));
    }
  }

  /*
   * creates a vote url for a given metric and content id and vote count
   */
  public static function vote_url($metric_id, $content_id, $vote) {
    $nonce = wp_create_nonce('evaluate-vote-' . $metric_id . '-' . $content_id . '-' . $vote . '-' . self::$user);
    return sprintf("?evaluate=vote&metric_id=%s&content_id=%s&vote=%s&_wpnonce=%s", $metric_id, $content_id, $vote, $nonce);
  }

  /*
   * general function to display metrics
   */
  public static function display_metric($metric_id, $post_id = null, $results = false) {
    global $wpdb, $post;
    $query = $wpdb->prepare('SELECT * FROM ' . EVAL_DB_METRICS . ' WHERE id=%s', $metric_id);
    $metric = $wpdb->get_row($query);

    if (!$metric) { //cannot find the metric, could be deleted, do not display
      return;
    }

    //check if metric is admin only
    if ($metric->admin_only && !current_user_can('manage_options')) {
      return;
    }

    //set post id manually if one isn't set from the variables, or quit
    if (!isset($post->ID)) {
      if (!$post_id) {
        return;
      } else {
        $post->ID = $post_id;
      }
    }

    $html = '<div class="evaluate-shell">';
    switch ($metric->type) { //switch and feed the metric data to respective functions
      case 'one-way':
        $html .= self::display_one_way($metric);
        break;

      case 'two-way':
        $html .= self::display_two_way($metric);
        break;

      case 'range':
        $html .= self::display_range($metric);
        break;

      case 'poll':
        if ($results) {
          $html .= self::display_poll_results($metric);
        } else {
          $html .= self::display_poll($metric);
        }
        break;
    }
    $html .= '</div>';
    return $html;
  }

  /*
   * show one-way vote block
   * if $metric is given, fetch metric data and display that
   * if metric is not given, it means we want a preview, so look at $style
   * and display a preview of that style. If no parameters given then
   * the universe collapses into itself
   */
  public static function display_one_way($metric = null, $style = null) {
    $state = ''; //state of the button, whether if its toggled or not depending on previous vote

    if (!$metric) { //preview, set defaults
      $counter = 0;
      $link = 'javascript:void(0)';
      $display_name = '';
    } else { //metric supplied
      global $post, $wpdb;
      $post_id = (isset($post->ID) ? $post->ID : '');

      //check if user needs to be logged in to vote
      if (($metric->require_login && is_user_logged_in()) || !$metric->require_login) {
        $link = self::vote_url($metric->id, $post_id, 1);
      } else {
        $link = 'wp-login.php?action=register';
      }

      $style = $metric->style;
      $display_name = ($metric->display_name ? $metric->nicename : '');

      //check # votes
      $counter = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . EVAL_DB_VOTES . ' WHERE metric_id=%s AND content_id=%s', $metric->id, $post_id));

      if ($counter > 0) { //no need to run extra query if no votes
        //check if vote by user exists
        $query = $wpdb->prepare('SELECT * FROM ' . EVAL_DB_VOTES . ' WHERE metric_id=%s AND content_id=%s AND user_id=%s', $metric->id, $post_id, self::$user);
        $result = $wpdb->get_row($query);
        if ($result && $result->vote == 1) { //vote already exists
          $state = '-selected';
        }
      }
    }

    //link titles
    switch ($style) { //prepare link title and text
      case 'thumb':
        $title = 'Like';
        break;

      case 'heart':
        $title = 'Heart';
        break;

      case 'arrow':
        $title = 'Vote Up';
        break;

      default:
        $title = '';
        break;
    }

    $html = <<<HTML
<span class="rate-name">$display_name</span>
<div class="rate-div">
  <span class="up-counter">$counter</span>
  <a href="$link" class="rate $style$state eval-link" title="$title">$title</a>
</div>
HTML;

    return $html;
  }

  /*
   * displays two-way ratings
   * if $metric is passed, displays that metric, if not, $style determines
   * preview style.
   */
  public static function display_two_way($metric = null, $style = null) {
    $state_up = '';
    $state_down = '';
    if (!$metric) { //preview
      $up_counter = 0;
      $down_counter = 0;

      $link_up = 'javascript:void(0)';
      $link_down = 'javascript:void(0)';

      $display_name = '';
    } else {
      global $post, $wpdb;
      $post_id = (isset($post->ID) ? $post->ID : '');
      //get #votes
      $up_counter = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . EVAL_DB_VOTES . ' WHERE metric_id=%s AND content_id=%s AND vote=%s', $metric->id, $post_id, 1));
      $down_counter = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . EVAL_DB_VOTES . ' WHERE metric_id=%s AND content_id=%s AND vote=%s', $metric->id, $post_id, -1));
      if ($up_counter + $down_counter > 0) { //at least 1 vote either way
        //check if user voted
        $query = $wpdb->prepare('SELECT * FROM ' . EVAL_DB_VOTES . ' WHERE metric_id=%s AND content_id=%s AND user_id=%s', $metric->id, $post_id, self::$user);
        $result = $wpdb->get_row($query);
        if ($result && $result->vote == 1) { //vote already exists
          $state_up = '-selected';
        } elseif ($result && $result->vote == -1) {
          $state_down = '-selected';
        }
      }

      //links
      //check if user needs to be logged in to vote
      if (($metric->require_login && is_user_logged_in()) || !$metric->require_login) {
        $link_up = self::vote_url($metric->id, $post_id, 1);
        $link_down = self::vote_url($metric->id, $post_id, -1);
      } else {
        $link_up = 'wp-login.php?action=register';
        $link_down = 'wp-login.php?action=register';
      }

      $style = $metric->style;
      if ($metric->display_name) {
        $display_name = $metric->nicename;
      } else {
        $display_name = '';
      }
    }

    switch ($style) {
      case 'thumb':
        $title_up = 'Thumbs Up';
        $title_down = 'Thumbs Down';
        break;

      case 'arrow':
        $title_up = 'Vote Up';
        $title_down = 'Vote Down';
        break;
    }

    $html = <<<HTML
<span class="rate-name">$display_name</span>
<div class="rate-div">
    <span class="up-counter">$up_counter</span>
    <a href="$link_up" class="rate $style$state_up eval-link" title="$title_up">&nbsp;</a>
      
    <span class="up-counter">$down_counter</span>
    <a href="$link_down" class="rate $style-down$state_down eval-link" title="$title_down">&nbsp;</a>
</div>
HTML;

    return $html;
  }

  /*
   * display range ratings
   * if $metric is passed display that metric, if not display
   * a preview
   */
  public static function display_range($metric = null) {
    $active = ''; //vote active state
    if (!$metric) { //preview
      $average = 2.5;
      $width = $average / 5.0 * 100; //width for current rating
      $display_name = '';
    } else {
      global $post, $wpdb;
      $post_id = (isset($post->ID) ? $post->ID : '');
      //get # votes and total sum
      $num_votes = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . EVAL_DB_VOTES . ' WHERE metric_id=%s AND content_id=%s', $metric->id, $post_id));
      $total_sum = $wpdb->get_var($wpdb->prepare('SELECT SUM(vote) FROM ' . EVAL_DB_VOTES . ' WHERE metric_id=%s AND content_id=%s', $metric->id, $post_id));
      if ($num_votes > 0) {
        $average = $total_sum / $num_votes;
        //get if our user voted
        $user_voted = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . EVAL_DB_VOTES . ' WHERE metric_id=%s AND content_id=%s AND user_id=%s', $metric->id, $post_id, self::$user));
        if ($user_voted) {
          $active = '-selected';
          $width = $user_voted->vote / 5.0 * 100;
        } else {
          $width = $average / 5.0 * 100;
        }
      } else {
        $average = 0;
        $width = $average / 5.0 * 100;
      }
      if ($metric->display_name) {
        $display_name = $metric->nicename;
      } else {
        $display_name = '';
      }
    }

    $average = round($average, 1);
    $html = <<<HTML
<span class="rate-name">$display_name</span>
<div class="rate-range">
  <div class="rating-text">Average Vote: $average/5 Stars</div>
  <div class="stars">
    <div class="rating$active" style="width:$width%"></div>
HTML;

    for ($i = 1; $i <= 5; $i++) {
      $title = "$i/5 Stars";
      //check if user needs to be logged in to vote
      if ($metric && (($metric->require_login && is_user_logged_in()) || !$metric->require_login)) {
        $link = self::vote_url($metric->id, $post_id, $i);
      } else {
        $link = 'wp-login.php?action=register';
      }
      $html .= <<<HTML
      <div class="starr"><a href="$link" title="$title" class="eval-link">&nbsp;</a>
HTML;
    }

    $html .= <<<HTML
  </div></div></div></div></div>
  </div>
</div>
<div class="clear"></div>
HTML;
    return $html;
  }

  /*
   * display poll metric
   * if $metric, then display the metric, if not then preview
   */
  public static function display_poll($metric = null, $results = false) {
    $checked = ''; //poll form state
    if (!$metric) {
      $html = <<<HTML
<div class="poll-div">
  <form method="post" action="" name="poll-form">
    <ul class="poll-list">
      <li class="poll-question"></li>
      <li class="poll-answer"><label><input type="radio" name="poll-preview" /></label></li>
      <li class="poll-answer"><label><input type="radio" name="poll-preview" /></label></li>
    </ul>
    <input type="button" value="Cast Vote" />
    <a href="javascript:void(0);" title="See vote results!">Show Results</a>
  </form>
</div>
HTML;
    } else {
      global $post, $wpdb;
      $post_id = (isset($post->ID) ? $post->ID : '');
      $params = unserialize($metric->params);
      $question = $params['poll']['question'];
      $answers = $params['poll']['answer'];
      $nonce = wp_create_nonce('evaluate-poll-' . $metric->id . '-' . $post_id . '-' . self::$user);

      //check previous vote by user
      $prev_vote = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . EVAL_DB_VOTES . ' WHERE metric_id=%s AND content_id=%s AND user_id=%s', $metric->id, $post_id, self::$user));

      /* this is to achieve:
       * if the user voted: show results
       * if the user is not voted: show form
       * but it can be overridden for specific metrics
       *//*
      $content_id = (isset($_GET['content_id']) ? $_GET['content_id'] : false);
      if (isset($_GET['poll_display']) && $_GET['poll_display'] == 'results' && $content_id == $post_id) {
        return self::display_poll_results($metric);
      } elseif ($content_id != $post_id && $prev_vote) {
        return self::display_poll_results($metric);
      }
*/
      //check if user needs to be logged in to vote
      if ($metric && (($metric->require_login && is_user_logged_in()) || !$metric->require_login)) {
        $action = "?";
      } else {
        $action = '';
      }

      //construct html
      $html = <<<HTML
<div class="poll-div">
  <form method="post" action="$action" name="poll-form">
    <ul class="poll-list">
      <li class="poll-question">$question</li>
HTML;

      foreach ($answers as $key => $answer) {
        if ($prev_vote && $prev_vote->vote == $key) {
          $checked = ' checked="checked"';
        } else {
          $checked = '';
        }
        $html .= '<li class="poll-answer"><label><input type="radio" name="vote" value="' . $key . '" ' . $checked . '/>' . $answer . '</label></li>';
      }

      $html .= <<<HTML
    </ul>
    <input type="hidden" value="$nonce" name="_wpnonce" />
    <input type="hidden" name="metric_id" value="$metric->id" />
    <input type="hidden" name="content_id" value="$post_id" />
    <input type="hidden" name="evaluate" value="vote" />
    <input type="submit" value="Cast Vote" />
    <a href="?evaluate=poll&metric_id=$metric->id&content_id=$post_id&poll_display=results" title="See vote results!">Show Results</a>
  </form>
</div>
HTML;
    }

    return $html;
  }

  /*
   * display results of a given poll
   */
  public static function display_poll_results($poll, $content_id = null) {
    global $wpdb, $post;
    $post_id = (isset($post->ID) ? $post->ID : $content_id);
    $votes = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . EVAL_DB_VOTES . ' WHERE metric_id=%s AND content_id=%s', $poll->id, $post_id));
    $params = unserialize($poll->params);
    $question = $params['poll']['question'];
    $answers = $params['poll']['answer'];
    $answer_votes = array_fill(1, count($answers), 0);
    $user_vote = false;
    foreach ($votes as $vote) {
      $answer_votes[$vote->vote]++;
      if ($vote->user_id == self::$user) {
        $user_vote = $vote->vote;
      }
    }

    $total_sum = 0;
    foreach ($answer_votes as $answer_vote) {
      $total_sum += $answer_vote;
    }

    $html = <<<HTML
<div class="poll-div">
  <ul class="poll-list">
  <li class="poll-question">$question</li>
HTML;

    foreach ($answers as $key => $answer) {
      $average = ($total_sum < 1 ? 0 : round($answer_votes[$key] / $total_sum * 100, 1));
      $selected = ($user_vote == $key ? '-selected' : '');
      $html .= <<<HTML
    <li><strong>$answer:</strong> $average% ($answer_votes[$key] votes)
      <div class="poll-result"><div class="poll-bar$selected" style="width: $average%"></div></div>
    </li>
HTML;
    }

    $html .= <<<HTML
  </ul>
  <a href="?evaluate=poll&metric_id=$poll->id&content_id=$post_id&poll_display=vote" title="See voting form">Back to vote</a>
</div>
HTML;

    return $html;
  }

  /*
   * content to be displayed after every post
   */
  public static function content_display($content) {
    global $post; //get current post object
    $post_metrics = get_post_meta($post->ID, 'metric');
    if (isset($post_metrics) && $post_metrics) {
      foreach ($post_metrics as $metric_id) {
        $content .= Evaluate::display_metric($metric_id, $post->ID);
      }
    }

    return $content;
  }

}

?>
