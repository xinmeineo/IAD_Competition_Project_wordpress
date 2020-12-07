<?php
/**
 * Class LiveChatAdmin
 *
 * @package LiveChat
 */

namespace LiveChat;

use Exception;
use LiveChat\Helpers\ConfirmIdentityNoticeHelper;
use LiveChat\Helpers\ConnectNoticeHelper;
use LiveChat\Helpers\ConnectServiceHelper;
use LiveChat\Helpers\DeactivationFeedbackFormHelper;
use LiveChat\Helpers\ReviewNoticeHelper;
use LiveChat\Helpers\ResourcesTabHelper;
use LiveChat\Services\ApiClient;
use LiveChat\Services\CertProvider;
use LiveChat\Services\ConnectTokenProvider;
use LiveChat\Services\Store;
use LiveChat\Services\TokenValidator;
use LiveChat\Services\User;
use WP_Error;

/**
 * Class LiveChatAdmin
 *
 * @package LiveChat
 */
final class LiveChatAdmin extends LiveChat {
	/**
	 * Returns true if "Advanced settings" form has just been submitted,
	 * false otherwise
	 *
	 * @var bool
	 */
	private $changes_saved = false;

	/**
	 * Timestamp from which review notice timeout count starts from
	 *
	 * @var int
	 */
	private $review_notice_start_timestamp = null;

	/**
	 * Timestamp offset
	 *
	 * @var int
	 */
	private $review_notice_start_timestamp_offset = null;

	/**
	 * Returns true if review notice was dismissed
	 *
	 * @var bool
	 */
	private $review_notice_dismissed = false;

	/**
	 * Starts the plugin
	 */
	public function __construct() {
		parent::__construct();

		add_action( 'admin_init', array( $this, 'load_translations' ) );
		add_action( 'admin_init', array( $this, 'load_menu_icon_styles' ) );
		add_action( 'admin_init', array( $this, 'load_general_scripts_and_styles' ) );
		add_action( 'admin_init', array( $this, 'inject_nonce_object' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'current_screen', array( $this, 'show_deactivation_feedback_form' ) );
		add_action( 'activated_plugin', array( $this, 'plugin_activated_action_handler' ) );

		add_filter( 'auto_update_plugin', array( $this, 'auto_update' ), 10, 2 );

		$this->register_admin_notices();
		$this->register_ajax_actions();
	}

	/**
	 * Enables auto-update for LC plugin.
	 *
	 * @param bool   $update Default WP API response.
	 * @param object $item Plugin's slug.
	 * @return bool
	 */
	public function auto_update( $update, $item ) {
		return PLUGIN_SLUG === $item->slug ? true : $update;
	}

	/**
	 * Returns instance of LiveChat class (singleton pattern).
	 *
	 * @return LiveChat
	 * @throws Exception Can be thrown by constructor.
	 */
	public static function get_instance() {
		if ( ! isset( static::$instance ) ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Make translation ready
	 */
	public function load_translations() {
		load_plugin_textdomain(
			'wp-live-chat-software-for-wordpress',
			false,
			'wp-live-chat-software-for-wordpress/languages'
		);
	}

	/**
	 * Fix CSS for icon in menu
	 */
	public function load_menu_icon_styles() {
		wp_enqueue_style( 'livechat-menu', $this->module->get_plugin_url() . 'css/livechat-menu.css', false, $this->module->get_plugin_version() );
	}

	/**
	 * Returns timestamp of review notice first occurrence.
	 *
	 * @return int
	 */
	private function get_review_notice_start_timestamp() {
		if ( is_null( $this->review_notice_start_timestamp ) ) {
			$timestamp = get_option( 'livechat_review_notice_start_timestamp' );
			// If timestamp was not set on install.
			if ( ! $timestamp ) {
				$timestamp = time();
				update_option( 'livechat_review_notice_start_timestamp', $timestamp ); // Set timestamp if not set on install.
			}

			$this->review_notice_start_timestamp = $timestamp;
		}

		return $this->review_notice_start_timestamp;
	}

	/**
	 * Returns timestamp offset of review notice occurrence.
	 *
	 * @return int
	 */
	private function get_review_notice_start_timestamp_offset() {
		if ( is_null( $this->review_notice_start_timestamp_offset ) ) {
			$offset = get_option( 'livechat_review_notice_start_timestamp_offset' );
			// If offset was not set on install.
			if ( ! $offset ) {
				$offset = 16;
				update_option( 'livechat_review_notice_start_timestamp_offset', $offset ); // Set shorter offset.
			}

			$this->review_notice_start_timestamp_offset = $offset;
		}

		return $this->review_notice_start_timestamp_offset;
	}

	/**
	 * Checks if review was dismissed.
	 *
	 * @return bool
	 */
	private function check_if_review_notice_was_dismissed() {
		if ( ! $this->review_notice_dismissed ) {
			$this->review_notice_dismissed = get_option( 'livechat_review_notice_dismissed' );
		}

		return $this->review_notice_dismissed;
	}

	/**
	 * Loads CSS.
	 */
	private function load_design_system_styles() {
		// phpcs:disable WordPress.WP.EnqueuedResourceParameters.MissingVersion
		// Files below don't need to be versioned.
		wp_register_style( 'livechat-source-sans-pro-font', 'https://fonts.googleapis.com/css?family=Source+Sans+Pro:400,600' );
		wp_register_style( 'livechat-material-icons', 'https://fonts.googleapis.com/icon?family=Material+Icons' );
		wp_register_style( 'livechat-design-system', 'https://cdn.livechat-static.com/design-system/styles.css' );
		wp_enqueue_style( 'livechat-source-sans-pro-font', false, $this->module->get_plugin_version() );
		wp_enqueue_style( 'livechat-material-icons', false, $this->module->get_plugin_version() );
		wp_enqueue_style( 'livechat-design-system', false, $this->module->get_plugin_version() );
		// phpcs:enable
	}

	/**
	 * Loads JS scripts and CSS.
	 */
	public function load_general_scripts_and_styles() {
		$this->load_design_system_styles();
		wp_enqueue_script( 'livechat', $this->module->get_plugin_url() . 'js/livechat.js', 'jquery', $this->module->get_plugin_version(), true );
		wp_enqueue_style( 'livechat', $this->module->get_plugin_url() . 'css/livechat-general.css', false, $this->module->get_plugin_version() );
		wp_enqueue_script( 'bridge', $this->module->get_plugin_url() . 'js/connect.js', 'jquery', $this->module->get_plugin_version(), false );

		$config = array(
			'agentAppUrl' => LC_AA_URL,
		);

		wp_localize_script( 'livechat', 'config', $config );
	}

	/**
	 * Adds nonce value to AJAX object in JS script.
	 */
	public function inject_nonce_object() {
		$nonce = array(
			'value' => wp_create_nonce( 'wp_ajax_lc_connect' ),
		);

		wp_localize_script( 'bridge', 'ajax_nonce', $nonce );
	}

	/**
	 * Loads scripts and CSS for review modal.
	 */
	public function load_review_scripts_and_styles() {
		wp_enqueue_script( 'livechat-review', $this->module->get_plugin_url() . 'js/livechat-review.js', 'jquery', $this->module->get_plugin_version(), true );
		wp_enqueue_style( 'livechat-review', $this->module->get_plugin_url() . 'css/livechat-review.css', false, $this->module->get_plugin_version() );
	}

	/**
	 * Adds LiveChat to WP administrator menu.
	 */
	public function register_admin_menu() {
		add_menu_page(
			'LiveChat',
			$this->is_installed() ? 'LiveChat' : 'LiveChat <span class="awaiting-mod">!</span>',
			'administrator',
			'livechat',
			array( $this, 'livechat_settings_page' ),
			$this->module->get_plugin_url() . 'images/livechat-icon.svg'
		);

		add_submenu_page(
			'livechat',
			__( 'Settings', 'wp-live-chat-software-for-wordpress' ),
			__( 'Settings', 'wp-live-chat-software-for-wordpress' ),
			'administrator',
			'livechat_settings',
			array( $this, 'livechat_settings_page' )
		);

		add_submenu_page(
			'livechat',
			__( 'Resources', 'wp-live-chat-software-for-wordpress' ),
			__( 'Resources', 'wp-live-chat-software-for-wordpress' ),
			'administrator',
			'livechat_resources',
			array( $this, 'livechat_resources_page' )
		);

		// Remove the submenu that is automatically added.
		if ( function_exists( 'remove_submenu_page' ) ) {
			remove_submenu_page( 'livechat', 'livechat' );
		}

		// Settings link.
		add_filter( 'plugin_action_links', array( $this, 'livechat_settings_link' ), 10, 2 );

		if ( $this->has_user_token() ) {
			add_submenu_page(
				'livechat',
				__( 'Go to LiveChat', 'wp-live-chat-software-for-wordpress' ),
				__( 'Go to LiveChat', 'wp-live-chat-software-for-wordpress' ),
				'administrator',
				'livechat_link',
				'__return_false'
			);

			add_filter( 'clean_url', array( $this, 'go_to_livechat_link' ), 10, 2 );
		}
	}

	/**
	 * Displays settings page
	 */
	public function livechat_settings_page() {
		ConnectServiceHelper::create()->render();
	}

	/**
	 * Displays resources page
	 */
	public function livechat_resources_page() {
		ResourcesTabHelper::create()->render();
	}

	/**
	 * Opens Agent App in new tab
	 *
	 * @param string $current_url URL of current menu page.
	 *
	 * @return string
	 */
	public function go_to_livechat_link( $current_url ) {
		if ( 'admin.php?page=livechat_link' === $current_url ) {
			$current_url = LC_AA_URL;
		}

		return $current_url;
	}

	/**
	 * Returns flag changes_saved.
	 *
	 * @return bool
	 */
	public function changes_saved() {
		return $this->changes_saved;
	}

	/**
	 * Returns link to LiveChat setting page.
	 *
	 * @param array  $links Array with links.
	 * @param string $file File name.
	 *
	 * @return mixed
	 */
	public function livechat_settings_link( $links, $file ) {
		if ( basename( $file ) !== 'livechat.php' ) {
			return $links;
		}

		$settings_link = sprintf( '<a href="admin.php?page=livechat_settings">%s</a>', __( 'Settings' ) );
		array_unshift( $links, $settings_link );
		return $links;
	}


	/**
	 * Checks if review notice should be visible.
	 *
	 * @return bool
	 */
	private function check_review_notice_conditions() {
		if (
			$this->is_installed() &&
			! $this->check_if_review_notice_was_dismissed() &&
			$this->get_time_since_connection() >= $this->get_offset_time() &&
			$this->check_if_license_is_active()
		) {
			return true;
		}

		return false;
	}

	/**
	 * Returns offset time in seconds.
	 *
	 * @return int
	 */
	private function get_offset_time() {
		return 60 * 60 * 24 * $this->get_review_notice_start_timestamp_offset();
	}

	/**
	 * Returns time in seconds since plugin conneciton.
	 *
	 * @return int
	 */
	private function get_time_since_connection() {
		return time() - $this->get_review_notice_start_timestamp();
	}

	/**
	 * Registers actions required for connect notice
	 *
	 * @return void
	 */
	public function register_connect_notice() {
		if ( $this->is_installed() || 'livechat_page_livechat_settings' === get_current_screen()->id ) {
			return;
		}

		if ( $this->has_license_number() ) {
			add_action( 'admin_notices', array( $this, 'show_confirm_identity_notice' ) );
			return;
		}

		add_action( 'admin_notices', array( $this, 'show_connect_notice' ) );
	}

	/**
	 * Checks if any notice should be displayed.
	 */
	private function register_admin_notices() {
		if ( $this->check_review_notice_conditions() ) {
			add_action( 'admin_init', array( $this, 'load_review_scripts_and_styles' ) );
			add_action( 'wp_ajax_lc_review_dismiss', array( $this, 'ajax_review_dismiss' ) );
			add_action( 'wp_ajax_lc_review_postpone', array( $this, 'ajax_review_postpone' ) );
			add_action( 'admin_notices', array( $this, 'show_review_notice' ) );
		}

		add_action( 'current_screen', array( $this, 'register_connect_notice' ) );
	}

	/**
	 * Checks if LiveChat license is active.
	 *
	 * @return boolean
	 */
	private function check_if_license_is_active() {
		try {
			$store         = Store::get_instance();
			$connect_token = ConnectTokenProvider::create( CertProvider::create() )->get( $store->get_store_token(), 'store' );
			$result        = ApiClient::create( $connect_token )->license_info();

			return array_key_exists( 'isActive', $result ) ? $result['isActive'] : false;
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Checks if current WP user has LC account.
	 *
	 * @return bool
	 */
	private function has_user_token() {
		return ! empty( User::get_instance()->get_current_user_token() );
	}

	/**
	 * Shows review notice.
	 */
	public function show_review_notice() {
		( new ReviewNoticeHelper() )->render();
	}

	/**
	 * Shows connect notice.
	 */
	public function show_connect_notice() {
		( new ConnectNoticeHelper() )->render();
	}

	/**
	 * Shows confirm identity notice.
	 */
	public function show_confirm_identity_notice() {
		( new ConfirmIdentityNoticeHelper() )->render();
	}

	/**
	 * Shows deactivation feedback form.
	 *
	 * @return void;
	 */
	public function show_deactivation_feedback_form() {
		if ( get_current_screen()->id !== 'plugins' ) {
			return;
		}

		add_action( 'in_admin_header', array( new DeactivationFeedbackFormHelper(), 'render' ) );
	}

	/**
	 * Marks review as dismissed in WP options.
	 */
	public function ajax_review_dismiss() {
		update_option( 'livechat_review_notice_dismissed', true );
		echo 'OK';
		wp_die();
	}

	/**
	 * Marks review as postponed in WP options.
	 */
	public function ajax_review_postpone() {
		update_option( 'livechat_review_notice_start_timestamp', time() );
		update_option( 'livechat_review_notice_start_timestamp_offset', 7 );
		echo 'OK';
		wp_die();
	}

	/**
	 * Connects WP plugin with LiveChat account.
	 * Validates tokens and, if they are valid, stores them in WP database.
	 */
	public function ajax_connect() {
		$user_token  = null;
		$store_token = null;

		check_ajax_referer( 'wp_ajax_lc_connect', 'security' );

		if ( isset( $_POST['user_token'] ) && isset( $_POST['store_token'] ) && isset( $_POST['security'] ) ) {
			$user_token  = sanitize_text_field( wp_unslash( $_POST['user_token'] ) );
			$store_token = sanitize_text_field( wp_unslash( $_POST['store_token'] ) );
		}

		try {
			TokenValidator::create( CertProvider::create() )->validate_tokens( $user_token, $store_token );
			User::get_instance()->authorize_current_user( $user_token );
			Store::get_instance()->authorize_store( $store_token );
			delete_option( 'livechat_license_number' );
			delete_option( 'livechat_email' );
			delete_option( 'livechat_disable_mobile' );
			delete_option( 'livechat_disable_guests' );

			wp_send_json_success( array( 'status' => 'ok' ) );
		} catch ( Exception $e ) {
			wp_send_json_error(
				new WP_Error( $e->getCode(), $e->getMessage() )
			);
		}
	}

	/**
	 * Disconnects LC plugin from current license.
	 */
	public function ajax_disconnect() {
		Store::get_instance()->remove_store_data();
		User::get_instance()->remove_authorized_users();
	}

	/**
	 * Removes store token when it was not found in LiveChat service.
	 */
	public function ajax_store_not_found() {
		Store::get_instance()->remove_store_data();
		User::get_instance()->remove_authorized_users();
	}

	/**
	 * Removes current user token when it was not found in LiveChat service.
	 */
	public function ajax_user_not_found() {
		User::get_instance()->remove_current_user_token();
	}

	/**
	 * Registers plugin's AJAX actions
	 */
	public function register_ajax_actions() {
		add_action( 'wp_ajax_lc_connect', array( $this, 'ajax_connect' ) );
		add_action( 'wp_ajax_lc_disconnect', array( $this, 'ajax_disconnect' ) );
		add_action( 'wp_ajax_lc_store_not_found', array( $this, 'ajax_store_not_found' ) );
		add_action( 'wp_ajax_lc_user_not_found', array( $this, 'ajax_user_not_found' ) );
	}

	/**
	 * Handles plugin activated action - redirects to plugin setting page.
	 *
	 * @param string $plugin Plugin slug.
	 */
	public function plugin_activated_action_handler( $plugin ) {
		if ( 'wp-live-chat-software-for-wordpress/livechat.php' !== $plugin ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=livechat_settings' ) );
		exit;
	}

	/**
	 * Removes all LiveChat data stored in WP database.
	 * It's called as uninstall hook.
	 */
	public static function uninstall_hook_handler() {
		$store = Store::get_instance();

		if ( ! empty( $store->get_store_token() ) ) {
			try {
				$connect_token = ConnectTokenProvider::create( CertProvider::create() )->get( $store->get_store_token(), 'store' );
				ApiClient::create( $connect_token )->uninstall();
				// phpcs:disable Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			} catch ( Exception $exception ) {
				// Exception during uninstall request is ignored to not break process of plugin uninstallation.
			}
			// phpcs:enable

			$store->remove_store_data();
		}

		User::get_instance()->remove_authorized_users();
		CertProvider::create()->remove_stored_cert();

		delete_option( 'livechat_review_notice_start_timestamp' );
		delete_option( 'livechat_review_notice_start_timestamp_offset' );
	}
}
