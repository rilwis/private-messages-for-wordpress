<?php
/*
Plugin Name: Private Messages For WordPress
Plugin URI: http://www.deluxeblogtips.com/private-messages-for-wordpress
Description: Allow members of WordPress blog send and receive private messages (PM)
Version: 2.1.9
Author: Rilwis
Author URI: http://www.deluxeblogtips.com
License: GNU GPL 2+
*/

// Prevent loading this file directly
defined( 'ABSPATH' ) || exit;

define( 'PM4WP_DIR', plugin_dir_path( __FILE__ ) );
define( 'PM4WP_INC_DIR', trailingslashit( PM4WP_DIR . 'inc' ) );

define( 'PM4WP_URL', plugin_dir_url( __FILE__ ) );
define( 'PM4WP_CSS_URL', trailingslashit( PM4WP_URL . 'css' ) );
define( 'PM4WP_JS_URL', trailingslashit( PM4WP_URL . 'js' ) );

include_once PM4WP_INC_DIR . 'widget.php';
include_once PM4WP_INC_DIR . 'inbox-page.php';
include_once PM4WP_INC_DIR . 'send-page.php';
include_once PM4WP_INC_DIR . 'outbox-page.php';

if ( is_admin() )
{
	include_once PM4WP_INC_DIR . 'options.php';
}

register_activation_hook( __FILE__, 'rwpm_activate' );
add_action( 'plugins_loaded', 'rwpm_load_text_domain' );
add_action( 'admin_notices', 'rwpm_notify' );
add_action( 'admin_bar_menu', 'rwpm_adminbar', 300 );
add_action( 'wp_ajax_rwpm_get_users', 'rwpm_get_users' );

/**
 * Load plugin text domain
 *
 * @return void
 */
function rwpm_load_text_domain()
{
	load_plugin_textdomain( 'pm4wp', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
}

/**
 * Create table and register an option when activate
 *
 * @return void
 */
function rwpm_activate()
{
	global $wpdb;

	// Create table
	$query = 'CREATE TABLE IF NOT EXISTS ' . $wpdb->prefix . 'pm (
		`id` bigint(20) NOT NULL auto_increment,
		`subject` text NOT NULL,
		`content` text NOT NULL,
		`sender` varchar(60) NOT NULL,
		`recipient` varchar(60) NOT NULL,
		`date` datetime NOT NULL,
		`read` tinyint(1) NOT NULL,
		`deleted` tinyint(1) NOT NULL,
		PRIMARY KEY (`id`)
	) COLLATE utf8_general_ci;';

	// Note: deleted = 1 if message is deleted by sender, = 2 if it is deleted by recipient

	$wpdb->query( $query );

	// Default numbers of PM for each group
	$default_option = array(
		'administrator' => 0,
		'editor'        => 50,
		'author'        => 20,
		'contributor'   => 10,
		'subscriber'    => 5,
		'type'          => 'dropdown', // How to choose recipient: dropdown list or autocomplete based on user input
		'email_enable'  => 1,
		'email_name'    => '%BLOG_NAME%',
		'email_address' => '%BLOG_ADDRESS%',
		'email_subject' => __( 'New PM at %BLOG_NAME%', 'pm4wp' ),
		'email_body'    => __( "You have new private message from <b>%SENDER%</b> at <b>%BLOG_NAME%</b>.\n\n<a href=\"%INBOX_URL%\">Click here</a> to go to your inbox.\n\nThis email is sent automatically. Please don't reply.", 'pm4wp' )
	);
	add_option( 'rwpm_option', $default_option, '', 'no' );
}

/**
 * Show notification of new PM
 */
function rwpm_notify()
{
	global $wpdb, $current_user;

	// get number of unread messages
	$num_unread = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'pm WHERE `recipient` = "' . $current_user->user_login . '" AND `read` = 0 AND `deleted` != "2"' );

	if ( !$num_unread )
		return;

	printf(
		'<div id="message" class="error"><p><b>%s</b> <a href="%s">%s</a></p></div>',
		sprintf( _n( 'You have %d new message!', 'You have %d new messages!', $num_unread, 'pm4wp' ), $num_unread ),
		admin_url( 'admin.php?page=rwpm_inbox' ),
		__( 'Click here to go to inbox', 'pm4wp' )
	);
}

/**
 * Show number of unread messages in admin bar
 */
function rwpm_adminbar()
{
	global $wp_admin_bar;
	global $wpdb, $current_user;

	// get number of unread messages
	$num_unread = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'pm WHERE `recipient` = "' . $current_user->user_login . '" AND `read` = 0 AND `deleted` != "2"' );

	if ( $num_unread && is_admin_bar_showing() )
	{
		$wp_admin_bar->add_menu( array(
			'id'    => 'rwpm',
			'title' => sprintf( _n( 'You have %d new message!', 'You have %d new messages!', $num_unread, 'pm4wp' ), $num_unread ),
			'href'  => admin_url( 'admin.php?page=rwpm_inbox' ),
			'meta'  => array( 'class' => "rwpm_newmessages" ),
		) );
	}
}

/**
 * Ajax callback function to get list of users
 */
function rwpm_get_users()
{
	$keyword = trim( strip_tags( $_POST['term'] ) );
	$values = array();
	$args = array( 'search' => '*' . $keyword . '*',
	               'fields' => 'all_with_meta' );
	$results_search_users = get_users( $args );
	$results_search_users = apply_filters( 'rwpm_recipients', $results_search_users );
	if ( !empty( $results_search_users ) )
	{
		foreach ( $results_search_users as $result )
		{
			$values[] = $result->display_name;
		}
	}
	die( json_encode( $values ) );
}
/**
 * Handle file upload
 *
 * @param string $name Name of the input field
 * @param array  $args Arguments
 *
 * @return mixed Single ID or array of IDs of uploaded files
 */
function rwpm_handle_upload( $name, $args = array() )
{
	if ( empty( $_FILES[$name] ) )
		return null;

	$args = wp_parse_args( $args, array(
		'error_setting' => '',
		'multiple'      => true,
		'extensions'    => array( 'jpg', 'jpeg', 'gif', 'png', 'pdf', 'doc', 'docx', 'ppt', 'pptx', 'pps', 'ppsx', 'odt', 'xls', 'xlsx', 'mp3', 'm4a', 'mp4', 'avi'  ),
	) );
	extract( $args );

	// Get list of uploaded files
	// Force to use array to make it easier to use foreach below
	$files = $multiple ? rwpm_fix_file_array( $_FILES[$name] ) : array( $_FILES[$name] );

	$uploaded = array();
	foreach ( $files as $file_item )
	{
		if ( $file_item['error'] )
			continue;

		// Check file extension
		$ext = strtolower( substr( $file_item['name'], strrpos( $file_item['name'], '.' ) + 1 ) );
		if ( !in_array( $ext, $extensions ) )
		{
			if ( $error_setting )
				add_settings_error( $error_setting, $name, __( 'Invalid file extension.', 'pf' ), 'error' );
			continue;
		}

		$file = wp_handle_upload( $file_item, array( 'test_form' => false ) );

		if ( !isset( $file['file'] ) )
		{
			if ( $error_setting )
				add_settings_error( $error_setting, $name, __( 'Error uploading. Please try again.', 'pf' ), 'error' );
			continue;
		}

		$filename = $file['file'];

		$attachment = array(
			'post_mime_type' => $file['type'],
			'guid'           => $file['url'],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
			'post_content'   => ''
		);
		$id = wp_insert_attachment( $attachment, $filename );

		if ( is_wp_error( $id ) )
		{
			if ( $error_setting )
				add_settings_error( $error_setting, $name, __( 'Cannot insert attachment. Please try again.', 'pf' ), 'error' );
			continue;
		}
		else
		{
			wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $filename ) );
			$uploaded[] = $id;
		}
	}

	return $multiple ? $uploaded : array_pop( $uploaded );
}

/**
 * Fixes the odd indexing of multiple file uploads from the format:
 *     $_FILES['field']['key']['index']
 * To the more standard and appropriate:
 *     $_FILES['field']['index']['key']
 *
 * @param $files
 *
 * @return array
 */
function rwpm_fix_file_array( $files )
{
	$output = array();
	foreach ( $files as $key => $list )
	{
		foreach ( $list as $index => $value )
		{
			$output[$index][$key] = $value;
		}
	}
	return $output;
}
