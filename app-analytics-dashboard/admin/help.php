<?php
/**
 * Help view.
 */
?>
<div class="wrap app-analytics-dashboard">
    <h1><?php esc_html_e( 'Help & Documentation', 'app-analytics-dashboard' ); ?></h1>

    <section>
        <h2><?php esc_html_e( 'Quick start', 'app-analytics-dashboard' ); ?></h2>
        <ol>
            <li><?php esc_html_e( 'Configure Apple App Store Connect credentials under Connections.', 'app-analytics-dashboard' ); ?></li>
            <li><?php esc_html_e( 'Add Google Play service account JSON and package names.', 'app-analytics-dashboard' ); ?></li>
            <li><?php esc_html_e( 'Link your applications and run an initial synchronization.', 'app-analytics-dashboard' ); ?></li>
        </ol>
    </section>

    <section>
        <h2><?php esc_html_e( 'Required scopes', 'app-analytics-dashboard' ); ?></h2>
        <p><?php esc_html_e( 'Google Play: https://www.googleapis.com/auth/playdeveloperreporting', 'app-analytics-dashboard' ); ?></p>
        <p><?php esc_html_e( 'Apple App Store Connect: JWT with Sales and Trends access.', 'app-analytics-dashboard' ); ?></p>
    </section>

    <section>
        <h2><?php esc_html_e( 'Troubleshooting', 'app-analytics-dashboard' ); ?></h2>
        <ul>
            <li><?php esc_html_e( '401/403 errors: verify API permissions and that the service account has access to the app.', 'app-analytics-dashboard' ); ?></li>
            <li><?php esc_html_e( 'Expired tokens: re-upload the private key or service account JSON.', 'app-analytics-dashboard' ); ?></li>
            <li><?php esc_html_e( 'Quota limits: schedule syncs during off-peak hours.', 'app-analytics-dashboard' ); ?></li>
        </ul>
    </section>

    <section>
        <h2><?php esc_html_e( 'Metric mapping', 'app-analytics-dashboard' ); ?></h2>
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Apple metric', 'app-analytics-dashboard' ); ?></th>
                    <th><?php esc_html_e( 'Google metric', 'app-analytics-dashboard' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php esc_html_e( 'Units', 'app-analytics-dashboard' ); ?></td>
                    <td><?php esc_html_e( 'Daily user installs', 'app-analytics-dashboard' ); ?></td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'Customer price', 'app-analytics-dashboard' ); ?></td>
                    <td><?php esc_html_e( 'Revenue', 'app-analytics-dashboard' ); ?></td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'Ratings', 'app-analytics-dashboard' ); ?></td>
                    <td><?php esc_html_e( 'Star ratings', 'app-analytics-dashboard' ); ?></td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'Crashes', 'app-analytics-dashboard' ); ?></td>
                    <td><?php esc_html_e( 'Crash rate', 'app-analytics-dashboard' ); ?></td>
                </tr>
            </tbody>
        </table>
    </section>
</div>
