<?php
/**
 * @package		WP_User_Sync
 * @author		Mechanical Pie Studio
 * @license		GPL-3.0+
 * @link		https://mechanical-pie.com
 * @version		1.0
 * 
 * @wordpress-plugin
 * Plugin Name:			WP User Sync
 * Plugin URI:			https://github.com/e13/wp-user-sync
 * Description:			Syncs users with MailChimp mailing lists depending on user roles
 * Version:			1.0
 * Text Domain:			wp-user-sync
 * Author:			Mechanical Pie Studio
 * Author URI:			https://mechanical-pie.com
 * License:			GPLv3 or later
 * License URI:			https://www.gnu.org/licenses/gpl-2.0.html
 * GitHub Plugin URI:		https://github.com/e13/wp-user-sync
 * GitHub Branch:		master
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'WPUSync_URL', plugin_dir_url( __FILE__ ) );
define( 'WPUSync_DIR', plugin_dir_path( __FILE__ ) );


define( 'WP_USER_SYNC_VERSION', '0.1' );

/**
 * The code that runs during plugin activation.
 */
function activate_wp_user_sync() {
}
/**
 * The code that runs during plugin deactivation.
 */
function deactivate_wp_user_sync() {
}

register_activation_hook( __FILE__, 'activate_wp_user_sync' );
register_deactivation_hook( __FILE__, 'deactivate_wp_user_sync' );



require_once( WPUSync_DIR . '/classes/class_WPUserSyncProcess.php' );

function wpusync_InitPlugin() {
	$wpusersync = new WPUserSyncProcess();
}

add_action( 'init', 'wpusync_InitPlugin' );
