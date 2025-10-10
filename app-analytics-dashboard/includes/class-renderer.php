<?php
/**
 * Renderer utilities.
 *
 * @package App_Analytics_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Responsible for rendering admin templates and preparing data for Chart.js.
 */
class AAD_Renderer {

    /**
     * Renders a template from the admin directory.
     *
     * @param string $template Template name without extension.
     * @param array  $context  Context variables.
     */
    public function render( $template, array $context = array() ) {
        $file = AAD_PLUGIN_PATH . '/admin/' . $template . '.php';

        if ( ! file_exists( $file ) ) {
            wp_die( esc_html( sprintf( __( 'Template %s not found.', 'app-analytics-dashboard' ), $template ) ) );
        }

        $context = $this->prepare_context( $context );
        extract( $context ); // phpcs:ignore WordPress.PHP.DontExtract

        include $file;
    }

    /**
     * Prepares context with helper callbacks.
     *
     * @param array $context Context.
     *
     * @return array
     */
    private function prepare_context( array $context ) {
        $context['chart'] = array(
            'labels' => array( $this, 'chart_labels' ),
            'series' => array( $this, 'chart_series' ),
        );

        return $context;
    }

    /**
     * Returns JSON encoded labels for Chart.js.
     *
     * @param array $data Data.
     *
     * @return string
     */
    public function chart_labels( array $data ) {
        return wp_json_encode( array_keys( $data ) );
    }

    /**
     * Returns JSON encoded series values.
     *
     * @param array $data Data.
     *
     * @return string
     */
    public function chart_series( array $data ) {
        return wp_json_encode( array_values( $data ) );
    }
}
