<?php
/*
Plugin Name: Facebook Toolbox
Plugin URI: http://wwww.simonfoster.co.uk/facebook-toolbox
Description: Provides various snippets for integrating Facebook in Wordpress posts
Version: 0.04
Author: Simon Foster
Author URI: http://www.simonfoster.co.uk
*/


/** pull out the options from Wordpress */
$fbt_options = get_option('fbt_options');

/** define plugin defaults using the Wordpress options */
DEFINE('FBT_APP_ID', $fbt_options['fbt_app_id']);
DEFINE('FBT_APP_SECRET', $fbt_options['fbt_app_secret']);
DEFINE('FBT_PAGE_ID', $fbt_options['fbt_page_id']);

if (!empty($fbt_options['debug_tick']) && $fbt_options['debug_tick']=='yes') {
	DEFINE('FBT_DEBUG', 'yes');
}
else {
	DEFINE('FBT_DEBUG', 'no');
}


/**
 * Register stylesheet
 *
 * Function used to add the stylesheet to the output from Wordpress.
 *
 * @return void
 */
function fbt_add_stylesheet() {
	wp_enqueue_style('facebook-toolbox', plugins_url('facebook-toolbox.css', __FILE__), false, '0.0.2', 'all');
}
add_action('wp_enqueue_scripts', 'fbt_add_stylesheet', 99999);
add_action('admin_enqueue_scripts', 'fbt_add_stylesheet');

/** tell wordpress to register the shortcode */
add_shortcode('fbt-text', 'fbt_text_handler');
add_shortcode('fbt-status', 'fbt_status_handler');
add_shortcode('fbt-event', 'fbt_event_handler');

/** add new media buttons */
add_action('media_buttons', 'fbt_add_buttons', 15);


/**
 * Add new media button to let the user select a post
 *
 * @return void
 */
function fbt_add_buttons()
{
	echo '<a href="#" id="fbt_add_post" class="button"><img src="' . plugins_url( 'FB-f-Logo__blue_20.png', __FILE__ ) . '" > Add Post</a>';
	echo '<a href="#" id="fbt_add_event" class="button"><img src="' . plugins_url( 'FB-f-Logo__blue_20.png', __FILE__ ) . '" > Add Event</a>';
}


/**
 * Access the Facebook Graph API
 *
 * Send a query to the Facebook Graph API and return the response as a PHP object.
 *
 * @param string $url The query to send to Facebook.
 * @param strign $params An extra paramaters to pass in the URL, appended to the url i.e. &forward=false
 *
 * @return object The response from Facebook, using json_encode to change it to an object.
 */
function fbt_api($url, $params = '')
{
	$url = 'https://graph.facebook.com/v2.8/' . $url . '?access_token=' . FBT_APP_ID . '|' . FBT_APP_SECRET . $params;

	$curl = curl_init();
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($curl, CURLOPT_VERBOSE, 0);
	curl_setopt($curl, CURLOPT_URL, $url);

	$data = curl_exec($curl);

	if (FBT_DEBUG=='yes') {
		error_log($url);
		error_log(' = ' . $data);
	}

	curl_close($curl);

	return json_decode($data);
}


/**
 * The handler for [fbt-text post=<id>] shortcode
 *
 * Access a Facebook post and return the text entered in it.
 *
 * @param array $arrParameters A list of options within the shortcode,namely the post to query.
 *
 * @return string The message retrieved from Facebook.
 */
function fbt_text_handler($arrParameters)
{
	$data = fbt_api($arrParameters['post']);

	if (empty($data)) {
		return '';
	}

	return $data->message;
}


/**
 * The handler for [fbt-status post=<id>] shortcode
 *
 * Access a Facebook post and retrieve the Likes and Comments, outputting a summary plus detail
 * detail in a hidden div which can be displayed by clicking the summary.
 *
 * @param array $arrParameters A list of options within the shortcode,namely the post to query.
 *
 * @return string The data retrieved from Facebook.
 */
function fbt_status_handler($arrParameters)
{
	$likes = fbt_api($arrParameters['post'] . '/likes');
	$comments = fbt_api($arrParameters['post'] . '/comments');

	$summary = count($likes->data) . ' like' . (count($likes->data)==1 ? '' : 's') . ' &bull; ' . count($comments->data) . ' comment' . (count($comments->data)==1 ? '' : 's');

	// depending on how many likes we have, express the names as text
	if (count($likes->data)>0) {
		switch (count($likes->data)) {
			case 1: 
				$detail = $likes->data[0]->name . ' likes this.';
				break;

			case 2:
				$detail = $likes->data[0]->name . ' and ' . $likes->data[1]->name . ' like this.';
				break;

			case 3:
				$detail = $likes->data[0]->name . ', ' . $likes->data[1]->name . ' and ' . $likes->data[2]->name . ' like this.';
				break;

			default:
				$detail = $likes->data[0]->name . ', ' . $likes->data[1]->name . ' and ' . (count($likes->data) - 2) . ' others like this.';
		}

		$detail = '<div class="fbt_likes"><a href="https://www.facebook.com/' . $arrParameters['post'] . '" target="_blank">' . $detail . '</a></div>';
	}

	/** depending on the comments, express the detail */
	if (count($comments->data)>0) {
		foreach ($comments->data as $comment) {
//			$detail .= '<hr>';
			$detail .= '<div class="fbt_comment">';
			$picture = fbt_api($comment->from->id . '/picture', '&redirect=0');
//			$detail .= '(' . $picture->data->url . ')';
			if (!empty($picture->data->url)) {
				$detail .= '<a href="https://www.facebook.com/' . $comment->from->id . '" target="_blank"><img src="' . $picture->data->url . '" width="27"></a>';
			}
			$detail .= '<a href="https://www.facebook.com/' . $comment->from->id . '" target="_blank"><b>' . $comment->from->name . '</b></a> ' . $comment->message . '<br>' . date('jS F Y', strtotime($comment->created_time)) . '</div>';
		}
	}

	$ret = '<div class="fbt_container">';
	$ret .= '<div id="click_' . $arrParameters['post'] . '" class="fbt_summary fbt_smaller">';
	$ret .= '<a href="https://www.facebook.com/' . $arrParameters['post'] . '" target="_blank">';
	$ret .= '<img src="' . plugins_url( 'FB-f-Logo__blue_29.png', __FILE__ ) . '" >';
	$ret .= '</a>';
	$ret .= '<span>' . $summary . '</span>';
	$ret .= '</div>';
	$ret .= '<div id="detail_' . $arrParameters['post'] . '" class="fbt_hidden fbt_smaller">' . $detail . '</div>';
	$ret .= '</div>';
	$ret .= '<script>jQuery(\'#click_' . $arrParameters['post'] . '\').on(\'click\', function() {';
	$ret .= ' jQuery(\'#detail_' . $arrParameters['post'] . '\').slideToggle(); ';
	$ret .= '}); </script>';

	return $ret;
}


/**
 * The handler for [fbt-event event=<id>] shortcode
 *
 * Access a Facebook event and retrieve the Attendees and Comments, outputting a summary plus detail
 * detail in a hidden div which can be displayed by clicking the summary.
 *
 * @param array $arrParameters A list of options within the shortcode,namely the event to query.
 *
 * @return string The data retrieved from Facebook.
 */
function fbt_event_handler($arrParameters)
{
	$attending = fbt_api($arrParameters['event'] . '/attending');
	$interested = fbt_api($arrParameters['event'] . '/interested');
	$comments = fbt_api($arrParameters['event'] . '/comments');

	$summary = count($attending->data) . ' attending &bull; ' . count($interested->data) . ' interested';

	// depending on how many likes we have, express the names as text
	if (count($attending->data)>0) {
		switch (count($attending->data)) {
			case 1: 
				$detail = $attending->data[0]->name . ' is attending.';
				break;

			case 2:
				$detail = $attending->data[0]->name . ' and ' . $attending->data[1]->name . ' are attending.';
				break;

			case 3:
				$detail = $attending->data[0]->name . ', ' . $attending->data[1]->name . ' and ' . $attending->data[2]->name . ' are attending.';
				break;

			default:
				$detail = $attending->data[0]->name . ', ' . $attending->data[1]->name . ' and ' . (count($attending->data) - 2) . ' others are attending.';
		}

		$detail = '<div class="fbt_likes"><a href="https://www.facebook.com/' . $arrParameters['event'] . '" target="_blank">' . $detail . '</a></div>';
	}

	/** depending on the comments, express the detail */
	if (count($comments->data)>0) {
		foreach ($comments->data as $comment) {
//			$detail .= '<hr>';
			$detail .= '<div class="fbt_comment">';
			$picture = fbt_api($comment->from->id . '/picture', '&redirect=0');
//			$detail .= '(' . $picture->data->url . ')';
			if (!empty($picture->data->url)) {
				$detail .= '<a href="https://www.facebook.com/' . $comment->from->id . '" target="_blank"><img src="' . $picture->data->url . '" width="27"></a>';
			}
			$detail .= '<span><b>' . $comment->from->name . '</b> ' . $comment->message . '<br>' . date('jS F Y', strtotime($comment->created_time)) . '</span></div>';
		}
	}

	$ret = '<div class="fbt_container">';
	$ret .= '<div id="click_' . $arrParameters['event'] . '" class="fbt_summary fbt_smaller">';
	$ret .= '<a href="https://www.facebook.com/' . $arrParameters['event'] . '" target="_blank">';
	$ret .= '<img src="' . plugins_url( 'FB-f-Logo__blue_29.png', __FILE__ ) . '" >';
	$ret .= '</a>';
	$ret .= '<span>' . $summary . '</span>';
	$ret .= '</div>';
	$ret .= '<div id="detail_' . $arrParameters['event'] . '" class="fbt_hidden fbt_smaller">' . $detail . '</div>';
	$ret .= '</div>';
	$ret .= '<script>jQuery(\'#click_' . $arrParameters['event'] . '\').on(\'click\', function() {';
	$ret .= ' jQuery(\'#detail_' . $arrParameters['event'] . '\').slideToggle(); ';
	$ret .= '}); </script>';

	return $ret;
}


/**
 * The functions used to create the options screens within the Wordpress admin area
 */

/** register the menu function */
add_action('admin_menu', 'fbt_plugin_menu');

/**
 * Create the menu entry in the settings section.
 *
 * @return void
 */
function fbt_plugin_menu() {
	add_options_page('Facebook Toolbox Settings', 'Facebook Toolbox', 'manage_options', 'fbt_options', 'fbt_plugin_options');
}

/**
 * Render the settings for the plugin.
 *
 * @return void
 */
function fbt_plugin_options() {
	if (!current_user_can('manage_options')) {
		wp_die(__('You do not have sufficient permissions to access this page.'));
	}
?>
	<div class="fbt_admin_wrapper">
		<h1>Facebook Toolbox Settings</h1>
		<div class="fbt_admin_left">
			<form action="options.php" method="post">
				<?php settings_fields('fbt_options'); ?>
				<?php do_settings_sections('fbt_plugin'); ?>

				<input name="Submit" type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes'); ?>" />
			</form>
		</div>
		<div class="fbt_admin_right">
			<h3>Posts</h3>
<?php
	if (empty(FBT_PAGE_ID)) {
		echo '<p><i>To download posts, please enter a Page ID.</i></p>';
	}
	else {
		$posts = fbt_api(FBT_PAGE_ID . '/posts');
		if (empty($posts)) {
			echo '<p><i>No posts found.</i></p>';
		}
		else {
			foreach ($posts->data as $post) {
				$detail = (empty($post->message) ? (empty($post->story) ? '' : $post->story) : $post->message);
				if (!empty($detail)) {
					echo '<p>' . substr($detail, 0, 100) . '...<br /><b><a href="https://www.facebook.com/' . $post->id . '" target="_blank">' . $post->id . '</a></b></p>';
				}
			}
		}
	}
?>
			<hr />
			<h3>Events</h3>
<?php
	if (empty(FBT_PAGE_ID)) {
		echo '<p><i>To download events, please enter a Page ID.</i></p>';
	}
	else {
		$events = fbt_api(FBT_PAGE_ID . '/events');
		if (empty($events)) {
			echo '<p><i>No events found.</i></p>';
		}
		else {
			foreach ($events->data as $event) {
				echo '<p>' . $event->name . ' - ' . date('jS F Y', strtotime($event->start_time)) . '<br /><b><a href="https://www.facebook.com/' . $event->id . '" target="_blank">' . $event->id . '</a></b></p>';
			}
		}
	}
?>
		</div>
	</div>
<?php
}

/** Register the settings with Wordpress */
add_action('admin_init', 'fbt_admin_init');

/**
 * Render the introductory text for the main settings.
 *
 * return @void
 */
function fbt_main_section_text() {
	echo '<p>Please enter your Facebook App details for accessing the Facebook API.</p>';
}

/**
 * Render the text box for entering the Facebook App ID.
 *
 * return @void
 */
function fbt_app_id_string() {
	$options = get_option('fbt_options');
	echo '<input id="fbt_app_id_string" name="fbt_options[fbt_app_id]" size="40" type="text" value="' . $options['fbt_app_id'] . '" />';
}

/**
 * Render the text box for entering the Facebook App secret.
 *
 * return @void
 */
function fbt_app_secret_string() {
	$options = get_option('fbt_options');
	echo '<input id="fbt_app_secret_string" name="fbt_options[fbt_app_secret]" size="40" type="text" value="' . $options['fbt_app_secret'] . '" />';
}

/** 
 * Render introductory text for the page settings.
 *
 * return @void
 */
function fbt_page_section_text() {
	echo '<p>Please enter a Page ID below to get a list of posts and events you can select form.</p>';
}

/**
 * Render the text box for entering the Page ID.
 *
 * return @void
 */
function fbt_page_id_string() {
	$options = get_option('fbt_options');
	echo '<input id="fbt_page_id_string" name="fbt_options[fbt_page_id]" size="40" type="text" value="' . $options['fbt_page_id'] . '" />';
}

/** 
 * Render introductory text for the debug settings.
 *
 * return @void
 */
function fbt_debug_section_text() {
	echo '<p>Debug mode can be enabled below.</p>';
}

/**
 * Render the checkbox for selecting debugging.
 *
 * return @void
 */
function fbt_debug_tick() {
	$options = get_option('fbt_options');
	echo '<input id="fbt_debug_tick" name="fbt_options[debug_tick]" type="checkbox" value="yes"';
	if ($options['debug_tick']=='yes') {
		echo ' checked';
	}
	echo ' />';
}

/**
 * Register all the sections that make up the settings screen for the plugin.
 *
 * return @void
 */
function fbt_admin_init() {
	register_setting('fbt_options', 'fbt_options', 'fbt_options_validate');
	add_settings_section('fbt_main', 'Access Settings', 'fbt_main_section_text', 'fbt_plugin');
	add_settings_field('fbt_app_id_string', 'App ID', 'fbt_app_id_string', 'fbt_plugin', 'fbt_main');
	add_settings_field('fbt_app_secret_string', 'App Secret', 'fbt_app_secret_string', 'fbt_plugin', 'fbt_main');
	add_settings_section('fbt_page', 'Page Settings', 'fbt_page_section_text', 'fbt_plugin');
	add_settings_field('fbt_page_id_string', 'Page ID', 'fbt_page_id_string', 'fbt_plugin', 'fbt_page');
	add_settings_section('fbt_debug', 'Debug Settings', 'fbt_debug_section_text', 'fbt_plugin');
	add_settings_field('fbt_debug_tick', 'Debug Mode', 'fbt_debug_tick', 'fbt_plugin', 'fbt_debug');
}

/**
 * Take the entered settings and validate them ready for storing.
 *
 * return array Validated set of options for Wordpress to save to it's database.
 */
function fbt_options_validate($input) {
	$options = get_option('fbt_options');
	$options['fbt_app_id'] = $input['fbt_app_id'];
	$options['fbt_app_secret'] = $input['fbt_app_secret'];
	$options['fbt_page_id'] = $input['fbt_page_id'];
	if (empty($input['debug_tick'])) {
		$options['debug_tick'] = 'no';
	}
	else {
		$options['debug_tick'] = $input['debug_tick'];
	}

	return $options;
}
