<?php

/**
 * @package syndication
 */
class WPMS_Syndication_Meta {

	/**
	 * Syndicate the post meta.
	 *
	 * @param $origin_post_id
	 * @param $syndicated_post_id
	 * @param $blog_id
	 */
	public static function syndicate( $origin_post_id, $syndicated_post_id, $blog_id ) {
		$post_meta = self::get_post_meta( $origin_post_id );
		self::update_post_meta( $syndicated_post_id, $post_meta, $blog_id );
	}

	/**
	 * Get all the post meta associated with the origin post
	 * whose meta key does not begin with an underscore.
	 *
	 * @param $origin_post_id
	 * @return array
	 */
	public static function get_post_meta( $origin_post_id ) {
		$origin_post_meta = get_post_custom( $origin_post_id );
		$syndicated_post_meta = array( );

		foreach ( $origin_post_meta as $meta_key => $meta_value ) {
			// Do not syndicate WordPress 'builtin' meta.
			if ( '_' === $meta_key[0] )
				continue;

			if ( is_array( $meta_value ) )
				$syndicated_post_meta[$meta_key] = maybe_unserialize( $meta_value[0] );
			else
				$syndicated_post_meta[$meta_key] = maybe_unserialize( $meta_value );
		}

		return $syndicated_post_meta;
	}

	/**
	 * Update the post meta on the remote blog.
	 * 
	 * @param $syndicated_post_id
	 * @param $post_meta
	 * @param $blog_id
	 */
	private function update_post_meta( $syndicated_post_id, $post_meta, $blog_id ) {
		if ( is_array( $post_meta ) ) {
			switch_to_blog( $blog_id );
			foreach ( $post_meta as $meta_key => $meta_value )
				update_post_meta( $syndicated_post_id, $meta_key, $meta_value );
			restore_current_blog();
		}
	}

}