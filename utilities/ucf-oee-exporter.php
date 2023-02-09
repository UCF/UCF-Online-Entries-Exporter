<?php
/**
 * Utility for exporting gravity form entries to an external
 * MySQL Database
 */

class UCF_OEE_Exporter {
	public
		$mysql_host,
		$mysql_user,
		$mysql_pass,
		$mysql_name,
		$mysql_table,
		$start_date_time,
		$end_date_time,
		$conn,
		$results = array();

	/**
	 * Constructs an instance of the UCF_OEE_Exporter.
	 *
	 * @since 1.0.0
	 * @author Jim Barnes
	 * @param array $connection_info An array containing the connection info for the external MySQL Database
	 * @param array $table_name The name of the table to write the entries to.
	 * @param array $forms An array of integers containing the form IDs to export
	 * @param array $mappings An array of field mappings
	 */
	public function __construct( $connection_info, $table_name = 'online_entries', $forms = array(), $start_date_time, $end_date_time ) {
		$this->mysql_host = $connection_info['host'] ?: null;
		$this->mysql_port = $connection_info['port'] ?: 3306;
		$this->mysql_user = $connection_info['user'] ?: null;
		$this->mysql_pass = $connection_info['pass'] ?: null;
		$this->mysql_name = $connection_info['name'] ?: null;
		$this->start_date_time = $start_date_time;
		$this->end_date_time = $end_date_time;
		$this->mysql_table = $table_name ?: null;
		$this->forms = $forms ?: array();

		$this->conn = new wpdb(
			$this->mysql_user,
			$this->mysql_pass,
			$this->mysql_name,
			"$this->mysql_host:$this->mysql_port"
		);

		if ( ! $this->test_connection() ) {
			throw new Exception( "There was an error connecting to the database. Please check the connection information." );
		}
	}

	/**
	 * Tests the MySQL Connection
	 *
	 * @since 1.0.0
	 * @author Jim Barnes
	 * @return bool True if the connection is valid, false if not.
	 */
	public function test_connection() {
		return $this->conn->db_connect();
	}

	public function export() {
		foreach( $this->forms as $form_id ) {
			$form = GFAPI::get_form( $form_id );

			WP_CLI::debug( "Exporting entries for form \"{$form['title']}\" (Form ID {$form['id']})..." );

			$mappings = $this->generate_mappings( $form['fields'] );

			$search_args = array();

			if ( $this->start_date_time ) {
				$search_args['start_date'] = $this->start_date_time;
			}

			if ( $this->end_date_time ) {
				$search_args['end_date'] = $this->end_date_time;
			}

			$total_count = GFAPI::count_entries(
				array( $form ),
				$search_args
			);

			WP_CLI::debug( "Processing $total_count entries..." );

			$entries = GFAPI::get_entries(
				array( $form ),
				$search_args
			);

			$entries_per_page = count( $entries );

			$page_count = ( $entries_per_page !== 0 ) ?
				ceil( $total_count / $entries_per_page ) :
				0;

			$this->results[$form_id] = $this->setup_results( $form, $total_count );

			for( $page = 0; $page < $page_count; $page++ ) {
				if ( $page !== 0 ) {
					$paging_args = array(
						'offset'    => $page * $entries_per_page,
						'page_size' => $entries_per_page
					);

					$entries = GFAPI::get_entries(
						array( $form ),
						$search_args,
						array(),
						$paging_args
					);
				}

				foreach( $entries as $entry ) {
					$this->write_to_external_db( $entry, $mappings, $form_id );
				}
			}
		}
	}

	/**
	 * Returns a string of results
	 *
	 * @author Jim Barnes
	 * @since 1.0.0
	 * @return string
	 */
	public function results() {
		$data_items = array();

		foreach( $this->results as $result_set ) {
			$data_items[] = array(
				'form' => $result_set['form_title'],
				'processed' => $result_set['entries_processed'],
				'written' => $result_set['entries_written'],
				'skipped' => $result_set['entries_skipped'],
				'errors'  => $result_set['entries_error']
			);
		}

		WP_CLI\Utils\format_items( 'table', $data_items, array( 'form', 'processed', 'written', 'skipped', 'errors' ) );
	}

	/**
	 * Generates a mapping array from the form fields
	 *
	 * @author Jim Barnes
	 * @since 1.0.0
	 * @param array $fields The fields array from the gravity form
	 * @return array
	 */
	private function generate_mappings( $fields ) {
		$retval = new stdClass;

		foreach( $fields as $field ) {
			$id = strval( $field['id'] );
			$label = $field['label'];
			$mapped = $this->field_label_formatted( $label );

			$retval->{ $id } = array(
				'label' => $label,
				'mapped' => $mapped
			);
		}

		$retval->{ 'id' } = array(
			'label'  => 'id',
			'mapped' => 'entryid'
		);

		$retval->{ 'date_created' } = array(
			'label'  => 'date_created',
			'mapped' => 'entrydate'
		);

		$retval->{ 'source_url' } = array(
			'label'  => 'source_url',
			'mapped' => 'leadsourceurl'
		);

		return (array) $retval;
	}

	/**
	 * Formats the field label to lower case and removes
	 * spaces for mapping to the external database.
	 *
	 * @author Jim Barnes
	 * @since 1.0.0
	 * @param string $field_name The name of the field
	 * @return string The formatted field name
	 */
	private function field_label_formatted( $field_name ) {
		return str_replace(
			' ',
			'',
			strtolower( $field_name )
		);
	}

	/**
	 * Returns the results array so it can be accessed
	 * during the export process.
	 *
	 * @author Jim Barnes
	 * @since 1.0.0
	 * @param array $form The form information
	 * @param int $total_count The total count of records to process
	 * @return array
	 */
	private function setup_results( $form, $total_count ) {
		return array(
			'form_id'           => $form['id'],
			'form_title'        => $form['title'],
			'entries_processed' => $total_count,
			'entries_written'   => 0,
			'entries_skipped'   => 0,
			'entries_error'     => 0
		);
	}

	/**
	 * Writes the record to the external database
	 *
	 * @author Jim Barnes
	 * @since 1.0.0
	 * @param Record $record The record object
	 * @return void
	 */
	private function write_to_external_db( $record, $mappings, $form_id ) {
		$data = array();

		foreach( $mappings as $key => $mapping ) {
			$field_name = $mapping['mapped'];

			if ( array_key_exists( $key, $record ) ) {
				$data[$field_name] = $record[$key];
			} else {
				$data[$field_name] = null;
			}
		}

		$entryid = array_key_exists( 'id', $record ) ? intval( $record['id'] ) : null;

		$exists_query = $this->conn->prepare(
			"SELECT EXISTS(SELECT entryid FROM {$this->conn->dbname}.{$this->mysql_table} WHERE entryid = %d);",
			array(
				$entryid
			)
		);

		if ( ! (bool) $this->conn->get_var( $exists_query ) ) {
			$record_id = $this->conn->insert(
				$this->mysql_table,
				$data
			);

			if ( $record_id === false ) {
				$this->results[$form_id]['entries_error'] += 1;
			} else {
				$this->results[$form_id]['entries_written'] += 1;
			}

			WP_CLI::debug( "Record $record_id created!" );
		} else {
			$this->results[$form_id]['entries_skipped'] += 1;
		}
	}

}
