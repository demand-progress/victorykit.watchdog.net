<?php
/*
 * WP Stagecoach Version 1.4.0
 */

if ( ! defined('ABSPATH') ) {
	die('Please do not load this file directly.');
}
$_POST['wpsc-create-stageurl'] = strtolower( $_POST['wpsc-create-stageurl'] );
define( 'WPSTAGECOACH_REL_CONTENT',	preg_replace( '#' . get_home_path() . '#', '', WP_CONTENT_DIR ) );
define( 'WPSTAGECOACH_DOMAIN',		'.wpstagecoach.com' );
define( 'WPSTAGECOACH_LIVE_SITE',	$_POST['wpsc-create-liveurl'] );
define( 'WPSTAGECOACH_STAGE_SITE',	$_POST['wpsc-create-stageurl'] . WPSTAGECOACH_DOMAIN );
define( 'WPSTAGECOACH_LIVE_PATH',	rtrim( ABSPATH, '/' ) );
define( 'WPSTAGECOACH_STAGE_PATH',	'/var/www/' . $_POST['wpsc-create-stageurl'] . '/staging-site' );
define( 'WPSTAGECOACH_DB_FILE',		WPSTAGECOACH_TEMP_DIR . WPSTAGECOACH_STAGE_SITE . '.sql.gz' );
define( 'WPSTAGECOACH_TAR_FILE',	WPSTAGECOACH_TEMP_DIR . WPSTAGECOACH_STAGE_SITE . '.tar.gz' );
define( 'WPSTAGECOACH_TAR_NOGZ_FILE', WPSTAGECOACH_TEMP_DIR . WPSTAGECOACH_STAGE_SITE . '.tar' );

global $BINARY_FILE_LIST;
if( isset($_POST['wpsc-options']['no-hotlink']) && $_POST['wpsc-options']['no-hotlink'] == true ){	
	$BINARY_FILE_LIST = '';
} else {
	$BINARY_FILE_LIST = '\.jpg$|\.jpeg$|\.png$|\.gif$|\.svg$|\.bmp$|\.tif$|\.tiff$|\.pct$|\.pdf$|\.git$|\.mp3$|\.mp4$|\.m4a$|\.aac$|\.aif$|\.mov$|\.qt$|\.mpg$|\.mpeg$|\.wmv$|\.mkv$|\.avi$|\.mpa$|\.ra$|\.rm$|\.swf$|\.avi$|\.mpg$|\.mpeg$|\.flv$|\.swf$|\.gz$|\.sql$|\.tar$|\.log$|\.db$|\.123$|\.zip$|\.rar$|\.iso$|\.vcd$|\.toast$|\.bin$|\.hqx$|\.sit$|\.bak$|\.old$|\.psd$|\.psp$|\.ps$|\.ai$|\.rtf$|\.wps$|\.wpd$|\.dll$|\.exe$|\.wks$|\.msg$|\.mdb$|\.xls$|\.doc$|\.ppt$|\.xlsx$|\.docx$|\.pptx$|\.core$';
}

if( isset( $_POST['wpsc-options']['password-protect-user'] ) && empty( $_POST['wpsc-options']['password-protect-user'] ) ){
	unset( $_POST['wpsc-options']['password-protect-user'] );
}
if( isset( $_POST['wpsc-options']['password-protect-password'] ) && empty( $_POST['wpsc-options']['password-protect-password'] ) ){
	unset( $_POST['wpsc-options']['password-protect-password'] );
}
if( ! isset( $_POST['wpsc-options']['password-protect'] ) || empty( $_POST['wpsc-options']['password-protect'] ) ){
	// some password managers pre-fill these without the user knowing, or wanting them filled, so unless they also check the box, we're going  to unset them.
	unset( $_POST['wpsc-options']['password-protect-user'] );
	unset( $_POST['wpsc-options']['password-protect-password'] );
}

set_time_limit( 0 );
ini_set( 'max_execution_time', '0' );
ini_set( 'memory_limit', '-1' );
if( !isset( $wpsc ) ){
	$wpsc = get_option( 'wpstagecoach' );
}

/*******************************************************************************************
*                                                                                          *
*                               Beginning of stepping form                                 *
*                                                                                          *
*******************************************************************************************/
$wpscpost_fields = array(
	'wpsc-create-liveurl' => $_POST['wpsc-create-liveurl'],
	'wpsc-create-stageurl' => $_POST['wpsc-create-stageurl'],
	'wpsc-create' => $_POST['wpsc-create'],
);
if( !empty($_POST['wpsc-options']) ){
	$wpscpost_wpsc_options = $_POST['wpsc-options'];
}


if( !isset( $_POST['wpsc-step'] ) ){
	$_POST['wpsc-step'] = 1;
} else {
	if(  !isset( $wpsc['disable-step-nonce'] ) && ! wpsc_check_step_nonce( 'create', $_POST['wpsc-nonce'] ) ){
		return false;
	}
}


// little bit of sanity with our post variables
if( !isset( $_POST['wpsc-create-liveurl'] ) || $_POST['wpsc-create-liveurl'] != preg_replace('#https?://#', '', site_url() ) ){
	$errmsg  = __( "You can't create a staging site for another domain.", 'wpstagecoach' ) . '<br/>';
	$errmsg .= sprintf( __( 'Your site is: "%s", but you sent me: "%s".', 'wpstagecoach' ), preg_replace('#https?://#', '', site_url() ),  filter_var( $_POST['wpsc-create-liveurl'], FILTER_SANITIZE_SPECIAL_CHARS ) );
	wpsc_display_error( $errmsg );
	return;
}

if( WPSC_DEBUG ){
	if( $_POST['wpsc-step'] == 1 && is_file( WPSTAGECOACH_TEMP_DIR . '/create_debug.log' ) ){
		rename( WPSTAGECOACH_TEMP_DIR . '/create_debug.log', WPSTAGECOACH_TEMP_DIR . '/create_debug.log.old' );
	}
	if( $create_log = fopen( WPSTAGECOACH_TEMP_DIR . '/create_debug.log', 'a') ){
		echo 'DEBUG: successfully opened log file "' . WPSTAGECOACH_TEMP_DIR . '/create_debug.log".<br/>'; 
	} else {
		wpsc_display_error( 'Error: could not open debug log file "' . WPSTAGECOACH_TEMP_DIR . '/create_debug.log"' );
	}
	echo '<pre>';
	print_r($_POST);
	echo '</pre>';
	echo str_pad( '', 65536 ) . PHP_EOL;
	ob_flush();
	flush();
	sleep(1);
}

$normal=true;

################################################################################################################################

		   #
		  ##
		 # #
		   #
		   #
		   #
		 #####

################################################################################################################################

if( $_POST['wpsc-step'] == 1 ){
	echo '<p>' . __( 'Step 1: Setting up and doing sanity checks.', 'wpstagecoach' ) . '</p>';
	echo '<p>' . __( sprintf( '(This should generally take around %d %s)', 15, 'seconds' ), 'wpstagecoach' ) . '</p>';

	if( WPSC_DEBUG ) fwrite( $create_log, '$create_debug_log[\'version\'] = "' . WPSTAGECOACH_VERSION . '";' . PHP_EOL);


	//	sanity checks
	if( !ctype_alnum( $_POST['wpsc-create-stageurl'] ) ){
		$errmsg = __( 'Sorry, you may only use alphanumeric characters in the subdomain name.', 'wpstagecoach' );
		wpsc_display_create_form( 'wpstagecoach.com', wpsc_display_error( $errmsg ), $wpsc );
		return;
	}
	if( WPSC_DEBUG ) fwrite( $create_log, '$create_debug_log[\'sanity\'][\'info\'] = "alphanumeric chars good";' . PHP_EOL);

	if( ctype_digit( $_POST['wpsc-create-stageurl'] ) ){
		$errmsg = __( 'Sorry, you must include letters in the subdomain name.', 'wpstagecoach' );
		wpsc_display_create_form( 'wpstagecoach.com', wpsc_display_error( $errmsg ), $wpsc );
		return;
	}
	if( WPSC_DEBUG ) fwrite( $create_log, '$create_debug_log[\'sanity\'][\'info\'] = "all digits good";' . PHP_EOL);

	if( isset( $_POST['wpsc-options']['password-protect'] ) || 
		isset( $_POST['wpsc-options']['password-protect-user'] ) ||
		isset( $_POST['wpsc-options']['password-protect-password'] )
	){
		if( isset( $_POST['wpsc-options']['password-protect'] ) && !empty( $_POST['wpsc-options']['password-protect'] ) ){
			if( empty( $_POST['wpsc-options']['password-protect-user'] ) || empty( $_POST['wpsc-options']['password-protect-password'] ) ){
				$errmsg = __( 'You must specify both a user and a password if you want to password-protect your staging site.', 'wpstagecoach' );
				wpsc_display_create_form( 'wpstagecoach.com', wpsc_display_error( $errmsg ), $wpsc );
				return;
			}
			if( WPSC_DEBUG ) fwrite( $create_log, '$create_debug_log[\'sanity\'][\'info\'] = "password protection good";' . PHP_EOL);
		}
		if( !ctype_alnum( $_POST['wpsc-options']['password-protect-user'] ) ||
			!ctype_alnum( $_POST['wpsc-options']['password-protect-password'] )
		){
			$errmsg = __( 'Sorry, but you may only use letters and numbers for the username and password.', 'wpstagecoach' );
			wpsc_display_create_form( 'wpstagecoach.com', wpsc_display_error( $errmsg ), $wpsc );
			return;
		}
	}
 
	// gather a little bit of information so we can set up the staging site on the proper server environment
	global $wp_version;
	$DB_HOST = explode(':', DB_HOST);

	if( WPSC_DEBUG ) fwrite( $create_log, '$create_debug_log[\'sanity\'][\'wpver\'] = "' . $wp_version . '";' . PHP_EOL);

	if(isset($DB_HOST[1]) ){
		if( ctype_digit( $DB_HOST[1] ) ){ // it is only digits, therefore, hopefully it is a port
			$db = mysqli_connect($DB_HOST[0], DB_USER, DB_PASSWORD, DB_NAME, $DB_HOST[1]); // these are defined in wp-config.php
		} else { // it has alpha characters, so it is likely a socket.
			$db = mysqli_connect($DB_HOST[0], DB_USER, DB_PASSWORD, DB_NAME, NULL, $DB_HOST[1]); // these are defined in wp-config.php
		}
	} else {
		$db = mysqli_connect($DB_HOST[0], DB_USER, DB_PASSWORD, DB_NAME); // these are defined in wp-config.php
	}

	if( WPSC_DEBUG ) fwrite( $create_log, '$create_debug_log[\'sanity\'][\'dbhost\'] = json_decode( \'' . json_encode( $DB_HOST ) . '\', true );' . PHP_EOL);


	$mysql_server_info = $db->server_info;
	$mysql_server_ver = $db->server_version;
	$mysql_client_info = mysqli_get_client_info();
	$mysql_client_ver = mysqli_get_client_version();
	$db->close();

	if( WPSC_DEBUG ) fwrite( $create_log, '$create_debug_log[\'sanity\'][\'dbinfo\'][\'server_info\'] = "' . $mysql_server_info . '";' . PHP_EOL);
	if( WPSC_DEBUG ) fwrite( $create_log, '$create_debug_log[\'sanity\'][\'dbinfo\'][\'server_ver\'] = "' . $mysql_server_ver . '";' . PHP_EOL);
	if( WPSC_DEBUG ) fwrite( $create_log, '$create_debug_log[\'sanity\'][\'dbinfo\'][\'client_info\'] = "' . $mysql_client_info . '";' . PHP_EOL);
	if( WPSC_DEBUG ) fwrite( $create_log, '$create_debug_log[\'sanity\'][\'dbinfo\'][\'client_ver\'] = "' . $mysql_client_ver . '";' . PHP_EOL);

	//	do some basic sanity checks, and make sure the staging site is created on the best server for it.
	$post_url = WPSTAGECOACH_CONDUCTOR.'/wpsc-sanity-check.php';
	$post_details = array(
		'wpsc-user'			=> $wpsc['username'],
		'wpsc-key'			=> $wpsc['apikey'],
		'wpsc-ver'			=> WPSTAGECOACH_VERSION,
		'wpsc-live-site'	=> WPSTAGECOACH_LIVE_SITE,
		'wpsc-stage-site'	=> WPSTAGECOACH_STAGE_SITE,
	);
	$post_options = array(
		'wp-ver'		=> $wp_version,
		'php-ver'		=> phpversion(),
		'mysqls-ver'	=> $mysql_server_ver,
		'mysqls-info'	=> $mysql_server_info,
		'mysqlc-ver'	=> $mysql_client_ver,
		'mysqlc-info'	=> $mysql_client_info,
	);
	if( WPSC_DEBUG ) fwrite( $create_log, '$create_debug_log[\'sanity\'][\'initial_post_options\'] = json_decode( \'' . json_encode( $post_options ) . '\', true );' . PHP_EOL);

	if( defined( 'DB_CHARSET' ) ){
		$post_options['charset'] = DB_CHARSET;
	} else {
		$post_options['charset'] = 'NA';
	}
	if( WPSC_DEBUG ) fwrite( $create_log, '$create_debug_log[\'sanity\'][\'dbcharset\'] = "' . $post_options['charset'] . '";' . PHP_EOL);

	if( isset( $_SERVER['SERVER_SOFTWARE'] ) && !empty( $_SERVER['SERVER_SOFTWARE'] ) ){
		$post_options['server-info'] = $_SERVER['SERVER_SOFTWARE'];
	} else {
		$post_options['server-info'] = 'NA';
	}

	if ( function_exists('curl_version') ){
		$curl_info = curl_version();
		$post_options['curl-ver'] = $curl_info['version'];
		$post_options['curl-host'] = $curl_info['host'];
		$post_options['curl-ssl-ver'] = $curl_info['ssl_version'];
		$post_options['curl-libz-ver'] = $curl_info['libz_version'];
	} else {
		$post_options['curl-ver'] = 'NA';
	}


	if( stripos( site_url(), 'https' ) !== false || stripos( get_option('siteurl'), 'https' ) !== false || isset($_SERVER['HTTPS']) ){
		$post_options['uses-ssl'] = true;
	} else {
		$post_options['uses-ssl'] = false;
	}
	if( isset($_POST['wpsc-options']['password-protect']) && !empty($_POST['wpsc-options']['password-protect']) ){
		$post_options['password-protect-user'] = $_POST['wpsc-options']['password-protect-user'];
		$post_options['password-protect-password'] = $_POST['wpsc-options']['password-protect-password'];
	}


	if( WPSC_DEBUG ) fwrite( $create_log, '$create_debug_log[\'sanity\'][\'end_post_options\'] = json_decode( \'' . json_encode( $post_options ) . '\', true );' . PHP_EOL);

	$post_details['wpsc-options'] = $post_options;

	$post_args = array(
		'timeout' => 120,
		'httpversion' => '1.1',
		'body' => $post_details
	);


	if( WPSC_DEBUG ){
		fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'sanity_post\'] = json_decode( \'' . json_encode( $post_details ) . '\', true );' . PHP_EOL);
	}



	// do some SSL sanity
	if( !isset($wpsc_sanity['https']) ){
		$wpsc_sanity['https'] = wpsc_ssl_connection_test();
	}
	if( $wpsc_sanity['https'] == 'NO_CA' ){
	 	$post_args['sslverify'] = false;
	} elseif( $wpsc_sanity['https'] == 'NO_CURL' ) {
		add_filter('use_curl_transport', '__return_false');
	}



	
	$post_result = wp_remote_post($post_url, $post_args );
	$result = wpsc_check_post_info('check_if_site_exists', $post_url, $post_details, $post_result, false) ; // check response from the server


	if( $result['result'] != 'OK' ){
		wpsc_display_error( print_r($result['info'],true) );
		return false;
	} else {
		if( is_string($result['info']['dest']) && strlen($result['info']['dest']) < 42 ){
			$wpscpost_wpsc_options['dest'] = $result['info']['dest'];
			echo '<p>' . __( 'Okay, the staging site name is good, and we know where to install it.', 'wpstagecoach' ) . '</p>';
		} else {
			$errmsg  = __('We did not get a valid staging server from the conductor. Please contact WP Stagecoach support with the following information:', 'wpstagecoach' );
			$errmsg .= print_r($result['info'],true);
			wpsc_display_error( $errmsg );
			return false;
		}
	}	

	echo str_pad( '', 65536 ) . PHP_EOL;
	ob_flush();
	flush();



	###   if old files are still around, delete them!
	if( file_exists( WPSTAGECOACH_DB_FILE ) ) unlink( WPSTAGECOACH_DB_FILE );
	if( file_exists( WPSTAGECOACH_TAR_FILE ) ) unlink( WPSTAGECOACH_TAR_FILE );

	global $wpdb;

	$db_size_query = 'SELECT sum( data_length + index_length ) FROM information_schema.TABLES where table_schema="'.DB_NAME.'";';
	$db_size = $wpdb->get_row( $db_size_query, ARRAY_N );
	$db_size = array_shift( $db_size );
	echo __( 'Database size: ', 'wpstagecoach' ) . wpsc_size_suffix( $db_size ) . '<br/>';


	if( WPSC_DEBUG ){
		fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'database_size\'] = "' . $db_size . '";' . PHP_EOL);
		fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'status\'] = "done";' . PHP_EOL );
	}

	echo str_pad( '', 65536 ) . PHP_EOL;
	ob_flush();
	flush();

	$nextstep = 2;


} // end of step 1

################################################################################################################################

         #####
        #     #
              #
         #####
        #
        #
        #######

################################################################################################################################

if( isset($_POST['wpsc-step']) && $_POST['wpsc-step'] == 2 ){
	echo '<p>' . sprintf( __('Step %s: creating database file.', 'wpstagecoach' ), $_POST['wpsc-step'] ) . '</p>';


	if( isset( $_POST['wpsc-step-again']) && 1 == $_POST['wpsc-step-again'] ){
		if( WPSC_DEBUG ) echo 'Stepping back into the Database dump.<br/>' . PHP_EOL;
		$first_step = false;
	} else {
		$first_step = true;
		if( WPSC_DEBUG ){
			echo 'First step into the Database dump.<br/>' . PHP_EOL;
			fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'first_step\'] = true;' . PHP_EOL );
		}
	}
	global $wpdb;


	if( isset( $_POST['wpsc-dump-done'] ) && 1 == $_POST['wpsc-dump-done'] ){
		$is_done = true;
	} else {
		$is_done = false;
	}


	// get the current DB host, including any ports it might use.
	$DB_HOST = explode(':', DB_HOST);
	if(isset($DB_HOST[1]) ){
		if( ctype_digit( $DB_HOST[1] ) ){ // it is only digits, therefore, hopefully it is a port
			$db = mysqli_connect($DB_HOST[0], DB_USER, DB_PASSWORD, DB_NAME, $DB_HOST[1]); // these are defined in wp-config.php
		} else { // it has alpha characters, so it is likely a socket.
			$db = mysqli_connect($DB_HOST[0], DB_USER, DB_PASSWORD, DB_NAME, NULL, $DB_HOST[1]); // these are defined in wp-config.php
		}
	} else {
		$db = mysqli_connect($DB_HOST[0], DB_USER, DB_PASSWORD, DB_NAME); // these are defined in wp-config.php
	}

	if( mysqli_errno( $db ) ) {
		$errmsg = __( 'Couldn\'t connect to database', 'wpstagecoach' ) . DB_NAME . __( 'on host', 'wpstagecoach' ) . DB_HOST . __( '. This should never happen.  Error: ', 'wpstagecoach' ) . mysqli_connect_error();
		wpsc_display_error( $errmsg );
		return;
	}


	if( isset( $_POST['wpsc-force-utf8'] ) && $_POST['wpsc-force-utf8'] == true ){
		$wpscpost_fields['wpsc-force-utf8'] = true;
		define( 'DB_CHARSET', 'utf8' );
	} elseif( isset( $_POST['wpsc-force-charset'] ) && ctype_alnum( $_POST['wpsc-force-charset'] ) ){
		$charset = $_POST['wpsc-force-charset'];
		$wpscpost_fields['wpsc-force-charset'] = $charset;
		define('DB_CHARSET', $charset);
	}

	// get and report the size of the database
	$db_size_query = 'SELECT sum( data_length + index_length ) FROM information_schema.TABLES where table_schema="' . DB_NAME . '";';
	$res = mysqli_query( $db, $db_size_query );
	$temp = mysqli_fetch_row( $res );
	$db_size = array_shift( $temp );
	mysqli_free_result( $res );

	echo __( 'Database size: ', 'wpstagecoach' ) . wpsc_size_suffix( $db_size ) . '<br/>';
	echo str_pad( '', 65536 ) . PHP_EOL;
	ob_flush();
	flush();





	//  make array of tables & get number of rows in database tables as well as total rows
	$tables = array(); // our list and type of tables
	$table_sizes = array();  // our list of tables with number of rows in each
	$total_rows = 0;  //  our total rows from all tables
	$tables_res = $db->query( 'SHOW FULL TABLES;' );
	// get table sizes
	while( $row = $tables_res->fetch_array( MYSQLI_NUM ) ){
		$table_name = $row[0];
		$table_type = $row[1];
		$tables[ $table_name ] = $table_type;

		//  get the number of tables
		$query = 'select count(*) as num_rows from `' . $table_name . '`;';
		$rows_res = $db->query( $query );
		if( ! $rows_res ){
			$errmsg  = __( 'There was an error in counting your tables\'s rows.' . '<br/>' . PHP_EOL );
			$errmsg .= 'Error: ' . $db->error . ' (' . $db->errno . ')' . '<br/>' . PHP_EOL;
			$errmsg .= 'query: ' . $query . '<br/>' . PHP_EOL;
			wpsc_display_error( $errmsg );
			return;
		}
		$table_rows = $rows_res->fetch_array( MYSQLI_ASSOC );
		$rows_res->free();
		$table_rows = $table_rows['num_rows'];
		$table_sizes[ $table_name ] = $table_rows;

		if( 'VIEW' != $table_type ){
			$total_rows += $table_rows;
		}
	}
	$tables_res->free();



	// check for valid charset
	if( ! defined( 'DB_CHARSET' ) && true == $first_step){

		$char_tables = $wpdb->get_results('SHOW tables', ARRAY_N);

		// check over all the tables and see if they all have the same charset, if so, use that, if not display an error
		foreach ($char_tables as $table) {
			$res = $wpdb->get_results( 'SHOW CREATE TABLE ' . $table[0], ARRAY_N );
			$res = $res[0][1];
			$pos = strpos( $res, 'CHARSET' );
			$substr = ltrim( substr( $res, ( $pos+7 ) ) ); // ltrim to remove any potential spaces
			$substr = ltrim( substr( $substr, 1 ) ); // remove =
			if( ! isset( $charset ) ){
				$charset = $substr;
			} elseif( $substr != $charset ) {
				$normal = false;
				?>

				<div class="wpstagecoach-error">
					<p><?php _e( 'Your Database Character Set (DB_CHARSET) is not defined in your <b>wp-config.php</b> file as is the standard for WordPress.', 'wpstagecoach' ); ?></p>
					<p><?php _e( 'Unfortunately, WP Stagecoach was not able to determine the character set used in your database.  We found conflicting results between tables.', 'wpstagecoach' ); ?></p>
					<p><?php _e( 'You will need to find what character set your database uses and set it in your wp-config.php file. See the <a href="http://codex.wordpress.org/Editing_wp-config.php#Database_character_set">WordPress Codex</a> for more information.', 'wpstagecoach' ); ?></p>
					<p><?php _e( 'Here is a list of each table\'s character set:', 'wpstagecoach' ); ?>
						<?php foreach ( $tables as $table => $type ) {
							$query = 'SHOW CREATE TABLE ' . $table;
							$res = $wpdb->get_results( $query, ARRAY_N );
							$res = $res[0][1];
							$pos = strpos($res, 'CHARSET');
							$substr = substr($res, $pos ); 
							echo '<li>' . __( 'Table: ', 'wpstagecoach' ). $table.' '.$substr.'</li>';
						} ?></p>
					<p><?php _e( 'If you can not determine what to do, you can contact <a href="https://wpstagecoach.com/support">WP Stagecoach support</a>, and we may be able to help.', 'wpstagecoach' ); ?>
				</div>

				<p><?php _e( 'Because WordPress has used the UTF-8 character set since version 2.2, you may wish to just create all tables on the staging site with the UTF-8 character set.  However, this might cause some character encoding problems if you import your database changes back from the staging site.', 'wpstagecoach' ); ?></p>

			 	<form method="POST" action="admin.php?page=wpstagecoach">
					<input name="wpsc-stop" type="submit" class="button submit-button" value="<?php _e( 'Don\'t create a staging site right now; you can go investigate your database situation.', 'wpstagecoach' ); ?>">
				</form>

			 	<form method="POST" id="wpsc-step-form" class="wpstagecoach-update-step-nonce-form">
				<?php
				$wpscpost_fields['wpsc-force-utf8'] = true;
				$nextstep = 2;
				$wpsc_nonce = wpsc_set_step_nonce( 'create', $nextstep );   // set a transient with next-step and a nonce
				echo '  <input type="hidden" name="wpsc-nonce" id="wpsc-nonce" value="' . $wpsc_nonce . '"/>' . PHP_EOL;
				echo '  <input type="hidden" name="wpsc-type" value="create"/>' . PHP_EOL;
				echo '<input name="goaheadwithutf8" type="submit" class="button submit-button wpstagecoach-update-step-nonce" value="' . __( 'Go ahead and create a staging with with UTF-8 encoding', 'wpstagecoach' ) . '">' . PHP_EOL;
				echo end_stepping_form( $nextstep, $wpscpost_fields, $wpscpost_wpsc_options, $normal );

				return; // need to stop here.

			}	
		}
		unset( $res );
		unset( $char_tables );
		unset( $table );
		unset( $pos );
		define( 'DB_CHARSET', $charset );
		$wpscpost_fields['wpsc-force-charset'] = $charset;

	} // end of first time DB_CHARSET check


	//  get number of rows per step from options, default or slow creation
	if( isset( $wpsc['advanced-create-mysql-rows-per-step'] ) && is_numeric( $wpsc['advanced-create-mysql-rows-per-step'] ) ){
		$rows_per_step = $wpsc['advanced-create-mysql-rows-per-step'];
	} elseif( isset( $wpsc['slow'] ) && true == $wpsc['slow'] ){
		$rows_per_step = 3000;
	} else {
		$rows_per_step = 100000;
	}
	if( WPSC_DEBUG ) echo '&nbsp;&nbsp;' . __( 'Rows per step: ', 'wpstagecoach' )  . number_format( $rows_per_step ) . '<br/>' . PHP_EOL;




	//  check if we need to step multiple times
	if( $total_rows < $rows_per_step ){ // we do this so we get at least one table per step
		$tables_to_dump = $tables;
		$tables_left_to_dump = array();
	} else {
		// we have to figure out which tables we're going to dump this time, and which tables we'll dump in subsequent steps
		echo '&nbsp;&nbsp;' . __( 'There are too many rows (' . number_format( $total_rows ) . ') for us to write the database all at once - going to split into multiple steps.', 'wpstagecoach' )  . '<br/>' . PHP_EOL;
		if( WPSC_DEBUG ) fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'database\'][\'rows_per_step\'] = ' . $rows_per_step . ';' . PHP_EOL);

		$wpscpost_fields['wpsc-step-again'] = 1;

		//   if we have tables left over from a previous step, we need to just find the size of them
		if( isset( $_POST['wpsc-tables-left'] ) ){
			if( WPSC_DEBUG ) echo '&nbsp;&nbsp;' . __( 'Getting remaining tables from POST.', 'wpstagecoach' )  . '<br/>' . PHP_EOL;
			$tables_left_to_dump = json_decode( base64_decode( $_POST['wpsc-tables-left'] ), true );

			if( ! is_array( $tables_left_to_dump ) ){  // couldn't decode the array from POST
				$errmsg  = __( 'We could not decipher the list of tables left to dump - something unexpected happened during the POST process.', 'wpstagecoach' ) . PHP_EOL;
				$errmsg .= __( 'Please <a target="_blank" href="https://wpstagecoach.com/support/">contact WP Stagecoach support</a> and give them this information','wpstagecoach' ) . PHP_EOL;
				$errmsg .= '$_POST: ' . print_r( $_POST, true );
				wpsc_display_error( $errmsg );
				return false;
			}

			// need to scrap the above total rows and get the total rows from current set of tables
			$total_rows = 0;
			foreach ( $tables_left_to_dump as $table => $type ) {
				$total_rows += $table_sizes[ $table ];
			}

			//  if it is less than we are going to do this step, we need to change the step-again setting
			if( $total_rows < $rows_per_step ){
				$wpscpost_fields['wpsc-step-again'] = 0;
			}

		} else {  // if we don't have wpsc-tables-left in POST, this is our first time through
			if( WPSC_DEBUG ) echo '&nbsp;&nbsp;' . __( 'This is our first time through the tables.', 'wpstagecoach' )  . '<br/>' . PHP_EOL;
			$tables_left_to_dump = $tables;
		}


		$this_step_total_rows = 0;
		$total_rows_left = $total_rows;

		foreach ( $tables_left_to_dump as $table => $table_type ) {
			if( 'VIEW' != $table_type ){


				$this_step_total_rows += $table_sizes[ $table ];
				$total_rows_left -= $table_sizes[ $table ];
				$tables_to_dump[ $table ] = $tables_left_to_dump[ $table ];
				unset( $tables_left_to_dump[ $table ]);

				if( WPSC_DEBUG ) echo '&nbsp;&nbsp;' . 'Adding table "' . $table . '" with ' . number_format( $table_sizes[ $table ] ) . ' rows. ' . number_format( $this_step_total_rows ) . ' rows so far this step.' . '<br/>';

				// if( WPSC_DEBUG ) echo '&nbsp;&nbsp;' . 'Checking table "' . $table . '" for size.  ' . $this_step_total_rows . ' rows this step.<br/>';
				if( $this_step_total_rows > $rows_per_step ){
					if( WPSC_DEBUG ) echo '&nbsp;&nbsp;' . 'Last table: ' . $table . '<br/>';
					break;
				}

			} else { // end if VIEW
				unset( $tables_left_to_dump[ $table ] );
			}
		} // end foreach over $tables_left_to_dump

		if( WPSC_DEBUG ){ // print some information about what tables are being dumped

			$pretty_tables_to_dump = '';
			foreach ($tables_to_dump as $table => $type ) {
				$pretty_tables_to_dump .= $table .', ';
			}
			echo '&nbsp;&nbsp;' . __( 'Dumping these tables on this step: ', 'wpstagecoach' ) . $pretty_tables_to_dump . '<br/>' . PHP_EOL;
			$pretty_tables_left_to_dump = '';
			foreach ($tables_left_to_dump as $table => $type ) {
				$pretty_tables_left_to_dump .= $table .', ';
			}
			echo '&nbsp;&nbsp;' . __( 'Adding remaining tables to next step\'s POST: ', 'wpstagecoach' ) . $pretty_tables_left_to_dump . '<br/>' . PHP_EOL;

		}

		$wpscpost_fields['wpsc-tables-left'] = base64_encode( json_encode( $tables_left_to_dump ) );
	} // end of if ( $total_rows < $rows_per_step ) check (putting the remaining tables into the next POST)



	// things to run only on the first time we go through the dump (find charset, write dump headers)
	if( true == $first_step ){

		if( WPSC_DEBUG ){
			fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'database\'][\'tables\'] = json_decode(\'' . json_encode( $tables ) . '\', true );' . PHP_EOL);
			fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'database\'][\'rows_per_step\'] = ' . $rows_per_step . ';' . PHP_EOL);
		}

		//	create new gzip'd dump file
		$db_fh = gzopen( WPSTAGECOACH_DB_FILE, 'w' );

		if( WPSC_DEBUG ){
			fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'database\'][\'sql_file\'] = "opened, ";' . PHP_EOL );
		}
		$db_header = '-- Dump of '. DB_NAME . PHP_EOL;
		$db_header .= '-- Server version '. mysqli_get_server_info($db) . PHP_EOL . PHP_EOL;
		$db_header .= '/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;';
		$db_header .= '/*!40101 SET NAMES ' . DB_CHARSET . ' */;'.PHP_EOL;
		$db_header .= '/*!40101 SET CHARACTER_SET_CLIENT = ' . DB_CHARSET . ' */;'.PHP_EOL;
		$db_header .= '/*!40103 SET TIME_ZONE=\'+00:00\' */;';
		$db_header .= '/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;';
		$db_header .= '/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;';
		$db_header .= '/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE=\'NO_AUTO_VALUE_ON_ZERO\' */;';
		$db_header .= '/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;';
		$db_header .= PHP_EOL;

		if( WPSC_DEBUG ){
			fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'database\'][\'sql_file\'] .= "header created, ";' . PHP_EOL);
		}

		$ret = fwrite( $db_fh, $db_header );
		if( !$ret ){ // unsuccessful write (0 bytes written)
			$errmsg  = __( 'Error: I couldn\'t write to the file "', 'wpstagecoach' ) . basename(WPSTAGECOACH_DB_FILE) . __( '" in the directory "', 'wpstagecoach' ) . WPSTAGECOACH_TEMP_DIR . '".<br/>' . PHP_EOL;
			$errmsg .= __( 'Please check your site\'s file and directory permissions for the above directory.', 'wpstagecoach' ) . PHP_EOL;
			wpsc_display_error( $errmsg );
			return;
		}
		if( WPSC_DEBUG ){
			fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'database\'][\'sql_file\'] .= "header written, ";' . PHP_EOL);
		}

	} else {  //  end of first_step only
		//	append to our gzip'd dump file
		$db_fh = gzopen( WPSTAGECOACH_DB_FILE, 'a' );
	} // end of ! first_step



	// check that we do really have a valid charset we can load!
	if ( ! mysqli_set_charset( $db, DB_CHARSET ) ) {
		$errmsg  = '<p>' . __( 'Error loading character set: ', 'wpstagecoach' ) . DB_CHARSET . ': ' . mysqli_error( $db ) . '</p><p>';
		$errmsg .= '<p>' . __( 'Please determine what character set your database uses and contact <a href="https://wpstagecoach.com/support" target="_blank">WP Stagecoach support</a>', 'wpstagecoach' ) . '</p>';
		wpsc_display_error( $errmsg );
		return;
	}



	// mention once that we're using smaller iterations or custom iterations

	if( isset( $wpsc['advanced-create-mysql-custom-iterations-sizes'] ) && is_array( $wpsc['advanced-create-mysql-custom-iterations-sizes'] ) ){
		if( WPSC_DEBUG ) echo '&nbsp;&nbsp;' . sprintf( __( 'We are using a custom number of iterations to dump table "%s"', 'wpstagecoach' ), $table ) . '<br/>' . PHP_EOL;
	} elseif( isset( $wpsc['slow'] ) && WPSC_DEBUG ) {
		echo '&nbsp;&nbsp;' . __('We are using a smaller number of iterations between writing to the file for the sql file to help make the database dump more reliable on low memory servers', 'wpstagecoach' ) . '<br/>' . PHP_EOL;
	}






	// go through tables, row by row
	foreach ($tables_to_dump as $table => $table_type) {
		if( isset( $wpsc['advanced-create-mysql-bypass-table-list'][ $table ] ) ){
			if( WPSC_DEBUG ) echo '&nbsp;&nbsp;' . sprintf( __( 'We are bypassing the table "%s" as per your settings in the Advanced menu', 'wpstagecoach' ), $table ) . '<br/>' . PHP_EOL;
			continue;
		}
		if( $table_type == 'VIEW' ){
			//  currently we just skip view tables
			continue;
		}

		//	get number of rows in table
		$query = 'select count(*) from `' . $table . '`;';
		$res = mysqli_query( $db, $query );
		$result = mysqli_fetch_row( $res );
		if( is_array($result) ){
			$num_rows = array_shift( $result );
		} else {
			$errmsg = '<p>' . __( 'Warning: could not count rows in "' . $table . '". bad result.', 'wpstagecoach' ) . '</p>';
			if( ! empty( $result ) ){
				$errmsg .= 'result: "<pre>' . print_r( $result, true ) . '</pre>"';
			}
			if( ! empty( $db->error ) ){
				$errmsg .= '<br/>Error: <pre>' . $db->error . '</pre>';
			}
			wpsc_display_error( $errmsg );
			echo str_pad( '', 65536 ) . PHP_EOL;
			ob_flush();
			flush();
			sleep(1);
			$num_rows = '(NA)';
		}
		mysqli_free_result($res);


		if( WPSC_DEBUG ){
			fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'database\'][\'' . $table . '_size\'] = "' . $num_rows . '";' . PHP_EOL);
		}


		// add table structure + size
		$table_dump = '--'.PHP_EOL.'-- Table structure for table `'.$table.'`, size '.$num_rows.' rows'.PHP_EOL.'--'.PHP_EOL.PHP_EOL;
		// add table dropping
		$table_dump .= 'DROP TABLE IF EXISTS `'.$table.'`;'.PHP_EOL;
		// add table creation
		$query = 'show create table `' . $table . '`;';
		$res = mysqli_query($db, $query);
		$result = mysqli_fetch_row($res);
		if( !is_array( $result ) ){
			$errmsg = __( 'Error: $result is not an array!<br/>query: ', 'wpstagecoach' ) . $query . '</br>';
			if( ! empty( $result ) ){
				$errmsg .= 'result: "<pre>' . print_r( $result, true ) . '</pre>"';
			}
			if( ! empty( $db->error ) ){
				$errmsg .= '<br/>Error: <pre>' . $db->error . '</pre>';
			}
			wpsc_display_error( $errmsg );
			return false;
		}
		$table_dump .= array_pop( $result ) . ';' . PHP_EOL;
		if( strpos( $table_dump, 'ENGINE=MyISAM') ){
			$ismyisam = true;
		}
		mysqli_free_result( $res );


		$table_dump .= PHP_EOL.'--'.PHP_EOL.'-- Dumping data for table `'.$table.'`'.PHP_EOL.'--'.PHP_EOL.PHP_EOL;
		$table_dump .= 'LOCK TABLES `'.$table.'` WRITE;'.PHP_EOL;
		if( isset($ismyisam) ) {
			$table_dump .= '/*!40000 ALTER TABLE `'.$table.'` DISABLE KEYS */;'.PHP_EOL;
		}

		// write out what we have so far to disk.
		fwrite( $db_fh, $table_dump );
		// clean up the dump variable
		$table_dump = '';

		// select everything from each table, in $rows_from_mysql (50) row chunks
		$query_base = 'select * from `' . $table . '` limit ';


		//  adjust the number of iterations for dumping each table
		if( isset($wpsc['slow']) ){
			$rows_from_mysql   = 5; // number of rows we get at once from the database server
			$num_of_iterations = 1; // number of rows we write out at once to the database file
		} else {
			$rows_from_mysql   = 500; // number of rows we get at once from the database server
			$num_of_iterations = 20; // number of rows we write out at once to the database file
		}
		if( isset( $wpsc['advanced-create-mysql-custom-iterations-sizes'] ) && is_array( $wpsc['advanced-create-mysql-custom-iterations-sizes'] ) ){
			$rows_from_mysql   = $wpsc['advanced-create-mysql-custom-iterations-sizes']['mysql_rows']; // number of rows we get at once from the database server
			$num_of_iterations = $wpsc['advanced-create-mysql-custom-iterations-sizes']['iterations']; // number of rows we write out at once to the database file
		}
		if( ctype_digit( $num_rows ) && $num_rows > 50000 && ! isset( $wpsc['mysql-dont-use-big-iterations'] ) ){
			if( WPSC_DEBUG ) echo '&nbsp;&nbsp;' . sprintf( __( 'We are using a larger number of iterations to dump table "%s"', 'wpstagecoach' ), $table ) . '<br/>' . PHP_EOL;
			$rows_from_mysql   = 10000; // number of rows we get at once from the database server
			$num_of_iterations = 1000; // number of rows we write out at once to the database file
		}
		if( ctype_digit( $num_rows ) && $num_rows > 500000 && ! isset( $wpsc['mysql-dont-use-big-iterations'] ) ){
			if( WPSC_DEBUG ) echo '&nbsp;&nbsp;' . sprintf( __( 'We are using a larger number of iterations to dump table "%s"', 'wpstagecoach' ), $table ) . '<br/>' . PHP_EOL;
			$rows_from_mysql   = 100000; // number of rows we get at once from the database server
			$num_of_iterations = 1000; // number of rows we write out at once to the database file
		}
		if( ctype_digit( $num_rows ) && $num_rows > 1000000 && ! isset( $wpsc['mysql-dont-use-big-iterations'] ) ){
			if( WPSC_DEBUG ) echo '&nbsp;&nbsp;' . sprintf( __( 'We are using a larger number of iterations to dump table "%s"', 'wpstagecoach' ), $table ) . '<br/>' . PHP_EOL;
			$rows_from_mysql   = 500000; // number of rows we get at once from the database server
			$num_of_iterations = 1000; // number of rows we write out at once to the database file
		}




		echo '&nbsp;&nbsp;' . sprintf( __( 'dumping table %s, with %d rows.', 'wpstagecoach' ), $table, $num_rows ) . '<br/>' . PHP_EOL;
		echo str_pad( '', 65536 ) . PHP_EOL;
		ob_flush();
		flush();

		$done = false;
		$curr_row = 0;
		$rows_left = $num_rows;
		while( ! $done ){



			// get each row
			$query = $query_base . ' ' . $curr_row . ', ' . $rows_from_mysql . ';';
			$res = mysqli_query( $db, $query );

			if( $num_rows > 0 ){
				$i=0;
				while ( $i < $rows_from_mysql && $i < $rows_left ) {
					$table_dump .= 'INSERT INTO `' . $table . '` VALUES ';
					$j=0;
					while ( ( $j < $num_of_iterations && $j < $rows_left ) && ( $i < $rows_from_mysql && $i < $rows_left ) ) { 

						$row = mysqli_fetch_row( $res );
						if( ! is_array( $row ) ){
							$errmsg = '<p>' . __( 'Error: ' . $row . ' is not a useful value!<br/>Query: ', 'wpstagecoach' ) . '</p>' . $query . '<pre>' . print_r( $row, true ) . '</pre>';
							$errmsg .= '<br/>information: <pre>' . $db->error . '</pre>';
							if( ! isset( $wpsc['mysql-dont-use-big-iterations'] ) ){
								$errmsg .= '<br/>You may need to disable WP Stagecoach\'s automatic optimization for larger tables to successfully create the database file.<br/>';
								$errmsg .= 'To do so, go to WP Stagecoach -> Settings, and check the "Enable Advanced Options?" and update.  ';
								$errmsg .= 'Next, go to the new Advanced menu, and check "Don\'t use a larger iteration sizes when dealing with a large table in the database." and click submit.  ';
								$errmsg .= 'Then try to create the staging site again as normal.  If it still doesn\'t work, please contact WP Stagecoach support.<br/>';
							} else {
								$errmsg .= '<br/>You may need to enable WP Stagecoach\'s optimizations for slower servers to successfully create the database file.<br/>';
								$errmsg .= 'To do so, go to WP Stagecoach -> Settings, and check the "Optimize WP Stagecoach for a slower server?" and update.  ';
								$errmsg .= 'Then try to create the staging site again as normal.  If it still doesn\'t work, please contact WP Stagecoach support.<br/>';
							}
							wpsc_display_error( $errmsg );
							return false;
						}
						$table_dump .= '(';
						foreach ($row as $key => $element) {
							if( ctype_digit( $element ) ){
								//  if the first element of our number is 0, we need to wrap it in quotes so we don't lose it.
								if( strpos( $element, '0' ) === 0 ){
									$table_dump .= "'" . $element . "',";
								} else {
									$table_dump .= $element . ',';
								}
							} elseif( is_null( $element ) ) {
								$table_dump .= 'NULL,'; // return a mysql friendly null for this element
							} else {
								$table_dump .= "'";
								if( $table != $wpdb->prefix . 'users' ){
									$element = wpsc_recursive_unserialize_replace( WPSTAGECOACH_LIVE_SITE, WPSTAGECOACH_STAGE_SITE, WPSTAGECOACH_LIVE_PATH, WPSTAGECOACH_STAGE_PATH, $element);
								}
								$table_dump .= mysqli_real_escape_string( $db, $element );
								$table_dump .= "',";
							}
						}

						$table_dump[ strlen($table_dump)-1 ] = ')';  // replace last ',' w/ ';'
						$table_dump .= ',';
						$i++;
						$j++;
					} // end for $j < $num_of_iterations


					$table_dump[ strlen($table_dump)-1 ] = ';';  // replace last ',' w/ ';'
					$table_dump .= PHP_EOL;

					// write out what we have so far to disk so we don't run out of memory.
					fwrite($db_fh, $table_dump);
					// clean up the dump variable
					$table_dump = '';

				} // end while $i < $num_rows
			} // end if num_rows > 0



			mysqli_free_result($res);
			$curr_row += $rows_from_mysql;
			$rows_left -= $rows_from_mysql;
			if( $curr_row >= $num_rows ){
				$done = true;
			}
		}  // end of while ! $done

		if( isset($ismyisam) ){
			$table_dump .= '/*!40000 ALTER TABLE `'.$table.'` ENABLE KEYS */;'.PHP_EOL;
		}

		$table_dump .= 'UNLOCK TABLES;'.PHP_EOL;
		
		$table_dump .= PHP_EOL;
		fwrite( $db_fh, $table_dump );
	}	// end $tables_to_dump foreach





	// check if we have any tables left:
	if( is_array( $tables_left_to_dump ) && empty( $tables_left_to_dump ) ){
		$is_done = true;
	}

	if( true == $is_done ){
		$nextstep = 3;
		echo '<p>' . __( 'Finished creating compressed database file, it is ' . wpsc_size_suffix( filesize( WPSTAGECOACH_DB_FILE ) ) . ' in size.', 'wpstagecoach' ) . '</p>';
		if( WPSC_DEBUG ) fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'status\'] = "done";' . PHP_EOL );

		// notate successful end of dump
		fwrite( $db_fh, PHP_EOL . '-- Dump completed on ' . date( 'Y-m-d H:i:s' ) . PHP_EOL );

	} else {
		$nextstep = 2;
		echo '<p>' . sprintf( __( 'Going back to step %s to continue dumping the database.', 'wpstagecoach' ), $nextstep )	 . '</p>';
		if( WPSC_DEBUG ) fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'status\'] = "done";' . PHP_EOL );
	}


	mysqli_close($db);
	//	close gzip'd dump file
	if( function_exists( 'gzclose' ) ){
		gzclose($db_fh);
	} elseif( function_exists( 'gzclose64' ) ){
		gzclose64($db_fh);
	}

} // end of step 2

################################################################################################################################

         #####
        #     #
              #
         #####
              #
        #     #
         #####

################################################################################################################################
if( isset($_POST['wpsc-step']) && $_POST['wpsc-step'] == 3 ){

	echo '<p>' . sprintf( __('Step %s: finding all files.', 'wpstagecoach' ), $_POST['wpsc-step'] ) . '</p>';
	/***************************************************************************************
	 *                               find all files by hand                                *
	 ***************************************************************************************/
	chdir( get_home_path() );

	//	Get list of directories that have special needs!
	//		(right now this means just copying all files within these dirs)	
	$post_url = WPSTAGECOACH_CONDUCTOR.'/wpsc-special-dirs.php';
	$post_details = array(
		'wpsc-user'			=> $wpsc['username'],
		'wpsc-key'			=> $wpsc['apikey'],
		'wpsc-ver'			=> WPSTAGECOACH_VERSION,
		'wpsc-live-site'	=> WPSTAGECOACH_LIVE_SITE,
		'wpsc-stage-site'	=> WPSTAGECOACH_STAGE_SITE,
	);
	$post_args = array(
		'timeout' => 120,
		'httpversion' => '1.1',
		'body' => $post_details
	);


	// do some SSL sanity
	if( !isset($wpsc_sanity['https']) ){
		$wpsc_sanity['https'] = wpsc_ssl_connection_test();
	}
	if( $wpsc_sanity['https'] == 'NO_CA' ){
	 	$post_args['sslverify'] = false;
	} elseif( $wpsc_sanity['https'] == 'NO_CURL' ) {
		add_filter('use_curl_transport', '__return_false');
	}

	$post_result = wp_remote_post( $post_url, $post_args );
	$result = wpsc_check_post_info('special_dirs', $post_url, $post_details, $post_result, false) ; // we got a bad response from the server...


	if( $result['result'] != 'OK' ){
		wpsc_display_error($result['info']);
		return;
	} else {
		echo '<p>' . __( 'Okay, we have the list of special needs directories.', 'wpstagecoach' ) . '</p>';
	}	

	echo str_pad( '', 65536 ) . PHP_EOL;
	ob_flush();
	flush();



	global $special_dirs; // we're in a function already, so we need to declare it as a global
	$special_dirs = $result['info'];

	if( empty($special_dirs) ){
		$errmsg  = '<p>' . __( '<b>Error</b>: We got a corrupted list of directories with special needs from the WP Stagecoach website.', 'wpstagecoach' ) . '</p>';
		$errmsg .= '<p>' . __( 'Please refresh this page (and confirm to resubmit form information) to try again.', 'wpstagecoach' ) . '</p>';
		$errmsg .= '<p>' . __( 'If this problem persists, please contact <a href="https://wpstagecoach.com/support" target="_blank">WP Stagecoach support</a>.', 'wpstagecoach' ) . '</p>';
		wpsc_display_error($errmsg);
		return false;
	}

	if( is_link( 'wp-admin' ) || is_link( 'wp-includes' ) ){
		$wpscpost_wpsc_options['symlinkwp'] = true;
	}

	// need to add other special handling dirs..
	// active theme
	$temparr = explode('/', str_replace( getcwd(), '.', get_template_directory() ) );
	$last = array_pop($temparr);
	$special_dirs[implode('/', $temparr)][$last] = true;
	// and child
	if( get_template_directory() != get_stylesheet_directory() ){
		$temparr = explode('/', str_replace( getcwd(), '.', get_stylesheet_directory() ) );
		$last = array_pop($temparr);
		$special_dirs[implode('/', $temparr)][$last] = true;
	}



	if( WPSC_DEBUG ){
		global $list_of_file_sizes;
		$list_of_file_sizes = array();
	}

	// list of excluded files specified under the advanced menu
	if( isset( $wpsc['advanced-create-skip-directories-list'] ) ){
		$exclude_list = explode( PHP_EOL, $wpsc['advanced-create-skip-directories-list'] );
		if( !is_array( $exclude_list ) ){
			$exclude_list = '';
		}
	} else {
		$exclude_list = '';
	}

	//  if the user checks optimize for a slow server, we're only going to look under wp-content and skip everything else
	global $slow_server;
	$slow_server = false;
	if( isset( $wpsc['slow'] ) ){
		$wpscpost_wpsc_options['symlinkwp'] = true;
		$slow_server = true;
	}

	echo '<p class="wpsc-info-from-crawling-fs">'.PHP_EOL;
	$list_of_files = array();
	$list_of_files['totalsize'] = 0;
	$list_of_files = wpsc_build_tar_list_rec( '.', $list_of_files, $exclude_list );
	$list_of_files = wpsc_append_special_dirs_to_list( $list_of_files );
	echo '</p>'.PHP_EOL;

	echo str_pad( '', 65536 ) . PHP_EOL;
	ob_flush();
	flush();
	if( WPSC_DEBUG ){
		// let's make a list of everything we've found
		fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'number_of_all_files\']=' . sizeof( $list_of_file_sizes ) . ';' . PHP_EOL);
		fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'totalsize\']=' . $list_of_files['totalsize'] . ';' . PHP_EOL);
		fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'list_of_file_sizes\']=json_decode(\'' . json_encode( $list_of_file_sizes ) . '\', true);' . PHP_EOL);
	}



	$totalsize = $list_of_files['totalsize'];
	unset($list_of_files['totalsize']);

	if( isset($list_of_files['largefiles']) && is_array($list_of_files['largefiles']) ){
		$normal=false;
		$nextstep = 4;
		$largefiles = $list_of_files['largefiles'];
		unset($list_of_files['largefiles']);

		if( WPSC_DEBUG ){
			fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'largefiles\']=json_decode(\'' . json_encode( $largefiles ) . '\', true);' . PHP_EOL);
		}

		echo '<p><div class="wpstagecoach-warn">' . __( 'These files are much larger than the files usually needed to create a staging site.', 'wpstagecoach' ) . '</p>';
		echo '<p>' . __( 'If you think these files are necessary for your staging site, please <b>check each file</b> to add it to the staging site.  Otherwise, WP Stagecoach will ignore these files.</p>', 'wpstagecoach' ) . '</p>';

		echo '<div><form method="POST" id="wpsc-step-form" class="wpstagecoach-update-step-nonce-form">'.PHP_EOL;
		foreach ( $largefiles as $file ) {
			echo '<input type="checkbox" name="wpsc-largefiles[]" id="' . base64_encode( $file ) . '" value="' . base64_encode( $file ) . '"><label for="' . base64_encode( $file ) . '">' . $file . ' (size: ' . wpsc_size_suffix( filesize( $file ) ) . ')</label><br/>'.PHP_EOL;
		}
		echo '<br /><input type="submit" class="button button-submit wpstagecoach-update-step-nonce" name="wpsc-step-form" value="' . __( 'Proceed', 'wpstagecoach') . '"></div>' . PHP_EOL;	
		echo '</div>' . PHP_EOL;
		
	} else {
		$nextstep = 5;
		if( ! $df = @disk_free_space(WPSTAGECOACH_TEMP_DIR) ){
			$df = 4294967296;
		}
		if( $totalsize > $df ){
			$need = (int)( $totalsize - $df );
			$errmsg  = '<p>' . __( 'There is not enough free space on your web host to create the tar file we need to create your staging site.', 'wpstagecoach' ) . '</p>';
			$errmsg .= '<p>' . sprintf( __( 'Please free at least %s and try again.</p>', 'wpstagecoach' ), wpsc_size_suffix( $need ) ) . '</p>';
			wpsc_display_error($errmsg);
			return;
		}

		echo '<p>' . __( 'Okay, we\'ve found all the files, next we\'ll make them into a single file.', 'wpstagecoach' ) . '</p>';

	}
	echo str_pad( '', 65536 ) . PHP_EOL;
	ob_flush();
	flush();

	if( ! in_array('./wp-config.php', $list_of_files) ){ // we didn't find the wp-config.php file in the list of files.
		// check if it is in the parent directory of the WP home
		if( is_file('../wp-config.php') ){
			$list_of_files[] = '../wp-config.php';
			_e( '<p>We are including the "wp-config.php" file from the above directory.</p>', 'wpstagecoach' );

			if( WPSC_DEBUG ){
				fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'notes\'][] = "including wp-config.php from the above directory" . PHP_EOL;' . PHP_EOL);
				fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'list_of_files\']=json_decode(\'' . json_encode( $list_of_files ) . '\', true);' . PHP_EOL);
			}

			echo str_pad( '', 65536 ) . PHP_EOL;
			ob_flush();
			flush();
		} else {
			$errmsg  = '<p>' . __( '<b>Problem</b>: We could not find your config file <b>wp-config.php.</b>  How are you running WordPress without it?', 'wpstagecoach' ) . '</p>';
			$errmsg .= '<p>' . __( 'This file is usually found in the root directory of your website, or the root\'s parent directory.  If it is somewhere else, you can move wp-config.php to your site\'s root directory, and try again.  If you are having trouble, please contact <a href="https://wpstagecoach.com/support" target="_blank">WP Stagecoach support</a> for help.', 'wpstagecoach' ) . '</p>';
			wpsc_display_error($errmsg);
			return false;	
		}
	}

	$temp_file = tempnam( WPSTAGECOACH_TEMP_DIR, 'wpsc_file_list_' );	
	$list_of_files_str = implode( PHP_EOL, $list_of_files );
	if( file_put_contents( $temp_file, $list_of_files_str ) === false ){
		$errmsg  = '<p>' . sprintf( __( '<b>Problem</b>: We could not write to the file <b> %s </b>.', 'wpstagecoach' ), $temp_file ) . '</p>';
		$errmsg .= '<p>' . sprintf( __( 'Please check the permissions on the %s directory, or contact <a href="https://wpstagecoach.com/support" target="_blank">WP Stagecoach Support</a>.', 'wpstagecoach' ), dirname($temp_file) ) . '</p>';
		$errmsg .= '<p>' . __( 'Unfortunately, we cannot proceed further.', 'wpstagecoach' ) . '</p>';
		wpsc_display_error($errmsg);
		return false;	
	} elseif( WPSC_DEBUG ){
		echo '<p>' . __( 'Successfully wrote list of files to disk.', 'wpstagecoach' ) . '</p>' . PHP_EOL;
		echo str_pad( '', 65536 ) . PHP_EOL;
		ob_flush();
		flush();
	}

	if( WPSC_DEBUG ){
		echo '<pre>list of files: ';
		print_r($temp_file);
		echo '</pre>';
	}
	echo str_pad( '', 65536 ) . PHP_EOL;
	ob_flush();
	flush();

	$wpscpost_fields['wpsc-temp-file-name'] = $temp_file;
	$wpscpost_fields['wpsc-step'] = $nextstep;
	if( $nextstep == 5 ){
		echo '<p>' . sprintf( __( 'Going to step %s next.', 'wpstagecoach' ), $nextstep ) . '</p>';
	} else
		$wpscpost_fields['wpsc-totalsize'] = $totalsize;
	if( $nextstep == 5 ){
		
	} else{
		$normal = false;
	}

	if( WPSC_DEBUG ) fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'status\'] = "done";' . PHP_EOL );
	chdir( 'wp-admin' );
} // end of step 3


################################################################################################################################

        #
        #    #
        #    #
        #    #
        #######
             #
             #

################################################################################################################################
if( isset($_POST['wpsc-step']) && $_POST['wpsc-step'] == 4 ){

	echo '<p>' . sprintf( __('Step %s: adding large files to the list of files.', 'wpstagecoach' ), $_POST['wpsc-step'] ) . '</p>';
	$step = $_POST['wpsc-step'];
	$totalsize = $_POST['wpsc-totalsize'];
	chdir( get_home_path() );

	if( ! empty( $_POST['wpsc-largefiles'] ) ){
		$totalsize = 0;
		if( ! isset( $_POST['wpsc-temp-file-name'] ) || strpos( $_POST['wpsc-temp-file-name'], WPSTAGECOACH_TEMP_DIR . 'wpsc_file_list_' ) === false ){
			$errmsg  = __( "I won't touch files that don't belong to WP Stagecoach.", 'wpstagecoach' );
			$errmsg .= __( 'You sent me: ', 'wpstagecoach' ) . filter_var( $_POST['wpsc-temp-file-name'], FILTER_SANITIZE_SPECIAL_CHARS );
			wpsc_display_error( $errmsg );
			die;
		}
		$fh_temp = fopen($_POST['wpsc-temp-file-name'], 'a');
		foreach ($_POST['wpsc-largefiles'] as $file) {
			$file = base64_decode($file);
			$totalsize += filesize($file);

			fwrite($fh_temp, PHP_EOL.$file);

		}
		fclose($fh_temp);

		$totalsize += $_POST['wpsc-totalsize'];

	}

	if( ! $df = @disk_free_space('.') ){
		$df = 4294967296;
	}
	if( $totalsize > $df ){
		$need = (int)( $totalsize - $df );
		$errmsg = '<p>' . __( 'There is not enough free space to create the tar file we need to create your staging site.', 'wpstagecoach');
		$errmsg .= '<p>' . sprintf( __( 'Please free at least %s and try again.', 'wpstagecoach'), wpsc_size_suffix( $need ) ) . '</p>';
		wpsc_display_error( $errmsg );
		return;
	}

	if( WPSC_DEBUG ) fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'status\'] = "done";' . PHP_EOL );

	$nextstep = 5;
	echo '<p>' . sprintf( __( 'Going to step %s next.', 'wpstagecoach' ), $nextstep ) . '</p>';
	$wpscpost_fields['wpsc-temp-file-name'] = $_POST['wpsc-temp-file-name'];
	$wpscpost_fields['wpsc-step'] = $nextstep;
} // end of step 4


################################################################################################################################

        #######
        #
        #
        ######
              #
        #     #
         #####

################################################################################################################################
	if( isset($_POST['wpsc-step']) && $_POST['wpsc-step'] == 5 ){

	echo '<p>' . sprintf( __('Step %s: creating tar file.', 'wpstagecoach' ), $_POST['wpsc-step'] ) . '</p>';

	$step = $_POST['wpsc-step'];
	/***************************************************************************************
	 *                               build tar file by hand                                *
	 ***************************************************************************************/

	if( ! isset( $_POST['wpsc-temp-file-name'] ) || strpos( $_POST['wpsc-temp-file-name'], WPSTAGECOACH_TEMP_DIR . 'wpsc_file_list_' ) === false ){
		$errmsg  = __( "I won't touch files that don't belong to WP Stagecoach.", 'wpstagecoach' );
		$errmsg .= __( 'You sent me: ', 'wpstagecoach' ) . filter_var( $_POST['wpsc-temp-file-name'], FILTER_SANITIZE_SPECIAL_CHARS );
		wpsc_display_error( $errmsg );
		die;
	}

	require_once(dirname(__FILE__).'/Tar.php');
	chdir( get_home_path() );

	//  check if we are retrying to make the tar file
	if ( isset( $_POST['wpsc-tar-retry'] ) &&  !empty($_POST['wpsc-tar-retry'] ) ) {

		switch ( $_POST['wpsc-tar-retry'] ) {
			case 'Yes':
				//  we are going to try again to make the tar file
				unset( $_POST['wpsc-tar-retry'] );
				unset( $_POST['wpsc-tar-worked'] );
				unset( $_POST['wpsc-tar-done'] );
				unset( $_POST['wpsc-done-files'] );

				echo '<p>' . __('We are going to try to create the tar file again.', 'wpstagecoach') . '</p>';

				if( ! copy( $_POST['wpsc-temp-file-name'] . '.bak', $_POST['wpsc-temp-file-name'] ) ){
					$errmsg  = __('There was a problem with the backup file that we need to create your tar file.  ', 'wpstagecoach');
					$errmsg .= __('The backup file with the list of files', 'wpstagecoach');
					if( is_file( $_POST['wpsc-temp-file-name'] . '.bak' ) ){
						$errmsg .= __('does exist, ', 'wpstagecoach');
						if( !is_readable( $_POST['wpsc-temp-file-name'] . '.bak' ) ) {
							$errmsg .= __('but we cannot read it.', 'wpstagecoach');
						} else {
							$errmsg .= __('and we can read it, but somehow we couldn\'t copy it.', 'wpstagecoach');
						}
					} else {
						$errmsg .= __('does not exist, ', 'wpstagecoach');
					}
					$errmsg .= __('Can you please try to ', 'wpstagecoach') . '<a href="' . get_admin_url().'admin.php?page=wpstagecoach' . '">' . __('create the staging site from the beginning again?', 'wpstagecoach') . '</a>';
					wpsc_display_error( $errmsg );
					return;
				}

				break;	
			case 'No':
				// something went wrong and we are stopping and displaying a feedback form

				$errmsg  = '<p>' . __('Sorry!  We were not able to create the tar file for your staging site.<br/>'.PHP_EOL, 'wpstagecoach');
				$errmsg .= __('Please give us as much information as you can in the feedback form below, and it will be sent to WP Stagecoach support for help!'.PHP_EOL, 'wpstagecoach') . '</p>';
				wpsc_display_error($errmsg);

				echo wpsc_display_feedback_form('create tar error',
				array(
					'wpsc-stage' => $_POST['wpsc-create-stageurl'].'.wpstagecoach.com',
					'wpsc-live' => $_POST['wpsc-create-liveurl'],
					'wpsc-user' => $wpsc['username'],
					'wpsc-key' => $wpsc['apikey'],
					'wpsc-step' => $_POST['wpsc-step'],
					'wpsc-dest' => $_POST['wpsc-options']['dest'],
					'wpsc-info' => 'Tar file couldn\'t be created. Should have had ' . $_POST['wpsc-tot-files'] . ' files, but we only had ' . $_POST['wpsc-actual-files'] . '.',
					), 'Is there anything unusual about your WordPress installation?', 5
				);
				return false;
				break;

			case 'Charge-boldly-ahead':
				//  we are charging boldly ahead with the current tar file, missing files and all!

				$skip_to_six = true;
				unset( $_POST['wpsc-tar-done'] );
				$_POST['wpsc-step'] = 6;
				$nextstep = 6;
				$tar_worked = true;
				echo '<p>' . __('We are charging ahead with the current tar file (even though it appears to be missing some files)!', 'wpstagecoach') . '</p>';
				break;
		}
	}


	if( isset( $wpsc['tar-all-files-at-once'] ) ){
		$list_of_files = explode( PHP_EOL, file_get_contents( $_POST['wpsc-temp-file-name'] ) );
		$split_size = sizeof( $list_of_files );
		if(WPSC_DEBUG)
			echo sprintf( __( 'We are making the tar all in one step.  There are %s files', 'wpstagecoach' ), number_format( $split_size ) ) . '<br/>' . PHP_EOL;
	} elseif( isset($wpsc['slow']) ){
		if(WPSC_DEBUG)
			echo __( 'We are adding fewer files per step to help make tar file creation more reliable on slower servers.', 'wpstagecoach' ) . '<br/>' . PHP_EOL;
		$split_size = 200;
	} else {
		$split_size = 1000;
	}

	if( ( empty( $_POST['wpsc-tar-done'] ) && empty( $_POST['wpsc-tar-worked'] ) ) && !isset( $skip_to_six ) ){
		// first time in this step--need to create the tar file and start splitting up the file list

		$temp_file = $_POST['wpsc-temp-file-name'];
		$list_of_files = explode( PHP_EOL, file_get_contents( $temp_file ) );

		$tot_num_files = sizeof($list_of_files);
		copy( $temp_file, $temp_file . '.bak' );

		$num_files = $tot_num_files;
		if( $num_files > $split_size ){
			// split off $split_size (1200) files and re-store the remain files in the $temp_file
			$chunk_of_files = array_splice( $list_of_files, 0, $split_size );
			file_put_contents( $temp_file, implode( PHP_EOL, $list_of_files ) );

		} else {
			$chunk_of_files = $list_of_files;
			$tar_done = true;
		}

		if( WPSC_DEBUG ){
			fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'chunk_of_files\'][] = json_decode(\'' . json_encode( $chunk_of_files ) . '\', true );' . PHP_EOL);
		}

		echo '<p>' . sprintf( __( 'We are %d%% done building the initial tar file.', 'wpstagecoach' ), 0 ) . '</p>';

		// show the list of files being worked on	
		echo '<p><a class="toggle">' . __( 'Show files being worked on', 'wpstagecoach' ) . '</a></p>';
		echo '<div class="more" style="display: none">'.PHP_EOL;
		echo implode('<br/>'.PHP_EOL, $chunk_of_files);
		echo '</div>'.PHP_EOL;

		echo str_pad( '', 65536 ) . PHP_EOL;
		ob_flush();
		flush();

		$tar = new Archive_Tar( WPSTAGECOACH_TAR_FILE );
		if( WPSC_DEBUG ){
			fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'start_tar_write\'][] = ' . date( 'U' ) . ';' . PHP_EOL);
		}


		if( !$tar->create( $chunk_of_files ) ){
			echo '<p>' . __( 'Error: we couldn\'t create the initial file we need to make your staging site.', 'wpstagecoach' ) . '</p>';
			$tar_worked = false;
			$nextstep = 6; // bailing b/c we couldn't create tar file
		} else {
			$tar_worked = true;
			$nextstep = 5;
			$files_done = $split_size;
			#echo '<p>We are 0% done building the initial tar file.</p>';
		}

		if( WPSC_DEBUG ){
			fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'finish_tar_write\'][] = ' . date( 'U' ) . ';' . PHP_EOL);
		}


		echo str_pad( '', 65536 ) . PHP_EOL;
		ob_flush();
		flush();

	} elseif( ( !isset($_POST['wpsc-tar-done']) || $_POST['wpsc-tar-done'] != true ) && !isset($skip_to_six)  ) {
		// we're coming back around--we need to append to the tar file

		$nextstep = 5;
		$files_done = $_POST['wpsc-done-files'];
		$tot_num_files = $_POST['wpsc-tot-files'];

		$temp_file = $_POST['wpsc-temp-file-name'];
		$list_of_files = explode( PHP_EOL, file_get_contents( $temp_file ) );


		$num_files = sizeof($list_of_files);
		if( $num_files > $split_size ){
			// split off $split_size (1000) files and re-store the remain files in the $temp_file
			$chunk_of_files = array_splice( $list_of_files, 0, $split_size );
			file_put_contents( $temp_file, implode( PHP_EOL, $list_of_files ) );
		} else {
			$chunk_of_files = $list_of_files;
			$tar_done = true;
		}

		$percentage = (int)(($files_done / $tot_num_files ) * 100 );
		echo '<p>' . sprintf( __('We are %d%% done building the initial tar file.', 'wpstagecoach' ), $percentage ) . '</p>';

		// show the list of files being worked on	
		echo '<p><a class="toggle">' . __( 'Show files being worked on', 'wpstagecoach' ) . '</a></p>';
		echo '<div class="more" style="display: none">'.PHP_EOL;
		echo implode('<br/>'.PHP_EOL, $chunk_of_files);
		echo '</div>'.PHP_EOL;

		echo str_pad( '', 65536 ) . PHP_EOL;
		ob_flush();
		flush();



		require_once('Tar.php');
		$tar = new Archive_Tar( WPSTAGECOACH_TAR_FILE );

		if( !$tar->add( $chunk_of_files ) ){
			echo '<p>' . __( 'Error: we couldn\'t append to the initial file we need to make your staging site.', 'wpstagecoach' ) . '</p>';
			$tar_worked = false;
			$nextstep = 6; // bailing b/c we couldn't create tar file
		} else {
			$tar_worked = true;
			$nextstep = 5;
			$files_done += $split_size;
		}

		echo str_pad( '', 65536 ) . PHP_EOL;
		ob_flush();
		flush();		
			
	} elseif( isset( $_POST['wpsc-tar-done'] ) && $_POST['wpsc-tar-done'] == true && !isset($skip_to_six) ) {
		//  tar file is done! yay!

		$nextstep = 6;
		$tar_worked = $_POST['wpsc-tar-worked'];
		$temp_file = $_POST['wpsc-temp-file-name'];
		unlink( $temp_file );

		// get the number of files from the tar again to make sure they match what we found	
		if( is_file( WPSTAGECOACH_TAR_FILE ) ){

			if( wpstagecoach_check_for_hhvm() == true ){
				// we are running HHVM, and we don't want to wait for HHVM to completely open the tar file and read it just to get the number of files.
				if( WPSC_DEBUG ){
					fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'tar_file\'] = "HHVM - skipping reading tar file back due to extreme slowness";' . PHP_EOL);
				}
				$num_files_tarred = $_POST['wpsc-tot-files'];

			} else {
				// we aren't running HHVM, and PHP can quickly open the tar file and get the number of files from it.
				if( ! $tar = new Archive_Tar( WPSTAGECOACH_TAR_FILE ) ){
					$errmsg = 'We couldn\'t open the tar file that WP Stagecoach created, we received a bad result.  Please try to create the staging site again, and if that fails, you may contact WP Stagecoach support.';
					wpsc_display_error( $errmsg );
					return false;
				}

				if( WPSC_DEBUG ){
					fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'start_tar_read\'][] = ' . date( 'U' ) . ';' . PHP_EOL);
				}
				$num_files_tarred = sizeof( $tar->listContent() );
				if( WPSC_DEBUG ){
					fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'finish_tar_read\'][] = ' . date( 'U' ) . ';' . PHP_EOL);
				}
			}

		} else {
			$errmsg = 'We couldn\'t open the tar file that WP Stagecoach created; it appears to be missing.  Please try to create the staging site again, and if that fails, you may contact WP Stagecoach support.';
			wpsc_display_error( $errmsg );
			return false;
		}


		$errmsg = __( 'It looks like something went wrong with the site\'s tar file creation: the number of files in the tar file doesn\'t match the number of files we found to include.', 'wpstagecoach' ) . '<br/>' . PHP_EOL;

		if( $num_files_tarred != $_POST['wpsc-tot-files'] ){
			// we didn't get the same number of files in the tar as we were supposed to...
			$normal = false;
			$nextstep = 5;

			$all_files_in_tar = $tar->listContent();
			if( is_array( $all_files_in_tar ) ){
				foreach ( $all_files_in_tar as $file ) {
					$list_of_tar_files[] = './' . $file['filename'];  // the found files all have ./ preceeding them.
				}
				$list_of_found_files =  explode( PHP_EOL, file_get_contents( $_POST['wpsc-temp-file-name'] . '.bak' ) );
				$missing_files = array_diff( $list_of_found_files, $list_of_tar_files );
			} else {
				$errmsg = __( 'Error: We could not even get a valid list of files back from the tar file.', 'wpstagecoach' ) . '<br/>' . PHP_EOL;
				wpsc_display_error( $errmsg );
				$all_files_in_tar = array();  //  we need to make it clear we got a bad result back, but we still need functions below to work.
			}


			$errmsg .= '<p>' . __( 'We encountered an error collecting the files we need to create your staging site.', 'wpstagecoach' ) . '</p>';
			$errmsg .= '<p>' . __( 'This might be because your site changed while we were gathering files, or your server might be too busy.', 'wpstagecoach' ) . '</p>';
			$errmsg .= '<a class="toggle">' . __( 'Show the missing files', 'wpstagecoach' ) . '</a><br/>';
			$errmsg .= '<div class="more" style="display: none"><b><ul>' . PHP_EOL;
			foreach ( $missing_files as $file ) {
				$errmsg .= '<li>' . $file ;
			}
			$errmsg .= '</b></ul></div>'.PHP_EOL;

			$errmsg .= '<p>' . __( 'Do you want to try to create the tar file again?', 'wpstagecoach' ) . '</p>';
			wpsc_display_error( $errmsg );


			echo '<form method="POST" id="wpsc-step-form" class="wpstagecoach-update-step-nonce-form">';
			echo '<input type="radio" name="wpsc-tar-retry" value="Yes" id="yes" checked="yes"><label for="yes">' . __( 'Yes, retry', 'wpstagecoach' ) . '</label></br>' . PHP_EOL;	
			echo '<input type="radio" name="wpsc-tar-retry" value="No" id="no"><label for="no">' . __( 'No, don\'t create a staging site', 'wpstagecoach' ) . '</label></br>' . PHP_EOL;	
			echo '<input type="radio" name="wpsc-tar-retry" value="Charge-boldly-ahead" id="charge"><label for="charge">' . __( 'Charge Boldly Ahead: create staging site without the missing files!', 'wpstagecoach' ) . '</label></br>' . PHP_EOL;
			echo '<input type="submit" class="button submit-button wpstagecoach-update-step-nonce" name="wpsc-tar-retry-submit" value="Submit">'.PHP_EOL;	
			echo '  <input type="hidden" name="wpsc-temp-file-name" value="' . $_POST['wpsc-temp-file-name'] . '"/>' . PHP_EOL;
			echo '  <input type="hidden" name="wpsc-tot-files" value="' . $_POST['wpsc-tot-files'] . '"/>' . PHP_EOL;
			echo '  <input type="hidden" name="wpsc-actual-files" value="' . $num_files_tarred . '"/>' . PHP_EOL;

		} else {
			echo __( 'Great, tar file looks like it contains the right number of files!', 'wpstagecoach' ) . '<br/>' . PHP_EOL;
			unlink($_POST['wpsc-temp-file-name'] . '.bak');
		}

		if( $normal !== false ){
			####    checking that tar file is good
			if( $tar_worked == false ||
				! is_file( WPSTAGECOACH_TAR_FILE ) ||
				filesize( WPSTAGECOACH_TAR_FILE ) < 102400 || // 100kB
				$num_files_tarred != $_POST['wpsc-tot-files']
			) {
				$errmsg = '<p>' . __( 'Unfortunately, we were not able to create the tar file we need to make your staging site.  Please contact <a href="https://wpstagecoach.com/support">WP Stagecoach support</a> with the following information: ', 'wpstagecoach' ) . '</p>';

				if( $tar_worked == false ){
					$errmsg .= '<p>' . __( 'The tar file creation didn\'t appear to work.', 'wpstagecoach' ) . '</p>';
				}
				if( ! is_file( WPSTAGECOACH_TAR_FILE ) ){
					$errmsg .= '<p>' . __( 'The tar file didn\'t exist.', 'wpstagecoach' ) . '</p>';
				}
				if( filesize( WPSTAGECOACH_TAR_FILE ) < 102400 ){
					$errmsg .= '<p>' . __( 'The tar file was too small.  It is: ', 'wpstagecoach' ) . filesize( WPSTAGECOACH_TAR_FILE ) . 'b</p>';
				} else {
					$errmsg .= '<p>' . __( 'The tar file is an appropriate size.  It is: ', 'wpstagecoach' ) . filesize( WPSTAGECOACH_TAR_FILE ) . 'b</p>';
				}
				if( $num_files_tarred != $_POST['wpsc-tot-files'] ){
					$errmsg .= '<p>' . __( 'The number of files in the tar file didn\'t agree with the number of files we found.', 'wpstagecoach' ) . '</p>';
				} else {
					$errmsg .= '<p>' . __( 'The number of files in the tar file is: ', 'wpstagecoach' ) . $num_files_tarred . '</p>';
				}
				wpsc_display_error( $errmsg );
				return false;
			} else {
				echo '<p>' . __( 'Great, created the initial file we need to make your staging site!', 'wpstagecoach' ) . '</p>';
				$tar_worked = true;
				flush();
				if( WPSC_DEBUG ) fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'status\'] = "done";' . PHP_EOL );
			}
		}
 
	}

	if( WPSC_DEBUG && ( !isset( $_POST['wpsc-tar-done'] ) || $_POST['wpsc-tar-done'] != true ) && isset( $chunk_of_files ) ){
		fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'chunk_of_files\'][] = json_decode(\'' . json_encode( $chunk_of_files ) . '\', true );' . PHP_EOL);
	}
	echo str_pad( '', 65536 ) . PHP_EOL;
	ob_flush();
	flush();

	if( $normal !== false ){
		$back = '';
		if( $step == $nextstep ){
			$wpscpost_fields['wpsc-temp-file-name'] = $temp_file;
			$back = __( 'back', 'wpstagecoach' );
		}
		echo '<p>' . sprintf( __( 'Going %s to step %s next', 'wpstagecoach' ), $back, $nextstep ) . '</p>';
		$wpscpost_fields['wpsc-tar-worked'] = $tar_worked;
		if( isset($tar_done) && $tar_done )				$wpscpost_fields['wpsc-tar-done'] = $tar_done;
		if( isset($tot_num_files) && $tot_num_files )	$wpscpost_fields['wpsc-tot-files'] = $tot_num_files;
		if( isset($files_done) && $files_done )			$wpscpost_fields['wpsc-done-files'] = $files_done;

	}
} // end of step 5


################################################################################################################################

		 #####
		#     #
		#
		######
		#     #
		#     #
		 #####

################################################################################################################################
if( isset($_POST['wpsc-step']) && $_POST['wpsc-step'] == 6 ){
	// have to figure out if we are uploading both files, or doing it over 2 steps.


	if( ! isset( $_POST['wpsc-options']['dest'] ) || ! preg_match('/^[a-z0-9-.]*\.wpstagecoach\.com$/', $_POST['wpsc-options']['dest'] ) ){
		$errmsg  = __( "I won't touch or send files that don't belong to WP Stagecoach.", 'wpstagecoach' );
		$errmsg .= __( 'You sent me: ', 'wpstagecoach' ) . filter_var( $_POST['wpsc-temp-file-name'], FILTER_SANITIZE_SPECIAL_CHARS );
		wpsc_display_error( $errmsg );
		die;
	}

	if( isset($_POST['wpsc-stop']) && !empty($_POST['wpsc-stop']) ){ // we are giving up instead of trying again and going on.
		$msg  = '<p>' . __( 'Sorry you were unable to create a staging site! :-(', 'wpstagecoach' ) . '</p>';
		$msg .= '<p>' . __( 'Please provide feedback below with any error messages or additional information that might help diagnose the problem.', 'wpstagecoach') . '</p>';
		echo wpsc_display_feedback_form('create-error',
		array(
			'wpsc-stage' => $_POST['wpsc-create-stageurl'].'.wpstagecoach.com',
			'wpsc-live' => $_POST['wpsc-create-liveurl'],
			'wpsc-user' => $wpsc['username'],
			'wpsc-key' => $wpsc['apikey'],
			'wpsc-step' => $_POST['wpsc-step'],
			'wpsc-dest' => $_POST['wpsc-options']['dest'],
			),
		$msg
		);
		return;
	} else { // normal!
		echo '<p>' . sprintf( __('Step %s: uploading your ', 'wpstagecoach' ), $_POST['wpsc-step'] ) ;
	}

	if( WPSC_DEBUG ){
		$debug_msg = 'starting upload';
		fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'info\'] .= "' . $debug_msg . '";' . PHP_EOL);
		// echo 'Debug: ' . $debug_msg . '<br/>';
	}


	if( !isset( $_POST['wpsc-upload-repeat'] ) ){
		if( WPSC_DEBUG ){
			$debug_msg = 'going to upload';
			fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'info\'] .= "' . $debug_msg . '";' . PHP_EOL);
			// echo 'Debug: ' . $debug_msg . '<br/>';
		}
		if( (filesize(WPSTAGECOACH_DB_FILE) + filesize(WPSTAGECOACH_TAR_FILE)) > 52428800 ){ // 50MB
			if( WPSC_DEBUG ){
				$debug_msg = 'files are larger than 50MB - uploading one at a time time" . PHP_EOL . "uploading tar now.';
				fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'info\'] .= "' . $debug_msg . '";' . PHP_EOL);
				// echo 'Debug: ' . $debug_msg . '<br/>';
			}
			echo __( 'tar file', 'wpstagecoach' ); 
			$file_type='tar';
			$wpscpost_fields['wpsc-upload-repeat'] = true;
			$nextstep = 6;
		} else { // files are small enough to hopefully upload both in one step.
			echo __( 'files', 'wpstagecoach' );
			$nextstep = 7;
		}
	} else { // we are repeating step 6 to upload sql file.
		if( WPSC_DEBUG ){
			$debug_msg = 'continuing upload - now on sql file.';
			fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'info\'] .= "' . $debug_msg . '";' . PHP_EOL);
			// echo 'Debug: ' . $debug_msg . '<br/>';
		}
		echo __( 'sql file', 'wpstagecoach' );
		$file_type='sql';
		$nextstep = 7;
	}

	echo __(' to the WP Stagecoach server.', 'wpstagecoach' ) . '</p>'; // complete our message to user...
	echo str_pad( '', 65536 ) . PHP_EOL;
	ob_flush();
	flush();

	if( WPSC_DEBUG ){
		$debug_msg = 'continuing upload - now on sql file.';
		fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'info\'] .= "' . $debug_msg . '";' . PHP_EOL);
		// echo 'Debug: ' . $debug_msg . '<br/>';
	}



	if( isset($_POST['wpsc-tar-worked']) && true != $_POST['wpsc-tar-worked'] && !isset($_POST['wpsc-upload-repeat']) ) {
		if( WPSC_DEBUG ){
			$debug_msg = '_POST[wpsc-tar-worked] is not true - problems making tar?';
			fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'info\'] .= "' . $debug_msg . '";' . PHP_EOL);
			// echo 'Debug: ' . $debug_msg . '<br/>';
		}
		$errmsg  = '<p>' . __( 'Uh oh.  We have run into a problem: we were not able to build the necessary files to create your staging site.', 'wpstagecoach' ) . '</p>';
		$errmsg .= '<p>' . __( 'Please submit the feedback form below get help from WP Stagecoach', 'wpstagecoach' ) . '</p>';
		wpsc_display_error($errmsg);

		echo wpsc_display_feedback_form('create-error',
		array(
			'wpsc-stage' => $_POST['wpsc-create-stageurl'].'.wpstagecoach.com',
			'wpsc-live' => $_POST['wpsc-create-liveurl'],
			'wpsc-user' => $wpsc['username'],
			'wpsc-key' => $wpsc['apikey'],
			'wpsc-step' => $_POST['wpsc-step'],
			'wpsc-dest' => $_POST['wpsc-options']['dest'],
			)
		);
		return false;
	}




	if( isset($file_type) ){
		$upload_array = array(0 => $file_type);
	} else {
		$upload_array = array('tar', 'sql');
	}

	foreach ($upload_array as $ftype) {
		// upload each file to the server


		if($ftype == 'sql'){
			$filename = WPSTAGECOACH_DB_FILE;
			$filesize = filesize( WPSTAGECOACH_DB_FILE );
			$chksum   = md5_file( WPSTAGECOACH_DB_FILE );
		} else {
			$filename = WPSTAGECOACH_TAR_FILE;
			$filesize = filesize( WPSTAGECOACH_TAR_FILE );
			$chksum   = md5_file( WPSTAGECOACH_TAR_FILE );
		}

		$post_details = array(
			'wpsc-user'			=> $wpsc['username'],
			'wpsc-key'			=> $wpsc['apikey'],
			'wpsc-type'			=> $ftype,
			'wpsc-chksum'		=> $chksum,
			'wpsc-ver'			=> WPSTAGECOACH_VERSION,
			'wpsc-live-site'	=> WPSTAGECOACH_LIVE_SITE,
			'wpsc-stage-site'	=> WPSTAGECOACH_STAGE_SITE,
		);

		$fsize = wpsc_size_suffix( $filesize );
		if( WPSC_DEBUG ){
			$debug_msg = 'preparing to upload ' . $ftype . ' file now. size ' . $fsize . '.';
			fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'info\'] .= "' . $debug_msg . '";' . PHP_EOL);
			// echo 'Debug: ' . $debug_msg . '<br/>';
		}
		echo sprintf( __('Uploading your %s file (size: %s ) to the staging server now...', 'wpstagecoach'), $ftype, $fsize );
		echo str_pad( '', 65536 ) . PHP_EOL;
		ob_flush();
		flush();


		if( !isset( $wpsc_sanity['https'] ) ){
			$wpsc_sanity['https'] = wpsc_ssl_connection_test();
		}
		
		if( $wpsc_sanity['https'] == 'ALL_GOOD' || $wpsc_sanity['https'] == 'NO_CA' ){
			$post_url = 'https://'.$_POST['wpsc-options']['dest'].'/wpsc-app-upload.php';
			// we can use curl to upload things
			if( version_compare( PHP_VERSION, '5.5.0', '>=' ) ){
				$post_details['file'] = new CURLFile( $filename );
			} else {
				$post_details['file'] = '@'.$filename;
			}


			$ch=curl_init();
			curl_setopt( $ch, CURLOPT_URL,				$post_url );
			curl_setopt( $ch, CURLOPT_POST,				1 );
			curl_setopt( $ch, CURLOPT_POSTFIELDS,		$post_details );
			curl_setopt( $ch, CURLOPT_TIMEOUT,			600 );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER,	1 );


			if( $wpsc_sanity['https'] == 'NO_CA' ){
				curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
				curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
			}

			if( WPSC_DEBUG ) fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'info\'] .= "uploading...";' . PHP_EOL);
			$post_result['body'] = curl_exec($ch);
			if( WPSC_DEBUG ) fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'info\'] .= "and, done!";' . PHP_EOL);

			if( WPSC_DEBUG ){
				echo '<p>' . __( 'Result from curl: ', 'wpstagecoach' ) . '<pre>' . print_r( $post_result['body'] , true ) . '</pre></p>' . PHP_EOL;
			}

			if( !curl_errno($ch) ){
				$post_result['response']['message'] = 'OK';
			} else {
				$post_result['response']['message'] = 'BAD';
				$post_result['response']['code'] = curl_error($ch);
			}
			curl_close($ch);
			if( WPSC_DEBUG ) fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'info\'] .= "response from workhorse: ' . print_r( $post_result['body'], true ) . '" . ;'. PHP_EOL);

			$curl_result = wpsc_check_post_info('upload_'.$ftype.'_file', $post_url, $post_details, $post_result, false ) ; // decoding response from the server  -- setting $display_output = false so we can display error below



			if( $curl_result['result'] != 'OK'){
				$curl_failed = true;
			} else {
				$result = $curl_result;
			}
			
		} elseif( $wpsc_sanity['https'] == 'NO_CURL' ) {
			$curl_failed = true;
		} else {
			$errmsg  = '<p>' . __( 'We could not determine what method SSL transport methods your website server supports.', 'wpstagecoach' ) . '</p>';
			$errmsg .= '<p>' . __( 'Please contact <a href="https://wpstagecoach.com/support" target="_blank">WP Stagecoach support</a> with your hosting provider, and the following details:', 'wpstagecoach' );
			$errmsg .= '<pre>'. print_r($wpsc_sanity,true).'</pre></p>';
			wpsc_display_error($errmsg);
			return;
		}

		if( ( isset( $curl_failed ) && $curl_failed ) ) {
			if( WPSC_DEBUG ) fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'info\'] .= "error from uploading with curl - trying wp_remote_get to push." . ;'. PHP_EOL);

			if( $wpsc_sanity['https'] != 'NO_CURL' ){
				echo '<div class="wpstagecoach-warn"><p>' . __( 'We encountered an error while uploading the file with Curl.  We are going to try now with WordPress\' remote post.<br/>', 'wpstagecoach' );
				echo __( 'However, due to how this function works, it may cause your site to run out of memory.  If so, please increase the WP memory limit and try again.', 'wpstagecoach' ) . '</p>';
				if( WPSC_DEBUG ){
					echo '<p>' . __( 'Here is more information: ', 'wpstagecoach' ) . print_r( $curl_result, true ) . '</p>' . PHP_EOL;
				}
				echo '</div>' . PHP_EOL;
			}


			$post_url = 'https://'.$_POST['wpsc-options']['dest'].'/wpsc-app-upload-wp.php';
			// add_filter('use_curl_transport', '__return_false');
			$headers = array(
				'content-type' => 'application/binary', // Set content type to binary
			);

			
			if( WPSC_DEBUG ) fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'info\'] .= "opening file to push over wp_remote_post." . ;'. PHP_EOL);
			$file = fopen( $filename, 'r' );

			if( WPSC_DEBUG ) fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'info\'] .= "uploading..." . ;'. PHP_EOL);
			$post_details = array(
				'wpsc-user'			=> $wpsc['username'],
				'wpsc-key'			=> $wpsc['apikey'],
				'wpsc-type'			=> $ftype,
				'wpsc-chksum'		=> $chksum,
				'wpsc-ver'			=> WPSTAGECOACH_VERSION,
				'wpsc-live-site'	=> WPSTAGECOACH_LIVE_SITE,
				'wpsc-stage-site'	=> WPSTAGECOACH_STAGE_SITE,
			);

			$post_url = $post_url . '?' . http_build_query( $post_details );
			$post_result = wp_remote_post( $post_url , array(
				'headers' => $headers,
				'method' => 'POST',
				'timeout' => 120,
				'body' => fread( $file, $filesize ),

				) );


			if( WPSC_DEBUG ) fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'info\'] .= "and, done!" . ;'. PHP_EOL);

			if( WPSC_DEBUG ) fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'info\'] .= "response from workhorse: ' . print_r( $post_result, true ) . '" . ;'. PHP_EOL);
			fclose( $file );	

			$result = wpsc_check_post_info( 'upload_' . $ftype . '_file', $post_url, $post_details, $post_result ) ; // decoding response from the server  -- setting $display_output = false so we can display error below
		}


		if( $result['result'] == 'OK' ){
			echo '<p>' . __( 'Done!', 'wpstagecoach' ) . '</p>';
		} else {
			$error = true;
			break;
		}

		// we're going to cheat to get the "import Changes" menu option up a step early.
		$wpsc['staging-site'] = true;	
		update_option('wpstagecoach', $wpsc);


		echo str_pad( '', 65536 ) . PHP_EOL;
		ob_flush();
		flush();
	}

	// if we run into problems, we can ask to retry it...
	if( isset( $error ) && true == $error ){
		$normal = false;
		$nextstep = 6;
		$errmsg  = '<p>' . __( 'There was a problem communicating with the staging server.', 'wpstagecoach' ) . '</p>';
		if( isset($result['info']) && !empty($result['info']) ){
			$errmsg .='<p>' . __( 'We got this information from the server: ', 'wpstagecoach' ) . '</p>';
			if( is_array($result) && isset($result['info']) ){
				$errmsg .= '<b>'.print_r($result['info'],true).'</b>'.PHP_EOL;
			} else {
				$errmsg .= '<b><pre>'.print_r($result,true).'</pre></b>'.PHP_EOL;
			}
		}
		if( isset($result['filesize']) ){
			$errmsg .= '<p><b>' . sprintf( __( 'The size of the %s file here on the live site is: %s', 'wpstagecoach' ), $ftype, $filesize ) . '</b></p>';
		} 
		$errmsg .= '<p>' . __( 'Would you like to try again?', 'wpstagecoach' ) . '</p>';
		wpsc_display_error($errmsg);


		echo '<form method="POST" id="wpsc-step-form" class="wpstagecoach-update-step-nonce-form">';
		foreach ( $_POST as $key => $value) {
			if( is_array($value) ){
				foreach ($value as $subkey => $subvalue) {
					echo '    <input type="hidden" name="' . $key . '[' . $subkey . ']" value="' . $subvalue . '"/>'.PHP_EOL;
				}
			} else {
				echo '  <input type="hidden" name="'.$key.'" value="'.$value.'"/>'.PHP_EOL;
			}
		}


		echo '  <input type="hidden" name="wpsc-retry" value="Yes"/>'.PHP_EOL;
		echo '<input type="submit" class="button submit-button wpstagecoach-update-step-nonce" name="wpsc-retry-upload" value="' . __( 'Yes', 'wpstagecoach' ) . '">';	
		echo '<input type="submit" class="button submit-button wpstagecoach-update-step-nonce" name="wpsc-stop" value="' . __( 'No', 'wpstagecoach' ) . '">';

		$db_size = wpsc_size_suffix( filesize(WPSTAGECOACH_DB_FILE) );
		$tarsize = wpsc_size_suffix( filesize(WPSTAGECOACH_TAR_FILE) );
		echo '<p>' . sprintf( __('Please note, the SQL file is %s in size, and the tar file is %s in size.', 'wpstagecoach' ), $db_size, $tarsize );
		if( (filesize(WPSTAGECOACH_DB_FILE)/1048576) > 50 )
			echo __( 'The SQL file is abnormally large. This may be the cause of the failure.', 'wpstagecoach' ) . '</p>';
		if( (filesize(WPSTAGECOACH_TAR_FILE)/1048576) > 100 )
			echo __( 'The tar file is abnormally large. This may be the cause of the failure.', 'wpstagecoach' ) . '</p>';


		if( isset($_POST['wpsc-retry']) ){
			echo '<p>' . __( 'If you don\'t want to try again, you can submit feedback to WP Stagecoach support and we will try to help.', 'wpstagecoach' ) . '</p>';
			echo wpsc_display_feedback_form('error',
			array(
				'wpsc-stage' => $_POST['wpsc-create-stageurl'].'.wpstagecoach.com',
				'wpsc-live' => $_POST['wpsc-create-liveurl'],
				'wpsc-user' => $wpsc['username'],
				'wpsc-key' => $wpsc['apikey'],
				'wpsc-step' => $_POST['wpsc-step'],
				'wpsc-dest' => $_POST['wpsc-options']['dest'],
				), ''
			);
		}

	} elseif( isset($_POST['wpsc-cleanup']) && $_POST['wpsc-cleanup'] != 'No' ) { // things are great!

		/***************************************************************************************
		 *                                    delete old stuff                                 *
		 ***************************************************************************************/
		if(file_exists(WPSTAGECOACH_DB_FILE)) unlink(WPSTAGECOACH_DB_FILE);
		if(file_exists(WPSTAGECOACH_TAR_FILE)) unlink(WPSTAGECOACH_TAR_FILE);
		if( WPSC_DEBUG ) fwrite( $create_log, '$create_debug_log[\'step' . $_POST['wpsc-step'] . '\'][\'status\'] = "done";' . PHP_EOL );

		// we're going to cheat to get the "import Changes" menu option up a step early.
		$wpsc['staging-site'] = true;	
		update_option('wpstagecoach', $wpsc);
	}
} // end of step 6


################################################################################################################################

		#######
		#    #
		    #
		   #
		  #
		  #
		  #

################################################################################################################################
if( isset($_POST['wpsc-step']) && $_POST['wpsc-step'] == 7 ){
	wpsc_display_sidebar();
	if( !isset($_POST['wpsc-cleanup']) ){ // we are on our first run
		echo '<p>' . sprintf( __('Step %s: creating your staging site!', 'wpstagecoach' ), $_POST['wpsc-step'] ) . '</p>';


		//	go talk to the conductor and create a staging site
		$post_url = WPSTAGECOACH_CONDUCTOR.'/wpsc-create-site.php';
		$post_details = array(
			'wpsc-user'			=> $wpsc['username'],
			'wpsc-key'			=> $wpsc['apikey'],
			'wpsc-ver'			=> WPSTAGECOACH_VERSION,
			'wpsc-live-site'	=> WPSTAGECOACH_LIVE_SITE,
			'wpsc-live-path'	=> WPSTAGECOACH_LIVE_PATH,
			'wpsc-stage-site'	=> WPSTAGECOACH_STAGE_SITE,
			'wpsc-dest'			=> $_POST['wpsc-options']['dest'],
		);

		if( isset( $_POST['wpsc-retry-create'] ) ){
			$post_details['wpsc-retry-create'] = 'yes';
		}

		global $wp_version;
		$DB_HOST = explode(':', DB_HOST);
		if(isset($DB_HOST[1]) ){
			if( ctype_digit( $DB_HOST[1] ) ){ // it is only digits, therefore, hopefully it is a port
				$db = mysqli_connect($DB_HOST[0], DB_USER, DB_PASSWORD, DB_NAME, $DB_HOST[1]); // these are defined in wp-config.php
			} else { // it has alpha characters, so it is likely a socket.
				$db = mysqli_connect($DB_HOST[0], DB_USER, DB_PASSWORD, DB_NAME, NULL, $DB_HOST[1]); // these are defined in wp-config.php
			}
		} else {
			$db = mysqli_connect($DB_HOST[0], DB_USER, DB_PASSWORD, DB_NAME); // these are defined in wp-config.php
		}
		$mysql_server_info = $db->server_info;
		$mysql_server_ver = $db->server_version;
		$mysql_client_info = mysqli_get_client_info();
		$mysql_client_ver = mysqli_get_client_version();
		global $table_prefix;
		$post_options = array(
			'wp-ver'		=> $wp_version,
			'php-ver'		=> phpversion(),
			'table-prefix'	=> $table_prefix,
			'mysqls-ver'	=> $mysql_server_ver,
			'mysqls-info'	=> $mysql_server_info,
			'mysqlc-ver'	=> $mysql_client_ver,
			'mysqlc-info'	=> $mysql_client_info,
		);


		if( defined( 'DB_CHARSET' ) ){
			$post_options['charset'] = DB_CHARSET;
		} else {
			$post_options['charset'] = 'NA';
		}

		if( isset( $_SERVER['SERVER_SOFTWARE'] ) && !empty( $_SERVER['SERVER_SOFTWARE'] ) ){
			$post_options['server-info'] = $_SERVER['SERVER_SOFTWARE'];
		} else {
			$post_options['server-info'] = 'NA';
		}

		if ( function_exists('curl_version') ){
			$curl_info = curl_version();
			$post_options['curl-ver'] = $curl_info['version'];
			$post_options['curl-host'] = $curl_info['host'];
			$post_options['curl-ssl-ver'] = $curl_info['ssl_version'];
			$post_options['curl-libz-ver'] = $curl_info['libz_version'];
		} else {
			$post_options['curl-ver'] = 'NA';
		}

		if( stripos( site_url(), 'https' ) !== false || stripos( get_option('siteurl'), 'https' ) !== false || isset($_SERVER['HTTPS']) ){
			$post_options['ssl'] = true;
		} else {
			$post_options['ssl'] = false;
		}

		$post_details['wpsc-options'] = $post_options;

		foreach ($wpscpost_wpsc_options as $key => $value){
			$post_details['wpsc-options'][$key] = $_POST['wpsc-options'][$key];
		}


		$post_args = array(
			'timeout' => 300,
			'httpversion' => '1.1',
			'body' => $post_details
		);


		// do some SSL sanity.
		if( !isset($wpsc_sanity['https']) ){
			$wpsc_sanity['https'] = wpsc_ssl_connection_test();
		}
		if( $wpsc_sanity['https'] == 'NO_CA' ){
		 	$post_args['sslverify'] = false;
		} elseif( $wpsc_sanity['https'] == 'NO_CURL' ) {
			add_filter('use_curl_transport', '__return_false');
		}


		$post_result = wp_remote_post($post_url, $post_args);
		$result = wpsc_check_post_info('create-site', $post_url, $post_details, $post_result) ; // check response from the server


		if( $result['result'] != 'OK' ){
			#wpsc_display_error( print_r($result['info'],true) );
			$error=true;
		} else { // success!


			delete_transient('wpstagecoach_sanity');

			wpstagecoach_show_staging_site_links( array( 'stage-site' => WPSTAGECOACH_STAGE_SITE ), $wpsc, true );

			wpsc_display_sftp_login( $_POST['wpsc-create-stageurl'].'.wpstagecoach.com', $_POST['wpsc-create-liveurl'] );
			echo wpsc_display_feedback_form('create',
			array(
				'wpsc-stage' => $_POST['wpsc-create-stageurl'].'.wpstagecoach.com',
				'wpsc-live' => $_POST['wpsc-create-liveurl'],
				'wpsc-user' => $wpsc['username'],
				'wpsc-key' => $wpsc['apikey'],
				'wpsc-step' => $_POST['wpsc-step'],
				'wpsc-dest' => $_POST['wpsc-options']['dest'],
				)
			);
			if( is_file( WPSTAGECOACH_DB_FILE ) )
				unlink( WPSTAGECOACH_DB_FILE );
			if( is_file( WPSTAGECOACH_TAR_FILE ) )
				unlink( WPSTAGECOACH_TAR_FILE );

			// update the wpstagecoach options so we see the import menu
			$wpsc['staging-site'] = $_POST['wpsc-create-stageurl'] . WPSTAGECOACH_DOMAIN;
			$wpsc['live-site'] = $_POST['wpsc-create-liveurl'];
			if( ! update_option('wpstagecoach', $wpsc) ){
				$msg = '<p>' . __( 'Could not update the WordPress option for wpstagecoach.  This shouldn\'t happen.', 'wpstagecoach' );
				$msg .= __( 'You might consider checking your database\'s consistency.', 'wpstagecoach' );
				$msg .= __( 'Please contact <a href="https://wpstagecoach.com/support" target="_blank">WP Stagecoach support</a> for help.</p>', 'wpstagecoach' );
				wpsc_display_error($msg, false);
			}
		}  // end of $result['result'] OK/BAD

	} else { //  $_POST['wpsc-cleanup']  is set, and we have to figure out what to do with it

		if ( $_POST['wpsc-cleanup'] == 'No' ) {
			// something went wrong and we are stopping and displaying a feedback form

			$errmsg  = '<p>' . __( 'Oh no!  We couldn\'t finish creating your staging site. :-(', 'wpstagecoach' );
			$errmsg .= __( 'Please give us as much information as you can in the feedback form below, and WP Stagecoach support will investigate.', 'wpstagecoach' ) . '</p>';
			wpsc_display_error($errmsg);

			// clean up our temporary staging site link	
			unset($wpsc['staging-site']);
			update_option('wpstagecoach', $wpsc);

			echo wpsc_display_feedback_form('create error',
			array(
				'wpsc-stage' => $_POST['wpsc-create-stageurl'].'.wpstagecoach.com',
				'wpsc-live' => $_POST['wpsc-create-liveurl'],
				'wpsc-user' => $wpsc['username'],
				'wpsc-key' => $wpsc['apikey'],
				'wpsc-step' => $_POST['wpsc-step'],
				'wpsc-dest' => $_POST['wpsc-options']['dest'],
				), ''
			);
			return false;
		} elseif( $_POST['wpsc-cleanup'] == 'Yes' ) { // things are great!

			// put up a reminder to re-enable step nonces if they are disabled!
			if( isset( $wpsc['disable-step-nonce'] ) && $wpsc['disable-step-nonce'] == true ){
				echo '<div class="wpstagecoach-warn">' . __( 'Please <a href="https://wpstagecoach.com/question/did-we-just-take-our-last-step-together/" target="_blank" rel="no-follow">remember to re-enable step nonces</a> (under the Advanced menu)!', 'wpstagecoach' ) . '</div>';
			}
			
			/***************************************************************************************
			 *                                    delete old stuff                                 *
			 ***************************************************************************************/
			if(file_exists(WPSTAGECOACH_DB_FILE)) unlink(WPSTAGECOACH_DB_FILE);
			if(file_exists(WPSTAGECOACH_TAR_FILE)) unlink(WPSTAGECOACH_TAR_FILE);
			delete_transient('wpstagecoach_sanity');

			// update the wpstagecoach options so we see the import menu
			$wpsc['staging-site'] = $_POST['wpsc-create-stageurl'].WPSTAGECOACH_DOMAIN;
			$wpsc['live-site'] = $_POST['wpsc-create-liveurl'].WPSTAGECOACH_DOMAIN;

			if( ! update_option('wpstagecoach', $wpsc) ){
				$msg = '<p>' . __( 'Could not update the WordPress option for wpstagecoach.  This shouldn\'t happen.', 'wpstagecoach' );
				$msg .= __( 'You might consider checking your database\'s consistency.', 'wpstagecoach' );
				$msg .= __( 'Please contact <a href="https://wpstagecoach.com/support" target="_blank">WP Stagecoach support</a> for help.', 'wpstagecoach' ) . '</p>';
				wpsc_display_error($msg, false);
			}


			echo wpsc_display_feedback_form('create',
			array(
				'wpsc-stage' => $_POST['wpsc-create-stageurl'].'.wpstagecoach.com',
				'wpsc-live' => $_POST['wpsc-create-liveurl'],
				'wpsc-user' => $wpsc['username'],
				'wpsc-key' => $wpsc['apikey'],
				'wpsc-step' => $_POST['wpsc-step'],
				'wpsc-dest' => $_POST['wpsc-options']['dest'],
				)
			);

		} elseif( !empty($_POST['wpsc-cleanup']) ){  //  weird error -- "wpsc-cleanup" is neither Yes nor No...
			$errmsg  = '<p>' . __( 'The "$_POST[\'wpsc-cleanup\']" variable is set and contains a value that wasn\'t recognized.</p>', 'wpstagecoach' ) . '</p>';
			$errmsg .= __( 'Please report this problem to <a href="https://wpstagecoach.com/support" target="_blank">WP Stagecoach support</a> and include the following information:<br/>', 'wpstagecoach' );
			$errmsg .= '<pre>'.print_r($_POST, true).'</pre></p>';
			wpsc_display_error($errmsg);
			// clean up our temporary staging site link	
			unset($wpsc['staging-site']);
			update_option('wpstagecoach', $wpsc);

			return false;	
		}
	} // end of $_POST['wpsc-cleanup'] being set





	if( isset($error) && $error == true ){
		$normal=false;
		$nextstep = 7;
		$errmsg  = '<p>' . __( 'Problem communicating with the staging server.', 'wpstagecoach' ) . '</p>';
		if( isset($result['info']) && !empty($result['info']) ){
			$errmsg .= '<p>' . __( 'We got the following information back from the server: ', 'wpstagecoach' ) . '</p>';
			if( is_array($result) && isset($result['info']) )
				$errmsg .= '<b>'.print_r($result['info'],true).'</b>'.PHP_EOL;
			else
				$errmsg .= '<b><pre>'.print_r($result,true).'</pre></b>'.PHP_EOL;
		}
		$errmsg .= '<p>' . __( 'Would you like to try again?', 'wpstagecoach' ) . '</p>';
		wpsc_display_error($errmsg);

		echo '<form method="POST" id="wpsc-step-form" class="wpstagecoach-update-step-nonce-form">';
		echo '<input type="submit" class="button submit-button wpstagecoach-update-step-nonce" name="wpsc-retry-create" value="' . __( 'Yes', 'wpstagecoach' ) . '">';	
		echo '<input type="submit" class="button submit-button wpstagecoach-update-step-nonce" name="wpsc-cleanup" value="' . __( 'No', 'wpstagecoach' ) . '">';	

		$sqlsize = wpsc_size_suffix( filesize( WPSTAGECOACH_DB_FILE ) );
		$tarsize = wpsc_size_suffix( filesize( WPSTAGECOACH_TAR_FILE ) );
		echo '<p>' . sprintf( __( 'Please note, the SQL file is %s in size, and the tar file is %s in size.', 'wpstagecoach' ), $sqlsize, $tarsize ) . '</p>';
		if( (filesize(WPSTAGECOACH_DB_FILE)/1048576) > 50 )
			__( '<p>The SQL file is abnormally large. This may be the cause of the failure.</p>', 'wpstagecoach' );
		if( (filesize(WPSTAGECOACH_TAR_FILE)/1048576) > 300 )
			__( '<p>The tar file is abnormally large. This may be the cause of the failure.</p>', 'wpstagecoach' );
	
	} elseif( isset($_POST['wpsc-cleanup']) && $_POST['wpsc-cleanup'] != 'No' ) { // things are great!

		/***************************************************************************************
		 *                                    delete old stuff                                 *
		 ***************************************************************************************/
		if(file_exists(WPSTAGECOACH_DB_FILE)) unlink(WPSTAGECOACH_DB_FILE);
		if(file_exists(WPSTAGECOACH_TAR_FILE)) unlink(WPSTAGECOACH_TAR_FILE);
		delete_transient('wpstagecoach_sanity');


		echo wpsc_display_feedback_form('create',
		array(
			'wpsc-stage' => $_POST['wpsc-create-stageurl'].'.wpstagecoach.com',
			'wpsc-live' => $_POST['wpsc-create-liveurl'],
			'wpsc-user' => $wpsc_user_name,
			'wpsc-key' => $wpsc_api_key,
			'wpsc-step' => $_POST['wpsc-step'],
			'wpsc-dest' => $_POST['wpsc-options']['dest'],
			)
		);


	}
	if( !isset($error) )
		return;
} // end of step 7

/*******************************************************************************************
*                                                                                          *
*                                End of stepping form                                      *
*                                                                                          *
*******************************************************************************************/

echo str_pad( '', 65536 ) . PHP_EOL;
ob_flush();
flush();
if( WPSC_DEBUG ){
	fclose( $create_log );
	sleep(2);
} else {
	sleep(1);
}



echo end_stepping_form( $nextstep, $wpscpost_fields, $wpscpost_wpsc_options, $normal );


//    feedback form for repeat problems
if(isset($error) && $error == true && isset($_POST['wpsc-retry'])){
	$msg  = '<p>' . __( 'It looks like you ran into problems creating your staging site.', 'wpstagecoach' ) . '</p>';
	$msg .= '<p>' . __( 'Sorry to hear that!  Please provide feedback with any error messages below.', 'wpstagecoach' ) . '</p>';
	echo wpsc_display_feedback_form('create',
	array(
		'wpsc-stage' => $_POST['wpsc-create-stageurl'].'.wpstagecoach.com',
		'wpsc-live' => $_POST['wpsc-create-liveurl'],
		'wpsc-user' => $wpsc['username'],
		'wpsc-key' => $wpsc['apikey'],
		'wpsc-dest' => $_POST['wpsc-options']['dest'],
		),
	$msg
	);
}

function end_stepping_form( $nextstep, $wpscpost_fields, $wpscpost_wpsc_options, $normal ){

	$wpscpost_fields['wpsc-step'] = $nextstep;

	$end_form = '';

	if( $normal === true ){
		$end_form .= '<form style="display: hidden"  method="POST" id="wpsc-step-form">'.PHP_EOL;
	}

	$wpsc_nonce = wpsc_set_step_nonce( 'create', $nextstep );   // set a transient with next-step and a nonce
	$end_form .= '  <input type="hidden" name="wpsc-nonce" id="wpsc-nonce" value="' . $wpsc_nonce . '"/>' . PHP_EOL;
	$end_form .= '  <input type="hidden" name="wpsc-type" value="create"/>' . PHP_EOL;

	foreach ($wpscpost_fields as $key => $value)
		$end_form .= '  <input type="hidden" name="'.$key.'" value="'.$value.'"/>'.PHP_EOL;

	if( !empty($wpscpost_wpsc_options) )
		foreach ($wpscpost_wpsc_options as $key => $value)
			$end_form .= '  <input type="hidden" name="wpsc-options['.$key.']" value="'.$value.'"/>'.PHP_EOL;

	if( $normal === true ){
		$end_form .= '</form>'.PHP_EOL;
		$end_form .= '<script>'.PHP_EOL;
		$end_form .= 'document.forms["wpsc-step-form"].submit();'.PHP_EOL;
		$end_form .= '</script>'.PHP_EOL;
	} else {
		$end_form .= '</form>'.PHP_EOL;
	}

	return $end_form;
} // end of end_stepping_form()

function wpsc_build_tar_list_rec( $dir, $list, $exclude_list='' ){
	/*********************************************************************************
	*	Scan the filesystem and make a list of all files on it.
	*	requires
	*		dir -- current directory we are scanning in
	*		list -- the list we are building up
	*	return value:
	*		list -- the full list we have found so far
	*********************************************************************************/
	global $special_dirs;
	global $slow_server;
	global $BINARY_FILE_LIST;
	$dir_list = scandir( $dir );
	unset( $dir_list[ array_search('.', $dir_list) ]);
	unset( $dir_list[ array_search('..', $dir_list) ]);
	if( $slow_server ){
		//  if the user checks optimize for a slow server, we're only going to look under wp-content and skip everything else
		$dir_list = array( 'wp-content' );
		if( is_file( 'wp-config.php' ) ){
			$dir_list[] = 'wp-config.php';
		}
		// we only want to change the dir_list once, after that we want it to run as normal.
		$slow_server = false;
	}
	if( empty( $dir_list ) ){
		$list[] = $dir;
	} else {
		foreach( $dir_list as $entry ){
			if ( $dir . '/' . $entry != rtrim( WPSTAGECOACH_REL_TEMP_DIR, '/') ) {

				//  if there is an exclude list, we need to check if the current dir (without the leading ./) is in the array
				if( !empty( $exclude_list ) && in_array( ltrim( $dir . '/' . $entry, './' ), $exclude_list ) ){
					continue;
				}
				if( empty( $BINARY_FILE_LIST ) ){
						if(   is_dir(  $dir . '/' . $entry ) &&
							! is_link( $dir . '/' . $entry ) && // don't want to actually copy the contents of symlinked dirs
							! is_file( $dir . '/' . $entry.'/wp-config.php' )  // don't need to archive other sub-WP directories
						){
							if( 'cache' != $entry ){
								$list = wpsc_build_tar_list_rec( $dir . '/' . $entry, $list, $exclude_list );
							}
						} elseif ( is_file( $dir . '/' . $entry ) ||
							(is_link( $dir . '/' . $entry ) && !is_dir( $dir . '/' . $entry )) &&
							!($dir == 'cache' && strpos($entry, 'timthumb') ) // don't want to copy timthumb caches
						){
							$fsize = @filesize( $dir . '/' . $entry );
							if( $fsize ){  // if we don't get a file size back, we shouldn't add it.
								if( $fsize > WPSTAGECOACH_LARGE_FILE ){ // 10MB
									$list['largefiles'][] = $dir . '/' . $entry;
								} else{
									$list[] = $dir . '/' . $entry;
								}
								$list['totalsize'] += $fsize;
								if( WPSC_DEBUG ){
									global $list_of_file_sizes;
									$list_of_file_sizes[$dir . '/' . $entry] = $fsize;
								}
							}
						} elseif( is_file( $dir . '/' . $entry.'/wp-config.php' ) ) {
							echo '<p>' . __( 'I am not backing up the WordPress install in: ', 'wpstagecoach' ) . $dir . '/' . $entry.'</p>'.PHP_EOL;
						} elseif( is_dir( $dir . '/' . $entry ) &&
							is_link( $dir . '/' . $entry )
						) {
							echo '<p>' . __( 'I am not backing up this symlink to a dir: ', 'wpstagecoach' ) .$dir . '/' . $entry.'</p>'.PHP_EOL;
						} else {
							echo '<p>' . __( 'I have no clue what this is: ', 'wpstagecoach' ) .$dir . '/' . $entry.'</p>'.PHP_EOL;
						}				

				} else {
					if( strpos( $dir, 'wp-includes' ) || ! preg_match( '/' . $BINARY_FILE_LIST . '/i', $entry ) ){
						if( isset( $special_dirs[$dir][$entry] ) && $special_dirs[$dir][$entry] == true ){
							// echo 'not backing this up yet: '.$dir . '/' . $entry.'<br/>';
						} elseif( is_dir( $dir . '/' . $entry ) &&
							!is_link( $dir . '/' . $entry ) && // don't want to actually copy the contents of symlinked dirs
							!is_file( $dir . '/' . $entry.'/wp-config.php' ) &&  // don't need to archive other sub-WP directories

							$dir . '/' . $entry != './' . WPSTAGECOACH_REL_CONTENT . '/uploads/' // not copying uploads dir for now

						){
							if( 'cache' != $entry ){
								$list = wpsc_build_tar_list_rec( $dir . '/' . $entry, $list, $exclude_list );
							}
						} elseif ( is_file( $dir . '/' . $entry ) ||
							(is_link( $dir . '/' . $entry ) && !is_dir( $dir . '/' . $entry )) &&
							!($dir == 'cache' && strpos($entry, 'timthumb') ) // don't want to copy timthumb caches
						){
							$fsize = @filesize($dir . '/' . $entry);

							if( $fsize !== false ){  // if we don't get a vlid file size back, we won't add it.
								if( $fsize > 10485760 ){ // 10MB
									$list['largefiles'][] = $dir . '/' . $entry;
								} else {
									$list[] = $dir . '/' . $entry;
								}
								$list['totalsize'] += $fsize;
								if( WPSC_DEBUG ){
									global $list_of_file_sizes;
									$list_of_file_sizes[ $dir . '/' . $entry ] = $fsize;
								}
							}
						} elseif( $dir . '/' . $entry == './' . WPSTAGECOACH_REL_CONTENT . '/uploads/' ) { // default uploads dir
							echo '<p>' . __( 'Because we are hotlinking images, we are not copying: ', 'wpstagecoach' ) .$dir . '/' . $entry.'</p>'.PHP_EOL;
						} elseif( is_file( $dir . '/' . $entry.'/wp-config.php' ) ) {
							echo '<p>' . __( 'We are not copying the WordPress install in: ', 'wpstagecoach' ) . $dir . '/' . $entry.'</p>'.PHP_EOL;
						} elseif( is_dir( $dir . '/' . $entry ) &&
							is_link( $dir . '/' . $entry )
						) {
							echo '<p>' . __( 'We are not copying this symlink to a dir: ', 'wpstagecoach' ) .$dir . '/' . $entry.'</p>'.PHP_EOL;
						} else {
							echo '<p>' . __( 'I have no clue what this is: ', 'wpstagecoach' ) .$dir . '/' . $entry.'</p>'.PHP_EOL;
						}
					}

				} // end of if empty(BINARY_FILE_LIST)
			}
		}
	}
	return $list;
} // end of wpsc_build_tar_list_rec()

function wpsc_append_special_dirs_to_list( $list ){
	/*********************************************************************************
	*	Adds a list of all files from directories which require all files to be transferred to staging site
	*	requires
	*		$list -- list of files to create tar file from
	*	return value:
	*		$list -- extended list of files to create tar file from
	*********************************************************************************/
	global $special_dirs;
	if( defined( 'WP_CONTENT_DIR' ) ){
		echo '';
	}
	$themedir = get_theme_root();

	if( !is_dir( $themedir ) ){
		echo WPSTAGECOACH_WARNDIV . '<p>It looks like you are missing the default theme directory "' . $themedir . '".</p>';
		echo '<p>If you are not expecting this, you should contact WP Stagecoach support and give them this error message.</p>';
		echo '</div>';
		return;
	}	
	$themes = glob( $themedir.'*', GLOB_ONLYDIR );


	foreach ( $themes as $theme ) {
		if( is_file( $themedir . $theme . '/screenshot.png' ) )
			$list[] = $themedir . $theme . '/screenshot.png';
	}

	foreach ( $special_dirs as $basedir => $array ) {
		foreach ( $array as $dir => $value  ) {
			if( file_exists( $basedir . '/' . $dir ) )
				$list = wpsc_build_tar_list_rec_no_exclusions( $basedir . '/' . $dir, $list );
		}
	}
	return $list;
} // end wpsc_append_special_dirs_to_list()

function wpsc_build_tar_list_rec_no_exclusions( $dir, $list ){
	/*********************************************************************************
	*	Does stuff
	*	requires
	*		none
	*	return value:
	*		none
	*********************************************************************************/

	$dir_list = scandir( $dir );
	unset( $dir_list[ array_search('.', $dir_list) ]);
	
	unset( $dir_list[ array_search('..', $dir_list) ]);
	foreach( $dir_list as $entry ){
		switch (filetype( $dir . '/' . $entry )) {
		 	case 'file':
		 		$list[] = $dir . '/' . $entry;
		 		break;
		 	case 'dir':
		 		$list = wpsc_build_tar_list_rec_no_exclusions( $dir . '/' . $entry, $list );
		 		break;
		 	case 'link':
		 		if( !is_dir( $dir . '/' . $entry ) ) // still don't want to back up symlink dirs
		 			$list[] = $dir . '/' . $entry;
		 		break;
		 	default:
		 		echo '<p>' . __( 'I have no clue what this is: ', 'wpstagecoach' ) .$entry.'</p>'.PHP_EOL;
		 		break;
		}
	}
	return $list;
} // end wpsc_build_tar_list_rec_no_exclusions()

