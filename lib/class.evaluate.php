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

    //js and css script hook
    add_action('wp_enqueue_scripts', array('Evaluate', 'enqueue_scripts'));
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
    user_id bigint(11) NOT NULL,
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
   * put the evaluate.css style in the queue to be linked
   */
  public static function enqueue_scripts() {
    wp_register_style('evaluate', EVAL_DIR_URL . '/css/evaluate.css');

    wp_enqueue_style('evaluate');
  }

  /*
   * general function to display metrics
   */
  public static function display_metric($metric_id) {
    global $wpdb;
    $query = $wpdb->prepare('SELECT * FROM ' . EVAL_DB_METRICS . ' WHERE id=%s', $metric_id);
    $metric = $wpdb->get_row($query);

    if (!$metric) { //cannot find the metric, could be deleted, do not display
      return;
    }

    switch ($metric->type) { //switch and feed the metric data to respective functions
      case 'one-way':
        echo Evaluate::display_one_way($metric);
        break;

      case 'two-way':
        echo Evaluate::display_two_way($metric);
        break;

      case 'range':
        echo Evaluate::display_range($metric);
        break;

      case 'poll':
        echo Evaluate::display_poll($metric);
        break;
    }
  }
  
  /*
   * show one-way vote block
   * if $metric is given, fetch metric data and display that
   * if metric is not given, it means we want a preview, so look at $style
   * and display a preview of that style. If no parameters given then
   * the universe collapses into itself
   */
  public static function display_one_way($metric = null, $style = null) {
    if (!$metric) { //preview, set defaults
      $counter = 0;
      $link = 'javascript:void(0)';
      $display_name = '';
    } else {
      $counter = 5;
      $link = 'javascript:void(0);';
      $style = $metric->style;
      if ($metric->display_name) {
        $display_name = $metric->nicename;
      } else {
        $display_name = '';
      }
    }

    switch ($style) {
      case 'thumb':
        $title = 'Like';
        break;

      case 'heart':
        $title = 'Heart';
        break;

      case 'arrow':
        $title = 'Vote Up';
        break;
    }

    $html = <<<HTML
<span class="rate-name">$display_name</span>
<div class="rate-div">
  <span class="up-counter">$counter</span>
  <a href="$link" class="rate $style" title="$title">$title</a>
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
    if (!$metric) { //preview
      $up_counter = 0;
      $down_counter = 0;

      $link_up = 'javascript:void(0)';
      $link_down = 'javascript:void(0)';

      $display_name = '';
    } else {
      $up_counter = 5;
      $down_counter = 5;
      $link_up = 'javascript:void(0)';
      $link_down = 'javascript:void(0)';
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
    <a href="$link_up" class="rate $style" title="$title_up">&nbsp;</a>
      
    <span class="up-counter">$down_counter</span>
    <a href="$link_down" class="rate $style-down" title="$title_down">&nbsp;</a>
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
    if (!$metric) { //preview
      $average = 2.5;
      $width = $average / 5.0 * 100; //width for current rating
      $display_name = '';
    } else {
      $average = 5;
      $width = $average / 5.0 * 100;
      if ($metric->display_name) {
        $display_name = $metric->nicename;
      } else {
        $display_name = '';
      }
    }

    $html = <<<HTML
<span class="rate-name">$display_name</span>
<div class="rate-range">
  <div class="rating-text">Average Vote: $average/5 Stars</div>
  <div class="stars">
    <div class="rating" style="width:$width%"></div>
HTML;

    for ($i = 1; $i <= 5; $i++) {
      $title = "$i/5 Stars";
      $link = 'javascript:void(0)';
      $html .= <<<HTML
      <div class="starr"><a href="$link" title="$title">&nbsp;</a>
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
  public static function display_poll($metric = null) {
    if (!$metric) {
      $html = <<<HTML
<div class="poll-div">
  <form method="post" action="wat.php?plz=lol" name="poll-form">
    <ul class="poll-list">
      <li class="poll-question"></li>
      <li class="poll-answer"><label><input type="radio" name="poll-preview" /></label></li>
      <li class="poll-answer"><label><input type="radio" name="poll-preview" /></label></li>
    </ul>
    <input type="button" value="Cast Vote" />
    <input type="button" value="Show Results" />
  </form>
</div>
HTML;
    } else {
      $params = unserialize($metric->params);
      $question = $params['poll']['question'];
      $answers = $params['poll']['answer'];

      $html = <<<HTML
<div class="poll-div">
  <form method="post" action="wat.php?plz=lol" name="poll-form">
    <ul class="poll-list">
      <li class="poll-question">$question</li>
HTML;

      foreach ($answers as $answer) {
        $html .= '<li class="poll-answer"><label><input type="radio" name="poll-preview" />' . $answer . '</label></li>';
      }

      $html .= <<<HTML
    </ul>
    <input type="button" value="Cast Vote" />
    <input type="button" value="Show Results" />
  </form>
</div>
HTML;
    }

    return $html;
  }

  /*
   * content to be displayed after every post
   */
  public static function content_display($content) {
    global $post; //get current post object
    $post_metrics = unserialize(get_post_meta($post->ID, 'metrics', true));
    if (isset($post_metrics) && $post_metrics) {
      foreach ($post_metrics as $key => $val) {
        Evaluate::display_metric($key);
      }
    }
  }

}
?>
