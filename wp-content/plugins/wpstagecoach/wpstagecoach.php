<?php

/*
Plugin Name: WP Stagecoach
Plugin URI: https://wpstagecoach.com/
Description: WordPress staging sites made easy
Version: 1.4.3
Author: WP Stagecoach
Author URI: https://wpstagecoach.com/
License: GPL2
*/

/*
Copyright 2018 Alchemy Computer Solutions, Inc.

This file is part of the WP Stagecoach plugin.

The WP Stagecoach plugin is free software: you can redistribute it and/or
modify it under the terms of the GNU General Public License as published by
the Free Software Foundation, version 2 of the License.

The WP Stagecoach plugin is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for
more details.

You should have received a copy of the GNU General Public License
along with the WP Stagecoach plugin.  If not, see <http://www.gnu.org/licenses/>.
*/

if ( ! defined('ABSPATH') ) {
	die('Please do not load this file directly.');
}
/* some plugin defines */
define( 'WPSTAGECOACH_PLUGIN_URL',			plugins_url( '', __FILE__ ) . '/' );
define( 'WPSTAGECOACH_INCLUDES_DIR',		dirname( __FILE__ ) . '/includes' );
define( 'WPSTAGECOACH_REL_DIR',				str_replace( site_url(), '.', WPSTAGECOACH_PLUGIN_URL ) );
define( 'WPSTAGECOACH_REL_TEMP_DIR',		WPSTAGECOACH_REL_DIR . 'temp/' );
define( 'WPSTAGECOACH_TEMP_DIR',			dirname( __FILE__ ).'/'.'temp/' );
define( 'WPSTAGECOACH_VERSION',				'1.4.3' );
define( 'WPSTAGECOACH_CONDUCTOR',			'https://conductor.wpstagecoach.com' );
define( 'WPSTAGECOACH_ERRDIV',				'<div class="wpstagecoach-error">' );
define( 'WPSTAGECOACH_WARNDIV',				'<div class="wpstagecoach-warn">' );
// define( 'WPSTAGECOACH_LARGE_FILE',			10485760);

/* What to do when the plugin is activated? */
register_activation_hook( __FILE__,'wpstagecoach_install' );

/* What to do when the plugin is deactivated? */
register_deactivation_hook( __FILE__, 'wpstagecoach_remove' );

function wpstagecoach_install() {
	define( 'WPSTAGECOACH_ACTION', 'install' );
	if( is_file( WPSTAGECOACH_INCLUDES_DIR . '/wpsc-special.inc.php' ) ){
		include_once( WPSTAGECOACH_INCLUDES_DIR . '/wpsc-special.inc.php' );
	}
}


function wpstagecoach_remove() {
	define( 'WPSTAGECOACH_ACTION', 'remove' );
	require_once( WPSTAGECOACH_INCLUDES_DIR . '/wpsc-functions.inc.php' );
	$wpsc = get_option('wpstagecoach');

	if( is_file( dirname( __FILE__ ) . '/includes/wpsc-special.inc.php' ) ){
		include_once( dirname( __FILE__ ) . '/includes/wpsc-special.inc.php' );
	}

	if( isset( $wpsc['delete_settings'] ) && $wpsc['delete_settings'] == true ){
		global $wpdb;
		wpsc_rm_rf( WPSTAGECOACH_TEMP_DIR );
		$wpdb->query("delete from ".$wpdb->prefix."options where option_name like 'wpstagecoach%';");
	}
}

function wpstagecoach_admin_init() {
	wp_register_script( 'wpsc-js', WPSTAGECOACH_PLUGIN_URL.'assets/wpstagecoach.js', array('jquery') );
	wp_register_style( 'wpsc-css', WPSTAGECOACH_PLUGIN_URL.'assets/wpstagecoach.css' );
}
add_action( 'admin_init', 'wpstagecoach_admin_init' );

function wpstagecoach_admin_menu() {
	$wpscmainpage = add_menu_page('WP Stagecoach', 'WP Stagecoach', 'manage_options','wpstagecoach', 'wpstagecoach_main', WPSTAGECOACH_PLUGIN_URL.'assets/wpsc-logo-16.png');

	$wpsc = get_option( 'wpstagecoach' );

	add_action( 'load-'.$wpscmainpage, 'wpstagecoach_admin_scripts' );
	if( isset( $wpsc['staging-site'] ) && ! empty( $wpsc['staging-site'] ) ) {
		$wpscimportpage = add_submenu_page( 'wpstagecoach', 'WP Stagecoach Import', 'Import Changes', 'manage_options', 'wpstagecoach_import', 'wpstagecoach_import' );
		add_action( 'load-'.$wpscimportpage, 'wpstagecoach_admin_scripts' );
	}
	$wpscsettingspage = add_submenu_page( 'wpstagecoach', 'WP Stagecoach Settings', 'Settings', 'manage_options', 'wpstagecoach_settings', 'wpstagecoach_settings' );
	add_action( 'load-'.$wpscsettingspage, 'wpstagecoach_admin_scripts' );
	if( isset( $wpsc['advanced'] ) && $wpsc['advanced'] ==  true ) {
		$wpscdebugpage = add_submenu_page( 'wpstagecoach', 'WP Stagecoach Advanced', 'Advanced', 'manage_options', 'wpstagecoach_advanced', 'wpstagecoach_advanced' );
		add_action( 'load-'.$wpscdebugpage, 'wpstagecoach_admin_scripts' );
	}
	if( isset( $wpsc['debug'] ) && $wpsc['debug'] ==  true ) {
		$wpscdebugpage = add_submenu_page( 'wpstagecoach', 'WP Stagecoach Debug', 'Debug', 'manage_options', 'wpstagecoach_debug', 'wpstagecoach_debug' );
		add_action( 'load-'.$wpscdebugpage, 'wpstagecoach_admin_scripts' );
	}
}
add_action('admin_menu', 'wpstagecoach_admin_menu');

function wpstagecoach_admin_scripts() {
	add_action('admin_enqueue_scripts', 'wpstagecoach_enqueue_js');
}

function wpstagecoach_enqueue_js() {
	wp_enqueue_script( 'wpsc-js' );
	wp_localize_script( 'wpsc-js', 'wpstagecoachajax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
	wp_enqueue_style( 'wpsc-css' );
}

// Initialize language so it can be translated
function wpstagecoach_language_init() {
	load_plugin_textdomain( 'wpstagecoach', false, dirname( plugin_basename( __FILE__ ) ).'languages' );
}
add_action('init', 'wpstagecoach_language_init');

//	Add a staging site notification to the toolbar of the staging site
function wpstagecoach_staging_menu_notice() {
	global $wp_admin_bar;
	$titlemsg = '<div style="background: #fc6345;padding: 0 15px;color: #fff;font-weight: normal;">';
	$titlemsg .= '<img style="position:relative; top:4px;" src="' . WPSTAGECOACH_PLUGIN_URL . '/assets/wpsc-logo-16.png' . '"> This is your Staging Site!';
	$titlemsg .= '</div>';
	$wp_admin_bar->add_node( array(
		'id' => 'wpstagecoach',
		'title' => $titlemsg,
		'href' => 'https://wpstagecoach.com/your-account/',
		'meta' => array(
			'target' => '_blank',
			'title' => 'Click here to go to your WP Stagecoach account page',
			)
		)
	);
}

if( isset( $_SERVER['SERVER_NAME'] ) ){
	$wpstagecoach_site_domain = explode( '.', $_SERVER['SERVER_NAME'] );
	while ( sizeof( $wpstagecoach_site_domain ) > 2 )
		array_shift( $wpstagecoach_site_domain );
	$wpstagecoach_site_domain = implode( '.', $wpstagecoach_site_domain );
	if( 'wpstagecoach.com' == $wpstagecoach_site_domain ){
		require_once( WPSTAGECOACH_INCLUDES_DIR . '/wpsc-functions.inc.php' );
		add_action( 'wp_before_admin_bar_render', 'wpstagecoach_staging_menu_notice' );
		add_action( 'admin_init', 'wpstagecoach_staging_login_notice' );
		add_action( 'switch_theme', 'wpsc_theme_check' );	
		add_action( 'admin_notices', 'wpsc_ignore_theme_warning' );
		add_action( 'admin_notices', 'wpsc_theme_warning' );
	}
}


function wpstagecoach_main() {
	define( 'WPSTAGECOACH_ACTION', 'main' );
	##    ##   ##      ###  ##    ##
	 ##  ##   #  #      #    ##   #
	 # ## #  #    #     #    # #  #
	 #    #  ######     #    #  # #
	 #    #  #    #     #    #   ##
	##    ####    ##   ###  ##    ##

	#####################################################################################
	################                  MAIN PAGE                 #########################
	#####################################################################################

	require_once( WPSTAGECOACH_INCLUDES_DIR . '/wpsc-functions.inc.php' );

	if( is_file( dirname( __FILE__ ) . '/includes/wpsc-special.inc.php' ) ){
		include_once( dirname( __FILE__ ) . '/includes/wpsc-special.inc.php' );
	}
	
	// check basic sanity, it will display error if it fails, if so we need to return.
	$wpsc_sanity=wpsc_sanity_check();
	if($wpsc_sanity == false)
		return;

	$wpsc = get_option( 'wpstagecoach' );
	if( empty( $wpsc['username'] ) || empty( $wpsc['apikey'] ) ){
		wpsc_force_redirect( 'admin.php?page=wpstagecoach_settings' );
		die;
	}

	wpsc_display_header();

	// if we have $_POST data, we are doing stuff!  Otherwise, just display the create-a-staging-site form
	if( ! empty($_POST) ){

		if ( isset( $_POST['wpstagecoach-feedback'] ) ) {
			// we have feedback in _POST, so we need to submit it with this function
			wpsc_send_feedback();
		} elseif( isset( $_POST['wpsc-create'] ) ){
			// updating settings in initial setup (or bad input from settings page)
			require_once( WPSTAGECOACH_INCLUDES_DIR . '/wpsc-create.inc.php' );
		} elseif( isset( $_POST['wpstagecoach-settings-updated'] ) || isset( $_POST['wpsc-go-home'] ) || isset( $_POST['wpsc-stop'] ) ) {
			// if we updated a setting that requires refreshing the screen (eg, debug), we want to just display the main form
			$display_main_form = true;
		} elseif( isset($_POST['wpsc-delete-site']) ){
			// delete the site!
			require_once( WPSTAGECOACH_INCLUDES_DIR . '/wpsc-delete.inc.php' );
		}
	}


	// display the create-a-staging-site form
	if( empty( $_POST ) || ( isset( $display_main_form ) && $display_main_form == true ) ){
		wpsc_display_sidebar();

		wpsc_display_welcome( $wpsc_sanity['auth'] );
		
		wpstagecoach_display_sqlite_message( $wpsc );
		
		wpsc_display_main_form( $wpsc_sanity['auth'], $wpsc );


		if( isset( $wpsc['db_backup_file'] ) ){
			echo '<p>' . __( 'The database backup WP Stagecoach made before importing changes is:', 'wpstagecoach' ) . '<br/>' . $wpsc['db_backup_file'] . '</p>';
		}
	}
	wpsc_display_footer();
}


function wpstagecoach_import() {
	define( 'WPSTAGECOACH_ACTION', 'import' );
   ###
    #   ##    # ######    ####  ######    #####
    #    ##  ##  #    #  #    #  #    #   # # #
    #    # ## #  #    #  #    #  #    #     #
    #    #    #  #####   #    #  #####      #
    #    #    #  #       #    #  #   #      #
   ###  ##    ## #        ####  ##    ##   ###
	###################################################################################################
	################                  Staging Site Check menu                 #########################
	###################################################################################################
	require_once( WPSTAGECOACH_INCLUDES_DIR . '/wpsc-functions.inc.php' );


	// check basic sanity, it will display error if it fails, if so we need to return.
	$wpsc_sanity=wpsc_sanity_check('import');
	if($wpsc_sanity == false)
		return;

	$wpsc = get_option( 'wpstagecoach' );
	if( empty( $wpsc['username'] ) || empty( $wpsc['apikey'] ) ){
		wpsc_force_redirect( 'admin.php?page=wpstagecoach_settings' );
		die;
	}
	
	wpsc_display_header();
	global $wpdb;

	if( empty($_POST) )
		wpsc_display_sidebar();
	


	// now that we have sanity, we can make some defines
	define('WPSTAGECOACH_LIVE_SITE',	$wpsc_sanity['auth']['live-site']);
	define('WPSTAGECOACH_STAGE_SITE',	$wpsc_sanity['auth']['stage-site']);
	define('WPSTAGECOACH_SERVER',		$wpsc_sanity['auth']['server']);
	define('WPSTAGECOACH_LIVE_PATH',	rtrim( ABSPATH, '/' ) );
	define('WPSTAGECOACH_DOMAIN',		'.wpstagecoach.com');


	if( $wpsc_sanity['auth']['type'] != 'live' ){
		$msg = __( 'Please go to your live site, <a href="http://'.$wpsc_sanity['auth']['live-site'].'" target="_blank">'.$wpsc_sanity['auth']['live-site'].'</a> to import changes from your WP Stagecoach staging site.', 'wpstagecoach' );
		wpsc_display_error($msg, false);
		return;
	}

	$step = get_option('wpstagecoach_importing');


	######      We need to see if we have changes stored locally in the DB
	$changes = get_option('wpstagecoach_retrieved_changes');	
	if ( is_array( $changes ) && sizeof( $changes ) > 0 ){
		$changes_stored = true;
	} elseif( 'sqlite' == $changes && true == $wpsc['has-sqlite'] ){

		$changesdb = wpstagecoach_open_changes_file();
		$query = 'SELECT name FROM sqlite_master WHERE type="table";';
		$result = $changesdb->query( $query );
		$count = 0;
		while ( $row = $result->fetchArray( SQLITE3_ASSOC ) ) { // get the table names of items to import
			$count_query = 'SELECT count(*) FROM ' . $row['name'] . ' WHERE processed is NULL OR processed = "selected";';
			$count_result = $changesdb->query( $count_query );
			$count_row = $count_result->fetchArray( SQLITE3_ASSOC );
			$count += $count_row['count(*)'];
		}
		if( $count ){
			$changes_stored = true;
		} elseif( $step ) { // if we have a valid $step, then we are almost certainly in an import, so we just need to get on with it.
			$changes_stored = true;
		} else {
			$changes_stored = false;
		}

		$changesdb->close();

	} else {
		$changes_stored = false;
	}
	unset($changes);

	######    Now we're going to do work--we need to display the check for changes button (and check for them), display changes if we got 'em, and apply them if told

	######    we have NOT received _POST info     ####
	if ( empty($_POST) ) {
		wpstagecoach_display_sqlite_message( $wpsc );


		// if we have a stored step, we should prompt the user whether they want to continue or start over.
		if ( empty( $_POST['wpsc-step'] ) && $step ){

			if( $step == 7 ) {  // we just need to go back to the import confirmation screen
				add_filter( 'nonce_life', 'wpstagecoach_step_nonce_time' );
				$wpsc_nonce = wpsc_set_step_nonce( 'import' );   // set a nonce with next-step
				?>

				<form method="POST" id="wpsc-step-form" class="wpstagecoach-update-step-nonce-form">
					<input type="hidden" name="wpsc-type" value="import" />
					<input type="hidden" name="wpsc-step" value="<?php echo $step; ?>" />
					<input type="hidden" name="wpsc-nonce" value="<?php echo $wpsc_nonce; ?>" />
					<input type="hidden" name="wpsc-import-changes" value="<?php _e( 'Continue', 'wpstagecoach' ); ?>">

				</form>
				<script>
				document.forms["wpsc-step-form"].submit()
				</script>
		

				<?php
			}
			?>

			<p><?php echo __( 'It looks like you have already started an import, and are currently on step ', 'wpstagecoach' ) . $step; ?></p>
			<p><?php _e( 'Click "Continue" below if you want to resume from this step.', 'wpstagecoach' ); ?></p>

			<?php
				add_filter( 'nonce_life', 'wpstagecoach_step_nonce_time' );
				$wpsc_nonce = wpsc_set_step_nonce( 'import' );   // set a nonce with next-step
			?>
			<form method="POST" id="wpsc-step-form" class="wpstagecoach-update-step-nonce-form">
				<input type="hidden" name="wpsc-type" value="import" />
				<input type="hidden" name="wpsc-step" value="<?php echo $step; ?>" />
				<input type="hidden" name="wpsc-nonce" value="<?php echo $wpsc_nonce; ?>" />
				<input class="button submit-button wpstagecoach-update-step-nonce" type="submit" name="wpsc-import-changes" value="<?php _e( 'Continue', 'wpstagecoach' ); ?>">
			</form>

			<p><?php _e( 'Alternatively, if you really know what you are doing, you can press "Start import over" to start the import process over.', 'wpstagecoach' ); ?></p>
			<p><?php _e( 'WP Stagecoach cannot reset your live site to how it was before the first import attempt: proceed at your own risk!', 'wpstagecoach' ); ?>
			<p><b><?php _e( 'This may leave your site in an unknown state, and reimporting may cause major problems!', 'wpstagecoach' ); ?></b></p>
			<form method="POST" name="wpsc-reset-import">
				<input class="button submit-button" type="submit" name="wpsc-reset-import" value="<?php _e( 'Start import over', 'wpstagecoach' ); ?>">
			</form>

			<?php
			wpsc_display_footer();
			return;
		}

		$import_step = get_option('wpstagecoach_import_step');
		if( is_numeric( $import_step ) ){
			$_POST = array(
				'wpsc-change-apply' => true,
				'wpsc-step' => $import_step
			);
			
		} else {
			######    offer to (re)check for changes
			echo '<p><form method="post">';
			if( $changes_stored ){
				$check='Recheck';
			} else {
				$check='Check';
			}
			_e( $check . ' for changes from staging site: ', 'wpstagecoach' );
			echo '<input type="submit" class="button submit-button" name="wpsc-check-changes" value="'.$check.' for changes" />';
			echo '</form></p>';

			if( $changes_stored ) {
				######    if we already have changes stored, display those changes
				require_once( WPSTAGECOACH_INCLUDES_DIR . '/wpsc-import-display.inc.php' );
			}

		}


	} else { // $_POST is not empty

		// we are checking for changes
		if ( isset($_POST['wpsc-check-changes']) ) {
			$done = false; // this will be set to true within the included file when it is done.
			//  doing check (or re-check (if $changes_stored is set))
			require_once( WPSTAGECOACH_INCLUDES_DIR . '/wpsc-import-check.inc.php' );

			if( $done ){
				require_once( WPSTAGECOACH_INCLUDES_DIR . '/wpsc-import-display.inc.php' );
			}

		} elseif ( isset( $_POST['wpsc-options']['stop'] ) ) {
			// we are stopping in the middle of something
			if( isset( $_POST['wpsc-options']['cleanup-import'] ) ){
				delete_option( 'wpstagecoach_importing' );
				delete_option( 'wpstagecoach_importing_db' );
				delete_option( 'wpstagecoach_importing_files' );

				_e( '<p>Okay, we will stop the import process here.</p>', 'wpstagecoach' );
				require_once( WPSTAGECOACH_INCLUDES_DIR . '/wpsc-import-display.inc.php' );
			}
		} elseif ( $changes_stored && isset( $_POST['wpsc-import-changes'] ) ) {
			// we are going to import changes!

			if( 'sqlite' == $changes_stored && true == $wpsc['has-sqlite'] ){
				include_once 'includes/wpsc-import-lt.inc.php';
			} else {
				include_once 'includes/wpsc-import-my.inc.php';
			}

		} elseif( isset( $_POST['wpsc-reset-import'] ) ){
			// 	we are resetting the step counter, and going to let the user choose what files/DB they want to import.
			_e( '<h3>The import step counter has been reset.<br/> WP Stagecoach cannot reset your live site to how it was before the first import attempt--proceed at your own risk.</h3>', 'wpstagecoach' );
			delete_option('wpstagecoach_importing');
			delete_option('wpstagecoach_importing_db');
			delete_option('wpstagecoach_importing_files');
			require_once( WPSTAGECOACH_INCLUDES_DIR . '/wpsc-import-display.inc.php' );

		} elseif( ! isset( $changes_stored ) && isset( $_POST['wpsc-import-changes'] ) ){
			_e( '<p>This is strange, it looks like you have selected to import, but I don\'t see any changes stored.  Are you continuing an import from before? </p>', 'wpstagecoach' );
		} else {
			_e( '<p>Sorry, we can\'t figure out what you\'re trying to do.  Since this is a little strange, here is what you have in the $_POST variable so you can contact WP Stagecoach support:</p>', 'wpstagecoach' );
			echo '<pre>' . print_r( $_POST, true ) . '</pre>' . PHP_EOL;
			echo 'Other information:';
			echo '<pre>';
			echo 'Import command: ' . $_POST['wpsc-import-changes'] . PHP_EOL;
			echo '$changes_stored: ';
			var_dump( $changes_stored );
			echo PHP_EOL;
		}
	}

	wpsc_display_footer();
}


function wpstagecoach_settings( $msg='', $auth_only=false, $display_header=true ) {
	define( 'WPSTAGECOACH_ACTION', 'settings' );	
	  ####   ######   #####   #####     #    #    #   ####    ####
	 #       #          #       #       #    ##   #  #    #  #
	  ####   #####      #       #       #    # #  #  #        ####
	      #  #          #       #       #    #  # #  #  ###       #
	 #    #  #          #       #       #    #   ##  #    #  #    #
	  ####   ######     #       #       #    #    #   ####    ####

	#########################################################################################
	################                  SETTINGS PAGE                 #########################
	#########################################################################################
	require_once( WPSTAGECOACH_INCLUDES_DIR . '/wpsc-functions.inc.php' );
	if( is_file( dirname( __FILE__ ) . '/includes/wpsc-special.inc.php' ) ){
		include_once( dirname( __FILE__ ) . '/includes/wpsc-special.inc.php' );
	}

	wpsc_display_header();
	wpsc_display_sidebar();
	// optionally display a message if there is one.
	if( !empty( $msg ) && $auth_only == false ){
		echo $msg;
	}
	$wpsc = get_option( 'wpstagecoach' );

	if( ! $wpsc ){ // nothing returned 
		$redirect_to_main_page = true;
	}

	if( isset( $wpsc['errormsg'] ) ){
		wpsc_display_error( $wpsc['errormsg'] );
		unset( $wpsc['errormsg'] );
		update_option( 'wpstagecoach', $wpsc );
	}


	// if we have _POST data, we are going to update the settings
	if( ! empty($_POST['wpstagecoach-settings']) ){

		if( ( empty( $_POST['wpsc-username'] ) || empty( $_POST['wpsc-apikey'] ) ) && $wpsc['subscription'] != 'hosting' ){
			$errmsg = __( 'The User name and API Key fields below are required.', 'wpstagecoach' );

			wpsc_display_error($errmsg);	
		} else {

			// We do this so if you enable or disable the debug menu, you see it immediately
			if( (
				( (!empty($_POST['wpsc-debug']) && !isset($wpsc['debug']) ) ||
				(empty($_POST['wpsc-debug']) && isset($wpsc['debug']) && $wpsc['debug'] ==  true ) ) ||

				( (!empty($_POST['wpsc-advanced']) && !isset($wpsc['advanced']) ) ||
				(empty($_POST['wpsc-advanced']) && isset($wpsc['advanced']) && $wpsc['advanced'] ==  true ) )
				) && ! isset( $_POST['wpstagecoach-settings-main-page-redirect'] ) 
			) {
				_e( 'updating...', 'wpstagecoach' );
				$redirect = true;
			}

			// check that we have valid entries in this post.
			$wpsc = wpsc_check_options_sanity( $wpsc );

			// delete the transient so we check again that everything is peachy now that we've updated things
			delete_transient( 'wpstagecoach_sanity' );

			if( update_option('wpstagecoach', $wpsc) !== false ){
				$msg = 'Settings successfully updated!<br/>'.PHP_EOL;
			}

			if( isset( $redirect ) && true == $redirect ){
				wpsc_force_redirect( 'admin.php?page=wpstagecoach_settings' );
			}


		}
		if( isset( $_POST['wpstagecoach-settings-main-page-redirect'] ) && 1 == $_POST['wpstagecoach-settings-main-page-redirect'] ){
			wpsc_force_redirect( 'admin.php?page=wpstagecoach' );
			die;
		}
	}

	// letting users know they have to enter their login info
	if( ( empty( $wpsc['username'] ) || empty( $wpsc['apikey'] ) ) && ( ! isset( $wpsc['subscription'] ) || ( isset( $wpsc['subscription'] ) && $wpsc['subscription'] != 'hosting' ) ) ){
		$errmsg = __( '<p>You must enter your authentication information before you can use WP Stagecoach.</p>', 'wpstagecoach' );
		$errmsg .= __( '<p>Your authentication information may be found on your <a href="https://wpstagecoach.com/your-account" target="_blank">account page on wpstagecoach.com</a>.</p>', 'wpstagecoach' );
		wpsc_display_error($errmsg);
	}

	// making the submit button have different text
	if ( !empty( $wpsc['username']) || !empty($wpsc['apikey']) ){
		$subtext = __( 'Update', 'wpstagecoach' );
	} else{
		$subtext = __( 'Submit', 'wpstagecoach' );
	}
	
	// if false (normal) we display the form with the data, otherwise, we only check & update authenication info (usually with _POST data, above)
	if( $auth_only != true ){  

		if( isset($wpsc['debug']) && $wpsc['debug'] ) //  if we have the debug option saved, we need to check it in the form below
			$wpsc_debug = 'checked';

		if( isset($wpsc['delete_settings']) && $wpsc['delete_settings'] ) //  if we have the delete_settings option saved, we need to check it in the form below
			$wpsc_delete_settings = 'checked';

		if( isset($wpsc['advanced']) && $wpsc['advanced'] ) //  if we have the delete_settings option saved, we need to check it in the form below
			$wpsc_advanced = 'checked';

		if( isset($wpsc['slow']) && $wpsc['slow'] ) //  if we have the delete_settings option saved, we need to check it in the form below
			$wpsc_slow = 'checked'; ?>

		<?php settings_errors(); ?>
		<?php
		if( isset( $_POST['wpstagecoach-settings'] ) && "Update" == $_POST['wpstagecoach-settings'] ) {
		?>
		    <div id="message" class="updated">
		        <p><strong><?php _e( 'Settings saved.', 'wpstagecoach' ); ?></strong></p>
		    </div>
		<?php
		} ?>

		<form method="POST" id="wpstagecoach-settings" >
			<?php if( isset( $redirect_to_main_page ) && true == $redirect_to_main_page ){ ?>
				<input type="hidden" id="wpstagecoach-settings-main-page-redirect" name="wpstagecoach-settings-main-page-redirect" value=1>
			<?php } ?>
			<table class="form-table">
				<?php if( ! isset( $wpsc['subscription'] ) || ( isset( $wpsc['subscription'] ) && $wpsc['subscription'] != 'hosting' ) ){ ?>
				<tr><th valign="top"><?php _e( 'User name:', 'wpstagecoach' ); ?></th>
					<td valign="top">
						<input type="text" size="40" name="wpsc-username" value="<?php echo ( isset( $wpsc['username'] ) ? $wpsc['username'] : '' ); ?>"/>
					</td>
				</tr>
				<tr><th valign="top"><?php _e( 'API Key:', 'wpstagecoach' ); ?></th>
					<td valign="top">
						<?php if( isset( $wpsc['apikey'] ) ){
							echo '<input type="text" autocomplete="off" size="40" name="wpsc-apikey" value="••••••••••••••••••••••••••••••"/>';
						} else {
							echo '<input type="text" autocomplete="off" size="40" name="wpsc-apikey" value=""/>';
						}
						?>
					</td>
				</tr>
				<?php } else {
					if( isset( $wpsc['hosting-logo'] ) && !empty( $wpsc['hosting-logo'] ) ){
						echo '<p>' . __( 'WP Stagecoach staging sites provided your hosting company:' ) . '</p>' . PHP_EOL;
						echo '<img src="' . $wpsc['hosting-logo'] . '">' . PHP_EOL;
					}
				} ?>
				<tr><th valign="top"><label for="wpsc-delete-settings"><?php _e( 'Delete plugin settings when you disable the plugin?', 'wpstagecoach' ); ?></label></th>
					<td valign="top">
						<input type="checkbox" id="wpsc-delete-settings" name="wpsc-delete-settings" <?php echo ( isset( $wpsc_delete_settings ) ? $wpsc_delete_settings : '' ); ?>/>
					</td>
				</tr>
				<tr><th valign="top"><label for="wpsc-advanced"><?php _e( 'Enable Advanced Options?', 'wpstagecoach' ); ?></label><a href="#" class="tooltip"><i class="dashicons dashicons-info"></i><span class="open"><b></b>Selecting this option gives you an additional menu for advanced settings.<br />If you are having trouble creating a staging site, some of the advanced settings might help.</span></a></th>
					<td valign="top">
						<input type="checkbox" id="wpsc-advanced" name="wpsc-advanced" <?php echo ( isset( $wpsc_advanced ) ? $wpsc_advanced : '' ); ?> />
					</td>
				</tr>
				<tr>
					<th valign="top"><label for="wpsc-debug"><?php _e( 'Enable Debug menu?', 'wpstagecoach' ); ?></label></th>
					<td valign="top">
						<input type="checkbox" id="wpsc-debug" name="wpsc-debug" <?php echo ( isset( $wpsc_debug ) ? $wpsc_debug : '' ); ?> />
					</td>
				</tr>
				<tr>
					<th valign="top"><label for="wpsc-slow"><?php _e( 'Optimize WP Stagecoach for a slower server?', 'wpstagecoach' ); ?></label><a href="#" class="tooltip"><i class="dashicons dashicons-info"></i><span class="open"><b></b>This option makes it easier for WP Stagecoach to make a staging site when the live site is on a slow server.  If staging site creation is stalling, check this option.</span></a></th>
					<td valign="top">
						<input type="checkbox" id="wpsc-slow" name="wpsc-slow" <?php echo ( isset( $wpsc_slow ) ? $wpsc_slow : '' ); ?> />
					</td>
				</tr>
			</table>
			<input class="button button-submit" type="submit" name="wpstagecoach-settings" value="<?php echo $subtext; ?>" />
		</form>

		<?php
	} // end if $auth_only

	wpsc_display_footer();
}


function wpstagecoach_advanced() {
	define( 'WPSTAGECOACH_ACTION', 'advanced' );
	   ##    #####   #    #    ##    #    #   ####   ######  #####
	  #  #   #    #  #    #   #  #   ##   #  #    #  #       #    #
	 #    #  #    #  #    #  #    #  # #  #  #       #####   #    #
	 ######  #    #  #    #  ######  #  # #  #       #       #    #
	 #    #  #    #   #  #   #    #  #   ##  #    #  #       #    #
	 #    #  #####     ##    #    #  #    #   ####   ######  #####

	require_once( WPSTAGECOACH_INCLUDES_DIR . '/wpsc-functions.inc.php' );
	wpsc_display_header();
	$wpsc = get_option( 'wpstagecoach' );
	_e( '<h3>Advanced Options</h3>', 'wpstagecoach' );
	wpsc_display_sidebar();

	if( !empty($_POST) ){
		// verify our nonce isn't nonsense
		if ( ! $res = wp_verify_nonce( $_POST['wpsc-advanced-nonce'], 'wpstagecoach-advanced' ) ) {
			echo __( 'Invalid security step - please try again.', 'wpstagecoach' ); 
			return;
		}

		if( isset( $_POST['wpsc-clear-retrieved-changes'] ) ){
			delete_option( 'wpstagecoach_retrieved_changes' );
			delete_option( 'wpstagecoach_importing' );
			delete_option( 'wpstagecoach_importing_files' );
			delete_option( 'wpstagecoach_importing_db' );
			if( is_file( WPSTAGECOACH_TEMP_DIR . $wpsc['import-file'] ) ){
				unlink( WPSTAGECOACH_TEMP_DIR . $wpsc['import-file'] );
				unset( $wpsc['import-file'] );
				update_option( 'wpstagecoach', $wpsc );
			}
			_e( '<p>The list of changes retrieved from your staging site has been removed.</p>', 'wpstagecoach' );
		}

		if( isset( $_POST['wpsc-delete-sanity-transient'] ) ){
			delete_transient( 'wpstagecoach_sanity' );
			_e( '<p>The WP Stagecoach sanity transient has been removed.</p>', 'wpstagecoach' );
		}

		if( isset( $_POST['wpsc-disable-step-nonce'] ) ){ // disable the step nonces
			$wpsc['disable-step-nonce'] = true;
			if( update_option( 'wpstagecoach', $wpsc ) !== false ) {
				_e( '<p>Successfully disabled step nonces!</p>', 'wpstagecoach' );
			}
		} elseif( !isset( $_POST['wpsc-disable-step-nonce'] ) && isset( $wpsc['disable-step-nonce'] ) ){ // remove it from the options
			unset( $wpsc['disable-step-nonce'] );
			if( update_option( 'wpstagecoach', $wpsc ) !== false ) {
				_e( '<p>Successfully re-enabled step nonces!</p>', 'wpstagecoach' );
			}
		}

		if( isset( $_POST['wpsc-tar-all-files-at-once'] ) ){ // disable the step nonces
			$wpsc['tar-all-files-at-once'] = true;
			if( update_option( 'wpstagecoach', $wpsc ) !== false ) {
				_e( '<p>The tar file will be created in a single step.</p>', 'wpstagecoach' );
			}
		} elseif( !isset( $_POST['wpsc-tar-all-files-at-once'] ) && isset( $wpsc['tar-all-files-at-once'] ) ){ // remove it from the options
			unset( $wpsc['tar-all-files-at-once'] );
			if( update_option( 'wpstagecoach', $wpsc ) !== false ) {
				_e( '<p>The tar file will be created normally.</p>', 'wpstagecoach' );
			}
		}

		// disable the large table custom iterations
		if( isset( $_POST['wpsc-mysql-dont-use-big-iterations'] ) ){
			$wpsc['mysql-dont-use-big-iterations'] = true;
			if( update_option( 'wpstagecoach', $wpsc ) !== false ) {
				_e( '<p>The plugin will use the regular iteration size when dumping a large table from the database.</p>', 'wpstagecoach' );
			}
		} elseif( !isset( $_POST['wpsc-mysql-dont-use-big-iterations'] ) && isset( $wpsc['mysql-dont-use-big-iterations'] ) ){ // remove it from the options
			unset( $wpsc['mysql-dont-use-big-iterations'] );
			if( update_option( 'wpstagecoach', $wpsc ) !== false ) {
				_e( '<p>The plugin will use a large iteration size when dumping a large table from the database.</p>', 'wpstagecoach' );
			}
		}

		// advanced mysql custom rows dumped per step
		if( isset( $_POST['wpsc-advanced-create-mysql-rows-per-step'] ) ){ 
			$wpsc['advanced-create-mysql-rows-per-step'] = $_POST['wpsc-advanced-create-mysql-rows-per-step-number'];
			if( update_option( 'wpstagecoach', $wpsc ) !== false ) {
				_e( '<p>The plugin will use the number of rows per step you specified when dumping the database.</p>', 'wpstagecoach' );
			}
		} elseif( ! isset( $_POST['wpsc-advanced-create-mysql-rows-per-step'] ) && isset( $wpsc['advanced-create-mysql-rows-per-step'] ) ){ // remove it from the stored options
			unset( $wpsc['advanced-create-mysql-rows-per-step'] );
			if( update_option( 'wpstagecoach', $wpsc ) !== false ) {
				_e( '<p>The plugin will use the regular number of rows per step when dumping the database.</p>', 'wpstagecoach' );
			}
		}


		// advanced mysql custom iteration size
		if( isset( $_POST['wpsc-advanced-create-mysql-custom-iterations'] ) ){
			$wpsc['advanced-create-mysql-custom-iterations'] = true;
			if( update_option( 'wpstagecoach', $wpsc ) !== false ) {
				_e( '<p>The plugin will use the values you have specified for the iterations for mysql when creating a staging site.</p>', 'wpstagecoach' );
			}
		} elseif( !isset( $_POST['wpsc-advanced-create-mysql-custom-iterations'] ) && isset( $wpsc['advanced-create-mysql-custom-iterations'] ) ){ // remove it from the options
			unset( $wpsc['advanced-create-mysql-custom-iterations'] );
			if( isset( $wpsc['advanced-create-mysql-custom-iterations-sizes'] ) ){ // remove all the selected tables
				unset( $wpsc['advanced-create-mysql-custom-iterations-sizes'] );
			}

			if( update_option( 'wpstagecoach', $wpsc ) !== false ) {
				_e( '<p>The plugin will use the default iterations for mysql when creating a staging site.</p>', 'wpstagecoach' );
			}
		}

		// for the list of directories to skip
		if( isset( $_POST['wpsc-advanced-create-skip-directories'] ) ){
			$wpsc['advanced-create-skip-directories'] = true;
			if( update_option( 'wpstagecoach', $wpsc ) !== false ) {
				_e( '<p>The plugin will skip the directories you have specified when creating a staging site.</p>', 'wpstagecoach' );
			}
		} elseif( !isset( $_POST['wpsc-advanced-create-skip-directories'] ) && isset( $wpsc['advanced-create-skip-directories'] ) ){ // remove it from the options
			unset( $wpsc['advanced-create-skip-directories'] );
			if( isset( $wpsc['advanced-create-skip-directories-list'] ) ){ // remove all the selected tables
				unset( $wpsc['advanced-create-skip-directories-list'] );
			}

			if( update_option( 'wpstagecoach', $wpsc ) !== false ) {
				_e( '<p>The plugin will not skip any directories when creating a staging site.</p>', 'wpstagecoach' );
			}
		}

		// for the mysql bypass tables form
		if( isset( $_POST['wpsc-advanced-create-mysql-bypass-tables'] ) ){
			$wpsc['advanced-create-mysql-bypass-tables'] = true;
			if( update_option( 'wpstagecoach', $wpsc ) !== false ) {
				_e( '<p>You can select tables to bypass during staging site creation now.</p>', 'wpstagecoach' );
			}
		} elseif( !isset( $_POST['wpsc-advanced-create-mysql-bypass-tables'] ) && isset( $wpsc['advanced-create-mysql-bypass-tables'] ) ){ // remove it from the options
			unset( $wpsc['advanced-create-mysql-bypass-tables'] );
			if( isset( $wpsc['advanced-create-mysql-bypass-table-list'] ) ){ // remove all the selected tables
				unset( $wpsc['advanced-create-mysql-bypass-table-list'] );
			}

			if( update_option( 'wpstagecoach', $wpsc ) !== false ) {
				_e( '<p>The plugin will create a staging site using all the tables as normal.</p>', 'wpstagecoach' );
			}
		}
		echo '<hr/>'.PHP_EOL;
	}

	//  now display the form for all the advanced options

	$advanced_nonce = wp_create_nonce( 'wpstagecoach-advanced' );


	echo '<form method="POST" id="wpsc-advanced" >'.PHP_EOL;
	echo '<input type="hidden" name="wpsc-advanced-nonce" value="' . $advanced_nonce . '" />' . PHP_EOL;

	//  delete all changes retrieved from staging site from our local database
	echo '<p><input type="checkbox" id="wpsc-clear-retrieved-changes" name="wpsc-clear-retrieved-changes" /><label for="wpsc-clear-retrieved-changes">' . __( 'Delete the list of staging site changes from local database', 'wpstagecoach' ) . '</label></p>'.PHP_EOL;

	//  delete transient
	echo '<p><input type="checkbox" id="wpsc-delete-sanity-transient" name="wpsc-delete-sanity-transient" /><label for="wpsc-delete-sanity-transient">' . __( 'Delete the WP Stagecoach sanity transient', 'wpstagecoach' ) . '</label></p>'.PHP_EOL;

	//  disable step nonces
	echo '<p><input type="checkbox" id="wpsc-disable-step-nonce" name="wpsc-disable-step-nonce"' . ( isset( $wpsc['disable-step-nonce'] ) ? ' checked ' : ' ') . '/><label for="wpsc-disable-step-nonce" />' . __( 'Disable the step nonces (don\'t check unless you know what you\'re doing)', 'wpstagecoach' ) . '</label></p>'.PHP_EOL;

	//  allow users to create the tar file all at once
	echo '<p><input type="checkbox" id="wpsc-tar-all-files-at-once" name="wpsc-tar-all-files-at-once"' . ( isset( $wpsc['tar-all-files-at-once'] ) ? ' checked ' : ' ') . '/><label for="wpsc-tar-all-files-at-once" />' . __( 'Create the tar file in a single step.', 'wpstagecoach' ) . '</label></p>'.PHP_EOL;

	//  allow users to force it not to use large iterations from the database when there is a very large table to go through
	echo '<p><input type="checkbox" id="wpsc-mysql-dont-use-big-iterations" name="wpsc-mysql-dont-use-big-iterations"' . ( isset( $wpsc['mysql-dont-use-big-iterations'] ) ? ' checked ' : ' ') . '/><label for="wpsc-mysql-dont-use-big-iterations" />' . __( 'Don\'t use a larger iteration sizes when dealing with a large table in the database.', 'wpstagecoach' ) . '</label></p>'.PHP_EOL;

	if( isset( $wpsc['debug'] ) && true == $wpsc['debug'] ){ // display debug-related entries too

		//  allow users to specify a custom number of database rows to dump per step
		echo '<p><input type="checkbox" id="wpsc-advanced-create-mysql-rows-per-step" name="wpsc-advanced-create-mysql-rows-per-step"' . ( isset( $wpsc['advanced-create-mysql-rows-per-step'] ) ? ' checked ' : ' ') . '/><label for="wpsc-advanced-create-mysql-rows-per-step" />' . __( 'Use a custom number of rows dumped per step when creating database file.', 'wpstagecoach' ) . '</label></p>'.PHP_EOL;
		wpsc_advanced_display_create_mysql_rows_per_step();

		//  allow users to specify a custom interation for mysql
		echo '<p><input type="checkbox" id="wpsc-advanced-create-mysql-custom-iterations" name="wpsc-advanced-create-mysql-custom-iterations"' . ( isset( $wpsc['advanced-create-mysql-custom-iterations'] ) ? ' checked ' : ' ') . '/><label for="wpsc-advanced-create-mysql-custom-iterations" />' . __( 'Use custom iteration sizes when creating database file.', 'wpstagecoach' ) . '</label></p>'.PHP_EOL;
		wpsc_advanced_display_create_mysql_custom_iterations_form();

		//  allow users to force it not to use large iterations from the database when there is a very large table to go through
		echo '<p><input type="checkbox" id="wpsc-advanced-create-skip-directories" name="wpsc-advanced-create-skip-directories"' . ( isset( $wpsc['advanced-create-skip-directories'] ) ? ' checked ' : ' ') . '/><label for="wpsc-advanced-create-skip-directories" />' . __( 'Skip specified directories when creating tar file.', 'wpstagecoach' ) . '</label></p>'.PHP_EOL;
		wpsc_advanced_display_create_skip_directories_form();

		//  allow users to not include certain tables in creation if they also have the debug menu turned on.
		echo '<p><input type="checkbox" id="wpsc-advanced-create-mysql-bypass-tables" name="wpsc-advanced-create-mysql-bypass-tables"' . ( isset( $wpsc['advanced-create-mysql-bypass-tables'] ) ? ' checked ' : ' ') . '/><label for="wpsc-advanced-create-mysql-bypass-tables" />' . __( 'Select tables to bypass while creating the staging site?', 'wpstagecoach' ) . '</label></p>'.PHP_EOL;
		wpsc_advanced_display_create_mysql_tables_bypass_form();
	}
		
	echo '<input type="submit" class="button button-submit" name="wpstagecoach-settings" value="' . __( 'Submit', 'wpstagecoach' ) . '" />'.PHP_EOL;
	echo '</form>'.PHP_EOL;

	wpsc_display_footer();
}


function wpstagecoach_debug() {
	define( 'WPSTAGECOACH_ACTION', 'debug' );
	#####   ######  #####   #    #   ####
	#    #  #       #    #  #    #  #    #
	#    #  #####   #####   #    #  #
	#    #  #       #    #  #    #  #  ###
	#    #  #       #    #  #    #  #    #
	#####   ######  #####    ####    ####


	#################################################################################
	################                  DEBUG                 #########################
	#################################################################################
	require_once( WPSTAGECOACH_INCLUDES_DIR . '/wpsc-functions.inc.php' );
	wpsc_display_header();

	global $wpdb;

	global $wpsc_sanity;
	if( empty( $wpsc_sanity) )
		$wpsc_sanity = wpsc_sanity_check();

	_e( '<h3>Debug information:</h3>', 'wpstagecoach' );
	wpsc_display_sidebar();

	$wpsc = get_option( 'wpstagecoach' );
	
	if( file_exists( WPSTAGECOACH_TEMP_DIR . 'upload_test' ) ){ // from the upload test
		unlink( WPSTAGECOACH_TEMP_DIR . 'upload_test' );
	}

	//  If we're updating the debug settings, we want to do that before we start on the rest of the page.
	if( isset( $_POST['wpsc-debug-settings'] ) ) {
		// display debug messages
		if( isset( $_POST['wpsc-show-debug-messages'] ) && $_POST['wpsc-show-debug-messages'] == 'on' ){
			$wpsc['debug-messages'] = true;
			unset( $_POST['wpsc-show-debug-messages'] );
			$update_debug = true;
		} elseif( isset( $wpsc['debug-messages'] ) && !isset( $_POST['wpsc-show-debug-messages'] ) ) {
			unset( $wpsc['debug-messages'] );
			$update_debug = true;
		} elseif( !isset( $wpsc['debug-messages'] ) && !isset( $_POST['wpsc-show-debug-messages'] ) ) {
			_e( '<p>Nothing to update.</p>', 'wpstagecoach' );
			$update_debug = false;
		} else {
			$errmsg = sprintf( __( 'Error: the option %s, does not have a valid value.', 'wpstagecoach' ), 'debug-messages' );
			wpsc_display_error( $errmsg );
			$update_debug = false;
		}

		if( $update_debug == true){
			if( update_option( 'wpstagecoach', $wpsc ) !== false ) {
				_e( '<p>Debug messages option successfully updated!</p>', 'wpstagecoach' );
			}
		}
		unset( $_POST['wpsc-debug-settings'] );
	}


	if( empty($_POST) ){

		$debug_nonce = wp_create_nonce( 'wpstagecoach-debug' );


		//  offer to display debug messages
		echo '<form method="POST" id="wpsc-debug-settings" >'.PHP_EOL.'<table class="form-table">'.PHP_EOL;
		echo '<tr><th valign="top"><label for="wpsc-show-debug-messages">Display debug messages?</label></th>'.PHP_EOL;
		echo '<td valign="top"><input type="checkbox" id="wpsc-show-debug-messages" name="wpsc-show-debug-messages" '.(isset($wpsc['debug-messages']) ? 'checked' : '').' /><br /></td></tr>'.PHP_EOL;

		echo '</table>'.PHP_EOL;
		echo '<input type="hidden" name="wpsc-debug-nonce" value="' . $debug_nonce . '" />'.PHP_EOL;
		echo '<input type="submit" name="wpsc-debug-settings" value="' . __( 'Update', 'wpstagecoach' ) . '" />'.PHP_EOL;
		echo '</form><br/>'.PHP_EOL;


		echo '<h3>' . __( 'Status:', 'wpstagecoach' ) . '</h3>' .PHP_EOL;	

		// test the SSL connection to the WP Stagecoach servers
		$wpsc_sanity['https'] = wpsc_ssl_connection_test();
		if( $wpsc_sanity['https'] == 'ALL_GOOD' ){
			echo '<div class="wpstagecoach-info">' . __( 'Great! We are able to connect to the WP Stagecoach server via https.', 'wpstagecoach' ) . '<br/></div>'.PHP_EOL;
		}

		// check for known theme incompatibilities
		$themetest = wpsc_theme_check();
		if( $themetest === true ){
			echo '<div class="wpstagecoach-info">' . __( 'Great! Your theme doesn\'t have any known incompatibilities with WP Stagecoach.', 'wpstagecoach' ) . '<br/></div>'.PHP_EOL;
		} else {
			echo 
			'<div class="wpstagecoach-warn">
				<p>' . sprintf( __( 'Some users of your theme, %s, have reported problems doing automatic imports with WP Stagecoach.  ', 'wpstagecoach' ), get_option('current_theme') ) . 
				__( 'You may need to do a manual import instead of an automatic import.  ', 'wpstagecoach' ) . 
				'<a href="https://wpstagecoach.com/question/isnt-theme-compatible-wp-stagecoach/" target="_blank">' . __( 'More information about incompatible themes', 'wpstagecoach' ) .
			'</a></p>
			</div>';
		}


		// check for curl
		if ( function_exists( 'curl_version' ) && function_exists( 'curl_exec' ) ){
			$curl_info = curl_version();
			$msg = '<div class="wpstagecoach-info">' . __( 'Great! Your server supports the PHP cURL extension, version: ', 'wpstagecoach' ) . $curl_info['version'];
		} else {
			$post_options['curl-ver'] = 'NA';
			$msg = '<div class="wpstagecoach-warn">' . __( 'It looks like the PHP cURL extension is not installed. This may cause problems creating and importing your site if it is a large site.  You may wish to ask your web host to enable it for you.', 'wpstagecoach' );
		}
		echo $msg . '</div>' . PHP_EOL;

		// check if we have SQLite
		if ( class_exists( 'SQLite3' )  ){
			$sqlite_ver = SQLite3::version();
			$msg = '<div class="wpstagecoach-info">' . __( 'Great! Your server supports the PHP SQLite3 extension, version: ', 'wpstagecoach' ) . $sqlite_ver['versionString'];
		} else {
			$post_options['curl-ver'] = 'NA';
			$msg = '<div class="wpstagecoach-warn">' . __( 'It looks like the PHP SQLite3 extension is not installed. Having this extension greatly improves the reliability of WP Stagecoach imports.  You may wish to ask your web host to enable it for you.', 'wpstagecoach' );
		}
		echo $msg . '</div>' . PHP_EOL;



		// check if the upload_path or upload_dir_path are set, or UPLOADS is defined in wp-config.php
		$wpsc_sanity['upload_path'] = wpsc_check_upload_path();


		wpsc_check_write_permissions();


		echo '<h3>' . __( 'Locally-stored changes:', 'wpstagecoach' ) . '</h3>'.PHP_EOL;	
		if( $retr_changes = get_option('wpstagecoach_retrieved_changes') ){
			echo '<div><p>' . __( 'This is a summary of all the stored changes from your staging site:', 'wpstagecoach' ) . '</p>'.PHP_EOL;

			if( 'sqlite' == $retr_changes ){
				global $wpsc_sanity;
				$USER = explode( '.', $wpsc_sanity['auth']['stage-site'] );
				$USER = array_shift( $USER );
				$changes_file = $USER . '-changes.db';
				$dest_file = WPSTAGECOACH_TEMP_DIR . $changes_file;

				$changesdb = @new SQLite3( $dest_file );
				$changes_db_table_names = array();
				$retr_changes = array();

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

				foreach ( $changes_db_table_names as $type => $table ) {
					$query = 'select * from ' . $table .';';
					$result = $changesdb->query( $query );

					while ( $row = $result->fetchArray( SQLITE3_ASSOC ) ) {
						if( 'file' == $type ){
							$retr_changes['file'][ $row['file_action'] ][] = $row['file_name'];
						} elseif( 'db' == $type ){
							$retr_changes['db'][ $row['type_table'] ][] = $row['command'];
						}
					}
				} // end of foreach over $changes_db_table_names
				$changesdb->close();
			} // end of sqlite section making $retr_changes into the old style array.

			if( is_array( $retr_changes ) ){
				foreach ( $retr_changes as $type => $sub_array ) {
					echo __( 'retrieved_changes[', 'wpstagecoach' ) . $type . __( '] size: ', 'wpstagecoach' ) . sizeof( $sub_array ) ;

					if( is_array( $sub_array ) ){
						echo '<ul class="wpstagecoach-indented-list">' . PHP_EOL;
						foreach ($sub_array as $subtype => $subsub_array) {
							if( is_array( $subsub_array ) ){
								echo '<li> ['.$subtype.'] ' . __( 'size: ', 'wpstagecoach' ) .sizeof($subsub_array).'</li>';
							}
						}
						echo '</ul>' . PHP_EOL;
					}
				}
			} // end of writing out the summary of changes.

			echo '<form method="POST" id="wpsc-debug" >'.PHP_EOL;
			echo '<input type="hidden" name="wpsc-debug-nonce" value="' . $debug_nonce . '" />' . PHP_EOL;
			echo '<input type="submit" name="wpsc-show-all-stored-changes" value="' . __( 'Show absolutely all the retrieved changes', 'wpstagecoach' ) . '" />'.PHP_EOL;
			echo '</form>'.PHP_EOL;
		} else {
			echo __( 'No changes have been stored.', 'wpstagecoach' ) . '<br/>' . PHP_EOL;
		}


		echo '<h3>' . __( 'Site information:', 'wpstagecoach' ) . '</h3>'.PHP_EOL;	

		echo '<pre>';
		echo __( 'siteurl (from DB): ', 'wpstagecoach' ) . '<b>';
		$db_siteurl = $wpdb->get_row( 'select option_value from ' . $wpdb->prefix . 'options where option_name="siteurl"', 'ARRAY_A' );
		$db_siteurl = $db_siteurl['option_value'];
		echo $db_siteurl;
		echo '</b>'.PHP_EOL;
		echo __( ' "option" siteurl: ', 'wpstagecoach' ) . '<b>' . print_r( get_option('siteurl') ,true ) . '</b>'.PHP_EOL;
		echo __( '   home (from DB): ', 'wpstagecoach' ) . '<b>';
		$db_siteurl = $wpdb->get_row( 'select option_value from ' . $wpdb->prefix . 'options where option_name="home"', 'ARRAY_A' );
		$db_siteurl = $db_siteurl['option_value'];
		echo $db_siteurl;
		echo '</b>'.PHP_EOL;
		echo __( '    "option" home: ', 'wpstagecoach' ) . '<b>' . print_r( get_option('home'), true ) . '</b>'.PHP_EOL;
		echo '</pre>';

		echo '<h3>' . __( 'Site directory information:', 'wpstagecoach' ) . '</h3>' . PHP_EOL;
		echo '<pre>';
		// get and report site's current file path
		$dir_arr = explode( '/', getcwd() );
		array_pop( $dir_arr ); // we start in wp-admin/
		echo __('     current path: ', 'wpstagecoach') . '<b>' . implode( '/', $dir_arr ) . '</b>' . PHP_EOL;
		echo __('          ABSPATH: ', 'wpstagecoach') . '<b>' . ABSPATH . '</b>' . PHP_EOL;
		echo __('   WP_CONTENT_DIR: ', 'wpstagecoach') . '<b>' . WP_CONTENT_DIR . '</b>' . PHP_EOL;
		echo __(' get_theme_root(): ', 'wpstagecoach') . '<b>' . get_theme_root() . '</b>' . PHP_EOL;
		$dir_arr = explode( '/', plugin_dir_path( __FILE__ ) );
		unset( $dir_arr[ sizeof( $dir_arr ) -1 ] );
		unset( $dir_arr[ sizeof( $dir_arr ) -1 ] );
		echo __('plugin_dir_path(): ', 'wpstagecoach') . '<b>' . implode( '/', $dir_arr ) . '</b>' . PHP_EOL;

		echo '</pre>';



		// check free disk space
		wpsc_display_disk_space_warning( @disk_free_space('.') );

		if( !function_exists( 'gzopen' ) ){
			$errmsg = __( 'Your server does not support the gzip function "gzopen", which is required by WP Stagecoach.  Please ask your webhost to enable this extension.', 'wpstagecoach' );
			wpsc_display_error( $errmsg );
		}

		// sanity check
		$db_siteurl = $wpdb->get_row( 'select option_value from ' . $wpdb->prefix . 'options where option_name="siteurl"', 'ARRAY_N' );
		$db_siteurl = rtrim( array_shift( $db_siteurl ), '/' );
		$opt_siteurl = rtrim( get_option( 'siteurl'), '/' );
		if( $opt_siteurl != $db_siteurl ){
			echo '<div class="wpstagecoach-error">' . PHP_EOL;
				echo '<h3>' . __( 'Your current Site URL is different from what is stored in the database.', 'wpstagecoach' ) . '</h3>' . PHP_EOL;
				echo '<p>' . __( 'This makes it very difficult for WP Stagecoach to reliably automatically change your site URL in the database so the site will work on the staging server.', 'wpstagecoach' ) . '</p>' . PHP_EOL;
				echo '<p>' . __( 'One common cause of this is that the <a href="http://codex.wordpress.org/Editing_wp-config.php#WordPress_address_.28URL.29" rel="nofollow">WP_SITEURL</a> 
				and the <a href="http://codex.wordpress.org/Editing_wp-config.php#Blog_address_.28URL.29" rel="nofollow">WP_HOME</a> are hard-coded into the
				<b>wp-config.php</b> file to change the URL of the site.', 'wpstagecoach' ) . '</p>' . PHP_EOL;
				echo '<p>' . __( 'For example, you may see this in your wp-config.php file:', 'wpstagecoach' ) . PHP_EOL;
					echo '<pre>define(\'WP_HOME\',\''.get_option('home').'\');' . PHP_EOL;
					echo 'define(\'WP_SITEURL\',\''.get_option('siteurl').'\');</pre>';
				echo '<p>' . __( 'If you do see this, you might consider running a serialized search and replace over your database to permanently change the Site URL.<br/>
				<a href="http://interconnectit.com/products/search-and-replace-for-wordpress-databases/" rel="nofollow">interconnect/it</a>
				has a wonderful product on github called <a href="https://github.com/interconnectit/Search-Replace-DB" rel="nofollow">Search Replace DB</a>.', 'wpstagecoach' ) . '</p>' . PHP_EOL;
				echo '<p>' . __( 'Unfortunately, WP Stagecoach cannot proceed further automatically. :-(', 'wpstagecoach' ) . '</p>' . PHP_EOL;
			echo '</div>' . PHP_EOL;
		}

		if( get_option('siteurl') != get_option('home') ){
			$errmsg  = '<h3>'  . __( 'WordPress is installed in a different directory from your URL', 'wpstagecoach' ) . '</h3>' . PHP_EOL;
			$errmsg .= '<p><a href="http://codex.wordpress.org/Giving_WordPress_Its_Own_Directory" rel="nofollow">' . __( 'More information about this.', 'wpstagecoach' ) . '</a></p>' . PHP_EOL;
			$errmsg .= '<p>'   . __( 'Unfortunately, WP Stagecoach does not yet support this: WP Stagecoach requires WordPress to be installed in the same directory as your site URL to run reliably.  Here is the data we found:', 'wpstagecoach' ) . '<ul>' . PHP_EOL;
			$errmsg .= '<li>'  . __( 'On the General Settings page, the Site Address (URL) is set to: ', 'wpstagecoach' ) . '<b>' . get_option('siteurl') . '</b></li>' . PHP_EOL;
			$errmsg .= '<li>'  . __( 'The actual URL of your site (in the title bar) is: ', 'wpstagecoach' ) . '<b>' . get_option('home') . '</b></li>' . PHP_EOL;
			$errmsg .= '</ul>' . __( 'Your staging site may not display or function properly.  You may proceed at your own risk.', 'wpstagecoach' ) . '</p>' . PHP_EOL;
			wpsc_display_error($errmsg);
		}


		//  show log files and offer to send them to WP Stagecoach support.
		if( file_exists( WPSTAGECOACH_TEMP_DIR . 'create_debug.log' ) && // they have at least a create_debug.log file
			isset( $wpsc['advanced'] ) && true == $wpsc['advanced'] // display advanced-related entries too
			){ 
			wpsc_debug_display_debug_logs( $debug_nonce );
		}

		// make sure all the files checksums match those from wpstagecoach.com
		echo '<p><form method="POST" id="wpsc-debug" >'.PHP_EOL;
		echo '<input type="hidden" name="wpsc-debug-nonce" value="' . $debug_nonce . '" />' . PHP_EOL;
		echo '<input type="submit" class="button button-submit" name="wpsc-check-chksums" value="' . __( 'Check the integrity of your plugin', 'wpstagecoach' ) . '" />'.PHP_EOL;
		echo '</form></p>'.PHP_EOL;


		// show the size of all the directories in the root of the site
		echo '<p><form method="POST" id="wpsc-debug" >'.PHP_EOL;
		echo '<input type="hidden" name="wpsc-debug-nonce" value="' . $debug_nonce . '" />' . PHP_EOL;
		echo '<input type="submit" class="button button-submit" name="wpsc-check-filesystem" value="' . __( 'Check the size of directories on your site', 'wpstagecoach' ) . '" />'.PHP_EOL;
		echo '</form></p>'.PHP_EOL;

		// check upload speed with WP Stagecoach servers
		echo '<p><form method="POST" id="wpsc-debug" >'.PHP_EOL;
		echo '<input type="hidden" name="wpsc-debug-nonce" value="' . $debug_nonce . '" />' . PHP_EOL;
		echo '<input type="submit" class="button button-submit" name="wpsc-upload-test" value="' . __( 'Test your site\'s upload speed', 'wpstagecoach' ) . '" />'.PHP_EOL;
		echo '</form></p>'.PHP_EOL;

		// Show what tables your database has, and how many entries they have
		echo '<p><form method="POST" id="wpsc-debug" >'.PHP_EOL;
		echo '<input type="hidden" name="wpsc-debug-nonce" value="' . $debug_nonce . '" />' . PHP_EOL;
		echo '<input type="submit" class="button button-submit" name="wpsc-show-database-info" value="' . __( 'Show information about your database', 'wpstagecoach' ) . '" />'.PHP_EOL;
		echo '</form></p>'.PHP_EOL;


		// show the entire sanity variable
		echo '<p><a class="toggle">' . __( 'Show full environment details', 'wpstagecoach' ) . '</a></p>';
		echo '<div class="more" style="display: none">'.PHP_EOL;
		echo '<pre>';
		echo print_r( $wpsc_sanity, true );
		echo '</pre>';
		echo '</div>'.PHP_EOL;


	} else { // $_POST is Not empty AND we don't want to display the main page!

		// show all the stored changes in their entirety
		if( isset($_POST['wpsc-show-all-stored-changes']) ){
			wpsc_debug_show_changes();
		}

		// check the filesystem for usage
		if( isset($_POST['wpsc-check-filesystem']) ){
			wpsc_debug_show_dir_sizes();
		}

		// test upload speed to staging server
		if( isset($_POST['wpsc-upload-test']) ){
			wpsc_debug_test_upload();
		}
		
		// test upload speed to staging server
		if( isset($_POST['wpsc-show-database-info']) ){
			wpsc_debug_show_database_info();
		}

		// make sure all the files checksums match those from wpstagecoach.com
		if( isset($_POST['wpsc-check-chksums']) ){
			wpsc_debug_check_checksums();
		}

		//  send the selected log files
		if( isset($_POST['wpsc-send-logfiles']) ){
			wpsc_debug_send_debug_logs();
		}

		// update site_url or home_url
		if( isset($_POST['wpsc-update-siteurl-or-homeurl']) ){
			wpsc_update_siteurl_or_homeurl();
		}

	}
	wpsc_display_footer();
} // end of DEBUG

function wpstagecoach_plugin_updater() {
	$wpsc = get_option('wpstagecoach');
	if( isset( $wpsc['apikey'] ) ){
		if( !class_exists( 'EDD_SL_Plugin_Updater' ) ) {
			require_once( WPSTAGECOACH_INCLUDES_DIR . '/wpsc-plugin-updater.php' );
		}
		$updater = new EDD_SL_Plugin_Updater( WPSTAGECOACH_CONDUCTOR . '/wpsc-plugin-update.php', __FILE__, array( 
				'version'	=> WPSTAGECOACH_VERSION,
				'license'	=> $wpsc['apikey'],
				'apikey'	=> $wpsc['apikey'],
				'username'	=> $wpsc['username'],
				'item_name' => 'WP Stagecoach',
				'author'	=> 'WP Stagecoach',
			)
		);
	}
}
add_action( 'admin_init', 'wpstagecoach_plugin_updater' );


function wpsc_submit_feedback() {
	require_once( WPSTAGECOACH_INCLUDES_DIR . '/wpsc-functions.inc.php' );


	$post_details = array();
	$post_url = WPSTAGECOACH_CONDUCTOR.'/wpsc-feedback.php';

	parse_str($_POST['information'], $post_details);


	// verify nonce
	add_filter( 'nonce_life', 'wpstagecoach_feedback_nonce_time' );
	if ( !wp_verify_nonce( $post_details['wpstagecoach-feedback-nonce'], "wpstagecoach-feedback-nonce")) {
		$error = __( 'Time out. Please refresh the page and try again.', 'wpstagecoach' );
        exit( $error);
    }


	// gather a little bit of information so we can set up the staging site on the proper server environment
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
	$post_options['wpsc-mysqls-ver']	= $db->server_version;
	$post_options['wpsc-mysqls-info']	= $db->server_info;
	$post_options['wpsc-mysqlc-ver']	= mysqli_get_client_version();
	$post_options['wpsc-mysqlc-info']	= mysqli_get_client_info();
	$db->close();

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
		$post_options['uses-ssl'] = true;
	} else {
		$post_options['uses-ssl'] = false;
	}
	
	$curr_theme = wp_get_theme();
	$post_options['theme-name'] = $curr_theme->get( 'Name' );
	$post_options['theme-version'] = $curr_theme->get( 'Version' );
	$post_options['theme-URL'] = $curr_theme->get( 'ThemeURI' );
	$post_options['theme-author'] = $curr_theme->get( 'AuthorURI' );


	$post_details['options'] = $post_options;


	// pick up log files if they are there
	if( is_file(WPSTAGECOACH_TEMP_DIR.'import.log')){
		$post_details['importlog'] = file_get_contents(WPSTAGECOACH_TEMP_DIR.'import.log');
	}
	if( is_file(WPSTAGECOACH_TEMP_DIR.'create_debug.log')){
		$post_details['createlog'] = file_get_contents(WPSTAGECOACH_TEMP_DIR.'create_debug.log');
	}

	$post_args = array(
		'timeout' => 120,
		'body' => $post_details,
	);
	global $wpsc_sanity;
	if( empty( $wpsc_sanity) )
		$wpsc_sanity = wpsc_sanity_check();
	if( !isset($wpsc_sanity['https']) ){
		$wpsc_sanity['https'] = wpsc_ssl_connection_test();
	}
	if( $wpsc_sanity['https'] == 'NO_CA' ){
	 	$post_args['sslverify'] = false;
	} elseif( $wpsc_sanity['https'] == 'NO_CURL' ) {
		add_filter('use_curl_transport', '__return_false');
	}
	
	$post_result = wp_remote_post($post_url, $post_args );
	$result = wpsc_check_post_info('feedback', $post_url, $post_details, $post_result) ; // check response from the server

	if( $result['result'] != 'OK' ){
		wpsc_display_error( print_r($result['info'],true) );
		return false;
	} else {
		$result = '<div class="wpsc-thankyou">Thank you for your feedback--it is invaluable to us!</div>';
	}

	die($result);
}
add_action( 'wp_ajax_wpsc_submit_feedback', 'wpsc_submit_feedback' );

function wpsc_manual_import_ajax() {
	require_once( WPSTAGECOACH_INCLUDES_DIR . '/wpsc-functions.inc.php' );
	add_filter( 'nonce_life', 'wpstagecoach_manual_nonce_time' );
	// verify nonce
	if ( !wp_verify_nonce( $_POST['nonce'], "wpsc_manual_import_nonce") ) {
		$error = __( 'Time out. Please refresh the page and try again.', 'wpstagecoach' );
		exit( $error);
	}
	$output = '';

	// check basic sanity, it will display error if it fails, if so we need to return.
	$wpsc_sanity = wpsc_sanity_check('import');
	if( false == $wpsc_sanity ){
		$errmsg = '<p>' . __( 'We couldn\'t determine some basic information for your staging site.  Please refresh this page and try again.', 'wpstagecoach' ) . '</p>';
		wpsc_display_error( $errmsg );
		return false;
	}

	$post_url = WPSTAGECOACH_CONDUCTOR . '/wpsc-make-manual-files.php';
	$post_details = array(
		'wpsc-user'			=> $_POST['user'],
		'wpsc-key'			=> $_POST['apikey'],
		'wpsc-ver'			=> WPSTAGECOACH_VERSION,
		'wpsc-live-site'	=> $wpsc_sanity['auth']['live-site'],
		'wpsc-stage-site'	=> $wpsc_sanity['auth']['stage-site'],
		'wpsc-live-path'	=> rtrim( ABSPATH, '/' ),
		'wpsc-dest'			=> $wpsc_sanity['auth']['server'],

	);

	if( ! empty( $_POST['wpsc-options'] ) ){
		$post_details['wpsc-options'] = $_POST['wpsc-options'];
	}

	$post_args = array(
		'timeout' => 300,
		'body' => $post_details
	);
	
	// do some SSL sanity
	global $wpsc_sanity;
	if( empty( $wpsc_sanity) )
		$wpsc_sanity = wpsc_sanity_check();
	if( !isset($wpsc_sanity['https']) ){
		$wpsc_sanity['https'] = wpsc_ssl_connection_test();
	}
	if( $wpsc_sanity['https'] == 'NO_CA' ){
	 	$post_args['sslverify'] = false;
	} elseif( $wpsc_sanity['https'] == 'NO_CURL' ) {
		add_filter('use_curl_transport', '__return_false');
	}

	$post_result = wp_remote_post( $post_url, $post_args );
	$result = wpsc_check_post_info( 'make-manual-files', $post_url, $post_details, $post_result, false ) ; // check response from the server

	if( $result['result'] == 'OK' && isset( $result['info'] ) && is_array( $result['info'] ) ){
		foreach ( $result['info'] as $type => $filename ) {
			if( $filename == 'ZIPEMPTY' ){
				$output .= '<p>' . __( 'No file changes were found.', 'wpstagecoach' ) . '</p>' . PHP_EOL;
			} else {
				$download_url = 'https://' . $wpsc_sanity['auth']['server'] . '/wpsc-app-download-manual-file.php';
				$download_args = '?wpsc-file=' . $filename . '&' . http_build_query( $post_details );
				$output .= '<p>' . $type . ': <a href="' . $download_url . $download_args . '" target="_blank">' . $filename. '</a><br />' . PHP_EOL;
				$wpsc[ $type . '-manual-file' ] = $filename;

				if( !preg_match('/.+\..+\..+-[0-9]{4}-[A-Z][a-z]{2}-[0-9]{2}_[0-9]{2}:[0-9]{2}\.' . $type . '/', $filename ) ){
					// that is: staging-site.wpstagecoach.com-YYYY-Mmm-DD_HH:MM.type
					$errmsg = sprintf( __( 'Error: the retrieved filename "%s", does not appear to be valid.', 'wpstagecoach' ), $filename );
					wpsc_display_error( $errmsg );
					die;
				}
			}
		}
		update_option( 'wpstagecoach', $wpsc );
		die( $output );
	} else {
		$errmsg  = '<p>' . __( 'There was a problem checking for changes on your staging site ', 'wpstagecoach' ) .  '.</p>';
		$errmsg .= '<p>' . __( 'Please contact WP Stagecoach support with this error information:', 'wpstagecoach' ) . '<pre>';

		if( is_array( $result ) ){
			$errmsg .= print_r( $result['info'], true );
		} else {
			$errmsg .= print_r( $result, true );
		}
		$errmsg .= '</pre>' . __( 'Please refresh this page to try again.', 'wpstagecoach' ) . '</p>';
		wpsc_display_error( $errmsg, false );
		die();
	}
}
add_action( 'wp_ajax_wpsc_manual_import_ajax', 'wpsc_manual_import_ajax' );

function wpstagecoach_update_step_nonce() {
	require_once( WPSTAGECOACH_INCLUDES_DIR . '/wpsc-functions.inc.php' );
	$next_step = $_POST['step']++;
	$result = wpsc_set_step_nonce( $_POST['type'], $next_step );
	die( $result );
}
add_action( 'wp_ajax_wpstagecoach_update_step_nonce', 'wpstagecoach_update_step_nonce');





///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
######  ######
#     # #     #
#     # #     #
#     # ######
#     # #     #
#     # #     #
######  ######
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function wpstagecoach_do_db_import() {
	require_once( WPSTAGECOACH_INCLUDES_DIR . '/wpsc-functions.inc.php' );

	// verify nonce


	$wpsc = get_option( 'wpstagecoach' );
	if( ! isset( $wpsc['rows_per_refresh'] ) && ! empty( $wpsc['rows_per_refresh'] ) ){
		$rows_per_refresh = $wpsc['rows_per_refresh'];
	} else {
		$rows_per_refresh = WPSTAGECOACH_ROWS_PER_REFRESH;
	}

	// $return_val['status'] = 'finished';
	// $return_val['type'] = 'database';
	// die( json_encode( $return_val ) ); // return this to JS


	$rows_per_refresh = 100;


		$changesdb = wpstagecoach_open_changes_file();
		$query = 'SELECT count(*) FROM wpstagecoach_database_imports WHERE processed="selected";';
		$result = $changesdb->query( $query );
		$row = $result->fetchArray( SQLITE3_ASSOC );
		$return_val['rowsleft'] = $row['count(*)'];






		$query = 'SELECT id,command FROM wpstagecoach_database_imports WHERE processed="selected" LIMIT ' . $rows_per_refresh . ';';
		if( ! $result = $changesdb->query( $query ) ){
			$errmsg .= 'We encountered an error with the SQLite database: "' . $changesdb->lastErrorMsg . '"<br/>';
			$errmsg .= 'Query: ' . $query . '<br/>';

			$return_val['status'] = 'error';
			$return_val['message'] = $errmsg;

			die( json_encode( $return_val ) );
		}

		$count = 0;
		while( $row = $result->fetchArray( SQLITE3_ASSOC ) ) {
			if( 0 == $count ){
				$first_row = $row['id'];
			}
			// $wpdb->query( $row['command'] );
			$processed_query = 'UPDATE wpstagecoach_database_imports set processed="processed ' . $import_time . '" where id=' . $row['id'];
			$processed_result = $changesdb->exec( $processed_query );

			if( $changesdb->lastErrorCode() ){
				$errmsg .= 'Could not update the SQLite database to reflect the moved file "' . $file . '".<br/>';
				$errmsg .= 'We received the following error: ' . $changesdb->lastErrorMsg();
				break;
			}

			$count++;
			if( $rows_per_refresh == $count ){
				$last_row = $row['id'];
			}
		} // end of while over $import_db
		$result->finalize();


		$return_val['first'] = $first_row;
		$return_val['last'] = $last_row;
		$return_val['count'] = $count;

		if( $count != $rows_per_refresh ){
			$return_val['status'] = 'finished';
		} else {
			$return_val['status'] = 'continue';
		}




	die( json_encode( $return_val ) ); // return this to JS
	if( 0 ){


		// verify nonce

		// grab the latest finished row from the database


			$start_row = get_option( 'wpstagecoach_import_last_row' );


		// echo '<br/>start row: '.$start_row . '<br/>';
		// 

		// do the import

			for ( $i = 0; $i < $rows_per_refresh; $i++ ) { 
				// echo  $start_row + $i . ' ';
				// echo  $start_row + $i . '<br/>';
			}
			$end_row = $start_row + $rows_per_refresh;
			// echo  'i: '.  $i . '<br/>';

			// echo   '<br/>';

		// save the latest finished row to the database

		// echo '<br/>end row: '.$end_row . '<br/>';
			update_option( 'wpstagecoach_import_last_row', ($end_row + 1) );


			if( $end_row > 400 ){
				$result = 'finished';
			} else {
				// $result = intval( $end_row );
				$result = $end_row;
			}


		// echo $result;
		// $result = 'finished';
		// $result = intval( '500' );
		// $result = 500;

		// $result = array();
		// $result['status'] = 'good';
		// $result['lastrow'] = $end_row;
		// $result = json_encode( $result );


		die( (string) $result );





			if( everything_went_smoothly() && we_still_have_more_to_do() ) {
				$result = intval( $latest_finished_row );
			} elseif( everything_went_smoothly() && we_are_all_done() ) {
				$result = 'finished';
			} else {
				$result = 'error';
			}


		  die( $result );
} // end if( 0 )

}
add_action( 'wp_ajax_wpstagecoach_do_db_import', 'wpstagecoach_do_db_import' );


///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
 ######     #    #       ######
 #          #    #       #
 #####      #    #       #####
 #          #    #       #
 #          #    #       #
 #          #    ######  ######
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function wpstagecoach_do_file_import() {
	require_once( WPSTAGECOACH_INCLUDES_DIR . '/wpsc-functions.inc.php' );

		global $flog;
	// verify nonce
	$emailmsg='';
			$continue = false;








		$wpsc = get_option( 'wpstagecoach' );
		if( ! isset( $wpsc['rows_per_refresh'] ) && ! empty( $wpsc['rows_per_refresh'] ) ){
			$rows_per_refresh = $wpsc['rows_per_refresh'];
		} else {
			$rows_per_refresh = WPSTAGECOACH_ROWS_PER_REFRESH;
		}

	// $return_val['status'] = 'finished';
	// die( json_encode( $return_val ) ); // return this to JS


	$rows_per_refresh = 100;

	// DB: id, file_action, file_name, processed

		$changesdb = wpstagecoach_open_changes_file();
		$query = 'SELECT count(*) FROM wpstagecoach_file_imports WHERE processed="selected";';
		$result = $changesdb->query( $query );
		$row = $result->fetchArray( SQLITE3_ASSOC );
		$return_val['rowsleft'] = $row['count(*)'];
		$result->finalize();


			if( LOG ) fwrite( $flog, 'starting work on file changes' . PHP_EOL );
	$emailmsg .= 'starting work on file changes' . PHP_EOL ;


			foreach ( array( 'new', 'modified', 'deleted' ) as $action_type ){
				if( LOG  ) fwrite( $flog, 'Working on ' . $action_type . ' files.' . PHP_EOL );
	$emailmsg .= 'Working on ' . $action_type . ' files' . PHP_EOL ;

				// check if we have any selected files for a given action
				// $query = 'SELECT id,command FROM wpstagecoach_files_imports WHERE processed="selected" LIMIT ' . $rows_per_refresh . ';';
				$query =  'SELECT COUNT(*) as count FROM wpstagecoach_file_imports WHERE processed="selected" and file_action="' . $action_type . '";';
				$result = $changesdb->query( $query );
				$num_rows = $result->fetchArray( SQLITE3_ASSOC );
				$result->finalize();
	$emailmsg .= 'query ' . $query . PHP_EOL;
	$emailmsg .= 'num_rows ' . print_r( $num_rows, true ) . PHP_EOL;

				if( $num_rows['count'] < 1 ){
					continue;
				}



				$query =  'SELECT id,file_name FROM wpstagecoach_file_imports WHERE processed="selected" and file_action="' . $action_type . '" LIMIT ' . $rows_per_refresh . ';';

				if( ! $result = $changesdb->query( $query ) ){
	$emailmsg .= 'We encountered an error with the SQLite database: "' . $changesdb->lastErrorMsg . '"' . PHP_EOL;
					$errmsg .= 'We encountered an error with the SQLite database: "' . $changesdb->lastErrorMsg . '"<br/>';
					$errmsg .= 'Query: ' . $query . '<br/>';

					$return_val['status'] = 'error';
					$return_val['message'] = $errmsg;

					die( json_encode( $return_val ) );
				}

				$count = 0;
	$emailmsg .= 'id ';
				while( $row = $result->fetchArray( SQLITE3_ASSOC ) ) {
	$emailmsg .= $row['id'] . ', ';
					if( 0 == $count ){
						$first_row = $row['id'];
					}
					// $wpdb->query( $row['command'] );
					$processed_query = 'UPDATE wpstagecoach_file_imports set processed="processed ' . $import_time . '" where id=' . $row['id'];
					$processed_result = $changesdb->exec( $processed_query );
					if( $changesdb->lastErrorCode() ){
	$emailmsg .= PHP_EOL.PHP_EOL. 'Could not update the SQLite database to reflect the moved file "' . $file . '".' . PHP_EOL;
						$errmsg .= 'Could not update the SQLite database to reflect the moved file "' . $file . '".<br/>';
						$errmsg .= 'We received the following error: ' . $changesdb->lastErrorMsg();
						break;
					}

					$count++;
					if( $rows_per_refresh == $count ){
						$last_row = $row['id'];
					}
				} // end of while over "selected" rows within the limit
	$emailmsg .= PHP_EOL.PHP_EOL;
				$result->finalize();
				$return_val['rowsleft'] = $return_val['rowsleft'] - $count;



				// if( $count < $rows_per_refresh ){
				if( $count > 0 ){  // if we've already done some imports, we need to break out
					$continue = true;
					break;
				}

			} // end of foreach over new, modified, deleted


	$return_val['type'] = 'files';
		$return_val['first'] = $first_row;
		$return_val['last'] = $last_row;
		$return_val['count'] = $count;

		if( $count == $rows_per_refresh || true == $continue ){
			$return_val['status'] = 'continue';
		} else {
			$return_val['status'] = 'finished';
		}

	$emailmsg .= 'count ' . $count . PHP_EOL;
	$emailmsg .= 'rows_per_refresh ' . $rows_per_refresh . PHP_EOL;
	$emailmsg .= 'return_val ' . print_r( $return_val, true ) . PHP_EOL;



	// mail( 'jpk@alchemycs.com' , 'do_file_import ran: ' . $count,
	// 		'wtf!' . PHP_EOL . date() . PHP_EOL . $emailmsg
	// 	);


		die( json_encode( $return_val ) ); // return this to JS

}
add_action( 'wp_ajax_wpstagecoach_do_file_import', 'wpstagecoach_do_file_import' );

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
   ##         #    ##    #    #
  #  #        #   #  #    #  #
 #    #       #  #    #    ##
 ######       #  ######    ##
 #    #  #    #  #    #   #  #
 #    #   ####   #    #  #    #
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function wpstagecoach_do_ajax_import(){
	require_once( WPSTAGECOACH_INCLUDES_DIR . '/wpsc-functions.inc.php' );

	// do logging if enabled
	if(LOG){
		$logname = WPSTAGECOACH_TEMP_DIR . 'import.log';
		$flog = fopen($logname, 'a');
	}




// verify nonce





// $errmsg .= __( 'TESTING ' ) . '</p>'.PHP_EOL;
// $return_val['message'] = $errmsg;
// $return_val['status'] = 'error';
// die( json_encode( $return_val ) ); // return this to JS



	$wpsc = get_option( 'wpstagecoach' );
	if( ! isset( $wpsc['rows_per_refresh'] ) && ! empty( $wpsc['rows_per_refresh'] ) ){
		$rows_per_refresh = $wpsc['rows_per_refresh'];
	} else {
		$rows_per_refresh = WPSTAGECOACH_ROWS_PER_REFRESH;
	}

// $return_val['status'] = 'finished';
// die( json_encode( $return_val ) ); // return this to JS


$rows_per_refresh = 100;

// DB: id, file_action, file_name, processed

	$changesdb = wpstagecoach_open_changes_file();
	// $query = 'SELECT count(*) FROM wpstagecoach_file_imports WHERE processed="selected";';
	// $result = $changesdb->query( $query );
	// $row = $result->fetchArray( SQLITE3_ASSOC );
	// $return_val['rowsleft'] = $row['count(*)'];
	// $result->finalize();


		// if( LOG ) fwrite( $flog, 'starting work on file changes' . PHP_EOL );

		$continue = false;
		foreach ( array( 'new', 'modified', 'deleted', 'db' ) as $action_type ){
			if( LOG ) fwrite( $flog, 'Working on ' . $action_type . ' files.' . PHP_EOL );

			switch ( $action_type ) {
				case 'new':
				case 'modified':
				case 'deleted':
					$count_query =  'SELECT COUNT(*) as num_selected FROM wpstagecoach_file_imports WHERE processed="selected" and file_action="' . $action_type . '";';
					$selected_query =  'SELECT id,file_name FROM wpstagecoach_file_imports WHERE processed="selected" and file_action="' . $action_type . '" LIMIT ' . $rows_per_refresh . ';';
					break;

				case 'db':
					global $wpdb;
					$count_query =  'SELECT COUNT(*) as num_selected FROM wpstagecoach_database_imports WHERE processed="selected";';
					$selected_query = 'SELECT id,command FROM wpstagecoach_database_imports WHERE processed="selected" LIMIT ' . $rows_per_refresh . ';';
					break;
			}

			$result = $changesdb->query( $count_query );
			$row = $result->fetchArray( SQLITE3_ASSOC );
			$return_val['rowsleft'] = $row['num_selected'];

			$result->finalize();

			if( $return_val['rowsleft'] < 1 ){
				continue;
			}





			if( ! $result = $changesdb->query( $selected_query ) ){
				$errmsg .= 'We encountered an error with the SQLite database: "' . $changesdb->lastErrorMsg . '"<br/>';
				$errmsg .= 'Query: ' . $selected_query . '<br/>';
				$return_val['status'] = 'error';
				$return_val['message'] = $errmsg;
				die( json_encode( $return_val ) );
			}

			$count = 0;
			while( $row = $result->fetchArray( SQLITE3_ASSOC ) ) {
				if( 0 == $count ){
					$first_row = $row['id'];
				}


				switch ( $action_type ) {
					case 'new':
					case 'modified':
					case 'deleted':
						$processed_query = 'UPDATE wpstagecoach_file_imports set processed="processed ' . $import_time . '" where id=' . $row['id'];
						$return_val['type'] = 'file';
						chdir( get_home_path() );  // need to get into the root directory of the site






///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////


						$file = $row['file_name'];
						if( LOG ) fwrite( $flog, 'working on ' . $file . ' -- it is ' . $action_type . '<br/>'.PHP_EOL );

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
							$return_val['message'] = $errmsg;
							$return_val['status'] = 'error';

							die( json_encode( $return_val ) ); // return this to JS
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
									$return_val['message'] = $errmsg;
									$return_val['status'] = 'error';
									die( json_encode( $return_val ) ); // return this to JS
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
									$return_val['message'] = $errmsg;
									$return_val['status'] = 'error';
									die( json_encode( $return_val ) ); // return this to JS
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
									$return_val['message'] = $errmsg;
									$return_val['status'] = 'error';
									die( json_encode( $return_val ) ); // return this to JS
								}
							} else {
								$rename_old = true;
							}
						}


						// move away old file
						if( true == $rename_old ){
							if( ! rename( $file, $file . '.wpsc_temp' ) ){
								// couldn't move file - something bad is going on.
								$errmsg .=  '<p>' . sprintf( __( 'Error: could not move the %s file from "./wp-content/plugins/wpstagecoach/temp/extract/%s" to "./%s".  Please check permissions on this directory: %s', 'wpstagecoach' ), $action_type, $file, $file, dirname($file) ) . '<p>';
								$normal = false;
							} elseif( 'deleted' == $action_type ){
								$processed_query = 'UPDATE wpstagecoach_file_imports set processed="processed ' . $import_time . '" where id=' . $row['id'];
								$processed_result = $changesdb->exec( $processed_query );
								if( $changesdb->lastErrorCode() ){
									$normal = false;
									$errmsg .= 'Could not update the SQLite database to reflect the change in processed for "' . $file . '".<br/>';
									$errmsg .= 'We received the following error: ' . $changesdb->lastErrorMsg();
									$return_val['message'] = $errmsg;
									$return_val['error'] = true;
									die( json_encode( $return_val ) ); // return this to JS
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
								$errmsg .=  '<p>' . sprintf( __( 'Error: could not move the %s file from "./wp-content/plugins/wpstagecoach/temp/extract/%s" to "./%s".  Please check permissions on this directory: %s', 'wpstagecoach' ), $action_type, $file, $file, dirname($file) ) . '<p>';
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
									$return_val['message'] = $errmsg;
									$return_val['error'] = true;
									die( json_encode( $return_val ) ); // return this to JS
								}
							}

						}
						if( ! $normal ){
							$return_val['message'] = $errmsg;
							$return_val['status'] = 'error';
							die( json_encode( $return_val ) ); // return this to JS
						}
			






///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////








						break;

					case 'db':
						$processed_query = 'UPDATE wpstagecoach_database_imports set processed="processed ' . $import_time . '" where id=' . $row['id'];
						$return_val['type'] = 'database';
						// $return_val['message'] = $row['command'] ;
file_put_contents( '/var/www/coach/website/wp-content/plugins/wpstagecoach/temp/import.log', 'doing command "' . $row['command'] . '" now' . PHP_EOL, FILE_APPEND );
						// $return_val['message'] = $row['id'] . ' ' ;
						$return_val['message'] = $row['id'] . ' ' . $wpdb->query( $row['command'] );
file_put_contents( '/var/www/coach/website/wp-content/plugins/wpstagecoach/temp/import.log', 'return value: "' .$return_val['message']  . '" now' . PHP_EOL, FILE_APPEND );

						break;
				}




				$processed_result = $changesdb->exec( $processed_query );
				if( $changesdb->lastErrorCode() ){
					$errmsg .= 'Could not update the SQLite database to reflect the ' . $action_type . ' file "' . $file . '".<br/>';
					$errmsg .= 'We received the following error: ' . $changesdb->lastErrorMsg();
					$return_val['message'] = $errmsg;
					$return_val['error'] = true;
					break;
				}

				$count++;
				if( $rows_per_refresh == $count ){
					$last_row = $row['id'];
				}
			} // end of while over "selected" rows within the limit
			$result->finalize();
			$return_val['rowsleft'] = $return_val['rowsleft'] - $count;



			// if( $count < $rows_per_refresh ){
			if( $count > 0 ){  // if we've already done some imports, we need to break out
				$continue = true;
				break;
			}

		} // end of foreach over new, modified, deleted


	// $return_val['type'] = $action_type;
	$return_val['first'] = $first_row;
	$return_val['last'] = $last_row;
	$return_val['count'] = $count;

	if( $count == $rows_per_refresh || true == $continue ){
		$return_val['status'] = 'continue';
	} else {
		$return_val['status'] = 'finished';
	}


	die( json_encode( $return_val ) ); // return this to JS
}
add_action( 'wp_ajax_wpstagecoach_do_ajax_import', 'wpstagecoach_do_ajax_import' );




































