<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$plugin      = Sisman_Suite::instance();
$log         = $plugin->logger->get_log();
$logSize     = $plugin->logger->get_size();
$errorCount  = $plugin->logger->count_errors();
$last_import = get_option( 'sisman_last_import', null );

// Handle clear action
if ( isset( $_POST['sisman_clear_log'] ) && check_admin_referer( 'sisman_clear_log_action' ) ) {
    $plugin->logger->clear();
    $log        = '';
    $logSize    = '0 B';
    $errorCount = 0;
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Log limpiado correctamente.', 'sisman-suite' ) . '</p></div>';
}
?>
<div class="wrap sisman-admin-wrap">
    <h1 class="sisman-title">
        <span aria-hidden="true" class="dashicons dashicons-editor-alignleft"></span>
        <?php esc_html_e( 'SISMAN Suite - Logs', 'sisman-suite' ); ?>
    </h1>

    <!-- Log Summary -->
    <div class="sisman-stats-grid sisman-log-stats" role="region" aria-label="<?php esc_attr_e( 'Resumen del log', 'sisman-suite' ); ?>">
        <div class="sisman-stat-card">
            <div class="sisman-stat-icon">
                <span aria-hidden="true" class="dashicons dashicons-media-text"></span>
            </div>
            <div class="sisman-stat-content">
                <h3><?php esc_html_e( 'Tamaño del Log', 'sisman-suite' ); ?></h3>
                <p class="sisman-stat-number"><?php echo esc_html( $logSize ); ?></p>
            </div>
        </div>

        <div class="sisman-stat-card <?php echo $errorCount > 0 ? 'sisman-stat-card--error' : ''; ?>">
            <div class="sisman-stat-icon">
                <span aria-hidden="true" class="dashicons dashicons-warning"></span>
            </div>
            <div class="sisman-stat-content">
                <h3><?php esc_html_e( 'Errores', 'sisman-suite' ); ?></h3>
                <p class="sisman-stat-number"><?php echo esc_html( $errorCount ); ?></p>
            </div>
        </div>

        <?php if ( $last_import ) : ?>
        <div class="sisman-stat-card sisman-stat-card--info">
            <div class="sisman-stat-icon">
                <span aria-hidden="true" class="dashicons dashicons-clock"></span>
            </div>
            <div class="sisman-stat-content">
                <h3><?php esc_html_e( 'Última Importación', 'sisman-suite' ); ?></h3>
                <p class="sisman-stat-number"><?php echo esc_html( $last_import['date'] ); ?></p>
                <p class="sisman-stat-label">
                    <?php
                    $total_imported = 0;
                    $total_errors   = 0;
                    if ( isset( $last_import['results'] ) ) {
                        foreach ( $last_import['results'] as $r ) {
                            if ( isset( $r['imported'] ) ) {
                                $total_imported += $r['imported'];
                            }
                            if ( isset( $r['success'] ) && ! $r['success'] ) {
                                $total_errors++;
                            }
                        }
                    }
                    echo esc_html( sprintf( '%d registros importados', $total_imported ) );
                    if ( $total_errors > 0 ) {
                        echo ' | <span style="color:#dc3232;">' . esc_html( sprintf( '%d errores', $total_errors ) ) . '</span>';
                    }
                    ?>
                </p>
            </div>
        </div>
        <?php endif; ?>

        <div class="sisman-stat-card">
            <div class="sisman-stat-icon">
                <span aria-hidden="true" class="dashicons dashicons-calendar-alt"></span>
            </div>
            <div class="sisman-stat-content">
                <h3><?php esc_html_e( 'Próxima Importación', 'sisman-suite' ); ?></h3>
                <p class="sisman-stat-number" style="font-size:14px;">
                    <?php
                    $next = wp_next_scheduled( 'sisman_scheduled_import' );
                    echo $next
                        ? esc_html( date_i18n( 'Y-m-d H:i:s', $next ) )
                        : esc_html__( 'No programada', 'sisman-suite' );
                    ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Log Viewer -->
    <div class="sisman-panel" role="region" aria-label="<?php esc_attr_e( 'Visor de logs', 'sisman-suite' ); ?>">
        <div class="sisman-panel-header">
            <h2 class="sisman-panel-title">
                <span aria-hidden="true" class="dashicons dashicons-editor-code"></span>
                <?php esc_html_e( 'Registro de Actividad', 'sisman-suite' ); ?>
            </h2>
            <div class="sisman-panel-actions">
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
                if ( $log ) {
                    // Color-code the log output
                    $lines = explode( PHP_EOL, $log );
                    foreach ( $lines as $line ) {
                        $escaped = esc_html( $line );
                        if ( strpos( $line, '[ERROR]' ) !== false ) {
                            echo '<span class="sisman-log-error">' . $escaped . '</span>' . "\n";
                        } elseif ( strpos( $line, '[WARN]' ) !== false ) {
                            echo '<span class="sisman-log-warn">' . $escaped . '</span>' . "\n";
                        } elseif ( strpos( $line, '[OK]' ) !== false ) {
                            echo '<span class="sisman-log-success">' . $escaped . '</span>' . "\n";
                        } elseif ( strpos( $line, '======' ) !== false ) {
                            echo '<span class="sisman-log-separator">' . $escaped . '</span>' . "\n";
                        } elseif ( strpos( $line, 'INICIO DE IMPORTACIÓN' ) !== false || strpos( $line, 'FIN DE IMPORTACIÓN' ) !== false ) {
                            echo '<span class="sisman-log-header">' . $escaped . '</span>' . "\n";
                        } else {
                            echo $escaped . "\n";
                        }
                    }
                } else {
                    echo esc_html__( 'El log está vacío.', 'sisman-suite' );
                }
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
            </tbody>
        </table>
    </div>
</div>
