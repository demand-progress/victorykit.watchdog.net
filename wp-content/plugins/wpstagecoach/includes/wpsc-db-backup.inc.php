<?php
/*
 * WP Stagecoach Version 1.4.0
 */

if ( ! defined('ABSPATH') ) {
	die('Please do not load this file directly.');
}


if( !defined( 'WPSTAGECOACH_DB_FILE' ) ){
	define('WPSTAGECOACH_DB_FILE', WPSTAGECOACH_TEMP_DIR . str_replace('/', '_', WPSTAGECOACH_LIVE_SITE) .'-backup_'.date('Y-M-d_H:i').'.sql.gz');
}

set_time_limit(0);
ini_set("max_execution_time", "0");
ini_set('memory_limit', '-1');

_e( '<p>Creating database backup.</p>', 'wpstagecoach' );
echo str_pad( '', 65536 ) . PHP_EOL;
ob_flush();
flush();

if( isset($_POST['wpsc-force-utf8']) && $_POST['wpsc-force-utf8'] == true ){
	define('DB_CHARSET', 'utf8');
} else {

	if( !defined('DB_CHARSET') ){
		$tables = $wpdb->get_results('SHOW tables', ARRAY_N);

		foreach ($tables as $table) {
			$res = $wpdb->get_results('SHOW CREATE TABLE '.$table[0], ARRAY_N);

			$res = $res[0][1];

			$pos = strpos($res, 'CHARSET');
			$substr = ltrim(substr($res, ($pos+7) )); //ltrim to remove any potential spaces
			$substr = ltrim(substr($substr, 1 )); //remove =
			if( !isset($charset) ){
				$charset = $substr;
			} elseif( $substr != $charset ) { ?>

				<div class="wpstagecoach-error">
					<p><?php _e( 'Your Database Character Set (DB_CHARSET) is not defined in your <b>wp-config.php</b> file as is the standard for WordPress.', 'wpstagecoach' ); ?></p>
					<p><?php _e( 'Unfortunately, WP Stagecoach was not able to determine the character set used in your database.  We found conflicting results between tables.', 'wpstagecoach' ); ?></p>
					<p><?php _e( 'You will need to find what character set your database uses and set it in your wp-config.php file. See the <a href="http://codex.wordpress.org/Editing_wp-config.php#Database_character_set">WordPress Codex</a> for more information.', 'wpstagecoach' ); ?></p>
					<p><?php _e( 'Here is a list of each table\'s character set:', 'wpstagecoach' ); ?>
						<?php foreach ($tables as $table) {
							$res = $wpdb->get_results('SHOW CREATE TABLE '.$table[0], ARRAY_N);
							$res = $res[0][1];
							$pos = strpos($res, 'CHARSET');
							$substr = substr($res, $pos ); 
							echo '<li>' . __( 'Table: ', 'wpstagecoach' ). $table[0].' '.$substr.'</li>';
						} ?></p>
					<p><?php _e( 'If you can not determine what to do, you can contact <a href="https://wpstagecoach.com/support">WP Stagecoach support</a>, and we may be able to help.', 'wpstagecoach' ); ?>
				</div>
				
				<p><?php _e( 'Because WordPress has used the UTF-8 character set since version 2.2, you may wish to just create all tables on the staging site with the UTF-8 character set.  However, this might cause some character encoding problems if you import your database changes back from the staging site.', 'wpstagecoach' ); ?></p>

			 	<form method="POST" action="admin.php?page=wpstagecoach">
					<input type="submit" value="<?php _e( 'Stop importing so you can go investigate your database situation.', 'wpstagecoach' ); ?>">
				</form>
				<form method="POST" id="wpsc-step-form">
					<?php $wpscpost_fields['wpsc-force-utf8'] = true; ?>
					<input type="submit" class="button submit-button" value="<?php _e( 'Go ahead and create a staging with with UTF-8 encoding', 'wpstagecoach' ); ?>">
				</form>

				<?php 
			}
			
		}
		unset($res);
		unset($tables);
		unset($table);
		unset($pos);
		define('DB_CHARSET', $charset);
		return;

	}
}

// proceed with dump

//SELECT table_schema "Data Base Name", sum( data_length + index_length ) / 1024 / 1024 "Data Base Size in MB" FROM information_schema.TABLES where table_schema='wp_waxon.com'  GROUP BY table_schema;
global $wpdb;

$DB_HOST = explode(':', DB_HOST);

if(isset($DB_HOST[1]) ){
	$db = mysqli_connect($DB_HOST[0], DB_USER, DB_PASSWORD, DB_NAME, $DB_HOST[1]); // these are defined in wp-config.php
} else {
	$db = mysqli_connect($DB_HOST[0], DB_USER, DB_PASSWORD, DB_NAME); // these are defined in wp-config.php
}

if( mysqli_errno($db) ){
	wpsc_display_error( __( 'Couldn\'t connect to database "'.DB_NAME.'" on host "'.DB_HOST.'".  This should never happen. Error: ', 'wpstagecoach' ).mysqli_connect_error() );
	return;
}

if (!mysqli_set_charset($db, DB_CHARSET)) {
	$msg = '<p>' . __( 'Error loading character set "', 'wpstagecoach' ).DB_CHARSET.': '.mysqli_error($db).'</p>'.PHP_EOL;
	$msg .= '<p>' . __( 'Please find out what character set your database is using and then contact <a href="https://wpstagecoach.com/support" target="_blank">WP Stagecoach support</a>.', 'wpstagecoach' ) . '</p>';
	wpsc_display_error($msg);
	return;
}


$db_size_query = 'SELECT sum( data_length + index_length ) / 1024 FROM information_schema.TABLES where table_schema="'.DB_NAME.'";';
$res = mysqli_query($db, $db_size_query);
$temp = mysqli_fetch_row( $res );
$db_size = array_shift( $temp );
mysqli_free_result($res);
echo __( 'Database size: ', 'wpstagecoach' ) . wpsc_size_suffix( $db_size ) .'<br/>';
echo str_pad( '', 65536 ) . PHP_EOL;
ob_flush();
flush();

// get array of tables
$res = mysqli_query($db, 'SHOW FULL TABLES;');
while( $row = mysqli_fetch_row($res) )
	$tables[$row[0]] = $row[1];
mysqli_free_result($res);

//	create new gzip'd dump file

if( function_exists( 'gzopen' ) ){
	$db_fh = gzopen( WPSTAGECOACH_DB_FILE, 'w' );
} elseif( function_exists( 'gzopen64' ) ){
	$db_fh = gzopen64( WPSTAGECOACH_DB_FILE, 'w' );
}

$db_header = '-- Dump of '. DB_NAME.PHP_EOL;
$db_header .= '-- Server version '. mysqli_get_server_info($db).PHP_EOL.PHP_EOL;
$db_header .= '/*!40101 SET NAMES '.DB_CHARSET.' */;'.PHP_EOL;
$db_header .= '/*!40101 SET character_set_client = '.DB_CHARSET.' */;'.PHP_EOL;
$db_header .= PHP_EOL;


$ret = fwrite( $db_fh, $db_header );
if( !$ret ){ // unsuccessful write (0 bytes written)
	$msg = '<p>' . __( 'Error: Couldn\'t write to the file', 'wpstagecoach' ) . ' <i>'.basename(WPSTAGECOACH_DB_FILE).'</i> ' . __( 'in the directory', 'wpstagecoach' ) . ' <i>'.WPSTAGECOACH_TEMP_DIR.'</i>.</p>';
	$msg .= __( 'Check your site\'s permissions.', 'wpstagecoach' );
	wpsc_display_error($msg);
	return;	
}

// create a special view variable to add on to the end
$view_dump = '';

if( isset( $wpsc['slow'] ) && WPSC_DEBUG ) echo 'We are using a smaller number of iterations between writing to the file for the sql file to help make the database dump more reilable on low memory servers.<br/>'.PHP_EOL; 

// go through tables, row by row
foreach ($tables as $table => $table_type) {
	if( $table_type == 'VIEW' ){

		$res = mysqli_query($db, 'show create table '.$table.';' );
		$view_create = mysqli_fetch_row($res);

		// $view_create:
		// 0 => View
		// 1 => Create View
		// 2 => character_set_client
		// 3 => collation_connection


		$view_dump .= '--'.PHP_EOL.'-- Final view structure for view `'.$view_create[0].'`'.PHP_EOL.'--'.PHP_EOL.PHP_EOL;

		$view_dump .= '/*!50001 DROP TABLE IF EXISTS `'.$view_create[0].'`*/;'.PHP_EOL;
		$view_dump .= '/*!50001 DROP VIEW IF EXISTS `'.$view_create[0].'`*/;'.PHP_EOL;
		$view_dump .= '/*!50001 SET @saved_cs_client          = @@character_set_client */;'.PHP_EOL;
		$view_dump .= '/*!50001 SET @saved_cs_results         = @@character_set_results */;'.PHP_EOL;
		$view_dump .= '/*!50001 SET @saved_col_connection     = @@collation_connection */;'.PHP_EOL;

		$temp_view_dump = strstr($view_create[1], ' VIEW `', true);
		/*!50001 CREATE ALGORITHM=eg.UNDEFINED */
		$view_dump .= '/*!50001 '.strstr($temp_view_dump, ' DEFINER=', true).' */'.PHP_EOL;
		/*!50013 DEFINER=`eg.root`@`eg.localhost` SQL SECURITY DEFINER */
		$view_dump .= '/*!50001'.strstr($temp_view_dump, ' DEFINER=').' */'.PHP_EOL;
		/*!50001 VIEW `view` AS select query */;
		$view_dump .= '/*!50001'.strstr($view_create[1], ' VIEW `').' */;'.PHP_EOL;

		$view_dump .= '/*!50001 SET character_set_client      = @saved_cs_client */;'.PHP_EOL;
		$view_dump .= '/*!50001 SET character_set_results     = @saved_cs_results */;'.PHP_EOL;
		$view_dump .= '/*!50001 SET collation_connection      = @saved_col_connection */;'.PHP_EOL;
		$view_dump .= PHP_EOL;				
		
		continue;
	}

	//	get number of rows in table
	$query = 'select count(*) from '.$table.';';
	$res = mysqli_query($db, $query);
	$result = mysqli_fetch_row($res);
	if( is_array($result) ){
		$num_rows = array_shift( $result );
	} else {
		$errmsg = '<p>' . __( 'Warning: could not count rows in "' . $table . '". $result is not an array.', 'wpstagecoach' ) . '</p>' . 'result: "<pre>' . print_r( $result, true ) . '</pre>"';
		wpsc_display_error( $errmsg );
		echo str_pad( '', 65536 ) . PHP_EOL;
		ob_flush();
		flush();
		sleep(1);
		$num_rows = '(NA)';
	}
	mysqli_free_result($res);

	// add table structure + size
	$table_dump = '--'.PHP_EOL.'-- Table structure for table `'.$table.'`, size '.$num_rows.' rows'.PHP_EOL.'--'.PHP_EOL.PHP_EOL;
	// add table dropping
	$table_dump .= 'DROP TABLE IF EXISTS `'.$table.'`;'.PHP_EOL;
	// add table creation
	$query = 'show create table '.$table.';';
	$res = mysqli_query($db, $query);
	$result = mysqli_fetch_row($res);
	if( !is_array($result) ){
		wpsc_display_error( __( '<p>Error: $result is not an array!</p>', 'wpstagecoach' ).$query.'<pre>'.print_r($result,true).'</pre>');
		return false;
	}
	$table_dump .= array_pop( $result ).';'.PHP_EOL;
	if( strpos($table_dump, 'ENGINE=MyISAM') ){
		$ismyisam = true;
	}
	mysqli_free_result($res);

	$table_dump .= PHP_EOL.'--'.PHP_EOL.'-- Dumping data for table `'.$table.'`'.PHP_EOL.'--'.PHP_EOL.PHP_EOL;
	$table_dump .= 'LOCK TABLES `'.$table.'` WRITE;'.PHP_EOL;
	if( isset($ismyisam) ){
		$table_dump .= '/*!40000 ALTER TABLE `'.$table.'` DISABLE KEYS */;'.PHP_EOL;
	}

	// write out what we have so far to disk.
	fwrite($db_fh, $table_dump);
	// clean up the dump variable
	$table_dump = '';

	// select everything from each table, in $rows_from_mysql chunks
	$query_base = 'select * from '.$table.' limit ';

	if( isset($wpsc['slow']) ){
		$rows_from_mysql = 10;
		$num_of_iterations=2;
	} else {
		$rows_from_mysql = 500;
		$num_of_iterations=20;
	}

	if( ctype_digit( $num_rows ) && $num_rows > 50000 && !isset( $wpsc['mysql-dont-use-big-iterations'] ) ){
		if( WPSC_DEBUG ) echo '&nbsp;&nbsp;' . sprintf( __( 'We are using a larger number of iterations to dump table "%s"', 'wpstagecoach' ), $table ) . '<br/>' . PHP_EOL;
		$rows_from_mysql   = 10000; // number of rows we get at once from the database server
		$num_of_iterations = 1000; // number of rows we write out at once to the database file
	}

	$done = false;
	$curr_row = 0;
	$rows_left = $num_rows;
	while( ! $done ){
		$query = $query_base . ' ' . $curr_row . ', ' . $rows_from_mysql . ';';
		// get each row
		$res = mysqli_query($db, $query);


		if( $num_rows > 0 ){
			$i=0;
			while ( $i < $rows_from_mysql && $i < $rows_left) {
				$table_dump .= 'INSERT INTO '.$table.' VALUES ';
				$j=0;
				while ( ( $j < $num_of_iterations && $j < $rows_left ) && ( $i < $rows_from_mysql && $i < $rows_left ) ) { 

					$row = mysqli_fetch_row($res);
					if( !is_array($row) ){
						$errmsg = '<p>' . __( 'Error: ' . $row . ' is not an array!<br/>Query: ', 'wpstagecoach' ) . '</p>' . $query . '<pre>' . print_r( $row, true ) . '</pre>';
						wpsc_display_error( $errmsg );
						return false;
					}
					$table_dump .= '(';

					foreach ($row as $element) {
						if( ctype_digit($element) ){
							//  if the first element of our number is 0, we need to wrap it in quotes so we don't lose it.
							if( strpos($element, '0') === 0 ){
								$table_dump .= "'" . $element . "',";
							} else {
								$table_dump .= $element . ',';
							}
						} elseif( is_null($element) ) {
							$table_dump .= 'NULL,'; // return a mysql friendly null for this element
						} else {
							$table_dump .= "'";
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

	if( isset($ismyisam) )
		$table_dump .= '/*!40000 ALTER TABLE `'.$table.'` ENABLE KEYS */;'.PHP_EOL;

	$table_dump .= 'UNLOCK TABLES;'.PHP_EOL;
	@mysqli_free_result($res);

	$table_dump .= PHP_EOL;
	fwrite($db_fh, $table_dump);

}	// end $tables foreach


// append views at the end of the database dump
if( !empty($view_dump) ){
	fwrite($db_fh, $view_dump);

}

mysqli_close($db);
if( function_exists( 'gzclose' ) ){
	gzclose($db_fh);
} elseif( function_exists( 'gzclose64' ) ){
	gzclose64($db_fh);
}


$db_size = (int)filesize(WPSTAGECOACH_DB_FILE);
echo '<p>' . __( 'Finished creating compressed database backup. Size: ', 'wpstagecoach' ) . wpsc_size_suffix( $db_size ) . ' </p>';

echo str_pad( '', 65536 ) . PHP_EOL;
ob_flush();
flush();
