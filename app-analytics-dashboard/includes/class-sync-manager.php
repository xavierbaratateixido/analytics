<?php
/**
 * Synchronisation manager.
 *
 * @package App_Analytics_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Coordinates sync, caching and exports.
 */
class AAD_Sync_Manager {

    /**
     * Apple service.
     *
     * @var AAD_Apple_Analytics_Service
     */
    private $apple_service;

    /**
     * Google service.
     *
     * @var AAD_Google_Analytics_Service
     */
    private $google_service;

    /**
     * Settings controller.
     *
     * @var AAD_Settings_Controller
     */
    private $settings_controller;

    /**
     * Cache option key.
     */
    private const OPTION_CACHE = 'aad_metrics_cache';

    /**
     * Last job status option key.
     */
    private const OPTION_LAST_JOB = 'aad_last_job_status';

    /**
     * Constructor.
     *
     * @param AAD_Apple_Analytics_Service  $apple_service  Apple service.
     * @param AAD_Google_Analytics_Service $google_service Google service.
     * @param AAD_Settings_Controller      $settings       Settings controller.
     */
    public function __construct( AAD_Apple_Analytics_Service $apple_service, AAD_Google_Analytics_Service $google_service, AAD_Settings_Controller $settings ) {
        $this->apple_service       = $apple_service;
        $this->google_service      = $google_service;
        $this->settings_controller = $settings;
    }

    /**
     * Runs sync manually or via cron.
     *
     * @param array|null $apps Specific apps.
     */
    public function run_sync( $apps = null ) {
        $start    = microtime( true );
        $apps     = null === $apps ? $this->settings_controller->get_active_apps() : $apps;
        $cache    = $this->get_cache();
        $success  = true;
        $messages = array();

        foreach ( $apps as $app ) {
            try {
                $metrics = $this->fetch_app_metrics( $app, '60d' );
                if ( ! empty( $metrics ) ) {
                    $cache[ $app['id'] ] = array(
                        'synced_at' => time(),
                        'store'     => $app['store'],
                        'data'      => $metrics,
                    );
                    $this->settings_controller->update_app_metadata( $app['id'], array( 'last_sync' => current_time( 'mysql' ) ) );
                }
            } catch ( \Exception $exception ) {
                $success    = false;
                $messages[] = sprintf( '%s: %s', $app['name'], $exception->getMessage() );
                $this->log( 'error', sprintf( 'Sync error for %s: %s', $app['name'], $exception->getMessage() ) );
            }
        }

        $this->set_cache( $cache );
        $duration = round( microtime( true ) - $start, 2 );

        $status = array(
            'time'     => current_time( 'mysql' ),
            'duration' => $duration,
            'success'  => $success,
            'message'  => implode( '\n', $messages ),
        );

        update_option( self::OPTION_LAST_JOB, $status, false );
        $this->log( 'info', sprintf( 'Sync completed in %ss. Success: %s', $duration, $success ? 'true' : 'false' ) );
    }

    /**
     * Cron callback.
     */
    public function handle_scheduled_sync() {
        $this->run_sync();
    }

    /**
     * Returns whether cron is enabled.
     *
     * @return bool
     */
    public function is_cron_enabled() {
        return (bool) wp_next_scheduled( 'aad_sync_event' );
    }

    /**
     * Enables or disables cron.
     *
     * @param bool $enabled Enabled flag.
     */
    public function set_cron_enabled( $enabled ) {
        $enabled = (bool) $enabled;
        if ( $enabled && ! $this->is_cron_enabled() ) {
            wp_schedule_event( time() + MINUTE_IN_SECONDS, 'daily', 'aad_sync_event' );
        } elseif ( ! $enabled ) {
            wp_clear_scheduled_hook( 'aad_sync_event' );
        }
    }

    /**
     * Returns last job status.
     *
     * @return array
     */
    public function get_last_job_status() {
        $status = get_option( self::OPTION_LAST_JOB, array() );
        return is_array( $status ) ? $status : array();
    }

    /**
     * Returns log entries.
     *
     * @return array
     */
    public function get_log_entries() {
        if ( ! file_exists( AAD_LOG_FILE ) ) {
            return array();
        }

        $lines   = array();
        $handle  = fopen( AAD_LOG_FILE, 'r' );
        if ( ! $handle ) {
            return array();
        }

        while ( ! feof( $handle ) ) {
            $line = fgets( $handle );
            if ( $line ) {
                $lines[] = trim( $line );
            }
        }

        fclose( $handle );

        return array_slice( array_reverse( $lines ), 0, 100 );
    }

    /**
     * Retrieves dashboard KPIs.
     *
     * @param array  $apps  Apps.
     * @param string $range Range.
     *
     * @return array
     */
    public function get_dashboard_kpis( array $apps, $range ) {
        $downloads = 0;
        $revenue   = 0.0;
        $ratings   = array();
        $anr       = array();
        $crashes   = array();

        foreach ( $apps as $app ) {
            $metrics   = $this->get_app_metrics( $app, $range );
            $downloads += $this->sum_range( $metrics['downloads']['timeseries'], $range );
            $revenue   += $this->sum_range( $metrics['revenue']['timeseries'], $range );
            if ( ! empty( $metrics['ratings']['average'] ) ) {
                $ratings[] = $metrics['ratings']['average'];
            }
            if ( isset( $metrics['quality']['anr'] ) ) {
                $anr[] = $metrics['quality']['anr'];
            }
            if ( isset( $metrics['quality']['crashes'] ) ) {
                $crashes[] = $metrics['quality']['crashes'];
            }
        }

        return array(
            'downloads' => $downloads,
            'revenue'   => round( $revenue, 2 ),
            'rating'    => ! empty( $ratings ) ? round( array_sum( $ratings ) / count( $ratings ), 2 ) : 0,
            'anr'       => ! empty( $anr ) ? round( array_sum( $anr ) / count( $anr ), 4 ) : 0,
            'crashes'   => ! empty( $crashes ) ? round( array_sum( $crashes ) / count( $crashes ), 4 ) : 0,
        );
    }

    /**
     * Returns download timeseries grouped by store.
     *
     * @param array  $apps  Apps.
     * @param string $range Range.
     *
     * @return array
     */
    public function get_download_timeseries( array $apps, $range ) {
        $series = array(
            'apple'  => array(),
            'google' => array(),
        );

        foreach ( $apps as $app ) {
            $metrics = $this->get_app_metrics( $app, $range );
            $store   = $app['store'];
            $series[ $store ] = $this->merge_series( $series[ $store ] ?? array(), $this->filter_range( $metrics['downloads']['timeseries'], $range ) );
        }

        $labels = array_keys( ! empty( $series['apple'] ) ? $series['apple'] : ( $series['google'] ?? array() ) );
        if ( empty( $labels ) ) {
            $labels = $this->parse_range( $range )['dates'];
            $series['apple']  = array_fill_keys( $labels, 0 );
            $series['google'] = array_fill_keys( $labels, 0 );
        }

        return array(
            'labels' => $labels,
            'apple'  => $series['apple'],
            'google' => $series['google'],
        );
    }

    /**
     * Returns revenue timeseries grouped by store.
     *
     * @param array  $apps  Apps.
     * @param string $range Range.
     *
     * @return array
     */
    public function get_revenue_timeseries( array $apps, $range ) {
        $series = array(
            'apple'  => array(),
            'google' => array(),
        );

        foreach ( $apps as $app ) {
            $metrics = $this->get_app_metrics( $app, $range );
            $store   = $app['store'];
            $series[ $store ] = $this->merge_series( $series[ $store ] ?? array(), $this->filter_range( $metrics['revenue']['timeseries'], $range ) );
        }

        $labels = array_keys( ! empty( $series['apple'] ) ? $series['apple'] : ( $series['google'] ?? array() ) );
        if ( empty( $labels ) ) {
            $labels = $this->parse_range( $range )['dates'];
            $series['apple']  = array_fill_keys( $labels, 0 );
            $series['google'] = array_fill_keys( $labels, 0 );
        }

        return array(
            'labels' => $labels,
            'apple'  => $series['apple'],
            'google' => $series['google'],
        );
    }

    /**
     * Returns top countries aggregated.
     *
     * @param array  $apps  Apps.
     * @param string $range Range.
     *
     * @return array
     */
    public function get_top_countries( array $apps, $range ) {
        $countries = array();
        foreach ( $apps as $app ) {
            $metrics = $this->get_app_metrics( $app, $range );
            if ( ! empty( $metrics['downloads']['countries'] ) ) {
                foreach ( $metrics['downloads']['countries'] as $country => $value ) {
                    $countries[ $country ] = ( $countries[ $country ] ?? 0 ) + (int) $value;
                }
            }
        }

        arsort( $countries );

        return array_slice( $countries, 0, 10, true );
    }

    /**
     * Returns per-app metrics for detail view.
     *
     * @param array  $app   App.
     * @param string $range Range.
     *
     * @return array
     */
    public function get_app_metrics( array $app, $range ) {
        $cache  = $this->get_cache();
        $cached = $cache[ $app['id'] ]['data'] ?? array();

        if ( empty( $cached ) ) {
            $this->run_sync( array( $app ) );
            $cache  = $this->get_cache();
            $cached = $cache[ $app['id'] ]['data'] ?? array();
        }

        if ( empty( $cached ) ) {
            $cached = $this->fetch_app_metrics( $app, $range );
        }

        $filtered_downloads = $this->filter_range( $cached['downloads']['timeseries'], $range );
        $filtered_revenue   = $this->filter_range( $cached['revenue']['timeseries'], $range );

        return array(
            'store'       => $app['store'],
            'downloads'   => array(
                'total'      => array_sum( $filtered_downloads ),
                'timeseries' => $filtered_downloads,
                'countries'  => $cached['downloads']['countries'] ?? array(),
            ),
            'revenue'     => array(
                'total'      => array_sum( $filtered_revenue ),
                'timeseries' => $filtered_revenue,
                'by_product' => $cached['revenue']['by_product'] ?? array(),
                'by_country' => $cached['revenue']['by_country'] ?? array(),
            ),
            'ratings'     => $cached['ratings'] ?? array(),
            'quality'     => $cached['quality'] ?? array(),
            'sessions'    => $cached['sessions'] ?? array(),
            'devices'     => $cached['devices'] ?? array(),
            'reviews'     => $cached['reviews'] ?? array(),
            'synced_at'   => $cache[ $app['id'] ]['synced_at'] ?? time(),
        );
    }

    /**
     * Returns quality metrics for overview.
     *
     * @param array $apps Apps.
     *
     * @return array
     */
    public function get_quality_metrics( array $apps ) {
        $data = array();
        foreach ( $apps as $app ) {
            $metrics = $this->get_app_metrics( $app, '30d' );
            $data[] = array(
                'app'     => $app,
                'quality' => $metrics['quality'],
            );
        }

        return $data;
    }

    /**
     * Returns revenue metrics overview.
     *
     * @param array $apps Apps.
     *
     * @return array
     */
    public function get_revenue_metrics( array $apps ) {
        $series = array();
        foreach ( $apps as $app ) {
            $metrics          = $this->get_app_metrics( $app, '90d' );
            $series[]         = array(
                'app'      => $app,
                'revenue'  => $metrics['revenue'],
                'downloads'=> $metrics['downloads'],
            );
        }

        return $series;
    }

    /**
     * Returns reviews metrics.
     *
     * @param array $apps Apps.
     *
     * @return array
     */
    public function get_review_metrics( array $apps ) {
        $data = array();
        foreach ( $apps as $app ) {
            $metrics = $this->get_app_metrics( $app, '30d' );
            $data[]  = array(
                'app'     => $app,
                'ratings' => $metrics['ratings'],
                'reviews' => $metrics['reviews'] ?? array(),
            );
        }

        return $data;
    }

    /**
     * Returns export data depending on context.
     *
     * @param string $context Context.
     * @param string $range   Range.
     * @param string $app_id  App id.
     *
     * @return array
     */
    public function get_export_data( $context, $range, $app_id ) {
        $apps = $app_id ? array( $this->settings_controller->get_app( $app_id ) ) : $this->settings_controller->get_active_apps();
        $apps = array_filter( $apps );
        $rows = array();

        foreach ( $apps as $app ) {
            $metrics = $this->get_app_metrics( $app, $range );
            switch ( $context ) {
                case 'revenue':
                    foreach ( $metrics['revenue']['timeseries'] as $date => $value ) {
                        $rows[] = array(
                            'app'     => $app['name'],
                            'store'   => $app['store'],
                            'date'    => $date,
                            'revenue' => $value,
                        );
                    }
                    break;
                case 'reviews':
                    if ( ! empty( $metrics['reviews'] ) ) {
                        foreach ( $metrics['reviews'] as $review ) {
                            $rows[] = array(
                                'app'     => $app['name'],
                                'rating'  => $review['rating'] ?? '',
                                'comment' => $review['comment'] ?? '',
                                'date'    => isset( $review['date'] ) ? gmdate( 'Y-m-d', $review['date'] ) : '',
                            );
                        }
                    }
                    break;
                default:
                    foreach ( $metrics['downloads']['timeseries'] as $date => $value ) {
                        $rows[] = array(
                            'app'       => $app['name'],
                            'store'     => $app['store'],
                            'date'      => $date,
                            'downloads' => $value,
                            'revenue'   => $metrics['revenue']['timeseries'][ $date ] ?? 0,
                        );
                    }
                    break;
            }
        }

        return $rows;
    }

    /**
     * Clears cached metrics.
     */
    public function clear_cache() {
        delete_option( self::OPTION_CACHE );
    }

    /**
     * Logs a message.
     *
     * @param string $level   Level.
     * @param string $message Message.
     */
    private function log( $level, $message ) {
        $line = sprintf( '[%s] %s: %s', wp_date( 'Y-m-d H:i:s' ), strtoupper( $level ), $message );
        file_put_contents( AAD_LOG_FILE, $line . PHP_EOL, FILE_APPEND );
    }

    /**
     * Returns cached metrics.
     *
     * @return array
     */
    private function get_cache() {
        $cache = get_option( self::OPTION_CACHE, array() );
        $cache = is_array( $cache ) ? $cache : array();

        $dirty = false;
        $cache = $this->clean_cache( $cache, $dirty );

        if ( $dirty ) {
            update_option( self::OPTION_CACHE, $cache, false );
        }

        return $cache;
    }

    /**
     * Persists cache.
     *
     * @param array $cache Cache data.
     */
    private function set_cache( array $cache ) {
        $dirty = false;
        $cache = $this->clean_cache( $cache, $dirty );
        update_option( self::OPTION_CACHE, $cache, false );
    }

    /**
     * Returns configured cache options.
     *
     * @return array
     */
    private function get_cache_config() {
        return array(
            'ttl'       => $this->settings_controller->get_cache_ttl(),
            'limit'     => $this->settings_controller->get_cache_size(),
            'retention' => $this->settings_controller->get_retention_days(),
        );
    }

    /**
     * Cleans cache entries based on TTL and retention rules.
     *
     * @param array $cache Cache data.
     * @param bool  $dirty Whether the cache was modified.
     *
     * @return array
     */
    private function clean_cache( array $cache, &$dirty = false ) {
        $config = $this->get_cache_config();
        $now    = time();

        foreach ( $cache as $app_id => $entry ) {
            $synced_at = isset( $entry['synced_at'] ) ? (int) $entry['synced_at'] : 0;
            if ( ! $synced_at || ( $now - $synced_at ) > $config['ttl'] ) {
                unset( $cache[ $app_id ] );
                $dirty = true;
                continue;
            }

            if ( empty( $entry['data'] ) || ! is_array( $entry['data'] ) ) {
                unset( $cache[ $app_id ] );
                $dirty = true;
                continue;
            }

            $pruned = $this->prune_metrics( $entry['data'], $config );
            if ( $pruned !== $entry['data'] ) {
                $cache[ $app_id ]['data'] = $pruned;
                $dirty = true;
            }
        }

        return $cache;
    }

    /**
     * Applies retention rules to stored metrics.
     *
     * @param array $metrics Metrics payload.
     * @param array $config  Cache configuration.
     *
     * @return array
     */
    private function prune_metrics( array $metrics, array $config ) {
        $series_paths = array(
            array( 'downloads', 'timeseries' ),
            array( 'revenue', 'timeseries' ),
            array( 'ratings', 'timeseries' ),
            array( 'sessions', 'timeseries' ),
        );

        foreach ( $series_paths as $path ) {
            list( $bucket, $key ) = $path;
            if ( isset( $metrics[ $bucket ][ $key ] ) && is_array( $metrics[ $bucket ][ $key ] ) ) {
                $metrics[ $bucket ][ $key ] = $this->prune_timeseries( $metrics[ $bucket ][ $key ], $config );
            }
        }

        if ( isset( $metrics['reviews'] ) && is_array( $metrics['reviews'] ) && $config['limit'] > 0 ) {
            $metrics['reviews'] = array_slice( $metrics['reviews'], -1 * $config['limit'] );
        }

        return $metrics;
    }

    /**
     * Ensures a timeseries complies with retention and cache size limits.
     *
     * @param array $series Series data keyed by date.
     * @param array $config Cache configuration.
     *
     * @return array
     */
    private function prune_timeseries( array $series, array $config ) {
        if ( empty( $series ) ) {
            return $series;
        }

        ksort( $series );

        $retention = absint( $config['retention'] );
        if ( $retention > 0 ) {
            $timezone     = $this->settings_controller->get_timezone();
            $cutoff_value = ( new \DateTimeImmutable( 'now', $timezone ) )->modify( sprintf( '-%d days', $retention ) )->format( 'Y-m-d' );

            foreach ( array_keys( $series ) as $date ) {
                if ( $date < $cutoff_value ) {
                    unset( $series[ $date ] );
                }
            }
        }

        $limit = absint( $config['limit'] );
        if ( $limit > 0 && count( $series ) > $limit ) {
            $series = array_slice( $series, -1 * $limit, null, true );
        }

        return $series;
    }

    /**
     * Fetches metrics based on store type.
     *
     * @param array  $app   App.
     * @param string $range Range.
     *
     * @return array
     */
    private function fetch_app_metrics( array $app, $range ) {
        switch ( $app['store'] ) {
            case 'apple':
                $metrics = $this->apple_service->fetch_metrics( $app, $range );
                break;
            case 'google':
                $metrics = $this->google_service->fetch_metrics( $app, $range );
                break;
            default:
                $metrics = array();
        }

        if ( empty( $metrics ) ) {
            return array();
        }

        $metrics['downloads']['timeseries'] = $metrics['downloads']['timeseries'] ?? array();
        $metrics['revenue']['timeseries']   = $metrics['revenue']['timeseries'] ?? array();

        return $metrics;
    }

    /**
     * Filters timeseries by range.
     *
     * @param array  $series Timeseries.
     * @param string $range  Range.
     *
     * @return array
     */
    private function filter_range( array $series, $range ) {
        $range_data = $this->parse_range( $range );
        $filtered   = array();

        foreach ( $range_data['dates'] as $date ) {
            if ( isset( $series[ $date ] ) ) {
                $filtered[ $date ] = $series[ $date ];
            } else {
                $filtered[ $date ] = 0;
            }
        }

        if ( empty( $filtered ) && ! empty( $series ) ) {
            return $series;
        }

        return $filtered;
    }

    /**
     * Merges two timeseries.
     *
     * @param array $base Base series.
     * @param array $incoming Incoming series.
     *
     * @return array
     */
    private function merge_series( array $base, array $incoming ) {
        foreach ( $incoming as $date => $value ) {
            $base[ $date ] = ( $base[ $date ] ?? 0 ) + $value;
        }
        ksort( $base );

        return $base;
    }

    /**
     * Sums values of a range.
     *
     * @param array  $series Timeseries.
     * @param string $range  Range.
     *
     * @return float
     */
    private function sum_range( array $series, $range ) {
        $series = $this->filter_range( $series, $range );
        return array_sum( $series );
    }

    /**
     * Parses range string into boundaries.
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

        $period = new \DatePeriod( $start, new \DateInterval( 'P1D' ), ( clone $end )->modify( '+1 day' ) );
        $dates  = array();
        foreach ( $period as $date ) {
            $dates[] = $date->format( 'Y-m-d' );
        }

        return array(
            'start' => min( $dates ),
            'end'   => max( $dates ),
            'dates' => $dates,
        );
    }
}
