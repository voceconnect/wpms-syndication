<?php

/**
 * - We make the assumption that taxonomies are consistent
 *   across all blogs.
 */
class WPMS_Syndication_Taxonomies {

	/**
	 * Insert or update the remote term and attach the terms
	 * to the syndicated post.
	 *
	 * @param $origin_post_id
	 * @param $syndicated_post_id
	 * @param $blog_id
	 */
	public static function syndicate( $origin_post_id, $syndicated_post_id, $blog_id ) {
		$taxonomies = get_taxonomies( array(
			'public' => true,
			) );

		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_post_terms( $origin_post_id, $taxonomy );
			if ( false === $terms || empty( $terms ) )
				continue;

			// Insert the remote term if the origin term does not exist.
			// Update the remote terms if the origin term has changed.
			$object_terms = array( );
			foreach ( $terms as $term ) {
				$object_terms[] = self::update_remote_term( $term, $taxonomy, $blog_id );
			}

			switch_to_blog( $blog_id );
			wp_set_object_terms( $syndicated_post_id, $object_terms, $taxonomy );
			restore_current_blog();
		}
	}

	/**
	 * Update the remote term if it exists. If the remote term does
	 * not exist, insert it.
	 *
	 * @param $origin_term
	 * @param $taxonomy
	 * @param $blog
	 * @return bool|int false if there was an error, otherwise return the term id
	 */
	public static function update_remote_term( $origin_term, $taxonomy, $blog_id ) {

		switch_to_blog( $blog_id );
		$syndicated_term = NULL;

		if ( $term_id = self::remote_term_exists( $origin_term, $taxonomy, $blog_id ) ) {
			$syndicated_term = wp_update_term( $term_id, $taxonomy, ( array ) $origin_term );
			if ( is_wp_error( $syndicated_term ) )
				return false;
		} else {
			$syndicated_term = wp_insert_term( $origin_term->name, $taxonomy );
			if ( is_wp_error( $syndicated_term ) )
				return false;

			// @todo use a more efficient implementation than 
			// than creating an option for each tax/term combo
			add_option( $taxonomy . '_' . $origin_term->term_id, $syndicated_term['term_id'] );
		}
		restore_current_blog();

		return $syndicated_term['term_id'];
	}

	/**
	 * Delete the remote term.
	 *
	 * @param $origin_term_id
	 * @param $taxonomy
	 * @param $blog_id
	 * @return bool|int false if term does not exist otherwise return the term id.
	 */
	public static function delete_remote_term( $origin_term, $taxonomy, $blog_id ) {

		switch_to_blog( $blog_id );

		// @todo: delete the option too
		if ( $term_id = self::remote_term_exists( $origin_term, $taxonomy, $blog_id ) )
			wp_delete_term( $term_id, $taxonomy );

		restore_current_blog();
	}

	/**
	 * Check if the origin term exists on the remote blog. 
	 *
	 * @param $origin_term object
	 * @param $taxonomy
	 * @param $blog_id
	 * @return bool|int false if term does not exist otherwise return the term id.
	 */
	public static function remote_term_exists( $origin_term, $taxonomy, $blog_id ) {
		switch_to_blog( $blog_id );

		$syndicated_term = term_exists( $origin_term->name, $taxonomy );
		$syndicated_term_id = get_option( $taxonomy . '_' . $origin_term->term_id, false );

		// The term may have been syndicated, but this does not guarantee the remote  
		// term actually exists. For example, it could have been deleted on the 
		// remote blog after it was syndicated. 
		if ( false !== $syndicated_term_id ) {

			// If the term exists on the remote blog return the 'local' term.
			if ( 0 !== $syndicated_term && null !== $syndicated_term ) {
				// It is possible the option and the actual term id are not
				// synchronized. If this is the case, delete the option, and
				// re-add the option with the correct term ID.
				if ( $syndicated_term_id !== $syndicated_term['term_id'] ) {
					delete_option( $taxonomy . '_' . $origin_term->term_id );
					add_option( $taxonomy . '_' . $origin_term->term_id, $syndicated_term['term_id'] );
				}
				$syndicated_term_id = $syndicated_term['term_id'];
			}

			// If the term does not exist on the remote site, delete the
			// option and return false.
			else {
				delete_option( $taxonomy . '_' . $origin_term->term_id );
				$syndicated_term_id = false;
			}
		}

		// The term has not yet been syndicated, but the term may already exist.
		// For example, the term 'Uncategorized' may exist on the remote blog. If
		// the term does exist, use that one instead.
		else {
			if ( 0 !== $syndicated_term && null !== $syndicated_term ) {
				$syndicated_term_id = $syndicated_term['term_id'];
				add_option( $taxonomy . '_' . $origin_term->term_id, $syndicated_term['term_id'] );
			}
		}

		restore_current_blog();
		return $syndicated_term_id;
	}

}