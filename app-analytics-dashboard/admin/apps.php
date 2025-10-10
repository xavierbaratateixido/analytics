<?php
/**
 * Apps view.
 *
 * @var array $apps
 */
?>
<div class="wrap app-analytics-dashboard">
    <h1><?php esc_html_e( 'Linked Apps', 'app-analytics-dashboard' ); ?></h1>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'app_analytics_bulk_apps' ); ?>
        <input type="hidden" name="action" value="app_analytics_bulk_apps" />

        <div class="bulk-actions">
            <select name="bulk_action">
                <option value=""><?php esc_html_e( 'Bulk actions', 'app-analytics-dashboard' ); ?></option>
                <option value="activate"><?php esc_html_e( 'Activate', 'app-analytics-dashboard' ); ?></option>
                <option value="deactivate"><?php esc_html_e( 'Deactivate', 'app-analytics-dashboard' ); ?></option>
                <option value="delete"><?php esc_html_e( 'Delete', 'app-analytics-dashboard' ); ?></option>
            </select>
            <button class="button"> <?php esc_html_e( 'Apply', 'app-analytics-dashboard' ); ?></button>
        </div>

        <table class="widefat fixed">
            <thead>
                <tr>
                    <td class="check-column"><input type="checkbox" class="select-all" /></td>
                    <th><?php esc_html_e( 'Name', 'app-analytics-dashboard' ); ?></th>
                    <th><?php esc_html_e( 'Store', 'app-analytics-dashboard' ); ?></th>
                    <th><?php esc_html_e( 'Identifier', 'app-analytics-dashboard' ); ?></th>
                    <th><?php esc_html_e( 'Active', 'app-analytics-dashboard' ); ?></th>
                    <th><?php esc_html_e( 'Last sync', 'app-analytics-dashboard' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'app-analytics-dashboard' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $apps ) ) : ?>
                    <tr>
                        <td colspan="7"><?php esc_html_e( 'No apps configured yet.', 'app-analytics-dashboard' ); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $apps as $app ) : ?>
                        <tr>
                            <th scope="row" class="check-column"><input type="checkbox" name="app_ids[]" value="<?php echo esc_attr( $app['id'] ); ?>" /></th>
                            <td><strong><?php echo esc_html( $app['name'] ); ?></strong></td>
                            <td><?php echo esc_html( ucfirst( $app['store'] ) ); ?></td>
                            <td><?php echo esc_html( $app['store'] === 'apple' ? $app['bundle_id'] : $app['package'] ); ?></td>
                            <td><?php echo $app['active'] ? esc_html__( 'Yes', 'app-analytics-dashboard' ) : esc_html__( 'No', 'app-analytics-dashboard' ); ?></td>
                            <td><?php echo ! empty( $app['last_sync'] ) ? esc_html( $app['last_sync'] ) : esc_html__( 'Never', 'app-analytics-dashboard' ); ?></td>
                            <td>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=app-analytics-app&app_id=' . urlencode( $app['id'] ) ) ); ?>" class="button button-small"><?php esc_html_e( 'View', 'app-analytics-dashboard' ); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </form>

    <hr />

    <h2><?php esc_html_e( 'Add new app', 'app-analytics-dashboard' ); ?></h2>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="app-analytics-add-app">
        <?php wp_nonce_field( 'app_analytics_add_app' ); ?>
        <input type="hidden" name="action" value="app_analytics_add_app" />

        <table class="form-table">
            <tr>
                <th scope="row"><label for="aad-app-name"><?php esc_html_e( 'Visible name', 'app-analytics-dashboard' ); ?></label></th>
                <td><input type="text" id="aad-app-name" name="app[name]" class="regular-text" required /></td>
            </tr>
            <tr>
                <th scope="row"><label for="aad-app-store"><?php esc_html_e( 'Store', 'app-analytics-dashboard' ); ?></label></th>
                <td>
                    <select id="aad-app-store" name="app[store]">
                        <option value="apple"><?php esc_html_e( 'Apple App Store', 'app-analytics-dashboard' ); ?></option>
                        <option value="google"><?php esc_html_e( 'Google Play', 'app-analytics-dashboard' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr class="store-field store-field-apple">
                <th scope="row"><label for="aad-app-bundle"><?php esc_html_e( 'Bundle ID', 'app-analytics-dashboard' ); ?></label></th>
                <td><input type="text" id="aad-app-bundle" name="app[bundle_id]" class="regular-text" /></td>
            </tr>
            <tr class="store-field store-field-apple">
                <th scope="row"><label for="aad-app-vendor"><?php esc_html_e( 'Vendor number', 'app-analytics-dashboard' ); ?></label></th>
                <td><input type="text" id="aad-app-vendor" name="app[vendor]" class="regular-text" /></td>
            </tr>
            <tr class="store-field store-field-google">
                <th scope="row"><label for="aad-app-package"><?php esc_html_e( 'Package name', 'app-analytics-dashboard' ); ?></label></th>
                <td><input type="text" id="aad-app-package" name="app[package]" class="regular-text" /></td>
            </tr>
        </table>

        <p class="submit">
            <button class="button button-primary"><?php esc_html_e( 'Add app', 'app-analytics-dashboard' ); ?></button>
        </p>
    </form>
</div>
