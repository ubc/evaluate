<?php
$evaluation_setting_varification_count = 0;

class Evaluate_Admin {

  static $options = array();

  public static function init() {

    register_setting('evaluate_settings_group', 'evaluate_settings', array('Evaluate_Admin', 'sanitize_evaluate_settings'));

    // register scripts and styles
    wp_register_script('evaluate-admin-add', EVALUATE_DIR_URL . '/js/admin-add.js', array('jquery'), '1.0', true);
    wp_register_style('evaluate-admin', EVALUATE_DIR_URL . '/css/admin.css');

    global $wpdb;
  }

  public static function admin_menu() {
    $page = add_options_page('Evaluate Options', 'Evaluate', 'manage_options', 'evaluate', array('Evaluate_Admin', 'page'));

    add_action('admin_print_styles-' . $page, array('Evaluate_Admin', 'admin_styles'));

    add_action('admin_footer-' . $page, array('Evaluate_Admin', 'print_script_admin_add'));
  }

  public static function admin_styles() {
    wp_enqueue_style('evaluate-admin');
  }

  public static function print_script_admin_add() {

    wp_print_scripts('evaluate-admin-add');
  }

  public static function page() {

    self::$options = get_option('evaluate_metrics');

    $title = ( $_GET['do'] != 'view' ? '<a class="add-new-h2" href="?page=evaluate#add">Add New</a>' : '<a class="add-new-h2" href="?page=evaluate">Back to metrics</a>');
    ?>
    <div class="wrap">
      <div id="icon-options-general" class="icon32"></div>
      <h2>Evaluation <?php echo $title; ?></h2>
      <?php
      settings_errors();

      switch ($_GET['do']) {
        /*
          case 'add':
          if( !isset($_GET['settings-updated']) )
          Evaluate_Admin::add_page();
          break;
         */
        case 'delete':

          if (isset($_GET['metric'])):
            if (isset(self::$options[$_GET['metric']])):
              Evaluate_Admin::delete_evaluation($_GET['metric']);
            else:
              Evaluate_Admin::display_error('Sorry but this metric doesn\'t exits');
            endif;
          else:
            Evaluate_Admin::display_error('Sorry but this metric you didn\'t include a metric');
          endif;

          Evaluate_Admin::display_page();
          Evaluate_Admin::edit_metric();

          break;

        case 'edit':

          if (isset($_GET['metric'])):
            if (isset(self::$options[$_GET['metric']])):
              Evaluate_Admin::edit_metric(self::$options[$_GET['metric']]);
            else:
              Evaluate_Admin::display_error('Sorry but this metric doesn\'t exits');
            endif;
          else:
            Evaluate_Admin::display_error('Sorry but this metric you didn\'t include a metric');
          endif;


          break;

        case 'view':
          if (isset($_GET['metric'])):
            if (isset(self::$options[$_GET['metric']])):
              Evaluate_Admin::display_data(self::$options[$_GET['metric']]);
            else:
              Evaluate_Admin::display_error('Sorry but this metric doesn\'t exits');
            endif;
          else:
            Evaluate_Admin::display_error('Sorry but this metric you didn\'t include a metric');
          endif;

          break;

        default:
          Evaluate_Admin::display_page();
          Evaluate_Admin::edit_metric();
          break;
      }
      ?>
    </div><!-- /.wrap -->
    <?php
  }

  public static function delete_evaluation($id) {

    // are you allowed to delete it? 
    if (current_user_can('manage_options') && wp_verify_nonce($_GET['_wpnonce'], 'delete-' . $id)):
      // what do you want to delete?
      unset(self::$options[$id]);

      // delete all the post meta
      $all_posts = get_posts('posts_per_page=-1&post_type=any&post_status=any');

      foreach ($all_posts as $postinfo) {
        delete_post_meta($postinfo->ID, 'count_' . $id);
        delete_post_meta($postinfo->ID, 'sum_' . $id);
      }
      // delete all the rows from the current table for that particular metric id
      Evaluate::delete_metric($id);

      if (empty(self::$options)):
        delete_option('evaluate_metrics');
      else:
        update_option('evaluate_metrics', self::$options);
      endif;
    endif;
  }

  public static function display_page() {

    if (!empty(self::$options)):
      ?>
      <table class="widefat">
        <thead>
          <tr>
            <th class="row-title"><?php _e('Name'); ?></th>
            <th><?php _e('Type'); ?></th>
            <th><?php _e('Post Type Included'); ?></th>
            <th><?php _e('Preview'); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php
          $i = 0;
          foreach (self::$options as $id => $option):
            $alternate = 'class="alternate"';
            $i++;
            ?>
            <tr <?php echo ( $i % 2 ? $alternate : '' ); ?> >
              <td class="post-title page-title column-title">
                <label for="tablecell"><strong><a href="?page=evaluate&do=edit&metric=<?php echo $id; ?>"><?php echo $option['name']; ?></a></strong></label>
                <div class="row-actions">
                  <span><a href="?page=evaluate&do=view&metric=<?php echo $id; ?>">View Data</a> | </span>
                  <span class="edit"><a href="?page=evaluate&do=edit&metric=<?php echo $id; ?>">Edit</a> | </span>
                  <span class="trash"><a href="?page=evaluate&do=delete&metric=<?php echo $id; ?>&_wpnonce=<?php echo wp_create_nonce('delete-' . $id); ?>">Delete</a> | </span>
                </div>
              </td>

              <td><?php echo $option['type']; ?></td>
              <td><?php
        if (is_array($option['post_type'])):
          echo implode(', ', $option['post_type']);
        endif;
            ?>
              </td>
              <td><?php
        echo Evaluate::display_metic($option);
            ?>
              </td>
            </tr>	
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th class="row-title"><?php _e('Name'); ?></th>
            <th><?php _e('Type'); ?></th>
            <th><?php _e('Post Type Included'); ?></th>
            <th><?php _e('Preview'); ?></th>
          </tr>
        </tfoot>
      </table>
      <?php
    else:

      Evaluate_Admin::display_error("You don't have any way for people to evaluate your content yet. Create a new metric.");

    endif;
  }

  public static function display_data($metric) {
    $id = $_GET['metric'];

    switch ($_GET['group']) {
      case 'user':
        $tab_content = '';
        $tab_user = 'nav-tab-active';
        $section = 'user';
        break;

      default:
        $tab_content = 'nav-tab-active';
        $tab_user = '';
        $section = 'metrics';
        break;
    }
    ?>
    <h3 class="nav-tab-wrapper"> 
      <a class="nav-tab <?php echo $tab_content; ?>" href="?page=evaluate&do=view&metric=<?php echo $id; ?>">Content</a>
      <a class="nav-tab <?php echo $tab_user; ?>" href="?page=evaluate&do=view&metric=<?php echo $id; ?>&group=user">Users</a>
    </h3>
    <?php
    Evaluate_Admin::display_metrics_table($section);
  }

  public static function display_metrics_table($section) {
    global $wpdb;
    if ($section == 'metrics') {
      $wp_table = new Evaluate_Display_List_Table();
      /*$wp_table->prepare_items();
      $wp_table->display();*/
      $wp_table->render();
    } else if ($section == 'user') {
      echo 'lolz';
    }
  }

  public static function display_error($error) {
    ?>
    <div class="updated settings-error" > 
      <p><strong><?php echo $error; ?></strong></p>
    </div>
    <?php
  }

  public static function edit_metric($metric = array()) {
    $title = "Edit";
    if (empty($metric)):

      $title = "Add New";
      if (isset($_GET['settings-updated']))
        $metric = get_option('evaluate_settings');

      if (!is_array($metric['post_type']))
        $metric['post_type'] = array();


    endif;
    ?>		
    <h3><?php echo $title; ?> Evaluation Criteria</h3>
    <form method="post" action="options.php" id="add">

      <input type="hidden" value="evaluate_settings_group" name="option_page">
      <input type="hidden" value="update" name="action">
      <input id="_wpnonce" type="hidden" value="<?php echo wp_create_nonce('evaluate_settings_group-options'); ?>" name="_wpnonce">
      <input type="hidden" value="<?php echo admin_url('options-general.php?page=evaluate'); ?>" name="_wp_http_referer">

      <table class="form-table">
        <tr valign="top"><th scope="row"><label for="name">Name</label></th>
          <td><input name="evaluate_settings[name]" type="text" value="<?php echo esc_attr($metric['name']); ?>" class="regular-text" />
            <?php if (isset($_GET['metric'])): ?>
              <input type="hidden" name="evaluate_settings[id]" value="<?php echo $_GET['metric']; ?>" />
            <?php endif; ?>
            <label><input type="checkbox" name="evaluate_settings[display_name]" value="1" <?php checked($metric['display_name']); ?> /> display name</label> </td>
        </tr>
        <tr valign="top">
          <th scope="row">Type</th>
          <td>
            <div>
              <label><input type="radio" name="evaluate_settings[type]" value="one-way" <?php checked($metric['type'], 'one-way'); ?> class="evaluate-type-selection" /> One way voting</label>
              <div class="hide evaluate-type-shell">
                <div><label><input type="radio" name="evaluate_settings[one-way]" value="thumb" <?php checked($metric['one-way'], 'thumb'); ?> /> Like </label> <?php echo Evaluate::one_way(array('one-way' => 'thumb')); ?></div>
                <div><label><input type="radio" name="evaluate_settings[one-way]" value="arrow" <?php checked($metric['one-way'], 'arrow'); ?> /> Vote Up </label> <?php echo Evaluate::one_way(array('one-way' => 'arrow')); ?></div>
                <div><label><input type="radio" name="evaluate_settings[one-way]" value="heart" <?php checked($metric['one-way'], 'heart'); ?> /> Heart </label> <?php echo Evaluate::one_way(array('one-way' => 'heart')); ?></div>
              </div>
            </div>
            <div>
              <label><input type="radio" name="evaluate_settings[type]" value="two-way" <?php checked($metric['type'], 'two-way'); ?> class="evaluate-type-selection" /> Two way voting, Up and Down</label>
              <div class="hide evaluate-type-shell">
                <div><label><input type="radio" name="evaluate_settings[two-way]" value="thumb" <?php checked($metric['two-way'], 'thumb'); ?>  /> Thumbs</label> <?php echo Evaluate::two_way(array('two-way' => 'thumb')); ?></div>
                <div><label><input type="radio" name="evaluate_settings[two-way]" value="arrow" <?php checked($metric['two-way'], 'arrow'); ?>  /> Arrows</label> <?php echo Evaluate::two_way(array('two-way' => 'arrow')); ?></div>
              </div>
            </div>
            <div>
              <label><input type="radio" name="evaluate_settings[type]" value="range" <?php checked($metric['type'], 'range'); ?> class="evaluate-type-selection" /> Range, Star Voting</label>
              <div class="hide evaluate-type-shell">
                <?php echo Evaluate::range(); ?>
              </div>
            </div>
            <div>
              <label><input type="radio" name="evaluate_settings[type]" value="poll" <?php checked($metric['type'], 'poll'); ?> class="evaluate-type-selection" /> Poll</label>
              <div class="hide evaluate-type-shell">
                <label>Question</label><br />
                <input type="text" name="evaluate_settings[poll][question]" value="<?php echo esc_attr($metric['poll']['question']); ?>"  class="regular-text" />
                <ul>
                  <?php
                  $j = 0;
                  while ($j < 5):
                    ?>
                    <li>
                      <label>name</label><br />
                      <input type="text" name="evaluate_settings[poll][name][<?php echo $j; ?>]" value="<?php echo esc_attr($metric['poll']['name'][$j]); ?>"   class="all-options" />
                      <?php /*
                        <select name="evaluate_settings[poll][value][<?php echo $j; ?>]">
                        <?php
                        $i = 0; while( $i < 21) { ?>
                        <option value="<?php echo $i; ?>" <?php selected($metric['poll']['value'][$j], $i); ?>> &nbsp;  <?php echo $i; ?>  &nbsp; </option>
                        <?php $i++; } ?>
                        </select>
                       */ ?>
                    </li>
                    <?php
                    $j++;
                  endwhile;
                  ?>
                </ul>
              </div>
            </div>
          </td>
        </tr>
        <tr valign="top" id="evaluation_post_type">
          <th scope="row">Add to post type</th>
          <td>
            <?php
            $types = get_post_types(array('public' => true), 'objects');
            foreach ($types as $type):
              ?>
              <div><label><input type="checkbox" name="evaluate_settings[post_type][]" value="<?php echo $type->name; ?>" <?php checked(in_array($type->name, $metric['post_type'])); ?> /> <?php echo $type->label; ?></label></div>
    <?php endforeach; ?>
          </td>
        </tr>

        <tr valign="top">
          <th scope="row">Display Options</th>
          <td>
            <div><label><input type="checkbox" name="evaluate_settings[loggedin]" value="1" <?php checked($metric['loggedin']); ?> /> Users have to be logged in to vote.</label></div>
            <div><label><input type="checkbox" name="evaluate_settings[admin_only]" value="1" <?php checked($metric['admin_only']); ?> /> Only Admins can see this criteria.</label></div>
          </td>
        </tr>
        <tr valign="top" id="preview">
          <th scope="row">Preview</th>
          <td>
            <!-- // todo: make preview work -->
          </td>
        </tr>
      </table>
    <?php submit_button(); ?>
    </form>

    <?php
  }

  public static function sanitize_evaluate_settings($settings) {
    global $evaluation_setting_varification_count;
    $evaluation_setting_varification_count++;

    if ($evaluation_setting_varification_count > 1):
      return $settings;
    endif;

    $error = false;


    $settings['name'] = trim($settings['name']);

    if (empty($settings['name'])):
      $setting = 'evaluate_settings';
      $code = 'evaluation_name';
      $message = "You didn't type in a name for your evaluation metric.";
      $type = "error";
      add_settings_error($setting, $code, $message, $type);
      $error = true;
    endif;

    if (empty($settings['type'])):

      $setting = 'evaluate_settings';
      $code = 'evaluation_type';
      $message = "You didn't select any type, we don't know what kind of metric you want to display.";
      $type = "error";
      add_settings_error($setting, $code, $message, $type);
      $error = true;
    endif;

    if (!is_array($settings['post_type'])):

      $setting = 'evaluate_settings';
      $code = 'evaluation_post_type';
      $message = "You didn't select any post type, your evaluation metric will not appear any where.";
      $type = "error";
      add_settings_error($setting, $code, $message, $type);
      $error = true;
    endif;

    $options = get_option('evaluate_metrics');

    if (isset($settings['id'])):
      $id = $settings['id'];
      $setting = 'evaluate_settings';
      $code = 'saved';
      $message = "The metric was updated,  <a href='?page=evaluate'>return back to the list</a>.";
      $type = "updated";
      add_settings_error($setting, $code, $message, $type);

    else:
      $id = Evaluate_Admin::get_id($settings['name'], $options);

    endif;
    $options[$id] = $settings;

    if (!$error)
      update_option('evaluate_metrics', $options);

    // todo: make sure that the user entered appropriate stuff in here

    return $settings;
  }

  public static function get_id($title, $options) {
    $id = sanitize_title_with_dashes(strtolower($title));
    if (empty($id))
      return false;

    if (is_array($options)):

      $counter = 1;
      while (Evaluate_Admin::id_exists($id, $options)) {
        $id = $id . "-" . $counter;
        $counter += 1;
      }
    endif;
    return $id;
  }

  public static function id_exists($new_id, $options) {

    if (is_array($options)):
      foreach ($options as $id => $option):
        if ($id == $new_id)
          return true;
      endforeach;
    endif;

    return false;
  }

}