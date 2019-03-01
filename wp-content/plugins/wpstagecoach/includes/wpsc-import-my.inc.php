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
ini_set( 'max_execution_time', 0);
ini_set( 'memory_limit', -1);


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
#	decypher the arrays of base64-encoded lines and insert them into their respective 'working' area in the WP options table.
#	detect whether we need to work on files, if not, skip to the DB step (5)
#	test whether files/dir we are replacing/deleting/inserting into are writable, and warn if not.
if( isset($_POST['wpsc-step']) && $_POST['wpsc-step'] == 1 ){
	echo '<p>' . __( 'Step 1: loop through changes, store choices, and make sure everything is writable.', 'wpstagecoach' ) . '</p>';

	// set some arrays we'll be using later
	$wpsc_writable_file_test_array = array();	// list of all the files we need to see if are writable

	// we must go up from wp-admin to crawl the filesystem
	chdir( get_home_path() );


	//  if the user is doing an advanced, per-selection 
	if( $_POST['wpsc-import-changes'] == 'Import Selected Changes' ){

		// get the list of changes that we have received from the staging site so we can be sure what we've recieved in _POST is actually in here.
		$retrieved_changes = get_option('wpstagecoach_retrieved_changes');

		// decode the select files & DB from $_POST & store it in $stored_array
		foreach ( array( 'db', 'new', 'modified', 'deleted' ) as $action_type){
			if( isset( $_POST['wpsc-'.$action_type] ) && is_array( $_POST['wpsc-'.$action_type] ) ){



				// this is what we'll use to put all the selections into a new format we can to import
				$temp_array = array();

				foreach ( $_POST['wpsc-'.$action_type] as $encoded_selection ){
					$temp = base64_decode( $encoded_selection );
					if( empty( $temp ) ) { // if it doesn't successfully base64_decode, we have a problem
						$errmsg = __( 'Error: this selected post array failed to decode: ', 'wpstagecoach' ) . $encoded_selection; 
						wpsc_display_error( $errmsg );
						return;
					}

					// need to make sure $_POST arguments we received are actually in the list of changes we got back from the staging site!
					switch ( $action_type ) {
						case 'new':
						case 'modified':
						case 'deleted':
							$found = false;
							if( in_array( base64_decode( $encoded_selection ), $retrieved_changes['file'][ $action_type ] ) ){
								$found = true;
							}
							// adding the decoded file selection to the array
							$temp_array[] = base64_decode( $encoded_selection );
							break;
						case 'db':
							$found = false;
							foreach ( $retrieved_changes['db'] as $table => $retrieved_table ) {
								if( ! $found ){
									if( in_array( $encoded_selection, $retrieved_table ) ){
										$found = true;
									}
								}
							}
							// adding the encoded database selection to the array
							$temp_array[] = $encoded_selection;
							break;
					}
					//  Yikes, we didn't find a selection in the _POST that matched an entry we received from the staging site--this is probably quite bad.
					if( ! $found ){
						$errmsg  = __( 'Error: We could not find the value you selected in the list of retrieved changes.  We are going to stop now for security reasons.<br />', 'wpstagecoach' );
						$errmsg .= sprintf( __( 'For reference, this is what was provided via _POST that we could not find in the list of changes:<br /> \'%s\'', 'wpstagecoach' ), filter_var( base64_decode( $encoded_selection ), FILTER_SANITIZE_SPECIAL_CHARS ) );
						wpsc_display_error( $errmsg );
						return;
					}

					
				}

				unset($_POST['wpsc-'.$action_type]);
				$store_array[$action_type] = $temp_array;

			}
		}
		unset( $retrieved_changes );
	}


	
	// we are picking up where we left off, and we should get the selected files & DB entries back from the database
	if( $_POST['wpsc-import-changes'] == 'Continue' ){
		echo '<p>' . __( 'Continuing the import.'. 'wpstagecoach' ) . '</p>'.PHP_EOL;
		$store_array = get_option('wpstagecoach_importing_files');
		$store_array['db'] = get_option('wpstagecoach_importing_db');
	}

	// we are importing all the changes (or just all the file or DB changes), need to move all retrieved changes to the $store_array variable
	if( $_POST['wpsc-import-changes'] == 'wpsc-import-all' || $_POST['wpsc-import-changes'] == 'wpsc-import-files' || $_POST['wpsc-import-changes'] == 'wpsc-import-db' ){
		// go get all the retrieved changes
		$store_array = get_option('wpstagecoach_retrieved_changes');

		// the store_array variable expects the data in a slightly different arrangement.
		if( isset( $store_array['file'] ) && is_array( $store_array['file'] ) ){
			//  move the retrieved changes from $stored_array['file'][$action_type] to $stored_array[$action_type]
			foreach ( array('new','modified','deleted') as $action_type){
			 	if( isset( $store_array['file'][ $action_type ] ) && is_array( $store_array['file'][ $action_type ] ) ){
			 		$store_array[ $action_type ] = $store_array['file'][ $action_type ];
			 		unset( $store_array['file'][ $action_type ] );
			 	}
			}
			unset( $store_array['file'] ); // we don't need this empty entry anymore
		} // end if ! empty $store_array['file']
		if( isset( $store_array['db'] ) && is_array( $store_array['db'] ) ){
			//  move the retrieved changes from $stored_array['db'][$table] to the flat array $stored_array['db']
			foreach ($store_array['db'] as $table) {
				foreach ($table as $row) {
					if( !empty( $row) ){
						$temp_db_array[] = $row;
					}
				}
			}
			$store_array['db'] = $temp_db_array;
		} // end if ! empty $store_array['db']

		switch ($_POST['wpsc-import-changes']) {
			case 'wpsc-import-all':
				echo '<p>' . __( 'Importing ALL changes.', 'wpstagecoach' ) . '</p>'.PHP_EOL;
			break;
			case 'wpsc-import-files':
				// we are importing just the file changes, remove retrieved DB changes from the $store_array variable
				echo '<p>' . __( 'Importing file changes only.', 'wpstagecoach' ) . '</p>'.PHP_EOL;
				// delete the database changes from our working variable
				if( isset( $store_array['db'] ) ) {
					unset( $store_array['db'] );
				}
			break;
			case 'wpsc-import-db':
				// we are importing only the DB changes, need to remove retrieved files changes from the $store_array variable
				echo '<p>' . __( 'Importing database changes only.', 'wpstagecoach' ) . '</p>'.PHP_EOL;
				// delete the file changes from our working variable
				foreach ( array('new','modified','deleted') as $action_type){
				 	if( isset( $store_array[$action_type] ) ) {
						unset( $store_array[$action_type] );
					}
				}
			break;
		}
	}  //  end of simple, import-all changes option

 	// print out a quick summary of the changes select & number of items in each area.
	if( WPSC_DEBUG ){
		foreach ($store_array as $type => $sub_array) {
			echo '$store_array['.$type.'] - size: '.sizeof($sub_array).'</br>';

			if( is_array( $sub_array ) ){
				foreach ($sub_array as $subtype => $subsub_array) {
					if( is_array( $subsub_array ) ){
						echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ['.$subtype.'] - size: '.sizeof($subsub_array).'</br>';
					}
				}
			}
		}
		unset($sub_array);
		unset($subsub_array);

		// display everything in a hidden div.



		echo '<p><a href="#" onclick="toggle_visibility(\'store_array\');">show absolutely all the import items</a>';
		echo '<div id="store_array" style="display: none;">'.PHP_EOL;
		echo 'store_array:<pre>';
		print_r($store_array);
		echo '</pre>';
		echo '</div></p>'.PHP_EOL;
	}


	// check for writablity of files
	foreach ( array('new','modified','deleted') as $action_type){
		if( isset($store_array[$action_type]) && is_array( $store_array[$action_type] ) ){


				//	get a list of dirs that New files will be put in to make sure they are writable
			if( $action_type == 'modified' || $action_type == 'new' ){
				foreach ($store_array[$action_type] as $file){
					$tempdir = dirname($file);

					// need to check the parent dir of any dirs that don't exist, but not those dirs!
					while( !is_dir( $tempdir ) ){
						$tempdir = dirname( $tempdir );
					}
					$wpsc_writable_file_test_array[] = $tempdir;
				}

				//	condense the list of dirs so we don't recheck repeats
				if( is_array( $wpsc_writable_file_test_array ) )
					$wpsc_writable_file_test_array= array_unique($wpsc_writable_file_test_array);
			}


			//	get a list of Modified or Deleted files that we need to be sure we can write to 
			if( $action_type == 'modified' || $action_type == 'deleted' ){
				if( is_array( $store_array[$action_type] )){
					$wpsc_writable_file_test_array = array_merge($store_array[$action_type], $wpsc_writable_file_test_array);
				}
			}

		}
	}

	// store the selected DB changes in the database:
	if( isset( $store_array['db'] ) ){
		delete_option( 'wpstagecoach_importing_db' );
		add_option( 'wpstagecoach_importing_db', $store_array['db'], '', 'no' );
		$import_db = true;
		unset( $store_array['db'] );
	} else {
		$import_db = false;
	}
	// store the selected file changes in the database:
	if( isset($store_array['new']) || isset($store_array['modified']) || isset($store_array['deleted']) ){
		delete_option('wpstagecoach_importing_files');
		add_option('wpstagecoach_importing_files', $store_array, '', 'no' );
	}


	if ( empty($wpsc_writable_file_test_array) && !$import_db ){
		if( LOG  ) fwrite($flog, 'no files/db selected. deleting "working" option.'.PHP_EOL);
		delete_option('wpstagecoach_importing');
		echo '<p>' . __( 'You did not select any files or database changes to import!', 'wpstagecoach' ) . '</p>';
		echo '<form method="POST" id="wpsc-no-items-checked-form">';
		echo '  <input type="submit" class="submit submit-button" value="' . __( 'Go back and select some file or database changes to import', 'wpstagecoach' ) . '">';
		echo '</form>';
	} elseif ( $import_db && empty( $wpsc_writable_file_test_array ) ){
		// DB only
		if( LOG  ) fwrite($flog, 'no files--only db selected.'.PHP_EOL);
		echo '<p>' . __( 'No files selected, just database entries, moving on.', 'wpstagecoach' ) . '</p>';
		$nextstep = 5;
	} else { // We have at least files, so onto step 2.
		if( LOG  ) fwrite($flog, 'at least files selected.'.PHP_EOL);
		$nextstep = 2;
		// don't need to worry about DB stuff until later.



		$store_array_modified = false;
		// check to make sure files which are supposed to be there are, and those that shouldn't aren't.
		foreach ( array( 'new', 'modified', 'deleted' ) as $action_type)  {
			if( isset( $store_array[$action_type] ) && is_array( $store_array[$action_type] ) ){
				foreach ($store_array[$action_type] as $key => $file) {
					if( $action_type == 'new' && is_file($file) ){
						$store_array['modified'][] = $store_array['new'][ $key ];
						unset( $store_array['new'][ $key ] );
						$store_array_modified = true;
						if( LOG  ) fwrite($flog, $file.' should NOT exist, but it does'.PHP_EOL);
					} elseif( $action_type == 'modified' && !is_file($file) ){
						$store_array['new'][] = $store_array['modified'][ $key ];
						unset( $store_array['modified'][ $key ] );
						$store_array_modified = true;
						if( LOG  ) fwrite($flog, $file.' SHOULD exist, but it does not'.PHP_EOL);
					} elseif( $action_type == 'deleted' && !is_file($file) ){
						unset( $store_array['deleted'][ $key ] );
						$store_array_modified = true;
						if( LOG  ) fwrite($flog, $file.' SHOULD exist, but it does not'.PHP_EOL);
					}
				}
			}
		}

		// store the selected file changes in the database:
		if( $store_array_modified ){
			delete_option('wpstagecoach_importing_files');
			add_option('wpstagecoach_importing_files', $store_array, '', 'no' );
		}


		// go over the list of files & dirs to make sure they are writable
		foreach ($wpsc_writable_file_test_array as $file) {
			if( is_file($file) && !is_writable($file) )
				$unwritable_array[] = $file;
		}

		// writablility checks
		//	if we have files or dirs that are unwritable, give error and prompt before moving on.
		if( (isset($unwritable_array) && is_array($unwritable_array)) || ( isset($file_problem) && is_array($file_problem)) ){
			$msg = '';
			if( isset($unwritable_array) && is_array($unwritable_array) ){

				if( LOG  ) fwrite($flog, __( 'Some files or dirs are unwritable:', 'wpstagecoach' ) . print_r($unwritable_array,true).PHP_EOL);
				$msg .= '<p>' . __( 'The following files or directories you are trying to import are not writable by your webserver:', 'wpstagecoach' ).PHP_EOL;
				$msg .= '<ul>'.PHP_EOL;
				foreach ($unwritable_array as $file) {
					$msg .= '<li style="margin-left: 2em">./';
					if ( $file == '.' )
						$msg .= __( ' (the root directory of your website)', 'wpstagecoach' );
					else
						$msg .= $file;
					$msg .= PHP_EOL;
				}
				$msg .= '</ul></p>'.PHP_EOL;
				$msg .= '<p>' . __( 'WP Stagecoach cannot import changes to these files unless you make them writable by your web server.', 'wpstagecoach' ).PHP_EOL;
				$msg .= '<a href="https://wpstagecoach.com/question/permissions-need-wordpress-site/" target="_blank">' . __( 'More information about file permissions.', 'wpstagecoach' ) . '</a><br/>'.PHP_EOL;
				$msg .= __( 'You may safely leave this page and come back when you are done and reload this page (you might have to press yes to resubmit the data).', 'wpstagecoach' ) . '</p>'.PHP_EOL;
			}

			if( isset($file_problem) && is_array($file_problem) ){
				if( isset($unwritable_array) && is_array($unwritable_array) )
					$msg .= '<br/>';
				if( LOG  ) fwrite($flog, __( 'Some files are missing or unexpectedly present:', 'wpstagecoach' ) . print_r($file_problem,true).PHP_EOL);
				$msg .= '<p>' . __( 'The following files are in a state different from when the staging site was created:', 'wpstagecoach' ).PHP_EOL;

				$msg .= '<ul>'.PHP_EOL;
				foreach ($file_problem as $file) {
					$msg .= '<li style="margin-left: 2em">';
					$msg .= $file;
					$msg .= PHP_EOL;
				}
				$msg .= '</ul></p>'.PHP_EOL;
				$msg .= '<p>' . __( 'Importing may yield unexpected results, and you may not be able to totally revert the import.', 'wpstagecoach' ) . '</p>'.PHP_EOL;

			}

			$msg .= '<h3>' . __( 'Do you want to continue the import, knowing that it may not go correctly?', 'wpstagecoach' ) . '</h3>'.PHP_EOL;
			$msg .= '<p>' . __( 'Make sure you have backed up your site first!', 'wpstagecoach' ) . '</p>'.PHP_EOL;


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
			wpsc_display_error($msg.$form);

			return;
		}

	}  //  end of having post data! :-)

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


	// make the list of all the new/modified files we need to go download
	if( ! $allfiles = get_option( 'wpstagecoach_importing_files' ) ){
		$errmsg = __( 'Uh oh! There was an error retrieving the selected file entries from the WordPress database. Please re-select which changes to import and try again.', 'wpstagecoach' );
		wpsc_display_error( $errmsg );
		require_once 'wpsc-import-display.inc.php';
		return;
	}
	$tar_file_list = array();
	foreach ( array( 'new', 'modified' ) as $action_type) {
		if( isset( $allfiles[ $action_type ] ) && is_array( $allfiles[ $action_type ] ) ){
			$tar_file_list = array_merge( $tar_file_list, $allfiles[ $action_type ] );
		}
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
		$dest_file = WPSTAGECOACH_TEMP_DIR.$post_details['wpsc-file'];

		
		echo 'Downloading the file '.$post_details['wpsc-file'].'  currently...';
		echo str_pad( '', 65536 ) . PHP_EOL;
		ob_flush();
		flush();

		if( !$changes_file = fopen ($dest_file, 'w') ){
			$errmsg = sprintf( __('Error: we could not open the %s file %s for writing!', 'wpstagecoach'), $ext, $dest_file );
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

		if( !is_file($dest_file) ){
			$errmsg = sprintf( __( 'Error: we could not find the %s file %s we just wrote!  Might something be wrong with your web host?', 'wpstagecoach'), $ext, $dest_file );
			wpsc_display_error( $errmsg );
			return;
		}

		sleep(.5); // give the slower webhosts a chance to flush their writes out!
		if($ext == '.tar.gz')  // might as well get the checksum while we're here!
			$md5sum = md5_file($dest_file);

		echo 'done!<br/>'.PHP_EOL;
		echo str_pad( '', 65536 ) . PHP_EOL;
		ob_flush();
		flush();
	} // end foreach over the file & .md5 file


	// make sure the file has the correct .md5 sum -- compare it to the current dest_file (the md5 file)
	if( $md5sum != trim(file_get_contents($dest_file)) ){
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
if( isset($_POST['wpsc-step']) && $_POST['wpsc-step'] == 4 ){
	echo '<p>' . sprintf( __('Step %s: untar tar file into "temp" dir.', 'wpstagecoach' ), $_POST['wpsc-step'] ) . '</p>';
	$nextstep = 5;

	chdir(WPSTAGECOACH_TEMP_DIR); // need to do everything in the temp dir!
	require_once('Tar.php');
	$TAR_FILE = WPSTAGECOACH_TEMP_DIR.'/'.$USER.'.tar.gz';

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
		$errmsg = sprintf( __('Error: can\'t find the tar file $s. I swear it has here a second ago!', 'wpstagecoach'), $TAR_FILE );
		wpsc_display_error( $errmsg );
		if( LOG  ) fwrite($flog, 'Error: can\'t find tar file?'.PHP_EOL);
		return;
	}

	// make the list of all the new/modified files that should be in the tar file-- need to compare to the tar file's actaul contents
	if( !$allfiles = get_option('wpstagecoach_importing_files') ){
		$errmsg = __( 'Uh oh! There was an error retrieving the selected file entries from the WordPress database. Please re-select your changes to import and try again.', 'wpstagecoach' );
		wpsc_display_error(''.PHP_EOL);
		require_once 'wpsc-import-display.inc.php';
		return;
	}
	$tar_file_list = array();
	foreach ( array( 'new', 'modified' ) as $action_type) {
		if( isset( $allfiles[$action_type] ) && is_array( $allfiles[$action_type] ) ){
			$tar_file_list = array_merge( $tar_file_list, $allfiles[$action_type] );
		}
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



	if( !$alldb = get_option('wpstagecoach_importing_db') ){
		echo '<p>' . __( 'No database entries selected. Just doing file changes. Moving on.', 'wpstagecoach' ) . '</p>';
		if( LOG  ) fwrite($flog, 'no database entries select, skipping DB steps.'.PHP_EOL);
		$nextstep = 6;
	} else {
		echo '<p>' . __( 'All the files have been extracted into the temp directory. Going to backup the database next.', 'wpstagecoach' ) . '</p>'.PHP_EOL;
	}
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

	
	define('WPSTAGECOACH_DB_FILE', WPSTAGECOACH_TEMP_DIR . str_replace('/', '_', WPSTAGECOACH_LIVE_SITE) .'-backup_'.date('Y-M-d_H:i').'.sql.gz');
	require_once('wpsc-db-backup.inc.php');

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


	echo str_pad( '', 65536 ) . PHP_EOL;
	ob_flush();
	flush();



	if( !$alldb = get_option('wpstagecoach_importing_db') ){ // no need to go do DB stuff.
		$do_db = false;
		echo '<p>' . __( 'No database entries selected, just doing file changes.', 'wpstagecoach' ) . '</p>';
		if( LOG  ) fwrite($flog, 'no database entries selected, skipping DB steps.'.PHP_EOL);
	} else {
		if( is_array($alldb) ){
			$do_db = true;
			echo '<p>' . __( 'Do DB changes.', 'wpstagecoach' ) , '</p>' .PHP_EOL;
			if( LOG  ) fwrite($flog, 'Doing DB changes too'.PHP_EOL);
		} else {
			$do_db = false;
			$errmsg = __( 'Error: the stored database changes entry is bad.', 'wpstagecoach' );
			wpsc_display_error( $errmsg );
			if( LOG  ) fwrite($flog, 'Error: stored database entry is bad!'.PHP_EOL);
			$normal = false;
		}
	
	}

	$allfiles = get_option('wpstagecoach_importing_files');
	if( !$allfiles && !$do_db ){
		$errmsg = __( 'Uh oh!  There was an error retrieving the selected file entries from the WordPress database. Please re-select which changes to import and try again.', 'wpstagecoach' );
		wpsc_display_error( $errmsg );
		require_once 'wpsc-import-display.inc.php';
		return;
	}

	// move the new files into place
	chdir( get_home_path() );  // need to get into the root directory of the site
	$errmsg = '';  // need to have an error message ready to append to.
	if( LOG  ) fwrite($flog, 'start work on file changes'.PHP_EOL);
	foreach ( array('new','modified','deleted') as $action_type){

		if( isset( $allfiles[$action_type] ) && is_array( $allfiles[$action_type] ) && $normal == true  ){
			#$file_array = json_decode( base64_decode($allfiles[$action_type]), true) ;

			if( LOG  ) fwrite($flog, 'working on '.$action_type.PHP_EOL);
			foreach ($allfiles[$action_type] as $file) {

				echo 'working on '.$file.' -- it is '.$action_type.'<br/>'.PHP_EOL;
				echo str_pad( '', 65536 ) . PHP_EOL;
				ob_flush();
				flush();

				if( ($action_type == 'new' || $action_type == 'modified') && !is_file(WPSTAGECOACH_TEMP_DIR.'extract/'.$file) ){
					$badfile = WPSTAGECOACH_TEMP_DIR.'extract/'.$file;
					$errmsg = '<p>' . sprintf( __( 'Whoa! The file %s, which we just extracted, does not appear to be there.', 'wpstagecoach' ), $badfile ).PHP_EOL;
					$errmsg .= __( 'Something strange is happening in the filesystem! WP Stagecoach will not continue importing because it will lead to unexpected results.', 'wpstagecoach' ) . '<br/>';
					$errmsg .= __( 'Please write down exactly what happened, and what you did before you got this error message, and contact <a href="https://wpstagecoach.com/support" target="_blank">WP Stagecoach support</a> with the information.', 'wpstagecoach' ) . '</p>'.PHP_EOL;
					wpsc_display_error($errmsg);
					return;
				}

				if( $action_type == 'modified' || $action_type == 'deleted' ){
					$moved = '';
					// move away old file
					if( is_file($file) ){
						if( !rename( $file, $file.'.wpsc_temp' ) ){
							echo '<p>' . sprintf( __( 'Error: could not rename %s file %s to temp file name %s.wpsc_temp.  Please check permissions on this directory: %s', 'wpstagecoach' ), $action_type, $file, $file, dirname($file) ) . '<p>';
							$moved = false;
							$normal = false;
							// the file that should be there isn't, we need to make a record of it.
							if( LOG  ) fwrite($flog, 'Failed moving '.$action_type.' file '.$file.' to include .wpsc_temp name.'.PHP_EOL);
						} else {
							$moved=true;
						}
					} elseif( !is_file($file) ) {
						$errmsg .= '<p>' . sprintf( __( 'The file %s does not appear to exist, but it should!', 'wpstagecoach' ), $file ) . '</p>';
						if( $action_type == 'modified' ){
							$errmsg .= '<p>' . __( 'We are going to try to move the file from the staging site to here anyway.', 'wpstagecoach' ) . '</p>';
							 #rename( WPSTAGECOACH_TEMP_DIR.'extract/'.$file, $file );
						} else {
							$errmsg .= '<p>' . __( 'We were just going to delete it anyway, but it is odd that it disappeared.', 'wpstagecoach' ) . '</p>' .PHP_EOL;
						}
					}

					if($action_type == 'modified' && $moved !== false ) {
						$moved = true;
					}
				}


				if( ($action_type == 'new' || ($action_type == 'modified' && $moved == true )) && !is_file($file) ){
					unset($moved);
					// make parent directory if it doesn't exist.
					$dir = dirname($file);
					while( !is_dir( $dir ) ){
						$dir_arr[] = $dir;
						$dir = dirname($dir);
					}
					if( isset($dir_arr) && is_array($dir_arr) ){
						sort($dir_arr);
						foreach ($dir_arr as $dir) {
							mkdir( $dir );
						}
						unset($dir_arr);
						unset($dir);
					}
					// move new file
					if( $normal == true && !rename( WPSTAGECOACH_TEMP_DIR.'extract/'.$file, $file )){
						echo '<p>' . sprintf( __( 'Error: could not move the %s file from "./wp-content/plugins/wpstagecoach/temp/extract/%s" to "./%s".  Please check permissions on this directory: %s', 'wpstagecoach' ), $action_type, $file, $file, dirname($file) ) . '<p>';
						$moved = false;
						$normal = false;
						if( LOG  ) fwrite($flog, 'Failed moving '.$action_type.' file '.$file.' into '.dirname($file).'.'.PHP_EOL);
					}
					if(!$normal){
						break;
					}
				} elseif( is_file($file) ){
					// the file that should be there isn't, we need to make a record of it.
					$errmsg .= '<p>' . sprintf( __( 'The file %s <b>should not exist</b>, but it does.  We are going to try to rename it to "%s.wpsc-temp" and put the new file from the staging site in its place.', 'wpstagecoach' ), $file, $file ) . '</p>'.PHP_EOL;
					rename( $file, $file.'.wpsc_temp' );
					rename( WPSTAGECOACH_TEMP_DIR.'extract/'.$file, $file );
					// todo -- maybe change file from 'new' to 'modified' action_type?
				}
			}

			if(!$normal)
				break;
		} else{
			if( LOG  ) fwrite($flog, $action_type.' is empty'.PHP_EOL);
		}
	}


	if( !empty($errmsg) ){
		$msg = '<p>' . __( 'Some files are not in the state WP Stagecoach expected. We have done our best to figure out what needed to happen, but just in case, here is what we found:', 'wpstagecoach' ) . '</p>' .PHP_EOL;
		wpsc_display_error($msg.$errmsg);
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
	if( $do_db && $normal == true ){
		foreach ( $alldb as $key => $row ) {

			$row = base64_decode( $row );
			$wpdb->query( $row );

		} // end foreach over $alldb
	}


	// store the next step in the database in case we get logged out!
	delete_option('wpstagecoach_importing');
	add_option('wpstagecoach_importing', 7, '', 'no');

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

	echo '<p>' . sprintf( __('Step %s: confirm and clean up!', 'wpstagecoach' ), $_POST['wpsc-step'] ) . '</p>';

	echo '<p>' . __( 'Your changes have been imported! Please go check your site and make sure everything is as you expected.', 'wpstagecoach' ) . '</p>'.PHP_EOL;
	echo '<a target="_blank" href="'.get_site_url().'">' . __( 'Check your site', 'wpstagecoach' ) . '</a></p>'.PHP_EOL;

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
	echo '<p>' . __( 'If you need to revert your database, you can find a backup of your database here:', 'wpstagecoach' ) . WPSTAGECOACH_TEMP_DIR.'wpsc-db-backup.sql.gz';
	echo __( 'To revert the database, follow the instructions for <a href="https://wpstagecoach.com/manual-import/#database" target="_blank">manually importing your database</a>.', 'wpstagecoach' ) . '</p>';

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

	if( isset( $_POST['wpsc-everythings-peachy'] ) || isset( $_POST['wpsc-everythings-peachy-delete'] ) ){
		// things are good!  clean up after ourselves

		echo '<p>' . sprintf( __('Step %s: everything is good!', 'wpstagecoach' ), $_POST['wpsc-step'] ) . '</p>';
		if( LOG  ) fwrite($flog, 'cleaning up after ourselves!'.PHP_EOL);

		chdir( get_home_path() );
		$allfiles = get_option('wpstagecoach_importing_files');
		if( $allfiles === false ){
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


		// clean up old DB file
		if( file_exists(WPSTAGECOACH_TEMP_DIR .'wpsc-db-backup.sql.gz') )
			unlink(WPSTAGECOACH_TEMP_DIR .'wpsc-db-backup.sql.gz');

		// clean up old extraction directories
		if( is_dir(WPSTAGECOACH_TEMP_DIR .'extract') )
			wpsc_rm_rf(WPSTAGECOACH_TEMP_DIR.'extract');

		// we're not working, and happy with what we've got, so we'll assume we're done with the changes
		delete_option('wpstagecoach_retrieved_changes');

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
		$allfiles = get_option('wpstagecoach_importing_files');
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
	} else {
		$errmsg  = __( "I don't understand what you are trying to do.  Here is some information that might help WP Stagecoach support: ", 'wpstagecoach' );
		$errmsg .= print_r( $_POST, true );
		wpsc_display_error( $errmsg );
		return false;
	}

	// we're no longer importing
	delete_option('wpstagecoach_importing');
	delete_option('wpstagecoach_importing_db');
	delete_option('wpstagecoach_importing_files');

	// clean up changes tar file
	if( file_exists(WPSTAGECOACH_TEMP_DIR .$USER.'.tar.gz') )
		unlink(WPSTAGECOACH_TEMP_DIR .$USER.'.tar.gz');
	if( file_exists(WPSTAGECOACH_TEMP_DIR .$USER.'.tar.gz.md5') )
		unlink(WPSTAGECOACH_TEMP_DIR .$USER.'.tar.gz.md5');

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


