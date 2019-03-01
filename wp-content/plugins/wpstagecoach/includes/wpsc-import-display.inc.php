<?php
/*
 * WP Stagecoach Version 1.4.0
 */

if ( ! defined('ABSPATH') ) {
	die('Please do not load this file directly.');
}

set_time_limit( 0 );
ini_set( 'max_execution_time', 0 );
ini_set( 'memory_limit', -1 );

/*
1)  open the changes option
2)  display them with checkboxes to allow selection
3)  make form button to start applying changes
*/

$changes = get_option('wpstagecoach_retrieved_changes');



// see how many items there are for importing, just in case they want to do the advanced import
$item_count = 0;
if( is_array( $changes ) ){ // old mysql method 
	$has_sqlite = false;
	foreach ( $changes as $key => $value ) {
		if( is_array( $value ) ){
			$changes_db_table_names[ $key ]= $key;
			foreach ( $value as $key2 => $value2 ) {
				$changes[ $key ][ $key2 ] = array_unique( $changes[ $key ][ $key2 ] );
				$item_count += sizeof( $changes[ $key ][ $key2 ] );
			}
		}
	}
} elseif( 'sqlite' == $changes ){
	// we need to create the $changes array which we'll use later on as well as give a count
	$changes = array();
	$has_sqlite = true;

	$changesdb = wpstagecoach_open_changes_file();
	$changes_db_table_names = array();

	$query = 'SELECT name FROM sqlite_master WHERE type="table";';
	$result = $changesdb->query( $query );

	while ( $row = $result->fetchArray( SQLITE3_ASSOC ) ) { // get the table names of items to import
		if( is_array( $row ) ){
			if( 'wpstagecoach_database_imports' == $row['name'] ){
				$changes_db_table_names['db'] = $row['name'];

				$clean_query = 'DELETE from wpstagecoach_database_imports where id not in (SELECT MAX(id) from wpstagecoach_database_imports group by command);';
				$query_result = $changesdb->exec( $clean_query );
			} elseif( 'wpstagecoach_file_imports' == $row['name'] ){
				$changes_db_table_names['file'] = $row['name'];

				$clean_query = 'DELETE from wpstagecoach_file_imports where id not in (SELECT MAX(id) from wpstagecoach_file_imports group by file_name);';
				$query_result = $changesdb->exec( $clean_query );
			}
		}
	}


	if( isset( $_POST['wpsc-reset-import'] ) && 'Start import over' == $_POST['wpsc-reset-import'] ){

		// we need to erase all the selected items in the sqlite db
		if( WPSC_DEBUG ){
			echo '<p>' . __( '&nbsp;&nbsp;Reseting any selected changes.', 'wpstagecoach' ) . '</p>';
		}
		foreach ( $changes_db_table_names as $table ) {
			$query = 'UPDATE ' . $table . ' set processed=null WHERE processed="selected";';
			if( ! $changesdb->exec( $query ) ){
				$errmsg  = __( 'Could not update the SQLite database while trying to reset the selected items.  Please make sure the database file ' . $dest_file . ' is writable by your website.  If it is, please contact WP Stagecoach support with this and the following message: ', 'wpstagecoach' ) . '<br/>';
				$errmsg .= 'Error: ' . $changesdb->lastErrorMsg();
				$wpsc_display_error( $errmsg );
				return;
			}
		} // end of foreach over $changes_db_table_names
	} // end of if( isset( $_POST['wpsc-reset-import'] )

	foreach ( $changes_db_table_names as $type => $table ) {
		$query = 'select * from ' . $table .';';
		$result = $changesdb->query( $query );


		while ( $row = $result->fetchArray( SQLITE3_ASSOC ) ) {
			$item_count ++;
			if( 'file' == $type ){
				if( is_array( $row ) ){
					$changes['file'][ $row['file_action'] ][] = $row['file_name'];
				}
			} elseif( 'db' == $type ){
				if( is_array( $row ) ){
					$changes['db'][ $row['type_table'] ][] = $row['command'];
				}
			}
		}
	} // end of foreach over $changes_db_table_names
	$changesdb->close();
} // end of getting data from sqlite

// set up tab'd area for basic or advanced import options
?>
<div id="wpstagecoach-import-tabs" class="wpsc_import_tabs">
	<ul>
		<li><a class="selected" href="#wpsc_import_tab1"><?php _e( 'One-Click', 'wpstagecoach' ); ?></a></li>
		<li><a href="#wpsc_import_tab2"><?php _e( 'Advanced', 'wpstagecoach' ); ?></a></li>
		<li><a href="#wpsc_import_tab3"><?php _e( 'Manual', 'wpstagecoach' ); ?></a></li>
	</ul>

	<div id="wpsc_import_tab1">
		<h3><?php _e( 'One-Click Import', 'wpstagecoach' ); ?></h3>
		<form id="wpsc-import" method="post">
			<input type="radio" name="wpsc-import-changes" value="wpsc-import-all" id="import-all" checked><label for="import-all"> <?php _e( 'Import all changes', 'wpstagecoach' ); ?></label><br/>
			<?php if( isset( $changes_db_table_names['file'] ) ){ ?>
			<input type="radio" name="wpsc-import-changes" value="wpsc-import-files" id="import-files"><label for="import-files"> <?php _e( 'Import only file changes', 'wpstagecoach' ); ?></label><br/>
			<?php } else { 
				echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . __( 'No file changes', 'wpstagecoach' ) . '<br/>';
			} ?>
			<?php if( isset( $changes_db_table_names['db'] ) ){ ?>
			<input type="radio" name="wpsc-import-changes" value="wpsc-import-db" id="import-db"><label for="import-db"> <?php _e( 'Import only database changes', 'wpstagecoach' ); ?></label><br/>
			<?php } else { 
				echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . __( 'No database changes', 'wpstagecoach' ) . '<br/>';
			} ?>
			<input type="submit" class="button submit-button" name="wpsc-import" value="Import" />
		</form>
		<p><a href="https://wpstagecoach.com/question/what-is-the-difference-between-file-changes-and-database-changes/" target="_blank">
			<?php _e( 'What is the difference between file changes and database changes?', 'wpstagecoach' ); ?>
		</a></p>
	</div>  <!--end wpsc_import_tab1 div-->



	<div id="wpsc_import_tab2">
		<h3><?php _e( 'Advanced Import', 'wpstagecoach' ); ?></h3>
		<p><?php _e( 'Advanced import lets you select which changes you want to import from the staging site.', 'wpstagecoach' ); ?></p>

		<?php
		if( $item_count > ini_get( 'max_input_vars' ) ){
			echo '<p>' . sprintf( '<div class="wpstagecoach-warn">' . __( 'The number of changes you have (%d) is greater than the number of changes your server reports to accept (%d) for an advanced import. If you select more than %d changes, your server will report an error. You can ask your webhost to adjust the "max_input_vars" PHP variable if you need to use this method.  You should only use this page if you really know what you are doing and what your server is capable of!', 'wpstagecoach' ) . '</div>', $item_count, ini_get( 'max_input_vars' ), ini_get( 'max_input_vars' ) ) . '</p>'.PHP_EOL;
		}

		if( false == $changes ){
			$msg = __( 'We were unable to retrieve the changes from the WordPress database. Please try again.  If you continue to have problems, contact <a href="https://wpstagecoach.com/support" target="_blank">WP Stagecoach support</a>.'. 'wpstagecoach' ).PHP_EOL;
			wpsc_display_error( $msg );
			return false;
		}


		// pull out file changes and put them into a string.
		if( isset( $changes['file'] ) && ! empty( $changes['file'] ) ){

			$file_output = '<p><input type="checkbox" name="files" id="checkFiles" class="overview"> <label for="checkFiles">' . __( 'Import all file changes', 'wpstagecoach' ) . '</label></p>';
			$file_output .= '<p><a href="#" onclick="toggle_visibility(\'files\');">' . __( 'or select specific file changes to import', 'wpstagecoach' ) . '</a></p>';

			$file_output .= '<div id="files" style="display: none;">';
			$file_output .= '<fieldset id="wpsc_files">';


			foreach ( $changes['file'] as $action_type => $file_list){
				if( !empty( $file_list ) ){		
					$display_file_output=1;
					$file_output .= '<h4>'.$action_type.' files</h4>'."\n";
					$file_output .= '<fieldset id="wpsc_'.$action_type.'_files">';
					$file_output .= make_select_all_links("",'wpsc_'.$action_type.'_files');
					foreach ($file_list as $file) {
						$file_output .= '<input type="checkbox" class="file overview" name="wpsc-' . $action_type . '[]" value="' . base64_encode( $file ) . '" id="' . base64_encode( $file ) . '"><label for="' . base64_encode( $file ) . '"> ' . $file . "</label><br/>\n";
					}
				}
				$file_output .= '</fieldset>'; // end wpsc_'.$action_type.'_files fieldset
			} ##  end of action_type foreach loop
			unset($changes['file']);
			$file_output .= '</fieldset>';   //  end of wpsc_files fieldset
			$file_output .= '</fieldset>';   //  end of all fieldset
			$file_output .= '<fieldset id="wpsc_all">';
		} // end file changes -- printout below


		// pull out database changes and put them into a string.
		if( isset( $changes['db'] ) && is_array( $changes['db'] ) ){
			$display_db_output = 1;

			$db_output = '<p><input type="checkbox" name="database" id="checkTables" class="overview"> <label for="checkTables">' . __( 'Import all database changes', 'wpstagecoach' ) . '</label></p>'.PHP_EOL;
			$db_output .= '<p><a href="#" onclick="toggle_visibility(\'tables\');">' . __( 'or select specific database changes to import', 'wpstagecoach' ) . '</a></p>'.PHP_EOL;

			$db_output .= '<div id="tables" style="display: none;">';
			$db_output .= '<fieldset id="wpsc_db">';
			$db_output .= '<p class="info"><b>' . __( 'WARNING! Selecting some database changes but not others can leave your site in an uncertain (broken) state.  Only do this if you know what you are doing!', 'wpstagecoach' ) . '</b></p>';
			$db_output .= __( 'The following tables have had changes made in them:', 'wpstagecoach' ) . '<br/>';
			foreach ( $changes['db'] as $table_name => $table ) {
				$db_output .= '<h4>' . __( 'table:', 'wpstagecoach' ) . $table_name . '</h4>';

				$db_output .= make_select_all_links("","wpsc_db_$table_name");
				$db_output .= '<fieldset id="wpsc_db_'.$table_name.'">';
				foreach ( $table as $row ) {

					if ( ! empty( $row ) ) {

						if( $has_sqlite ){
							$row = htmlspecialchars( $row );
							$html_row = base64_encode( $row );
						} else {
							$html_row = htmlspecialchars( $row ); // already base64_encoded
							$row = htmlspecialchars( base64_decode( $row ) );
						}
						
						$db_output .= '<input type="checkbox" class="table overview" name="wpsc-db[]" value="' . $html_row . '" id="' . $html_row . '"> <label for="' . $html_row . '">';
						switch (  strtoupper( esc_html( substr( $row, 0, 6 ) ) ) ){
							case 'INSERT':
								switch( $table_name ){
									case 'usermeta':
										$db_output .= "insert: ";
										$output = preg_split('/.[)(]/i', $row);
										$column = preg_split('/,/i', $output[1]);
										$value = preg_split('/,/i', $output[3]);
										$db_output .= "'".trim($value[1], " \t\n\r\0\x0B'`" )."'=>'";
										if( isset( $value[2]) )
											$db_output .= trim($value[2], " \t\n\r\0\x0B'`" )."', for user_id ";
										$db_output .= trim($value[0], " \t\n\r\0\x0B'`" )."\n";
									break;
									case 'posts':
										$db_output .= "new post: '";
										$output = preg_split('/.[)(]/i', $row);
										$column = preg_split('/,/i', $output[1]);
										$value = preg_split('/,/i', $output[3]);
										if( isset( $value[5]) )
											$db_output .= substr( trim($value[5], " \t\n\r\0\x0B'`" ), 0, 50 )."', by user_id:";
										$db_output .= trim($value[0], " \t\n\r\0\x0B'`" )."'\n";

									break;						
									case 'postmeta':
										$db_output .= "insert: ";
										$output = preg_split('/.[)(]/i', $row);
										$column = preg_split('/,/i', $output[1]);
										$value = preg_split('/,/i', $output[3]);
										$db_output .= "'".trim($value[1], " \t\n\r\0\x0B'`" )."'=>'";
										if( isset( $value[2]) )
											$db_output .= trim($value[2], " \t\n\r\0\x0B'`" )."', for user_id ";
										$db_output .= trim($value[0], " \t\n\r\0\x0B'`" )."\n";
									break;
									default:
										$db_output .= "insert into ";
										$insert  = preg_split( '/INTO /i', $row );
										$table   = explode( ' ', $insert[1] );
										$table   = array_shift( $table );
										$table   = trim( $table, " \t\n\r\0\x0B'`" );
										$db_output .= $table . ': ';
										$insert  = preg_split( '/VALUES/i', $row );

										$columns = preg_split( '/\) | \(/i', $insert[0] );
										if( isset( $columns[1] ) ){
											$columns = explode( ',', $columns[1] );
										} else {
											$columns[0] = '';
										}
										$values  = preg_split( '/\) | \(/i', $insert[1] );
										if( isset( $values[1] ) ){
											$values  = explode( ',', $values[1]);
										} else {
											$values[0] = '';
										}


										if( sizeof($columns) == sizeof($values) ){
											$cols = sizeof($columns);
										} elseif(sizeof($columns) > sizeof($values)){
											$cols = sizeof($values);
										} elseif(sizeof($values) > sizeof($columns)){
											$cols = sizeof($columns);
										}
										for ($i=0; $i < $cols; $i++) { 
											$db_output .= substr($columns[ $i ], 0, 20) . ' => ' . substr($values[ $i ], 0, 30) . ', ';
										}
									break;
								}
								$db_output .= '</label><br/>'.PHP_EOL;
								break;
							case 'UPDATE':
								$db_output .= 'modify ';
								switch($table_name){
									case 'posts':
										$output = preg_split('/SET/i', $row);
										$output = preg_split('/\', `/', (string)$output[1]);
										if( sizeof($output) > 2 ){
											$db_output .= trim($output[4], " \t\n\r\0\x0B'`" ).'", ';
											$db_output .= '"'.trim(substr($output[2],0,50), " \t\n\r\0\x0B'`" ).'...", ';
											$output = preg_split('/WHERE/i', $row);
											$output = preg_split('/=/', (string)$output[1]);
											$db_output .= " where: \"";
											$db_output .= substr(trim($output[0], " \t\n\r\0\x0B'`" ), 0, 20) . "\" = \"";
											$db_output .= substr(trim($output[1], " \t\n\r\0\x0B'`" ), 0, 40);
											$db_output .= '"</label><br/>'.PHP_EOL;
										} else {
											$output = preg_split('/SET/i', $row);
											$db_output .= substr($output[1], 0, 60);
											$db_output .= '"</label><br/>'.PHP_EOL;
										}

									break;
									default:
										$modify = preg_split('/SET/i', $row);
										$table  = preg_split('/ /', (string)$modify[0]);
										$table  = trim($table[1], " \t\n\r\0\x0B'`" );
										$value  = preg_split('/=/', (string)$modify[1]);
										#$value = trim($value[0], " \t\n\r\0\x0B'`" );
										$where  = preg_split('/WHERE/i', $row);
										if( isset( $where[1] ) ){
											$where  = preg_split('/=/', (string)$where[1]);
										}
										$db_output .= $table . ': ' ;
										$db_output .= ( isset( $where[1] ) ) ? ' where "' . substr(trim($where[0], " \t\n\r\0\x0B'`" ), 0, 50) . '" = "' . trim($where[1], " \t\n\r\0\x0B'`" ) . '" ' : ' ';
										$db_output .= 'set "' . trim($value[0], " \t\n\r\0\x0B'`" ) . '" = "' . substr(trim($value[1], " \t\n\r\0\x0B'`" ), 0,40) . '..."';
										if( 0 && isset($output[1]) && isset($output[2])){
											$output = preg_split('/WHERE/i', $output[1].'='.$output[2]);
											$output = preg_split('/=/', (string)$output[1]);
											$db_output .= " where: \"";
											$db_output .= trim($output[0], " \t\n\r\0\x0B'`" )."\" = \"";
											if( isset( $output[1] ) ) {
												$db_output .= trim($output[1], " \t\n\r\0\x0B'`" );
											} else {
												echo '<hr/><pre>';
												print_r($row);
												echo '</pre><hr/>';	
											}
										} 
										$db_output .= '</label><br/>'.PHP_EOL;

									break;
								}
								break;
							case 'DELETE':
								$output = preg_split('/WHERE/i', $row);


								if( is_array( $output ) && isset( $output[1] ) ) {
									$temp = rtrim( $output[0] );
									$temp = explode( ' ', $temp );
									$db_output .= 'delete from "' . array_pop( $temp ) . '" where ' . substr( $output[1], 0, 50 );
									if( strlen( $output[1] ) > 40 ){
										$db_output .= '...';
									}
								} else {
									$db_output .= substr( $output[0], 0, 60 );
								}
								$db_output .= '</label><br/>'.PHP_EOL;
								break;
							default:
								$db_output .= $row;
								$db_output .= '</label><br/>'.PHP_EOL;
								break;
						}

					} // end of if( !empty($row) )

				} # end of $row foreach  loop
				$db_output .= '</fieldset>';  # end of table fieldset
			} # end of $table foreach loop
			$db_output .= '</fieldset>';  # end of database fieldset
			$db_output .= '</div><!-- #tables -->'.PHP_EOL;


		} // end database changes -- printout below


		//  print out all the strings created above
		if( isset( $display_file_output ) || isset( $display_db_output ) ){ ?>
			
			<form id="wpsc" method="post">
				<fieldset id="wpsc_all">
					<p><input type="checkbox" name="all" id="checkAll"> <label for="checkAll"><?php _e( 'Import all changes', 'wpstagecoach' ); ?></label></p>
					<?php if( isset($display_file_output) ){
						echo $file_output;
					}

					if( isset($display_db_output) ){
						echo $db_output;
					} ?>
				</fieldset>

				<div id="message" class="wpstagecoach-error">
					<p><b><?php _e( 'Please back up your site before you import changes from your staging site!', 'wpstagecoach' ); ?></b></p>
				</div>
				
				<input type="submit" class="button submit-button" name="wpsc-import-changes" value="<?php _e( 'Import Selected Changes', 'wpstagecoach' ); ?>" />
			</form></p>
			<?php } ?>
	</div> <!--end wpsc_import_tab2 div-->

	<div id="wpsc_import_tab3">
		<h3>Manual Import</h3>
		<p><?php _e( 'If automatic imports are not working, you might need to do a manual import. Use this page to generate the files you need for a manual import.', 'wpstagecoach' ); ?></p>
		<p><a href="https://wpstagecoach.com/manual-import/" target="_blank"><?php _e( 'Detailed instructions for manual import.', 'wpstagecoach' ); ?></a></p>
		<form id="wpsc-happiness-form" method="post">
			<?php
			add_filter( 'nonce_life', 'wpstagecoach_manual_nonce_time' );
			?>
			<input type="hidden" id="wpsc-manual-import-nonce" name="wpsc-manual-import-nonce" value="<?php echo wp_create_nonce( 'wpsc_manual_import_nonce' ); ?>">
			<input type="hidden" id="wpsc-user" name="wpsc-user" value="<?php echo $wpsc['username']; ?>">
			<input type="hidden" id="wpsc-key" name="wpsc-key" value="<?php echo $wpsc['apikey']; ?>">
			<input type="hidden" id="wpsc-live-site" name="wpsc-live-site" value="<?php echo $wpsc['live-site']; ?>">
			<input type="hidden" id="wpsc-stage-site" name="wpsc-stage-site" value="<?php echo $wpsc['staging-site']; ?>">

                            <input type="hidden" id="wpsc-site-user" name="wpsc-site-user" value="<?php echo "username"; ?>">
                            <input type="hidden" id="wpsc-live-url" name="wpsc-live-url" value="<?php echo "live-url"; ?>">

			<a class="button submit-button" id="submit-manual-import"><?php _e( 'Generate Manual Import Files', 'wpstagecoach' ); ?></a>
		</form>
		<div id="wpsc-manual-files">
			<?php 
			$post_details = array(
				'wpsc-user'			=> $wpsc['username'],
				'wpsc-key'			=> $wpsc['apikey'],
				'wpsc-ver'			=> WPSTAGECOACH_VERSION,
				'wpsc-live-site'	=> $wpsc['live-site'],
				'wpsc-stage-site'	=> $wpsc['staging-site'],
				'wpsc-live-path'	=> rtrim( ABSPATH, '/' ),
				'wpsc-dest'			=> $wpsc_sanity['auth']['server'],
			);


			foreach ( array( 'zip', 'sql' ) as $type ) {
				if( isset( $wpsc[ $type . '-manual-file' ] ) ){
					$download_url = 'https://' . $wpsc_sanity['auth']['server'] . '/wpsc-app-download-manual-file.php';
					$download_args = '?wpsc-file=' . $wpsc[ $type . '-manual-file' ] . '&' . http_build_query( $post_details );
					echo '<p>' . $type . ': <a href="' . $download_url . $download_args . '" target="_blank">' . $wpsc[ $type . '-manual-file' ] . '</a><br />' . PHP_EOL;

				}
			}
			?>
		</div>
	</div> <!--end wpsc_import_tab3 div-->

</div><!-- #import tabs -->
<?php
# used below to write select all script links
function make_select_all_links( $message, $item ){
	return 
	$message ?>
	<a rel="$item" href="#select_all">Select All</a>
	<a rel="$item" href="#select_none">Select None</a>
	<a rel="$item" href="#invert_selection">Invert Selection</a><br/>
	<?php EOF;
} 
