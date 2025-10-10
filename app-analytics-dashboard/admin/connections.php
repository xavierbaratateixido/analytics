<?php
/**
 * Connections view.
 *
 * @var array $apple_credentials
 * @var array $google_credentials
 * @var array $test_results
 */
?>
<div class="wrap app-analytics-dashboard">
    <h1><?php esc_html_e( 'API Connections', 'app-analytics-dashboard' ); ?></h1>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="app-analytics-connections">
        <?php wp_nonce_field( 'app_analytics_save_connections' ); ?>
        <input type="hidden" name="action" value="app_analytics_save_connections" />

        <h2 class="nav-tab-wrapper">
            <a href="#apple" class="nav-tab nav-tab-active" data-tab="apple"><?php esc_html_e( 'Apple App Store Connect', 'app-analytics-dashboard' ); ?></a>
            <a href="#google" class="nav-tab" data-tab="google"><?php esc_html_e( 'Google Play', 'app-analytics-dashboard' ); ?></a>
        </h2>

        <div id="apple" class="tab-panel active">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="apple-key-id"><?php esc_html_e( 'Key ID', 'app-analytics-dashboard' ); ?></label></th>
                    <td><input type="text" id="apple-key-id" name="apple[key_id]" class="regular-text" value="<?php echo esc_attr( $apple_credentials['key_id'] ?? '' ); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="apple-issuer-id"><?php esc_html_e( 'Issuer ID', 'app-analytics-dashboard' ); ?></label></th>
                    <td><input type="text" id="apple-issuer-id" name="apple[issuer_id]" class="regular-text" value="<?php echo esc_attr( $apple_credentials['issuer_id'] ?? '' ); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="apple-private-key"><?php esc_html_e( 'Private Key (.p8)', 'app-analytics-dashboard' ); ?></label></th>
                    <td><textarea id="apple-private-key" name="apple[private_key]" rows="6" class="large-text" placeholder="-----BEGIN PRIVATE KEY-----"><?php echo esc_textarea( $apple_credentials['private_key'] ?? '' ); ?></textarea></td>
                </tr>
                <tr>
                    <th scope="row"><label for="apple-vendor"><?php esc_html_e( 'Vendor Number', 'app-analytics-dashboard' ); ?></label></th>
                    <td><input type="text" id="apple-vendor" name="apple[vendor]" class="regular-text" value="<?php echo esc_attr( $apple_credentials['vendor'] ?? '' ); ?>" /></td>
                </tr>
            </table>

            <?php
            $apple_status      = $test_results['apple']['status'];
            $apple_status_class = 'status-pending';
            if ( true === $apple_status ) {
                $apple_status_class = 'status-ok';
            } elseif ( false === $apple_status ) {
                $apple_status_class = 'status-error';
            }
            ?>
            <p class="connection-status <?php echo esc_attr( $apple_status_class ); ?>">
                <?php echo esc_html( $test_results['apple']['message'] ); ?>
                <?php if ( ! empty( $test_results['apple']['checked'] ) ) : ?>
                    <span class="timestamp"><?php echo esc_html( wp_date( 'Y-m-d H:i', $test_results['apple']['checked'] ) ); ?></span>
                <?php endif; ?>
            </p>
        </div>

        <div id="google" class="tab-panel">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="google-service-account"><?php esc_html_e( 'Service Account JSON', 'app-analytics-dashboard' ); ?></label></th>
                    <td><textarea id="google-service-account" name="google[service_account]" rows="6" class="large-text" placeholder="{ \"type\": \"service_account\" }"><?php echo esc_textarea( $google_credentials['service_account'] ?? '' ); ?></textarea></td>
                </tr>
                <tr>
                    <th scope="row"><label for="google-packages"><?php esc_html_e( 'Package names', 'app-analytics-dashboard' ); ?></label></th>
                    <td><input type="text" id="google-packages" name="google[packages][]" class="regular-text" value="<?php echo esc_attr( implode( ',', $google_credentials['packages'] ?? array() ) ); ?>" />
                        <p class="description"><?php esc_html_e( 'Separate multiple package names with commas.', 'app-analytics-dashboard' ); ?></p>
                    </td>
                </tr>
            </table>

            <?php
            $google_status       = $test_results['google']['status'];
            $google_status_class = 'status-pending';
            if ( true === $google_status ) {
                $google_status_class = 'status-ok';
            } elseif ( false === $google_status ) {
                $google_status_class = 'status-error';
            }
            ?>
            <p class="connection-status <?php echo esc_attr( $google_status_class ); ?>">
                <?php echo esc_html( $test_results['google']['message'] ); ?>
                <?php if ( ! empty( $test_results['google']['checked'] ) ) : ?>
                    <span class="timestamp"><?php echo esc_html( wp_date( 'Y-m-d H:i', $test_results['google']['checked'] ) ); ?></span>
                <?php endif; ?>
            </p>
        </div>

        <p class="submit">
            <button class="button button-secondary" name="test_connection" value="1"><?php esc_html_e( 'Test connection', 'app-analytics-dashboard' ); ?></button>
            <button class="button button-primary"><?php esc_html_e( 'Save credentials', 'app-analytics-dashboard' ); ?></button>
        </p>
    </form>
</div>
