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
		$use_ssl,
		$start_date_time,
		$end_date_time,
		$entries_per_page = 20,
		$conn,
		$results = array(),
		$schema_errors = array();

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
		$this->mysql_table = $table_name ?: null;
		$this->use_ssl    = $connection_info['ssl'] ?: false;
		$this->start_date_time = $start_date_time;
		$this->end_date_time = $end_date_time;
		$this->entries_per_page = 20;
		$this->forms = $forms ?: array();

		$this->conn = new ssl_wpdb(
			$this->mysql_user,
			$this->mysql_pass,
			$this->mysql_name,
			"$this->mysql_host:$this->mysql_port",
			$this->use_ssl
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

			WP_CLI::log( "Exporting entries for form \"{$form['title']}\" (Form ID {$form['id']})..." );

			$mappings = $this->generate_mappings( $form['fields'] );

			WP_CLI::log( "Verifying schema in target database for form \"{$form['title']}\"..." );
			$valid = $this->verify_columns( $form, $mappings );

			if ( ! $valid ) {
				WP_CLI::warning( "There were schema errors found for form \"{$form['title']}\".\n The columns that are missing will be reported at the end of the import." );
				continue;
			}

			$search_args = array();

			if ( $this->start_date_time ) {
				$search_args['start_date'] = $this->start_date_time;
			}

			if ( $this->end_date_time ) {
				$search_args['end_date'] = $this->end_date_time;
			}

			$total_count = GFAPI::count_entries(
				$form_id,
				$search_args
			);

			WP_CLI::log( "Exporting {$total_count} entries for form \"{$form['title']}\"..." );

			$entries = GFAPI::get_entries(
				$form_id,
				$search_args
			);

			$page_count = ( $this->entries_per_page !== 0 ) ?
				ceil( $total_count / $this->entries_per_page ) :
				0;

			$this->results[$form_id] = $this->setup_results( $form, $total_count );

			for( $page = 0; $page < $page_count; $page++ ) {
				if ( $page !== 0 ) {
					$paging_args = array(
						'offset'    => $page * $this->entries_per_page,
						'page_size' => $this->entries_per_page
					);

					$entries = GFAPI::get_entries(
						array( $form ),
						$search_args,
						array(),
						$paging_args
					);
				}

				$entry_count = count( $entries );

				WP_CLI::debug( "Entry count {$entry_count}" );

				foreach( $entries as $entry ) {
					$this->write_to_external_db( $entry, $mappings, $form_id );
				}
			}

			WP_CLI::log( "Finished exporting form \"{$form['title']}\"!\n" );
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

		$totals = array(
			'form'      => 'Total',
			'processed' => 0,
			'written'   => 0,
			'skipped'   => 0,
			'errors'    => 0
		);

		foreach( $this->results as $result_set ) {
			$data_items[] = array(
				'form' => $result_set['form_title'],
				'processed' => $result_set['entries_processed'],
				'written' => $result_set['entries_written'],
				'skipped' => $result_set['entries_skipped'],
				'errors'  => $result_set['entries_error']
			);

			$totals['processed'] += $result_set['entries_processed'];
			$totals['written'] += $result_set['entries_written'];
			$totals['skipped'] += $result_set['entries_skipped'];
			$totals['errors'] += $result_set['entries_error'];
		}

		if ( count( $this->results ) > 0 ) {
			// Add empty line
			$data_items[] = array(
				'form'      => '',
				'processed' => '',
				'written'   => '',
				'skipped'   => '',
				'errors'    => ''
			);

			// Add totals
			$data_items[] = $totals;
		}

		if ( count( $this->schema_errors ) > 0 ) {
			$schema_error_items = array();

			foreach( $this->schema_errors as $form ) {
				$columns_str = implode( "\n", $form['errors'] );

				$schema_error_items[] = array(
					'form' => $form['form'],
					'errors' => "The following columns were missing from the target database:\n{$columns_str}"
				);
			}

			WP_CLI::error( "The following schema errors were found during the export process:" );
				WP_CLI\Utils\format_items( 'table', $schema_error_items, array( 'form', 'errors' ) );
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
	 * Verifies that the target columns in the mapping
	 * array all exist.
	 *
	 * @author Jim Barnes
	 * @since 1.0.0
	 * @param  array $mappings The mappings array
	 * @return bool
	 */
	private function verify_columns( $form, $mappings ) {
		$columns = array();

		foreach( $mappings as $mapping ) {
			$columns[] = $mapping['mapped'];
		}

		$column_query = $this->conn->prepare(
			"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s;",
			array(
				$this->mysql_name,
				$this->mysql_table
			)
		);

		$results = $this->conn->get_results( $column_query );

		foreach( $results as $result ) {
			$columns[] = $result->COLUMN_NAME;
		}

		$verified = true;

		foreach( $mappings as $mapping ) {
			if ( ! in_array( $mapping['mapped'], $columns ) ) {
				$verified = false;
				$this->add_schema_error( $form, $mapping['mapped'] );
			}
		}

		return $verified;
	}

	/**
	 * Adds a schema error to the schema_error array
	 *
	 * @author Jim Barnes
	 * @since 1.0.0
	 * @param  GFForm $form
	 * @param  string $column
	 * @return void
	 */
	private function add_schema_error( $form, $column ) {
		if ( ! in_array( $form['id'], $this->schema_errors ) ) {
			$this->schema_errors[$form['id']] = array(
				'form' => $form['title'],
				'errors'     => array()
			);
		}

		$this->schema_errors[$form_id['id']]['errors'][] = $column;
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
			array( ' ', '?', ':', '(', ')', '.', '_', ','),
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
