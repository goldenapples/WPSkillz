<?php

add_action('admin_menu','wpskillz_admin_pages');

function wpskillz_admin_pages() {
	add_submenu_page(
		'edit.php?post_type=quiz',
		__( 'WP-Skillz Test Settings',
		'wpskillz_settings' ),
		'Test Settings',
		'activate_plugins',
		'wpskillz_plugin_settings',
		'wpskillz_plugin_settings'
	);
}

function wpskillz_plugin_settings() {

	if ( !empty( $_POST ) && check_admin_referer( 'wpskillz-settings', '_wpnonce') )
		update_wpskillz_settings( $page );
	$current_settings = get_option( 'wpskillz_plugin_options' );

?>
<div class="wrap">
	<div id="icon-themes" class="icon32"></div>
	<h2><?php _e( 'WPSkillz Test Settings', 'wpskillz' ); ?></h2>

	<form method="post">
		<table class="form-table">
		<tr>
			<th scope="row">
			<label for="start-test-page"><?php _e( 'Select a page to start the test on:', 'wpskillz' ); ?></label>
			</th>
			<td><?php 
				wp_dropdown_pages(
					array(
						'name' => 'start-test-page',
						'show_option_none' => false,
						'exclude' => array( get_option('page_for_posts') ),
						'selected' => $current_settings['start-test-page']
					)
				); ?>
			</td>
		</tr>
		<tr>
			<th scope="row">
			<label for="leaderboard-page"><?php _e( 'Select a page to hold the leaderboard:', 'wpskillz' ); ?></label>
			</th>
			<td><?php
				wp_dropdown_pages(
					array(
						'name' => 'leaderboard-page',
						'show_option_none' => false,
						'exclude' => array( get_option('page_for_posts') ),
						'selected' => $current_settings['leaderboard-page']
					)
				); ?>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for=""></label>
			</th>
			<td>
				<input type="submit" class="button-primary" value="<?php _e( 'Save changes', 'wpskillz' ); ?>" />
			</td>
		</tr>

		</table>
		<?php wp_nonce_field( 'wpskillz-settings' ); ?>
	</form>
</div>

<?php
}

function update_wpskillz_settings( $page ) {

	$settings = get_option( 'wpskillz_plugin_options' );

	$settings['start-test-page'] = intval( $_POST['start-test-page'] );
	$settings['leaderboard-page'] = intval( $_POST['leaderboard-page'] );

	update_option( 'wpskillz_plugin_options', $settings );

	echo '<div id="message" class="messages updated"><p>Plugin settings updated!</p></div>';
}
