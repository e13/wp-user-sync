<?php
/*
Plugin Name: WordPress Users Sync
Description: Syncs users with mailing lists depending on user roles
Version: 0.1
Author: Mechanical Pie Studio
Author URI: https://mechanical-pie.com
*/

define( 'WPUSync_URL', plugin_dir_url(__FILE__) );
define( 'WPUSync_DIR', plugin_dir_path(__FILE__) );

require_once( WPUSync_DIR.'/classes/class_WPUserSyncProcess.php' );

function wpusync_InitPlugin()
{
    $wpusersync = new WPUserSyncProcess();
}
add_action('init', 'wpusync_InitPlugin');

?>