<?php
/*
 * Plugin Name: bbPress - Featured Replies
 * Plugin URI: http://www.josheaton.org/wordpress-plugins/bbpress-featured-replies
 * Description: Lets the admin add "featured" or "buried" css class to selected bbPress replies. Handy to highlight replies that add value to the topic. Also includes a Featured Replies widget.
 * Version: 0.1.1
 * Author: Josh Eaton
 * Author URI: http://www.josheaton.org/
 * Contributors: jjeaton
 * Textdomain: bbp-featured-replies
 */

/*
Based on Featured Comments by Pippin Williamson and Utkarsh Kukreti

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
Online: http://www.gnu.org/licenses/gpl.txt
*/


final class Featured_Replies {


	/** Singleton *************************************************************/

	/**
	 * @var Featured_Replies
	 */
	private static $instance;

	/**
	 * @var array possible feature/bury actions
	 */
	private static $actions;


	/**
	 * Main Featured_Replies Instance
	 *
	 * Ensures that only one instance of Featured_Replies exists in memory at any one
	 * time. Also prevents needing to define globals all over the place.
	 *
	 * @since v1.0
	 * @staticvar array $instance
	 * @see wp_featured_replies_load()
	 * @return The one true Featured_Replies
	 */
	public static function instance() {

		add_action( 'admin_init', array( 'Featured_Replies', 'check_for_bbpress' ) );

		if ( ! self::is_bbpress_active() ) {
			return;
		}

		if ( ! isset( self::$instance ) ) {
			self::$instance = new Featured_Replies;
			self::$instance->includes();
			self::$instance->init();
			self::$instance->load_textdomain();
		}
		return self::$instance;

	}

	private function includes() {

		include_once( dirname( __FILE__ ) . '/includes/widget.php' );

	}

	/** Filters & Actions **/
	private function init() {

		self::$actions = array(
			'feature'   => __( 'Feature',   'bbp-featured-replies' ),
			'unfeature' => __( 'Unfeature', 'bbp-featured-replies' ),
			'bury'      => __( 'Bury',      'bbp-featured-replies' ),
			'unbury'    => __( 'Unbury',    'bbp-featured-replies' )
		);

		/* Backend */
		add_action( 'save_post',                array( $this, 'save_meta_box_postdata' ) );
		add_action( 'add_meta_boxes',           array( $this, 'add_reply_meta_box'     ) );
		add_action( 'wp_ajax_featured_replies', array( $this, 'ajax'                   ) );
		add_filter( 'bbp_get_reply_content',    array( $this, 'add_reply_actions'      ), 10, 2 );
		add_filter( 'post_row_actions',         array( $this, 'reply_row_actions'      ), 10, 2 );

		add_action( 'wp_enqueue_scripts',       array( $this, 'maybe_print_scripts'    ) );
		add_action( 'admin_print_scripts',      array( $this, 'maybe_print_scripts'    ) );
		add_action( 'wp_print_styles',          array( $this, 'maybe_print_styles'     ) );
		add_action( 'admin_print_styles',       array( $this, 'maybe_print_styles'     ) );

		add_filter( 'user_has_cap',             array( $this, 'map_feature_cap'        ), 10, 4 );

		/* Frontend */
		add_filter( 'bbp_get_reply_class',      array( $this, 'reply_class'            ), 10, 2 );

	}

	public function load_textdomain() {

		// Set filter for plugin's languages directory
		$lang_dir = dirname( plugin_basename( __FILE__ ) ) . '/languages/';
		$lang_dir = apply_filters( 'featured_replies_languages_directory', $lang_dir );


		// Traditional WordPress plugin locale filter
		$locale        = apply_filters( 'plugin_locale',  get_locale(), 'bbp-featured-replies' );
		$mofile        = sprintf( '%1$s-%2$s.mo', 'bbp-featured-replies', $locale );

		// Setup paths to current locale file
		$mofile_local  = $lang_dir . $mofile;
		$mofile_global = WP_LANG_DIR . '/bbp-featured-replies/' . $mofile;

		if ( file_exists( $mofile_global ) ) {
			// Look in global /wp-content/languages/bbp-featured-replies folder
			load_textdomain( 'bbp-featured-replies', $mofile_global );
		} elseif ( file_exists( $mofile_local ) ) {
			// Look in local /wp-content/plugins/bbp-featured-replies/languages/ folder
			load_textdomain( 'bbp-featured-replies', $mofile_local );
		} else {
			// Load the default language files
			load_plugin_textdomain( 'bbp-featured-replies', false, $lang_dir );
		}

	}

	private static function is_bbpress_active() {
		return class_exists( 'bbPress' );
	}

	public static function activation_check() {
	    if ( ! self::is_bbpress_active() ) {
	        deactivate_plugins( plugin_basename( __FILE__ ) );
	        wp_die( __( 'bbPress - Featured Replies requires bbPress to be activated.', 'bbp-featured-replies' ) );
	    }
	}

	public static function check_for_bbpress() {
		if ( ! self::is_bbpress_active() ) {
			if ( is_plugin_active( plugin_basename( __FILE__ ) ) ) {
				deactivate_plugins( plugin_basename( __FILE__ ) );

				add_action( 'admin_notices', array( 'Featured_Replies', 'disabled_notice' ) );

				if ( isset( $_GET['activate'] ) ) {
					unset( $_GET['activate'] );
				}
			}
		}
	}

	public function disabled_notice() {
		echo '<div class="updated"><p><strong>' . esc_html__( 'bbPress - Featured Replies was deactivated. This plugin requires bbPress to be activated.', 'bbp-featured-replies' ) . '</strong></p></div>';
	}

	// Scripts
	public function maybe_print_scripts() {

		// Admin
		if ( is_admin() ) {
			if ( current_user_can( 'moderate' ) ) {
				$this->enqueue_scripts();
			}

			return;
		}

		// Front-end only
		$post = get_post();

		if ( current_user_can( 'feature_replies', $post->ID ) ) {
			$this->enqueue_scripts();
		}

	}

	public function enqueue_scripts() {

		wp_enqueue_script( 'featured-replies', plugin_dir_url( __FILE__ ) . 'js/featured-replies.js', array( 'jquery' ), filemtime( dirname( __FILE__ ) . '/js/featured-replies.js' ) );
		wp_localize_script( 'featured-replies', 'Featured_Replies', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
	}

	// Styles
	public function maybe_print_styles() {

		// Admin
		if ( is_admin() ) {
			if ( current_user_can( 'moderate' ) ) {
				$this->print_styles();
			}

			return;
		}

		// Front-end only
		$post = get_post();

		if ( current_user_can( 'feature_replies', $post->ID ) ) {
			$this->print_styles();
		}

	}

	public function print_styles() {
		?>
		<style>
			#bbpress-forums .featured-replies.unfeature, #bbpress-forums .featured-replies.unbury, .featured-replies.unfeature, .featured-replies.unbury { display:none; }
			#bbpress-forums .featured-replies, .featured-replies { cursor:pointer;}
			#bbpress-forums .featured.featured-replies.feature, .featured.featured-replies.feature { display:none; }
			#bbpress-forums .featured.featured-replies.unfeature, .featured.featured-replies.unfeature { display:inline; }
			#bbpress-forums .buried.featured-replies.bury, .buried.featured-replies.bury { display:none; }
			#bbpress-forums .buried.featured-replies.unbury, .buried.featured-replies.unbury { display:inline; }
			.post-type-reply #the-list tr.featured { background-color: #dfd; }
			.post-type-reply #the-list tr.buried { opacity: 0.5; }
		</style>
		<?php
	}


	public function ajax() {

		if ( ! isset( $_POST['do'] ) ) {
			die();
		}

		$action = $_POST['do'];

		$actions = array_keys( self::$actions );

		if ( in_array( $action, $actions ) ) {

			$reply_id = absint( $_POST['reply_id'] );

			// Verify reply exists
			if ( ! $reply = bbp_get_reply( $reply_id ) ) {
				die();
			}

			// Verify whether user has the capability to moderate it
			if ( ! current_user_can( 'feature_replies', $reply_id ) ) {
				die();
			}

			// TAKE ACTION!
			switch ( $action ) {

				case 'feature':
					add_post_meta( $reply_id, 'featured', '1' );
					break;

				case 'unfeature':
					delete_post_meta( $reply_id, 'featured' );
					break;

				case 'bury':
					add_post_meta( $reply_id, 'buried', '1' );
				break;

				case 'unbury':
					delete_post_meta( $reply_id, 'buried' );
				break;

			}
		}

		die();

	}

	public function add_reply_actions( $content, $reply_id ) {
		if ( is_admin() || ! current_user_can( 'feature_replies', $reply_id ) ) {
			return $content;
		}

		$reply = bbp_get_reply( $reply_id );

		// Check if reply exists, and verify that reply isn't the main topic
		if ( ! $reply || $reply_id == $reply->post_parent ) {
			return $content;
		}

		$data_id    = ' data-reply_id=' . $reply_id;

		$current_status = implode( ' ', $this->reply_class( array(), $reply_id ) );

		$o = '<div class="feature-bury-replies">';

		foreach ( self::$actions as $action => $label ) {
			$o .= "<a class='featured-replies {$current_status} {$action}' data-do='{$action}' {$data_id} title='{$label}'>{$label}</a> ";
		}

		$o .= '</div>';

		return $content . $o;

	}

	/**
	 * Reply Row actions
	 *
	 * Add featured replies action links to replies
	 *
	 * @since 0.1.0
	 *
	 * @param array $actions Actions
	 * @param array $reply Reply object
	 * @return array $actions Actions
	 */
	public function reply_row_actions( $actions, $reply ) {

		// Bail if we're not editing replies
		if ( bbp_get_reply_post_type() != get_current_screen()->post_type ) {
			return $actions;
		}

		// Only show the actions if the user is capable of viewing them :)
		if ( current_user_can( 'feature_replies', $reply->ID ) ) {

			$data_id = ' data-reply_id=' . $reply->ID;

			$current_status = implode( ' ', self::reply_class( array(), $reply->ID ) );

			$o = '';
			$o .= "<a data-do='unfeature' {$data_id} class='featured-replies unfeature {$current_status}' title='" . esc_attr__( 'Unfeature this comment', 'bbp-featured-replies' ) . "'>" . __( 'Unfeature', 'bbp-featured-replies' ) . '</a>';
			$o .= "<a data-do='feature' {$data_id} class='featured-replies feature {$current_status}' title='" . esc_attr__( 'Feature this comment', 'bbp-featured-replies' ) . "'>" . __( 'Feature', 'bbp-featured-replies' ) . '</a>';
			$o .= ' | ';
			$o .= "<a data-do='unbury' {$data_id} class='featured-replies unbury {$current_status}' title='" . esc_attr__( 'Unbury this comment', 'bbp-featured-replies' ) . "'>" . __( 'Unbury', 'bbp-featured-replies' ) . '</a>';
			$o .= "<a data-do='bury' {$data_id}  class='featured-replies bury {$current_status}' title='" . esc_attr__( 'Bury this comment', 'bbp-featured-replies' ) . "'>" . __( 'Bury', 'bbp-featured-replies' ) . '</a>';

			$o = "<span class='$current_status'>$o</span>";

			$actions['featured_replies'] = $o;
		}

		return $actions;

	}

	public function add_reply_meta_box() {

		add_meta_box( 'reply_meta_box', __( 'Featured Replies', 'bbp-featured-replies' ), array( $this, 'reply_meta_box' ), bbp_get_reply_post_type(), 'normal' );
	}

	public function save_meta_box_postdata( $reply_id ) {

		if ( ! isset( $_POST['featured_replies_nonce'] ) || ! wp_verify_nonce( $_POST['featured_replies_nonce'], plugin_basename( __FILE__ ) ) ) {
			return;
		}

		if ( ! current_user_can( 'feature_replies', $reply_id ) ) {
			wp_die( __( 'You are not allowed to moderate this reply.', 'bbp-featured-replies' ) );
		}

		// Handle feature
		if ( isset( $_POST['featured'] ) ) {
			update_post_meta( $reply_id, 'featured', '1' );
		} else {
			delete_post_meta( $reply_id, 'featured' );
		}

		// Handle bury
		if ( isset( $_POST['buried'] ) ) {
			update_post_meta( $reply_id, 'buried', '1' );
		} else {
			delete_post_meta( $reply_id, 'buried' );
		}

	}

	public function reply_meta_box( $post ) {

		// Bail if post isn't set
		if ( ! $post ) {
			return;
		}

		// Non-moderator topic authors shouldn't be in the admin, so we only check for 'moderate' cap here
		if ( ! current_user_can( 'moderate' ) ) {
			return;
		}

		echo '<p>';
			echo wp_nonce_field( plugin_basename( __FILE__ ), 'featured_replies_nonce' );

			echo '<input id = "featured" type="checkbox" name="featured" value="true"' . checked( true, self::is_reply_featured( $post->ID ), false ) . '/>';
			echo ' <label for="featured">' . __( "Featured", 'bbp-featured-replies' ) . '</label>&nbsp;';
			echo '<input id = "buried" type="checkbox" name="buried" value="true"' . checked( true, self::is_reply_buried( $post->ID ), false ) . '/>';
			echo ' <label for="buried">' . __( "Buried", 'bbp-featured-replies' ) . '</label>';
		echo '</p>';

	}

	public function reply_class( $classes = array(), $reply_id ) {

		if ( self::is_reply_featured( $reply_id ) ) {
			$classes[] = 'featured';
		}

		if ( self::is_reply_buried( $reply_id ) ) {
			$classes[] = 'buried';
		}

		return $classes;

	}

	private static function is_reply_featured( $reply_id ) {

		return '1' == get_post_meta( $reply_id, 'featured', true );
	}

	private static function is_reply_buried( $reply_id ) {

		return '1' == get_post_meta( $reply_id, 'buried', true );
	}

	public function map_feature_cap( $allcaps, $caps, $args, $user ) {

		if ( 'feature_replies' == $args[0] ) {

			// All moderators should have this cap
			if ( isset( $allcaps['moderate'] ) ) {
				$allcaps[ $caps[0] ] = true;
			}

			// Get reply topic id
			$topic_id        = bbp_get_reply_topic_id( absint( $args[2] ) );
			$topic_author_id = bbp_get_topic_author_id( $topic_id );

			// Check if the user authored the reply, and give them the cap if so
			if ( $user->ID == $topic_author_id ) {
				$allcaps[ $caps[0] ] = true;
			}

		}

		return $allcaps;
	}

}

function wp_featured_replies_load() {

	return Featured_Replies::instance();
}

// Register activation hook
register_activation_hook( __FILE__, array( 'Featured_Replies', 'activation_check' ) );

// load bbPress - Featured Replies
add_action( 'plugins_loaded', 'wp_featured_replies_load' );
