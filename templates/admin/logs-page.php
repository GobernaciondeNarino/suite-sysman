<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$plugin      = Sysman_Suite::instance();
$log         = $plugin->logger->get_log();
$logSize     = $plugin->logger->get_size();
$errorCount  = $plugin->logger->count_errors();
$last_import = get_option( 'sysman_last_import', null );

// Handle clear action
if ( isset( $_POST['sysman_clear_log'] ) && check_admin_referer( 'sysman_clear_log_action' ) ) {
    $plugin->logger->clear();
    $log        = '';
    $logSize    = '0 B';
    $errorCount = 0;
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Log limpiado correctamente.', 'sysman-suite' ) . '</p></div>';
}
?>
<div class="wrap sysman-admin-wrap">
    <h1 class="sysman-title">
        <span aria-hidden="true" class="dashicons dashicons-editor-alignleft"></span>
        <?php esc_html_e( 'SYSMAN Suite - Logs', 'sysman-suite' ); ?>
    </h1>

    <!-- Log Summary -->
    <div class="sysman-stats-grid sysman-log-stats" role="region" aria-label="<?php esc_attr_e( 'Resumen del log', 'sysman-suite' ); ?>">
        <div class="sysman-stat-card">
            <div class="sysman-stat-icon">
                <span aria-hidden="true" class="dashicons dashicons-media-text"></span>
            </div>
            <div class="sysman-stat-content">
                <h3><?php esc_html_e( 'Tamaño del Log', 'sysman-suite' ); ?></h3>
                <p class="sysman-stat-number"><?php echo esc_html( $logSize ); ?></p>
            </div>
        </div>

        <div class="sysman-stat-card <?php echo $errorCount > 0 ? 'sysman-stat-card--error' : ''; ?>">
            <div class="sysman-stat-icon">
                <span aria-hidden="true" class="dashicons dashicons-warning"></span>
            </div>
            <div class="sysman-stat-content">
                <h3><?php esc_html_e( 'Errores', 'sysman-suite' ); ?></h3>
                <p class="sysman-stat-number"><?php echo esc_html( $errorCount ); ?></p>
            </div>
        </div>

        <?php if ( $last_import ) : ?>
        <div class="sysman-stat-card sysman-stat-card--info">
            <div class="sysman-stat-icon">
                <span aria-hidden="true" class="dashicons dashicons-clock"></span>
            </div>
            <div class="sysman-stat-content">
                <h3><?php esc_html_e( 'Última Importación', 'sysman-suite' ); ?></h3>
                <p class="sysman-stat-number"><?php echo esc_html( $last_import['date'] ); ?></p>
                <p class="sysman-stat-label">
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

        <div class="sysman-stat-card">
            <div class="sysman-stat-icon">
                <span aria-hidden="true" class="dashicons dashicons-calendar-alt"></span>
            </div>
            <div class="sysman-stat-content">
                <h3><?php esc_html_e( 'Próxima Importación', 'sysman-suite' ); ?></h3>
                <p class="sysman-stat-number" style="font-size:14px;">
                    <?php
                    $next = wp_next_scheduled( 'sysman_scheduled_import' );
                    echo $next
                        ? esc_html( date_i18n( 'Y-m-d H:i:s', $next ) )
                        : esc_html__( 'No programada', 'sysman-suite' );
                    ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Log Viewer -->
    <div class="sysman-panel" role="region" aria-label="<?php esc_attr_e( 'Visor de logs', 'sysman-suite' ); ?>">
        <div class="sysman-panel-header">
            <h2 class="sysman-panel-title">
                <span aria-hidden="true" class="dashicons dashicons-editor-code"></span>
                <?php esc_html_e( 'Registro de Actividad', 'sysman-suite' ); ?>
            </h2>
            <div class="sysman-panel-actions">
                <form method="post" style="display:inline;">
                    <?php wp_nonce_field( 'sysman_clear_log_action' ); ?>
                    <button type="submit" name="sysman_clear_log" class="button" onclick="return confirm('<?php esc_attr_e( '¿Limpiar el log?', 'sysman-suite' ); ?>');">
                        <span aria-hidden="true" class="dashicons dashicons-trash"></span>
                        <?php esc_html_e( 'Limpiar Log', 'sysman-suite' ); ?>
                    </button>
                </form>
            </div>
        </div>

        <div class="sysman-log-viewer">
            <pre class="sysman-log-content" role="log" aria-label="<?php esc_attr_e( 'Contenido del log', 'sysman-suite' ); ?>"><?php
                if ( $log ) {
                    // Color-code the log output
                    $lines = explode( PHP_EOL, $log );
                    foreach ( $lines as $line ) {
                        $escaped = esc_html( $line );
                        if ( strpos( $line, '[ERROR]' ) !== false ) {
                            echo '<span class="sysman-log-error">' . $escaped . '</span>' . "\n";
                        } elseif ( strpos( $line, '[WARN]' ) !== false ) {
                            echo '<span class="sysman-log-warn">' . $escaped . '</span>' . "\n";
                        } elseif ( strpos( $line, '[OK]' ) !== false ) {
                            echo '<span class="sysman-log-success">' . $escaped . '</span>' . "\n";
                        } elseif ( strpos( $line, '======' ) !== false ) {
                            echo '<span class="sysman-log-separator">' . $escaped . '</span>' . "\n";
                        } elseif ( strpos( $line, 'INICIO DE IMPORTACIÓN' ) !== false || strpos( $line, 'FIN DE IMPORTACIÓN' ) !== false ) {
                            echo '<span class="sysman-log-header">' . $escaped . '</span>' . "\n";
                        } else {
                            echo $escaped . "\n";
                        }
                    }
                } else {
                    echo esc_html__( 'El log está vacío.', 'sysman-suite' );
                }
            ?></pre>
        </div>
    </div>

    <!-- System Info -->
    <div class="sysman-panel" role="region" aria-label="<?php esc_attr_e( 'Información del sistema', 'sysman-suite' ); ?>">
        <h2 class="sysman-panel-title">
            <span aria-hidden="true" class="dashicons dashicons-info"></span>
            <?php esc_html_e( 'Información del Sistema', 'sysman-suite' ); ?>
        </h2>

        <table class="widefat striped">
            <tbody>
                <tr>
                    <td><strong><?php esc_html_e( 'Versión del Plugin', 'sysman-suite' ); ?></strong></td>
                    <td><?php echo esc_html( SYSMAN_SUITE_VERSION ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e( 'Versión de WordPress', 'sysman-suite' ); ?></strong></td>
                    <td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e( 'Versión de PHP', 'sysman-suite' ); ?></strong></td>
                    <td><?php echo esc_html( PHP_VERSION ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e( 'Versión de MySQL', 'sysman-suite' ); ?></strong></td>
                    <td><?php echo esc_html( $GLOBALS['wpdb']->db_version() ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e( 'Memoria Límite PHP', 'sysman-suite' ); ?></strong></td>
                    <td><?php echo esc_html( ini_get( 'memory_limit' ) ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e( 'Timeout PHP', 'sysman-suite' ); ?></strong></td>
                    <td><?php echo esc_html( ini_get( 'max_execution_time' ) . 's' ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e( 'cURL Habilitado', 'sysman-suite' ); ?></strong></td>
                    <td><?php echo function_exists( 'curl_version' ) ? esc_html__( 'Sí', 'sysman-suite' ) : esc_html__( 'No', 'sysman-suite' ); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
