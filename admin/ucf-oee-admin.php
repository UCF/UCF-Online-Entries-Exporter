<?php
/**
 * Handles admin specific logic
 */
class UCF_OEE_Admin {
	/**
	 * Registers the options page for form exports
	 *
	 * @author Jim Barnes
	 * @since 1.0.0
	 * @return void
	 */
	public static function register_options_page() {
		if ( ! function_exists( 'acf_add_options_page' ) ) return;

		acf_add_options_page( array(
			'page_title' => 'UCF Online Entries Exporter Options',
			'menu_title' => 'UCF Online Entries Exporter',
			'menu_slug'  => 'ucf-online-entries-exporter',
			'icon_url'   => 'dashicons-media-document',
			'capability' => 'manage_options',
			'redirect'   => false
		) );
	}

	/**
	 * Registers the fields for the options page
	 *
	 * @author Jim Barnes
	 * @since 1.0.0
	 * @return void
	 */
	public static function add_option_page_fields() {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

		$options = array(
			'key'      => 'ucf_oee_options',
			'title'    => 'UCF Online Entries Exporter Options',
			'fields'   => array(),
			'location' => array(
				array(
					array(
						'param'    => 'options_page',
						'operator' => '==',
						'value'    => 'ucf-online-entries-exporter'
					)
				)
			),
			'active' => true
		);

		$fields = array();

		/**
		 * Add fields for the External Database Tab
		 */
		$fields[] = array(
			'key'   => 'external_database_options_tab',
			'label' => 'External Database Options',
			'type'  => 'tab'
		);

		$fields[] = array(
			'key'          => 'ucf_oee_database_host',
			'label'        => 'Host',
			'name'         => 'ucf_oee_database_host',
			'type'         => 'text',
			'instructions' => 'The hostname of the external database',
			'required'     => 1
		);

		$fields[] = array(
			'key'          => 'ucf_oee_database_port',
			'label'        => 'Port',
			'name'         => 'ucf_oee_database_port',
			'type'         => 'number',
			'instructions' => 'The port of the external database',
			'required'     => 0,
			'default'      => 6446
		);

		$fields[] = array(
			'key'          => 'ucf_oee_database_user',
			'label'        => 'Username',
			'name'         => 'ucf_oee_database_user',
			'type'         => 'text',
			'instructions' => 'The username to use to connect to the external database',
			'required'     => 1
		);

		$fields[] = array(
			'key'          => 'ucf_oee_database_pass',
			'label'        => 'Password',
			'name'         => 'ucf_oee_database_pass',
			'type'         => 'password',
			'instructions' => 'The password to use to connect to the external database',
			'required'     => 1
		);

		$fields[] = array(
			'key'          => 'ucf_oee_database_name',
			'label'        => 'Database Name',
			'name'         => 'ucf_oee_database_name',
			'type'         => 'text',
			'instructions' => 'The name of the database to write to',
			'required'     => 1
		);

		$fields[] = array(
			'key'          => 'ucf_oee_database_table_name',
			'label'        => 'Table Name',
			'name'         => 'ucf_oee_database_table_name',
			'type'         => 'text',
			'instructions' => 'The name of the table to write to',
			'required'     => 1
		);

		$fields[] = array(
			'key'           => 'ucf_oee_database_use_ssl',
			'label'         => 'Use SSL',
			'name'          => 'ucf_oee_database_use_ssl',
			'type'          => 'true_false',
			'instructions'  => 'When enabled, mysqli will attempt to connect with SSL',
			'default_value' => false
		);

		$fields[] = array(
			'key'           => 'ucf_oee_database_ca_pem',
			'label'         => 'CA Pem File',
			'name'          => 'ucf_oee_database_ca_pem',
			'type'          => 'textarea',
			'instructions'  => 'Paste in the contents of the ca.pem certificate file.'
		);

		/**
		 * Add fields for the Forms tab
		 */
		$fields[] = array(
			'key'          => 'forms_tab',
			'label'        => 'Forms',
			'type'         => 'tab'
		);

		$fields[] = array(
			'key'           => 'ucf_oee_forms_to_export',
			'label'         => 'Forms to Export',
			'name'          => 'ucf_oee_forms_to_export',
			'type'          => 'forms',
			'instructions'  => 'Select the forms to export entries from.',
			'required'      => 0,
			'allow_null'    => 0,
			'multiple'      => 1,
			'return_format' => 'id',
		);

		/**
		 * Add fields for the Record retainment tab
		 */
		$fields[] = array(
			'key'          => 'record_retention_tab',
			'label'        => 'Record Retention',
			'type'         => 'tab'
		);

		$fields[] = array(
			'key'          => 'record_retention_message',
			'label'        => 'Important',
			'type'         => 'message',
			'message'      => 'Only records from forms that are selected for export in the "Forms" tab will have their records purged regularly. Be sure to check off any forms that should have their data removed.'
		);

		$fields[] = array(
			'key'           => 'ucf_oee_retain_days',
			'label'         => 'Number of Days to Retain',
			'name'          => 'ucf_oee_retain_days',
			'type'          => 'number',
			'instructions'  => 'The number of days which to retain records. After this time period the record will be removed through an automated process.',
			'required'      => 1,
			'default_value' => 30
		);

		$options['fields'] = $fields;

		acf_add_local_field_group( $options );
	}
}

add_action( 'init', array( 'UCF_OEE_Admin', 'add_option_page_fields' ), 10, 0 );
add_action( 'admin_menu', array( 'UCF_OEE_Admin', 'register_options_page' ), 10, 0 );
