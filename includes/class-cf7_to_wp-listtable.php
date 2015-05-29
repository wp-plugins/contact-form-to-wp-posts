<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Messages_List extends WP_List_Table {

	public function __construct() {

		parent::__construct( [
			'singular' => __( 'Form Message', 'cf7_to_wp' ), //singular name of the listed records
			'plural'   => __( 'Messages', 'cf7_to_wp' ), //plural name of the listed records
			'ajax'     => false //does this table support ajax?
		] );

		add_action( 'admin_head', array( &$this, 'admin_header' ) );
	}

	/**
	 * Retrieve form_msgs data from the database
	 *
	 * @param int $per_page
	 * @param int $page_number
	 *
	 * @return mixed
	 */
	public static function get_form_msgs( $per_page = 5, $page_number = 1 ) {

		global $wpdb;

		$sql = "SELECT * FROM {$wpdb->prefix}posts WHERE post_type = 'form_msg'";

		if ( ! empty( $_REQUEST['orderby'] ) ) {
			$sql .= ' ORDER BY ' . esc_sql( $_REQUEST['orderby'] );
			$sql .= ! empty( $_REQUEST['order'] ) ? ' ' . esc_sql( $_REQUEST['order'] ) : ' ASC';
		}

		$sql .= " LIMIT $per_page";
		$sql .= ' OFFSET ' . ( $page_number - 1 ) * $per_page;


		$result = $wpdb->get_results( $sql, 'ARRAY_A' );

		return $result;
	}


	/**
	 * Delete a form_msg record.
	 *
	 * @param int $id form_msg ID
	 */
	public static function delete_form_msg( $id ) {
		global $wpdb;

		$wpdb->delete(
			"{$wpdb->prefix}posts",
			[ 'ID' => $id ],
			[ '%d' ]
		);
	}


	/**
	 * Returns the count of records in the database.
	 *
	 * @return null|string
	 */
	public static function record_count() {
		global $wpdb;

		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}form_msgs";

		return $wpdb->get_var( $sql );
	}


	/** Text displayed when no form_msg data is available */
	public function no_items() {
		_e('No contact form messages found yet !', 'cf7_to_wp' );
	}


	/**
	 * Render the bulk edit checkbox
	 *
	 * @param array $item
	 *
	 * @return string
	 */
	function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="bulk-delete[]" value="%s" />', $item['ID']
		);
	}


	/**
	 * Method for name column
	 *
	 * @param array $item an array of DB data
	 *
	 * @return string
	 */
	function column_name( $item ) {

		$delete_nonce = wp_create_nonce( 'cf7_to_wp_delete_form_msg' );

		$title = '<strong>' . $item['name'] . '</strong>';

		$actions = [
			'delete' => sprintf( '<a href="?page=%s&action=%s&form_msg=%s&_wpnonce=%s">Delete</a>', esc_attr( $_REQUEST['page'] ), 'delete', absint( $item['ID'] ), $delete_nonce )
		];

		return $title . $this->row_actions( $actions );
	}


	/**
	 *  Associative array of columns
	 *
	 * @return array
	 */
	function get_columns() {
		$columns = [
			'cb'      => '<input type="checkbox" />',
			'title_content'    => __('Title & content', 'cf7_to_wp' ),
			'date' => __( 'Submitted on', 'cf7_to_wp' ),
			'meta' => __( 'Info', 'cf7_to_wp' ),
		];

		return $columns;
	}


	/**
	 * Render a column when no column specific method exist.
	 *
	 * @param array $item
	 * @param string $column_name
	 *
	 * @return mixed
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'title_content':
				$content = '<strong class="row-title">' . $item['post_title'] . '</strong>';
				$content .= '<div class="row-actions"><span class="read"><a href="#" title="' . __('Read message', 'cf7_to_wp') . '">' . __('Read message', 'cf7_to_wp') . '</a></span></div>';
				$content .= '<div class="cf7towp-message">' . $item['post_content'] . '</div>';

				return $content;
				break;

			case 'date':
				$meta = get_post_meta($item['ID'], 'cf7towp_meta', true);
				if (is_array($meta) && array_key_exists('date', $meta) && array_key_exists('time', $meta))
					return sprintf('%1$s @ %2$s', $meta['date'], $meta['time']);
				else
					return __('Unknown', 'cf7_to_wp');
				break;

			case 'meta':
				$meta = get_post_meta($item['ID'], 'cf7towp_meta', true);
				$content = '';

				if (is_array($meta)) {
					$content .= '<ul class="cf7towp-meta">';

					if (array_key_exists('ip', $meta))
						$content .= '<li><strong>' . __('IP address', 'cf7_to_wp') . ' :</strong> '. $meta['ip'] . '</li>';

					if (array_key_exists('ua', $meta))
						$content .= '<li><strong>' . __('User agent', 'cf7_to_wp') . ' :</strong> '. $meta['ua'] . '</li>';

					if (array_key_exists('url', $meta))
						$content .= '<li><strong>' . __('Origin', 'cf7_to_wp') . ' :</strong> <a href="' . esc_url($meta['url']) . '" target="_blank">'. $meta['url'] . '</a></li>';
				}

				return $content;

				break;
			default:
				return print_r( $item, true );
		}
	}



	/**
	 * Returns an associative array containing the bulk action
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		$actions = [
			'bulk-delete' => 'Delete'
		];

		return $actions;
	}


	/**
	 * Handles data query and filter, sorting, and pagination.
	 */
	public function prepare_items() {

		$this->_column_headers = $this->get_column_info();

		/** Process bulk action */
		$this->process_bulk_action();

		$per_page     = $this->get_items_per_page( 'form_msgs_per_page', 5 );
		$current_page = $this->get_pagenum();
		$total_items  = self::record_count();

		$this->set_pagination_args( [
			'total_items' => $total_items, //WE have to calculate the total number of items
			'per_page'    => $per_page //WE have to determine how many items to show on a page
		] );

		$this->items = self::get_form_msgs( $per_page, $current_page );
	}

	public function process_bulk_action() {

		//Detect when a bulk action is being triggered...
		if ( 'delete' === $this->current_action() ) {

			// In our file that handles the request, verify the nonce.
			$nonce = esc_attr( $_REQUEST['_wpnonce'] );

			if ( ! wp_verify_nonce( $nonce, 'cf7_to_wp_delete_form_msg' ) ) {
				die( 'Ble, noh.' );
			}
			else {
				self::delete_form_msg( absint( $_GET['form_msg'] ) );

				wp_redirect( esc_url( add_query_arg() ) );
				exit;
			}

		}

		// If the delete bulk action is triggered
		if ( ( isset( $_POST['action'] ) && $_POST['action'] == 'bulk-delete' )
			|| ( isset( $_POST['action2'] ) && $_POST['action2'] == 'bulk-delete' )
		) {

			$delete_ids = esc_sql( $_POST['bulk-delete'] );

			// loop over the array of record IDs and delete them
			foreach ( $delete_ids as $id ) {
				self::delete_form_msg( $id );

			}

			wp_redirect( esc_url( add_query_arg() ) );
			exit;
		}
	}


	function admin_header() {
		$page = ( isset($_GET['page'] ) ) ? esc_attr( $_GET['page'] ) : false;
		if ('list_form_msgs' != $page )
			return;

		?>
		<style type="text/css">
		.wp-list-table #title_content { width: 65%; }
		.wp-list-table #date { width: 15%; }
		.wp-list-table #meta { width: 20%; }
		div.cf7towp-message {
			padding:.5em;
			background:#f1f1f1;
			border-radius:3px;
			border:1px solid rgba(0,0,0,.25);
			margin:1em 0;
			display: none;
		}
		ul.cf7towp-meta {
			margin:0;
		}
		ul.cf7towp-meta li {
			font-size:.8em;
			margin-bottom: 0.25em;
		}
		</style>
		<script>
			jQuery(document).ready(function($){
				$('.title_content .row-actions .read a').click(function(e){
					$(this).closest('.title_content').find('.cf7towp-message').slideToggle();
					e.preventDefault();
				});
			});
		</script>
	<?php }

}


class Form_Msg_Table {

	// class instance
	static $instance;

	// form_msg WP_List_Table object
	public $form_msgs_obj;

	// class constructor
	public function __construct() {
		add_filter( 'set-screen-option', [ __CLASS__, 'set_screen' ], 10, 3 );
		add_action( 'admin_menu', [ $this, 'plugin_menu' ] );
	}


	public static function set_screen( $status, $option, $value ) {
		return $value;
	}

	public function plugin_menu() {

		$hook = add_submenu_page(
			'wpcf7',
			__('Contact Form Messages', 'cf7_to_wp'),
			__('Messages', 'cf7_to_wp'),
			'manage_options',
			'list_form_msgs',
			[ $this, 'plugin_settings_page' ]
		);

		add_action( "load-$hook", [ $this, 'screen_option' ] );

	}


	/**
	 * Plugin settings page
	 */
	public function plugin_settings_page() {
		?>
		<div class="wrap">
			<h2><?php _e('Contact Form Messages', 'cf7_to_wp'); ?></h2>

			<div id="poststuff">
				<div id="post-body" class="metabox-holder">
					<div id="post-body-content">
						<div class="meta-box-sortables ui-sortable">
							<form method="post">
								<?php
								$this->form_msgs_obj->prepare_items();
								$this->form_msgs_obj->display(); ?>
							</form>
						</div>
					</div>
				</div>
				<br class="clear">
			</div>
		</div>
	<?php
	}

	/**
	 * Screen options
	 */
	public function screen_option() {

		$option = 'per_page';
		$args   = [
			'label'   => 'Messages',
			'default' => 5,
			'option'  => 'form_msgs_per_page'
		];

		add_screen_option( $option, $args );

		$this->form_msgs_obj = new Messages_List();
	}


	/** Singleton instance */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

}


add_action( 'plugins_loaded', function () {
	Form_Msg_Table::get_instance();
} );
