<?php
/*
 * WP Stagecoach Version 1.4.0
 */

if ( ! defined('ABSPATH') ) {
	die('Please do not load this file directly.');
}


wpsc_display_sidebar();



if( !isset($wpsc_sanity['auth']) || empty($wpsc_sanity['auth']['live-site']) || empty($wpsc_sanity['auth']['stage-site']) ){
	$msg = '<p>' . __( 'You tried to delete a staging site, but this site doesn\'t have a staging site. You shouldn\'t be able to access this page.  Please contact <a href="https://wpstagecoach.com/support" target="_blank">WP Stagecoach support</a> with details of how you got to this page.', 'wpstagecoach' ) . '</p>';
	wpsc_display_error($msg, false);
	wpsc_display_create_form('wpstagecoach.com', __( 'Would you like to create a new staging site?', 'wpstagecoach' ), $wpsc, ' again');
	return;
}

#define('WPSTAGECOACH_DOMAIN',		'.wpstagecoach.com');
if ( ! defined('WPSTAGECOACH_LIVE_SITE') )
	define('WPSTAGECOACH_LIVE_SITE',	$wpsc_sanity['auth']['live-site']);
if ( ! defined('WPSTAGECOACH_STAGE_SITE') )
	define('WPSTAGECOACH_STAGE_SITE',	$wpsc_sanity['auth']['stage-site']);
if ( ! defined('WPSTAGECOACH_SERVER') )
	define('WPSTAGECOACH_SERVER',		$wpsc_sanity['auth']['server']);


$post_url = WPSTAGECOACH_CONDUCTOR.'/wpsc-delete-site.php';
$post_details = array(
	'wpsc-user'			=> $wpsc['username'],
	'wpsc-key'			=> $wpsc['apikey'],
	'wpsc-ver'			=> WPSTAGECOACH_VERSION,
	'wpsc-live-site'	=> WPSTAGECOACH_LIVE_SITE,
	'wpsc-stage-site'	=> WPSTAGECOACH_STAGE_SITE,
	'wpsc-dest'			=> WPSTAGECOACH_SERVER,
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


$post_result = wp_remote_post($post_url, $post_args);
$result = wpsc_check_post_info('create-site', $post_url, $post_details, $post_result) ; // check response from the server

if($result['result'] == 'OK'){


	// get rid of the old db backup file being listed
	if( isset( $wpsc['db_backup_file'] ) ||
		isset( $wpsc['sql-manual-file'] ) ||
		isset( $wpsc['zip-manual-file'] )
		){
		if( isset( $wpsc['db_backup_file'] ) ){
			echo '<p>' . __( 'You have a database dump file: ', 'wpstagecoach' ) . $wpsc['db_backup_file'];
			echo __( ' You may wish to delete it.', 'wpstagecoach' ) . '</p>';
			unset( $wpsc['db_backup_file'] );
		}
		if( isset( $wpsc['sql-manual-file'] ) )
			unset( $wpsc['sql-manual-file'] );
		if( isset( $wpsc['zip-manual-file'] ) )
			unset( $wpsc['zip-manual-file'] );
		update_option( 'wpstagecoach', $wpsc );
	}

	delete_option('wpstagecoach_retrieved_changes');
	delete_option('wpstagecoach_old_retrieved_changes');
	delete_option('wpstagecoach_importing');
	delete_option('wpstagecoach_importing_db');
	delete_option('wpstagecoach_importing_files');

	delete_transient('wpstagecoach_sanity');


	// delete the wpstagecoach option for the staging & live site
	if( isset($wpsc['staging-site']) ){
		unset($wpsc['staging-site']);
	}
	if( isset($wpsc['live-site']) ){
		unset($wpsc['live-site']);
	}
	if( isset($wpsc['db_backup_file']) ){
		unset($wpsc['db_backup_file']);
	}
	if( isset($wpsc['sql-manual-file']) ){
		unset($wpsc['sql-manual-file']);
	}
	if( isset($wpsc['zip-manual-file']) ){
		unset($wpsc['zip-manual-file']);
	}
	if( !update_option('wpstagecoach', $wpsc) ){
		$msg = __( '<p>Could not update the WordPress option for wpstagecoach.  This shouldn\'t happen.', 'wpstagecoach' );
		$msg .= __( 'You might consider checking your database\'s consistency.', 'wpstagecoach' );
		$msg .= __( 'Please contact <a href="https://wpstagecoach.com/support" target="_blank">WP Stagecoach support</a> for help.</p>', 'wpstagecoach' );
		wpsc_display_error($msg, false);
	}

	// clean up log & db files
	$temp_files = scandir(WPSTAGECOACH_TEMP_DIR);
	foreach ($temp_files as $file) {
		if( (strpos($file, 'mport.log') || strpos($file, '.gz') ) && !($file == '.' || $file == '..') ){
			unlink( WPSTAGECOACH_TEMP_DIR.$file);
		}
	}


	_e( '<p>Your staging site '.WPSTAGECOACH_STAGE_SITE.' has been deleted. Thank you for using WP Stagecoach!', 'wpstagecoach' );

	wpsc_display_create_form('wpstagecoach.com', __('Create a new staging site', 'wpstagecoach' ), $wpsc, ' again');

} else {
	$msg = __( '<p>There was a problem deleting your staging site: </p>', 'wpstagecoach' );
	$msg .= WPSTAGECOACH_STAGE_SITE;
	$msg .= __( '<p>Please contact <a href="https://wpstagecoach.com/support" target="_blank">WP Stagecoach support</a> with the above error information.</p>', 'wpstagecoach' );
	wpsc_display_error($msg, false);
	// go ahead & delet--this way we will clear this out and can check whether the site really was sucessfully deleted or not.
	delete_transient('wpstagecoach_sanity');
}


