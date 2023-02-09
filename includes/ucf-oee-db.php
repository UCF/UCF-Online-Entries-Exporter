<?php
require_once ABSPATH . 'wp-includes/wp-db.php';

class ssl_wpdb extends wpdb {
	public $use_ssl;

	/**
	 * Constructs a new instance of the ssl_wpdb class
	 *
	 * @author Jim Barnes
	 * @since 1.0.0
	 * @param  string $dbuser
	 * @param  string $dbpassword
	 * @param  string $dbname
	 * @param  string $dbhost
	 */
    public function  __construct( $dbuser, $dbpassword, $dbname, $dbhost, $use_ssl = False )
    {
		$this->use_ssl = $use_ssl;
        parent::__construct($dbuser, $dbpassword, $dbname, $dbhost);
    }

   /**
    * Method copied from wp-include/wp-db.php
    */
	public function db_connect( $allow_bail = true ) {
		$this->is_mysql = true;

		/*
		 * Deprecated in 3.9+ when using MySQLi. No equivalent
		 * $new_link parameter exists for mysqli_* functions.
		 */
		$new_link     = defined( 'MYSQL_NEW_LINK' ) ? MYSQL_NEW_LINK : true;
		$client_flags = defined( 'MYSQL_CLIENT_FLAGS' ) ? MYSQL_CLIENT_FLAGS : 0;

		if ( $this->use_mysqli ) {
			$this->dbh = mysqli_init();

			$host    = $this->dbhost;
			$port    = null;
			$socket  = null;
			$is_ipv6 = false;

			if ( $host_data = $this->parse_db_host( $this->dbhost ) ) {
				list( $host, $port, $socket, $is_ipv6 ) = $host_data;
			}

			/*
			 * If using the `mysqlnd` library, the IPv6 address needs to be
			 * enclosed in square brackets, whereas it doesn't while using the
			 * `libmysqlclient` library.
			 * @see https://bugs.php.net/bug.php?id=67563
			 */
			if ( $is_ipv6 && extension_loaded( 'mysqlnd' ) ) {
				$host = "[$host]";
			}

			if ( $this->use_ssl ) {
				$this->configure_ssl();
			}

			if ( WP_DEBUG ) {
				mysqli_real_connect( $this->dbh, $host, $this->dbuser, $this->dbpassword, null, $port, $socket,
$client_flags );
			} else {
				@mysqli_real_connect( $this->dbh, $host, $this->dbuser, $this->dbpassword, null, $port, $socket,
$client_flags );
			}

			if ( $this->dbh->connect_errno ) {
				$this->dbh = null;

				/*
				 * It's possible ext/mysqli is misconfigured. Fall back to ext/mysql if:
				  *  - We haven't previously connected, and
				  *  - WP_USE_EXT_MYSQL isn't set to false, and
				  *  - ext/mysql is loaded.
				  */
				$attempt_fallback = true;

				if ( $this->has_connected ) {
					$attempt_fallback = false;
				} elseif ( defined( 'WP_USE_EXT_MYSQL' ) && ! WP_USE_EXT_MYSQL ) {
					$attempt_fallback = false;
				} elseif ( ! function_exists( 'mysql_connect' ) ) {
					$attempt_fallback = false;
				}

				if ( $attempt_fallback ) {
					$this->use_mysqli = false;
					return $this->db_connect( $allow_bail );
				}
			}
		}

		if ( ! $this->dbh && $allow_bail ) {
			wp_load_translations_early();

			// Load custom DB error template, if present.
			if ( file_exists( WP_CONTENT_DIR . '/db-error.php' ) ) {
				require_once( WP_CONTENT_DIR . '/db-error.php' );
				die();
			}

			$message = '
' . __( 'Error establishing a database connection' ) . "
\n";

			$message .= '
' . sprintf(
				/* translators: 1: wp-config.php, 2: database host */
				__( 'This either means that the username and password information in your %1$s file is incorrect or we
can’t contact the database server at %2$s. This could mean your host’s database server is down.' ),
				'wp-config.php',
				'' . htmlspecialchars( $this->dbhost, ENT_QUOTES ) . ''
			) . "

\n";

			$message .= "
\n";
			$message .= '
' . __( 'Are you sure you have the correct username and password?' ) . "
\n";
			$message .= '
' . __( 'Are you sure that you have typed the correct hostname?' ) . "
\n";
			$message .= '
' . __( 'Are you sure that the database server is running?' ) . "
\n";
			$message .= "
\n";

			$message .= '
' . sprintf(
				/* translators: %s: support forums URL */
				__( 'If you’re unsure what these terms mean you should probably contact your host. If you still need
help you can always visit the WordPress Support Forums.' ),
				__( 'https://wordpress.org/support/forums/' )
			) . "

\n";

			$this->bail( $message, 'db_connect_fail' );

			return false;
		} elseif ( $this->dbh ) {
			if ( ! $this->has_connected ) {
				$this->init_charset();
			}

			$this->has_connected = true;

			$this->set_charset( $this->dbh );

			$this->ready = true;
			$this->set_sql_mode();
			$this->select( $this->dbname, $this->dbh );

			return true;
		}

		return false;
    }

    private function configure_ssl()
    {
		$ca_path = UCF_OEE__SSL_PATH . 'ca.pem';
		$ca_string = get_field( 'ucf_oee_database_ca_pem', 'option' );
		$success = file_put_contents( $ca_path, $ca_string );

		mysqli_options( $this->dbh, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true );
        mysqli_ssl_set( $this->dbh, null, null, $ca_path, NULL, NULL );
    }
}
