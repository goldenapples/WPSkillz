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

				global $current_user;

				echo '
					<h3>Current User:</h3>
					<ul>
						<li><strong>ID</strong>: ' . $current_user->ID . '</li>
						<li><strong>Name</strong>: ' . $current_user->display_name . '</li>
					</ul>

					<h3>Test Progress:</h3>
					<ul>
						<li>Completed ' . WPSkillz_Session::$complete . ' of ' . WPSkillz_Session::$oftotal . ' questions</li>
						<li>Correct answers: ' . WPSkillz_Session::$correct . ' of ' . WPSkillz_Session::$complete .'</li>
					</ul>
					';

				echo '<h3>$progress</h3>';

				var_dump( WPSkillz_Session::$progress );

				echo '<br><br>';

				echo '<h3>Session variable contents</h3>';

				var_dump( $_SESSION['wpskillz_test'] );

				echo '</div>';
			}
		}

	$panels[] = new WPSkillz_Test_Panel;
	return $panels;
}
