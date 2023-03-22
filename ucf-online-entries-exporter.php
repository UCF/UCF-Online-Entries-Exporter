<?php
/*
Plugin Name: UCF Online Entries Exporter
Description: Provides a WP CLI command for exporting gravity form entries to an external MySQL database.
Version: 1.0.3
Author: UCF Web Communications
License: GPL3
GitHub Plugin URI: UCF/UCF-Online-Entries-Exporter
*/

if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'UCF_OEE__PLUGIN_FILE', __FILE__ );
define( 'UCF_OEE__PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'UCF_OEE__SSL_PATH', UCF_OEE__PLUGIN_PATH . 'ssl/' );

include_once UCF_OEE__PLUGIN_PATH . 'admin/ucf-oee-admin.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	include_once UCF_OEE__PLUGIN_PATH . 'includes/ucf-oee-db.php';
	include_once UCF_OEE__PLUGIN_PATH . 'utilities/ucf-oee-exporter.php';
	include_once UCF_OEE__PLUGIN_PATH . 'utilities/ucf-oee-purger.php';
	include_once UCF_OEE__PLUGIN_PATH . 'includes/ucf-oee-wpcli.php';

	WP_CLI::add_command( 'online', 'UCF_OEE_Commands' );
}
