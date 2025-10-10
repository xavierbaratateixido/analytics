<?php
/**
 * Plugin Name:       App Analytics Dashboard
 * Description:       Integrates App Store Connect and Google Play Developer Reporting metrics into the WordPress admin dashboard.
 * Version:           1.0.0
 * Author:            OpenAI Assistant
 * Text Domain:       app-analytics-dashboard
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'AAD_PLUGIN_VERSION', '1.0.0' );
define( 'AAD_PLUGIN_FILE', __FILE__ );
define( 'AAD_PLUGIN_PATH', __DIR__ );
define( 'AAD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AAD_LOG_FILE', __DIR__ . '/logs/app-analytics.log' );

$autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $autoload ) ) {
    require_once $autoload;
}

if ( ! file_exists( AAD_LOG_FILE ) ) {
    wp_mkdir_p( dirname( AAD_LOG_FILE ) );
    touch( AAD_LOG_FILE );
}

// Load core classes explicitly to avoid autoloader issues on certain hosts.
$aad_required_classes = array(
    'class-settings-controller.php',
    'class-apple-analytics-service.php',
    'class-google-analytics-service.php',
    'class-sync-manager.php',
    'class-renderer.php',
    'class-app-analytics-dashboard.php',
);

foreach ( $aad_required_classes as $aad_required_class ) {
    $aad_path = AAD_PLUGIN_PATH . '/includes/' . $aad_required_class;
    if ( file_exists( $aad_path ) ) {
        require_once $aad_path;
    }
}

register_activation_hook( __FILE__, 'aad_activate_plugin' );
register_deactivation_hook( __FILE__, 'aad_deactivate_plugin' );

/**
 * Handles plugin activation.
 */
function aad_activate_plugin() {
    if ( ! current_user_can( 'activate_plugins' ) ) {
        return;
    }

    $roles = array( 'administrator' );
    foreach ( $roles as $role_slug ) {
        $role = get_role( $role_slug );
        if ( $role && ! $role->has_cap( 'manage_app_analytics' ) ) {
            $role->add_cap( 'manage_app_analytics' );
            $role->add_cap( 'read_app_analytics' );
        }
    }

    if ( ! wp_next_scheduled( 'aad_sync_event' ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'aad_sync_event' );
    }
}

/**
 * Handles plugin deactivation.
 */
function aad_deactivate_plugin() {
    if ( ! current_user_can( 'activate_plugins' ) ) {
        return;
    }

    wp_clear_scheduled_hook( 'aad_sync_event' );
}

add_action( 'plugins_loaded', static function () {
    load_plugin_textdomain( 'app-analytics-dashboard', false, basename( dirname( __FILE__ ) ) . '/languages' );

    $settings_controller = new AAD_Settings_Controller();
    $apple_service       = new AAD_Apple_Analytics_Service( $settings_controller );
    $google_service      = new AAD_Google_Analytics_Service( $settings_controller );
    $renderer            = new AAD_Renderer();
    $sync_manager        = new AAD_Sync_Manager( $apple_service, $google_service, $settings_controller );

    $plugin = new AAD_App_Analytics_Dashboard( $settings_controller, $apple_service, $google_service, $sync_manager, $renderer );
    $plugin->init();
} );
