<?php
/*
Plugin Name: AllPlayers Connect - Base
Plugin URI:
Description: Default OAuth configuration to support AllPlayers.com WordPress integration.
Author: AllPlayers.com
Version: 0.1
Author URI: https://allplayers.com
License: GPL2

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License version 2,
    as published by the Free Software Foundation.

    You may NOT assume that you can use any other version of the GPL.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    The license for this software can likely be found here:
    http://www.gnu.org/licenses/gpl-2.0.html


    Source inspired by Samuel Wood (Otto): Simple Twitter Connect
    Author(s): Chris Christensen (http://imetchrischris.com)
*/

add_action('init','ap_init');
function ap_init() {
	// fast check for authentication requests on plugin load.
	if (session_id() == '') {
		session_start();
	}
	if(isset($_GET['ap_oauth_start'])) {
		ap_oauth_start();
	}
	if(isset($_GET['oauth_token'])) {
		ap_oauth_confirm();
	}
}

// require PHP 5
function ap_activation_check(){
	if (version_compare(PHP_VERSION, '5.0.0', '<')) {
		deactivate_plugins(basename(__FILE__)); // Deactivate ourself
		wp_die("Sorry, AllPlayers.com Connect requires PHP 5 or higher. Ask your host how to enable PHP 5 as the default on your servers.");
	}
}
register_activation_hook(__FILE__, 'ap_activation_check');

function ap_version() {
	return '0.11';
}

// action links
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'ap_settings_link', 10, 1);
function ap_settings_link($links) {
	$links[] = '<a href="'.admin_url('options-general.php?page=ap').'">Settings</a>';
	return $links;
}

// add the admin settings and such
add_action('admin_init', 'ap_admin_init',9); // 9 to force it first, subplugins should use default
function ap_admin_init(){
	$options = get_option('ap_options');
	if (empty($options['consumer_key']) || empty($options['consumer_secret'])) {
		add_action('admin_notices', create_function( '', "echo '<div class=\"error\"><p>".sprintf('AllPlayers.com Connect needs configuration information on its <a href="%s">settings</a> page.', admin_url('options-general.php?page=ap'))."</p></div>';" ) );
	}
	wp_enqueue_script('jquery');
	register_setting( 'ap_options', 'ap_options', 'ap_options_validate' );
	add_settings_section('ap_main', 'Main Settings', 'ap_section_text', 'ap');
	if (!defined('AP_CONSUMER_KEY')) add_settings_field('ap_consumer_key', 'AllPlayers.com Consumer Key', 'ap_setting_consumer_key', 'ap', 'ap_main');
	if (!defined('AP_CONSUMER_SECRET')) add_settings_field('ap_consumer_secret', 'AllPlayers.com Consumer Secret', 'ap_setting_consumer_secret', 'ap', 'ap_main');
	add_settings_field('ap_default_button', 'AllPlayers.com Default Button', 'ap_setting_default_button', 'ap', 'ap_main');
}

// add the admin options page
add_action('admin_menu', 'ap_admin_add_page');
function ap_admin_add_page() {
	global $ap_options_page;
	$ap_options_page = add_options_page('AllPlayers.com Connect', 'AllPlayers.com Connect', 'manage_options', 'ap', 'ap_options_page');
}

function ap_plugin_help($contextual_help, $screen_id, $screen) {

	global $ap_options_page;
	if ($screen_id == $ap_options_page) {

		$home = home_url('/');
		$contextual_help = <<< END
<p>To connect your site to AllPlayers.com, you will need tokens.
If you have already tokens, please insert your Consumer Key and Consumer Secret below.</p>

<p><strong>Haven't created an application yet?</strong> Don't worry, it's easy!</p>
<ol>
<li><a target="_blank" href="http://develop.allplayers.com/oauth.html">AllPlayers.com OAuth documentation.</a></li>
<li>Important Settings:<ol>
<li>Callback URL must be set to <strong>{$home}</strong></li>
</ol>
</li>
<li>After creating the application, copy and paste the Consumer Key and Consumer Secret from the Application Details page.</li>
</ol>
END;
	}
	return $contextual_help;
}
add_action('contextual_help', 'ap_plugin_help', 10, 3);

// display the admin options page
function ap_options_page() {
?>
	<div class="wrap">
	<h2>AllPlayers.com Connect</h2>
	<p>Options relating to the AllPlayers.com Connect plugins.</p>
	<form method="post" action="options.php">
	<?php settings_fields('ap_options'); ?>
	<table><tr><td>
	<?php do_settings_sections('ap'); ?>
	</td></tr></table>
	<p class="submit">
	<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
	</p>
	</form>

	</div>

<?php
}

function ap_oauth_start() {
	$options = get_option('ap_options');
	if (empty($options['consumer_key']) || empty($options['consumer_secret'])) return false;
	include_once "AllPlayersOAuth.php";

	$to = new AllPlayersOAuth($options['consumer_key'], $options['consumer_secret']);
	$tok = $to->getRequestToken();

	$token = $tok['oauth_token'];
	$_SESSION['ap_req_token'] = $token;
	$_SESSION['ap_req_secret'] = $tok['oauth_token_secret'];

	$_SESSION['ap_callback'] = $_GET['loc'];
	$_SESSION['ap_callback_action'] = $_GET['apaction'];

	if ($_GET['type'] == 'authorize') $url=$to->getAuthorizeURL($token);
	else $url=$to->getAuthenticateURL($token);

	wp_redirect($url);
	exit;
}

function ap_oauth_confirm() {
	$options = get_option('ap_options');
	if (empty($options['consumer_key']) || empty($options['consumer_secret'])) return false;
	include_once "AllPlayersOAuth.php";

	$to = new AllPlayersOAuth($options['consumer_key'], $options['consumer_secret'], $_SESSION['ap_req_token'], $_SESSION['ap_req_secret']);

	$tok = $to->getAccessToken();

	$_SESSION['ap_acc_token'] = $tok['oauth_token'];
	$_SESSION['ap_acc_secret'] = $tok['oauth_token_secret'];

	$to = new AllPlayersOAuth($options['consumer_key'], $options['consumer_secret'], $tok['oauth_token'], $tok['oauth_token_secret']);

	// this lets us do things actions on the return from AllPlayers.com and such
	if ($_SESSION['ap_callback_action']) {
		do_action('ap_'.$_SESSION['ap_callback_action']);
		$_SESSION['ap_callback_action'] = ''; // clear the action
	}

	wp_redirect($_SESSION['ap_callback']);
	exit;
}

// get the user credentials from AllPlayers.com
function ap_get_credentials($force_check = false) {
	// cache the results in the session so we don't do this over and over
	//if (!$force_check && $_SESSION['ap_credentials']) return $_SESSION['ap_credentials'];

	$_SESSION['ap_credentials'] = ap_do_request('https://www.allplayers.com/api/v1/rest/users/current');

	return $_SESSION['ap_credentials'];
}

// json is assumed for this, so don't add .xml or .json to the request URL
function ap_do_request($url, $args = array(), $type = NULL) {

	if ($args['acc_token']) {
		$acc_token = $args['acc_token'];
		unset($args['acc_token']);
	} else {
		$acc_token = $_SESSION['ap_acc_token'];
	}

	if ($args['acc_secret']) {
		$acc_secret = $args['acc_secret'];
		unset($args['acc_secret']);
	} else {
		$acc_secret = $_SESSION['ap_acc_secret'];
	}

	$options = get_option('ap_options');
	if (empty($options['consumer_key']) || empty($options['consumer_secret']) ||
		empty($acc_token) || empty($acc_secret) ) return false;

	include_once "AllPlayersOAuth.php";

	$to = new AllPlayersOAuth($options['consumer_key'], $options['consumer_secret'], $acc_token, $acc_secret);
	$json = $to->OAuthRequest($url.'.json', $args, $type);

	return json_decode($json);
}

function ap_section_text() {
	$options = get_option('ap_options');
	if (empty($options['consumer_key']) || empty($options['consumer_secret'])) {
?>
<p>To connect your site to AllPlayers.com, you will need tokens.
If you have already tokens, please insert your Consumer Key and Consumer Secret below.</p>

<p><strong>Haven't created an application yet?</strong> Don't worry, it's easy!</p>
<ol>
<li><a target="_blank" href="http://develop.allplayers.com/oauth.html">AllPlayers.com OAuth documentation.</a></li>
<li>Important Settings:<ol>
<li>Callback URL must be set to <strong><?php echo home_url('/') ?></strong></li>
</ol>
</li>
<li>After creating the application, copy and paste the Consumer Key and Consumer Secret from the Application Details page.</li>
</ol>
<?php
	}
}

function ap_get_connect_button($action='', $type='authenticate') {
	$options = get_option('ap_options');
	if (empty($options['default_button'])) $options['default_button'] = 'Sign-in-with-AllPlayers-darker';
	return '<a href="'.get_bloginfo('home').'/?ap_oauth_start=1&apaction='.urlencode($action).'&loc='.urlencode(ap_get_current_url()).'&type='.urlencode($type).'">'.
		   '<img border="0" src="'.plugins_url('/images/'.$options['default_button'].'.png', __FILE__).'" />'.
		   '</a>';
}

function ap_get_current_url() {
	// build the URL in the address bar
	$requested_url  = ( !empty($_SERVER['HTTPS'] ) && strtolower($_SERVER['HTTPS']) == 'on' ) ? 'https://' : 'http://';
	$requested_url .= $_SERVER['HTTP_HOST'];
	$requested_url .= $_SERVER['REQUEST_URI'];
	return $requested_url;
}

function ap_setting_consumer_key() {
	if (defined('ap_CONSUMER_KEY')) return;
	$options = get_option('ap_options');
	echo "<input type='text' id='apconsumerkey' name='ap_options[consumer_key]' value='{$options['consumer_key']}' size='40' /> (required)";
}

function ap_setting_consumer_secret() {
	if (defined('AP_CONSUMER_SECRET')) return;
	$options = get_option('ap_options');
	echo "<input type='text' id='apconsumersecret' name='ap_options[consumer_secret]' value='{$options['consumer_secret']}' size='40' /> (required)";
}

function ap_setting_default_button() {
	$options = get_option('ap_options');
	if (empty($options['default_button'])) $options['default_button'] = 'Sign-in-with-AllPlayers-darker';
	?>
	<select name="ap_options[default_button]" id="ap_select_default_button">
	<option value="Sign-in-with-AllPlayers-darker" <?php selected('Sign-in-with-AllPlayers-darker', $options['default_button']); ?>><?php _e('Darker', 'ap'); ?></option>
	<option value="Sign-in-with-AllPlayers-darker-small" <?php selected('Sign-in-with-AllPlayers-darker-small', $options['default_button']); ?>><?php _e('Darker small', 'ap'); ?></option>
	<option value="Sign-in-with-AllPlayers-lighter" <?php selected('Sign-in-with-AllPlayers-lighter', $options['default_button']); ?>><?php _e('Lighter', 'ap'); ?></option>
	<option value="Sign-in-with-AllPlayers-lighter-small" <?php selected('Sign-in-with-AllPlayers-lighter-small', $options['default_button']); ?>><?php _e('Lighter small', 'ap'); ?></option>
	</select>
	<br /><br />
	<img id="ap_select_default_button_preview_image" src="<?php echo plugins_url('/images/'.$options['default_button'].'.png', __FILE__); ?>" />
	<script type="text/javascript">
		jQuery(document).ready(function() {
			jQuery("#ap_select_default_button").change(function() {
				var selected = jQuery("#ap_select_default_button").val();
				jQuery("#ap_select_default_button_preview_image").attr('src',"<?php echo plugins_url('/images/', __FILE__); ?>"+selected+".png");
			});
		});
	</script>
<?php
}

// this will override the main options if they are pre-defined
function ap_override_options($options) {
	if (defined('AP_CONSUMER_KEY')) $options['consumer_key'] = AP_CONSUMER_KEY;
	if (defined('AP_CONSUMER_SECRET')) $options['consumer_secret'] = AP_CONSUMER_SECRET;
	return $options;
}
add_filter('option_ap_options', 'ap_override_options');


// validate our options
function ap_options_validate($input) {
	if (!defined('AP_CONSUMER_KEY')) {
		$input['consumer_key'] = trim($input['consumer_key']);
		if(! preg_match('/^[A-Za-z0-9]+$/i', $input['consumer_key'])) {
		  $input['consumer_key'] = '';
		}
	}

	if (!defined('AP_CONSUMER_SECRET')) {
		$input['consumer_secret'] = trim($input['consumer_secret']);
		if(! preg_match('/^[A-Za-z0-9]+$/i', $input['consumer_secret'])) {
		  $input['consumer_secret'] = '';
		}
	}

	$input = apply_filters('ap_validate_options',$input); // filter to let sub-plugins validate their options too
	return $input;
}
