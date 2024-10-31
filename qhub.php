<?php
/**
 * Plugin Name: Qhub Wordpress Widget
 * Plugin URI: http://qhub.com
 * Description: A widget that makes the qhub api accessible to wordpress.
 * Version: 1.04.96
 * Author: Simon Dann
 * Author URI: http://photogabble.co.uk
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

$QdescURL = '<a href="'.WP_PLUGIN_URL.'/qhub-wordpress-widget/HelpDoc.txt" target="_blank">See readme</a>';

$new_meta_boxes = array(
	"qhub_tags" => array(
	"name" => "qhub_tags",
	"std" => "",
	"title" => "Default Tags",
        "type" => "text",
	"description" => "Fine-tune how you want your related Qhub questions to display. <strong style=\"text-decoration:underline;\">Leave Empty to use post tags.</strong>"),
	"qhub_output" => array(
	"name" => "qhub_output",
	"std" => htmlspecialchars(get_option('qhub_default_question_output')),
	"title" => "Default Output",
        "type" => "text",
	"description" => $QdescURL. " for description on formatting tags for the output."),
	"qhub_numbertoshow" => array(
	"name" => "qhub_numbertoshow",
	"std" => '5',
	"title" => "Number of Questions to Show",
        "type" => "text",
	"description" => "Number of related questions you wish the plugin to show."),
        "qhub_displayanswered" => array(
	"name" => "qhub_displayanswered",
	"std" => 'All',
	"title" => "Show",
        "type" => "select",
	"description" => "Show all questions or limit to just those that are answered/unanswered.")
);

function Qhubnicetime($date)
{
    // Function found on http://php.net/manual/en/function.time.php

    if(empty($date)) {
        return "No date provided";
    }

    $periods         = array("second", "minute", "hour", "day", "week", "month", "year", "decade");
    $lengths         = array("60","60","24","7","4.35","12","10");

    $now             = time();
    $unix_date       = strtotime($date);

    // check validity of date
    if(empty($unix_date)) {
        return "Bad date";
    }

    // is it future date or past date
    if($now > $unix_date) {
        $difference     = $now - $unix_date;
        $tense         = "ago";

    } else {
        $difference     = $unix_date - $now;
        $tense         = "from now";
    }

    for($j = 0; $difference >= $lengths[$j] && $j < count($lengths)-1; $j++) {
        $difference /= $lengths[$j];
    }

    $difference = round($difference);

    if($difference != 1) {
        $periods[$j].= "s";
    }

    return "$difference $periods[$j] {$tense}";
}

/**
 * Add function to widgets_init that'll load our widget.
 * @since 0.1
 */
add_action( 'widgets_init', 'Qhub_load_widgets' );

/**
 * Register our widget.
 * 'Example_Widget' is the widget class used below.
 *
 * @since 0.1
 */
function Qhub_load_widgets() {
	register_widget( 'Qhub_Widget' );
}

/**
 * Example Widget class.
 * This class handles everything that needs to be handled with the widget:
 * the settings, form, display, and update.  Nice!
 *
 * @since 0.1
 */
class Qhub_Widget extends WP_Widget {

	/**
	 * Widget setup.
	 */
	function Qhub_Widget() {
		/* Widget settings. */
		$widget_ops = array( 'classname' => 'qhub', 'description' => __('Qhub Widget to display questions in your sidebar.', 'qhub') );

		/* Widget control settings. */
		$control_ops = array( 'width' => 350, 'height' => 350, 'id_base' => 'qhub-widget' );

		/* Create the widget. */
		$this->WP_Widget( 'qhub-widget', __('Qhub Widget', 'example'), $widget_ops, $control_ops );
	}

	/**
	 * How to display the widget on the screen.
	 */
	function widget( $args, $instance ) {
		extract( $args );
		global $post;
		global $wpdb;

		/* Get the current pages tags, if any are set we will use them for filtering questions */
		$this_post_tags = get_the_tags();
                /*
                 * This is the current class instance id for the widget
                 * it may be useful in the future echo $args['widget_id'];
                 */


		$show = false;
		if ($instance['filter_pagetype'] == 'All Pages'):
			$show = true;
		else:
			if ($instance['filter_pagetype'] == 'Frontpage Only' && is_front_page()):
				$show = true;
			endif;
			if ($instance['filter_pagetype'] == 'Blog Post Page Only' && is_single()):
				$show = true;
			endif;
			if ($instance['filter_pagetype'] == 'Single Page Only' && is_page()):
				$show = true;
			endif;
		endif;

		if (!$show)
			return;

		/* Our variables from the widget settings. */
		$title 				= apply_filters('widget_title', $instance['title'] );
		$filter_tags 			= explode(',',$instance['qhubtags']);
		$filter_cats			= explode(',',$instance['catfilter']);
		$questions_to_show		= $instance['questions_to_show'];
		$question_showrelevant          = isset( $instance['question_showrelevant'] ) ? $instance['question_showrelevant'] : false;
		$question_link_type		= isset( $instance['question_link_newwindow'] ) ? $instance['question_link_newwindow'] : false;
		$question_order			= $instance['questions_order'];
                $questions_answeredshow         = $instance['questions_answeredshow'];

		$question_format		= htmlspecialchars_decode($instance['question_output']);
		$before_question		= htmlspecialchars_decode($instance['before_question']);
		$after_question			= htmlspecialchars_decode($instance['after_question']);

		$table_name 			= $wpdb->prefix . "qhub_questions";
		$show 				= false;
		$firstTag			= true;

		if ( empty($filter_cats[0]) ):
			$show = true;
		else:
			foreach((get_the_category()) as $category) {
				if (in_array($category->cat_ID, $filter_cats)):
					$show = true;
				endif;

			}
		endif;

		if (!$show)
			return;

		/* Display the widget title if one was input (before and after defined by themes). */
		if ( $title )
			echo $before_title . $title . $after_title;

                /*
                 * The following checks the current instances filter settings
                 * and then forwards them to the caching function which will
                 * either get a local copy or make a fresh api call.
                 */
                if($question_showrelevant && !empty($this_post_tags)):
			foreach($this_post_tags as $tag) {
				$filter_tags[] = $tag->name;
			}
		endif;

		foreach($filter_tags as $query_tag):
                        if (!empty($query_tag)):
                                $filterTags.=$query_tag.',';
			else:
			// Do nothing.
			endif;
		endforeach;



                switch ($questions_answeredshow):
                    case 'All':
                        break;
                    case 'Answered':
                        $filterType = 'answered';
                        break;
                    case 'UnAnswered':
                        $filterType = 'unanswered';
                        break;
                endswitch;

                switch ($question_order):

			case 'Newest to Oldest';
				$filterShow = 'newest';
			break;

			case 'Oldest to Newest';
				$filterShow = 'oldest';
			break;

			case 'Random';
				$filterShow = '';
                                /*
                                 * Can we do this now?
                                 */
			break;
                        case 'Popular Questions Only';
				$filterShow = 'popular';
			break;

		endswitch;

                $filterLimit = $questions_to_show;
                $qhub_results = qhub_cache($filterTags, $filterShow, $filterType, $filterLimit);
                $emptyTest = (array)$qhub_results->item;
				
		if (!empty($emptyTest)):
			/* Before widget (defined by themes). */
			if ($before_question == ''):
				echo $before_widget;
			else:
				echo $before_question;
			endif;
			
			$done = array();
			foreach ($qhub_results->item as $qhub_result):
				$question_id = (int)$qhub_result->question_id;
				if(!$qhub_result->question or !$qhub_result->question_id or $done[$question_id]) continue;
				$qhub_output_tags = array(
				  "question" => $qhub_result->question,
				  "question_url" => $qhub_result->question_url,
				  "tags_url" => "Working on this",
				  "author" => $qhub_result->user_name,
				  "author_url" => $qhub_result->user_url,
				  "answers" => $qhub_result->num_answers,
				  "created" => $qhub_result->date_created,
				  "timesince" => Qhubnicetime($qhub_result->date_created)
				);

				$t2 = $question_format;
				foreach ($qhub_output_tags as $tag => $data):
					$t2 = str_replace("{".$tag."}", $data, $t2);
				endforeach;
				$qhub_output .= $t2;

				$done[$question_id] = 1;

			endforeach;
			echo $qhub_output;
			echo $after_question;
		else:
			echo "There were no Questions found.";
		endif;

                /* After widget (defined by themes). */
                if ($after_question == ''):
                    echo $after_widget;
                else:
                    echo $after_question;
                endif;
	}

	/**
	 * Update the widget settings.
	 */
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		/* Strip tags for text inputs to remove HTML (important for text inputs). */
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['qhubtags'] = strip_tags( $new_instance['qhubtags'] );
		$instance['catfilter'] = strip_tags( $new_instance['catfilter'] );


		/* Strip most html tags from these other than safe for inclusion within sidebar */
		$safe_tags = '<br><br/><li><p><ul><ol><span><a>';
		$instance['after_question'] = htmlspecialchars(strip_tags( $new_instance['after_question'], $safe_tags));
		$instance['before_question'] = htmlspecialchars(strip_tags( $new_instance['before_question'], $safe_tags));
		$instance['question_output'] = htmlspecialchars(strip_tags( $new_instance['question_output'], $safe_tags));


		/* No need to strip tags for select and checkboxes. */
		$instance['questions_to_show'] = $new_instance['questions_to_show'];
		$instance['question_link_newwindow'] = $new_instance['question_link_newwindow'];
		$instance['questions_order'] = $new_instance['questions_order'];
		$instance['question_showrelevant'] = $new_instance['question_showrelevant'];
		$instance['filter_pagetype'] = $new_instance['filter_pagetype'];
                $instance['questions_answeredshow'] = $new_instance['questions_answeredshow'];



		return $instance;
	}

	/**
	 * Displays the widget settings controls on the widget panel.
	 */
	function form( $instance ) {

		/* Set up some default widget settings. */
		$defaults = array( 'title' => __('My Qhub Questions', 'qhub'), 'questionlabel' => __('Ask a question:', 'qhub'), 'qhubtags' => '', 'questions_to_show' => '10', 'questions_order' => 'Newest to Oldest', 'show_question_box' => false, 'question_link_newwindow' => false, 'questions_answeredshow' => 'All', 'question_showrelevant' => false, 'filter_pagetype' => 'All Pages', 'before_question' => htmlspecialchars(get_option('qhub_default_before_question')), 'after_question' => htmlspecialchars(get_option('qhub_default_after_question')), 'question_output' => htmlspecialchars(get_option('qhub_default_question_output')) );
		$instance = wp_parse_args( (array) $instance, $defaults ); ?>
		<!-- Widget Title: Text Input -->
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e('Title:', 'hybrid'); ?></label>
			<input id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo $instance['title']; ?>" class="widefat" />
		</p>

		<!-- Widget Display By Category: Text Input -->
		<p>
			<label for="<?php echo $this->get_field_id( 'catfilter' ); ?>"><?php _e('Show for Categories(Separate by comma):', 'hybrid'); ?></label>
			<input id="<?php echo $this->get_field_id( 'catfilter' ); ?>" name="<?php echo $this->get_field_name( 'catfilter' ); ?>" value="<?php echo $instance['catfilter']; ?>" class="widefat" />
		</p>

		<!-- Default Filter Tags: Text Input -->
		<p>
			<label for="<?php echo $this->get_field_id( 'qhubtags' ); ?>"><?php _e('Default Filter Tags (Separate by comma):', 'qhub'); ?></label>
			<input id="<?php echo $this->get_field_id( 'qhubtags' ); ?>" name="<?php echo $this->get_field_name( 'qhubtags' ); ?>" value="<?php echo $instance['qhubtags']; ?>" class="widefat" />
		</p>

		<!-- Display by page type: Select Box -->
		<p>
			<label for="<?php echo $this->get_field_id( 'filter_pagetype' ); ?>"><?php _e('Display on:', 'qhub'); ?></label>
			<select id="<?php echo $this->get_field_id( 'filter_pagetype' ); ?>" name="<?php echo $this->get_field_name( 'filter_pagetype' ); ?>" class="widefat">
				<option <?php if ( 'All Pages' == $instance['filter_pagetype'] ) echo 'selected="selected"'; ?>>All Pages</option>
				<option <?php if ( 'Frontpage Only' == $instance['filter_pagetype'] ) echo 'selected="selected"'; ?>>Frontpage Only</option>
				<option <?php if ( 'Blog Post Page Only' == $instance['filter_pagetype'] ) echo 'selected="selected"'; ?>>Blog Post Page Only</option>
				<option <?php if ( 'Single Page Only' == $instance['filter_pagetype'] ) echo 'selected="selected"'; ?>>Single Page Only</option>
			</select>
		</p>

		<!-- Number of Questions to show: Select Box -->
		<p>
			<label for="<?php echo $this->get_field_id( 'questions_to_show' ); ?>"><?php _e('Questions to Show:', 'qhub'); ?></label>
			<select id="<?php echo $this->get_field_id( 'questions_to_show' ); ?>" name="<?php echo $this->get_field_name( 'questions_to_show' ); ?>" class="widefat">
				<option <?php if ( '1' == $instance['questions_to_show'] ) echo 'selected="selected"'; ?>>1</option>
				<option <?php if ( '5' == $instance['questions_to_show'] ) echo 'selected="selected"'; ?>>5</option>
				<option <?php if ( '10' == $instance['questions_to_show'] ) echo 'selected="selected"'; ?>>10</option>
				<option <?php if ( '15' == $instance['questions_to_show'] ) echo 'selected="selected"'; ?>>15</option>
				<option <?php if ( '20' == $instance['questions_to_show'] ) echo 'selected="selected"'; ?>>20</option>
				<option <?php if ( '25' == $instance['questions_to_show'] ) echo 'selected="selected"'; ?>>25</option>
			</select>
		</p>

                <!-- Display by date ASC or DESC - converted to non-tech speak for normal people: Select Box -->
		<p>
			<label for="<?php echo $this->get_field_id( 'questions_order' ); ?>"><?php _e('Sort Questions by:', 'qhub'); ?></label>
			<select id="<?php echo $this->get_field_id( 'questions_order' ); ?>" name="<?php echo $this->get_field_name( 'questions_order' ); ?>" class="widefat">
				<option <?php if ( 'Newest to Oldest' == $instance['questions_order'] ) echo 'selected="selected"'; ?>>Newest to Oldest</option>
				<option <?php if ( 'Oldest to Newest' == $instance['questions_order'] ) echo 'selected="selected"'; ?>>Oldest to Newest</option>

                                <option <?php if ( 'Popular Questions Only' == $instance['questions_order'] ) echo 'selected="selected"'; ?>>Popular Questions Only</option>
			</select>
		</p>

		<!-- Display answered, unanswered or all - converted to non-tech speak for normal people: Select Box -->
		<p>
			<label for="<?php echo $this->get_field_id( 'questions_answeredshow' ); ?>"><?php _e('Show:', 'qhub'); ?></label>
			<select id="<?php echo $this->get_field_id( 'questions_answeredshow' ); ?>" name="<?php echo $this->get_field_name( 'questions_answeredshow' ); ?>" class="widefat">
				<option <?php if ( 'All' == $instance['questions_answeredshow'] ) echo 'selected="selected"'; ?>>All</option>
				<option <?php if ( 'Answered' == $instance['questions_answeredshow'] ) echo 'selected="selected"'; ?>>Answered</option>
				<option <?php if ( 'UnAnswered' == $instance['questions_answeredshow'] ) echo 'selected="selected"'; ?>>UnAnswered</option>
			</select>
		</p>

		<!-- Show related questions to post (this is done by post tag): Checkbox -->
		<p>
			<input class="checkbox" type="checkbox" <?php if ($instance['question_showrelevant']){ echo "checked";} ?> id="<?php echo $this->get_field_id( 'question_showrelevant' ); ?>" name="<?php echo $this->get_field_name( 'question_showrelevant' ); ?>" />
			<label for="<?php echo $this->get_field_id( 'question_showrelevant' ); ?>"><?php _e('Show related questions', 'qhub'); ?></label>
		</p>
		<h3>Output Formatting:</h3>

		<!-- Before Question HTML: Text Input -->
		<p>
			<label for="<?php echo $this->get_field_id( 'before_question' ); ?>"><?php _e('HTML Before Question List:', 'qhub'); ?></label>
			<input id="<?php echo $this->get_field_id( 'before_question' ); ?>" name="<?php echo $this->get_field_name( 'before_question' ); ?>" value="<?php echo $instance['before_question']; ?>" class="widefat" />
		</p>

		<!-- After Question HTML: Text Input -->
		<p>
			<label for="<?php echo $this->get_field_id( 'after_question' ); ?>"><?php _e('HTML After Question List:', 'qhub'); ?></label>
			<input id="<?php echo $this->get_field_id( 'after_question' ); ?>" name="<?php echo $this->get_field_name( 'after_question' ); ?>" value="<?php echo $instance['after_question']; ?>" class="widefat" />
		</p>

		<!-- Output Template HTML: Textarea -->
		<p>
			<label for="<?php echo $this->get_field_id( 'question_output' ); ?>"><?php _e('Question List Item Template:', 'qhub'); ?></label>
			<textarea name="<?php echo $this->get_field_name( 'question_output' ); ?>" id="<?php echo $this->get_field_id( 'question_output' ); ?>" cols="8" rows="6" class="widefat"><?php echo htmlspecialchars_decode($instance['question_output']); ?></textarea>
		</p>
	<?php
	}
}
function calling_file () {
	$bta = debug_backtrace();
	$ret='';
	foreach ($bta as $bti) {
		if (strpos($bti['file'],'class.base.php') and $bti['function'] == 'calling_file' or $bti['function'] == 'error' or $bti['function'] == 'warning') continue;
		$ret.=basename($bti['file']).'#'.$bti['line'].';';
	}
	return $ret;
}		

function in_excerpt () {
	$bta = debug_backtrace();
	foreach ($bta as $bti) {
		if($bti['function'] == 'the_excerpt')
			return true;
	}
	return false;
}		

function qhub_cache($filterTags = NULL, $filterShow = NULL, $filterType = NULL, $filterLimit = NULL){
	$ret = new stdClass(); /*empty class	*/
	if (get_option('qhub_user_url')) {
		require_once ('class.qhub.php');		
		global $wpdb;
		$url = '/questions.xml?';
		$table_name = $wpdb->prefix . "qhub_instances";
		if (!empty($filterTags)):
			$tagsArray = explode(',',$filterTags);
			$firstTag = true;
			foreach($tagsArray as $query_tag):
				if ($query_tag != ''):
					if ($firstTag == false):
						$url.='&tags[]='. urlencode($query_tag);
					else:
						$url.='tags[]='. urlencode($query_tag);								$firstTag = false;							endif;
				endif;
			endforeach;
		endif;
		if (!empty($filterShow)):
			$url.='&order='.$filterShow;
		endif;
		if (!empty($filterType)):
			$url.='&type='.$filterType;
		endif;
		if ( !empty($filterLimit) ):
			$url.='&limit='.$filterLimit;
		else:
			$url.='&limit=5';
		endif;
		$url.="&ip=".$_SERVER['REMOTE_ADDR']."&referrer=".urlencode($_SERVER['REQUEST_URI']);
		$qhub = new qhub(get_option('qhub_user_url'),get_option('qhub_user_id'),get_option('qhub_user_password'),get_option('qhub_user_apikey'));							
		if ($result = $qhub->request($url, $postfields)) (object)$ret = simplexml_load_string($result);
	}
	return $ret;
}

function initQhubPlugin()
{ ?>
    	<div class="wrap">
        	<h2>Qhub Plugin Configuration.</h2>
            <p>Welcome to the Qhub wordpress plugin configuration, please enter your Qhub credentials below before setting up widgets.</p>
            <form method="post" action="options.php">
				<?php settings_fields( 'qhub-settings-group' ); ?>
				<p><h4>API Settings:</h4></p>
				<table class="form-table">
					<tr valign="top">
						<th scope="row">Qhub URL (http://):</th>
						<td><input style="width:400px;" type="text" name="qhub_user_url" value="<?php echo get_option('qhub_user_url'); ?>" class="regular-text"/></td>
					</tr>

					<tr valign="top">
						<th scope="row">API Key:</th>
						<td><input style="width:400px;" type="text" name="qhub_user_apikey" value="<?php echo get_option('qhub_user_apikey'); ?>" class="regular-text" /></td>
					</tr>
				</table>

				<p><h4>Default Global Settings:</h4></p>
				<table class="form-table">
					<tr valign="top">
						<th scope="row">HTML Before Question List:</th>
						<td><input style="width:400px;" type="text" name="qhub_default_before_question" value="<?php echo htmlspecialchars(get_option('qhub_default_before_question')); ?>" class="regular-text code" /></td>
					</tr>
					<tr valign="top">
						<th scope="row">HTML After Question List:</th>
						<td><input style="width:400px;" type="text" name="qhub_default_after_question" value="<?php echo htmlspecialchars(get_option('qhub_default_after_question')); ?>" class="regular-text code" /></td>
					</tr>
					<? /*
					<tr valign="top">
						<th scope="row">Default Text if no related questions found:</th>
						<td><input style="width:400px;" type="text" name="qhub_default_noquestion_txt" value="<?php echo get_option('qhub_default_noquestion_txt'); ?>" class="regular-text code" /></td>
						</tr>*/?>
					<tr valign="top">
						<th scope="row">Default Title Text:</th>
						<td><input style="width:400px;" type="text" name="qhub_default_title_txt" value="<?php echo htmlspecialchars(get_option('qhub_default_title_txt')); ?>" class="regular-text code" /></td>
					</tr>
                                        <?php
                                            if (get_option('qhub_default_showme')):
                                                    $sboption = ' checked="checked"';
                                            else:
                                                $sboption = '';
                                            endif;
                                        ?>
                                        <tr valign="top">
						<th scope="row">Include Within Post Text:</th>
						<td><input type="checkbox" id="qhub_default_showme" name="qhub_default_showme" value="1" <?php echo $sboption; ?> /></td>
					</tr>

					<tr valign="top">
						<th scope="row">Question List Item Template::</th>
						<td><textarea rows="3" style="width:400px;" class="code" id="qhub_default_question_output" name="qhub_default_question_output"><?php echo htmlspecialchars(get_option('qhub_default_question_output')); ?></textarea></td>
					</tr>
				</table>

				<p><h4>Formatting Tags:</h4></p>
				<p>{question}, {question_url}, {tags}, {author}, {author_url}, {answers}, {created}, {timesince}</p>

				<p class="submit">
				<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
				</p>
			</form>
       	</div>
    <?php
}

function addQhubPluginToSubmenu()
{
    add_submenu_page('options-general.php', 'Qhub Plugins Configuration Page', 'Qhub Plugin Config', 10, __FILE__, 'initQhubPlugin');
}

function register_qhubsettings() {
	// Register qhub api settings
	register_setting( 'qhub-settings-group', 'qhub_user_id' );
	register_setting( 'qhub-settings-group', 'qhub_user_password' );
	register_setting( 'qhub-settings-group', 'qhub_user_apikey' );
	register_setting( 'qhub-settings-group', 'qhub_user_url' );
	// Register Default Settings
	register_setting( 'qhub-settings-group', 'qhub_default_before_question' );
	register_setting( 'qhub-settings-group', 'qhub_default_after_question' );
	register_setting( 'qhub-settings-group', 'qhub_default_question_output' );
	/*register_setting( 'qhub-settings-group', 'qhub_default_noquestion_txt' );*/
	register_setting( 'qhub-settings-group', 'qhub_default_title_txt' );
        register_setting( 'qhub-settings-group', 'qhub_default_showme' );
        register_setting( 'qhub-settings-group', 'qhub_default_showby' );
        register_setting( 'qhub-settings-group', 'qhub_default_api_order' );

}
function qhub_plugin_install (){
	global $wpdb;
   	global $qhub_db_version;
	register_qhubsettings();

	/*update_option( 'qhub_default_noquestion_txt', '<li>No Related Questions</li>');*/
	update_option( 'qhub_default_before_question', '<ul>' );
	update_option( 'qhub_default_after_question', '</ul>' );
	update_option( 'qhub_default_question_output', '<li><a href="{question_url}">{question}</a> - {timesince}</li>' );
	update_option( 'qhub_default_title_txt', '<h5>Related Questions</h5>' );
	update_option( 'qhub_user_apikey', 'Q_64544008bade08fa1875831e13b34b4d' );
        update_option( 'qhub_default_showme', true );
        update_option( 'qhub_default_showby','All');
        update_option( 'qhub_default_api_order', 'popular');
}

function qhub_plugin_uninstall(){
        global $wpdb;
	// Un-Register qhub api settings
	unregister_setting( 'qhub-settings-group', 'qhub_user_id' );
	unregister_setting( 'qhub-settings-group', 'qhub_user_password' );
	unregister_setting( 'qhub-settings-group', 'qhub_user_apikey' );
	unregister_setting( 'qhub-settings-group', 'qhub_user_url' );
	// Un-Register Default Settings
	unregister_setting( 'qhub-settings-group', 'qhub_default_before_question' );
	unregister_setting( 'qhub-settings-group', 'qhub_default_after_question' );
	unregister_setting( 'qhub-settings-group', 'qhub_default_question_output' );
	/*unregister_setting( 'qhub-settings-group', 'qhub_default_noquestion_txt' );*/
	unregister_setting( 'qhub-settings-group', 'qhub_default_title_txt' );
        unregister_setting( 'qhub-settings-group', 'qhub_default_showme' );
        unregister_setting( 'qhub-settings-group', 'qhub_default_showby' );
        unregister_setting( 'qhub-settings-group', 'qhub_default_api_order' );

}

function qhub_related_display($content)
{
       /**
	* Displays the related questions in post content.
	*/
	global $post;
	global $wpdb;
	if ( (is_single() || is_page()) && get_option('qhub_default_showme') && !in_excerpt()){

		$table_name = $wpdb->prefix . "qhub_questions";
		$QnumbertoShow = get_post_meta($post->ID, "qhub_numbertoshow_value", $single = true);
		if (!$QoutputTemplate = htmlspecialchars_decode(get_post_meta($post->ID, "qhub_output_value",$single = true))) {
			$QoutputTemplate = htmlspecialchars_decode(get_option('qhub_default_question_output'));
		}
		$QfilterTags = explode(',', get_post_meta($post->ID, "qhub_tags_value",$single = true));
        $questions_answeredshow = get_post_meta($post->ID, "qhub_displayanswered_value", $single = true);

		$firstTag = True;
		$emptyCheck = implode('',$QfilterTags);

		if (empty($emptyCheck)):
			// There have been no defaults set therefore we shall use the posts own tags!
			$this_post_tags = get_the_tags();
			if (!empty($this_post_tags)):
				foreach($this_post_tags as $tag) {
					$QfilterTags[] = $tag->name;
				}
			endif;

		endif;

		foreach($QfilterTags as $query_tag):
			if (!empty($query_tag)):
                $filterTags.=$query_tag.',';
			else:
			// Do nothing.
			endif;
		endforeach;

                switch ($questions_answeredshow):
                    case 'All':
                        break;
                    case 'Answered':
                        $filterType = 'answered';
                        break;
                    case 'UnAnswered':
                        $filterType = 'unanswered';
                        break;
                    case 'Popular Questions Only';
                        $filterShow = 'popular';
                    break;
                endswitch;

                $filterLimit = $QnumbertoShow;

		if ($filterTags AND $qhub_results = qhub_cache($filterTags, $filterShow, $filterType, $filterLimit)) {
			$output  = get_option('qhub_default_title_txt');
			$output .= get_option('qhub_default_before_question');							
			$done = array();
			
			if ($qhub_results->item) {
				foreach ($qhub_results->item as $qhub_result) {
					$question_id = (int)$qhub_result->question_id;
					if(!$qhub_result->question or !$qhub_result->question_id or $done[$question_id]) continue;
					$qhub_output_tags = array(
				  	"question" => $qhub_result->question,
				  	"question_url" => $qhub_result->question_url,
				  	"tags_url" => "Working on this",
				  	"author" => $qhub_result->user_name,
				  	"author_url" => $qhub_result->user_url,
				  	"answers" => $qhub_result->num_answers,
				  	"created" => $qhub_result->date_created,
				  	"timesince" => Qhubnicetime($qhub_result->date_created)
					);
					
					$t2 = $QoutputTemplate;
					foreach ($qhub_output_tags as $tag => $data) {
						$t2 = str_replace("{".$tag."}", $data, $t2);
					}
					$output .= $t2;
					$done[$question_id] = 1;
				}
			}		else {
				$output .= get_option('qhub_default_noquestion_txt');
			}
			$output .= get_option('qhub_default_after_question');
			
			$content .= $output;
		}
	}
	return $content;
}

function new_meta_boxes() {
	global $post, $new_meta_boxes;

	foreach($new_meta_boxes as $meta_box) {
		$meta_box_value = get_post_meta($post->ID, $meta_box['name'].'_value', true);

		if($meta_box_value == "")
		$meta_box_value = $meta_box['std'];

                if ($meta_box['type'] == 'text'):

	?>
		<p>
			<input type="hidden" name="<?php echo $meta_box['name']; ?>_noncename" id="<?php echo $meta_box['name']; ?>_noncename" value="<?php echo wp_create_nonce( plugin_basename(__FILE__) ); ?>" />
			<label for="<?php echo $meta_box['name']; ?>_value"><?php echo $meta_box['title']; ?></label>
			<input type="text" name="<?php echo $meta_box['name']; ?>_value" value="<?php echo $meta_box_value; ?>" style="width:99%;" /><br />
			<?php echo $meta_box['description']; ?>
		</p>
	<?php
                else:
        ?>
		<p>
                        <input type="hidden" name="<?php echo $meta_box['name']; ?>_noncename" id="<?php echo $meta_box['name']; ?>_noncename" value="<?php echo wp_create_nonce( plugin_basename(__FILE__) ); ?>" />
			<label for="<?php echo $meta_box['name']; ?>_value">Show:</label>
			<select id="<?php echo $meta_box['name']; ?>_value" name="<?php echo $meta_box['name']; ?>_value" class="widefat">
				<option <?php if ( 'All' == $meta_box_value ) echo 'selected="selected"'; ?>>All</option>
				<option <?php if ( 'Answered' == $meta_box_value ) echo 'selected="selected"'; ?>>Answered</option>
				<option <?php if ( 'UnAnswered' == $meta_box_value ) echo 'selected="selected"'; ?>>UnAnswered</option>
                                <option <?php if ( 'Popular Questions Only' == $meta_box_value ) echo 'selected="selected"'; ?>>Popular Questions Only</option>
			</select>
                        <?php echo $meta_box['description']; ?>
		</p>
        <?php

                endif;
	}
}

function create_meta_box() {
	global $theme_name;
	if ( function_exists('add_meta_box') ) {
		add_meta_box( 'new-meta-boxes', 'Related Qhub Settings', 'new_meta_boxes', 'post', 'normal', 'high' );
	}
}

function save_postdata( $post_id ) {
	global $post, $new_meta_boxes;

	foreach($new_meta_boxes as $meta_box) {
		// Verify
		if ( !wp_verify_nonce( htmlspecialchars($_POST[$meta_box['name'].'_noncename']), plugin_basename(__FILE__) )) {
		return $post_id;
	}

	if ( 'page' == $_POST['post_type'] ) {
		if ( !current_user_can( 'edit_page', $post_id ))
			return $post_id;
		} else {
			if ( !current_user_can( 'edit_post', $post_id ))
		return $post_id;
	}

	$data = htmlspecialchars($_POST[$meta_box['name'].'_value']);

	if(get_post_meta($post_id, $meta_box['name'].'_value') == "")
		add_post_meta($post_id, $meta_box['name'].'_value', $data, true);
	elseif($data != get_post_meta($post_id, $meta_box['name'].'_value', true))
		update_post_meta($post_id, $meta_box['name'].'_value', $data);
	elseif($data == "")
		delete_post_meta($post_id, $meta_box['name'].'_value', get_post_meta($post_id, $meta_box['name'].'_value', true));
	}
}



add_action('admin_init', 'register_qhubsettings' );
add_action('admin_menu', 'addQhubPluginToSubmenu');

add_action('the_content', 'qhub_related_display');

add_action('admin_menu', 'create_meta_box');
add_action('save_post', 'save_postdata');

register_activation_hook(__FILE__,'qhub_plugin_install');
register_deactivation_hook(__FILE__, 'qhub_plugin_uninstall');

?>
