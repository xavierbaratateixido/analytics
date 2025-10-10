<?php
/**
 * Settings view.
 *
 * @var array $settings
 * @var array $roles
 */
?>
<div class="wrap app-analytics-dashboard">
    <h1><?php esc_html_e( 'Plugin Settings', 'app-analytics-dashboard' ); ?></h1>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'app_analytics_save_settings' ); ?>
        <input type="hidden" name="action" value="app_analytics_save_settings" />

        <table class="form-table">
            <tr>
                <th scope="row"><label for="aad-timezone"><?php esc_html_e( 'Timezone', 'app-analytics-dashboard' ); ?></label></th>
                <td><input type="text" id="aad-timezone" name="aad_settings[timezone]" value="<?php echo esc_attr( $settings['timezone'] ); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="aad-date-format"><?php esc_html_e( 'Date format', 'app-analytics-dashboard' ); ?></label></th>
                <td><input type="text" id="aad-date-format" name="aad_settings[date_format]" value="<?php echo esc_attr( $settings['date_format'] ); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="aad-currency"><?php esc_html_e( 'Default currency', 'app-analytics-dashboard' ); ?></label></th>
                <td><input type="text" id="aad-currency" name="aad_settings[currency]" value="<?php echo esc_attr( $settings['currency'] ); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="aad-retention"><?php esc_html_e( 'Data retention (days)', 'app-analytics-dashboard' ); ?></label></th>
                <td><input type="number" id="aad-retention" name="aad_settings[retention_days]" value="<?php echo esc_attr( $settings['retention_days'] ); ?>" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="aad-cache-size"><?php esc_html_e( 'Cache size (entries)', 'app-analytics-dashboard' ); ?></label></th>
                <td><input type="number" id="aad-cache-size" name="aad_settings[cache_size]" value="<?php echo esc_attr( $settings['cache_size'] ); ?>" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="aad-cache-ttl"><?php esc_html_e( 'Cache TTL (seconds)', 'app-analytics-dashboard' ); ?></label></th>
                <td><input type="number" id="aad-cache-ttl" name="aad_settings[cache_ttl]" value="<?php echo esc_attr( $settings['cache_ttl'] ); ?>" /></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Read-only roles', 'app-analytics-dashboard' ); ?></th>
                <td>
                    <?php foreach ( $roles as $role_key => $role ) : ?>
                        <label>
                            <input type="checkbox" name="aad_settings[readonly_roles][]" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( in_array( $role_key, $settings['readonly_roles'], true ) ); ?> />
                            <?php echo esc_html( translate_user_role( $role['name'] ) ); ?>
                        </label><br />
                    <?php endforeach; ?>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button class="button button-secondary" name="clear_cache" value="1"><?php esc_html_e( 'Clear cache', 'app-analytics-dashboard' ); ?></button>
            <button class="button button-primary"><?php esc_html_e( 'Save settings', 'app-analytics-dashboard' ); ?></button>
        </p>
    </form>
</div>
