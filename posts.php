<?php

class WPMS_Syndication_Post {

	/**
	 * Syndicate the a post and the posts meta.
	 *
	 * @param $origin_post_id
	 * @param $blog_id
	 * @return int The syndicated post ID
	 */
	public static function syndicate( $origin_post_id, $blog_id ) {

		if ( self::post_exists( $origin_post_id ) && self::blog_exists( $blog_id ) ) {
			$origin_post = get_post( $origin_post_id );
			$syndicated_post = self::prepare_post( $origin_post );
			$blog_name = get_bloginfo( 'name' );
			$notice = "Warning: This post has been syndicated from <a href='" . get_edit_post_link( $origin_post_id ) . "' target='_blank'>" . $blog_name . " - " . get_the_title( $origin_post_id ) . "</a>";

			switch_to_blog( $blog_id );

			if ( $remote_post = self::remote_post_exists( $origin_post_id, $blog_id ) ) {
				$syndicated_post_id = $remote_post->ID;
				$syndicated_post['ID'] = $remote_post->ID;
				wp_update_post( $syndicated_post );
			} else {
				$syndicated_post_id = wp_insert_post( $syndicated_post );
				update_post_meta( $syndicated_post_id, 'origin_post_id', $origin_post_id );
				update_post_meta( $syndicated_post_id, 'admin_warning', $notice );
			}
			restore_current_blog();

			// Syndicate the posts' meta
			WPMS_Syndication_Meta::syndicate( $origin_post_id, $syndicated_post_id, $blog_id );
			return $syndicated_post_id;
		}
	}

	/**
	 * Verify post exists for current blog.
	 *
	 * @param $origin_post_id 
	 * @return bool
	 */
	private static function post_exists( $origin_post_id ) {
		if ( get_post( $origin_post_id ) )
			return true;

		return false;
	}

	/**
	 * Verify blog_id exists
	 *
	 * @return bool
	 */
	private static function blog_exists( $blog_id ) {
		if ( get_blog_option( $blog_id, 'blogname' ) )
			return true;

		return false;
	}

	/**
	 * Check if post exists on remote blog. 
	 *
	 * @param $origin_post_id
	 * @param $blog_id
	 * @return bool|WP_Post If the post remote exists return the syndicated post. 
	 * If the post does not exist return false.
	 */
	public static function remote_post_exists( $origin_post_id, $blog_id ) {
		switch_to_blog( $blog_id );
		$query = new WP_Query( array(
			'meta_key' => 'origin_post_id',
			'meta_value' => $origin_post_id,
			'post_status' => 'any',
			'post_type' => get_post_types(),
			'posts_per_page' => 1,
			) );
		restore_current_blog();

		if ( $query->have_posts() )
			return $query->post;

		return false;
	}

	/**
	 * Strip out unused data from the origin post and create
	 * an array of data to be used by the syndicated post.
	 *
	 * @param WP_Post $origin_post 
	 * @return array
	 */
	private static function prepare_post( $origin_post ) {
		if ( !is_a( $origin_post, 'WP_Post' ) )
			return array( );

		$syndicated_post = array(
			'comment_status' => $origin_post->comment_status,
			'ping_status' => $origin_post->ping_status,
			'post_author' => $origin_post->post_author,
			'post_content' => $origin_post->post_content,
			'post_date_gmt' => $origin_post->post_date_gmt,
			'post_excerpt' => $origin_post->post_excerpt,
			'post_password' => $origin_post->post_password,
			'post_status' => $origin_post->post_status,
			'post_title' => $origin_post->post_title,
			'post_type' => $origin_post->post_type,
		);

		if ( 'attachment' === $origin_post->post_type ) {
			$syndicated_post['guid'] = $origin_post->guid;
			$syndicated_post['mime_type'] = $origin_post->mime_type;
			$syndicated_post['post_parent'] = $origin_post->post_parent;
		}

		return $syndicated_post;
	}

	/**
	 * Delete a post on a remote blog.
	 *
	 * @param $origin_post_id
	 * @param $blog_id
	 * @return bool
	 */
	public static function delete_remote_post( $origin_post_id, $blog_id ) {
		if ( $syndicated_post = self::remote_post_exists( $origin_post_id, $blog_id ) ) {
			switch_to_blog( $blog_id );
			$ret = wp_delete_post( $syndicated_post->ID, true );
			restore_current_blog();

			if ( $ret )
				return true;
		}

		return false;
	}

}