<?php
/*
Plugin Name: Private Messages For WordPress
Plugin URI: http://www.deluxeblogtips.com/private-messages-for-wordpress
Description: Allow members of WordPress blog send and receive private messages (PM)
Version: 2.1.6
Author: Rilwis
Author URI: http://www.deluxeblogtips.com

Copyright 2009-2010 Rilwis (email : rilwis@gmail.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

include_once plugin_dir_path( __FILE__ ) . 'widget.php';

// load text domain for plugin
$locale = get_locale( );
$mofile = WP_PLUGIN_DIR . '/' . plugin_basename( dirname( __FILE__ ) ) . '/lang/pm4wp' . '-' . $locale . '.mo';
load_textdomain( 'pm4wp', $mofile );

/**
 * Register an option group
 */
add_action( 'admin_init', 'rwpm_init' );

function rwpm_init( ) {
	register_setting( 'rwpm_option_group', 'rwpm_option' );
}

/**
 * Create table and register an option when activate
 */
register_activation_hook( __FILE__, 'rwpm_activate' );

function rwpm_activate( ) {
	global $wpdb;

	// create table
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

	// default numbers of PM for each group
	$default_option = array(
		'administrator' => 0,
		'editor' => 50,
		'author' => 20,
		'contributor' => 10,
		'subscriber' => 5,
		'type' => 'dropdown', // how to choose recipient: dropdown list or autocomplete based on user input
		'email_enable' => 1,
		'email_name' => '%BLOG_NAME%',
		'email_address' => '%BLOG_ADDRESS%',
		'email_subject' => __( 'New PM at %BLOG_NAME%', 'pm4wp' ),
		'email_body' => __( "You have new private message from <b>%SENDER%</b> at <b>%BLOG_NAME%</b>.\n\n<a href=\"%INBOX_URL%\">Click here</a> to go to your inbox.\n\nThis email is sent automatically. Please don't reply.", 'pm4wp' )
	);
	add_option( 'rwpm_option', $default_option, '', 'no' );
}

/**
 * Delete table and option when uninstall
 */
register_uninstall_hook( __FILE__, 'rwpm_uninstall' );

function rwpm_uninstall( ) {
	global $wpdb;

	$wpdb->query( 'DROP table ' . $wpdb->prefix . 'pm' );

	delete_option( 'rwpm_option' );
}

/**
 * Add Option page and PM Menu
 */
add_action( 'admin_menu', 'rwpm_add_menu' );

function rwpm_add_menu( ) {
	global $wpdb, $current_user;

	// get number of unread messages
	$num_unread = $wpdb->get_var( 'SELECT COUNT(`id`) FROM ' . $wpdb->prefix . 'pm WHERE `recipient` = "' . $current_user->user_login . '" AND `read` = 0 AND `deleted` != "2"' );

	if ( empty( $num_unread ) )
		$num_unread = 0;

	// option page
	add_options_page( __( 'Private Messages Options', 'pm4wp' ), __( 'Private Messages', 'pm4wp' ), 'manage_options', 'rwpm_option', 'rwpm_option_page' );

	// add Private Messages Menu
	$icon_url = WP_PLUGIN_URL . '/' . plugin_basename( dirname( __FILE__ ) ) . '/icon.png';
	add_menu_page( __( 'Private Messages', 'pm4wp' ), __( 'Messages', 'pm4wp' ) . "<span class='update-plugins count-$num_unread'><span class='plugin-count'>$num_unread</span></span>", 'read', 'rwpm_inbox', 'rwpm_inbox', $icon_url );

	// inbox page
	add_submenu_page( 'rwpm_inbox', __( 'Inbox', 'pm4wp' ), __( 'Inbox', 'pm4wp' ), 'read', 'rwpm_inbox', 'rwpm_inbox' );
	// outbox page
	add_submenu_page( 'rwpm_inbox', __( 'Outbox', 'pm4wp' ), __( 'Outbox', 'pm4wp' ), 'read', 'rwpm_outbox', 'rwpm_outbox' );

	// send page	
	$send_page = add_submenu_page( 'rwpm_inbox', __( 'Send Private Message', 'pm4wp' ), __( 'Send', 'pm4wp' ), 'read', 'rwpm_send', 'rwpm_send' );
	add_action( "admin_print_styles-$send_page", 'rwpm_admin_print_styles' );
}

function rwpm_admin_print_styles( ) {
	wp_enqueue_style( 'rwpm_css', plugins_url( 'css/style.css', __FILE__ ) );
	wp_enqueue_script( 'rwpm_autosuggest_js', plugins_url( 'js/jquery.autoSuggest.packed.js', __FILE__ ), array( 'jquery' ) );
	wp_enqueue_script( 'rwpm_js', plugins_url( 'js/script.js', __FILE__ ), array( 'rwpm_autosuggest_js' ) );
}

/**
 * Option page
 * Change number of PMs for each group
 */
function rwpm_option_page( ) {
	?>
<div class="wrap">
	<h2><?php _e( 'Private Messages Options', 'pm4wp' ); ?></h2>

	<div style="width:600px;float:left">
		<form method="post" action="options.php">

			<?php
   		settings_fields( 'rwpm_option_group' );
			$option = get_option( 'rwpm_option' );

			if ( empty( $option['hide_update'] ) ) {
				echo '<div class="updated">',
				'<p><strong>', __( '1. The plugin goes with a page template for front-end usage.', 'pm4wp' ), '</strong></p>',
				'<p>', __( 'Copy file <code>pm4wp-template.php</code> to your theme folder and create a page with template <code>Private Messages</code>', 'pm4wp' ), '</p>',
				'<p>', __( 'The template is just the backbone. You should modify it to fit your theme.', 'pm4wp' ), '</p>',
				'<p></p><p><strong>', __( '2. You can send to multiple recipients now.', 'pm4wp' ), '</strong></p>',
				'</div>';
				echo '<input type="checkbox" name="rwpm_option[hide_update]"> ', __( 'Don\'t show this message next time', 'pm4wp' );
			}

			echo '<h3>', __( 'Please set numbers of private messages for each user role:', 'pm4wp' ), '</h3>';
			echo '<p>', __( '<b><i>0</i></b> means <b><i>unlimited</i></b>', 'pm4wp' ), '</p>';
			echo '<p>', __( '<b><i>-1</i></b> means <b><i>not allowed</i></b> to send PM', 'pm4wp' ), '</p>';


			?>
			<table class="form-table">
				<tr>
					<th><?php _e( 'Administrator', 'pm4wp' ); ?></th>
					<td>
						<input type="text" name="rwpm_option[administrator]" value="<?php echo $option['administrator']; ?>"/>
					</td>
				</tr>
				<tr>
					<th><?php _e( 'Editor', 'pm4wp' ); ?></th>
					<td><input type="text" name="rwpm_option[editor]" value="<?php echo $option['editor']; ?>"/></td>
				</tr>
				<tr>
					<th><?php _e( 'Author', 'pm4wp' ); ?></th>
					<td><input type="text" name="rwpm_option[author]" value="<?php echo $option['author']; ?>"/></td>
				</tr>
				<tr>
					<th><?php _e( 'Contributor', 'pm4wp' ); ?></th>
					<td>
						<input type="text" name="rwpm_option[contributor]" value="<?php echo $option['contributor']; ?>"/>
					</td>
				</tr>
				<tr>
					<th><?php _e( 'Subscriber', 'pm4wp' ); ?></th>
					<td><input type="text" name="rwpm_option[subscriber]" value="<?php echo $option['subscriber']; ?>"/>
					</td>
				</tr>
				<tr>
					<th><?php _e( 'How do you want to choose recipient?', 'pm4wp' ); ?></th>
					<td>
						<input type="radio" name="rwpm_option[type]" value="dropdown" <?php if ( $option['type'] == 'dropdown' )
							echo 'checked="checked"'; ?> /><?php _e( 'Dropdown list', 'pm4wp' ); ?>
						<input type="radio" name="rwpm_option[type]" value="autosuggest" <?php if ( $option['type'] == 'autosuggest' )
							echo 'checked="checked"'; ?> /><?php _e( 'Auto suggest from user input', 'pm4wp' ); ?>
					</td>
				</tr>
			</table>

			<h3><?php _e( 'Email template:', 'pm4wp' ); ?></h3>

			<table class="form-table">
				<tr>
					<th><?php _e( 'Enable sending email when user receive new PM?', 'pm4wp' ); ?></th>
					<td>
						<input type="radio" name="rwpm_option[email_enable]" value="1" <?php if ( $option['email_enable'] )
							echo 'checked="checked"'; ?> /> <?php _e( 'Yes', 'pm4wp' ); ?>
						<input type="radio" name="rwpm_option[email_enable]" value="0" <?php if ( !$option['email_enable'] )
							echo 'checked="checked"'; ?> /> <?php _e( 'No', 'pm4wp' ); ?>
					</td>
				</tr>
				<tr>
					<th><?php _e( 'From name (optional)', 'pm4wp' ); ?></th>
					<td><input type="text" name="rwpm_option[email_name]" value="<?php echo $option['email_name']; ?>"/>
					</td>
				</tr>
				<tr>
					<th><?php _e( 'From email (optional)', 'pm4wp' ); ?></th>
					<td>
						<input type="text" name="rwpm_option[email_address]" value="<?php echo $option['email_address']; ?>"/>
					</td>
				</tr>
				<tr>
					<th><?php _e( 'Subject', 'pm4wp' ); ?></th>
					<td>
						<input type="text" name="rwpm_option[email_subject]" value="<?php echo $option['email_subject']; ?>"/>
					</td>
				</tr>
				<tr>
					<th><?php _e( 'Body', 'pm4wp' ); ?></th>
					<td>
						<textarea name="rwpm_option[email_body]" rows="10" cols="50"><?php echo $option['email_body']; ?></textarea><br/>
						<?php _e( 'Allowed HTML tags: ', 'pm4wp' ); ?> a, br, b, i, u, img, ul, ol, li, hr
					</td>
				</tr>
				<tr>
					<th><strong><?php _e( 'Available tags', 'pm4wp' ); ?></strong></th>
					<td>&nbsp;</td>
				</tr>
				<tr>
					<th>%BLOG_NAME%</th>
					<td><?php _e( 'Blog name', 'pm4wp' ) ?></td>
				</tr>
				<tr>
					<th>%BLOG_ADDRESS%</th>
					<td><?php _e( 'Email address of blog', 'pm4wp' ) ?></td>
				</tr>
				<tr>
					<th>%SENDER%</th>
					<td><?php _e( 'Sender name', 'pm4wp' ) ?></td>
				</tr>
				<tr>
					<th>%INBOX_URL%</th>
					<td><?php _e( 'URL of inbox', 'pm4wp' ) ?></td>
				</tr>
			</table>

			<p class="submit">
				<input type="submit" name="submit" class="button-primary" value="<?php _e( 'Save Changes', 'pm4wp' ) ?>"/>
			</p>

		</form>

	</div>
	<div style="width:200px;float:right;border:1px solid #ccc;padding:10px">
		<h3><?php _e( 'Donation', 'pm4wp' ); ?></h3>

		<p><?php _e( 'This plugin has cost me countless hours of work, if you use it, please donate a token of your appreciation!', 'pm4wp' ); ?></p>

		<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
			<input type="hidden" name="cmd" value="_donations">
			<input type="hidden" name="business" value="rilwis@gmail.com"> <input type="hidden" name="lc" value="US">
			<input type="hidden" name="item_name" value="Private Messages For WordPress">
			<input type="hidden" name="item_number" value="pm4wp">
			<input type="hidden" name="currency_code" value="USD">
			<input type="hidden" name="bn" value="PP-DonationsBF:btn_donate_LG.gif:NonHosted">
			<input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donate_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
			<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1">
		</form>
	</div>
</div>
			<?php

}

/**
 * Send form page
 */
function rwpm_send( ) {
	global $wpdb, $current_user;
	?>
<div class="wrap">
<h2><?php _e( 'Send Private Message', 'pm4wp' ); ?></h2>
	<?php
	$option = get_option( 'rwpm_option' );
	if ( $_REQUEST['page'] == 'rwpm_send' && isset( $_POST['submit'] ) ) {
		$error = false;
		$status = array( );

		// check if total pm of current user exceed limit
		$role = $current_user->roles[0];
		$sender = $current_user->user_login;
		$total = $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'pm WHERE `sender` = "' . $sender . '" OR `recipient` = "' . $sender . '"' );
		if ( ( $option[$role] != 0 ) && ( $total >= $option[$role] ) ) {
			$error = true;
			$status[] = __( 'You have exceeded the limit of mailbox. Please delete some messages before sending another.', 'pm4wp' );
		}

		// get input fields with no html tags and all are escaped
		$subject = strip_tags( $_POST['subject'] );
		$content = strip_tags( $_POST['content'] );
		$recipient = $option['type'] == 'autosuggest' ? explode( ',', $_POST['recipient'] ) : $_POST['recipient'];
		$recipient = array_map( 'strip_tags', $recipient );
		if ( get_magic_quotes_gpc( ) ) {
			$subject = stripslashes( $subject );
			$content = stripslashes( $content );
			$recipient = array_map( 'stripslashes', $recipient );
		}
		$subject = esc_sql( $subject );
		$content = esc_sql( $content );
		$recipient = array_map( 'esc_sql', $recipient );

		// remove duplicate and empty recipient
		$recipient = array_unique( $recipient );
		$recipient = array_filter( $recipient );

		// check input fields
		if ( empty( $recipient ) ) {
			$error = true;
			$status[] = __( 'Please enter username of recipient.', 'pm4wp' );
		}
		if ( empty( $subject ) ) {
			$error = true;
			$status[] = __( 'Please enter subject of message.', 'pm4wp' );
		}
		if ( empty( $content ) ) {
			$error = true;
			$status[] = __( 'Please enter content of message.', 'pm4wp' );
		}

		/*
		// old check if recipient exists
		$recipient = $wpdb->get_var("SELECT user_login FROM $wpdb->users WHERE display_name = '$recipient' LIMIT 1");
		if (empty($recipient)) {
			$error = true;
			$status[] = __('Please enter correct username of recipient.', 'pm4wp');
		}
		*/

		/*
		// check if send to yourself
		if ($sender == $recipient) {
			$error = true;
			$status[] = __('Hey! Sending messages to yourself is not allowed.', 'pm4wp');
		}
		*/

		if ( !$error ) {
			$numOK = $numError = 0;
			foreach ( $recipient as $rec ) {
				// get user_login field
				$rec = $wpdb->get_var( "SELECT user_login FROM $wpdb->users WHERE display_name = '$rec' LIMIT 1" );
				$new_message = array(
					'id' => NULL,
					'subject' => $subject,
					'content' => $content,
					'sender' => $sender,
					'recipient' => $rec,
					'date' => current_time( 'mysql' ),
					'read' => 0,
					'deleted' => 0
				);
				// insert into database
				if ( $wpdb->insert( $wpdb->prefix . 'pm', $new_message, array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d' ) ) ) {
					$numOK++;
					unset( $_REQUEST['recipient'], $_REQUEST['subject'], $_REQUEST['content'] );

					// send email to user
					if ( $option['email_enable'] ) {

						$sender = $wpdb->get_var( "SELECT display_name FROM $wpdb->users WHERE user_login = '$sender' LIMIT 1" );

						// replace tags with values
						$tags = array( '%BLOG_NAME%', '%BLOG_ADDRESS%', '%SENDER%', '%INBOX_URL%' );
						$replacement = array( get_bloginfo( 'name' ), get_bloginfo( 'admin_email' ), $sender, admin_url( 'admin.php?page=rwpm_inbox' ) );

						$email_name = str_replace( $tags, $replacement, $option['email_name'] );
						$email_address = str_replace( $tags, $replacement, $option['email_address'] );
						$email_subject = str_replace( $tags, $replacement, $option['email_subject'] );
						$email_body = str_replace( $tags, $replacement, $option['email_body'] );

						// set default email from name and address if missed
						if ( empty( $email_name ) )
							$email_name = get_bloginfo( 'name' );

						if ( empty( $email_address ) )
							$email_address = get_bloginfo( 'admin_email' );

						$email_subject = strip_tags( $email_subject );
						if ( get_magic_quotes_gpc( ) ) {
							$email_subject = stripslashes( $email_subject );
							$email_body = stripslashes( $email_body );
						}
						$email_body = nl2br( $email_body );

						$recipient_email = $wpdb->get_var( "SELECT user_email from $wpdb->users WHERE display_name = '$rec'" );
						$mailtext = "<html><head><title>$email_subject</title></head><body>$email_body</body></html>";

						// set headers to send html email
						$headers = "To: $recipient_email\r\n";
						$headers .= "From: $email_name <$email_address>\r\n";
						$headers .= "MIME-Version: 1.0\r\n";
						$headers .= 'Content-Type: ' . get_bloginfo( 'html_type' ) . '; charset=' . get_bloginfo( 'charset' ) . "\r\n";

						wp_mail( $recipient_email, $email_subject, $mailtext, $headers );
					}
				} else {
					$numError++;
				}
			}

			$status[] = sprintf( _n( '%d message sent.', '%d messages sent.', $numOK, 'pm4wp' ), $numOK ) . ' ' . sprintf( _n( '%d error.', '%d errors.', $numError, 'pm4wp' ), $numError );
		}

		echo '<div id="message" class="updated fade"><p>', implode( '</p><p>', $status ), '</p></div>';
	}
	?>
<form method="post" action="" id="send-form">
	<table class="form-table">
		<tr>
			<th><?php _e( 'Recipient', 'pm4wp' ); ?></th>
			<td>
	<?php
 				// if message is not sent (by errors) or in case of replying, all input are saved

		$recipient = !empty( $_POST['recipient'] ) ? $_POST['recipient'] : ( !empty( $_GET['recipient'] )
			? $_GET['recipient'] : '' );

		// strip slashes if needed
		$subject = isset( $_REQUEST['subject'] ) ? ( get_magic_quotes_gpc( ) ? stripcslashes( $_REQUEST['subject'] )
			: $_REQUEST['subject'] ) : '';
		$subject = urldecode( $subject );  // for some chars like '?' when reply
		$content = isset( $_REQUEST['content'] ) ? ( get_magic_quotes_gpc( ) ? stripcslashes( $_REQUEST['content'] )
			: $_REQUEST['content'] ) : '';

		// Get all users of blog
		$users = $wpdb->get_results( "SELECT display_name FROM $wpdb->users ORDER BY display_name ASC" );

		if ( $option['type'] == 'autosuggest' ) { // if auto suggest feature is turned on
			?>
			<input id="recipient"/>
			<span id="all-users" style="display:none">
					<script type="text/javascript">
							<?php
	   					$all = array( );
							foreach ( $users as $user ) {
								$user_data = array( 'value' => $user->display_name );
								$all[] = $user_data;
							}
							echo 'var data = ' . json_encode( $all );
							?>
					</script>
					</span>
							<?php

		} else { // classic way: select recipient from dropdown list
			?>
			<select name="recipient[]" multiple="multiple" size="5">
				<?php
							foreach ( $users as $user ) {
				$selected = ( $user->display_name == $recipient ) ? ' selected="selected"' : '';
				echo "<option value='$user->display_name'$selected>$user->display_name</option>";
			}
				?>
			</select>
				<?php

		}
		?>
			</td>
		</tr>
		<tr>
			<th><?php _e( 'Subject', 'pm4wp' ); ?></th>
			<td><input type="text" name="subject" value="<?php echo $subject; ?>"/></td>
		</tr>
		<tr>
			<th><?php _e( 'Content', 'pm4wp' ); ?></th>
			<td><textarea cols="50" rows="10" name="content"><?php echo $content; ?></textarea></td>
		</tr>
	</table>
	<p class="submit" id="submit">
		<input type="hidden" name="page" value="rwpm_send"/>
		<input type="submit" name="submit" class="button-primary" value="<?php _e( 'Send', 'pm4wp' ) ?>"/>
	</p>

</form>
</div>
	<?php

}

/**
 * Inbox page
 */
function rwpm_inbox( ) {
	global $wpdb, $current_user;

	// if view message
	if ( isset( $_GET['action'] ) && 'view' == $_GET['action'] && !empty( $_GET['id'] ) ) {
		$id = $_GET['id'];

		check_admin_referer( "rwpm-view_inbox_msg_$id" );

		// mark message as read
		$wpdb->update( $wpdb->prefix . 'pm', array( 'read' => 1 ), array( 'id' => $id ) );

		// select message information
		$msg = $wpdb->get_row( 'SELECT * FROM ' . $wpdb->prefix . 'pm WHERE `id` = "' . $id . '" LIMIT 1' );
		$msg->sender = $wpdb->get_var( "SELECT display_name FROM $wpdb->users WHERE user_login = '$msg->sender'" );
		?>
	<div class="wrap">
		<h2><?php _e( 'Inbox \ View Message', 'pm4wp' ); ?></h2>

		<p><a href="?page=rwpm_inbox"><?php _e( 'Back to inbox', 'pm4wp' ); ?></a></p>
		<table class="widefat fixed" cellspacing="0">
			<thead>
			<tr>
				<th class="manage-column" width="20%"><?php _e( 'Info', 'pm4wp' ); ?></th>
				<th class="manage-column"><?php _e( 'Message', 'pm4wp' ); ?></th>
				<th class="manage-column" width="15%"><?php _e( 'Action', 'pm4wp' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<tr>
				<td><?php printf( __( '<b>Sender</b>: %s<br /><b>Date</b>: %s', 'pm4wp' ), $msg->sender, $msg->date ); ?></td>
				<td><?php printf( __( '<p><b>Subject</b>: %s</p><p>%s</p>', 'pm4wp' ), stripcslashes( $msg->subject ), nl2br( stripcslashes( $msg->content ) ) ); ?></td>
				<td>
						<span class="delete">
							<a class="delete" href="<?php echo wp_nonce_url( "?page=rwpm_inbox&action=delete&id=$msg->id", 'rwpm-delete_inbox_msg_' . $msg->id ); ?>"><?php _e( 'Delete', 'pm4wp' ); ?></a>
						</span>
						<span class="reply">
							| <a class="reply" href="<?php echo wp_nonce_url( "?page=rwpm_send&recipient=$msg->sender&subject=Re: " . stripcslashes( $msg->subject ), 'rwpm-reply_inbox_msg_' . $msg->id ); ?>"><?php _e( 'Reply', 'pm4wp' ); ?></a>
						</span>
				</td>
			</tr>
			</tbody>
			<tfoot>
			<tr>
				<th class="manage-column" width="20%"><?php _e( 'Info', 'pm4wp' ); ?></th>
				<th class="manage-column"><?php _e( 'Message', 'pm4wp' ); ?></th>
				<th class="manage-column" width="15%"><?php _e( 'Action', 'pm4wp' ); ?></th>
			</tr>
			</tfoot>
		</table>
	</div>
	<?php
		// don't need to do more!
		return;
	}

	// if mark messages as read
	if ( isset( $_GET['action'] ) && 'mar' == $_GET['action'] && !empty( $_GET['id'] ) ) {
		$id = $_GET['id'];

		if ( !is_array( $id ) ) {
			check_admin_referer( "rwpm-mar_inbox_msg_$id" );
			$id = array( $id );
		} else {
			check_admin_referer( "rwpm-bulk-action_inbox" );
		}
		$n = count( $id );
		$id = implode( ',', $id );
		if ( $wpdb->query( 'UPDATE ' . $wpdb->prefix . 'pm SET `read` = "1" WHERE `id` IN (' . $id . ')' ) ) {
			$status = _n( 'Message marked as read.', 'Messages marked as read', $n, 'pm4wp' );
		} else {
			$status = __( 'Error. Please try again.', 'pm4wp' );
		}
	}

	// if delete message
	if ( isset( $_GET['action'] ) && 'delete' == $_GET['action'] && !empty( $_GET['id'] ) ) {
		$id = $_GET['id'];

		if ( !is_array( $id ) ) {
			check_admin_referer( "rwpm-delete_inbox_msg_$id" );
			$id = array( $id );
		} else {
			check_admin_referer( "rwpm-bulk-action_inbox" );
		}

		$error = false;
		foreach ( $id as $msg_id ) {
			// check if the sender has deleted this message
			$sender_deleted = $wpdb->get_var( 'SELECT `deleted` FROM ' . $wpdb->prefix . 'pm WHERE `id` = "' . $msg_id . '" LIMIT 1' );

			// create corresponding query for deleting message
			if ( $sender_deleted == 1 ) {
				$query = 'DELETE from ' . $wpdb->prefix . 'pm WHERE `id` = "' . $msg_id . '"';
			} else {
				$query = 'UPDATE ' . $wpdb->prefix . 'pm SET `deleted` = "2" WHERE `id` = "' . $msg_id . '"';
			}

			if ( !$wpdb->query( $query ) ) {
				$error = true;
			}
		}
		if ( $error ) {
			$status = __( 'Error. Please try again.', 'pm4wp' );
		} else {
			$status = _n( 'Message deleted.', 'Messages deleted.', count( $id ), 'pm4wp' );
		}
	}

	// show all messages which have not been deleted by this user (deleted status != 2)
	$msgs = $wpdb->get_results( 'SELECT `id`, `sender`, `subject`, `read`, `date` FROM ' . $wpdb->prefix . 'pm WHERE `recipient` = "' . $current_user->user_login . '" AND `deleted` != "2" ORDER BY `date` DESC' );
	?>
<div class="wrap">
	<h2><?php _e( 'Inbox', 'pm4wp' ); ?></h2>
	<?php
	if ( !empty( $status ) ) {
	echo '<div id="message" class="updated fade"><p>', $status, '</p></div>';
}
	if ( empty( $msgs ) ) {
		echo '<p>', __( 'You have no items in inbox.', 'pm4wp' ), '</p>';
	} else {
		$n = count( $msgs );
		$num_unread = 0;
		foreach ( $msgs as $msg ) {
			if ( !( $msg->read ) ) {
				$num_unread++;
			}
		}
		echo '<p>', sprintf( _n( 'You have %d private message (%d unread).', 'You have %d private messages (%d unread).', $n, 'pm4wp' ), $n, $num_unread ), '</p>';
		?>
		<form action="" method="get">
			<?php wp_nonce_field( 'rwpm-bulk-action_inbox' ); ?>
			<input type="hidden" name="page" value="rwpm_inbox"/>

			<div class="tablenav">
				<select name="action">
					<option value="-1" selected="selected"><?php _e( 'Bulk Action', 'pm4wp' ); ?></option>
					<option value="delete"><?php _e( 'Delete', 'pm4wp' ); ?></option>
					<option value="mar"><?php _e( 'Mark As Read', 'pm4wp' ); ?></option>
				</select> <input type="submit" class="button-secondary" value="<?php _e( 'Apply', 'pm4wp' ); ?>"/>
			</div>

			<table class="widefat fixed" cellspacing="0">
				<thead>
				<tr>
					<th class="manage-column check-column"><input type="checkbox"/></th>
					<th class="manage-column" width="10%"><?php _e( 'Sender', 'pm4wp' ); ?></th>
					<th class="manage-column"><?php _e( 'Subject', 'pm4wp' ); ?></th>
					<th class="manage-column" width="20%"><?php _e( 'Date', 'pm4wp' ); ?></th>
				</tr>
				</thead>
				<tbody>
					<?php
	 			foreach ( $msgs as $msg ) {
					$msg->sender = $wpdb->get_var( "SELECT display_name FROM $wpdb->users WHERE user_login = '$msg->sender'" );
					?>
				<tr>
					<th class="check-column"><input type="checkbox" name="id[]" value="<?php echo $msg->id; ?>"/></th>
					<td><?php echo $msg->sender; ?></td>
					<td>
						<?php
						if ( $msg->read ) {
						echo '<a href="', wp_nonce_url( "?page=rwpm_inbox&action=view&id=$msg->id", 'rwpm-view_inbox_msg_' . $msg->id ), '">', stripcslashes( $msg->subject ), '</a>';
					} else {
						echo '<a href="', wp_nonce_url( "?page=rwpm_inbox&action=view&id=$msg->id", 'rwpm-view_inbox_msg_' . $msg->id ), '"><b>', stripcslashes( $msg->subject ), '</b></a>';
					}
						?>
						<div class="row-actions">
							<span>
								<a href="<?php echo wp_nonce_url( "?page=rwpm_inbox&action=view&id=$msg->id", 'rwpm-view_inbox_msg_' . $msg->id ); ?>"><?php _e( 'View', 'pm4wp' ); ?></a>
							</span>
						<?php
	  							if ( !( $msg->read ) ) {
							?>
							<span>
								| <a href="<?php echo wp_nonce_url( "?page=rwpm_inbox&action=mar&id=$msg->id", 'rwpm-mar_inbox_msg_' . $msg->id ); ?>"><?php _e( 'Mark As Read', 'pm4wp' ); ?></a>
							</span>
							<?php

						}
							?>
							<span class="delete">
								| <a class="delete" href="<?php echo wp_nonce_url( "?page=rwpm_inbox&action=delete&id=$msg->id", 'rwpm-delete_inbox_msg_' . $msg->id ); ?>"><?php _e( 'Delete', 'pm4wp' ); ?></a>
							</span>
							<span class="reply">
								| <a class="reply" href="<?php echo wp_nonce_url( "?page=rwpm_send&recipient=$msg->sender&subject=Re: " . stripcslashes( $msg->subject ), 'rwpm-reply_inbox_msg_' . $msg->id ); ?>"><?php _e( 'Reply', 'pm4wp' ); ?></a>
							</span>
						</div>
					</td>
					<td><?php echo $msg->date; ?></td>
				</tr>
						<?php

				}
					?>
				</tbody>
				<tfoot>
				<tr>
					<th class="manage-column check-column"><input type="checkbox"/></th>
					<th class="manage-column"><?php _e( 'Sender', 'pm4wp' ); ?></th>
					<th class="manage-column"><?php _e( 'Subject', 'pm4wp' ); ?></th>
					<th class="manage-column"><?php _e( 'Date', 'pm4wp' ); ?></th>
				</tr>
				</tfoot>
			</table>
		</form>
					<?php

	}
	?>
</div>
	<?php

}

/**
 * Outbox page
 */
function rwpm_outbox( ) {
	global $wpdb, $current_user;

	// if view message
	if ( isset( $_GET['action'] ) && 'view' == $_GET['action'] && !empty( $_GET['id'] ) ) {
		$id = $_GET['id'];

		check_admin_referer( "rwpm-view_outbox_msg_$id" );

		// select message information
		$msg = $wpdb->get_row( 'SELECT * FROM ' . $wpdb->prefix . 'pm WHERE `id` = "' . $id . '" LIMIT 1' );
		$msg->recipient = $wpdb->get_var( "SELECT display_name FROM $wpdb->users WHERE user_login = '$msg->recipient'" );
		?>
	<div class="wrap">
		<h2><?php _e( 'Outbox \ View Message', 'pm4wp' ); ?></h2>

		<p><a href="?page=rwpm_outbox"><?php _e( 'Back to outbox', 'pm4wp' ); ?></a></p>
		<table class="widefat fixed" cellspacing="0">
			<thead>
			<tr>
				<th class="manage-column" width="20%"><?php _e( 'Info', 'pm4wp' ); ?></th>
				<th class="manage-column"><?php _e( 'Message', 'pm4wp' ); ?></th>
				<th class="manage-column" width="15%"><?php _e( 'Action', 'pm4wp' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<tr>
				<td><?php printf( __( '<b>Recipient</b>: %s<br /><b>Date</b>: %s', 'pm4wp' ), $msg->recipient, $msg->date ); ?></td>
				<td><?php printf( __( '<p><b>Subject</b>: %s</p><p>%s</p>', 'pm4wp' ), stripcslashes( $msg->subject ), nl2br( stripcslashes( $msg->content ) ) ); ?></td>
				<td>
						<span class="delete">
							<a class="delete" href="<?php echo wp_nonce_url( "?page=rwpm_outbox&action=delete&id=$msg->id", 'rwpm-delete_outbox_msg_' . $msg->id ); ?>"><?php _e( 'Delete', 'pm4wp' ); ?></a>
						</span>
				</td>
			</tr>
			</tbody>
			<tfoot>
			<tr>
				<th class="manage-column" width="20%"><?php _e( 'Info', 'pm4wp' ); ?></th>
				<th class="manage-column"><?php _e( 'Message', 'pm4wp' ); ?></th>
				<th class="manage-column" width="15%"><?php _e( 'Action', 'pm4wp' ); ?></th>
			</tr>
			</tfoot>
		</table>
	</div>
	<?php
  // don't need to do more!
		return;
	}

	// if delete message
	if ( isset( $_GET['action'] ) && 'delete' == $_GET['action'] && !empty( $_GET['id'] ) ) {
		$id = $_GET['id'];

		if ( !is_array( $id ) ) {
			check_admin_referer( "rwpm-delete_outbox_msg_$id" );
			$id = array( $id );
		} else {
			check_admin_referer( "rwpm-bulk-action_outbox" );
		}
		$error = false;
		foreach ( $id as $msg_id ) {
			// check if the recipient has deleted this message
			$recipient_deleted = $wpdb->get_var( 'SELECT `deleted` FROM ' . $wpdb->prefix . 'pm WHERE `id` = "' . $msg_id . '" LIMIT 1' );
			// create corresponding query for deleting message
			if ( $recipient_deleted == 2 ) {
				$query = 'DELETE from ' . $wpdb->prefix . 'pm WHERE `id` = "' . $msg_id . '"';
			} else {
				$query = 'UPDATE ' . $wpdb->prefix . 'pm SET `deleted` = "1" WHERE `id` = "' . $msg_id . '"';
			}

			if ( !$wpdb->query( $query ) ) {
				$error = true;
			}
		}
		if ( $error ) {
			$status = __( 'Error. Please try again.', 'pm4wp' );
		} else {
			$status = _n( 'Message deleted.', 'Messages deleted.', count( $id ), 'pm4wp' );
		}
	}

	// show all messages
	$msgs = $wpdb->get_results( 'SELECT `id`, `recipient`, `subject`, `date` FROM ' . $wpdb->prefix . 'pm WHERE `sender` = "' . $current_user->user_login . '" AND `deleted` != 1 ORDER BY `date` DESC' );
	?>
<div class="wrap">
	<h2><?php _e( 'Outbox', 'pm4wp' ); ?></h2>
	<?php
	if ( !empty( $status ) ) {
	echo '<div id="message" class="updated fade"><p>', $status, '</p></div>';
}
	if ( empty( $msgs ) ) {
		echo '<p>', __( 'You have no items in outbox.', 'pm4wp' ), '</p>';
	} else {
		$n = count( $msgs );
		echo '<p>', sprintf( _n( 'You wrote %d private message.', 'You wrote %d private messages.', $n, 'pm4wp' ), $n ), '</p>';
		?>
		<form action="" method="get">
			<?php wp_nonce_field( 'rwpm-bulk-action_outbox' ); ?>
			<input type="hidden" name="action" value="delete"/> <input type="hidden" name="page" value="rwpm_outbox"/>

			<div class="tablenav">
				<input type="submit" class="button-secondary" value="<?php _e( 'Delete Selected', 'pm4wp' ); ?>"/>
			</div>

			<table class="widefat fixed" cellspacing="0">
				<thead>
				<tr>
					<th class="manage-column check-column"><input type="checkbox"/></th>
					<th class="manage-column" width="10%"><?php _e( 'Recipient', 'pm4wp' ); ?></th>
					<th class="manage-column"><?php _e( 'Subject', 'pm4wp' ); ?></th>
					<th class="manage-column" width="20%"><?php _e( 'Date', 'pm4wp' ); ?></th>
				</tr>
				</thead>
				<tbody>
					<?php
	 			foreach ( $msgs as $msg ) {
					$msg->recipient = $wpdb->get_var( "SELECT display_name FROM $wpdb->users WHERE user_login = '$msg->recipient'" );
					?>
				<tr>
					<th class="check-column"><input type="checkbox" name="id[]" value="<?php echo $msg->id; ?>"/></th>
					<td><?php echo $msg->recipient; ?></td>
					<td>
						<?php
						echo '<a href="', wp_nonce_url( "?page=rwpm_outbox&action=view&id=$msg->id", 'rwpm-view_outbox_msg_' . $msg->id ), '">', stripcslashes( $msg->subject ), '</a>';
						?>
						<div class="row-actions">
							<span>
								<a href="<?php echo wp_nonce_url( "?page=rwpm_outbox&action=view&id=$msg->id", 'rwpm-view_outbox_msg_' . $msg->id ); ?>"><?php _e( 'View', 'pm4wp' ); ?></a>
							</span>
							<span class="delete">
								| <a class="delete" href="<?php echo wp_nonce_url( "?page=rwpm_outbox&action=delete&id=$msg->id", 'rwpm-delete_outbox_msg_' . $msg->id ); ?>"><?php _e( 'Delete', 'pm4wp' ); ?></a>
							</span>
						</div>
					</td>
					<td><?php echo $msg->date; ?></td>
				</tr>
						<?php

				}
					?>
				</tbody>
				<tfoot>
				<tr>
					<th class="manage-column check-column"><input type="checkbox"/></th>
					<th class="manage-column"><?php _e( 'Recipient', 'pm4wp' ); ?></th>
					<th class="manage-column"><?php _e( 'Subject', 'pm4wp' ); ?></th>
					<th class="manage-column"><?php _e( 'Date', 'pm4wp' ); ?></th>
				</tr>
				</tfoot>
			</table>
		</form>
					<?php

	}
	?>
</div>
	<?php

}

/**
 * Show notification of new PM
 */
add_action( 'admin_notices', 'rwpm_notify' );

function rwpm_notify( ) {
	global $wpdb, $current_user;

	// get number of unread messages
	$num_unread = $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'pm WHERE `recipient` = "' . $current_user->user_login . '" AND `read` = 0 AND `deleted` != "2"' );

	if ( empty( $num_unread ) ) {
		$num_unread = 0;
	}

	if ( $num_unread ) {
		echo '<div id="message" class="error"><p><b>', sprintf( _n( 'You have %d new message!', 'You have %d new messages!', $num_unread, 'pm4wp' ), $num_unread ), '</b> <a href="admin.php?page=rwpm_inbox">', __( 'Click here to go to inbox', 'pm4wp' ), ' &raquo;</a></p></div>';
	}
}