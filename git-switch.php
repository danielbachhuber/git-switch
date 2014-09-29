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

	const CACHE_KEY = 'git-switch-status';

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

		add_action( 'wp_ajax_git-switch-branch', array( $this, 'handle_switch_branch_action' ) );
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
	 * Handle the action to switch a branch
	 */
	public function handle_switch_branch_action() {

		if ( ! current_user_can( $this->capability ) || ! wp_verify_nonce( $_GET['nonce'], 'git-switch-branch-' . $_GET['branch'] ) ) {
			wp_die( "You can't do this." );
		}

		if ( ! $status = $this->get_git_status() ) {
			wp_die( "Can't interact with Git." );
		}

		if ( ! empty( $status['dirty'] ) ) {
			wp_die( "Can't switch when Git is dirty." );
		}

		$theme_path = get_stylesheet_directory();

		exec( sprintf( 'cd %s; git checkout %s', escapeshellarg( $theme_path ), escapeshellarg( $_GET['branch'] ) ), $results );
		delete_transient( self::CACHE_KEY );
		wp_safe_redirect( home_url() );
		exit;

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

		if ( ! empty( $status['remote'] ) ) {
			$wp_admin_bar->add_menu( array(
				'parent' => 'git-switch',
				'id'     => 'git-switch-branches',
				'title'  => 'Switch branch:',
				'href'   => '#'
			) );
			foreach( $status['remote'] as $remote_branch ) {

				$query_args = array(
					'action'        => 'git-switch-branch',
					'branch'        => $remote_branch,
					'nonce'         => wp_create_nonce( 'git-switch-branch-' . $remote_branch ),
					);
				$branch_switch_url = add_query_arg( $query_args, admin_url( 'admin-ajax.php' ) );

				$wp_admin_bar->add_menu( array(
					'parent' => 'git-switch-branches',
					'id'     => 'git-switch-branch-' . sanitize_key( $remote_branch ),
					'title'  => esc_html( $remote_branch ),
					'href'   => esc_url( $branch_switch_url ),
				) );
			}
		}

	}

	/**
	 * Get the current Git status
	 */
	public function get_git_status() {

		if ( false !== ( $cache_status = get_transient( self::CACHE_KEY ) ) ) {
			return $cache_status;
		}

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
				return trim( str_replace( 'origin/', '', $branch ) );
			}, $branches );
			$return['remote'] = $branches;
		}

		set_transient( self::CACHE_KEY, $return, MINUTE_IN_SECONDS * 5 );

		return $return;
	}

	/**
	 * Refresh Git
	 */
	public function refresh() {

		if ( ! function_exists( 'exec' ) ) {
			return false;
		}

		$theme_path = get_stylesheet_directory();
		exec( sprintf( 'cd %s; git fetch origin', escapeshellarg( $theme_path ) ) );

		delete_transient( self::CACHE_KEY );

		$status = $this->get_git_status();
		if ( 'detached' !== $status['branch'] && empty( $status['dirty'] ) ) {
			exec( sprintf( 'cd %s; git pull origin %s', escapeshellarg( $theme_path ), escapeshellarg( $status['branch'] ) ) );
		}

		delete_transient( self::CACHE_KEY );
	}

}

/**
 * Release the kraken!
 */
function Git_Switch() {
	return Git_Switch::get_instance();
}
add_action( 'plugins_loaded', 'Git_Switch' );