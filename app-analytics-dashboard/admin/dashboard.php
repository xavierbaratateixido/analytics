<?php
/**
 * Dashboard view.
 *
 * @var array $data
 * @var string $range
 * @var array $apps
 * @var string $app_id
 * @var array $alerts
 * @var array $chart
 */
?>
<div class="wrap app-analytics-dashboard">
    <h1><?php esc_html_e( 'App Analytics Dashboard', 'app-analytics-dashboard' ); ?></h1>

    <?php foreach ( $alerts as $alert ) : ?>
        <div class="notice notice-<?php echo esc_attr( $alert['type'] ); ?>">
            <p><?php echo esc_html( $alert['message'] ); ?></p>
        </div>
    <?php endforeach; ?>

    <form method="get" action="">
        <input type="hidden" name="page" value="app-analytics-dashboard" />
        <div class="app-analytics-filters">
            <label for="aad-range"><?php esc_html_e( 'Date range', 'app-analytics-dashboard' ); ?></label>
            <select id="aad-range" name="range">
                <?php
                $ranges = array(
                    '7d'  => __( 'Last 7 days', 'app-analytics-dashboard' ),
                    '30d' => __( 'Last 30 days', 'app-analytics-dashboard' ),
                    '90d' => __( 'Last 90 days', 'app-analytics-dashboard' ),
                );
                foreach ( $ranges as $value => $label ) :
                    ?>
                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $range, $value ); ?>><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select>

            <label for="aad-app-id"><?php esc_html_e( 'Application', 'app-analytics-dashboard' ); ?></label>
            <select id="aad-app-id" name="app_id">
                <option value=""><?php esc_html_e( 'All apps', 'app-analytics-dashboard' ); ?></option>
                <?php foreach ( $apps as $app ) : ?>
                    <option value="<?php echo esc_attr( $app['id'] ); ?>" <?php selected( $app_id, $app['id'] ); ?>><?php echo esc_html( $app['name'] ); ?> (<?php echo esc_html( ucfirst( $app['store'] ) ); ?>)</option>
                <?php endforeach; ?>
            </select>

            <button class="button button-primary"><?php esc_html_e( 'Apply', 'app-analytics-dashboard' ); ?></button>
        </div>
    </form>

    <div class="app-analytics-actions">
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="inline">
            <?php wp_nonce_field( 'app_analytics_manual_sync' ); ?>
            <input type="hidden" name="action" value="app_analytics_manual_sync" />
            <button class="button button-secondary"><?php esc_html_e( 'Update now', 'app-analytics-dashboard' ); ?></button>
        </form>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="inline">
            <?php wp_nonce_field( 'app_analytics_export' ); ?>
            <input type="hidden" name="action" value="app_analytics_export" />
            <input type="hidden" name="context" value="dashboard" />
            <input type="hidden" name="range" value="<?php echo esc_attr( $range ); ?>" />
            <input type="hidden" name="app_id" value="<?php echo esc_attr( $app_id ); ?>" />
            <button class="button"><?php esc_html_e( 'Export CSV', 'app-analytics-dashboard' ); ?></button>
        </form>
    </div>

    <section class="app-analytics-kpis">
        <div class="kpi">
            <span class="label"><?php esc_html_e( 'Total downloads', 'app-analytics-dashboard' ); ?></span>
            <span class="value"><?php echo esc_html( number_format_i18n( $data['kpis']['downloads'] ) ); ?></span>
        </div>
        <div class="kpi">
            <span class="label"><?php esc_html_e( 'Revenue', 'app-analytics-dashboard' ); ?></span>
            <span class="value"><?php echo esc_html( number_format_i18n( $data['kpis']['revenue'], 2 ) ); ?></span>
        </div>
        <div class="kpi">
            <span class="label"><?php esc_html_e( 'Average rating', 'app-analytics-dashboard' ); ?></span>
            <span class="value"><?php echo esc_html( number_format_i18n( $data['kpis']['rating'], 2 ) ); ?></span>
        </div>
        <div class="kpi">
            <span class="label"><?php esc_html_e( 'ANR / Crashes', 'app-analytics-dashboard' ); ?></span>
            <span class="value"><?php echo esc_html( number_format_i18n( $data['kpis']['anr'], 3 ) . ' / ' . number_format_i18n( $data['kpis']['crashes'], 3 ) ); ?></span>
        </div>
    </section>

    <section class="app-analytics-charts">
        <div class="chart-card">
            <h2><?php esc_html_e( 'Daily downloads', 'app-analytics-dashboard' ); ?></h2>
            <canvas id="aad-downloads-chart" data-labels='<?php echo esc_attr( wp_json_encode( $data['downloads']['labels'] ) ); ?>' data-apple='<?php echo esc_attr( wp_json_encode( array_values( $data['downloads']['apple'] ) ) ); ?>' data-google='<?php echo esc_attr( wp_json_encode( array_values( $data['downloads']['google'] ) ) ); ?>'></canvas>
        </div>
        <div class="chart-card">
            <h2><?php esc_html_e( 'Revenue by store', 'app-analytics-dashboard' ); ?></h2>
            <canvas id="aad-revenue-chart" data-labels='<?php echo esc_attr( wp_json_encode( $data['revenue']['labels'] ) ); ?>' data-apple='<?php echo esc_attr( wp_json_encode( array_values( $data['revenue']['apple'] ) ) ); ?>' data-google='<?php echo esc_attr( wp_json_encode( array_values( $data['revenue']['google'] ) ) ); ?>'></canvas>
        </div>
    </section>

    <section class="app-analytics-table">
        <h2><?php esc_html_e( 'Top countries by downloads', 'app-analytics-dashboard' ); ?></h2>
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Country', 'app-analytics-dashboard' ); ?></th>
                    <th><?php esc_html_e( 'Downloads', 'app-analytics-dashboard' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $data['countries'] ) ) : ?>
                    <tr>
                        <td colspan="2"><?php esc_html_e( 'No data available for the selected period.', 'app-analytics-dashboard' ); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $data['countries'] as $country => $value ) : ?>
                        <tr>
                            <td><?php echo esc_html( $country ); ?></td>
                            <td><?php echo esc_html( number_format_i18n( $value ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</div>
