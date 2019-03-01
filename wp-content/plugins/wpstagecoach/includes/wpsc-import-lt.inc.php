<?php
/*
 * WP Stagecoach Version 1.4.0
 */

if ( ! defined('ABSPATH') ) {
	die('Please do not load this file directly.');
}

###                                                                                     ###
###       this file applies changes that are stored locally (via mysql storage)         ###
###                                                                                     ###

define( 'LOG', true );

set_time_limit( 0 );
ini_set( 'max_execution_time', 0 );
ini_set( 'memory_limit', -1 );


$USER = explode( '.', WPSTAGECOACH_STAGE_SITE );
$USER = array_shift( $USER );

// check to see if the site is in a subdir (or multiple subdirs) on the live site
$subdir_array =  explode( '/', WPSTAGECOACH_LIVE_SITE );
array_shift( $subdir_array );
if( !empty( $subdir_array ) ){
	$subdir = implode( '/', $subdir_array );
	unset($subdir_array);
}

//	must check if our temp dir is writable, otherwise, bad!
if( ! is_writable( WPSTAGECOACH_TEMP_DIR ) ){
	$msg = '<p><b>' . __( 'The "temp/" directory in the WP Stagecoach plugin is not writable!', 'wpstagecoach' ) . '</b>'.PHP_EOL;
	$msg .= __( 'You need to fix this before we can continue. The full path is: ', 'wpstagecoach' ) . WPSTAGECOACH_TEMP_DIR . '</p>'.PHP_EOL;
	return false;
}
if( WPSC_DEBUG ){
	echo '<pre>';
	print_r($_POST);
	echo '</pre>';
	echo str_pad( '', 65536 ) . PHP_EOL;
	ob_flush();
	flush();
	sleep(2);
};

if( !isset($_POST['wpsc-step']) || empty($_POST['wpsc-step'] ) ){
	$_POST['wpsc-step'] = 1;
} else {

	if( ! wpsc_check_step_nonce( 'import', $_POST['wpsc-nonce'] ) ){
		return false;
	}
}

if( $_POST['wpsc-step'] != 7 && $_POST['wpsc-step'] != 8 ){
	echo '<br/><hr/>'.PHP_EOL;
	_e( 'WP Stagecoach is importing your changes', 'wpstagecoach' );
	echo '<hr/><br/>'.PHP_EOL;
}


// do logging if enabled
if(LOG){
	$logname = WPSTAGECOACH_TEMP_DIR.'import.log';

	if( $_POST['wpsc-step'] == 1 ){
		if( is_file( $logname ) ){
			rename( $logname, WPSTAGECOACH_TEMP_DIR . 'import.log-' . date( 'Y-m-d_H:i', filemtime( $logname ) ) );
		}
		$flog = fopen($logname, 'a');
		fwrite($flog, '--------------------------------------------------------------------------------' . PHP_EOL);
		fwrite($flog, "starting import on " . date('Y-m-d_H:i') . PHP_EOL);
		fwrite($flog, '--------------------------------------------------------------------------------' . PHP_EOL);
		fwrite($flog, PHP_EOL . 'wpsc_sanity: ' . print_r( $wpsc_sanity, true ) . PHP_EOL ); 
	} else {
		$flog = fopen($logname, 'a');
	}

	$posttemp = array();
	foreach ($_POST as $key => $post) {
		if( is_array($post) )
			$posttemp[$key] = 'array, size: '.sizeof($post);
		elseif( is_string($post) && strlen($post) > 50)
			$posttemp[$key] = 'string, len: '.strlen($post);
		else
			$posttemp[$key] = $post;
	}
	fwrite($flog, PHP_EOL.'step '.$_POST['wpsc-step'].PHP_EOL.'$_POST:'.print_r($posttemp,true).PHP_EOL);
}


// set the current step stored in the database
delete_option('wpstagecoach_importing');
add_option('wpstagecoach_importing', $_POST['wpsc-step'], '', 'no');



/*******************************************************************************************
*                                                                                          *
*                               Beginning of stepping form                                 *
*                                                                                          *
*******************************************************************************************/

$wpscpost_fields = array(
	'wpsc-import-changes' => $_POST['wpsc-import-changes'],
);
if( !empty($_POST['wpsc-options']) )
	$wpscpost_wpsc_options = $_POST['wpsc-options'];

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
#	opens the sqlite file and checks what has been selected.
#	detect whether we need to work on files, if not, skip to the DB step (5)
#	test whether files/dir we are replacing/deleting/inserting into are writable, and warn if not.
#	check on whether files/dirs are writable
if( isset($_POST['wpsc-step']) && $_POST['wpsc-step'] == 1 ){
	echo '<p>' . __( 'Step 1: loop through changes, store choices, and make sure everything is writable.', 'wpstagecoach' ) . '</p>';
	$error = false;

	// set some arrays we'll be using later
	$wpsc_writable_file_test_array = array();	// list of all the files we need to see if are writable

	// we must go up from wp-admin to crawl the filesystem
	chdir( get_home_path() );

	$changesdb = wpstagecoach_open_changes_file();
	// get the list of tables the change DB has (file/db)
	$changes_db_table_names = array();
	$query = 'SELECT name FROM sqlite_master WHERE type="table";';
	$result = $changesdb->query( $query );

	while ( $row = $result->fetchArray( SQLITE3_ASSOC ) ) { // get the table names of items to import
		if( is_array( $row ) ){
			if( 'wpstagecoach_database_imports' == $row['name'] ){
				$changes_db_table_names[ 'db' ] = $row['name'];
			} elseif( 'wpstagecoach_file_imports' == $row['name'] ){
				$changes_db_table_names[ 'file' ] = $row['name'];
			}
		}
	}

	//  if the user is doing an advanced, per-selection 
	if( 'Import Selected Changes' == $_POST['wpsc-import-changes'] ){
		// check the SQLite DB that we have received from the staging site so we can be sure what we've recieved in _POST is actually in here.

		// decode the selected files & DB from $_POST & mark them as selected in sqlite
		foreach ( array( 'db', 'new', 'modified', 'deleted' ) as $action_type ){
			if( isset( $_POST[ 'wpsc-' . $action_type ] ) && is_array( $_POST[ 'wpsc-' . $action_type ] ) ){

				foreach ( $_POST[ 'wpsc-' . $action_type ] as $encoded_selection ){

					switch ( $action_type ) {
						case 'db':
							$table = 'wpstagecoach_database_imports';
							$column = 'command';
							$selected_tables['db'] = 'wpstagecoach_database_imports';
							break;
	
						case 'new':
						case 'modified':
						case 'deleted':
							$table = 'wpstagecoach_file_imports';
							$column = 'file_name';
							$selected_tables['file'] = 'wpstagecoach_file_imports';
							break;
						
						default:
							$error = true;
							break;
					} // end of switch over $action_type

					$query = 'SELECT count(*) FROM ' . $table . ' WHERE ' . $column . ' = "' . base64_decode( $encoded_selection ) . '";';
					$result = $changesdb->query( $query );
					$row = $result->fetchArray( SQLITE3_ASSOC );

					if( 1 == $row['count(*)'] ){
						$query = 'UPDATE ' . $table . ' set processed="selected" WHERE ' . $column . ' = "' . base64_decode( $encoded_selection ) . '";';
						if( ! $changesdb->exec( $query ) ){
							$errmsg  = __( 'Could not update the SQLite database while trying to mark the selected items.  Please make sure the database file ' . $wpsc['import-file'] . ' is writable by your website.  If it is, please contact WP Stagecoach support with this and the following message: ', 'wpstagecoach' ) . '<br/>';
							$errmsg .= 'Error: ' . $changesdb->lastErrorMsg();
							wpsc_display_error( $errmsg );
							return;
						}
					} else {  //  we didn't find a selection in the _POST that matched an entry we received from the staging site--this is probably quite bad.
						$errmsg  = __( 'Error: We could not find the value you selected in the list of retrieved changes.  We are going to stop now to make sure we don\'t break anything.<br />', 'wpstagecoach' );
						$errmsg .= sprintf( __( 'For reference, this is the selected line via _POST that we could not find in the list of changes:<br /> \'%s\'', 'wpstagecoach' ), filter_var( base64_decode( $encoded_selection ), FILTER_SANITIZE_SPECIAL_CHARS ) );
						wpsc_display_error( $errmsg );
						return;
					}
				} // end foreach over $_POST of the 4 different $action_types
			} // end of if isset for each $action_type
		} // end of foreach over the 4 different $action_types 
	} // end of 'Import Selected Changes'


	// we are importing a conglomerate of ALL changes (all, file or DB)
	if( 'wpsc-import-all'   == $_POST['wpsc-import-changes'] ||
		'wpsc-import-files' == $_POST['wpsc-import-changes'] || 
		'wpsc-import-db'    == $_POST['wpsc-import-changes']
	) {
		// here we decide which "all" changes to import.
		$selected_tables = array();
		switch ( $_POST['wpsc-import-changes'] ) {
			case 'wpsc-import-all':
				echo '<p>' . __( 'Importing ALL changes.', 'wpstagecoach' ) . '</p>'.PHP_EOL;
				if( isset( $changes_db_table_names['file'] ) )
					$selected_tables['file'] = 'wpstagecoach_file_imports';
				if( isset( $changes_db_table_names['db'] ) )
					$selected_tables['db'] = 'wpstagecoach_database_imports';
			break;
			case 'wpsc-import-files':
				echo '<p>' . __( 'Importing file changes only.', 'wpstagecoach' ) . '</p>'.PHP_EOL;
				if( isset( $changes_db_table_names['file'] ) )
					$selected_tables['file'] = 'wpstagecoach_file_imports';
			break;
			case 'wpsc-import-db':
				// we are importing only the DB changes, need to remove retrieved files changes from the $store_array variable
				echo '<p>' . __( 'Importing database changes only.', 'wpstagecoach' ) . '</p>'.PHP_EOL;
				if( isset( $changes_db_table_names['db'] ) )
					$selected_tables['db'] = 'wpstagecoach_database_imports';
			break;
		}

		$query = '';
		foreach ( $selected_tables as $table ) {

			$query = 'UPDATE ' . $table . ' set processed="selected" WHERE processed is null;';
			if( ! $changesdb->exec( $query ) ){
				$errmsg  = __( 'Could not update the SQLite database while trying to mark all the selected items.  Please make sure the database file ' . $wpsc['import-file'] . ' is writable by your website.  If it is, please contact WP Stagecoach support with this and the following message: ', 'wpstagecoach' ) . '<br/>';
				$errmsg .= 'Error: ' . $changesdb->lastErrorMsg();
				wpsc_display_error( $errmsg );
				return;
			}
		} // end of foreach over selected_tables
	}  //  end of simple, import-all changes option


	// we are doing a Continue - picking up where we left off, and we should get the selected files & DB entries back from the database
	if( 'Continue' == $_POST['wpsc-import-changes'] ){
		echo '<p>' . __( 'Continuing the import.', 'wpstagecoach' ) . '</p>'.PHP_EOL;
		$selected_tables = array();

		foreach ( $changes_db_table_names as $type => $table ) {
			switch ( $type ) {
				case 'db':
					$query = 'SELECT count(*) FROM ' . $table .' WHERE processed = "selected";';
					break;
				
				case 'file':
					$query = 'SELECT count(*) FROM ' . $table .' WHERE processed = "selected";';
					break;

				default:
					break;
			}
			$result = $changesdb->query( $query );
			$row = $result->fetchArray( SQLITE3_ASSOC );

			if( $row['count(*)'] > 0 ){
				$selected_tables[ $type ] = $table;
			}
		}
	} // end of Continue import setup

	// get information about selected items to import
	$query = '';
	$hidden_full_changes_array = array(); // list of files/dirs to check that we can write if we are importing files
	$hidden_full_changes_number = array(); // number of files per file action
	$write_permission_array = array(); // list of files/dirs to check that we can write if we are importing files
	$changes_file_array = array(); // list of files that we need to check if they exist or not.
	$total_number_file_changes = 0;
	$total_number_db_changes = 0;
	foreach ( array( 'db', 'new', 'modified', 'deleted' ) as $action_type ){
		$continue = false;
		// set up some variables based on what type of change entry we're dealing with
		switch ( $action_type ) {
			case 'db':
				if( ! isset( $selected_tables['db'] ) ){
					$continue = true;
					break;
				}
				$is_file = false;
				$table = 'wpstagecoach_database_imports';
				$column = 'command';
				$label = 'Database';
				$query_extra = ';';
				break;

			case 'new':
			case 'modified':
			case 'deleted':
				if( ! isset( $selected_tables['file'] ) ){
					$continue = true;
					break;
				}
				$is_file = true;
				$table = 'wpstagecoach_file_imports';
				$column = 'file_name';
				$label = ucfirst( $action_type ) . ' Files';
				$query_extra = ' AND file_action = "' . $action_type . '";';
				break;
			
			default:
				$error = true;
				$continue = true;
				break;
		} // end of switch over $action_type

		if( $continue ){
			continue;
		}

		//  while over the SQLite DB files & make sure all the file action_types are correct (in case files have been added/deleted since the staging site was created)
		$query = 'SELECT id,' . $column . ' FROM ' . $table . ' WHERE processed="selected"' . $query_extra;
		$result = $changesdb->query( $query );
		$num_results = 0;

		while( $row = $result->fetchArray( SQLITE3_ASSOC ) ){

			if( $is_file ){

				$change_file_action = false;
				$id = $row['id'];
				$file = $row['file_name'];
				$old_action = $action_type;
				$new_action = $action_type;

				// need to see if the files are still in the same places	
				if( $action_type == 'new' && is_file( $file ) ){
					// we have to update the row from "new" to "modified"
					if( LOG  ) fwrite( $flog, $file . ' should NOT exist, but it does' . PHP_EOL);
					$change_file_action = true;
					$key_name           = 'file_action';
					$new_action         = 'modified';
					// $new_array_key = 'modified';

				} elseif( $action_type == 'modified' && ! is_file( $file ) ){
					// we have to update the row from "modified"
					if( LOG  ) fwrite( $flog, $file . ' SHOULD exist, but it does not' . PHP_EOL);
					$change_file_action = true;
					$key_name           = 'file_action';
					$new_action         = 'new';
					// $new_array_key = 'new';

				} elseif( $action_type == 'deleted' && ! is_file( $file ) ){
					// we don't need to worry about the file already being gone - just set it up 
					if( LOG  ) fwrite( $flog, $file . ' SHOULD exist, but it does not' . PHP_EOL);
					$change_file_action = true;
					$key_name           = 'processed';
					$new_action         = 'already-deleted';
					// $new_array_key = 'deleted';
				}

				if( $change_file_action ){
					$change_query = 'UPDATE wpstagecoach_file_imports SET ' . $key_name . '="' . $new_action .'" where id = ' . $id . ';';
					$change_result = $changesdb->exec( $change_query );
					if( ! $change_result ){
						$errmsg = __( 'WP Stagecoach could not update the SQLite file - please check that your web server has write permissions to ' . $wpsc['import-file'] . ' and try again.', 'wpstagecoach' );
						wpsc_display_error( $errmsg );
						require_once 'wpsc-import-display.inc.php';
						return;
					}

				} // end of $do_changes if files aren't what they are supposed to be.
				$action_type = $new_action; // we want to set action_type to be new_action just in case it changed during above tests.

				//  now we add the file to its corrected list of the hidden full_changes_array and add it to the list of 
				if( 'already-deleted' !== $action_type ){

					if( ! isset( $wpsc['skip-write-permissions-check'] ) ){
						if( 'new' == $action_type || 'modified' == $action_type ){
							$write_permission_array[] = dirname( $row[ $column ] );
							// need to check the parent dir of any dirs that don't exist, but not those dirs!
							$tempdir = dirname( $row[ $column ] );
							while( ! preg_match( '/^.$/', $tempdir ) ){  // we keep going up until we hit the get_home_path()
								if( WPSC_DEBUG ){

								}
								if( is_dir( get_home_path() . $tempdir ) ){ // if the dir exists, we need to check it.  If it doesn't exist, we'll create it later.
									$write_permission_array[] = $tempdir;
								} elseif( WPSC_DEBUG ) {
									$non_existant_dirs[] = $tempdir;
								}
								$tempdir = dirname( $tempdir );
							}
						} //end of new/modified file test
						if( 'modified' == $action_type || 'deleted' == $action_type ){
							$write_permission_array[] = $row[ $column ];
						}
					} // end of if skip-write-permissions-check 

					$label = ucfirst( $action_type ) . ' Files';

				} else {
					continue; // we don't want to run either command if it is already-deleted
				} // end of already-deleted file
				
				// HEY YOU - remember the continue above - be careful adding code here!

				$total_number_file_changes++;
				$action_type = $old_action; // need to put this back for the next file
			} else { // end of is_file 
				$total_number_db_changes++;
			}

			$num_results++;
			if( WPSC_DEBUG ){
				$hidden_full_changes_array[ $label ][] = $row[ $column ]; // store either the file_name or the DB command to display in full mode.
			}

		} // end of while over results

		if( WPSC_DEBUG && $num_results > 0 ){  // add to quick list of number of changes
			$hidden_full_changes_number[ $label ] = $num_results;
		}

	} // end of foreach over $action_types


	if( WPSC_DEBUG && ! empty( $non_existant_dirs ) ){
		$non_existant_dirs = array_unique( $non_existant_dirs );	
		echo '<small>NOTICE: The following directories don\'t exist yet (they will be created later during import):<br/>';
		foreach ( $non_existant_dirs as $dir ) {
			echo '&nbsp;&nbsp;' . $dir. '<br/>' . PHP_EOL;
		}
		echo '</small>' . PHP_EOL;
	}


	if( $total_number_db_changes || $total_number_file_changes ){  // print out a quick summary of the changes selected & the number of items in each area.
		echo 'Number of changes selected for import:<br/>' . PHP_EOL;
		foreach ( $hidden_full_changes_number as $label => $count ) {
			echo '&nbsp;&nbsp;' . $label . ': ' . $count . '</br>';
		}
	} else { // nothing was selected. Return to Go, do not collect $200.
		if( LOG  ) fwrite( $flog, 'No files or database changes selected.  Deleting "working" option.' . PHP_EOL );
		delete_option( 'wpstagecoach_importing' );
		echo '<p>' . __( 'You did not select any files or database changes to import!', 'wpstagecoach' ) . '</p>';
		echo '<form method="POST" id="wpsc-no-items-checked-form">';
		echo '  <input type="submit" class="submit submit-button" value="' . __( 'Go back and select some file or database changes to import', 'wpstagecoach' ) . '">';
		echo '</form>';
		return;
	}


	if( WPSC_DEBUG ){ // display all selected changes.
		echo '<p><a href="#" onclick="toggle_visibility(\'store_array\');">show absolutely all the import items</a>';
		echo '<div id="store_array" style="display: none;">' . PHP_EOL;
		echo 'Full list of all selected changes:<pre>' . PHP_EOL;
		foreach ( $hidden_full_changes_array as $label => $file_array ) {
			echo '<b>' . $label . ':</b><br/>';

			foreach( $file_array as $file ){
				echo '&nbsp;&nbsp;' . html_entity_decode( $file ) . PHP_EOL ;
			}
		}
		echo '</pre>';
		echo '</div></p>' . PHP_EOL;
	} // end of WPSC_DEBUG display all selected changes.

	
	if( 0 == $total_number_file_changes ){ // no files, only database changes selected
		if( LOG  ) fwrite( $flog, 'No files selected.  Only database changes selected.' . PHP_EOL );
		echo '<p>' . __( 'No files selected, just database entries, moving onto database imports.', 'wpstagecoach' ) . '</p>';
		$nextstep = 5;
	} else { // we have at least file changes selected - we'll check on DB changes later.
		if( LOG  ) fwrite( $flog, 'Some files were selected.' . PHP_EOL );
		$nextstep = 2;


		//  now, onto check the list of files and directories to make sure we can actually modify them!
		$write_permission_array = array_unique( $write_permission_array );

		$unwritable_array = array();
		// check for writablity of files
		if( isset( $write_permission_array ) && ! isset( $wpsc['skip-write-permissions-check'] ) ){
			foreach ( $write_permission_array as $target ){
				if( ! is_dir( dirname( $target ) ) ){
					if( WPSC_DEBUG ) echo '<small>NOTICE: not adding non-existant directory: ' . $target . '<br/></small>';
				} elseif( ! file_exists( $target ) ){
					if( WPSC_DEBUG ) echo '<small>NOTICE: not adding non-existant file: ' . $target . '<br/></small>';
				} elseif( ! is_writable( $target ) ){
					$unwritable_array[] = $target;
				}
			} // end of foreach over write_permissions_array


			//	if we have files or dirs that are unwritable, give error and prompt before moving on.
			if( isset( $unwritable_array ) && is_array( $unwritable_array ) && ! empty( $unwritable_array ) ){
				$errmsg = '';

				if( LOG  ) fwrite( $flog, __( 'Some files or dirs are unwritable:', 'wpstagecoach' ) . print_r( $unwritable_array, true ) . PHP_EOL );
				$errmsg .= '<p>' . __( 'The following files or directories you are trying to import are not writable by your webserver:', 'wpstagecoach' ).PHP_EOL;
				$errmsg .= '<ul>'.PHP_EOL;
				foreach ($unwritable_array as $file) {
					$errmsg .= '<li style="margin-left: 2em">./';
					if ( $file == '.' )
						$errmsg .= __( ' (the root directory of your website)', 'wpstagecoach' );
					else
						$errmsg .= $file;
					$errmsg .= PHP_EOL;
				}
				$errmsg .= '</ul></p>'.PHP_EOL;
				$errmsg .= '<p>' . __( 'WP Stagecoach cannot import changes to these files unless you make them writable by your web server.', 'wpstagecoach' ).PHP_EOL;
				$errmsg .= '<a href="https://wpstagecoach.com/question/permissions-need-wordpress-site/" target="_blank">' . __( 'More information about file permissions.', 'wpstagecoach' ) . '</a><br/>'.PHP_EOL;
				$errmsg .= __( 'You may safely leave this page and come back when you are done and reload this page (you might have to press yes to resubmit the data).', 'wpstagecoach' ) . '</p>'.PHP_EOL;


				$errmsg .= '<h3>' . __( 'Do you want to continue the import, knowing that it may not go correctly?', 'wpstagecoach' ) . '</h3>'.PHP_EOL;
				$errmsg .= '<p>' . __( 'Make sure you have backed up your site first!', 'wpstagecoach' ) . '</p>'.PHP_EOL;


				$form = '<form method="POST" id="wpsc-unwritable-form" class="wpstagecoach-update-step-nonce-form">'.PHP_EOL;

				//  YES
				$post_fields = array(
					'wpsc-step' => $nextstep,
					'wpsc-import-changes' => $_POST['wpsc-import-changes'],
				);

				$wpsc_nonce = wpsc_set_step_nonce( 'import' );   // set a transient with next-step and a nonce
				$form .= '  <input type="hidden" name="wpsc-nonce" value="' . $wpsc_nonce . '"/>' . PHP_EOL;
				$form .= '  <input type="hidden" name="wpsc-type" value="import"/>' . PHP_EOL;
				foreach ($post_fields as $key => $value) {
					$form .= '  <input type="hidden" name="'.$key.'" value="'.$value.'"/>'.PHP_EOL;
				}
				$form .= '  <input type="submit" name="wpsc-unwritable-files" class="button submit-button wpstagecoach-update-step-nonce" value="' . __( 'Yes, charge boldly ahead!', 'wpstagecoach' ) . '">'.PHP_EOL;
				$form .= '</form>'.PHP_EOL;
				//  NO
				$form .= '<form method="POST" id="wpsc-unwritable-form">';
				$form .= '  <input type="hidden" name="wpsc-options[cleanup-import]" value="' . __( 'Yes', 'wpstagecoach' ) . '"/>'.PHP_EOL;
				$form .= '<input type="submit" class="button submit-button" name="wpsc-options[stop]" value="' . __( 'No, go back and choose different files', 'wpstagecoach' ) . '">'.PHP_EOL;
				$form .= '</form>';
				wpsc_display_error( $errmsg . $form );

				return;
			} // end of checking on $unwritable_array

			} // end of if skip-write-permissions-check

	} // end of files selected for import & setting nextstep=2

	// close our SQLite DB
	$changesdb->close();

}  // end of STEP 1

################################################################################################################################
         #####
        #     #
              #
         #####
        #
        #
        #######
################################################################################################################################
#	talk to WPSC.com and specify tar file creation--it should wait until the tar is finished
if( isset($_POST['wpsc-step']) && $_POST['wpsc-step'] == 2 ){
	echo '<p>' . sprintf( __('Step %s: talking the conductor to have new tar file created.', 'wpstagecoach' ), $_POST['wpsc-step'] ) . '</p>';
	$nextstep = 3;	
	echo str_pad( '', 65536 ) . PHP_EOL;
	ob_flush();
	flush();

	$changesdb = wpstagecoach_open_changes_file();
	$query = 'SELECT file_name FROM wpstagecoach_file_imports WHERE processed="selected" AND ( file_action = "new" OR file_action = "modified" );';
	$result = $changesdb->query( $query );

	while ( $row = $result->fetchArray( SQLITE3_ASSOC ) ) {
		$tar_file_list[] = $row['file_name'];
	}

	if( ! empty( $tar_file_list ) ){
		if( LOG  ) fwrite($flog, __( 'list of new/mod files we need to download: ', 'wpstagecoach' ).print_r($tar_file_list,true).PHP_EOL);

		//	check if given host name is taken.
		$post_url = WPSTAGECOACH_CONDUCTOR.'/wpsc-make-tar-file.php';
		$post_details = array(
			'wpsc-user'			=> $wpsc['username'],
			'wpsc-key'			=> $wpsc['apikey'],
			'wpsc-ver'			=> WPSTAGECOACH_VERSION,
			'wpsc-live-site'	=> WPSTAGECOACH_LIVE_SITE,
			'wpsc-stage-site'	=> WPSTAGECOACH_STAGE_SITE,
			'wpsc-dest'			=> WPSTAGECOACH_SERVER,
			'wpsc-file-list'	=> base64_encode(json_encode( $tar_file_list )),		
		);


		$post_args = array(
			'timeout' => 300,
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


		$post_result = wp_remote_post($post_url, $post_args );
		$result = wpsc_check_post_info('check_if_site_exists', $post_url, $post_details, $post_result) ; // check response from the server

		if(isset($result['result']) && $result['result'] == 'OK'){
			echo '<p>' . __( 'Great, we were able to successfully make the tar file.', 'wpstagecoach' ) . '</p>'.PHP_EOL;
			if( LOG  ) fwrite($flog, 'successfully talked to webserver & had tar file created.'.PHP_EOL);
		} else { // we got a bad result--it will output the reason above
			return false;
		}
	} else {
		if( LOG  ) fwrite( $flog, __( 'no new/mod files needed to download: ', 'wpstagecoach' ) . PHP_EOL);
		echo '<p>' . __( 'No new or modified files selected, going to next step.', 'wpstagecoach' ) . '</p>';
		$nextstep = 5;
	}
	
	$changesdb->close();
}  // end of STEP 2

################################################################################################################################
		 #####
		#     #
		      #
		 #####
		      #
		#     #
		 #####
################################################################################################################################
#	talk to workhorse server and download the tar file
if( isset($_POST['wpsc-step']) && $_POST['wpsc-step'] == 3 ){
	echo '<p>' . sprintf( __('Step %s: download tar file from WP Stagecoach workhorse server.', 'wpstagecoach' ), $_POST['wpsc-step'] ) . '</p>';
	$nextstep = 4;

	$post_url = 'https://'.WPSTAGECOACH_SERVER.'/wpsc-app-download-tar-file.php';
	$post_details = array(
		'wpsc-user'			=> $wpsc['username'],
		'wpsc-key'			=> $wpsc['apikey'],
		'wpsc-ver'			=> WPSTAGECOACH_VERSION,
		'wpsc-live-site'	=> WPSTAGECOACH_LIVE_SITE,
		'wpsc-stage-site'	=> WPSTAGECOACH_STAGE_SITE,
		'wpsc-conductor'	=> WPSTAGECOACH_CONDUCTOR,
		'wpsc-live-path'	=> rtrim( ABSPATH, '/' ),
		'wpsc-dest'			=> WPSTAGECOACH_SERVER,
		'wpsc-file'			=> $USER,
	);

	foreach (array('.tar.gz', '.md5') as $ext) {
		$post_details['wpsc-file'] .= $ext;
		$tar_file = WPSTAGECOACH_TEMP_DIR . $post_details['wpsc-file'];

		
		echo 'Downloading the file '.$post_details['wpsc-file'].'  currently...';
		echo str_pad( '', 65536 ) . PHP_EOL;
		ob_flush();
		flush();

		if( !$changes_file = fopen ($tar_file, 'w') ){
			$errmsg = sprintf( __('Error: we could not open the %s file %s for writing!', 'wpstagecoach'), $ext, $tar_file );
			wpsc_display_error( $errmsg );
			return;
		}

		if( $wpsc_sanity['https'] == 'ALL_GOOD' || $wpsc_sanity['https'] == 'NO_CA' ){
			$ch=curl_init($post_url);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_details));
			curl_setopt($ch, CURLOPT_TIMEOUT, 600);
			curl_setopt($ch, CURLOPT_FILE, $changes_file); // write curl response to file
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			
			if( $wpsc_sanity['https'] == 'NO_CA' ){
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			}

			$result = curl_exec($ch);
			fclose($changes_file);

			if( curl_errno($ch) ){
				$errmsg = sprintf( __( 'Error: we received an error from curl while downloading the %s file: ', 'wpstagecoach'), $ext ) . curl_error($ch);
				wpsc_display_error( $errmsg );
				return;
			}

			curl_close($ch);

			if( !$result ){ // bad result
				$errmsg = sprintf( __( 'Error: we were not able to download the %s file!', 'wpstagecoach'), $ext );
				wpsc_display_error( $errmsg );
				return;
			}

		} elseif( $wpsc_sanity['https'] == 'NO_CURL' ) {

			add_filter('use_curl_transport', '__return_false');

			$post_args = array(
				'timeout' => 120,
				'httpversion' => '1.1',
				'body' => $post_details
			);

			$post_result = wp_remote_post( $post_url , $post_args );
			if( $post_result['response']['code'] != 200  && $post_result['response']['message'] != 'OK' ){
				$errmsg = sprintf( __( ' Error: we got a bad response from the staging server while downloading the %s file. Details: ', 'wpstagecoach' ), $ext ) . print_r($post_result,true);
				wpsc_display_error( $errmsg );
				return;
			}
			if( !fwrite($changes_file,  $post_result['body'] ) ){
				$errmsg = sprintf( __( 'Error: we were not able to write to the %s file!', 'wpstagecoach'), $ext );
				wpsc_display_error( $errmsg );
				return;
			}
			fclose($changes_file);

		} else {
			$errmsg  = '<p>' . __( 'We could not determine what SSL transport methods your live site supports.', 'wpstagecoach' );
			$errmsg .= __( 'Please contact <a href="https://wpstagecoach.com/support" target="_blank">WP Stagecoach support</a> to tell us your hosting provider and the following details:', 'wpstagecoach' ) . '<pre>'. print_r($wpsc_sanity,true).'</pre>'.PHP_EOL;
			wpsc_display_error($errmsg);
			return;
		}

		if( ! is_file( $tar_file ) ){
			$errmsg = sprintf( __( 'Error: we could not find the %s file %s we just wrote!  Might something be wrong with your web host?', 'wpstagecoach'), $ext, $tar_file );
			wpsc_display_error( $errmsg );
			return;
		}

		sleep( .5 ); // give the slower webhosts a chance to flush their writes out!
		if( $ext == '.tar.gz' )  // might as well get the checksum while we're here!
			$md5sum = md5_file( $tar_file );

		echo 'done!<br/>' . PHP_EOL;
		echo str_pad( '', 65536 ) . PHP_EOL;
		ob_flush();
		flush();
	} // end foreach over the file & .md5 file


	// make sure the file has the correct .md5 sum -- compare it to the current tar_file (the md5 file)
	if( $md5sum != trim( file_get_contents( $tar_file ) ) ){
		$normal=false;
		$nextstep = 3;

		$msg = '<p>' . __( 'Error: the changes file we just downloaded appears to be corrupted! Would you like to try to download it again?', 'wpstagecoach' ) . '</p>'.PHP_EOL;
		wpsc_display_error($msg);

		echo '<form method="POST" id="wpsc-step-form" class="wpstagecoach-update-step-nonce-form">'.PHP_EOL;
		$wpsc_nonce = wpsc_set_step_nonce( 'import', $nextstep );   // set a transient with next-step and a nonce
		echo '  <input type="hidden" name="wpsc-nonce" value="' . $wpsc_nonce . '"/>' . PHP_EOL;
		echo '  <input type="hidden" name="wpsc-type" value="import"/>' . PHP_EOL;
		echo '  <input type="hidden" name="wpsc-step" value="' . $nextstep . '"/>'.PHP_EOL;
		echo '  <input type="hidden" name="wpsc-import-changes" value="Yes"/>'.PHP_EOL;
		echo '<input type="submit" class="button submit-button wpstagecoach-update-step-nonce" name="wpsc-options[retry]" value="Yes">'.PHP_EOL;
		echo '</form>';
		echo '<form method="POST" name="wpsc-import-cleanup" action="admin.php?page=wpstagecoach_import">'.PHP_EOL;
		echo '  <input type="hidden" name="wpsc-options[cleanup-import]" value="' . __( 'Yes', 'wpstagecoach' ) . '"/>'.PHP_EOL;
		echo '<input type="submit" class="button submit-button" name="wpsc-options[stop]" value="' . __( 'No', 'wpstagecoach' ) . '">'.PHP_EOL;
 
	} else {
		echo '<p>' . __( 'Successfully downloaded the changes file!', 'wpstagecoach' ) . '</p>'.PHP_EOL;
		$nextstep = 4;
	}
}  // end of STEP 3



################################################################################################################################
		#
		#    #
		#    #
		#    #
		#######
		     #
		     #
################################################################################################################################
#	untar tar file into plugin's 'temp' dir
if( isset( $_POST['wpsc-step'] ) && 4 == $_POST['wpsc-step'] ){
	echo '<p>' . sprintf( __('Step %s: untar tar file into "temp" dir.', 'wpstagecoach' ), $_POST['wpsc-step'] ) . '</p>';
	$nextstep = 5;

	chdir( WPSTAGECOACH_TEMP_DIR ); // need to do everything in the temp dir!
	require_once( 'Tar.php' );
	$TAR_FILE = WPSTAGECOACH_TEMP_DIR . '/' . $USER . '.tar.gz';

	// get an array of all the files we have from the tar file
	if( file_exists( $TAR_FILE ) ){ 
		$tar = new Archive_Tar( $TAR_FILE );
		$files_from_tar_array = $tar->listContent();
		if( !is_array($files_from_tar_array) ){
			$msg = __( 'Error: we didn\'t get a valid list of files from the tar file. Please check the file: ', 'wpstagecoach' ) .$TAR_FILE.PHP_EOL;
			wpsc_display_error($msg);
			if( LOG  ) fwrite($flog, $msg.PHP_EOL);
			return;
		} else {
			echo __( 'opened tar.', 'wpstagecoach' ) . '<br/>';
			if( LOG  ) fwrite($flog, 'successfully opened tar file.'.PHP_EOL);
			foreach ($files_from_tar_array as $file) {
				$files_from_tar[] = $file['filename'];
			}
		}
	} else{
		$errmsg = sprintf( __('Error: can\'t find the tar file %s. I swear it has here a second ago!', 'wpstagecoach'), $TAR_FILE );
		wpsc_display_error( $errmsg );
		if( LOG  ) fwrite($flog, 'Error: can\'t find tar file?'.PHP_EOL);
		return;
	}

	$changesdb = wpstagecoach_open_changes_file();

	$query = 'SELECT file_name FROM wpstagecoach_file_imports WHERE processed="selected" AND ( file_action = "new" OR file_action = "modified" );';
	$result = $changesdb->query( $query );

	while ( $row = $result->fetchArray( SQLITE3_ASSOC ) ) {
		$tar_file_list[] = $row['file_name'];
	}

	// compare them so we know we have everything we need.
	$diff_array = array_diff( $files_from_tar, $tar_file_list);
	if( !empty($diff_array) ){
		$errmsg = __( 'Error: the tar file does not have all the files we requested for import. Please try again, or contact <a href="https://wpstagecoach.com/support" target="_blank">WP Stagecoach support</a> if it happens again.', 'wpstagecoach' );
		wpsc_display_error( $errmsg ).PHP_EOL;
		if( LOG  ) fwrite($flog, 'Error: $diff_array was not empty--we are missing files we want from the tar file.'.PHP_EOL);
		return;
	} else{ 
		echo '<p>' . __( 'The tar file has all the files we asked for, yay!', 'wpstagecoach' ) . '</p>'.PHP_EOL;
		if( LOG  ) fwrite($flog, 'all the files we want are in the tar file.'.PHP_EOL);

		if ( $tar->extract('extract/') ){
			echo '<p>' . __( 'Files were extracted successfully', 'wpstagecoach' ) . '</p>'.PHP_EOL;
			if( LOG  ) fwrite($flog, 'Files were extracted successfully.'.PHP_EOL);
		} else {
			$msg = '<p>' . sprintf( __( 'We ran into a problem extracting the files to %s/extract.  Please check the permissions on that directory'), WPSTAGECOACH_TEMP_DIR ) . '</p>';
			wpsc_display_error( $msg );
			if( LOG  ) fwrite($flog, $msg.PHP_EOL);
			return;
		}

	}


	// check if we have database entries to import
	$query = 'SELECT count(*) FROM sqlite_master WHERE type="table" AND name = "wpstagecoach_database_imports";';
	$result = $changesdb->query( $query );
	$row = $result->fetchArray( SQLITE3_ASSOC );
	if( $row['count(*)'] > 0 ){
		$query = 'SELECT count(*) FROM wpstagecoach_database_imports WHERE processed="selected";';
		$result = $changesdb->query( $query );
		$row = $result->fetchArray( SQLITE3_ASSOC );
		if( $row['count(*)'] > 0 ){
			$import_db = true;
		} else {
			$import_db = false;
		}
	} else {
		$import_db = false;
	}




	if( ! $import_db ){
		echo '<p>' . __( 'No database entries selected. Just doing file changes. Skipping database backup.', 'wpstagecoach' ) . '</p>';
		if( LOG  ) fwrite($flog, 'no database entries select, skipping DB steps.'.PHP_EOL);
		$nextstep = 6;
	} else {
		echo '<p>' . __( 'All the files have been extracted into the temp directory. Going to backup the database next.', 'wpstagecoach' ) . '</p>'.PHP_EOL;
	}
	$changesdb->close();
}  // end of STEP 4


################################################################################################################################
		#######
		#
		#
		######
		      #
		#     #
		 #####
################################################################################################################################
#	back up all the 
if( isset($_POST['wpsc-step']) && $_POST['wpsc-step'] == 5 ){
	echo '<p>' . sprintf( __('Step %s: backup DB!', 'wpstagecoach' ), $_POST['wpsc-step'] ) . '</p>';
	$nextstep = 6;

	$import_time = date( 'Y-M-d_H:i' );
	update_option( 'wpstagecoach_import_time', $import_time );
	
	define( 'WPSTAGECOACH_DB_FILE', WPSTAGECOACH_TEMP_DIR . str_replace( '/', '_', WPSTAGECOACH_LIVE_SITE ) . '-backup_' . $import_time . '.sql.gz' );
	require_once( 'wpsc-db-backup.inc.php' );

	if( ! is_file(WPSTAGECOACH_DB_FILE) && filesize(WPSTAGECOACH_DB_FILE) > 1 ){
		$msg = __( 'Error: we couldn\'t create the database backup file. Please try again. Contact <a href="https://wpstagecoach.com/support" target="_blank">WP Stagecoach support</a> if it happens again.', 'wpstagecoach' ) .PHP_EOL;
		wpsc_display_error($msg);
		if( LOG  ) fwrite($flog, 'Error: tar file not saved successfully.'.PHP_EOL);
		die;
	}

	if( LOG  ) fwrite($flog, 'backed up DB.'.PHP_EOL);

	echo '<div class="wpstagecoach-info"><h4>' . __( 'We have backed up your database in its current state to the file:', 'wpstagecoach' ) . WPSTAGECOACH_DB_FILE . '</h4>'.PHP_EOL;
	echo '<p>' . __( 'Your backup file has been noted in the WP Stagecoach dashboard, in case you need it!', 'wpstagecoach' ) . '</div>'.PHP_EOL;

	echo str_pad( '', 65536 ) . PHP_EOL;
	ob_flush();
	flush();


	$wpsc['db_backup_file'] = WPSTAGECOACH_DB_FILE;
	update_option('wpstagecoach', $wpsc);

	echo 'continuing in ';
	for ($i=3; $i > 0; $i--) { 
		echo $i.' ';
		echo str_pad( '', 65536 ) . PHP_EOL;
		ob_flush();
		flush();
		sleep(1);
	}

}  // end of STEP 5




################################################################################################################################
		 #####
		#     #
		#
		######
		#     #
		#     #
		 #####
################################################################################################################################
#	moves away all the files to be replaced or deleted, move new files into place
#	if there are DB changes, we need to apply those.
if( isset($_POST['wpsc-step']) && $_POST['wpsc-step'] == 6 ){
	echo '<p>' . sprintf( __('Step %s: Loop through file lists, move old files away and move new files into place.', 'wpstagecoach' ), $_POST['wpsc-step'] ) . '</p>';
	$nextstep = 7;


	$import_time = get_option( 'wpstagecoach_import_time' );

	$import_db = false;
	$import_files = false;

	echo str_pad( '', 65536 ) . PHP_EOL;
	ob_flush();
	flush();

	if( WPSC_DEBUG ) echo 'Import time: ' . $import_time . '<br/>';

	$changesdb = wpstagecoach_open_changes_file();

	$query = 'SELECT name FROM sqlite_master WHERE type="table";';
	$result = $changesdb->query( $query );
	while ( $row = $result->fetchArray( SQLITE3_ASSOC ) ) {
		// check table names for items to import
		if( is_array( $row ) ){
			if( 'wpstagecoach_database_imports' == $row['name'] ){
				// check if we actually have database entries to import
				$query = 'SELECT count(*) FROM wpstagecoach_database_imports WHERE processed="selected";';
				$result = $changesdb->query( $query );
				$row = $result->fetchArray( SQLITE3_ASSOC );
				if( $row['count(*)'] > 0 ){
					$import_db = true;
				}
			} elseif( 'wpstagecoach_file_imports' == $row['name'] ){
				// check if we have file entries to import
				$query = 'SELECT count(*) FROM wpstagecoach_file_imports WHERE processed="selected";';
				$result = $changesdb->query( $query );
				$row = $result->fetchArray( SQLITE3_ASSOC );
				if( $row['count(*)'] > 0 ){
					$import_files = true;
				}
			}
		}
	}

	if( $import_files ){
		// move the new files into place
		chdir( get_home_path() );  // need to get into the root directory of the site
		$errmsg = '';  // need to have an error message ready to append to.
		if( LOG ) fwrite( $flog, 'starting work on file changes' . PHP_EOL );


		foreach ( array( 'new', 'modified', 'deleted' ) as $action_type ){
			if( LOG  ) fwrite( $flog, 'Working on ' . $action_type . ' files.' . PHP_EOL );

			$query = 'SELECT id,file_name FROM wpstagecoach_file_imports WHERE processed="selected" AND file_action = "' . $action_type . '";';
			$result = $changesdb->query( $query );

			while ( $row = $result->fetchArray( SQLITE3_ASSOC ) ) {

				$file = $row['file_name'];
				echo 'working on ' . $file . ' -- it is ' . $action_type . '<br/>'.PHP_EOL;
				echo str_pad( '', 65536 ) . PHP_EOL;
				ob_flush();
				flush();


				// check if the file we're going to move is actually still in the extract directory. shouldn't have to do this, but it has come up.
				if( ( $action_type == 'new' || $action_type == 'modified' ) &&
					! is_file( WPSTAGECOACH_TEMP_DIR . 'extract/' . $file )
				){
					$badfile = WPSTAGECOACH_TEMP_DIR . 'extract/' . $file;
					$errmsg .= '<p>' . sprintf( __( 'Whoa! The %s file %s, which we just extracted, does not appear to be there.', 'wpstagecoach' ), $action_type, $badfile ).PHP_EOL;
					$errmsg .= __( 'Something strange is happening in the filesystem! WP Stagecoach will not continue importing because it will lead to unexpected results.', 'wpstagecoach' ) . '<br/>';
					$errmsg .= __( 'Please write down exactly what happened, and what you did before you got this error message, and contact <a href="https://wpstagecoach.com/support" target="_blank">WP Stagecoach support</a> with the information.', 'wpstagecoach' ) . '</p>'.PHP_EOL;
					wpsc_display_error($errmsg);
					return;
				}


				// re-set these each cycle
				$move_new = false;
				$rename_old = false;

				//  set what file actions we need to perform below
				if( 'new' == $action_type ){
					if( file_exists( $file ) ){
						// change file_action to modified
						$processed_query = 'UPDATE wpstagecoach_file_imports set file_action="modified" where id=' . $row['id'];
						$processed_result = $changesdb->exec( $processed_query );
						if( $changesdb->lastErrorCode() ){
							$normal = false;
							$errmsg .= 'Could not update the SQLite database to reflect the change in file_action for "' . $file . '".<br/>';
							$errmsg .= 'We received the following error: ' . $changesdb->lastErrorMsg();
							break;
						}
					} else {
						$move_new = true;
					}

				} elseif( 'modified' == $action_type ){
					if( ! file_exists( $file ) ){
						// change file_action to new, and then just move it
						$processed_query = 'UPDATE wpstagecoach_file_imports set file_action="new" where id=' . $row['id'];
						$processed_result = $changesdb->exec( $processed_query );
						if( $changesdb->lastErrorCode() ){
							$normal = false;
							$errmsg .= 'Could not update the SQLite database to reflect the change in file_action for "' . $file . '".<br/>';
							$errmsg .= 'We received the following error: ' . $changesdb->lastErrorMsg();
							break;
						}
						$move_new = true;
					} else {
						$move_new = true;
						$rename_old = true;
					}

				} elseif( 'deleted' == $action_type ){
					if( ! file_exists( $file ) ){
						// change file_action to already deleted & move on
						$processed_query = 'UPDATE wpstagecoach_file_imports set file_action="already-deleted" where id=' . $row['id'];
						$processed_result = $changesdb->exec( $processed_query );
						if( $changesdb->lastErrorCode() ){
							$normal = false;
							$errmsg .= 'Could not update the SQLite database to reflect the change in file_action for "' . $file . '".<br/>';
							$errmsg .= 'We received the following error: ' . $changesdb->lastErrorMsg();
							break;
						}
					} else {
						$rename_old = true;
					}
				}


				// move away old file
				if( true == $rename_old ){
					if( ! rename( $file, $file . '.wpsc_temp' ) ){
						// couldn't move file - something bad is going on.
						echo '<p>' . sprintf( __( 'Error: could not move the %s file from "./wp-content/plugins/wpstagecoach/temp/extract/%s" to "./%s".  Please check permissions on this directory: %s', 'wpstagecoach' ), $action_type, $file, $file, dirname($file) ) . '<p>';
						$normal = false;
					} elseif( 'deleted' == $action_type ){
						$processed_query = 'UPDATE wpstagecoach_file_imports set processed="processed ' . $import_time . '" where id=' . $row['id'];
						$processed_result = $changesdb->exec( $processed_query );
						if( $changesdb->lastErrorCode() ){
							$normal = false;
							$errmsg .= 'Could not update the SQLite database to reflect the change in processed for "' . $file . '".<br/>';
							$errmsg .= 'We received the following error: ' . $changesdb->lastErrorMsg();
							break;
						}

					}
				}


				// move new file into place
				if( true == $move_new ){
					// make parent directory if it doesn't exist.
					$dir = dirname( $file );
					while( ! is_dir( $dir ) ){
						$dir_arr[] = $dir;
						$dir = dirname( $dir );
					}
					if( isset( $dir_arr ) && is_array( $dir_arr ) ){
						sort( $dir_arr );
						foreach ( $dir_arr as $dir ) {
							if( WPSC_DEBUG ) echo '<small>NOTICE: creating non-existant directory: ' . $dir . '</small><br/>' . PHP_EOL;
							mkdir( $dir );
						}
						unset( $dir_arr );
						unset( $dir );
					}

					// do actual move of new file
					if( ! rename( WPSTAGECOACH_TEMP_DIR . 'extract/' . $file, $file ) ){
						// couldn't move file - something bad is going on.
						echo '<p>' . sprintf( __( 'Error: could not move the %s file from "./wp-content/plugins/wpstagecoach/temp/extract/%s" to "./%s".  Please check permissions on this directory: %s', 'wpstagecoach' ), $action_type, $file, $file, dirname($file) ) . '<p>';
						$normal = false;
						if( LOG  ) fwrite($flog, 'Failed moving '.$action_type.' file '.$file.' into '.dirname($file).'.'.PHP_EOL);
					} else {
						// update that the file was moved
						$processed_query = 'UPDATE wpstagecoach_file_imports set processed="processed ' . $import_time . '" where id=' . $row['id'];
						$processed_result = $changesdb->exec( $processed_query );
						if( $changesdb->lastErrorCode() ){
							$normal = false;
							$errmsg .= 'Could not update the SQLite database to reflect the moved file "' . $file . '".<br/>';
							$errmsg .= 'We received the following error: ' . $changesdb->lastErrorMsg();
							break;
						}
					}

				}
				if( ! $normal ){
					break;
				}
			} // end of while over selected files
			if( ! $normal ){
				break;
			}
		} // end of foreach over file action_types
	} // end of $import_files import


	if( ! empty( $errmsg ) ){
		$msg = '<p>' . __( 'Some files are not in the state WP Stagecoach expected. We have done our best to figure out what needed to happen, but just in case, here is what we found:', 'wpstagecoach' ) . '</p>' .PHP_EOL;
		wpsc_display_error( $msg . $errmsg );
		echo str_pad( '', 65536 ) . PHP_EOL;
		ob_flush();
		flush();
		$normal = false;

		echo '<form method="POST" id="wpsc-files-in-abnormal-state-form" class="wpstagecoach-update-step-nonce-form">'.PHP_EOL;

		//  YES
		$post_fields = array(
			'wpsc-step' => $nextstep,
			'wpsc-import-changes' => true,
		);
		$wpsc_nonce = wpsc_set_step_nonce( 'import' );   // set a transient with next-step and a nonce
		echo '  <input type="hidden" name="wpsc-nonce" value="' . $wpsc_nonce . '"/>' . PHP_EOL;
		echo '  <input type="hidden" name="wpsc-type" value="import"/>' . PHP_EOL;
		foreach ($post_fields as $key => $value) {
			echo '  <input type="hidden" name="'.$key.'" value="'.$value.'"/>'.PHP_EOL;
		}
		echo '  <input type="submit" class="button submit-button wpstagecoach-update-step-nonce" name="wpsc-files-in-abnormal-state" value="' . __( 'Continue', 'wpstagecoach' ) . '">'.PHP_EOL;
		echo '</form>'.PHP_EOL;

		return;
	}

	// now we apply all the items from the database.
	if( $import_db && $normal == true ){

		$query = 'SELECT id,command FROM wpstagecoach_database_imports WHERE processed="selected";';
		if( ! $result = $changesdb->query( $query ) ){
			$errmsg .= 'We encountered an error with the SQLite database: ' . $changesdb->lastErrorMsg . '<br/>';
			wpsc_display_error( $errmsg );
			return;
		}

		while( $row = $result->fetchArray( SQLITE3_ASSOC ) ) {
			$wpdb->query( $row['command'] );
			$processed_query = 'UPDATE wpstagecoach_database_imports set processed="processed ' . $import_time . '" where id=' . $row['id'];
			$processed_result = $changesdb->exec( $processed_query );
			if( $changesdb->lastErrorCode() ){
				$errmsg .= 'Could not update the SQLite database to reflect the moved file "' . $file . '".<br/>';
				$errmsg .= 'We received the following error: ' . $changesdb->lastErrorMsg();
				break;
			}
		} // end of while over $import_db
	} // end of if $import_db

	if( ! empty( $errmsg ) ){
		wpsc_display_error( $msg . $errmsg );
		return;
	}


	// store the next step in the database in case we get logged out!
	delete_option('wpstagecoach_importing');
	add_option('wpstagecoach_importing', 7, '', 'no');
	$changesdb->close();
}  // end of STEP 6


################################################################################################################################
		#######
		#    #
		    #
		   #
		  #
		  #
		  #
################################################################################################################################
#	prompt user to check the live site and see if things have changed.
#	offer to revert files, or clean up after ourselves
if( isset($_POST['wpsc-step']) && $_POST['wpsc-step'] == 7 ){
	wpsc_display_sidebar();
	$import_time = get_option( 'wpstagecoach_import_time' );


	echo '<p>' . sprintf( __('Step %s: confirm and clean up!', 'wpstagecoach' ), $_POST['wpsc-step'] ) . '</p>';
	if( ! isset( $_POST['wpsc-files-in-abnormal-state'] ) ){
		echo '<p>' . __( 'Your changes have been imported! Please go check your site and make sure everything is as you expected.', 'wpstagecoach' ) . '</p>'.PHP_EOL;
		echo '<a target="_blank" href="'.get_site_url().'">' . __( 'Check your site', 'wpstagecoach' ) . '</a></p>'.PHP_EOL;
	} else {
		echo '<p>' . __( 'We ran into problems doing the import, but some changes may have been imported. Please go check your site and contact support with any error message .', 'wpstagecoach' ) . '</p>'.PHP_EOL;
		echo '<a target="_blank" href="'.get_site_url().'">' . __( 'Check your site', 'wpstagecoach' ) . '</a></p>'.PHP_EOL;

	}

	echo '<p>' . __( 'If everything looks right, press the "Clean Up" button below and WP Stagecoach will clean up after itself (we have left some temporary files ending with .wpsc_temp: Clean Up will remove all those)', 'wpstagecoach' ) . '</p>'.PHP_EOL;

	//  YES
	$post_fields = array(
		'wpsc-import-changes' => true,
	);
	foreach ( array('new','modified','deleted','db') as $action_type){
		if( !empty( $_POST['wpsc-'.$action_type] ) ){
			$post_fields['wpsc-'.$action_type] = $_POST['wpsc-'.$action_type];
		}
	}

	$wpsc_nonce = wpsc_set_step_nonce( 'import' );   // set a transient with next-step and a nonce

	echo '<form method="POST" id="wpsc-unwritable-form" class="wpstagecoach-update-step-nonce-form">'.PHP_EOL;
	foreach ($post_fields as $key => $value)
		echo '  <input type="hidden" name="'.$key.'" value="'.$value.'"/>'.PHP_EOL;
	echo '  <input type="hidden" name="wpsc-step" value="8">'.PHP_EOL;
	echo '  <input type="hidden" name="wpsc-test-one" value="1">'.PHP_EOL;
	echo '  <input type="hidden" name="wpsc-nonce" value="' . $wpsc_nonce . '"/>' . PHP_EOL;
	echo '  <input type="hidden" name="wpsc-type" value="import"/>' . PHP_EOL;
	echo '  <input type="submit" class="button submit-button wpstagecoach-update-step-nonce" name="wpsc-everythings-peachy-delete" value="' . __( 'Clean Up and Delete staging site', 'wpstagecoach' ) .'"  />'.PHP_EOL;
	echo '  <input type="submit" class="button submit-button wpstagecoach-update-step-nonce" name="wpsc-everythings-peachy" value="' . __( 'Clean Up', 'wpstagecoach' ) . '">'.PHP_EOL;

	echo '<p>' . __( 'If everything is not perfect, and you want to revert all your files back to the way they were before the import, click "Revert Files."', 'wpstagecoach' ) . '</p>'.PHP_EOL;
	echo '<p>' . __( 'If you need to revert your database, you can find a backup of your database here:', 'wpstagecoach' ) . '</p>' . $wpsc['db_backup_file'] . '<br/>' . PHP_EOL;
	echo '<p>' . __( 'To revert the database, follow the instructions for <a href="https://wpstagecoach.com/manual-import/#database" target="_blank">manually importing your database</a>.', 'wpstagecoach' ) . '</p>' . PHP_EOL;

	echo '  <input type="submit" name="wpsc-everythings-in-a-handbasket" class="button submit-button wpstagecoach-update-step-nonce" value="' . __( 'Revert Files', 'wpstagecoach' ) . '">'.PHP_EOL;
	echo '</form>'.PHP_EOL;

	return;

}  // end of STEP 7

################################################################################################################################
		 #####
		#     #
		#     #
		 #####
		#     #
		#     #
		 #####
################################################################################################################################
#	user said everything was good--we're deleting all the .wpsc_temp files!
if( isset($_POST['wpsc-step']) && $_POST['wpsc-step'] == 8 ){
	wpsc_display_sidebar();

	$import_time = get_option( 'wpstagecoach_import_time' );
	$changesdb = wpstagecoach_open_changes_file();
	$changes_db_table_names = array();
	$query = 'SELECT name FROM sqlite_master WHERE type="table";';
	$result = $changesdb->query( $query );
	$allfiles = false;
	while ( $row = $result->fetchArray( SQLITE3_ASSOC ) ) { // get the table names of items to import
		if( is_array( $row ) ){
			if( 'wpstagecoach_file_imports' == $row['name'] ){

				$query = 'SELECT file_action,file_name FROM wpstagecoach_file_imports WHERE processed="processed ' . $import_time . '";';
				$result = $changesdb->query( $query );
				while( $row = $result->fetchArray( SQLITE3_ASSOC ) ){
					$allfiles[ $row['file_action'] ][] = $row['file_name'];
				}
			}
		}
	}


	// check if we have database entries to import


	if( isset( $_POST['wpsc-everythings-peachy'] ) || isset( $_POST['wpsc-everythings-peachy-delete'] ) ){
		// things are good!  clean up after ourselves

		echo '<p>' . sprintf( __('Step %s: everything is good!', 'wpstagecoach' ), $_POST['wpsc-step'] ) . '</p>';
		if( LOG  ) fwrite($flog, 'cleaning up after ourselves!'.PHP_EOL);

		chdir( get_home_path() );

		if( false === $allfiles  ){
			// no files to clean up
		} elseif( empty( $allfiles ) ){
			$errmsg = __( 'Uh oh! There was an error retrieving the selected file entries from the WordPress database; please contact WP Stagecoach support to help you delete the .wpsc_temp files.', 'wpstagecoach ');
			wpsc_display_error( $errmsg . PHP_EOL);
			return;
		} elseif( is_array( $allfiles ) ) {
			// we have a list of files to clean up
			foreach ( array( 'new', 'modified', 'deleted' ) as $action_type){
				if( isset( $allfiles[ $action_type ] ) ){
					if( is_array( $allfiles[ $action_type ] ) ){
						// go delete the temp files
						if( $action_type == 'modified' || $action_type == 'deleted' ){
							foreach ( $allfiles[ $action_type ] as $file ){
								if( is_file( $file . '.wpsc_temp' ) ){
									unlink( $file . '.wpsc_temp' );
								} else {
									if( LOG ) fwrite($flog, 'strange. the file "' . $file . '.wpsc_temp" is missing.' . PHP_EOL );
								}
							}
						}
					}
				}
			}
		}

		// put up a reminder to re-enable step nonces if they are disabled!
		if( isset( $wpsc['disable-step-nonce'] ) && $wpsc['disable-step-nonce'] == true ){
			echo '<div class="wpstagecoach-warn">' . __( 'Please <a href="https://wpstagecoach.com/question/did-we-just-take-our-last-step-together/" target="_blank" rel="no-follow">remember to re-enable step nonces</a> (under the Advanced menu)!', 'wpstagecoach' ) . '</div>';
		}

		// clean up old extraction directories
		if( is_dir( WPSTAGECOACH_TEMP_DIR .'extract' ) )
			wpsc_rm_rf( WPSTAGECOACH_TEMP_DIR.'extract' );

		// clean up old import file
		$import_logs = scandir( WPSTAGECOACH_TEMP_DIR );
		foreach( $import_logs as $file ) {
			if( strpos( $file, 'import.log' ) !== false ){
				unlink( WPSTAGECOACH_TEMP_DIR . '/' . $file ) .'<br/>';
			}
		}

		// we're not working, and happy with what we've got, so we'll assume we're done with the changes
		delete_option( 'wpstagecoach_retrieved_changes' );

		// clean up old import db
		if( file_exists( WPSTAGECOACH_TEMP_DIR . $wpsc['import-file'] ) )
			unlink( WPSTAGECOACH_TEMP_DIR . $wpsc['import-file'] );
		if( file_exists( WPSTAGECOACH_TEMP_DIR . $wpsc['import-file'] . '.md5' ) )
			unlink( WPSTAGECOACH_TEMP_DIR . $wpsc['import-file'] . '.md5' );
		unset( $wpsc['import-file'] );

		// clean up backup DB file
		if( file_exists( $wpsc['db_backup_file'] ) )
			unlink( $wpsc['db_backup_file'] );
		unset( $wpsc['db_backup_file'] );

		if( isset( $wpsc['sqlite'] ) ){
			unset( $wpsc['sqlite'] );
		}

		update_option( 'wpstagecoach', $wpsc );


		// clear the import time
		delete_option( 'wpstagecoach_import_time' );

		if( isset( $_POST['wpsc-everythings-peachy-delete'] ) ){
			require_once( 'wpsc-delete.inc.php' );
		}
		$feedback_result = 'import_good';
		$feedback_message = __( 'You have cleaned up after the import. Did everything work properly?', 'wpstagecoach' );

	} elseif( isset( $_POST['wpsc-everythings-in-a-handbasket'] ) ){
		// things are not so good -- do a bit a triage and clean up.

		echo '<p>' . sprintf( __('Step %s: something went wrong, so we are reverting files', 'wpstagecoach' ), $_POST['wpsc-step'] ) . '</p>';
		if( LOG  ) fwrite($flog, 'Reverting files--that\'s too bad...'.PHP_EOL);

		echo '<p>' . __( 'Restoring the original files.', 'wpstagecoach' ) . '</p>'.PHP_EOL;

		if( get_option('wpstagecoach_importing_db') ){
			echo '<p>' . __( 'If you need, you may restore the database from the DB dump file: ', 'wpstagecoach' ) . $wpsc['db_backup_file'] .PHP_EOL;
			echo __( ' Follow the instructions for <a href="https://wpstagecoach.com/manual-import/#database" target="_blank">manually importing your database</a>', 'wpstagecoach' ) . '</p>';
		}
		
		// put up a reminder to re-enable step nonces if they are disabled!
		if( isset( $wpsc['disable-step-nonce'] ) && $wpsc['disable-step-nonce'] == true ){
			echo '<div class="wpstagecoach-warn">' . __( 'Please <a href="https://wpstagecoach.com/question/did-we-just-take-our-last-step-together/" target="_blank" rel="no-follow">remember to re-enable step nonces</a> (under the Advanced menu)!', 'wpstagecoach' ) . '</div>';
		}

		chdir( get_home_path() );

		if( $allfiles === false ){
			// no files to clean up
		} elseif( empty( $allfiles ) ){
			$errmsg = __( 'Uh oh! The plugin could not retreive the list of changed files from the WordPress database; it is unable to revert your files.  Please contact WP Stagecoach support to see if they can help.', 'wpstagecoach ');
			wpsc_display_error( $errmsg . PHP_EOL);
			return;
		} elseif( is_array( $allfiles ) ) {
			// we have a list of files to revert
			foreach ( array( 'new', 'modified', 'deleted' ) as $action_type){
				if( isset( $allfiles[ $action_type ] ) ){

					if( is_array( $allfiles[ $action_type ] ) ){
						// go restore the backup files
						if( $action_type == 'new' || $action_type == 'modified' ){
							foreach ( $allfiles[ $action_type ] as $file ){
								unlink( $file );
								if( sizeof( scandir( dirname( $file ) ) ) == 2 ){
									rmdir( dirname( $file ) );
									$file = dirname( $file );
									while( sizeof( scandir( dirname( $file ) ) ) == 2 ){
										rmdir( dirname( $file ) );
										$file = dirname( $file );
									}
								}
							}

						}
						if( $action_type == 'modified' || $action_type == 'deleted' ){
							foreach ($allfiles[$action_type] as $file) {
								rename($file.'.wpsc_temp', $file);
							}
						}
					}
				}
			}	
		}		

		// clean up old extraction directories
		if( is_dir(WPSTAGECOACH_TEMP_DIR .'extract') )
			wpsc_rm_rf(WPSTAGECOACH_TEMP_DIR.'extract');

		$feedback_result = 'import_revert';
		$feedback_message = __( 'Sorry to see you reverted back your file changes. Can you give us details on what didn\'t work?', 'wpstagecoach' );		
		delete_option( 'wpstagecoach_import_time' );
	} else {
		$errmsg  = __( "I don't understand what you are trying to do.  Here is some information that might help WP Stagecoach support: ", 'wpstagecoach' );
		$errmsg .= print_r( $_POST, true );
		wpsc_display_error( $errmsg );
		return false;
	}

	// we're no longer importing
	delete_option('wpstagecoach_importing');
	delete_option('wpstagecoach_importing_db');

	// clean up changes tar file
	if( file_exists( WPSTAGECOACH_TEMP_DIR .$USER.'.tar.gz' ) )
		unlink( WPSTAGECOACH_TEMP_DIR .$USER.'.tar.gz' );
	if( file_exists( WPSTAGECOACH_TEMP_DIR .$USER.'.tar.gz.md5' ) )
		unlink( WPSTAGECOACH_TEMP_DIR .$USER.'.tar.gz.md5' );

	echo wpsc_display_feedback_form( $feedback_result,
		array(
			'wpsc-stage' => $wpsc_sanity['auth']['stage-site'],
			'wpsc-live'  => $wpsc_sanity['auth']['live-site'],
			'wpsc-user'  => $wpsc['username'],
			'wpsc-key'   => $wpsc['apikey'],
			'wpsc-step' => $_POST['wpsc-step'],
			'wpsc-dest'  => $wpsc_sanity['auth']['server'],
		),
		$feedback_message );
	
	$changesdb->close();
}  // end of STEP 8

/*******************************************************************************************
*                                                                                          *
*                              End of importing step form                                  *
*                                                                                          *
*******************************************************************************************/

if( 1 ){
	echo str_pad( '', 65536 ) . PHP_EOL;
	ob_flush();
	flush();
	sleep(1);
}

if( !isset($nextstep)){
	$nextstep = 0;
	$normal = false;
}

$wpscpost_fields['wpsc-step'] = $nextstep;

if( $normal == true )
	echo '<form style="display: none"  method="POST" id="wpsc-step-form">'.PHP_EOL;

$wpsc_nonce = wpsc_set_step_nonce( 'import', $nextstep );   // set a transient with next-step and a nonce
echo '  <input type="hidden" name="wpsc-nonce" id="wpsc-nonce" value="' . $wpsc_nonce . '"/>' . PHP_EOL;
echo '  <input type="hidden" name="wpsc-type" value="import"/>' . PHP_EOL;

foreach ($wpscpost_fields as $key => $value)
	echo '  <input type="hidden" name="'.$key.'" value="'.$value.'"/>'.PHP_EOL;

if( !empty($wpscpost_wpsc_options) )
	foreach ($wpscpost_wpsc_options as $key => $value)
		echo '  <input type="hidden" name="wpsc-options['.$key.']" value="'.$value.'"/>'.PHP_EOL;

if( $normal === true ){
	echo '</form>'.PHP_EOL;
#echo '<script>alert("ready?")</script>';
	echo '<script>'.PHP_EOL;
	echo 'document.forms["wpsc-step-form"].submit();'.PHP_EOL;
	echo '</script>'.PHP_EOL;
} else { // not normal, and the step will have created its own form.
	echo '</form>'.PHP_EOL;
}

if( LOG  ){
	fclose($flog);
}


