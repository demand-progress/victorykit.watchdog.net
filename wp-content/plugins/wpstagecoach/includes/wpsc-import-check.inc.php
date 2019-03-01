<?php
/*
 * WP Stagecoach Version 1.4.0
 */

if ( ! defined('ABSPATH') ) {
	die('Please do not load this file directly.');
}

set_time_limit(0);
ini_set("max_execution_time", "0");
ini_set('memory_limit', '-1');

/*******************************************************************************************
*                                                                                          *
*                             Beginning of checking step form                              *
*                                                                                          *
*******************************************************************************************/
$wpscpost_fields = array(
	'wpsc-check-changes' => $_POST['wpsc-check-changes'],
);
if( !empty($_POST['wpsc-options']) )
	$wpscpost_wpsc_options = $_POST['wpsc-options'];


if( !empty( $_POST['wpsc-use-file'] ) ) // if we are going to use the file, we want to skip ahead to step 2 directly!
	$_POST['wpsc-step'] = 2;
$USER = explode( '.', WPSTAGECOACH_STAGE_SITE );
$USER=array_shift( $USER );

if( !isset($_POST['wpsc-step']) || empty($_POST['wpsc-step']) ){
	$_POST['wpsc-step'] = 1;
} else {
	if( ! wpsc_check_step_nonce( 'check', $_POST['wpsc-nonce'] ) ){
		return false;
	}
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
	echo '<p>' . __( 'Step 1: talking to the conductor and storing the file containing all changes.', 'wpstagecoach' ) . '</p>';

	$post_url = WPSTAGECOACH_CONDUCTOR . '/wpsc-make-changes-file.php';
	$post_details = array(
		'wpsc-user'			=> $wpsc['username'],
		'wpsc-key'			=> $wpsc['apikey'],
		'wpsc-ver'			=> WPSTAGECOACH_VERSION,
		'wpsc-live-site'	=> WPSTAGECOACH_LIVE_SITE,
		'wpsc-stage-site'	=> WPSTAGECOACH_STAGE_SITE,
		'wpsc-live-path'	=> rtrim(ABSPATH, '/'),
		'wpsc-dest'			=> WPSTAGECOACH_SERVER,
		'wpsc-has-sqlite'	=> $wpsc['has-sqlite'],
	);
	if( ! empty($_POST['wpsc-options']) ){
		$post_details['wpsc-options'] = $_POST['wpsc-options'];
	}


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
	$result = wpsc_check_post_info( 'make-changes-file', $post_url, $post_details, $post_result, false ) ; // check response from the server

	if( $result['result'] == 'OK' ){
		if(isset( $result['info'] ) &&  $result['info'] == 'EMPTY'){
			echo '<p>' . __( 'There are no new changes on your staging site.', 'wpstagecoach' ) . '</p>'.PHP_EOL;
			// send them back to the main page
			?>
			<form method="POST" name="wpsc-go-home" action="<?php echo admin_url('admin.php?page=wpstagecoach'); ?>">
				<input class="button submit-button" type="submit" name="wpsc-go-home" value="<?php _e( 'Okay', 'wpstagecoach' ); ?>">
			</form>
			<?php
			// todo -- maybe send users back to the import window instead of home?
			return;
		} else {
			echo '<p>' . __( 'We found changes on your staging site.', 'wpstagecoach' ) . '</p>'.PHP_EOL;
			echo '<p>' . __( 'We will download them in the next step, hang on!', 'wpstagecoach' ) . '</p>'.PHP_EOL;
			$nextstep = 2;
		}
	} elseif($result['result'] == 'OTHER') {
		$normal = false;
		$msg = '<p>' . __( 'There was a complication checking for changes on your staging site ', 'wpstagecoach' ) . WPSTAGECOACH_STAGE_SITE . '</p>';
		$msg .= print_r($result['info'], true);
		echo '<div class="wpstagecoach-warn">'.$msg.'</div>'.PHP_EOL;

		echo '<form method="POST" id="wpsc-step-form" class="wpstagecoach-update-step-nonce-form">'.PHP_EOL;
		echo '<input type="submit" class="button submit-button wpstagecoach-update-step-nonce" name="wpsc-options[use-file]" value="' . __( 'Use File', 'wpstagecoach' ) . '">'.PHP_EOL;	
		echo '<input type="submit" class="button submit-button wpstagecoach-update-step-nonce" name="wpsc-options[check-anew]" value="' . __( 'Check again', 'wpstagecoach' ) . '">'.PHP_EOL;

	} else {
		$normal = false;
		$errmsg  = '<p>' . __( 'There was a problem checking for changes on your staging site ', 'wpstagecoach' ) . WPSTAGECOACH_STAGE_SITE . '.</p>';
		$errmsg .= '<p>' . __( 'Please contact WP Stagecoach support with this error information:', 'wpstagecoach' ) . '<pre>';

		if( is_array($result) ){
			$errmsg .= print_r($result['info'], true);
		} else {
			$errmsg .= print_r($result, true);
		}
		wpsc_display_error( $errmsg, false );

		echo '</pre></p><p>';
		_e( 'We ran into a problem checking for changes. Would you like to try again?', 'wpstagecoach' );
		echo '</p><form method="POST" id="wpsc-step-form" class="wpstagecoach-update-step-nonce-form">'.PHP_EOL;
		echo '<input type="submit" class="button submit-button wpstagecoach-update-step-nonce" name="wpsc-options[retry]" value="' . __( 'Yes', 'wpstagecoach' ) . '">'.PHP_EOL;	
		echo '<input type="submit" class="button submit-button" name="wpsc-options[stop]" value="' . __( 'No', 'wpstagecoach' ) . '">'.PHP_EOL;
	}
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
	echo '<p>' . sprintf( __('Step %s: Downloading changes file.', 'wpstagecoach' ), $_POST['wpsc-step'] ) . '</p>';


	$remote_file_name = $USER . '-changes' . ( $wpsc['has-sqlite']? '.db' : '' );
	$import_file_name = md5( $remote_file_name . microtime( true ) ) . ( $wpsc['has-sqlite']? '.db' : '' );

	$post_url = 'https://' . WPSTAGECOACH_SERVER . '/wpsc-app-download-changes-file.php';
	$post_details = array(
		'wpsc-user'			=> $wpsc['username'],
		'wpsc-key'			=> $wpsc['apikey'],
		'wpsc-ver'			=> WPSTAGECOACH_VERSION,
		'wpsc-live-site'	=> WPSTAGECOACH_LIVE_SITE,
		'wpsc-stage-site'	=> WPSTAGECOACH_STAGE_SITE,
		'wpsc-conductor'	=> WPSTAGECOACH_CONDUCTOR,
		'wpsc-live-path'	=> rtrim(ABSPATH, '/'),
		'wpsc-dest'			=> WPSTAGECOACH_SERVER,
		'wpsc-file'			=> $remote_file_name,
	);
	if( !empty($_POST['wpsc-options']) )
		$post_details['wpsc-options'] = $_POST['wpsc-options'];

	foreach( array( '', '.md5' ) as $ext ) {
		$post_details['wpsc-file'] = $post_details['wpsc-file'] . $ext;
		$dest_file = WPSTAGECOACH_TEMP_DIR . $import_file_name . $ext;
	
		echo 'Downloading the ' . $ext . ' file currently...';
		echo str_pad( '', 65536 ) . PHP_EOL;
		ob_flush();
		flush();

		if( ! $changes_file = fopen ( $dest_file, 'w' ) ){
			$errmsg = '<p>' . sprintf( __( 'Error: We were not able to open the %s file %s for writing!', 'wpstagecoach' ), $ext, $dest_file ) . '</p>';
			wpsc_display_error( $errmsg );
			return;
		}

		if( $wpsc_sanity['https'] == 'ALL_GOOD' || $wpsc_sanity['https'] == 'NO_CA' ){

			$ch = curl_init( $post_url );
			curl_setopt( $ch, CURLOPT_POST,           1 );
			curl_setopt( $ch, CURLOPT_POSTFIELDS,     http_build_query( $post_details ) );
			curl_setopt( $ch, CURLOPT_TIMEOUT,        600 );
			curl_setopt( $ch, CURLOPT_FILE,           $changes_file ); // write curl response to file
			curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );

			if( $wpsc_sanity['https'] == 'NO_CA' ){
				curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
				curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
			}
			
			$result = curl_exec( $ch ); // get curl response
			if( curl_errno( $ch ) ){
				$errmsg = '<p>' . sprintf( __( 'Error: We received an error from curl while downloading the %s file. Details: %s', 'wpstagecoach' ), $ext, curl_error( $ch ) ) . '</p>'.PHP_EOL;
				wpsc_display_error( $errmsg );
				return;
			}
			curl_close( $ch );
			fclose( $changes_file );

			if( !$result ){ // bad result
				$errmsg = '<p>' . sprintf( __( 'Error: We were not able to download the %s file with curl.', 'wpstagecoach' ), $ext ) . '</p>'.PHP_EOL;
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
				$errmsg = '<p>' . sprintf( __('Error: We received an error from wp_remote_post while downloading the %s file. Details: %s', 'wpstagecoach' ), $ext, print_r( $post_result, true ) ) . '</p>' . PHP_EOL;
				wpsc_display_error( $errmsg );
				return;
			}
			if( ! fwrite($changes_file,  $post_result['body'] ) ){
				$errmsg = '<p>' . sprintf( __('Error: We were not able to write to the %s file with wp_remote_post.', 'wpstagecoach' ), $ext ) . '</p>' . PHP_EOL;
				wpsc_display_error( $errmsg );
				return;
			}
			fclose( $changes_file );

		} else {
			$errmsg  = __( 'We could not determine what method SSL transport methods your live site supports.', 'wpstagecoach' ) . '<br/>' . PHP_EOL;
			$errmsg .= __( 'Please contact WP Stagecoach support with your hosting provider, and the following details: ', 'wpstagecoach' ) . '<pre>' . print_r( $wpsc_sanity, true ) . '</pre>' . PHP_EOL;
			wpsc_display_error( $errmsg );
			return;
		} // done downloading the changes file from the workhorse



		if( ! is_file( $dest_file ) ){
			$errmsg = '<p>' . sprintf( __( 'Error: We could not find the %s file %s that we just created!  Might something be wrong with your web host?', 'wpstagecoach' ), $ext, $dest_file ) . '</p>'.PHP_EOL;
			wpsc_display_error( $errmsg );
			return;
		}

		sleep(.5); // give the slower webhosts a chance to flush their writes out...
		if($ext == '')  // might as well get the checksum while we're here!
			$md5sum = md5_file( $dest_file );

		echo '<p>' . __( 'Done!', 'wpstagecoach' ) . '</p>'.PHP_EOL;
		echo str_pad( '', 65536 ) . PHP_EOL;
		ob_flush();
		flush();
	} // end foreach over the file & .md5 file


	// make sure the file has the correct .md5 sum -- compare it to the current dest_file
	if( $md5sum != file_get_contents( $dest_file ) && filesize( $dest_file ) > 30 ){
		$normal=false;
		$nextstep = 2;

		$errmsg = '<p>' . __( 'Error: the changes file doesn\'t appear to have finished downloading. Would you like to try to download it again?', 'wpstagecoach' ) . '</p>'.PHP_EOL;
		if( WPSC_DEBUG ){
			$errmsg .= 'DEBUG: The checksum we calculated for your download: ' . $md5sum . '<br/>' . PHP_EOL;
			$errmsg .= 'DEBUG: The checksum calculated on the server: ' . file_get_contents( $dest_file ) . '<br/>' . PHP_EOL;
		}	
		wpsc_display_error( $errmsg );


		echo '<form method="POST" id="wpsc-step-form" class="wpstagecoach-update-step-nonce-form">'.PHP_EOL;
		echo '<input type="submit" class="button submit-button wpstagecoach-update-step-nonce" name="wpsc-options[retry]" value="' . __( 'Yes', 'wpstagecoach' ) . '">'.PHP_EOL;	
		echo '<input type="submit" class="button submit-button" name="wpsc-options[stop]" value="' . __( 'No', 'wpstagecoach' ) . '">'.PHP_EOL;

	} else {
		echo '<p>' . __( 'The changes file downloaded without a problem!', 'wpstagecoach' ) .'</p>'.PHP_EOL;


		// don't need old checksum.
		unlink( WPSTAGECOACH_TEMP_DIR . $import_file_name . '.md5' );

		// need to store the file name we get back from the staging server
		$wpsc['import-file'] = $import_file_name;
		update_option( 'wpstagecoach', $wpsc );

		$nextstep = 3;
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
	echo '<p>' . sprintf( __('Step %s: opening file and recording the changes.', 'wpstagecoach' ), $_POST['wpsc-step'] ) . '</p>';

	if( isset( $wpsc['has-sqlite'] ) && $wpsc['has-sqlite'] ){ // sqlite method
		
		// check which tables are present
		$changesdb = wpstagecoach_open_changes_file();
		$query = 'SELECT name FROM sqlite_master WHERE type="table";';
		$result = $changesdb->query( $query );
		while ( $temp = $result->fetchArray( SQLITE3_ASSOC ) ) {
			if( is_array( $temp ) ){
				if( 'wpstagecoach_database_imports' == $temp['name'] ){
					$changes_tables[ 'db' ] = $temp['name'];
				} elseif( 'wpstagecoach_file_imports' == $temp['name'] ){
					$changes_tables[ 'file' ] = $temp['name'];
				}
			}
		}

		if( sizeof( $changes_tables ) > 0 ){
			// add special sqlite message to retrieved changes
			delete_option( 'wpstagecoach_retrieved_changes' );
			add_option( 'wpstagecoach_retrieved_changes', 'sqlite', '', 'no' );

			foreach ( $changes_tables as $type => $table ) {
				$query = 'select * from ' . $table .';';
				$result = $changesdb->query( $query );
				while ( $temp = $result->fetchArray( SQLITE3_ASSOC ) ) {
					if( 'file' == $type ){
						$new_changes[ $type ][ $temp['file_action'] ][] = $temp['file_name'];
					} elseif( 'db' == $type ){
						$new_changes[ $type ][ $temp['type_table'] ][ $temp['id'] ] = $temp['command'];
					}
				}
			} // end of foeach over $changes_tables
			$changesdb->close();
		
			$okay_to_delete = true;


		} else { // we didn't find any tables in the sqlite file... 
			$errmsg  = '<p>' . __( 'It looks like the changes file is empty or invalid. Please contact <a href="https://wpstagecoach.com/support" target="_blank">WP Stagecoach support</a> with the following information:', 'wpstagecoach' ) . '</p>'.PHP_EOL;
			$errmsg .= '<pre>sanity: ' . print_r( $wpsc_sanity, true ) . PHP_EOL;
			$errmsg .= 'file: ' . $dest_file . PHP_EOL;
			$errmsg .= 'first little bit of the file: ' . substr( file_get_contents( $dest_file ), 0, 512 ) . PHP_EOL;
			if( is_file( $dest_file ) ){
				$errmsg .= 'file-size: ' . filesize( $dest_file ) . PHP_EOL;
			} else {
				$errmsg .= 'file doesn\'t exist! ' . PHP_EOL;
			}
			wpsc_display_error( $errmsg );
			return false;
		}

	} else { // use old mysql method
		$dest_file = WPSTAGECOACH_TEMP_DIR . $wpsc['import-file'];

		// get the contents of the file
		$new_changes = json_decode( file_get_contents( $dest_file ), true );

		// make sure the changes stored in the file are sane!
		if( is_array( $new_changes ) && ( ( isset( $new_changes['db'] ) && is_array( $new_changes['db'] ) ) ||
			( isset( $new_changes['file'] ) && is_array( $new_changes['file'] ) ) )
		){
			echo '<p>' . __( 'The changes file looks okay!', 'wpstagecoach' ) . '</p>'.PHP_EOL;
			if( WPSC_DEBUG ){
				$new_summary = '<p>' . __( 'Summary of changes:', 'wpstagecoach' ) . '<ul>' . PHP_EOL;

				if( isset( $new_changes['db'] ) && is_array( $new_changes['db'] ) ){
					$new_summary .= '<li>' . __( 'database tables', 'wpstagecoach' ) . ': (' . sizeof( $new_changes['db'] ) . ')</li>' . PHP_EOL;
					$new_summary .= '<ul>';
					foreach ( $new_changes['db'] as $name => $table) {
						$new_summary .= '<li>' . __( 'database', 'wpstagecoach' ) . '[' . $name . ']: (' . sizeof( $new_changes['db'][ $name ] ) . ')</li>' . PHP_EOL;
					}
					$new_summary .= '</ul>';
				}
				if( isset( $new_changes['file']['new'] ) )
					$new_summary .= '<li>' . __( 'new files', 'wpstagecoach' ) . ': (' . sizeof( $new_changes['file']['new'] ) . ')</li>' . PHP_EOL;
				if( isset( $new_changes['file']['modified'] ) )
					$new_summary .= '<li>' . __( 'modified files', 'wpstagecoach' ) . ': (' . sizeof( $new_changes['file']['modified'] ) . ')</li>' . PHP_EOL;
				if( isset( $new_changes['file']['deleted'] ) )
					$new_summary .= '<li>' . __( 'deleted files', 'wpstagecoach' ) . ': (' . sizeof( $new_changes['file']['deleted'] ) . ')</li>' . PHP_EOL;
				echo '</ul></p>'.PHP_EOL;
				echo $new_summary;
			}
			if( WPSC_DEBUG ){
				echo '<a class="toggle">' . __( 'Show raw list of changes', 'wpstagecoach' ) . '</a><br/>'.PHP_EOL;
				echo '<div class="more" style="display: none">'.PHP_EOL;
				echo '<pre>';
				print_r($new_changes);
				echo '</pre>';	
				echo '</div>'.PHP_EOL;
			}
		} else {
			$errmsg  = '<p>' . __( 'It looks like the changes file invalid. Please contact <a href="https://wpstagecoach.com/support" target="_blank">WP Stagecoach support</a> with the following information:', 'wpstagecoach' ) . '</p>'.PHP_EOL;
			$errmsg .= '<pre>sanity: ' . print_r( $wpsc_sanity, true ) . PHP_EOL;
			$errmsg .= 'file: ' . $dest_file . PHP_EOL;
			$errmsg .= 'first little bit of the file: ' . substr( file_get_contents( $dest_file ), 0, 512 ) . PHP_EOL;
			if( is_file( $dest_file ) ){
				$errmsg .= 'file-size: ' . filesize( $dest_file ) . PHP_EOL;
			} else {
				$errmsg .= 'file doesn\'t exist! ' . PHP_EOL;
			}
			wpsc_display_error( $errmsg );
			return false;
		}

		$current_changes = get_option( 'wpstagecoach_retrieved_changes' );
		
		if( ! is_array( $current_changes ) ){
			$current_changes = false;
		}

		// if we have already stored some retrieved changes, but check for more changes, then we need to convert all the changes to base64_encoded.
		if( isset($current_changes['db']) && is_array($current_changes['db']) ){
			if( WPSC_DEBUG ){
				_e( 'There are already stored changes in your database -- adding to them' , 'wpstagecoach' );
			}
			foreach ( $current_changes['db'] as $table => $rows) {
				foreach ( $rows as $rowkey => $row) {
					$temprow = base64_decode( $row, true );
					if( empty( $temprow ) ){
						$current_changes['db'][ $table ][$rowkey] = base64_encode($row);
					}
				}
			}
		}

		if( filesize( $dest_file ) > 20971520 ){  // changes file larger than 20MB - try and split it up
			
			if( $current_changes ){   // we have to merge all the entries
				$new_changes = array_merge_recursive($current_changes, $new_changes);
				if( $new_changes ){
					if( WPSC_DEBUG ){
						_e( 'The stored changes were succesfully merged with the new changes' , 'wpstagecoach' );
					}
					delete_option('wpstagecoach_retrieved_changes');
					$error = false;
				} else {
					$errmsg = __( 'We were\'t able to merge the new changes with the stored changes', 'wpstagecoach' );
					wpsc_display_error( $errmsg );
					$error = true;
				}
			}


			if( ! $error ) {
				echo '<p>' . __( 'Breaking up import into a managable packet size!', 'wpstagecoach' ) . '</p>';

				$new_changes = serialize( $new_changes );
				global $wpdb;
				define( 'WPSC_DB_CHUNK_SIZE', 1048576 ); // 1MB

				$out = substr( $new_changes, 0, WPSC_DB_CHUNK_SIZE );
				$new_changes = substr_replace( $new_changes, '', 0, WPSC_DB_CHUNK_SIZE );

				if( WPSC_DEBUG) {
					echo __( 'changes variable size:', 'wpstagecoach' ) . strlen( $new_changes ) . '<br/>' .PHP_EOL;
				}

				$wpdb->query( 'insert into ' . $wpdb->prefix . 'options (option_name, option_value, autoload) values ("wpstagecoach_retrieved_changes", \'' . $out . '\', "no");');

				while ( !empty( $new_changes ) ){

					$out = substr( $new_changes, 0, WPSC_DB_CHUNK_SIZE );
					$new_changes = substr_replace( $new_changes, '', 0, WPSC_DB_CHUNK_SIZE );

					$wpdb->query( 'update ' . $wpdb->prefix . 'options set option_value = CONCAT ( option_value, \'' .  $out . '\') where option_name = "wpstagecoach_retrieved_changes";');

					if( WPSC_DEBUG) {
						echo __( 'changes varaible size:', 'wpstagecoach' ) . strlen( $new_changes ) . '<br/>' .PHP_EOL;
					}
					sleep( '.5' );
				}
			} // no error
		} else { // small file - go ahead with insert into DB
			// merge new changes with old ones, if they exist
			if( ! $current_changes ){
				if( WPSC_DEBUG ){
					_e( 'Attempting to store new changes to the database' , 'wpstagecoach' );
				}
				$res = add_option('wpstagecoach_retrieved_changes', $new_changes, '', 'no');
				unset( $new_changes );
			} else {  // we have to merge all the entries
				// new stuff
				//  First we need to check if we have files that were deleted since the last check for changes
				if( isset( $new_changes['file']['deleted'] ) ){
					foreach( $new_changes['file']['deleted'] as $key => $file) {
						$deleted_file_id = array_search( $file, $current_changes['file']['new'] );
						if( $deleted_file_id !== false ){
							unset( $current_changes['file']['new'][ $deleted_file_id ] );
							//  if the file was new, then we don't need it in the deleted list, eihter.
							unset( $new_changes['file']['deleted'][ $key ] );
						}
						$deleted_file_id = array_search( $file, $current_changes['file']['modified'] );
						if( $deleted_file_id !== false ){
							unset( $current_changes['file']['modified'][ $deleted_file_id ] );
						}
					}		
				}


				//  also need to check if we have files that were modified since the last check for changes
				//     if they were new previously, we need to leave them as new.
				//     they can't have been marked as deleted before without being marked as new first, so don't need to check the deleted aray
				if( isset( $new_changes['file']['modified'] ) ){
					foreach( $new_changes['file']['modified'] as $key => $file) {
						$modified_file_id = array_search( $file, $current_changes['file']['new'] );
						if( $modified_file_id !== false ){
							unset( $new_changes['file']['modified'][ $key ] );
						}
					}
				}

				//  also need to check if we have files that were new since the last check for changes to make sure they don't get deleted
				if( isset( $new_changes['file']['new'] ) ){
					foreach( $new_changes['file']['new'] as $key => $file) {
						if( ! empty( $current_changes['file']['deleted'] ) ){
							$new_file_id = array_search( $file, $current_changes['file']['deleted'] );
							if( $new_file_id !== false ){
								unset( $current_changes['file']['deleted'][ $new_file_id ] );
							}
						}

						//  if we have a new file that was previously marked as modified, we should just leave it in the modified only (as the original file is likely there)
						if( ! empty( $current_changes['file']['modified'] ) ){
							$new_file_id = array_search( $file, $current_changes['file']['modified'] );
							if( $new_file_id !== false ){
								unset( $new_changes['file']['new'][ $key ] );
							}
						}
					}
				} // end new stuff

				//  now we can go ahead and merge the new changes with the old changes
				$merged_changes = array_merge_recursive($current_changes, $new_changes);
				if( $merged_changes ){
					if( WPSC_DEBUG ){
						_e( 'The stored changes were succesfully merged with the new changes.' , 'wpstagecoach' );
					}
					delete_option('wpstagecoach_retrieved_changes');
					$res = add_option('wpstagecoach_retrieved_changes', $merged_changes, '', 'no');
				} else {
					$errmsg = __( 'We were\'t able to merge the new changes with the stored changes.', 'wpstagecoach' );
					wpsc_display_error( $errmsg );
					$res = false;
				}
			} // end of merging new and old changes into the db
		} // end of inserting into mysql db

		if( ! $res ){

			$errmsg  = '<p>' . __( "WP Stagecoach could not insert the changes from your staging site into your live site's database. You can ask your web host to increase the MySQL buffer length (net_buffer_length) or max packet size (max_allowed_packet), or you can do a manual import.", 'wpstagecoach' ) . '</p>'.PHP_EOL;
			$errmsg .= print_r($wpsc_sanity, true);
			$errmsg .= '<p>' . __( 'The result from our attempt to store the changes: ', 'wpstagecoach' ) . print_r( $res, true ) . '</p>' . PHP_EOL;
			$errmsg .= $new_summary;
			if( $current_changes ){
				$errmsg .= __( 'previously stored changes:', 'wpstagecoach' ) . '<br/>';
				if( isset( $current_changes['db'] ) )
					$errmsg .= __( 'database: ', 'wpstagecoach' ) . sizeof( $current_changes['db'] );
				if( isset( $current_changes['file'] ) )
					$errmsg .= __( 'files: ', 'wpstagecoach' ) . sizeof( $current_changes['file'] );
			}
			wpsc_display_error( $errmsg );
			return;
		} else { // everything is okay!
			$okay_to_delete = true;
		}
	} // end of sqlite/mysql methods


	if( true == $okay_to_delete ){

		if( $wpsc['has-sqlite'] ){
			echo '<p>' . __( 'The changes have succesfully been downloaded!', 'wpstagecoach' ) . '</p>'.PHP_EOL;

		} else {
			echo '<p>' . __( 'We stored your changes into the database!', 'wpstagecoach' ) . '</p>'.PHP_EOL;
			unlink( $dest_file ); // the changes file -- md5 file deleted in step 2
		}



		// go tell the staging server we got it, and it can delete the old file
		$post_url = WPSTAGECOACH_CONDUCTOR.'/wpsc-delete-changes-file.php';
		$post_details = array(
			'wpsc-user'			=> $wpsc['username'],
			'wpsc-key'			=> $wpsc['apikey'],
			'wpsc-ver'			=> WPSTAGECOACH_VERSION,
			'wpsc-live-site'	=> WPSTAGECOACH_LIVE_SITE,
			'wpsc-stage-site'	=> WPSTAGECOACH_STAGE_SITE,
			'wpsc-live-path'	=> rtrim(ABSPATH, '/'),
			'wpsc-dest'			=> WPSTAGECOACH_SERVER,
			'wpsc-file'			=> $USER.'-changes',
			'wpsc-has-sqlite'	=> $wpsc['has-sqlite'],
		);
		if( !empty($_POST['wpsc-options']) )
			$post_details['wpsc-options'] = $_POST['wpsc-options'];

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
		$result = wpsc_check_post_info('delete-changes-file', $post_url, $post_details, $post_result) ; // check response from the server

		if($result['result'] == 'OK'){
			echo '<p>' . __( 'We succesfully cleaned up your changes files from the server.', 'wpstagecoach' ) . '</p>'.PHP_EOL;
		} else {
			$normal = false;
			$errmsg  = '<p>' . __( 'There was a problem cleaning up the changes on your staging site ', 'wpstagecoach' ) .WPSTAGECOACH_STAGE_SITE.'.</p>'.PHP_EOL;
			$errmsg .= '<p>' .__( 'Please contact <a href="https://wpstagecoach.com/support" target="_blank">WP Stagecoach support</a> with this error information:', 'wpstagecoach' );
			$errmsg .= '<pre>'.print_r($result['info'], true).'</pre>';
			wpsc_display_error( $errmsg ); 
		}
		$done = true;


		// make sure the changes stored in the file are sane!
		if( isset( $new_changes ) && is_array( $new_changes ) && (
			( isset( $new_changes['db'] ) && is_array( $new_changes['db'] ) ) ||
			( isset( $new_changes['file'] ) && is_array( $new_changes['file'] ) )
		) ) {
			echo '<p>' . __( 'The changes database file looks okay!', 'wpstagecoach' ) . '</p>'.PHP_EOL;
			if( WPSC_DEBUG ){
				$new_summary = '<p>' . __( 'Summary of changes:', 'wpstagecoach' ) . '<ul>' . PHP_EOL;

				if( isset( $new_changes['db'] ) && is_array( $new_changes['db'] ) ){
					$new_summary .= '<li>' . __( 'database tables', 'wpstagecoach' ) . ': (' . sizeof( $new_changes['db'] ) . ')</li>' . PHP_EOL;
					$new_summary .= '<ul>';
					foreach ( $new_changes['db'] as $name => $table) {
						# code...
						$new_summary .= '<li>' . __( 'database', 'wpstagecoach' ) . '[' . $name . ']: (' . sizeof( $new_changes['db'][ $name ] ) . ')</li>' . PHP_EOL;
					}
					$new_summary .= '</ul>';
				}
				if( isset( $new_changes['file']['new'] ) )
					$new_summary .= '<li>' . __( 'new files', 'wpstagecoach' ) . ': (' . sizeof( $new_changes['file']['new'] ) . ')</li>' . PHP_EOL;
				if( isset( $new_changes['file']['modified'] ) )
					$new_summary .= '<li>' . __( 'modified files', 'wpstagecoach' ) . ': (' . sizeof( $new_changes['file']['modified'] ) . ')</li>' . PHP_EOL;
				if( isset( $new_changes['file']['deleted'] ) )
					$new_summary .= '<li>' . __( 'deleted files', 'wpstagecoach' ) . ': (' . sizeof( $new_changes['file']['deleted'] ) . ')</li>' . PHP_EOL;
				echo '</ul></p>'.PHP_EOL;
				echo $new_summary;
			}
			if( WPSC_DEBUG ){
				echo '<a class="toggle">' . __( 'Show raw list of changes', 'wpstagecoach' ) . '</a><br/>'.PHP_EOL;
				echo '<div class="more" style="display: none">'.PHP_EOL;
				echo '<pre>';
				print_r($new_changes);
				echo '</pre>';	
				echo '</div>'.PHP_EOL;
			}

		}
	} // end of $okay_to_delete
} // end of step 3



/*******************************************************************************************
*                                                                                          *
*                              End of checking step form                                   *
*                                                                                          *
*******************************************************************************************/

if( 1 ){
	echo str_pad( '', 65536 ) . PHP_EOL;
	ob_flush();
	flush();
	sleep(1);
}

if( !isset($nextstep)){
	$nextstep =0;
	$normal = false;
}

$wpscpost_fields['wpsc-step'] = $nextstep;

if( $normal === true )
	echo '<form style="display: hidden"  method="POST" id="wpsc-step-form">'.PHP_EOL;

$wpsc_nonce = wpsc_set_step_nonce( 'check', $nextstep );   // set a transient with next-step and a nonce
echo '  <input type="hidden" name="wpsc-nonce" id="wpsc-nonce" value="' . $wpsc_nonce . '"/>' . PHP_EOL;
echo '  <input type="hidden" name="wpsc-type" value="check"/>' . PHP_EOL;
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
} else {
	echo '</form>'.PHP_EOL;
}




