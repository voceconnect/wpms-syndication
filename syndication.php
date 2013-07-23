<?php

/*
  Plugin Name: WPMS Syndication
  Plugin URI:
  Description: Broadcast posts to other blogs on the multisite.
  Version: 1.0
  Author: Voce Platforms
  Author URI:
  License:
 */

require_once( __DIR__ . '/posts.php' );
require_once( __DIR__ . '/meta.php' );
require_once( __DIR__ . '/taxonomies.php' );
require_once( __DIR__ . '/media.php' );
require_once( __DIR__ . '/p2p.php' );
require_once( __DIR__ . '/metaboxes.php' );

/**
 * @author johnciacia
 * @package syndication
 */
class WPMS_Syndication {

	static $DOING_SYNDICATION = false;

	public static function initialize() {
		/**
		 * @todo: if origin post is deleted or trashed, delete remote post?
		 */
		add_action( 'delete_term', array( __CLASS__, 'delete_term' ), 10, 4 );
		add_action( 'edited_term', array( __CLASS__, 'edited_term' ), 10, 3 );
		add_action( 'save_post', array( __CLASS__, 'save_post' ), 99, 2 );
		add_action( 'wp_ajax_syndicate_post', array( __CLASS__, 'ajax_syndicate_post' ) );
		add_action( 'wp_ajax_delete_syndicated_post', array( __CLASS__, 'ajax_delete_syndicated_post' ) );
	}

	/**
	 * When a term is deleted on the origin blog, delete that term on all
	 * remote blogs.
	 * 
	 * @param $term
	 * @param $tt_id
	 * @param $taxonomy
	 */
	public static function delete_term( $term, $tt_id, $taxonomy, $deleted_term ) {
		remove_action( 'delete_term', array( __CLASS__, 'delete_term' ), 10, 3 );

		$blogs = self::get_syndicated_blogs();
		foreach ( $blogs as $blog )
			WPMS_Syndication_Taxonomies::delete_remote_term( $deleted_term, $taxonomy, $blog->userblog_id );

		add_action( 'delete_term', array( __CLASS__, 'delete_term' ), 10, 3 );
	}

	/**
	 * When a term is edited on the origin blog, update the term on all
	 * remote blogs.
	 *
	 * @param $term_id
	 * @param $tt_id
	 * @param $taxonomy
	 */
	public static function edited_term( $term_id, $tt_id, $taxonomy ) {
		if ( self::$DOING_SYNDICATION && self::$DOING_SYNDICATION === true )
			return;



		remove_action( 'edited_term', array( __CLASS__, 'edited_term' ), 10, 3 );

		$blogs = self::get_syndicated_blogs();
		$origin_term = get_term( $term_id, $taxonomy );
		foreach ( $blogs as $blog )
			WPMS_Syndication_Taxonomies::update_remote_term( $origin_term, $taxonomy, $blog->userblog_id );

		add_action( 'edited_term', array( __CLASS__, 'edited_term' ), 10, 3 );
	}

	/**
	 * When a post is created or updated, syndicate the post, post meta
	 * attached media and P2P connected posts.
	 *
	 * @param $origin_post_id
	 * @param $origin_post
	 */
	public static function save_post( $origin_post_id, $origin_post ) {

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return;

		if ( wp_is_post_revision( $origin_post_id ) )
			return;

		remove_action( 'save_post', array( __CLASS__, 'save_post' ), 99, 2 );

		$origin_post_id = apply_filters( 'syndication_origin_post_id', $origin_post_id, $origin_post );

		$syndicate_to = get_post_meta( $origin_post_id, 'syndicate_to' );
		if ( !isset( $syndicate_to[0] ) )
			return;


		foreach ( $syndicate_to[0] as $blog_id )
			self::syndicate_all( $origin_post_id, $blog_id );

		add_action( 'save_post', array( __CLASS__, 'save_post' ), 99, 2 );
	}

	/**
	 * Ajax function used to syndicate a post to remote blogs. To syndicate a post
	 * we need an array of blog ids and the origin post id.
	 */
	public static function ajax_syndicate_post() {
		header( 'Content-Type: application/json' );

		if ( !isset( $_POST['origin_post_id'] ) )
			die( array( 'response' => 'error', 'message' => 'The origin post ID must be sent.' ) );

		if ( isset( $_POST['blog_ids'] ) ) {
			foreach ( $_POST['blog_ids'] as $blog_id )
				self::syndicate_all( ( int ) $_POST['origin_post_id'], ( int ) $blog_id );

			update_post_meta( ( int ) $_POST['origin_post_id'], 'syndicate_to', $_POST['blog_ids'] );
		} else {
			// When you unsyndicate the very last blog, $_POST['blog_ids'] will not be
			// sent (because none of the blogs are checked). In this situation, delete the 
			// syndicate_to meta key.
			$syndicate_to = get_post_meta( ( int ) $_POST['origin_post_id'], 'syndicate_to' );
			if ( isset( $syndicate_to[0] ) && 1 == count( $syndicate_to[0] ) ) {
				delete_post_meta( ( int ) $_POST['origin_post_id'], 'syndicate_to' );
			}

			// We should never actually get here.
			else {
				die( json_encode( array( 'response' => 'error', 'message' => 'Unknown error.' ) ) );
			}
		}

		die( json_encode( array( 'response' => 'success' ) ) );
	}

	/**
	 * Ajax function used to delete the remote post. To delete a remote post
	 * we need the origin post id and the remote blog id.
	 */
	public static function ajax_delete_syndicated_post() {
		header( 'Content-Type: application/json' );

		if ( !isset( $_POST['origin_post_id'] ) || !isset( $_POST['blog_id'] ) )
			die( json_encode( array( 'response' => 'error', 'message' => 'The origin post ID and remote blog ID must be set.' ) ) );

		WPMS_Syndication_Post::delete_remote_post( ( int ) $_POST['origin_post_id'], ( int ) $_POST['blog_id'] );

		die( json_encode( array( 'response' => 'success' ) ) );
	}

	/**
	 * Helper function to get a list of blogs.
	 *
	 * @param $post_id
	 */
	public static function get_syndicated_blogs( $post_id = null ) {
		if ( null === $post_id )
			return get_blogs_of_user( 1 );

		// @todo: get syndicated blogs for $post_id
	}

	/**
	 * Helper function that calls helpers classes to do individual (WordPress) object syndication.
	 *
	 * @param $origin_post_id
	 * @param $blog_id
	 */
	public static function syndicate_all( $origin_post_id, $blog_id ) {
		self::$DOING_SYNDICATION = true;

		remove_action( 'save_post', array( __CLASS__, 'save_post' ), 99 );

		// Post to Post hooks into save_post. This action causes syndication to
		// break when WPMS_Syndication_Post::syndicate tries to insert a post; 
		// the Post to Post plugin expects $_POST['p2p_connections'] to 
		// be set with specific values. When WPMS_Syndication_Post::syndicate runs
		// and attempts to insert the syndicated post, the values Post to Post 
		// expects are incorrect. We unset the values to prevent the Post to Post
		// hook from running when a syndicated post is inserted, and we restore
		// $_POST['p2p_connections'] after syndication has finished.
		if ( isset( $_POST['p2p_connections'] ) ) {
			$restore_p2p_connections = true;
			$p2p_connections = $_POST['p2p_connections'];
			unset( $_POST['p2p_connections'] );
		}

		$syndicated_post_id = WPMS_Syndication_Post::syndicate( $origin_post_id, $blog_id );
		WPMS_Syndication_Taxonomies::syndicate( $origin_post_id, $syndicated_post_id, $blog_id );
		WPMS_Syndication_P2P::syndicate( $origin_post_id, $syndicated_post_id, $blog_id );
		WPMS_Syndication_Media::syndicate( $origin_post_id, $syndicated_post_id, $blog_id );


		// Restore the correct values for $_POST['p2p_connections']
		if ( isset( $restore_p2p_connections ) && $restore_p2p_connections === true ) {
			$_POST['p2p_connections'] = $p2p_connections;
		}

		add_action( 'save_post', array( __CLASS__, 'save_post' ), 99 );
		self::$DOING_SYNDICATION = false;
	}

}

WPMS_Syndication::initialize();