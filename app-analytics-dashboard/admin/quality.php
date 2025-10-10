<?php
/**
 * Quality & Stability view.
 *
 * @var array $apps
 * @var array $series
 */
?>
<div class="wrap app-analytics-dashboard">
    <h1><?php esc_html_e( 'Quality & Stability', 'app-analytics-dashboard' ); ?></h1>

    <div class="quality-grid">
        <?php foreach ( $series as $entry ) : ?>
            <div class="quality-card">
                <h2><?php echo esc_html( $entry['app']['name'] ); ?> <span class="badge badge-<?php echo esc_attr( $entry['app']['store'] ); ?>"><?php echo esc_html( ucfirst( $entry['app']['store'] ) ); ?></span></h2>
                <p><?php esc_html_e( 'ANR rate', 'app-analytics-dashboard' ); ?>: <strong><?php echo esc_html( number_format_i18n( $entry['quality']['anr'] ?? 0, 3 ) ); ?></strong></p>
                <p><?php esc_html_e( 'Crash rate', 'app-analytics-dashboard' ); ?>: <strong><?php echo esc_html( number_format_i18n( $entry['quality']['crashes'] ?? 0, 3 ) ); ?></strong></p>
                <canvas class="quality-chart" data-app="<?php echo esc_attr( $entry['app']['id'] ); ?>"></canvas>
            </div>
        <?php endforeach; ?>
    </div>

    <p class="description"><?php esc_html_e( 'Charts display ANR and crash rate trends per version when data is available.', 'app-analytics-dashboard' ); ?></p>
</div>
