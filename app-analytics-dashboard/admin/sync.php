<?php
/**
 * Sync view.
 *
 * @var bool  $cron_enabled
 * @var array $last_job
 * @var array $log_entries
 */
?>
<div class="wrap app-analytics-dashboard">
    <h1><?php esc_html_e( 'Synchronization', 'app-analytics-dashboard' ); ?></h1>

    <div class="sync-actions">
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="inline">
            <?php wp_nonce_field( 'app_analytics_manual_sync' ); ?>
            <input type="hidden" name="action" value="app_analytics_manual_sync" />
            <button class="button button-primary"><?php esc_html_e( 'Run global sync', 'app-analytics-dashboard' ); ?></button>
        </form>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="inline">
            <?php wp_nonce_field( 'app_analytics_toggle_cron' ); ?>
            <input type="hidden" name="action" value="app_analytics_toggle_cron" />
            <input type="hidden" name="cron_enabled" value="<?php echo $cron_enabled ? '0' : '1'; ?>" />
            <button class="button"><?php echo $cron_enabled ? esc_html__( 'Disable daily cron', 'app-analytics-dashboard' ) : esc_html__( 'Enable daily cron', 'app-analytics-dashboard' ); ?></button>
        </form>
    </div>

    <div class="sync-status">
        <h2><?php esc_html_e( 'Last job status', 'app-analytics-dashboard' ); ?></h2>
        <?php if ( empty( $last_job ) ) : ?>
            <p><?php esc_html_e( 'No jobs executed yet.', 'app-analytics-dashboard' ); ?></p>
        <?php else : ?>
            <ul>
                <li><?php esc_html_e( 'Date', 'app-analytics-dashboard' ); ?>: <?php echo esc_html( $last_job['time'] ); ?></li>
                <li><?php esc_html_e( 'Duration', 'app-analytics-dashboard' ); ?>: <?php echo esc_html( $last_job['duration'] . 's' ); ?></li>
                <li><?php esc_html_e( 'Result', 'app-analytics-dashboard' ); ?>: <?php echo $last_job['success'] ? esc_html__( 'Success', 'app-analytics-dashboard' ) : esc_html__( 'Errors', 'app-analytics-dashboard' ); ?></li>
                <?php if ( ! empty( $last_job['message'] ) ) : ?>
                    <li><?php esc_html_e( 'Message', 'app-analytics-dashboard' ); ?>: <?php echo esc_html( $last_job['message'] ); ?></li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="log-viewer">
        <h2><?php esc_html_e( 'Log entries', 'app-analytics-dashboard' ); ?></h2>
        <?php if ( empty( $log_entries ) ) : ?>
            <p><?php esc_html_e( 'Log is empty.', 'app-analytics-dashboard' ); ?></p>
        <?php else : ?>
            <textarea readonly rows="10" class="widefat code"><?php echo esc_textarea( implode( "\n", $log_entries ) ); ?></textarea>
        <?php endif; ?>
    </div>
</div>
