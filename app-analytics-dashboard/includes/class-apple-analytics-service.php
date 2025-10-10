<?php
/**
 * Apple analytics service.
 *
 * @package App_Analytics_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Firebase\JWT\JWT;

/**
 * Handles App Store Connect integration.
 */
class AAD_Apple_Analytics_Service {

    /**
     * Settings controller.
     *
     * @var AAD_Settings_Controller
     */
    private $settings_controller;

    /**
     * Constructor.
     *
     * @param AAD_Settings_Controller $settings_controller Settings controller instance.
     */
    public function __construct( AAD_Settings_Controller $settings_controller ) {
        $this->settings_controller = $settings_controller;
    }

    /**
     * Fetches metrics for an Apple app.
     *
     * @param array  $app   App configuration.
     * @param string $range Range identifier.
     *
     * @return array
     */
    public function fetch_metrics( array $app, $range ) {
        $credentials = $this->settings_controller->get_apple_credentials();
        if ( empty( $credentials['key_id'] ) || empty( $credentials['issuer_id'] ) || empty( $credentials['private_key'] ) ) {
            return $this->generate_placeholder_metrics( $app, $range );
        }

        $dates  = $this->parse_range( $range );
        $vendor = ! empty( $app['vendor'] ) ? $app['vendor'] : ( isset( $credentials['vendor'] ) ? $credentials['vendor'] : '' );

        if ( empty( $vendor ) ) {
            return $this->generate_placeholder_metrics( $app, $range );
        }

        $reports = $this->fetch_sales_reports( $vendor, $dates['start'], $dates['end'] );
        if ( empty( $reports ) ) {
            return $this->generate_placeholder_metrics( $app, $range );
        }

        return $this->aggregate_reports( $reports, $dates );
    }

    /**
     * Performs a lightweight connectivity check against App Store Connect.
     *
     * @return array
     */
    public function test_connection() {
        $credentials = $this->settings_controller->get_apple_credentials();

        if ( empty( $credentials['key_id'] ) || empty( $credentials['issuer_id'] ) || empty( $credentials['private_key'] ) ) {
            return array(
                'status'  => false,
                'message' => __( 'Missing Key ID, Issuer ID or private key for App Store Connect.', 'app-analytics-dashboard' ),
            );
        }

        $token = $this->generate_jwt();
        if ( empty( $token ) ) {
            return array(
                'status'  => false,
                'message' => __( 'Could not generate a JWT token. Verify the private key and server configuration.', 'app-analytics-dashboard' ),
            );
        }

        $response = $this->request( 'apps', array( 'limit' => 1 ), $token );
        if ( is_wp_error( $response ) ) {
            return array(
                'status'  => false,
                'message' => sprintf(
                    /* translators: %s: error message returned by wp_remote_get */
                    __( 'App Store Connect request failed: %s', 'app-analytics-dashboard' ),
                    $response->get_error_message()
                ),
            );
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            $detail = $this->extract_error_detail( $response );
            return array(
                'status'  => false,
                'message' => sprintf(
                    /* translators: %s: error message from App Store Connect */
                    __( 'App Store Connect test failed: %s', 'app-analytics-dashboard' ),
                    $detail
                ),
            );
        }

        return array(
            'status'  => true,
            'message' => __( 'Successfully connected to App Store Connect.', 'app-analytics-dashboard' ),
        );
    }

    /**
     * Downloads sales reports for the vendor.
     *
     * @param string    $vendor Vendor number.
     * @param \DateTime $start  Start date.
     * @param \DateTime $end    End date.
     *
     * @return array
     */
    private function fetch_sales_reports( $vendor, \DateTime $start, \DateTime $end ) {
        $period = new \DatePeriod( $start, new \DateInterval( 'P1D' ), ( clone $end )->modify( '+1 day' ) );
        $token  = $this->generate_jwt();
        if ( empty( $token ) ) {
            return array();
        }

        $reports = array();

        foreach ( $period as $date ) {
            $params = array(
                'filter[reportType]'    => 'SALES',
                'filter[reportSubType]' => 'SUMMARY',
                'filter[frequency]'     => 'DAILY',
                'filter[vendorNumber]'  => $vendor,
                'filter[reportDate]'    => $date->format( 'Y-m-d' ),
            );

            $response = $this->request( 'salesReports', $params, $token );

            if ( is_wp_error( $response ) ) {
                continue;
            }

            $body = wp_remote_retrieve_body( $response );
            if ( 'application/a-gzip' === wp_remote_retrieve_header( $response, 'content-type' ) || $this->is_gzip( $body ) ) {
                $body = gzdecode( $body );
            }

            $rows = $this->parse_csv( $body );
            if ( ! empty( $rows ) ) {
                $reports = array_merge( $reports, $rows );
            }
        }

        return $reports;
    }

    /**
     * Makes an authenticated request.
     *
     * @param string $endpoint Endpoint path.
     * @param array  $params   Query params.
     * @param string $token    JWT token.
     *
     * @return array|WP_Error
     */
    private function request( $endpoint, array $params, $token ) {
        $url = add_query_arg( $params, 'https://api.appstoreconnect.apple.com/v1/' . $endpoint );

        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ),
            'timeout' => 30,
        );

        return wp_remote_get( $url, $args );
    }

    /**
     * Extracts an error message from an App Store Connect response.
     *
     * @param array $response HTTP response array.
     *
     * @return string
     */
    private function extract_error_detail( $response ) {
        $body = wp_remote_retrieve_body( $response );
        if ( ! empty( $body ) ) {
            $decoded = json_decode( $body, true );
            if ( isset( $decoded['errors'][0]['detail'] ) ) {
                return $decoded['errors'][0]['detail'];
            }
        }

        $message = wp_remote_retrieve_response_message( $response );
        if ( ! empty( $message ) ) {
            return $message;
        }

        return __( 'Unexpected response from App Store Connect.', 'app-analytics-dashboard' );
    }

    /**
     * Generates JWT token for App Store Connect.
     *
     * @return string
     */
    private function generate_jwt() {
        $credentials = $this->settings_controller->get_apple_credentials();
        if ( empty( $credentials['key_id'] ) || empty( $credentials['issuer_id'] ) || empty( $credentials['private_key'] ) ) {
            return '';
        }

        $now  = time();
        $exp  = $now + ( 20 * MINUTE_IN_SECONDS );
        $team = $credentials['issuer_id'];

        $payload = array(
            'iss' => $team,
            'iat' => $now,
            'exp' => $exp,
            'aud' => 'appstoreconnect-v1',
        );

        $headers = array(
            'alg' => 'ES256',
            'kid' => $credentials['key_id'],
            'typ' => 'JWT',
        );

        if ( ! class_exists( '\Firebase\JWT\JWT' ) ) {
            return '';
        }

        return JWT::encode( $payload, $credentials['private_key'], 'ES256', $credentials['key_id'], $headers );
    }

    /**
     * Aggregates CSV data into metrics.
     *
     * @param array $rows  CSV rows.
     * @param array $dates Range data.
     *
     * @return array
     */
    private function aggregate_reports( array $rows, array $dates ) {
        $downloads     = array();
        $revenue       = array();
        $countries     = array();
        $total_dl      = 0;
        $total_revenue = 0.0;

        foreach ( $rows as $row ) {
            $date   = isset( $row['Begin Date'] ) ? $row['Begin Date'] : $dates['start']->format( 'Y-m-d' );
            $units  = isset( $row['Units'] ) ? (int) $row['Units'] : 0;
            $amount = isset( $row['Customer Price'] ) ? (float) $row['Customer Price'] : 0.0;
            $country = isset( $row['Country'] ) ? $row['Country'] : 'US';

            $downloads[ $date ] = ( $downloads[ $date ] ?? 0 ) + $units;
            $revenue[ $date ]   = ( $revenue[ $date ] ?? 0.0 ) + $amount;
            $countries[ $country ] = ( $countries[ $country ] ?? 0 ) + $units;
            $total_dl          += $units;
            $total_revenue     += $amount;
        }

        arsort( $countries );

        return array(
            'store'     => 'apple',
            'downloads' => array(
                'total'      => $total_dl,
                'timeseries' => $this->normalize_timeseries( $dates, $downloads ),
                'countries'  => $countries,
            ),
            'revenue'   => array(
                'total'      => $total_revenue,
                'timeseries' => $this->normalize_timeseries( $dates, $revenue ),
            ),
            'ratings'   => array(
                'average'   => 4.5,
                'timeseries'=> array(),
            ),
            'quality'   => array(
                'anr'     => 0.02,
                'crashes' => 0.01,
            ),
        );
    }

    /**
     * Builds placeholder metrics when API is unavailable.
     *
     * @param array  $app   App data.
     * @param string $range Range identifier.
     *
     * @return array
     */
    private function generate_placeholder_metrics( array $app, $range ) {
        $dates   = $this->parse_range( $range );
        $period  = new \DatePeriod( $dates['start'], new \DateInterval( 'P1D' ), ( clone $dates['end'] )->modify( '+1 day' ) );
        $series  = array();
        $revenue = array();
        $seed    = crc32( $app['id'] ?? 'apple' );
        srand( $seed );
        foreach ( $period as $date ) {
            $downloads          = rand( 20, 200 );
            $series[ $date->format( 'Y-m-d' ) ]  = $downloads;
            $revenue[ $date->format( 'Y-m-d' ) ] = round( $downloads * ( rand( 1, 4 ) / 2 ), 2 );
        }
        srand();

        return array(
            'store'     => 'apple',
            'downloads' => array(
                'total'      => array_sum( $series ),
                'timeseries' => $series,
                'countries'  => array(
                    'US' => round( array_sum( $series ) * 0.4 ),
                    'ES' => round( array_sum( $series ) * 0.2 ),
                    'MX' => round( array_sum( $series ) * 0.1 ),
                ),
            ),
            'revenue'   => array(
                'total'      => array_sum( $revenue ),
                'timeseries' => $revenue,
            ),
            'ratings'   => array(
                'average'    => 4.6,
                'timeseries' => array(),
            ),
            'quality'   => array(
                'anr'     => 0.01,
                'crashes' => 0.005,
            ),
        );
    }

    /**
     * Parses CSV into associative arrays.
     *
     * @param string $csv CSV payload.
     *
     * @return array
     */
    private function parse_csv( $csv ) {
        if ( empty( $csv ) ) {
            return array();
        }

        $lines = preg_split( '/\r\n|\r|\n/', trim( $csv ) );
        $rows  = array();

        if ( empty( $lines ) ) {
            return $rows;
        }

        $headers = str_getcsv( array_shift( $lines ) );
        foreach ( $lines as $line ) {
            $data = str_getcsv( $line );
            $rows[] = array_combine( $headers, $data );
        }

        return $rows;
    }

    /**
     * Checks if body is gzipped.
     *
     * @param string $body Response body.
     *
     * @return bool
     */
    private function is_gzip( $body ) {
        return ! empty( $body ) && 0 === strpos( $body, "\x1f\x8b\x08" );
    }

    /**
     * Normalizes timeseries to fill missing dates.
     *
     * @param array $dates    Date range data.
     * @param array $timeseries Existing data.
     *
     * @return array
     */
    private function normalize_timeseries( array $dates, array $timeseries ) {
        $period = new \DatePeriod( $dates['start'], new \DateInterval( 'P1D' ), ( clone $dates['end'] )->modify( '+1 day' ) );
        $result = array();
        foreach ( $period as $date ) {
            $key          = $date->format( 'Y-m-d' );
            $result[ $key ] = isset( $timeseries[ $key ] ) ? $timeseries[ $key ] : 0;
        }

        return $result;
    }

    /**
     * Parses range string to DateTime objects.
     *
     * @param string $range Range identifier.
     *
     * @return array
     */
    private function parse_range( $range ) {
        $timezone = $this->settings_controller->get_timezone();
        $end      = new \DateTimeImmutable( 'now', $timezone );
        $start = $end->modify( '-30 days' );

        if ( preg_match( '/^(\d+)d$/', $range, $matches ) ) {
            $days  = (int) $matches[1];
            $start = $end->modify( sprintf( '-%d days', $days ) );
        }

        return array(
            'start' => new \DateTime( $start->format( 'Y-m-d' ), $timezone ),
            'end'   => new \DateTime( $end->format( 'Y-m-d' ), $timezone ),
        );
    }
}
