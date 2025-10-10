<?php
/**
 * Google analytics service.
 *
 * @package App_Analytics_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Google\Client as Google_Client;
use Google\Service\Playdeveloperreporting as Google_PlayReporting;
use Google\Service\Playdeveloperreporting\GooglePlayDeveloperReportingV1beta1GetRevenueMetricsRequest;
use Google\Service\Playdeveloperreporting\GooglePlayDeveloperReportingV1beta1QueryAnrRateMetricSetRequest;
use Google\Service\Playdeveloperreporting\GooglePlayDeveloperReportingV1beta1QueryCrashRateMetricSetRequest;
use Google\Service\Playdeveloperreporting\GooglePlayDeveloperReportingV1beta1QueryUserAcquisitionMetricsRequest;

/**
 * Handles Google Play Developer Reporting API integration.
 */
class AAD_Google_Analytics_Service {

    /**
     * Settings controller.
     *
     * @var AAD_Settings_Controller
     */
    private $settings_controller;

    /**
     * Constructor.
     *
     * @param AAD_Settings_Controller $settings_controller Settings controller.
     */
    public function __construct( AAD_Settings_Controller $settings_controller ) {
        $this->settings_controller = $settings_controller;
    }

    /**
     * Fetches metrics for a Google Play app.
     *
     * @param array  $app   App configuration.
     * @param string $range Range identifier.
     *
     * @return array
     */
    public function fetch_metrics( array $app, $range ) {
        $credentials = $this->settings_controller->get_google_credentials();
        if ( empty( $credentials['service_account'] ) ) {
            return $this->generate_placeholder_metrics( $app, $range );
        }

        $client = $this->get_client( $credentials['service_account'] );
        if ( ! $client || ! class_exists( '\Google\\Service\\Playdeveloperreporting' ) ) {
            return $this->generate_placeholder_metrics( $app, $range );
        }

        $service = new Google_PlayReporting( $client );
        $dates   = $this->parse_range( $range );
        $package = ! empty( $app['package'] ) ? $app['package'] : '';

        if ( empty( $package ) ) {
            return $this->generate_placeholder_metrics( $app, $range );
        }

        $metrics = array(
            'store'     => 'google',
            'downloads' => array(
                'total'      => 0,
                'timeseries' => array(),
                'countries'  => array(),
            ),
            'revenue'   => array(
                'total'      => 0,
                'timeseries' => array(),
            ),
            'ratings'   => array(
                'average'    => 0,
                'timeseries' => array(),
            ),
            'quality'   => array(
                'anr'     => 0,
                'crashes' => 0,
            ),
        );

        try {
            $metrics['downloads'] = $this->query_downloads( $service, $package, $dates );
            $metrics['revenue']   = $this->query_revenue( $service, $package, $dates );
            $quality              = $this->query_quality( $service, $package, $dates );
            $metrics['quality']['anr']     = $quality['anr'];
            $metrics['quality']['crashes'] = $quality['crashes'];
            $metrics['ratings']            = $this->query_ratings( $service, $package, $dates );
        } catch ( \Exception $exception ) {
            error_log( '[AAD] Google metrics error: ' . $exception->getMessage() );
            return $this->generate_placeholder_metrics( $app, $range );
        }

        return $metrics;
    }

    /**
     * Performs a connectivity test with the Google Play Developer Reporting API.
     *
     * @return array
     */
    public function test_connection() {
        $credentials = $this->settings_controller->get_google_credentials();

        if ( empty( $credentials['service_account'] ) ) {
            return array(
                'status'  => false,
                'message' => __( 'Service account JSON is required to connect to Google Play.', 'app-analytics-dashboard' ),
            );
        }

        if ( ! class_exists( '\Google\\Client' ) || ! class_exists( '\Google\\Service\\Playdeveloperreporting' ) ) {
            return array(
                'status'  => false,
                'message' => __( 'Google API Client libraries are missing. Run composer install.', 'app-analytics-dashboard' ),
            );
        }

        try {
            $client = $this->get_client( $credentials['service_account'] );
        } catch ( \Exception $exception ) {
            return array(
                'status'  => false,
                'message' => sprintf(
                    /* translators: %s: error message thrown during Google client initialisation */
                    __( 'Failed to initialise Google client: %s', 'app-analytics-dashboard' ),
                    $exception->getMessage()
                ),
            );
        }

        if ( ! $client ) {
            return array(
                'status'  => false,
                'message' => __( 'Unable to bootstrap Google API client with the provided credentials.', 'app-analytics-dashboard' ),
            );
        }

        try {
            $token = $client->fetchAccessTokenWithAssertion();
        } catch ( \Exception $exception ) {
            return array(
                'status'  => false,
                'message' => sprintf(
                    /* translators: %s: error message returned when requesting an access token */
                    __( 'Authentication failed: %s', 'app-analytics-dashboard' ),
                    $exception->getMessage()
                ),
            );
        }

        if ( isset( $token['error'] ) ) {
            $description = isset( $token['error_description'] ) ? $token['error_description'] : $token['error'];
            return array(
                'status'  => false,
                'message' => sprintf(
                    /* translators: %s: error description returned by Google */
                    __( 'Authentication failed: %s', 'app-analytics-dashboard' ),
                    $description
                ),
            );
        }

        $status  = true;
        $message = __( 'Successfully authenticated with Google Play Developer Reporting.', 'app-analytics-dashboard' );

        $package_results = array();
        $packages        = isset( $credentials['packages'] ) && is_array( $credentials['packages'] ) ? $credentials['packages'] : array();

        if ( ! empty( $packages ) ) {
            $service = new Google_PlayReporting( $client );
            $now     = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
            $start   = $now->modify( '-1 day' );

            foreach ( $packages as $package ) {
                $package = trim( (string) $package );
                if ( empty( $package ) ) {
                    continue;
                }

                try {
                    $request = new GooglePlayDeveloperReportingV1beta1QueryAnrRateMetricSetRequest();
                    $request->setMetrics( array( 'ANR_RATE' ) );
                    $request->setTimelineSpec(
                        array(
                            'startTime' => array( 'seconds' => $start->getTimestamp() ),
                            'endTime'   => array( 'seconds' => $now->getTimestamp() ),
                        )
                    );

                    $service->apps_anrRateMetrics->query( 'apps/' . $package . '/anrRateMetrics', $request );
                    $package_results[] = array(
                        'package' => $package,
                        'status'  => true,
                    );
                } catch ( \Exception $exception ) {
                    $package_results[] = array(
                        'package' => $package,
                        'status'  => false,
                        'error'   => $exception->getMessage(),
                    );
                }
            }

            $failures = array_filter(
                $package_results,
                static function ( $result ) {
                    return empty( $result['status'] );
                }
            );

            $successes = count( $package_results ) - count( $failures );

            if ( $successes > 0 ) {
                $message .= ' ' . sprintf(
                    /* translators: %d: number of packages that passed the connectivity check */
                    _n( 'Access verified for %d package.', 'Access verified for %d packages.', $successes, 'app-analytics-dashboard' ),
                    $successes
                );
            }

            if ( ! empty( $failures ) ) {
                $status = $status && ( $successes > 0 );

                $details = array();
                foreach ( array_slice( $failures, 0, 3 ) as $failure ) {
                    $details[] = sprintf( '%s: %s', $failure['package'], $failure['error'] );
                }

                $detail_message = implode( '; ', $details );

                if ( $successes > 0 ) {
                    $message .= ' ' . sprintf(
                        /* translators: %s: list of packages with partial access issues */
                        __( 'Some packages could not be queried: %s', 'app-analytics-dashboard' ),
                        $detail_message
                    );
                } else {
                    $message = sprintf(
                        /* translators: %s: list of packages that failed the connection test */
                        __( 'Unable to query any configured packages: %s', 'app-analytics-dashboard' ),
                        $detail_message
                    );
                    $status = false;
                }
            }
        }

        return array(
            'status'  => $status,
            'message' => $message,
        );
    }

    /**
     * Creates an authenticated Google client.
     *
     * @param string $service_account JSON payload.
     *
     * @return Google_Client|null
     */
    private function get_client( $service_account ) {
        if ( ! class_exists( '\Google\Client' ) ) {
            return null;
        }

        $client = new Google_Client();
        $client->setAuthConfig( json_decode( $service_account, true ) );
        $client->addScope( 'https://www.googleapis.com/auth/playdeveloperreporting' );
        $client->setSubject( null );
        $client->setAccessType( 'offline' );

        return $client;
    }

    /**
     * Queries download metrics.
     *
     * @param Google_PlayReporting $service Service instance.
     * @param string               $package Package name.
     * @param array                $dates   Dates.
     *
     * @return array
     */
    private function query_downloads( Google_PlayReporting $service, $package, array $dates ) {
        $request = new GooglePlayDeveloperReportingV1beta1QueryUserAcquisitionMetricsRequest();
        $request->setMetrics( array( 'DAILY_USER_INSTALLS', 'DAILY_DEVICE_INSTALLS', 'DAILY_DEVICE_UNINSTALLS' ) );
        $request->setDimensions( array( 'DATE', 'COUNTRY' ) );
        $request->setTimelineSpec( array(
            'startTime' => array( 'seconds' => $dates['start']->getTimestamp() ),
            'endTime'   => array( 'seconds' => $dates['end']->modify( '+1 day' )->getTimestamp() ),
        ) );

        $response = $service->apps_userAcquisitionMetrics->query( 'apps/' . $package . '/userAcquisitionMetrics', $request );

        $downloads = array();
        $countries = array();
        $total     = 0;

        if ( ! empty( $response->getRows() ) ) {
            foreach ( $response->getRows() as $row ) {
                $date    = $row->getDimensions()['date'] ?? array();
                $country = $row->getDimensions()['country'] ?? 'US';
                $metrics = $row->getMetrics();
                $value   = isset( $metrics['dailyUserInstalls'] ) ? (int) $metrics['dailyUserInstalls'] : 0;

                $date_key = sprintf( '%04d-%02d-%02d', $date['year'], $date['month'], $date['day'] );
                $downloads[ $date_key ] = ( $downloads[ $date_key ] ?? 0 ) + $value;
                $countries[ $country ]  = ( $countries[ $country ] ?? 0 ) + $value;
                $total                 += $value;
            }
        }

        return array(
            'total'      => $total,
            'timeseries' => $this->normalize_timeseries( $dates, $downloads ),
            'countries'  => $countries,
        );
    }

    /**
     * Queries revenue metrics.
     *
     * @param Google_PlayReporting $service Service.
     * @param string               $package Package name.
     * @param array                $dates   Date range.
     *
     * @return array
     */
    private function query_revenue( Google_PlayReporting $service, $package, array $dates ) {
        $request = new GooglePlayDeveloperReportingV1beta1GetRevenueMetricsRequest();
        $request->setMetrics( array( 'REVENUE', 'NEW_SUBSCRIBERS' ) );
        $request->setTimeGranularity( 'DAILY' );
        $request->setStartDate( array( 'year' => (int) $dates['start']->format( 'Y' ), 'month' => (int) $dates['start']->format( 'm' ), 'day' => (int) $dates['start']->format( 'd' ) ) );
        $request->setEndDate( array( 'year' => (int) $dates['end']->format( 'Y' ), 'month' => (int) $dates['end']->format( 'm' ), 'day' => (int) $dates['end']->format( 'd' ) ) );

        $response = $service->apps_revenueMetrics->get( 'apps/' . $package . '/revenueMetrics', $request );

        $series = array();
        $total  = 0.0;

        if ( $response && $response->getRows() ) {
            foreach ( $response->getRows() as $row ) {
                $date      = $row->getStartTime();
                $timestamp = isset( $date['seconds'] ) ? (int) $date['seconds'] : time();
                $value     = $row->getMetricValues()['revenue'] ?? 0;
                $date_key  = gmdate( 'Y-m-d', $timestamp );

                $series[ $date_key ] = ( $series[ $date_key ] ?? 0 ) + (float) $value;
                $total              += (float) $value;
            }
        }

        return array(
            'total'      => $total,
            'timeseries' => $this->normalize_timeseries( $dates, $series ),
        );
    }

    /**
     * Queries ANR and crash metrics.
     *
     * @param Google_PlayReporting $service Service instance.
     * @param string               $package Package name.
     * @param array                $dates   Dates.
     *
     * @return array
     */
    private function query_quality( Google_PlayReporting $service, $package, array $dates ) {
        $anr_request = new GooglePlayDeveloperReportingV1beta1QueryAnrRateMetricSetRequest();
        $anr_request->setTimelineSpec( array(
            'startTime' => array( 'seconds' => $dates['start']->getTimestamp() ),
            'endTime'   => array( 'seconds' => $dates['end']->modify( '+1 day' )->getTimestamp() ),
        ) );
        $anr_request->setMetrics( array( 'ANR_RATE' ) );

        $anr_response = $service->apps_anrRateMetrics->query( 'apps/' . $package . '/anrRateMetrics', $anr_request );

        $crash_request = new GooglePlayDeveloperReportingV1beta1QueryCrashRateMetricSetRequest();
        $crash_request->setTimelineSpec( array(
            'startTime' => array( 'seconds' => $dates['start']->getTimestamp() ),
            'endTime'   => array( 'seconds' => $dates['end']->modify( '+1 day' )->getTimestamp() ),
        ) );
        $crash_request->setMetrics( array( 'CRASH_RATE' ) );

        $crash_response = $service->apps_crashRateMetrics->query( 'apps/' . $package . '/crashRateMetrics', $crash_request );

        return array(
            'anr'     => $this->extract_latest_metric( $anr_response ),
            'crashes' => $this->extract_latest_metric( $crash_response ),
        );
    }

    /**
     * Queries ratings metrics.
     *
     * @param Google_PlayReporting $service Service instance.
     * @param string               $package Package name.
     * @param array                $dates   Dates.
     *
     * @return array
     */
    private function query_ratings( Google_PlayReporting $service, $package, array $dates ) {
        $response = $service->apps_reviews->listAppsReviews( 'apps/' . $package . '/reviews', array( 'pageSize' => 50 ) );

        $reviews     = array();
        $average     = 0;
        $total_stars = 0;

        if ( $response && $response->getReviews() ) {
            foreach ( $response->getReviews() as $review ) {
                $star = $review->getStarRating();
                $total_stars += (int) $star;
                $reviews[] = array(
                    'author'  => $review->getReviewerLanguage() ?? '',
                    'rating'  => $star,
                    'comment' => $review->getText() ?? '',
                    'date'    => $review->getLastModified() ? $review->getLastModified()['seconds'] : time(),
                );
            }

            if ( count( $reviews ) > 0 ) {
                $average = round( $total_stars / count( $reviews ), 2 );
            }
        }

        return array(
            'average'    => $average,
            'timeseries' => array(),
            'recent'     => $reviews,
        );
    }

    /**
     * Extracts the latest metric from a response.
     *
     * @param mixed $response API response.
     *
     * @return float
     */
    private function extract_latest_metric( $response ) {
        if ( ! $response || ! method_exists( $response, 'getRows' ) ) {
            return 0;
        }

        $rows = $response->getRows();
        if ( empty( $rows ) ) {
            return 0;
        }

        $row = end( $rows );
        if ( isset( $row['metrics'] ) && is_array( $row['metrics'] ) ) {
            return (float) array_values( $row['metrics'] )[0];
        }

        if ( method_exists( $row, 'getMetricValues' ) ) {
            $values = $row->getMetricValues();
            if ( ! empty( $values ) ) {
                return (float) array_values( $values )[0];
            }
        }

        return 0;
    }

    /**
     * Generates placeholder metrics.
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
        $seed    = crc32( $app['id'] ?? 'google' );
        srand( $seed );
        foreach ( $period as $date ) {
            $downloads          = rand( 10, 150 );
            $series[ $date->format( 'Y-m-d' ) ]  = $downloads;
            $revenue[ $date->format( 'Y-m-d' ) ] = round( $downloads * ( rand( 1, 5 ) / 3 ), 2 );
        }
        srand();

        return array(
            'store'     => 'google',
            'downloads' => array(
                'total'      => array_sum( $series ),
                'timeseries' => $series,
                'countries'  => array(
                    'US' => round( array_sum( $series ) * 0.35 ),
                    'BR' => round( array_sum( $series ) * 0.25 ),
                    'IN' => round( array_sum( $series ) * 0.15 ),
                ),
            ),
            'revenue'   => array(
                'total'      => array_sum( $revenue ),
                'timeseries' => $revenue,
            ),
            'ratings'   => array(
                'average'    => 4.3,
                'timeseries' => array(),
                'recent'     => array(),
            ),
            'quality'   => array(
                'anr'     => 0.015,
                'crashes' => 0.02,
            ),
        );
    }

    /**
     * Normalizes timeseries to ensure all days are present.
     *
     * @param array $dates     Dates.
     * @param array $timeseries Timeseries.
     *
     * @return array
     */
    private function normalize_timeseries( array $dates, array $timeseries ) {
        $period = new \DatePeriod( $dates['start'], new \DateInterval( 'P1D' ), ( clone $dates['end'] )->modify( '+1 day' ) );
        $result = array();
        foreach ( $period as $date ) {
            $key           = $date->format( 'Y-m-d' );
            $result[ $key ] = isset( $timeseries[ $key ] ) ? $timeseries[ $key ] : 0;
        }

        return $result;
    }

    /**
     * Parses range string into date objects.
     *
     * @param string $range Range.
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
