<?php

/**
 * @todo: refactor ALL the code
 */
class WPMS_Syndication_Media {

	public function syndicate( $origin_post_id, $syndicated_post_id, $blog_id ) {
		$origin_attachments = get_posts( array(
			'post_type' => 'attachment',
			'post_parent' => $origin_post_id,
			) );

		$origin_post_thumbnail_id = get_post_thumbnail_id( $origin_post_id );
		$origin_upload_dir = wp_upload_dir();

		// @todo: refactor WPMS_Syndication_Post to handle this since a lot of it is copy/paste
		foreach ( $origin_attachments as $origin_attachment ) {
			$metadata = wp_get_attachment_metadata( $origin_attachment->ID );
			$origin_file_path = $origin_upload_dir['basedir'] . '/' . $metadata['file'];

			switch_to_blog( $blog_id );

			$syndicated_upload_dir = wp_upload_dir();
			$syndicated_file_path = $syndicated_upload_dir['basedir'] . '/' . $metadata['file'];

			copy( $origin_file_path, $syndicated_file_path );

			$attachment = array(
				'guid' => $syndicated_upload_dir['url'] . '/' . basename( $metadata['file'] ),
				'post_mime_type' => $origin_attachment->post_mime_type,
				'comment_status' => $origin_attachment->comment_status,
				'ping_status' => $origin_attachment->ping_status,
				'post_author' => $origin_attachment->post_author,
				'post_content' => $origin_attachment->post_content,
				'post_date_gmt' => $origin_attachment->post_date_gmt,
				'post_excerpt' => $origin_attachment->post_excerpt,
				'post_password' => $origin_attachment->post_password,
				'post_status' => $origin_attachment->post_status,
				'post_title' => $origin_attachment->post_title,
				'post_type' => $origin_attachment->post_type,
			);



			if ( $syndicated_attachment = WPMS_Syndication_Post::remote_post_exists( $origin_attachment->ID, $blog_id ) ) {
				$syndicated_attachment_id = $attachment['ID'] = $syndicated_attachment->ID;
				wp_update_post( $attachment );
			} else {
				$syndicated_attachment_id = wp_insert_attachment( $attachment, $metadata['file'], $syndicated_post_id );
				update_post_meta( $syndicated_attachment_id, 'origin_post_id', $origin_attachment->ID );
			}

			$attach_data = wp_generate_attachment_metadata( $syndicated_attachment_id, $syndicated_file_path );
			wp_update_attachment_metadata( $syndicated_attachment_id, $attach_data );


			// @todo: the featured image will not be set if the featured image is not
			// explicitally attached to the origin post.
			if ( isset( $origin_post_thumbnail_id ) && $origin_attachment->ID == $origin_post_thumbnail_id )
				set_post_thumbnail( $syndicated_post_id, $syndicated_attachment_id );

			restore_current_blog();
		}
	}

}