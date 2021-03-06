<?php
/*
	Core plugin class
*/

class WPUserSyncProcess
{
	// Class cunstructor
	function __construct () {
		// Load public scripts and styles
		add_action( 'wp_enqueue_scripts', array( $this,'publicStylesLoad' ) );

		// Load admin scrupts and styles
		add_action( 'admin_init', array( $this, 'dashStylesLoad' ) );

		// Dashboard menu setup
		add_action( 'admin_menu', array( $this, 'dashMenu' ) );

		// User registered hook
		add_action( 'user_register', array( $this, 'userRegistration' ), 10, 1 );

		// User profile update hook
		add_action( 'profile_update', array( $this, 'userProfileUpdate' ), 10, 2 );

		// User delete hook
		add_action( 'delete_user', array( $this, 'userDelete' ), 10, 1 );

		load_plugin_textdomain( 'wp-user-sync' , false, 'wp-user-sync/languages' );
	}

	public function publicStylesLoad () {
		// Public styles - none for now
	}

	public function dashStylesLoad () {
		// Dashboard styles
		wp_enqueue_style( 'wpusersync-css', WPUSync_URL.'css/admin.css' );
	}

	public function dashMenu () {
		add_menu_page(
				__( 'WP Users Sync', 'wp-user-sync' ),
				__( 'WP Users Sync', 'wp-user-sync'),
				'manage_options',
				'wp-user-sync',
				array( $this, 'dashMainPage' ),
				'dashicons-analytics'
			);
		add_submenu_page(
				'wp-user-sync',
				__( 'MailChimp Settings', 'wp-user-sync' ),
				__( 'MailChimp Settings', 'wp-user-sync' ),
				'manage_options',
				'wp-user-sync-mailchimp-settings',
				array( $this, 'dashSettingsPage' )
			);
	}

	public function dashMainPage() {
		$options = get_option('wp-user-sync');
		?>
		<h1><?php echo __( 'Sync Status', 'wp-user-sync' ); ?></h1>
		<?php
		$users = get_users();
		require_once( WPUSync_DIR . '/vendor/mailchimp/MailChimp.php' );
		try
		{
			$wrapper = new mc3_MailChimp( $options['api-key'] );
			$batch = $wrapper->new_batch();
			$lists = $wrapper->get( 'lists' );
			foreach ( $lists['lists'] as $item ) {
				$lists_str[ $item['id'] ] = $item['name'];
			}

		} catch ( Exception $e ) {
			$wrapper = false;
			$error = $e;
			?>
			<h3><?php echo __( 'Error while trying connect to MailChimp', 'wp-user-sync' ); ?>:</h3>
			<div class="mc-error"><?php echo $error->getMessage(); ?></div>
			<?php
			return;
		}
		?>
		<table class="form-table" style="max-width: 80%">
			<tr valign="top" style="background-color: #999; color: white;">
				<td class=""><?php echo __('User'); ?></td>
				<td><?php echo __('Role'); ?></td>
				<td><?php echo __('Lists'); ?></td>
				<td><?php echo __('Status'); ?></td>
			</tr>
			<?php
			foreach ( $users as $i => $user ) {
			?>
				<tr valign="top">
					<td><strong><?php echo $user->user_login; ?></strong></td>
					<td><?php echo implode( '<br/>', $user->roles ); ?></td>
					<td>
						<?php
						$act_lists = array();
						$status = array();
						foreach ( $user->roles as $role ) {
							if ( !isset( $options['roles'][ $role ] ) || $options['roles'][ $role ] == 0 ) {
								$act_lists['n_a'] = __('Not assigned', 'wp-user-sync');
								$list_id = '';
								//echo ;
							} else {
								$list_id = isset( $options['roles'][ $role ] ) ? $options['roles'][ $role ] : 0;
								if ( !$list_id ) {
									//continue;
								}
								$act_lists[ $list_id ] = $lists_str[ $list_id ];
							}

							if ( $list_id ) {
								$subscriber_hash = $wrapper->subscriberHash( $user->user_email );
								$result = $wrapper->get( "lists/$list_id/members/$subscriber_hash" );
								switch ( $result['status'] ) {
									case 'subscribed':
										$status[] = __( 'Subscribed', 'wp-user-sync' );
										break;
									case '404':
										// no break
									default:
										$status[] = __( 'Not Subscribed', 'wp-user-sync' );
										break;
								}
							} else {
								$status[] = __( 'Not Subscribed', 'wp-user-sync' );
							}
						}
						echo implode( '<br/>', $act_lists );
						//echo '( ' . $wrapper->subscriberHash( $user->user_email ) . ' )';
						
						?>
					</td>
					<td>
						<?php echo implode( '<br/>', $status ); ?>
					</td>
				</tr>
		   		<?php
				}
				?>
		</table>
		<?php

	}

	public function dashSettingsPage () {
		if ( isset( $_POST['wp-user-sync'] ) ) {
			$this->dashSettingsPageSave();
		}
		?>
		<h1><?php echo __( 'MailChimp Users Sync Settings', 'wp-user-sync' ); ?></h1>
		<form action="" method="POST">
		<table class="wp-user-sync-settings form-table">
		<?php
		global $wp_roles;
		$roles = $wp_roles->get_names();
		$options = get_option( 'wp-user-sync' );

		require_once( WPUSync_DIR . '/vendor/mailchimp/MailChimp.php' );
		?>
			<tr class="head underlined">
				<th><?php echo __( 'MailChimp API key', 'wp-user-sync' ); ?></th>
				<td><input name="wp-user-sync[api-key]" type="text" class="regular-text ltr" value="<?php echo $options['api-key']; ?>" /></td>
			</tr>
			<tr>
				<th><?php echo __( 'On List Assign', 'wp-user-sync' ); ?></th>
				<td>
					<label class="subscribe-options"><input name="wp-user-sync[subscribe]"
						<?php echo $this->isChecked( $options, 'subscribe', 'yes' ); ?>
						type="radio" value="yes"><?php echo __( 'Subscribe users', 'wp-user-sync' ); ?></label>
					&nbsp;&nbsp;&nbsp;

					<label class="subscribe-options"><input name="wp-user-sync[subscribe]"
						<?php echo $this->isChecked( $options, 'subscribe', 'no', true ); ?>
						type="radio" value="no"><?php echo __( 'Do nothing', 'wp-user-sync' ); ?></label>
					<small><?php echo __('What to do when a new list is assigned to a role', 'wp-user-sync') ?></small>
				</td>
			</tr>
			<tr>
				<th><?php echo __( 'On List Reassign', 'wp-user-sync' ); ?></th>
				<td>
					<label class="subscribe-options"><input name="wp-user-sync[resubscribe]"
						<?php echo $this->isChecked( $options, 'resubscribe', 'yes' ); ?>
						type="radio" value="yes"><?php echo __( 'Resubscribe users', 'wp-user-sync' ); ?></label>
					&nbsp;&nbsp;&nbsp;

					<label class="subscribe-options"><input name="wp-user-sync[resubscribe]"
						<?php echo $this->isChecked( $options, 'subscribe', 'no', true ); ?>
						type="radio" value="no"><?php echo __( 'Do nothing', 'wp-user-sync' ); ?></label>
					<small><?php echo __('What to do when a list is reassigned to a role', 'wp-user-sync') ?></small>
				</td>
			</tr>
			<tr>
				<th><?php echo __( 'On List Unassign', 'wp-user-sync' ); ?></th>
				<td>
					<label class="subscribe-options"><input name="wp-user-sync[unsubscribe]"
						<?php echo $this->isChecked( $options, 'unsubscribe', 'yes' ); ?>
						type="radio" value="yes"><?php echo __( 'Unsubscribe', 'wp-user-sync' ); ?></label>
					&nbsp;&nbsp;&nbsp;

					<label class="subscribe-options"><input name="wp-user-sync[unsubscribe]"
						<?php echo $this->isChecked( $options, 'unsubscribe', 'no', true ); ?>
						type="radio" value="no"><?php echo __( 'Do nothing', 'wp-user-sync' ); ?></label>
					<small><?php echo __('What to do when a list is unassigned from a role', 'wp-user-sync') ?></small>
				</td>
			</tr>
			<tr class="underlined">
				<th><?php echo __( 'On User Delete', 'wp-user-sync' ); ?></th>
				<td>
					<label class="subscribe-options"><input name="wp-user-sync[del_unsubscribe]"
						<?php echo $this->isChecked( $options, 'del_unsubscribe', 'yes' ); ?>
						type="radio" value="yes"><?php echo __( 'Unsubscribe', 'wp-user-sync' ); ?></label>
					&nbsp;&nbsp;&nbsp;

					<label class="subscribe-options"><input name="wp-user-sync[del_unsubscribe]"
						<?php echo $this->isChecked( $options, 'del_unsubscribe', 'no', true ); ?>
						type="radio" value="no"><?php echo __( 'Do nothing', 'wp-user-sync' ); ?></label>
					<small><?php echo __('What to do when a user is deleted', 'wp-user-sync') ?></small>
				</td>
			</tr>

		<?php
		if ( isset( $options['api-key'] ) && !empty( $options['api-key'] ) ) {

			try {
				$wrapper = new mc3_MailChimp( $options['api-key'] );
				$lists = $wrapper->get( 'lists' );
				$lists_str = array();
				foreach ( $lists['lists'] as $item ) {
					$lists_str[ $item['id'] ] = $item['name'];
				}
				$error = false;
			} catch( Exception $e ) {
				$wrapper = false;
				$error = $e;
			}

			if ( $wrapper ) {
		?>
			<tr class="head">
				<th colspan="2">
					<h2><?php echo __( 'Assign roles to lists', 'wp-user-sync' ); ?></h2>
				</th>
			</tr>
			<?php
					foreach ( $roles as $code => $role ) {
			?>
			<tr>
				<th>
					<?php echo $role; ?>
				</th>
				<td>
					<select name="wp-user-sync[roles][<?php echo $code; ?>]">
						<option value="0"> <?php echo __( 'Not assigned', 'wp-user-sync' ); ?></option>
						<?php echo $this->getFormattedList( $lists_str, isset( $options['roles'][ $code ] ) ? $options['roles'][ $code ] : '' ); ?>
					</select>
				</td>
			</tr>
			<?php
				}
			} else {
			?>
			<tr>
				<td colspan="2"><h3><?php echo __( 'Error occured while trying to make MailChimp connection', 'wp-user-sync' ); ?>:</h3>
					<?php echo $error->getMessage(); ?>
				</td>
			</tr>
			<?php
			}
			?>
		<?php
		}
		?>
		</table>
		<input type="submit" class="button button-primary" value="<?php echo __( 'Save Changes' ); ?>" />
		</form>
		<?php
	}

	private function dashSettingsPageSave () {
		global $wp_roles;
		$roles = $wp_roles->get_names();
		$options = get_option( 'wp-user-sync' );
		if ( $options['subscribe'] == 'yes' || $options['resubscribe'] == 'yes' || $options['unsubscribe'] ) {
			require_once( WPUSync_DIR . '/vendor/mailchimp/MailChimp.php' );

			if ( $options['api-key'] == '' ) {
				if ( $_POST['wp-user-sync']['api-key'] ) {
					foreach ( $options['roles'] as $code => $role ) {
						$options['roles'][ $code ] = 0;
					}
				}
				$wrapper = false;
			} else {
				try {
					$wrapper = new mc3_MailChimp( $options['api-key'] );
				} catch ( Exception $e ) {
					$wrapper = false;
				}
			}

			if ( $wrapper ) {
				foreach ( $roles as $code => $role ) {
					$newlist_id = $_POST['wp-user-sync']['roles'][ $code ];

					if ( !isset( $options['roles'][ $code ] ) || $options['roles'][ $code ] == 0) {
						if ( $newlist_id != 0 ) {
							// First assign
							$case = 'subscribe';
						} else {
							// No change
							continue;
						}
					} elseif ( $options['roles'][ $code ] != $newlist_id ) {
						if ( $newlist_id != 0 ) {
							// Reassign
							$case = 'resubscribe';
						} else {
							// Assigned list changed to 0
							$case = 'unsubscribe';
						}
					} else {
						// Previously assigned and not changed
						continue;
					}

					// Check if our case should be handled
					if ( $options[$case] != 'yes' ) {
						continue;
					}

					$batch = $wrapper->new_batch();

					$users = get_users( array( 'role'=> $code ) );
				
					foreach ( $users as $id => $user ) {
						switch ( $case ) {
							case 'unsubscribe':
								// no break
							case 'resubscribe':
								$list_id = $options['roles'][ $code ];
								$subscriber_hash = $wrapper->subscriberHash( $user->user_email );
								$batch->delete( 'op_u' . $id, "lists/$list_id/members/$subscriber_hash" );
								if ( $case == 'unsubscribe' ) {
									break;
								}
								// no break
							case 'subscribe':
								$list_id = $_POST['wp-user-sync']['roles'][ $code ];
								$batch->post( 'op' . $id, "lists/$list_id/members",
								array(
									'email_address' => $user->user_email,
									'status'		=> 'subscribed',
									'merge_fields' => array( 'FNAME'=>$user->first_name, 'LNAME'=>$user->last_name )
								) );
								break;
						}
					}
					$batch->execute();
					//$this->getFormattedList( $lists_str, isset( $options['roles'][ $code ] ) ? $options['roles'][ $code ] : '' )

				}
			}
		}
		update_option( 'wp-user-sync', $_POST['wp-user-sync'] );
		?>
		<?php
	}

	private function getFormattedList ( $list, $key = '' ) {
		ob_start();
		foreach ( $list as $id => $item ) {
			?>
			<option value="<?php echo $id; ?>" <?php echo ( $key == $id ? 'selected' : '' ); ?> ><?php echo $item; ?></option>
			<?php
		}
		return ob_get_clean();
	}

	private function isChecked ( $options, $item, $key, $def = false ) {
		if ( isset ( $options[ $item ] ) && $options[ $item ] == $key ) {
			return 'checked';
		} elseif ( $def && ( !isset( $options[ $item ]) || $options[ $item ] == '' ) ) {
			return 'checked';
		} else {
			return '';
		}
	}

	public function userRegistration ( $user_id ) {
		$user = get_userdata( $user_id );
		$options = get_option( 'wp-user-sync' );

		try
		{
			require_once( WPUSync_DIR . '/vendor/mailchimp/MailChimp.php' );
			$wrapper = new mc3_MailChimp( $options['api-key'] );
			$error = false;
		} catch( Exception $e ) {
			$wrapper = false;
			return;
		}

		foreach ( $user->roles as $role ) {
			if ( !isset( $options['roles'][ $role ] ) || $options['roles'][ $role ] == 0 ) {
				continue;
			}
			$list_id = $options['roles'][ $role ];
			$result = $wrapper->post( "lists/$list_id/members", array(
				'email_address' => $user->user_email,
				'status'		=> 'subscribed',
				'merge_fields' => array( 'FNAME' => $user->first_name, 'LNAME' => $user->last_name )
			) );
			if ( isset( $result['id'] ) ) {
				update_user_meta( $user_id, "list_{$list_id}_id", $result['id'] );
			}
		}
	}

	public function userProfileUpdate ( $user_id, $old_data ) {
		$user = get_userdata( $user_id );
		$options = get_option( 'wp-user-sync' );
		try
		{
			require_once( WPUSync_DIR . '/vendor/mailchimp/MailChimp.php' );
			$wrapper = new mc3_MailChimp( $options['api-key'] );
			$error = false;
		} catch( Exception $e ) {
			$wrapper = false;
			return;
		}

		foreach ( $user->roles as $role ) {
			if ( !isset( $options['roles'][ $role ] ) || $options['roles'][ $role ] == 0 ) {
				continue;
			}
			$list_id = $options['roles'][ $role ];
			//$subscriber_id = get_user_meta($user_id, "list_{$list_id}_id", $result['id']);
			$subscriber_hash = $wrapper->subscriberHash( $old_data->data->user_email );

			$result = $wrapper->patch( "lists/$list_id/members/$subscriber_hash", array(
				'email_address' => $user->user_email,
				'merge_fields' => array( 'FNAME' => $user->first_name, 'LNAME' => $user->last_name )
			) );
		}
	}

	public function userDelete ( $user_id ) {
		$user = get_userdata( $user_id );
		$options = get_option( 'wp-user-sync' );
		if ( $options['del_unsubscribe'] == 'yes' ) {
			try
			{
				require_once( WPUSync_DIR . '/vendor/mailchimp/MailChimp.php' );
				$wrapper = new mc3_MailChimp( $options['api-key'] );
				$error = false;
			} catch( Exception $e ) {
				$wrapper = false;
				return;
			}

			foreach ( $user->roles as $role ) {
				if ( !isset( $options['roles'][ $role ] ) || $options['roles'][ $role ] == 0 ) {
					continue;
				}
				$list_id = $options['roles'][ $role ];
				$subscriber_hash = $wrapper->subscriberHash( $user->user_email );
				$wrapper->delete( "lists/$list_id/members/$subscriber_hash" );
			}
		}
	}
}
