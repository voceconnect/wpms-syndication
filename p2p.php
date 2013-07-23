<?php

class WPMS_Syndication_P2P {

	/**
	 * Syndicate all the connected posts and create the p2p connections
	 * on the remote blog.
	 *
	 * @param $origin_post_id
	 * @param $syndicated_post_id
	 * @param $blog_id
	 */
	public static function syndicate( $origin_post_id, $syndicated_post_id, $blog_id ) {
		if ( !class_exists( 'P2P_Connection_Type_Factory' ) )
			return;

		// Get all the registered connections.
		$instances = P2P_Connection_Type_Factory::get_all_instances();

		foreach ( $instances as $instance ) {
			// Get all the connected posts.
			$connections = p2p_get_connections( $instance->name, array(
				'from' => $origin_post_id,
				) );


			foreach ( $connections as $connections ) {
				$syndicated_connected_post_id = WPMS_Syndication_Post::syndicate( $connections->p2p_to, $blog_id );

				// Create the p2p conenction on the remote blog.
				switch_to_blog( $blog_id );
				$args = array(
					'to' => $syndicated_connected_post_id,
					'from' => $syndicated_post_id,
				);

				if ( !p2p_connection_exists( $instance->name, $args ) ) {
					p2p_create_connection( $instance->name, $args );
				}
				restore_current_blog();
			}
		}
	}

}