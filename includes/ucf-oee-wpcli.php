<?php
/**
 * Commands for exporting gravity form entries to external MySQL databases
 *
 * ## OPTIONS
 *
 * [--start-date-time=<start-date-time>]
 * : The earliest entry dates to export.
 *
 * [--end-date-time=<end-date-time>]
 * : The latest entry dates to export.
 *
 * [--form-ids=<form_ids>]
 * : Comma separated list of form ids to export
 */
class UCF_OEE_Commands extends WP_CLI_Command {
	public function export( $args, $assoc_args ) {
		$start_date_time = isset( $assoc_args['start-date-time'] ) ?
			$assoc_args['start-date-time'] :
			null;

		$end_date_time = isset( $assoc_args['end-date-time'] ) ?
			$assoc_args['end-date-time'] :
			null;

		$form_ids = isset( $assoc_args['form-ids'] ) ?
			array_map( function( $value ) {
				return intval( $value );
			}, explode( ',', $assoc_args['form-ids'] ) ) :
			$forms      = get_field( 'ucf_oee_forms_to_export', 'option' );

		$connection_info = array(
			'host' => get_field( 'ucf_oee_database_host', 'option' ),
			'port' => get_field( 'ucf_oee_database_port', 'option' ),
			'user' => get_field( 'ucf_oee_database_user', 'option' ),
			'pass' => get_field( 'ucf_oee_database_pass', 'option' ),
			'name' => get_field( 'ucf_oee_database_name', 'option' ),
			'ssl'  => get_field( 'ucf_oee_database_use_ssl', 'option' )
		);

		$table_name = get_field( 'ucf_oee_database_table_name', 'option' );

		try {
			$exporter = new UCF_OEE_Exporter(
				$connection_info,
				$table_name,
				$form_ids,
				$start_date_time,
				$end_date_time
			);

			$exporter->export();
			$exporter->results();
			WP_CLI::success( "Export complete!" );

		} catch ( Exception $e ) {
			WP_CLI::error( "An error occurred: $e" );
		}
	}

	public function purge( $args, $assoc_args ) {
		WP_CLI::debug( "Running the purge command..." );
	}

	public function test_external_connect( $args, $assoc_args ) {
		$connection_info = array(
			'host' => get_field( 'ucf_oee_database_host', 'option' ),
			'port' => get_field( 'ucf_oee_database_port', 'option' ),
			'user' => get_field( 'ucf_oee_database_user', 'option' ),
			'pass' => get_field( 'ucf_oee_database_pass', 'option' ),
			'name' => get_field( 'ucf_oee_database_name', 'option' ),
			'ssl'  => get_field( 'ucf_oee_database_use_ssl', 'option' )
		);

		$host = $connection_info['port'] !== 3306 ?
			"{$connection_info['host']}:{$connection_info['port']}" :
			$connection_info['host'];

		$conn = new ssl_wpdb(
			$connection_info['user'],
			$connection_info['pass'],
			$connection_info['name'],
			"{$connection_info['host']}:{$connection_info['port']}",
			$connection_info['ssl']
		);

		if ( $conn->db_connect() ) {
			WP_CLI::success( "Successfully connected!" );
		} else {
			WP_CLI::error( "Unable to connect to MySQL" );
		}
	}
}
