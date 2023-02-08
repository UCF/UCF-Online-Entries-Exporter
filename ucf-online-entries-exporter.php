<?php
/*
Plugin Name: UCF Online Entries Exporter
Description: Provides a WP CLI command for exporting gravity form entries to an external MySQL database.
Version: 1.0.0
Author: UCF Web Communications
License: GPL3
GitHub Plugin URI: UCF/UCF-Online-Entries-Exporter
*/

if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'UCF_OEE__PLUGIN_FILE', __FILE__ );
define( 'UCF_OEE__PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

include_once UCF_OEE__PLUGIN_PATH . 'admin/ucf-oee-admin.php';
