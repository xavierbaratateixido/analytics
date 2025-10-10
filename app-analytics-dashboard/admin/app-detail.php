<?php
/**
 * App detail view.
 *
 * @var array $app
 * @var array $data
 * @var string $range
 */
?>
<div class="wrap app-analytics-dashboard">
    <h1><?php echo esc_html( sprintf( __( 'App detail: %s', 'app-analytics-dashboard' ), $app['name'] ) ); ?></h1>

    <?php
    $last_sync = ! empty( $app['last_sync'] ) ? human_time_diff( strtotime( $app['last_sync'] ), current_time( 'timestamp' ) ) : __( 'never', 'app-analytics-dashboard' );
    ?>
    <div class="app-meta">
        <span class="badge badge-<?php echo esc_attr( $app['store'] ); ?>"><?php echo esc_html( ucfirst( $app['store'] ) ); ?></span>
        <span class="identifier"><?php echo esc_html( $app['store'] === 'apple' ? $app['bundle_id'] : $app['package'] ); ?></span>
        <span class="last-sync"><?php esc_html_e( 'Last sync:', 'app-analytics-dashboard' ); ?> <?php echo esc_html( $last_sync ); ?></span>
    </div>

    <form method="get" action="">
        <input type="hidden" name="page" value="app-analytics-app" />
        <input type="hidden" name="app_id" value="<?php echo esc_attr( $app['id'] ); ?>" />
        <label for="aad-app-range"><?php esc_html_e( 'Date range', 'app-analytics-dashboard' ); ?></label>
        <select id="aad-app-range" name="range">
            <option value="7d" <?php selected( $range, '7d' ); ?>><?php esc_html_e( 'Last 7 days', 'app-analytics-dashboard' ); ?></option>
            <option value="30d" <?php selected( $range, '30d' ); ?>><?php esc_html_e( 'Last 30 days', 'app-analytics-dashboard' ); ?></option>
            <option value="90d" <?php selected( $range, '90d' ); ?>><?php esc_html_e( 'Last 90 days', 'app-analytics-dashboard' ); ?></option>
        </select>
        <button class="button"> <?php esc_html_e( 'Filter', 'app-analytics-dashboard' ); ?></button>
    </form>

    <div class="app-analytics-actions">
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="inline">
            <?php wp_nonce_field( 'app_analytics_single_sync' ); ?>
            <input type="hidden" name="action" value="app_analytics_single_sync" />
            <input type="hidden" name="app_id" value="<?php echo esc_attr( $app['id'] ); ?>" />
            <button class="button button-secondary"><?php esc_html_e( 'Sync this app', 'app-analytics-dashboard' ); ?></button>
        </form>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="inline">
            <?php wp_nonce_field( 'app_analytics_export' ); ?>
            <input type="hidden" name="action" value="app_analytics_export" />
            <input type="hidden" name="context" value="app" />
            <input type="hidden" name="app_id" value="<?php echo esc_attr( $app['id'] ); ?>" />
            <input type="hidden" name="range" value="<?php echo esc_attr( $range ); ?>" />
            <button class="button"><?php esc_html_e( 'Export CSV', 'app-analytics-dashboard' ); ?></button>
        </form>
    </div>

    <section class="app-kpis">
        <div class="kpi">
            <span class="label"><?php esc_html_e( 'Downloads', 'app-analytics-dashboard' ); ?></span>
            <span class="value"><?php echo esc_html( number_format_i18n( $data['downloads']['total'] ) ); ?></span>
        </div>
        <div class="kpi">
            <span class="label"><?php esc_html_e( 'Revenue', 'app-analytics-dashboard' ); ?></span>
            <span class="value"><?php echo esc_html( number_format_i18n( $data['revenue']['total'], 2 ) ); ?></span>
        </div>
        <div class="kpi">
            <span class="label"><?php esc_html_e( 'Average rating', 'app-analytics-dashboard' ); ?></span>
            <span class="value"><?php echo esc_html( number_format_i18n( $data['ratings']['average'] ?? 0, 2 ) ); ?></span>
        </div>
    </section>

    <div class="app-charts">
        <div class="chart-card">
            <h2><?php esc_html_e( 'Downloads & Revenue', 'app-analytics-dashboard' ); ?></h2>
            <canvas id="aad-app-downloads" data-labels='<?php echo esc_attr( wp_json_encode( array_keys( $data['downloads']['timeseries'] ) ) ); ?>' data-downloads='<?php echo esc_attr( wp_json_encode( array_values( $data['downloads']['timeseries'] ) ) ); ?>' data-revenue='<?php echo esc_attr( wp_json_encode( array_values( $data['revenue']['timeseries'] ) ) ); ?>'></canvas>
        </div>
        <div class="chart-card">
            <h2><?php esc_html_e( 'Quality', 'app-analytics-dashboard' ); ?></h2>
            <canvas id="aad-app-quality" data-anr="<?php echo esc_attr( $data['quality']['anr'] ?? 0 ); ?>" data-crashes="<?php echo esc_attr( $data['quality']['crashes'] ?? 0 ); ?>" data-rating="<?php echo esc_attr( $data['ratings']['average'] ?? 0 ); ?>"></canvas>
        </div>
    </div>

    <section class="app-tables">
        <div class="table-card">
            <h2><?php esc_html_e( 'Recent reviews', 'app-analytics-dashboard' ); ?></h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Rating', 'app-analytics-dashboard' ); ?></th>
                        <th><?php esc_html_e( 'Comment', 'app-analytics-dashboard' ); ?></th>
                        <th><?php esc_html_e( 'Date', 'app-analytics-dashboard' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $data['reviews'] ) ) : ?>
                        <tr><td colspan="3"><?php esc_html_e( 'No reviews found.', 'app-analytics-dashboard' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( array_slice( $data['reviews'], 0, 10 ) as $review ) : ?>
                            <tr>
                                <td><?php echo esc_html( $review['rating'] ?? '' ); ?></td>
                                <td><?php echo esc_html( wp_trim_words( $review['comment'] ?? '', 20 ) ); ?></td>
                                <td><?php echo isset( $review['date'] ) ? esc_html( wp_date( 'Y-m-d', $review['date'] ) ) : ''; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="table-card">
            <h2><?php esc_html_e( 'Top countries', 'app-analytics-dashboard' ); ?></h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Country', 'app-analytics-dashboard' ); ?></th>
                        <th><?php esc_html_e( 'Downloads', 'app-analytics-dashboard' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $data['downloads']['countries'] ) ) : ?>
                        <tr><td colspan="2"><?php esc_html_e( 'No country breakdown available.', 'app-analytics-dashboard' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $data['downloads']['countries'] as $country => $value ) : ?>
                            <tr>
                                <td><?php echo esc_html( $country ); ?></td>
                                <td><?php echo esc_html( number_format_i18n( $value ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
