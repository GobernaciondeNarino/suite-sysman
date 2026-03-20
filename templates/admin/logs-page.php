<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$plugin  = Sisman_Suite::instance();
$log     = $plugin->logger->get_log();
$logSize = $plugin->logger->get_size();

// Handle clear action
if ( isset( $_POST['sisman_clear_log'] ) && check_admin_referer( 'sisman_clear_log_action' ) ) {
    $plugin->logger->clear();
    $log     = '';
    $logSize = '0 B';
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Log limpiado correctamente.', 'sisman-suite' ) . '</p></div>';
}
?>
<div class="wrap sisman-admin-wrap">
    <h1 class="sisman-title">
        <span aria-hidden="true" class="dashicons dashicons-editor-alignleft"></span>
        <?php esc_html_e( 'SISMAN Suite - Logs', 'sisman-suite' ); ?>
    </h1>

    <!-- Log Viewer -->
    <div class="sisman-panel" role="region" aria-label="<?php esc_attr_e( 'Visor de logs', 'sisman-suite' ); ?>">
        <div class="sisman-panel-header">
            <h2 class="sisman-panel-title">
                <span aria-hidden="true" class="dashicons dashicons-editor-code"></span>
                <?php esc_html_e( 'Registro de Actividad', 'sisman-suite' ); ?>
            </h2>
            <div class="sisman-panel-actions">
                <span class="sisman-log-size"><?php echo esc_html( sprintf( __( 'Tamaño: %s', 'sisman-suite' ), $logSize ) ); ?></span>
                <form method="post" style="display:inline;">
                    <?php wp_nonce_field( 'sisman_clear_log_action' ); ?>
                    <button type="submit" name="sisman_clear_log" class="button" onclick="return confirm('<?php esc_attr_e( '¿Limpiar el log?', 'sisman-suite' ); ?>');">
                        <span aria-hidden="true" class="dashicons dashicons-trash"></span>
                        <?php esc_html_e( 'Limpiar Log', 'sisman-suite' ); ?>
                    </button>
                </form>
            </div>
        </div>

        <div class="sisman-log-viewer">
            <pre class="sisman-log-content" role="log" aria-label="<?php esc_attr_e( 'Contenido del log', 'sisman-suite' ); ?>"><?php
                echo $log ? esc_html( $log ) : esc_html__( 'El log está vacío.', 'sisman-suite' );
            ?></pre>
        </div>
    </div>

    <!-- System Info -->
    <div class="sisman-panel" role="region" aria-label="<?php esc_attr_e( 'Información del sistema', 'sisman-suite' ); ?>">
        <h2 class="sisman-panel-title">
            <span aria-hidden="true" class="dashicons dashicons-info"></span>
            <?php esc_html_e( 'Información del Sistema', 'sisman-suite' ); ?>
        </h2>

        <table class="widefat striped">
            <tbody>
                <tr>
                    <td><strong><?php esc_html_e( 'Versión del Plugin', 'sisman-suite' ); ?></strong></td>
                    <td><?php echo esc_html( SISMAN_SUITE_VERSION ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e( 'Versión de WordPress', 'sisman-suite' ); ?></strong></td>
                    <td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e( 'Versión de PHP', 'sisman-suite' ); ?></strong></td>
                    <td><?php echo esc_html( PHP_VERSION ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e( 'Versión de MySQL', 'sisman-suite' ); ?></strong></td>
                    <td><?php echo esc_html( $GLOBALS['wpdb']->db_version() ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e( 'Memoria Límite PHP', 'sisman-suite' ); ?></strong></td>
                    <td><?php echo esc_html( ini_get( 'memory_limit' ) ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e( 'Timeout PHP', 'sisman-suite' ); ?></strong></td>
                    <td><?php echo esc_html( ini_get( 'max_execution_time' ) . 's' ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e( 'cURL Habilitado', 'sisman-suite' ); ?></strong></td>
                    <td><?php echo function_exists( 'curl_version' ) ? esc_html__( 'Sí', 'sisman-suite' ) : esc_html__( 'No', 'sisman-suite' ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e( 'Próxima importación programada', 'sisman-suite' ); ?></strong></td>
                    <td>
                        <?php
                        $next = wp_next_scheduled( 'sisman_scheduled_import' );
                        echo $next
                            ? esc_html( date_i18n( 'Y-m-d H:i:s', $next ) )
                            : esc_html__( 'No programada', 'sisman-suite' );
                        ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
