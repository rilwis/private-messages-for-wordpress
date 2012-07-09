<?php
/*
Plugin Name: Private Messages For WordPress
Plugin URI: http://www.deluxeblogtips.com/private-messages-for-wordpress
Description: Allow members of WordPress blog send and receive private messages (PM)
Version: 2.1.6
Author: Rilwis
Author URI: http://www.deluxeblogtips.com
License: GNU GPL 2+
*/

// Prevent loading this file directly
if ( !class_exists( 'WP' ) )
{
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

define( 'PM4WP_DIR', plugin_dir_path( __FILE__ ) );
define( 'PM4WP_INC_DIR', trailingslashit( PM4WP_DIR . 'inc/' ) );

define( 'PM4WP_URL', plugin_dir_url( __FILE__ ) );
define( 'PM4WP_CSS_URL', trailingslashit( PM4WP_URL . 'css' ) );
define( 'PM4WP_JS_URL', trailingslashit( PM4WP_URL . 'js' ) );

include_once PM4WP_INC_DIR . 'widget.php';

if ( is_admin() )
{
	include_once PM4WP_INC_DIR . 'options.php';
}

add_action( 'plugins_loaded', 'rwpm_load_text_domain' );
register_activation_hook( __FILE__, 'rwpm_activate' );

/**
 * Load plugin text domain
 *
 * @return void
 */
function rwpm_load_text_domain()
{
	load_plugin_textdomain( 'pm4wp', trailingslashit( PM4WP_DIR . 'lang' ) );
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
		'editor' => 50,
		'author' => 20,
		'contributor' => 10,
		'subscriber' => 5,
		'type' => 'dropdown', // How to choose recipient: dropdown list or autocomplete based on user input
		'email_enable' => 1,
		'email_name' => '%BLOG_NAME%',
		'email_address' => '%BLOG_ADDRESS%',
		'email_subject' => __( 'New PM at %BLOG_NAME%', 'pm4wp' ),
		'email_body' => __( "You have new private message from <b>%SENDER%</b> at <b>%BLOG_NAME%</b>.\n\n<a href=\"%INBOX_URL%\">Click here</a> to go to your inbox.\n\nThis email is sent automatically. Please don't reply.", 'pm4wp' )
	);
	add_option( 'rwpm_option', $default_option, '', 'no' );
}

/**
 * Send form page
 */
function rwpm_send() {
	global $wpdb, $current_user;
	?>
<div class="wrap">
<h2><?php _e( 'Send Private Message', 'pm4wp' ); ?></h2>
	<?php
	$option = get_option( 'rwpm_option' );
	if ( $_REQUEST['page'] == 'rwpm_send' && isset( $_POST['submit'] ) ) {
		$error = false;
		$status = array();

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
		if ( get_magic_quotes_gpc() ) {
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
						if ( get_magic_quotes_gpc() ) {
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


		$recipient = "";
		$subject = "";
		$content = "";
		
		if ($_GET["msgID"])
		{
		  // preload fields with selected message
			$current_user = wp_get_current_user();
			$sql = $wpdb->prepare("SELECT * FROM ".$wpdb->prefix."pm WHERE id = %d AND recipient = '%s'",$_GET["msgID"],$current_user->user_login);
			$message = $wpdb->get_row( $sql );
			if ($message)
			{
				$recipient = $wpdb->get_var( "SELECT display_name FROM $wpdb->users WHERE user_login = '$message->sender'" );
				$subject = $message->subject;
				$content = $message->content;
				
				$subject = preg_replace("/^(Re[^a-zA-Z]*:\s*)/i","",$subject);
				$subject = preg_replace("/^(Fwd[^a-zA-Z]*:\s*)/i","",$subject);
				$subject = "Re: ".$subject;
				
				$content = "> ".str_replace("\n","\n> ",$content)."\n\n";
			}
		}
		
		$post = $_POST;
		if ( get_magic_quotes_gpc( ) )
			$post = array_map( 'stripslashes_deep', $post );
		
		if ($post['recipient'])
			$recipient = $post['recipient'];
		if ($post['subject'])
			$subject = $post['subject'];
		if ($post['content'])
			$content = $post['content'];
		
		
		
		// Get all users of blog
		$users = $wpdb->get_results( "SELECT display_name FROM $wpdb->users ORDER BY display_name ASC" );

		if ( $option['type'] == 'autosuggest' ) { // if auto suggest feature is turned on
			?>
			<input id="recipient"/>
			<span id="all-users" style="display:none">
					<script type="text/javascript">
							<?php
	   					$all = array();
							foreach ( $users as $user ) {
								$user_data = array( 'value' => $user->display_name );
								$all[] = $user_data;
							}
							echo 'var data = ' . json_encode( $all );
							?>
							jQuery(document).ready(function($) {
								$('#recipient').autoSuggest(data<?=($recipient?(",{preFill:\"".esc_js($recipient)."\"}"):"")?>);
							});
							
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
function rwpm_inbox() {
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
function rwpm_outbox() {
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

function rwpm_notify() {
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

function rwpm_adminbar() {
  global $wp_admin_bar;
	global $wpdb, $current_user;

	// get number of unread messages
	$num_unread = $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'pm WHERE `recipient` = "' . $current_user->user_login . '" AND `read` = 0 AND `deleted` != "2"' );

	if ( empty( $num_unread ) ) {
		$num_unread = 0;
	}
  
  if ($num_unread && is_admin_bar_showing() )
  {
    $wp_admin_bar->add_menu( array(
      'id' => 'rwpm',
      'title' => sprintf( _n( 'You have %d new message!', 'You have %d new messages!', $num_unread, 'pm4wp' ), $num_unread ),
      'href' => admin_url( 'admin.php?page=rwpm_inbox' ),
      'meta' => array('class'=>"rwpm_newmessages"),
    ) ); 
  }    
}
add_action('admin_bar_menu', "rwpm_adminbar", 300);
