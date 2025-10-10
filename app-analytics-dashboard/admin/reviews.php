<?php
/**
 * Reviews view.
 *
 * @var array $apps
 * @var array $series
 */
?>
<div class="wrap app-analytics-dashboard">
    <h1><?php esc_html_e( 'Ratings & Reviews', 'app-analytics-dashboard' ); ?></h1>

    <div class="reviews-grid">
        <?php foreach ( $series as $entry ) : ?>
            <div class="reviews-card">
                <h2><?php echo esc_html( $entry['app']['name'] ); ?></h2>
                <p><?php esc_html_e( 'Average rating', 'app-analytics-dashboard' ); ?>: <strong><?php echo esc_html( number_format_i18n( $entry['ratings']['average'] ?? 0, 2 ) ); ?></strong></p>
                <canvas class="reviews-chart" data-app="<?php echo esc_attr( $entry['app']['id'] ); ?>"></canvas>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Rating', 'app-analytics-dashboard' ); ?></th>
                            <th><?php esc_html_e( 'Comment', 'app-analytics-dashboard' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $entry['reviews'] ) ) : ?>
                            <tr><td colspan="2"><?php esc_html_e( 'No recent reviews.', 'app-analytics-dashboard' ); ?></td></tr>
                        <?php else : ?>
                            <?php foreach ( array_slice( $entry['reviews'], 0, 5 ) as $review ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $review['rating'] ?? '' ); ?></td>
                                    <td><?php echo esc_html( wp_trim_words( $review['comment'] ?? '', 12 ) ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    </div>
</div>
