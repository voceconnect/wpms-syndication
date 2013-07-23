<?php

class WPMS_Syndication_Metaboxes {

	public static function initialize() {
		add_action( 'admin_footer', array( __CLASS__, 'admin_footer' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
	}

	public static function admin_footer() {
		?>
		<script>
			jQuery(document).ready(function($) {

				$('.syndicate_to').click(function(e) {
					do_wpms_syndication_ajax($('#syndicate'));
				});

				$('#syndicate').click(function(e) {
					e.preventDefault();
					do_wpms_syndication_ajax($(this));
				});

				function do_wpms_syndication_ajax($el) {
					var blog_ids = new Array(),
							post_id = $el.data('post_id'),
							$spinner = $('#major-syndaction-actions .spinner');

					$spinner.show();
					$('.syndicate_to:checked').each(function() {
						blog_ids.push($(this).data('blog_id'));
					});

					$.post(
							ajaxurl,
							{
								'action': 'syndicate_post',
								'blog_ids': blog_ids,
								'origin_post_id': post_id
							},
					function(data) {
						for (var i = 0; i < blog_ids.length; i++) {
							if (!$('#delete_from_' + blog_ids[i]).length) {
								$('#blog_' + blog_ids[i]).next().after('<a href="#" id="delete_from_' + blog_ids[i] + '" class="delete_syndicated" data-post_id="' + post_id + '" data-blog_id="' + blog_ids[i] + '" style="color: #f00;border-bottom-color: #f00;display:inline-block;float:right;">Delete</a>');
							}
						}

						$spinner.hide();
					}
					);
				}

				$('.delete_syndicated').live('click', function(e) {
					e.preventDefault();

					var $spinner = $('#major-syndaction-actions .spinner'),
							$el = $(this);

					$spinner.show();
					$.post(
							ajaxurl,
							{
								'action': 'delete_syndicated_post',
								'blog_id': $el.data('blog_id'),
								'origin_post_id': $el.data('post_id')
							},
					function(data) {
						$el.remove();
						$spinner.hide();
					}
					);
				});
			});
		</script>
		<?php
	}

	public static function add_meta_boxes() {
		$post_types = get_post_types( array(
			'public' => true,
			) );

		foreach ( $post_types as $post_type ) {
			add_meta_box( 'syndication_postdiv', 'Syndication', array( __CLASS__, 'syndication_post_metabox' ), $post_type, 'side', 'core' );
		}
	}

	public static function syndication_post_metabox() {
		// @todo: don't rely on the user being 1
		$blogs = get_blogs_of_user( 1 );

		$syndicate_to = get_post_meta( get_the_ID(), 'syndicate_to' );

		foreach ( $blogs as $blog ) {
			if ( $blog->userblog_id === get_current_blog_id() )
				continue;

			$checked = '';
			if ( isset( $syndicate_to[0] ) && in_array( $blog->userblog_id, $syndicate_to[0] ) )
				$checked = ' checked';
			?>
			<div style="line-height:20px;">
				<input 
					id="blog_<?php echo $blog->userblog_id; ?>"
					class="syndicate_to" 
					type="checkbox" 
					data-blog_id="<?php echo esc_attr( $blog->userblog_id ); ?>"
					<?php echo $checked; ?>
					/>
				<label for="blog_<?php echo $blog->userblog_id; ?>"><?php echo $blog->blogname; ?></label>

				<?php if ( WPMS_Syndication_Post::remote_post_exists( get_the_ID(), $blog->userblog_id ) ) : ?>
					<a href="#" 
						 id="delete_from_<?php echo esc_attr( $blog->userblog_id ); ?>" 
						 class="delete_syndicated" 
						 data-post_id="<?php echo esc_attr( get_the_ID() ); ?>" 
						 data-blog_id="<?php echo esc_attr( $blog->userblog_id ); ?>" 
						 style="color: #f00;border-bottom-color: #f00;display:inline-block;float:right;">Delete</a>
					 <?php endif; ?>
			</div>
			<?php
		}
		?>

		<div id="major-syndaction-actions" style="padding-top:10px;">
			<div id="publishing-action">
				<span class="spinner"></span>

				<input
					type="button" 
					id="syndicate" 
					class="button button-primary button-large"
					style="float:right;"
					data-post_id="<?php echo esc_attr( get_the_ID() ); ?>"
					value="Syndicate" />
			</div>

			<div class="clear"></div>
		</div>


		<?php
	}

}

WPMS_Syndication_Metaboxes::initialize();