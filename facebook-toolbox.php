<?php
/*
Plugin Name: Facebook Toolbox
Plugin URI: http://wwww.simonfoster.co.uk/facebook-toolbox
Description: Provides various snippets for integrating Facebook in Wordpress posts
Version: 0.01
Author: Simon Foster
Author URI: http://www.simonfoster.co.uk
*/


// pull out the options
$fbt_options = get_option('fbt_options');

// define plugin defaults
DEFINE('FBT_APP_ID', $fbt_options['fbt_app_id']);
DEFINE('FBT_APP_SECRET', $fbt_options['fbt_app_secret']);

if (!empty($fbt_options['debug_tick']) && $fbt_options['debug_tick']=='yes') {
	DEFINE('FBT_DEBUG', 'yes');
}
else {
	DEFINE('FBT_DEBUG', 'no');
}
if (!empty($fbt_options['cache_tick']) && $fbt_options['cache_tick']=='yes') {
	DEFINE('FBT_CACHE', 'no');
}
else {
	DEFINE('FBT_CACHE', 'yes');
}


// register stylesheet
function fbt_add_stylesheet() {
	wp_enqueue_style('facebook-toolbox', plugins_url('facebook-toolbox.css', __FILE__), false, '0.0.2', 'all');
}
add_action('wp_enqueue_scripts', 'fbt_add_stylesheet', 99999);

// tell wordpress to register the shortcode
add_shortcode('fbt-text', 'fbt_text_handler');
add_shortcode('fbt-status', 'fbt_status_handler');



/** Initialise the Facebook Graph-API SDK
 */

function fbt_initialise()
{
	require_once __DIR__ . '/vendor/autoload.php';

	$fb = new Facebook\Facebook([
		'app_id'				=> FBT_APP_ID, 
		'app_secret'			=> FBT_APP_SECRET,
		'default_graph_version'	=> 'v2.7'
	]);

	$fb->setDefaultAccessToken(FBT_APP_ID . '|' . FBT_APP_SECRET);

	return $fb;
}



// access the Facebook Graph API
function fbt_api($url)
{
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($curl, CURLOPT_VERBOSE, 1);
	curl_setopt($curl, CURLOPT_URL, 'https://graph.facebook.com/v2.7/' . $url . '?access_token=' . FBT_APP_ID . '|' . FBT_APP_SECRET);

	return json_decode(curl_exec($curl));
}



// handle the facebook text
function fbt_text_handler($arrParameters)
{
	$data = fbt_api($arrParameters['post']);

	if (empty($data)) {
		return '';
	}

	return $data->message;
}



// handle the facebook status
function fbt_status_handler($arrParameters)
{
	$likes = fbt_api($arrParameters['post'] . '/likes');
	$comments = fbt_api($arrParameters['post'] . '/comments');

	$summary = count($likes->data) . ' likes ' . count($comments->data) . ' comments';

	// depending on how many likes we have, express the names as text
	if (count($likes->data)>0) {
		switch (count($likes->data)) {
			case 1: 
				$detail = $likes->data[0]->name . ' likes this';
				break;

			case 2:
				$detail = $likes->data[0]->name . ' and ' . $likes->data[1]->name . ' like this';
				break;

			case 3:
				$detail = $likes->data[0]->name . ', ' . $likes->data[1]->name . ' and ' . $likes->data[2]->name . ' like this';
				break;

			default:
				$detail = $likes->data[0]->name . ', ' . $likes->data[1]->name . ' and ' . (count($likes->data) - 2) . ' others like this';
		}

		$detail = '<p>' . $detail . '</p>';
	}

	// depending on the comments, express the detail
	if (count($comments->data)>0) {
		foreach ($comments->data as $comment) {
			$detail .= '<hr>';
			$detail .= '<p>' . $comment->from->name . '<br>' . $comment->message . '</p>';
		}
	}

	$ret = '<div id="click_' . $arrParameters['post'] . '" class="fbt_summary fbt_smaller"><p>' . $summary . '</p></div>';
	$ret .= '<div id="detail_' . $arrParameters['post'] . '" class="fbt_hidden fbt_smaller">' . $detail . '</div>';
	$ret .= '<script>jQuery(\'#click_' . $arrParameters['post'] . '\').on(\'click\', function() {';
	$ret .= ' jQuery(\'#detail_' . $arrParameters['post'] . '\').slideToggle(); ';
	$ret .= '}); </script>';

	return $ret;
}



/***************************************************************************
 *
 * OPTIONS
 *
 ***************************************************************************/

// register the menu function
add_action('admin_menu', 'fbt_plugin_menu');

// the menu function
function fbt_plugin_menu() {
	add_options_page('Facebook Toolbox Settings', 'Facebook Toolbox', 'manage_options', 'fbt_options', 'fbt_plugin_options');
}

// menu options
function fbt_plugin_options() {
	if (!current_user_can('manage_options')) {
		wp_die(__('You do not have sufficient permissions to access this page.'));
	}
?>
	<div>
		<h2>Facebook Toolbox Settings</h2>
		<form action="options.php" method="post">
			<?php settings_fields('fbt_options'); ?>
			<?php do_settings_sections('fbt_plugin'); ?>

			<input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" />
		</form>
	</div>
<?php
}

// register the settings
add_action('admin_init', 'fbt_admin_init');

function fbt_main_section_text() {
	echo '<p>Please enter your Facebook App details for accessing Facebook.</p>';
}

function fbt_app_id_string() {
	$options = get_option('fbt_options');
	echo '<input id="fbt_app_id_string" name="fbt_options[fbt_app_id]" size="40" type="text" value="' . $options['fbt_app_id'] . '" />';
}

function fbt_app_secret_string() {
	$options = get_option('fbt_options');
	echo '<input id="fbt_app_secret_string" name="fbt_options[fbt_app_secret]" size="40" type="text" value="' . $options['fbt_app_secret'] . '" />';
}

function fbt_debug_section_text() {
	echo '<p>In order for the plugin to use the specified date and year make sure the debug mode option is ticked.</p>';
}

function fbt_debug_tick() {
	$options = get_option('fbt_options');
	echo '<input id="fbt_debug_tick" name="fbt_options[debug_tick]" type="checkbox" value="yes"';
	if ($options['debug_tick']=='yes') {
		echo ' checked';
	}
	echo ' />';
}

function fbt_cache_section_text() {
	echo '<p>If you would like to turn off the cache, please tick the option below.</p>';
}

function fbt_cache_tick() {
	$options = get_option('fbt_options');
	echo '<input id="fbt_cache_tick" name="fbt_options[cache_tick]" type="checkbox" value="yes"';
	if ($options['cache_tick']=='yes') {
		echo ' checked';
	}
	echo ' />';
}

function fbt_admin_init() {
	register_setting('fbt_options', 'fbt_options', 'fbt_options_validate');
	add_settings_section('fbt_main', 'Access Settings', 'fbt_main_section_text', 'fbt_plugin');
	add_settings_field('fbt_app_id_string', 'App ID', 'fbt_app_id_string', 'fbt_plugin', 'fbt_main');
	add_settings_field('fbt_app_secret_string', 'App Secret', 'fbt_app_secret_string', 'fbt_plugin', 'fbt_main');
	add_settings_section('fbt_debug', 'Debug Settings', 'fbt_debug_section_text', 'fbt_plugin');
	add_settings_field('fbt_debug_tick', 'Debug Mode', 'fbt_debug_tick', 'fbt_plugin', 'fbt_debug');
	add_settings_section('fbt_cache', 'Cache Settings', 'fbt_cache_section_text', 'fbt_plugin');
	add_settings_field('fbt_cache_tick', 'Disable Cache', 'fbt_cache_tick', 'fbt_plugin', 'fbt_cache');
}

function fbt_options_validate($input) {
	$options = get_option('fbt_options');
	$options['fbt_app_id'] = $input['fbt_app_id'];
	$options['fbt_app_secret'] = $input['fbt_app_secret'];
	if (empty($input['debug_tick'])) {
		$options['debug_tick'] = 'no';
	}
	else {
		$options['debug_tick'] = $input['debug_tick'];
	}
	if (empty($input['cache_tick'])) {
		$options['cache_tick'] = 'no';
	}
	else {
		$options['cache_tick'] = $input['cache_tick'];
	}

	return $options;
}
