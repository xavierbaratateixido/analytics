<?php
/**
 * Main plugin controller.
 *
 * @package App_Analytics_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Boots the admin dashboard and orchestrates services.
 */
class AAD_App_Analytics_Dashboard {

    /**
     * Settings controller instance.
     *
     * @var AAD_Settings_Controller
     */
    private $settings_controller;

    /**
     * Apple service instance.
     *
     * @var AAD_Apple_Analytics_Service
     */
    private $apple_service;

    /**
     * Google service instance.
     *
     * @var AAD_Google_Analytics_Service
     */
    private $google_service;

    /**
     * Sync manager instance.
     *
     * @var AAD_Sync_Manager
     */
    private $sync_manager;

    /**
     * Renderer helper.
     *
     * @var AAD_Renderer
     */
    private $renderer;

    /**
     * Flag for hidden submenu registration.
     *
     * @var bool
     */
    private $app_detail_registered = false;

    /**
     * Constructor.
     *
     * @param AAD_Settings_Controller      $settings_controller Settings controller.
     * @param AAD_Apple_Analytics_Service  $apple_service       Apple analytics service.
     * @param AAD_Google_Analytics_Service $google_service      Google analytics service.
     * @param AAD_Sync_Manager             $sync_manager        Sync manager.
     * @param AAD_Renderer                 $renderer            Renderer helper.
     */
    public function __construct( AAD_Settings_Controller $settings_controller, AAD_Apple_Analytics_Service $apple_service, AAD_Google_Analytics_Service $google_service, AAD_Sync_Manager $sync_manager, AAD_Renderer $renderer ) {
        $this->settings_controller = $settings_controller;
        $this->apple_service       = $apple_service;
        $this->google_service      = $google_service;
        $this->sync_manager        = $sync_manager;
        $this->renderer            = $renderer;
    }

    /**
     * Registers hooks.
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'register_menus' ) );
        add_action( 'admin_menu', array( $this, 'register_app_detail_submenu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        add_action( 'admin_post_app_analytics_save_connections', array( $this, 'handle_save_connections' ) );
        add_action( 'admin_post_app_analytics_save_settings', array( $this, 'handle_save_settings' ) );
        add_action( 'admin_post_app_analytics_add_app', array( $this, 'handle_add_app' ) );
        add_action( 'admin_post_app_analytics_bulk_apps', array( $this, 'handle_bulk_app_action' ) );
        add_action( 'admin_post_app_analytics_manual_sync', array( $this, 'handle_manual_sync' ) );
        add_action( 'admin_post_app_analytics_single_sync', array( $this, 'handle_single_app_sync' ) );
        add_action( 'admin_post_app_analytics_export', array( $this, 'handle_export_csv' ) );
        add_action( 'admin_post_app_analytics_toggle_cron', array( $this, 'handle_toggle_cron' ) );

        add_action( 'aad_sync_event', array( $this->sync_manager, 'handle_scheduled_sync' ) );
    }

    /**
     * Registers admin menu entries.
     */
    public function register_menus() {
        $capability = 'manage_app_analytics';

        add_menu_page(
            __( 'App Analytics', 'app-analytics-dashboard' ),
            __( 'App Analytics', 'app-analytics-dashboard' ),
            $capability,
            'app-analytics-dashboard',
            array( $this, 'render_dashboard' ),
            'dashicons-chart-line',
            56
        );

        add_submenu_page( 'app-analytics-dashboard', __( 'Dashboard', 'app-analytics-dashboard' ), __( 'Dashboard', 'app-analytics-dashboard' ), $capability, 'app-analytics-dashboard', array( $this, 'render_dashboard' ) );
        add_submenu_page( 'app-analytics-dashboard', __( 'Connections', 'app-analytics-dashboard' ), __( 'Connections', 'app-analytics-dashboard' ), $capability, 'app-analytics-connections', array( $this, 'render_connections' ) );
        add_submenu_page( 'app-analytics-dashboard', __( 'Apps', 'app-analytics-dashboard' ), __( 'Apps', 'app-analytics-dashboard' ), $capability, 'app-analytics-apps', array( $this, 'render_apps' ) );
        add_submenu_page( 'app-analytics-dashboard', __( 'Quality & Stability', 'app-analytics-dashboard' ), __( 'Quality & Stability', 'app-analytics-dashboard' ), $capability, 'app-analytics-quality', array( $this, 'render_quality' ) );
        add_submenu_page( 'app-analytics-dashboard', __( 'Revenue', 'app-analytics-dashboard' ), __( 'Revenue', 'app-analytics-dashboard' ), $capability, 'app-analytics-revenue', array( $this, 'render_revenue' ) );
        add_submenu_page( 'app-analytics-dashboard', __( 'Reviews', 'app-analytics-dashboard' ), __( 'Reviews', 'app-analytics-dashboard' ), $capability, 'app-analytics-reviews', array( $this, 'render_reviews' ) );
        add_submenu_page( 'app-analytics-dashboard', __( 'Sync', 'app-analytics-dashboard' ), __( 'Sync', 'app-analytics-dashboard' ), $capability, 'app-analytics-sync', array( $this, 'render_sync' ) );
        add_submenu_page( 'app-analytics-dashboard', __( 'Settings', 'app-analytics-dashboard' ), __( 'Settings', 'app-analytics-dashboard' ), $capability, 'app-analytics-settings', array( $this, 'render_settings' ) );
        add_submenu_page( 'app-analytics-dashboard', __( 'Help', 'app-analytics-dashboard' ), __( 'Help', 'app-analytics-dashboard' ), $capability, 'app-analytics-help', array( $this, 'render_help' ) );
    }

    /**
     * Enqueues assets for admin screens.
     *
     * @param string $hook Current admin hook.
     */
    public function enqueue_assets( $hook ) {
        if ( false === strpos( $hook, 'app-analytics' ) ) {
            return;
        }

        wp_enqueue_style( 'app-analytics-dashboard', AAD_PLUGIN_URL . 'assets/css/dashboard.css', array(), AAD_PLUGIN_VERSION );
        wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), null, true );
        wp_enqueue_script( 'app-analytics-dashboard', AAD_PLUGIN_URL . 'assets/js/dashboard.js', array( 'chartjs', 'jquery' ), AAD_PLUGIN_VERSION, true );

        wp_localize_script(
            'app-analytics-dashboard',
            'AppAnalyticsDashboard',
            array(
                'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
                'nonce'      => wp_create_nonce( 'app_analytics_dashboard' ),
                'i18n'       => array(
                    'exporting' => __( 'Preparing export…', 'app-analytics-dashboard' ),
                ),
                'capability' => current_user_can( 'manage_app_analytics' ),
            )
        );
    }

    /**
     * Registers plugin settings and options.
     */
    public function register_settings() {
        register_setting( 'aad_settings', 'aad_settings', array( $this, 'sanitize_settings' ) );
    }

    /**
     * Sanitizes settings array.
     *
     * @param array $settings Settings payload.
     *
     * @return array
     */
    public function sanitize_settings( $settings ) {
        $defaults = array(
            'timezone'       => 'UTC',
            'date_format'    => 'Y-m-d',
            'currency'       => 'USD',
            'retention_days' => 365,
            'cache_size'     => 5000,
            'cache_ttl'      => DAY_IN_SECONDS,
            'readonly_roles' => array(),
        );

        $settings = wp_parse_args( (array) $settings, $defaults );

        $settings['timezone']       = sanitize_text_field( $settings['timezone'] );
        $settings['date_format']    = sanitize_text_field( $settings['date_format'] );
        $settings['currency']       = sanitize_text_field( $settings['currency'] );
        $settings['retention_days'] = max( 1, absint( $settings['retention_days'] ) );
        $settings['cache_size']     = max( 10, absint( $settings['cache_size'] ) );
        $settings['cache_ttl']      = max( MINUTE_IN_SECONDS, absint( $settings['cache_ttl'] ) );

        $roles = array();
        if ( ! empty( $settings['readonly_roles'] ) && is_array( $settings['readonly_roles'] ) ) {
            foreach ( $settings['readonly_roles'] as $role ) {
                $roles[] = sanitize_text_field( $role );
            }
        }

        $settings['readonly_roles'] = $roles;
        $this->settings_controller->sync_readonly_roles( $roles );

        return $settings;
    }

    /**
     * Dashboard renderer.
     */
    public function render_dashboard() {
        $this->ensure_access();

        $range    = isset( $_GET['range'] ) ? sanitize_text_field( wp_unslash( $_GET['range'] ) ) : '30d'; // phpcs:ignore WordPress.Security.NonceVerification
        $app_id   = isset( $_GET['app_id'] ) ? sanitize_text_field( wp_unslash( $_GET['app_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        $apps     = $this->settings_controller->get_apps();
        $data     = $this->get_dashboard_data( $range, $app_id );
        $alerts   = $this->settings_controller->get_connection_alerts();

        $this->renderer->render( 'dashboard', compact( 'data', 'range', 'apps', 'app_id', 'alerts' ) );
    }

    /**
     * Render connections page.
     */
    public function render_connections() {
        $this->ensure_access();

        $apple_credentials  = $this->settings_controller->get_apple_credentials();
        $google_credentials = $this->settings_controller->get_google_credentials();
        $test_results       = $this->settings_controller->get_last_connection_tests();

        $this->renderer->render( 'connections', compact( 'apple_credentials', 'google_credentials', 'test_results' ) );
    }

    /**
     * Render apps page.
     */
    public function render_apps() {
        $this->ensure_access();

        $apps = $this->settings_controller->get_apps();

        $this->renderer->render( 'apps', compact( 'apps' ) );
    }

    /**
     * Render app detail page.
     */
    public function render_app_detail() {
        $this->ensure_access();

        $app_id = isset( $_GET['app_id'] ) ? sanitize_text_field( wp_unslash( $_GET['app_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        $app    = $this->settings_controller->get_app( $app_id );

        if ( empty( $app ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=app-analytics-apps' ) );
            exit;
        }

        $range = isset( $_GET['range'] ) ? sanitize_text_field( wp_unslash( $_GET['range'] ) ) : '30d'; // phpcs:ignore WordPress.Security.NonceVerification
        $data  = $this->sync_manager->get_app_metrics( $app, $range );

        $this->renderer->render( 'app-detail', compact( 'app', 'data', 'range' ) );
    }

    /**
     * Render quality page.
     */
    public function render_quality() {
        $this->ensure_access();

        $apps   = $this->settings_controller->get_apps();
        $series = $this->sync_manager->get_quality_metrics( $apps );

        $this->renderer->render( 'quality', compact( 'apps', 'series' ) );
    }

    /**
     * Render revenue page.
     */
    public function render_revenue() {
        $this->ensure_access();

        $apps   = $this->settings_controller->get_apps();
        $series = $this->sync_manager->get_revenue_metrics( $apps );

        $this->renderer->render( 'revenue', compact( 'apps', 'series' ) );
    }

    /**
     * Render reviews page.
     */
    public function render_reviews() {
        $this->ensure_access();

        $apps   = $this->settings_controller->get_apps();
        $series = $this->sync_manager->get_review_metrics( $apps );

        $this->renderer->render( 'reviews', compact( 'apps', 'series' ) );
    }

    /**
     * Render sync page.
     */
    public function render_sync() {
        $this->ensure_access();

        $cron_enabled = $this->sync_manager->is_cron_enabled();
        $last_job     = $this->sync_manager->get_last_job_status();
        $log_entries  = $this->sync_manager->get_log_entries();

        $this->renderer->render( 'sync', compact( 'cron_enabled', 'last_job', 'log_entries' ) );
    }

    /**
     * Render settings page.
     */
    public function render_settings() {
        $this->ensure_access();

        $settings = $this->settings_controller->get_settings();
        $roles    = $this->settings_controller->get_editable_roles();

        $this->renderer->render( 'settings', compact( 'settings', 'roles' ) );
    }

    /**
     * Render help page.
     */
    public function render_help() {
        $this->ensure_access();
        $this->renderer->render( 'help' );
    }

    /**
     * Handles saving connections.
     */
    public function handle_save_connections() {
        $this->ensure_access();
        check_admin_referer( 'app_analytics_save_connections' );

        $apple  = isset( $_POST['apple'] ) ? wp_unslash( $_POST['apple'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
        $google = isset( $_POST['google'] ) ? wp_unslash( $_POST['google'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification

        $this->settings_controller->save_apple_credentials( $apple );
        $this->settings_controller->save_google_credentials( $google );

        if ( isset( $_POST['test_connection'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            $this->settings_controller->test_connections( $this->apple_service, $this->google_service );
        }

        wp_safe_redirect( add_query_arg( 'updated', 'true', wp_get_referer() ) );
        exit;
    }

    /**
     * Handles settings save.
     */
    public function handle_save_settings() {
        $this->ensure_access();
        check_admin_referer( 'app_analytics_save_settings' );

        $settings = isset( $_POST['aad_settings'] ) ? wp_unslash( $_POST['aad_settings'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
        update_option( 'aad_settings', $this->sanitize_settings( $settings ) );

        if ( isset( $_POST['clear_cache'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            $this->sync_manager->clear_cache();
        }

        wp_safe_redirect( add_query_arg( 'updated', 'true', wp_get_referer() ) );
        exit;
    }

    /**
     * Handles app creation.
     */
    public function handle_add_app() {
        $this->ensure_access();
        check_admin_referer( 'app_analytics_add_app' );

        $payload = isset( $_POST['app'] ) ? wp_unslash( $_POST['app'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
        $this->settings_controller->add_app( $payload );

        wp_safe_redirect( add_query_arg( 'added', 'true', admin_url( 'admin.php?page=app-analytics-apps' ) ) );
        exit;
    }

    /**
     * Handles bulk actions.
     */
    public function handle_bulk_app_action() {
        $this->ensure_access();
        check_admin_referer( 'app_analytics_bulk_apps' );

        $action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        $ids    = isset( $_POST['app_ids'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['app_ids'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification

        foreach ( $ids as $id ) {
            switch ( $action ) {
                case 'activate':
                    $this->settings_controller->update_app_status( $id, true );
                    break;
                case 'deactivate':
                    $this->settings_controller->update_app_status( $id, false );
                    break;
                case 'delete':
                    $this->settings_controller->delete_app( $id );
                    break;
                default:
                    break;
            }
        }

        wp_safe_redirect( add_query_arg( 'updated', 'true', admin_url( 'admin.php?page=app-analytics-apps' ) ) );
        exit;
    }

    /**
     * Handles manual sync.
     */
    public function handle_manual_sync() {
        $this->ensure_access();
        check_admin_referer( 'app_analytics_manual_sync' );

        $this->sync_manager->run_sync();

        wp_safe_redirect( add_query_arg( 'synced', 'true', wp_get_referer() ) );
        exit;
    }

    /**
     * Handles single app sync.
     */
    public function handle_single_app_sync() {
        $this->ensure_access();
        check_admin_referer( 'app_analytics_single_sync' );

        $app_id = isset( $_POST['app_id'] ) ? sanitize_text_field( wp_unslash( $_POST['app_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        $app    = $this->settings_controller->get_app( $app_id );

        if ( $app ) {
            $this->sync_manager->run_sync( array( $app ) );
        }

        wp_safe_redirect( add_query_arg( 'synced', 'true', wp_get_referer() ) );
        exit;
    }

    /**
     * Handles CSV exports.
     */
    public function handle_export_csv() {
        $this->ensure_access();
        check_admin_referer( 'app_analytics_export' );

        $context = isset( $_POST['context'] ) ? sanitize_text_field( wp_unslash( $_POST['context'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification
        $range   = isset( $_POST['range'] ) ? sanitize_text_field( wp_unslash( $_POST['range'] ) ) : '30d'; // phpcs:ignore WordPress.Security.NonceVerification
        $app_id  = isset( $_POST['app_id'] ) ? sanitize_text_field( wp_unslash( $_POST['app_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

        $filename = sprintf( 'app-analytics-%s-%s.csv', $context, wp_date( 'Ymd-His' ) );
        $data     = $this->sync_manager->get_export_data( $context, $range, $app_id );

        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename=' . $filename );

        $out = fopen( 'php://output', 'w' );
        if ( $out && ! empty( $data ) ) {
            fputcsv( $out, array_keys( current( $data ) ) );
            foreach ( $data as $row ) {
                fputcsv( $out, $row );
            }
            fclose( $out );
        }

        exit;
    }

    /**
     * Toggles cron schedule.
     */
    public function handle_toggle_cron() {
        $this->ensure_access();
        check_admin_referer( 'app_analytics_toggle_cron' );

        $enabled = isset( $_POST['cron_enabled'] ) && '1' === $_POST['cron_enabled']; // phpcs:ignore WordPress.Security.NonceVerification
        $this->sync_manager->set_cron_enabled( $enabled );

        wp_safe_redirect( add_query_arg( 'updated', 'true', admin_url( 'admin.php?page=app-analytics-sync' ) ) );
        exit;
    }

    /**
     * Ensures the user has access to the dashboard.
     */
    private function ensure_access() {
        if ( ! current_user_can( 'manage_app_analytics' ) && ! current_user_can( 'read_app_analytics' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'app-analytics-dashboard' ) );
        }

    }

    /**
     * Registers the app detail submenu when accessed.
     */
    public function register_app_detail_submenu() {
        if ( $this->app_detail_registered ) {
            return;
        }

        add_submenu_page( null, __( 'App Detail', 'app-analytics-dashboard' ), __( 'App Detail', 'app-analytics-dashboard' ), 'manage_app_analytics', 'app-analytics-app', array( $this, 'render_app_detail' ) );
        $this->app_detail_registered = true;
    }

    /**
     * Collects dashboard data for the selected range.
     *
     * @param string $range  Date range identifier.
     * @param string $app_id Optional app filter.
     *
     * @return array
     */
    private function get_dashboard_data( $range, $app_id = '' ) {
        $apps = $this->settings_controller->get_active_apps( $app_id );

        $data = array(
            'kpis'        => $this->sync_manager->get_dashboard_kpis( $apps, $range ),
            'downloads'   => $this->sync_manager->get_download_timeseries( $apps, $range ),
            'revenue'     => $this->sync_manager->get_revenue_timeseries( $apps, $range ),
            'countries'   => $this->sync_manager->get_top_countries( $apps, $range ),
            'alerts'      => $this->settings_controller->get_connection_alerts(),
            'range'       => $range,
            'active_apps' => $apps,
        );

        return $data;
    }
}
