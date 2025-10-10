<?php
/**
 * Revenue view.
 *
 * @var array $apps
 * @var array $series
 */
?>
<div class="wrap app-analytics-dashboard">
    <h1><?php esc_html_e( 'Revenue Analytics', 'app-analytics-dashboard' ); ?></h1>

    <section class="revenue-overview">
        <?php foreach ( $series as $entry ) : ?>
            <div class="revenue-card">
                <h2><?php echo esc_html( $entry['app']['name'] ); ?></h2>
                <p><?php esc_html_e( 'MTD revenue', 'app-analytics-dashboard' ); ?>: <strong><?php echo esc_html( number_format_i18n( array_sum( $entry['revenue']['timeseries'] ), 2 ) ); ?></strong></p>
                <canvas class="revenue-chart" data-labels='<?php echo esc_attr( wp_json_encode( array_keys( $entry['revenue']['timeseries'] ) ) ); ?>' data-values='<?php echo esc_attr( wp_json_encode( array_values( $entry['revenue']['timeseries'] ) ) ); ?>'></canvas>
            </div>
        <?php endforeach; ?>
    </section>

    <p class="description"><?php esc_html_e( 'Revenue charts show daily totals per app and store. Export data for detailed analysis from the dashboard.', 'app-analytics-dashboard' ); ?></p>
</div>
