<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class cf7_to_wp {

	/**
	 * The single instance of cf7_to_wp.
	 * @var 	object
	 * @access  private
	 * @since 	1.0.0
	 */
	private static $_instance = null;

	/**
	 * Settings class object
	 * @var     object
	 * @access  public
	 * @since   1.0.0
	 */
	public $settings = null;

	/**
	 * The version number.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_version;

	/**
	 * The token.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_token;

	/**
	 * The main plugin file.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $file;

	/**
	 * The main plugin directory.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $dir;

	/**
	 * The plugin assets directory.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $assets_dir;

	/**
	 * The plugin assets URL.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $assets_url;

	/**
	 * Suffix for Javascripts.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $script_suffix;

	/**
	 * Constructor function.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function __construct ( $file = '', $version = '0.1' ) {
		$this->_version = $version;
		$this->_token = 'cf7_to_wp';

		// Load plugin environment variables
		$this->file = $file;
		$this->dir = dirname( $this->file );
		$this->assets_dir = trailingslashit( $this->dir ) . 'assets';
		$this->assets_url = esc_url( trailingslashit( plugins_url( '/assets/', $this->file ) ) );

		$this->script_suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		register_activation_hook( $this->file, array( $this, 'install' ) );

		add_action('init', array( $this, 'register_form_msg_post_type' ) );
		add_action('admin_head', array( $this, 'add_form_msg_menu_icon' ) );
		add_filter('add_menu_classes', array( $this, 'menu_msg_form_bubble' ) );

		// Hook into CF7 actions
		add_action('wpcf7_add_meta_boxes', array( $this, 'add_cf7_form_metabox' ), 50, 1 );
		add_action('wpcf7_after_save', array( $this, 'save_cf7_data' ), 50, 1 );
		add_action('wpcf7_mail_sent', array( $this, 'create_post_on_form_submission' ), 50, 1 );
		add_action('wpcf7_mail_failed', array( $this, 'create_post_on_form_submission' ), 50, 1 );


		// Handle localisation
		$this->load_plugin_textdomain();
		add_action( 'init', array( $this, 'load_localisation' ), 0 );
	} // End __construct ()


	/**
	 * Load plugin localisation
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function load_localisation () {
		load_plugin_textdomain( 'cf7_to_wp', false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_localisation ()

	/**
	 * Load plugin textdomain
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function load_plugin_textdomain () {
	    $domain = 'cf7_to_wp';

	    $locale = apply_filters( 'plugin_locale', get_locale(), $domain );

	    load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
	    load_plugin_textdomain( $domain, false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_plugin_textdomain ()

	public function register_form_msg_post_type() {
		register_post_type( 'form_msg',
			array(
				'labels' => array(
					'name' => __( 'Messages', 'cf7_to_wp'),
					'singular_name' => __( 'Message', 'cf7_to_wp'),
					'add_new' => __( 'Add New', 'cf7_to_wp' ),
					'add_new_item' => __( 'Add new message', 'cf7_to_wp'),
					'edit' => __( 'Edit', 'cf7_to_wp' ),
				),
				'menu_position' => 32,
				'show_ui' => true,
				'show_in_menu' => true,
				'public' => false,
				'supports' => array(
					'title',
					'editor',
				),
			)
		);
	}

	public function add_form_msg_menu_icon(){
		?><style>#menu-posts-form_msg div.wp-menu-image:before { content: '\f125'; }</style><?php
	}

	public function add_cf7_form_metabox($post_id) {
		add_meta_box(
			'cf7_to_wp',
			__( 'Save each form submission to a post', 'cf7_to_wp' ),
			array( $this, 'cf7_to_wp_form_metabox' ),
			null,
			'mail',
			'core'
		);
	}

	public function cf7_to_wp_form_metabox($post) {
		$id = $post->id();
		$cf7towp = get_post_meta($id, '_cf7towp', true); ?>

		<p class="description" style="margin-bottom:1em;">
			<?php _e('If enabled, this addon will automagically save this form submissions into a new WordPress "Messages" post.', 'cf7_to_wp'); ?>
		</p>

		<div class="mail-field">
			<label for="cf7towp-active">
				<input type="checkbox" id="cf7towp-active" name="wpcf7-cf7towp-active" value="1" <?php checked($cf7towp['active'], 1); ?> />
				<?php echo esc_html( __( 'Should we save this form submissions to WordPress posts ?', 'cf7_to_wp' ) ); ?>
			</label>
		</div>

		<div class="pseudo-hr"></div>

		<div class="mail-field">
			<label for="cf7towp-title"><?php echo esc_html( __( 'Post title', 'cf7_to_wp' ) ); ?></label><br />
			<input type="text" id="cf7towp-title" name="wpcf7-cf7towp-title" class="wide" value="<?php echo esc_attr( $cf7towp['title'] ); ?>" />
		</div>

		<div class="mail-field">
			<label for="cf7towp-content">
				<?php echo esc_html( __( 'Post content', 'cf7_to_wp' ) ); ?></label><br />
				<textarea id="cf7towp-content" name="wpcf7-cf7towp-content" cols="100" rows="10"><?php echo esc_attr( $cf7towp['content'] ); ?></textarea>
		</div>

		<p class="description" style="margin-top:.25em;">
			<span style="float:left; width:75%;"><?php _e('Use the usual CF7 [tags] to populate the post title and post content.', 'cf7_to_wp'); ?></span>
			<span style="text-align:right; float:right; width:25%;"><?php _e('An CF7 addon by', 'cf7_to_wp'); ?> <a target="_blank" href="http://mosaika.fr">Mosaika</a></span>
			<hr>
		</p>
	<?php }

	public function save_cf7_data($contact_form) {
		$id = $contact_form->id();

		$cf7towp = array();

		$cf7towp['active'] = !empty($_POST['wpcf7-cf7towp-active']);

		if ( isset( $_POST['wpcf7-cf7towp-title'] ) ) {
			$cf7towp['title'] = trim($_POST['wpcf7-cf7towp-title']);
		}

		if ( isset( $_POST['wpcf7-cf7towp-content'] ) ) {
			$cf7towp['content'] = trim($_POST['wpcf7-cf7towp-content']);
		}

		update_post_meta($id, '_cf7towp', $cf7towp);
	}

	public function create_post_on_form_submission($cf) {
		$form_post = $cf->id();
		$cf7towp_data = get_post_meta($form_post, '_cf7towp', true);

		if ($cf7towp_data['active']) {
			$submission = WPCF7_Submission::get_instance();

			if ($submission) {
				$meta = array();
				$meta['ip'] = $submission->get_meta('remote_ip');
				$meta['ua'] = $submission->get_meta('user_agent');
				$meta['url'] = $submission->get_meta('url');
				$meta['date'] =  date_i18n( get_option( 'date_format' ), $submission->get_meta('timestamp'));
				$meta['time'] =  date_i18n( get_option( 'time_format' ), $submission->get_meta('timestamp'));
			}

			$post_title_template = $cf7towp_data['title'];
			$post_content_template = $cf7towp_data['content'];

			$post_title = wpcf7_mail_replace_tags(
				$post_title_template,
				array(
					'html' => true,
					'exclude_blank' => true
				)
			);

			$post_content = wpcf7_mail_replace_tags(
				$post_content_template,
				array(
					'html' => true,
					'exclude_blank' => true
				)
			);

			$new_form_msg = wp_insert_post(
				array(
					'post_type' => 'form_msg',
					'post_title' => $post_title,
					'post_content' => $post_content
				)
			);

			if ($submission) {
				update_post_meta($new_form_msg, 'cf7towp_meta', $meta);
			}
		}
	}

	public function menu_msg_form_bubble($menu) {
		$pending_count = wp_count_posts('form_msg')->draft;

		foreach($menu as $menu_key => $menu_data) {
			if ('edit.php?post_type=form_msg' != $menu_data[2])
				continue;

			$menu[$menu_key][0] .= " <span class='update-plugins count-$pending_count'><span class='plugin-count'>" . number_format_i18n($pending_count) . '</span></span>';
		}

		return $menu;
	}

	/**
	 * Main cf7_to_wp Instance
	 *
	 * Ensures only one instance of cf7_to_wp is loaded or can be loaded.
	 *
	 * @since 0.1
	 * @static
	 * @see cf7_to_wp()
	 * @return Main cf7_to_wp instance
	 */
	public static function instance ( $file = '', $version = '1.0.0' ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $file, $version );
		}
		return self::$_instance;
	} // End instance ()

	/**
	 * Cloning is forbidden.
	 *
	 * @since 0.1
	 */
	public function __clone () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->_version );
	} // End __clone ()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 0.1
	 */
	public function __wakeup () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->_version );
	} // End __wakeup ()

	/**
	 * Installation. Runs on activation.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function install () {
		$this->_log_version_number();
	} // End install ()

	/**
	 * Log the plugin version number.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	private function _log_version_number () {
		update_option( $this->_token . '_version', $this->_version );
	} // End _log_version_number ()

}
