<?php

class Featured_Replies_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'widget-name-id',
			__( '(bbPress) Featured Replies', 'bbp-featured-replies' ),
			array(
				'classname'		=>	'bbp-featured-replies-widget',
				'description'	=>	__( 'Display bbPress replies marked as "featured".', 'bbp-featured-replies' )
			)
		);

	} // end constructor


	/**
	 * Outputs the content of the widget.
	 *
	 * @param	array	args		The array of form elements
	 * @param	array	instance	The current instance of the widget
	 */
	public function widget( $args, $instance ) {

		$cache = wp_cache_get( 'featured_replies_widget', 'widget' );

		if ( ! is_array( $cache ) )
			$cache = array();

		if ( ! isset( $args['widget_id'] ) )
			$args['widget_id'] = $this->id;

		if ( isset( $cache[ $args['widget_id'] ] ) ) {
			echo $cache[ $args['widget_id'] ];
			return;
		}

		extract( $args, EXTR_SKIP );

		$title  = apply_filters( 'widget_title', $instance['title'] );

		if( empty( $number ) || ! $number = absint( $number ) )
			$number = 5;

			$query_args = apply_filters( 'featured_replies_query', array(
				'number'      => $number,
				'status'      => 'approve',
				'post_status' => 'publish',
				'post_type'   => bbp_get_reply_post_type(),
				'meta_query'  => array(
					array(
						'key'    => 'featured',
						'value'  => '1'
					)
				)
			) );

			$query    = new WP_Query;

			$replies = $query->query( $query_args );

			if( $replies ) :

				$output = $before_widget;
				if ( $title )
					$output .= $before_title . $title . $after_title;

				$output .= '<ul id="bbp-featured-replies">';

				if ( $replies ) {

					foreach ( (array) $replies as $reply) {
						$topic_id = bbp_get_reply_topic_id( $reply->ID );
						$output .=  '<li class="bbp-featured-replies">';
							$output .= sprintf(
								_x( '%1$s on %2$s', 'widgets' ),
								bbp_get_reply_author_link( array(
									'post_id' => $reply->ID,
									'type' => 'name',
									) ),
								'<a href="' . esc_url( bbp_get_topic_permalink( $topic_id ) . '#post-' . $reply->ID  ) . '">' . bbp_get_topic_title( $topic_id ) . '</a>'
							);
						$output .= '</li>';
					}

		 		}
				$output .= '</ul>';

			endif;

		$output .= $after_widget;

		echo $output;

		$cache[$args['widget_id']] = $output;
		wp_cache_set( 'featured_replies_widget', $cache, 'widget' );

	} // end widget


	/**
	 * Processes the widget's options to be saved.
	 *
	 * @param	array	new_instance	The previous instance of values before the update.
	 * @param	array	old_instance	The new instance of values to be generated via the update.
	 */
	public function update( $new_instance, $old_instance ) {

		$instance = $old_instance;

		$instance['title']  = sanitize_text_field( $new_instance['title'] );
		$instance['number'] = absint( $new_instance['number'] );

		$this->flush_widget_cache();

		return $instance;

	} // end widget

	function flush_widget_cache() {
		wp_cache_delete( 'featured_replies_widget', 'widget' );
	}

	/**
	 * Generates the administration form for the widget.
	 *
	 * @param	array	instance	The array of keys and values for the widget.
	 */
	public function form( $instance ) {

		$defaults = array(
			'title'  => __( 'Featured Replies', 'bbp-featured-replies' ),
			'number' => 5
		);

		$args = wp_parse_args( $instance, $defaults );

    	$title  = esc_attr( $args['title'] );
    	$number = esc_attr( $args['number'] );

    	?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e( 'Widget Title:', 'bbp-featured-replies' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p>
			<input class="widefat small-text" id="<?php echo $this->get_field_id('number'); ?>" name="<?php echo $this->get_field_name('number'); ?>" type="number" value="<?php echo esc_attr( $number ); ?>" />
			<label for="<?php echo $this->get_field_id('number'); ?>"><?php _e( 'Number to show', 'bbp-featured-replies' ); ?></label>
		</p>

		<?php

	} // end form


} // end class
add_action( 'widgets_init', create_function( '', 'register_widget("Featured_Replies_Widget");' ) );
