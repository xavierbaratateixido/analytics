<?php
/**
 * Settings controller.
 *
 * @package App_Analytics_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles secure storage of credentials and app configuration.
 */
class AAD_Settings_Controller {

    /**
     * Option keys.
     */
    private const OPTION_APPLE_CREDENTIALS  = 'aad_apple_credentials';
    private const OPTION_GOOGLE_CREDENTIALS = 'aad_google_credentials';
    private const OPTION_APPS               = 'aad_apps';
    private const OPTION_SETTINGS           = 'aad_settings';
    private const OPTION_CONNECTION_TESTS   = 'aad_connection_tests';

    /**
     * Encryption method.
     */
    private const ENCRYPTION_METHOD = 'AES-256-CBC';

    /**
     * Saves Apple credentials.
     *
     * @param array $data Apple credentials payload.
     */
    public function save_apple_credentials( $data ) {
        $payload = array(
            'key_id'       => isset( $data['key_id'] ) ? sanitize_text_field( $data['key_id'] ) : '',
            'issuer_id'    => isset( $data['issuer_id'] ) ? sanitize_text_field( $data['issuer_id'] ) : '',
            'private_key'  => isset( $data['private_key'] ) ? $this->sanitize_multiline_secret( $data['private_key'] ) : '',
            'vendor'       => isset( $data['vendor'] ) ? sanitize_text_field( $data['vendor'] ) : '',
            'last_test'    => 0,
            'last_success' => false,
            'last_message' => '',
        );

        $this->update_encrypted_option( self::OPTION_APPLE_CREDENTIALS, $payload );
    }

    /**
     * Saves Google credentials.
     *
     * @param array $data Google credentials payload.
     */
    public function save_google_credentials( $data ) {
        $packages = array();
        if ( isset( $data['packages'] ) ) {
            $raw = is_array( $data['packages'] ) ? $data['packages'] : explode( ',', $data['packages'] );
            foreach ( $raw as $package ) {
                $package = sanitize_text_field( trim( $package ) );
                if ( ! empty( $package ) ) {
                    $packages[] = $package;
                }
            }
        }

        $payload = array(
            'service_account' => isset( $data['service_account'] ) ? $this->sanitize_multiline_secret( $data['service_account'] ) : '',
            'packages'        => $packages,
            'last_test'       => 0,
            'last_success'    => false,
            'last_message'    => '',
        );

        $this->update_encrypted_option( self::OPTION_GOOGLE_CREDENTIALS, $payload );
    }

    /**
     * Retrieves Apple credentials.
     *
     * @return array
     */
    public function get_apple_credentials() {
        return $this->get_decrypted_option( self::OPTION_APPLE_CREDENTIALS );
    }

    /**
     * Retrieves Google credentials.
     *
     * @return array
     */
    public function get_google_credentials() {
        return $this->get_decrypted_option( self::OPTION_GOOGLE_CREDENTIALS );
    }

    /**
     * Stores connection test results.
     */
    public function test_connections( AAD_Apple_Analytics_Service $apple_service, AAD_Google_Analytics_Service $google_service ) {
        $apple_result            = $apple_service->test_connection();
        $apple_result['checked'] = time();

        $google_result            = $google_service->test_connection();
        $google_result['checked'] = time();

        $tests = array(
            'apple'  => $apple_result,
            'google' => $google_result,
        );

        update_option( self::OPTION_CONNECTION_TESTS, $tests );

        $this->store_connection_result( self::OPTION_APPLE_CREDENTIALS, $apple_result );
        $this->store_connection_result( self::OPTION_GOOGLE_CREDENTIALS, $google_result );
    }

    /**
     * Persists metadata from a connection test into the credential payload.
     *
     * @param string $option_key Option identifier.
     * @param array  $result     Test result payload.
     */
    private function store_connection_result( $option_key, array $result ) {
        $credentials = $this->get_decrypted_option( $option_key );
        if ( empty( $credentials ) ) {
            return;
        }

        $credentials['last_test']    = isset( $result['checked'] ) ? (int) $result['checked'] : time();
        $credentials['last_success'] = ! empty( $result['status'] );
        $credentials['last_message'] = isset( $result['message'] ) ? wp_strip_all_tags( (string) $result['message'] ) : '';

        $this->update_encrypted_option( $option_key, $credentials );
    }

    /**
     * Retrieves last connection tests.
     *
     * @return array
     */
    public function get_last_connection_tests() {
        $tests = get_option( self::OPTION_CONNECTION_TESTS, array() );
        $tests = is_array( $tests ) ? $tests : array();

        $apple_credentials  = $this->get_apple_credentials();
        $google_credentials = $this->get_google_credentials();

        $tests['apple'] = $this->prepare_connection_status(
            isset( $tests['apple'] ) ? $tests['apple'] : array(),
            $apple_credentials,
            __( 'Connection test has not been run yet for Apple App Store Connect.', 'app-analytics-dashboard' )
        );

        $tests['google'] = $this->prepare_connection_status(
            isset( $tests['google'] ) ? $tests['google'] : array(),
            $google_credentials,
            __( 'Connection test has not been run yet for Google Play.', 'app-analytics-dashboard' )
        );

        return $tests;
    }

    /**
     * Normalises a stored connection test entry.
     *
     * @param array $result       Stored result payload.
     * @param array $credentials  Credential metadata.
     * @param string $default_msg Message shown when the connection has not been tested.
     *
     * @return array
     */
    private function prepare_connection_status( $result, array $credentials, $default_msg ) {
        $result = is_array( $result ) ? $result : array();

        $status  = array_key_exists( 'status', $result ) ? filter_var( $result['status'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) : null;
        $message = isset( $result['message'] ) && '' !== trim( (string) $result['message'] )
            ? wp_strip_all_tags( (string) $result['message'] )
            : $default_msg;
        $checked = isset( $result['checked'] ) ? (int) $result['checked'] : 0;

        if ( 0 === $checked && ! empty( $credentials ) ) {
            if ( ! empty( $credentials['last_test'] ) ) {
                $checked = (int) $credentials['last_test'];
            }

            if ( array_key_exists( 'last_success', $credentials ) && null === $status ) {
                $status = (bool) $credentials['last_success'];
            }

            if ( ! empty( $credentials['last_message'] ) && $default_msg === $message ) {
                $message = wp_strip_all_tags( (string) $credentials['last_message'] );
            }
        }

        return array(
            'status'  => $status,
            'message' => $message,
            'checked' => $checked,
        );
    }

    /**
     * Returns alert messages when credentials are incomplete.
     *
     * @return array
     */
    public function get_connection_alerts() {
        $alerts  = array();
        $apple   = $this->get_apple_credentials();
        $google  = $this->get_google_credentials();
        $missing = array();

        if ( empty( $apple['key_id'] ) || empty( $apple['issuer_id'] ) || empty( $apple['private_key'] ) ) {
            $missing[] = __( 'Apple App Store Connect credentials are incomplete.', 'app-analytics-dashboard' );
        }

        if ( empty( $google['service_account'] ) ) {
            $missing[] = __( 'Google Play service account JSON is missing.', 'app-analytics-dashboard' );
        }

        if ( ! empty( $missing ) ) {
            $alerts[] = array(
                'type'    => 'error',
                'message' => implode( ' ', $missing ),
            );
        }

        return $alerts;
    }

    /**
     * Returns stored apps.
     *
     * @return array
     */
    public function get_apps() {
        $apps = get_option( self::OPTION_APPS, array() );
        if ( ! is_array( $apps ) ) {
            return array();
        }

        return $apps;
    }

    /**
     * Returns single app by id.
     *
     * @param string $app_id App identifier.
     *
     * @return array|null
     */
    public function get_app( $app_id ) {
        $apps = $this->get_apps();
        return isset( $apps[ $app_id ] ) ? $apps[ $app_id ] : null;
    }

    /**
     * Returns active apps filtered by optional id.
     *
     * @param string $app_id Optional app id.
     *
     * @return array
     */
    public function get_active_apps( $app_id = '' ) {
        $apps = $this->get_apps();

        if ( $app_id && isset( $apps[ $app_id ] ) ) {
            return array( $apps[ $app_id ] );
        }

        return array_filter(
            $apps,
            static function ( $app ) {
                return ! empty( $app['active'] );
            }
        );
    }

    /**
     * Adds a new app definition.
     *
     * @param array $payload Payload.
     */
    public function add_app( $payload ) {
        $apps      = $this->get_apps();
        $store     = isset( $payload['store'] ) ? sanitize_text_field( $payload['store'] ) : '';
        $name      = isset( $payload['name'] ) ? sanitize_text_field( $payload['name'] ) : '';
        $bundle_id = isset( $payload['bundle_id'] ) ? sanitize_text_field( $payload['bundle_id'] ) : '';
        $vendor    = isset( $payload['vendor'] ) ? sanitize_text_field( $payload['vendor'] ) : '';

        $allowed = array( 'apple', 'google' );
        if ( empty( $store ) || empty( $name ) || ! in_array( $store, $allowed, true ) ) {
            return;
        }

        $id = uniqid( 'aad_', true );

        $apps[ $id ] = array(
            'id'          => $id,
            'name'        => $name,
            'store'       => $store,
            'bundle_id'   => $bundle_id,
            'vendor'      => $vendor,
            'active'      => true,
            'last_sync'   => null,
            'package'     => isset( $payload['package'] ) ? sanitize_text_field( $payload['package'] ) : '',
            'created_at'  => time(),
        );

        update_option( self::OPTION_APPS, $apps, false );
    }

    /**
     * Updates app status.
     *
     * @param string $app_id App id.
     * @param bool   $status Active status.
     */
    public function update_app_status( $app_id, $status ) {
        $apps = $this->get_apps();
        if ( isset( $apps[ $app_id ] ) ) {
            $apps[ $app_id ]['active'] = (bool) $status;
            update_option( self::OPTION_APPS, $apps, false );
        }
    }

    /**
     * Removes app.
     *
     * @param string $app_id App id.
     */
    public function delete_app( $app_id ) {
        $apps = $this->get_apps();
        if ( isset( $apps[ $app_id ] ) ) {
            unset( $apps[ $app_id ] );
            update_option( self::OPTION_APPS, $apps, false );
        }
    }

    /**
     * Updates app sync metadata.
     *
     * @param string $app_id App id.
     * @param array  $data   Metadata updates.
     */
    public function update_app_metadata( $app_id, array $data ) {
        $apps = $this->get_apps();
        if ( isset( $apps[ $app_id ] ) ) {
            $apps[ $app_id ] = array_merge( $apps[ $app_id ], $data );
            update_option( self::OPTION_APPS, $apps, false );
        }
    }

    /**
     * Retrieves plugin settings.
     *
     * @return array
     */
    public function get_settings() {
        $defaults = array(
            'timezone'       => 'UTC',
            'date_format'    => 'Y-m-d',
            'currency'       => 'USD',
            'retention_days' => 365,
            'cache_size'     => 5000,
            'cache_ttl'      => DAY_IN_SECONDS,
            'readonly_roles' => array(),
        );

        $settings = get_option( self::OPTION_SETTINGS, $defaults );

        return wp_parse_args( $settings, $defaults );
    }

    /**
     * Returns the configured timezone.
     *
     * @return \DateTimeZone
     */
    public function get_timezone() {
        $settings = $this->get_settings();
        $timezone = ! empty( $settings['timezone'] ) ? $settings['timezone'] : 'UTC';

        try {
            return new \DateTimeZone( $timezone );
        } catch ( \Exception $exception ) {
            return wp_timezone();
        }
    }

    /**
     * Returns cache TTL in seconds.
     *
     * @return int
     */
    public function get_cache_ttl() {
        $settings = $this->get_settings();

        return max( MINUTE_IN_SECONDS, absint( $settings['cache_ttl'] ?? DAY_IN_SECONDS ) );
    }

    /**
     * Returns the cache size limit for stored datapoints.
     *
     * @return int
     */
    public function get_cache_size() {
        $settings = $this->get_settings();

        return max( 10, absint( $settings['cache_size'] ?? 5000 ) );
    }

    /**
     * Returns how many days of metrics to retain.
     *
     * @return int
     */
    public function get_retention_days() {
        $settings = $this->get_settings();

        return max( 1, absint( $settings['retention_days'] ?? 365 ) );
    }

    /**
     * Returns editable roles.
     *
     * @return array
     */
    public function get_editable_roles() {
        global $wp_roles;
        if ( ! isset( $wp_roles ) ) {
            $wp_roles = wp_roles();
        }

        return $wp_roles->roles;
    }

    /**
     * Syncs readonly roles capabilities.
     *
     * @param array $roles Roles list.
     */
    public function sync_readonly_roles( array $roles ) {
        foreach ( $this->get_editable_roles() as $role_key => $role_details ) {
            $role = get_role( $role_key );
            if ( ! $role ) {
                continue;
            }

            if ( in_array( $role_key, $roles, true ) ) {
                $role->add_cap( 'read_app_analytics' );
            } else {
                $role->remove_cap( 'read_app_analytics' );
            }
        }
    }

    /**
     * Encrypts and stores an option.
     *
     * @param string $key     Option key.
     * @param array  $payload Payload data.
     */
    private function update_encrypted_option( $key, array $payload ) {
        $encrypted = $this->encrypt( wp_json_encode( $payload ) );
        update_option( $key, $encrypted, false );
    }

    /**
     * Returns decrypted option data.
     *
     * @param string $key Option key.
     *
     * @return array
     */
    private function get_decrypted_option( $key ) {
        $value = get_option( $key );
        if ( empty( $value ) || ! is_array( $value ) ) {
            return array();
        }

        $decrypted = $this->decrypt( $value );

        if ( empty( $decrypted ) ) {
            return array();
        }

        $decoded = json_decode( $decrypted, true );

        return is_array( $decoded ) ? $decoded : array();
    }

    /**
     * Encrypts a payload.
     *
     * @param string $payload Payload string.
     *
     * @return array
     */
    private function encrypt( $payload ) {
        $key = $this->get_encryption_key();
        $iv  = openssl_random_pseudo_bytes( openssl_cipher_iv_length( self::ENCRYPTION_METHOD ) );

        $cipher = openssl_encrypt( $payload, self::ENCRYPTION_METHOD, $key, 0, $iv );

        return array(
            'iv'      => base64_encode( $iv ),
            'payload' => $cipher,
        );
    }

    /**
     * Decrypts payload.
     *
     * @param array $data Encrypted data.
     *
     * @return string
     */
    private function decrypt( array $data ) {
        if ( empty( $data['iv'] ) || empty( $data['payload'] ) ) {
            return '';
        }

        $key = $this->get_encryption_key();
        $iv  = base64_decode( $data['iv'] );

        return openssl_decrypt( $data['payload'], self::ENCRYPTION_METHOD, $key, 0, $iv );
    }

    /**
     * Generates encryption key.
     *
     * @return string
     */
    private function get_encryption_key() {
        return hash( 'sha256', wp_salt() . get_site_url() );
    }

    /**
     * Normalizes multi-line secrets.
     *
     * @param string $secret Secret payload.
     *
     * @return string
     */
    private function sanitize_multiline_secret( $secret ) {
        $secret = is_string( $secret ) ? trim( $secret ) : '';
        $secret = str_replace( array( "\r\n", "\r" ), "\n", $secret );

        return $secret;
    }
}
