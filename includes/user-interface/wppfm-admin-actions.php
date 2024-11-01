<?php

/**
 * @package WP Product Feed Manager/User Interface/Functions
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add the feed manager menu in the Admin page
 *
 * @param bool $channel_updated default false
 */
function wppfm_add_feed_manager_menu( $channel_updated = false ) {

	// defines the feed manager menu
	add_menu_page(
		__( 'WP Feed Manager', 'wp-product-feed-manager' ),
		__( 'Feed Manager', 'wp-product-feed-manager' ),
		'manage_woocommerce',
		'wp-product-feed-manager',
		'wppfm_feed_list_page',
		wppfm_get_menu_icon_svg()
	);

	// add the feed editor page
	add_submenu_page(
		'wp-product-feed-manager',
		__( 'Feed Editor', 'wp-product-feed-manager' ),
		__( 'Feed Editor', 'wp-product-feed-manager' ),
		'manage_woocommerce',
		'wppfm-feed-editor-page',
		'wppfm_feed_editor_page'
	);

	// add the settings page
	add_submenu_page(
		'wp-product-feed-manager',
		__( 'Settings', 'wp-product-feed-manager' ),
		__( 'Settings', 'wp-product-feed-manager' ),
		'manage_woocommerce',
		'wppfm-settings-page',
		'wppfm_settings_page'
	);

	// add the support page
	add_submenu_page(
		'wp-product-feed-manager',
		__( 'Support', 'wp-product-feed-manager' ),
		__( 'Support', 'wp-product-feed-manager' ),
		'manage_woocommerce',
		'wppfm-support-page',
		'wppfm_support_page'
	);
}

add_action( 'admin_menu', 'wppfm_add_feed_manager_menu' );

/**
 * Checks if the backups are valid for the current database version and warns the user if not
 *
 * @since 1.9.6
 */
function wppfm_check_backups() {
	if ( ! wppfm_check_backup_status() ) {
		$msg = __( 'Due to an update of your Feed Manager plugin, your feed backups are no longer valid! Please open the Feed Manager Settings page, remove all current backups, and make a new one.', 'wp-product-feed-manager' )
		?>
		<div class="notice notice-warning is-dismissible">
			<p><?php echo $msg; ?></p>
		</div>
		<?php
	}
}

add_action( 'admin_notices', 'wppfm_check_backups' );

/**
 * Sets the global background process
 *
 * @since 1.10.0
 *
 * @global WPPFM_Feed_Processor $background_process
 */
function initiate_background_process() {
	global $background_process;

	if ( isset( $_GET['feed-type'] ) ) {
		$active_tab = wppfm_get_url_parameter( 'feed-type' );
		set_transient( 'wppfm_set_global_background_process', $active_tab, WPPFM_TRANSIENT_LIVE );
	} else {
		$active_tab = ! get_transient( 'wppfm_set_global_background_process' ) ? 'feed-list' : get_transient( 'wppfm_set_global_background_process' );
	}

	if ( ( 'product-feed' === $active_tab || 'feed-list' === $active_tab ) ) {
		if ( ! class_exists( 'WPPFM_Feed_Processor' ) ) {
			require_once __DIR__ . '/../application/class-wppfm-feed-processor.php';
		}

		$background_process = new WPPFM_Feed_Processor();
		return;
	}

	if ( 'google-product-review-feed' === $active_tab ) {
		if ( ! class_exists( 'WPPRFM_Review_Feed_Processor' ) && function_exists( 'wpprfm_include_background_classes' ) ) {
			wpprfm_include_background_classes();
		}

		// @since 2.29.0 to prevent a PHP fatal error when a review feed fails and the user deactivates the plugin.
		if ( class_exists( 'WPPRFM_Review_Feed_Processor' ) ) {
			$background_process = new WPPRFM_Review_Feed_Processor();
			return;
		}
	}

	if ( 'google-merchant-promotions-feed' === $active_tab ) {
		if ( ! class_exists( 'WPPPFM_Promotions_Feed_Processor' ) && function_exists( 'wpppfm_include_background_classes' ) ) {
			wpppfm_include_background_classes();
		}

		if ( class_exists( 'WPPPFM_Promotions_Feed_Processor' ) ) {
			$background_process = new WPPPFM_Promotions_Feed_Processor();
		}
	}
}

// register the background process
add_action( 'wp_loaded', 'initiate_background_process' );

/**
 * Sets a day event schedule that can be used to activate important actions that need to run only once a day.
 *
 * @since 3.7.0.
 * @return void
 */
function wppfm_setup_schedule_daily() {
	if ( ! wp_next_scheduled( 'wppfm_daily_event' ) ) {
		wp_schedule_event( time(), 'daily', 'wppfm_daily_event' );
	}
}

add_action( 'wp_loaded', 'wppfm_setup_schedule_daily' );

/**
 * Makes sure the automatic feed update cron schedule is still installed.
 *
 * @since 2.20.0
 */
function wppfm_verify_feed_update_schedule_registration() {
	wppfm_check_feed_update_schedule();
}

add_action( 'admin_menu', 'wppfm_verify_feed_update_schedule_registration' );

function wppfm_summer_promotion_notice() {
	// Only show on the free version
	if ( 'free' !== WPPFM_PLUGIN_VERSION_ID ) {
		return false;
	}

	$current_date = date( 'Y-m-d' );
	$start_date = '2024-07-10';
	$end_date = '2024-08-31';

	// Check the correct time frame
	if ( $current_date < $start_date || $current_date > $end_date ) {
		return false;
	}

	// Check if the promotion message has been dismissed
	if ( 'canceled' === get_option( 'wppfm_summer_promotion_2024_dismissed', 'keep_it_on' ) ) {
		return false;
	}

	wp_localize_script( 'wppfm_notice-handling-script', 'ajax_object', array( 'ajax_url' => admin_url( 'admin-ajax.php') ) );

	wp_register_style( 'wp-product-feed-manager-promotion-banner', WPPFM_PLUGIN_URL . '/css/wppfm-promotion-notice.css', '', WPPFM_VERSION_NUM, 'screen' );
	wp_enqueue_style( 'wp-product-feed-manager-promotion-banner' );

	// Show the summer promotion message
	WPPFM_Notice::summer_promotion_2024();

	return true;
}

add_action( 'admin_notices', 'wppfm_summer_promotion_notice' );