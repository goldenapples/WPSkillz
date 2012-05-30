<?php

add_filter( 'debug_bar_panels', 'wpskillz_debug_bar_panel' );

function wpskillz_debug_bar_panel( $panels ) {

		class WPSkillz_Test_Panel extends Debug_Bar_Panel {
			function init() {
				$this->title( __('WPSkillz', 'debug-bar') );
			}

			function prerender() {
				$this->set_visible( true );
			}

			function render() {
				global $wp;

				echo "<div id='debug-bar-wpskillz'>";

				global $current_user, $wpskillz_session;

				echo <<<EOF
					<h3>Current User:</h3>
					<ul>
						<li><strong>ID</strong>: {$current_user->ID}</li>
						<li><strong>Name</strong>: {$current_user->display_name}</li>
					</ul>

					<h3>Test Progress:</h3>
					<ul>
						<li>Completed {$wpskillz_session->complete} of {$wpskillz_session->oftotal} questions</li>
						<li>Correct answers: {$wpskillz_session->correct} of {$wpskillz_session->complete}</li>
					</ul>
EOF;

				echo '<h3>$progress</h3>';

				var_dump( $wpskillz_session->progress );

				echo '<br><br>';

				echo '<h3>Session variable contents</h3>';

				var_dump( $_SESSION['wpskillz_test'] );

				echo '</div>';
			}
		}

	$panels[] = new WPSkillz_Test_Panel;
	return $panels;
}
