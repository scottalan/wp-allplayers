<?php
/*
Plugin Name: AllPlayers Connect - Login
Plugin URI:
Description: Integrates AllPlayers.com Login and Authentication to WordPress
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

// if you want people to be able to disconnect their WP and AllPlayers accounts, set this to true in wp-config
if (!defined('AP_ALLOW_DISCONNECT'))
	define('AP_ALLOW_DISCONNECT',false);

// checks for ap on activation
function ap_login_activation_check(){
	if (function_exists('ap_version')) {
		if (version_compare(ap_version(), '0.1', '>=')) {
			return;
		}
	}
	deactivate_plugins(basename(__FILE__)); // Deactivate ourself
	wp_die("The base AllPlayers.com plugin must be activated before this plugin will run.");
}
register_activation_hook(__FILE__, 'ap_login_activation_check');

// add the section on the user profile page
add_action('profile_personal_options','ap_login_profile_page');

function ap_login_profile_page($profile) {
	$options = get_option('ap_options');
?>
	<table class="form-table">
		<tr>
			<th><label>AllPlayers.com Connect</label></th>
<?php
	$apuuid = get_usermeta($profile->ID, 'apuuid');
	if (empty($apuuid)) {
		?>
			<td><p><?php echo ap_get_connect_button('login_connect'); ?></p></td>
		</tr>
	</table>
	<?php
	} else { ?>
		<td><p><a href='https://www.allplayers.com/users/uuid/<?php echo $apuuid; ?>' title='<?php echo $apuuid; ?>'>Connected to AllPlayers.com</a>
<?php if (AP_ALLOW_DISCONNECT) { ?>
		<input type="button" class="button-primary" value="Disconnect this account from WordPress" onclick="ap_login_disconnect(); return false;" />
		<script type="text/javascript">
		function ap_login_disconnect() {
			var ajax_url = '<?php echo admin_url("admin-ajax.php"); ?>';
			var data = {
				action: 'disconnect_apuuid',
				apuuid: '<?php echo $apuuid; ?>'
			}
			jQuery.post(ajax_url, data, function(response) {
				if (response == '1') {
					location.reload(true);
				}
			});
		}
		</script>
<?php } ?>
</p></td>
	<?php } ?>
	</tr>
	</table>
	<?php
}

add_action('wp_ajax_disconnect_apuuid', 'ap_login_disconnect_apuuid');
function ap_login_disconnect_apuuid() {
	$user = wp_get_current_user();

	if (!AP_ALLOW_DISCONNECT) {
		// disconnect not allowed
		echo 1;
		exit();
	}

	$apuuid = get_usermeta($user->ID, 'apuuid');
	if ($apuuid == $_POST['apuuid']) {
		delete_usermeta($user->ID, 'apuuid');
	}

	echo 1;
	exit();
}

add_action('ap_login_connect','ap_login_connect');
function ap_login_connect() {
	if (!is_user_logged_in()) return; // this only works for logged in users
	$user = wp_get_current_user();

	$ap = ap_get_credentials();
	if ($ap) {
		// we have a user, update the user meta
		update_usermeta($user->ID, 'apuuid', $ap->uuid);
	}
}

add_action('login_form','ap_login_add_login_button');
function ap_login_add_login_button() {
	global $action;
	if ($action == 'login') echo '<p>'.ap_get_connect_button('login').'</p><br />';
}

add_filter('authenticate','ap_login_check');
function ap_login_check($user) {
	if ( is_a($user, 'WP_User') ) { return $user; } // check if user is already logged in, skip

	$ap = ap_get_credentials();
	if ($ap) {
		global $wpdb;
		$apuuid = $ap->uuid;
		$user_id = $wpdb->get_var( $wpdb->prepare("SELECT user_id FROM $wpdb->usermeta WHERE meta_key = 'apuuid' AND meta_value = '%s'", $apuuid) );

		if ($user_id) {
			$user = new WP_User($user_id);
		} else {
			do_action('ap_login_new_ap_user',$ap); // hook for creating new users if desired
			global $error;
			$error = '<strong>ERROR</strong>: AllPlayers.com user not recognized.';
		}
	}
	return $user;
}

add_action('wp_logout','ap_login_logout');
function ap_login_logout() {
	session_start();
	session_unset();
	session_destroy();
}

// add the AllPlayers.com to the admin bar
add_filter('admin_user_info_links','ap_login_admin_header');
function ap_login_admin_header($links) {
	$user = wp_get_current_user();
	$apuuid = get_user_meta($user->ID, 'apuuid', true);
	if ($apuuid) $links[7]="<a href='https://www.allplayers.com/users/uuid/$apuuid'>AllPlayers.com</a>";
	return $links;
}
