<?php
/*
 * Plugin Name: Git Switch
 * Plugin URI: http://danielbachhuber.com
 * Description: Switch your theme between Git branches.
 * Author: Daniel Bachhuber
 * Version: 0.1-alpha
 * Author URI: http://danielbachhuber.com
 */

class Git_Switch {

	private static $instance;

	public static function get_instance() {

		if ( ! isset( self::$instance ) ) {
			self::$instance = new Git_Switch;
			self::$instance->load();
		}
		return self::$instance;
	}

	/**
	 * Load the plugin
	 */
	private function load() {

		add_action( 'admin_bar_menu', array( $this, 'action_admin_bar_menu' ), 999 );

		add_action( 'wp_head', function() {
			?>
			<style>
			#wp-admin-bar-git-switch-details > a {
				height: auto !important;
			}
			#wp-admin-bar-git-switch-details > a:hover {
				color: #eee !important;
			}
			</style>
			<?php
		});

	}

	/**
	 * Display helpful details in the admin bar
	 */
	public function action_admin_bar_menu( $wp_admin_bar ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! $status = $this->get_git_status() ) {
			return;
		}

		$wp_admin_bar->add_menu( array(
			'id'     => 'git-switch',
			'title'  => sprintf( 'git(%s)%s', $status['branch'], $status['dirty'] ),
			'href'   => '#'
		) );

		$wp_admin_bar->add_menu( array(
			'parent' => 'git-switch',
			'id'     => 'git-switch-details',
			'title'  => ( implode('<br>', array_map( 'esc_html', $status['status'] ) ) ),
			'href'   => '#'
		) );

	}

	/**
	 * Get the current Git status
	 */
	public function get_git_status() {

		if ( ! function_exists( 'exec' ) ) {
			return false;
		}

		$theme_path = get_stylesheet_directory();

		exec( sprintf( 'cd %s; git status', escapeshellarg( $theme_path ) ), $status );

		if ( empty( $status ) or ( false !== strpos( $status[0], 'fatal' ) ) ) {
			return false;
		}

		$end = end( $status );
		$return = array(
			'dirty'  => '*',
			'branch' => 'detached',
			'status' => $status,
			'remote' => array(),
		);

		if ( preg_match( '/On branch (.+)$/', $status[0], $matches ) ) {
			$return['branch'] = trim( $matches[1] );
		}

		if ( empty( $end ) or ( false !== strpos( $end, 'nothing to commit' ) ) ) {
			$return['dirty'] = '';
		}

		exec( sprintf( 'cd %s; git branch -r', escapeshellarg( $theme_path ) ), $branches );
		if ( ! empty( $branches ) ) {
			$branches = array_map( function( $branch ) {
				return str_replace( 'origin/', '', $branch );
			}, explode( PHP_EOL, $branches ) );
			$return['remote'] = $branches;
		}

		return $return;
	}

}

/**
 * Release the kraken!
 */
function Git_Switch() {
	return Git_Switch::get_instance();
}
add_action( 'plugins_loaded', 'Git_Switch' );