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

    <!-- Header -->
    <div class="sysman-page-header">
        <div class="sysman-page-header-content">
            <div class="sysman-header-logo">
                <span class="dashicons dashicons-media-text" aria-hidden="true" style="font-size:32px;width:32px;height:32px;color:#1a5632;"></span>
            </div>
            <div>
                <h1 class="sysman-page-title"><?php esc_html_e( 'Logs del Sistema', 'sysman-suite' ); ?></h1>
                <p class="sysman-page-subtitle"><?php esc_html_e( 'Registro de actividad e importaciones', 'sysman-suite' ); ?></p>
            </div>
        </div>
    </div>

    <!-- Log Summary -->
    <div class="sysman-dashboard-stats">
        <div class="sysman-dash-stat">
            <div class="sysman-dash-stat-icon sysman-dash-stat-icon--info">
                <span class="dashicons dashicons-media-text" aria-hidden="true"></span>
            </div>
            <div class="sysman-dash-stat-body">
                <span class="sysman-dash-stat-value"><?php echo esc_html( $logSize ); ?></span>
                <span class="sysman-dash-stat-label"><?php esc_html_e( 'Tamaño del Log', 'sysman-suite' ); ?></span>
            </div>
        </div>

        <div class="sysman-dash-stat">
            <div class="sysman-dash-stat-icon <?php echo $errorCount > 0 ? 'sysman-dash-stat-icon--danger' : 'sysman-dash-stat-icon--success'; ?>">
                <span class="dashicons dashicons-<?php echo $errorCount > 0 ? 'warning' : 'yes-alt'; ?>" aria-hidden="true"></span>
            </div>
            <div class="sysman-dash-stat-body">
                <span class="sysman-dash-stat-value"><?php echo esc_html( $errorCount ); ?></span>
                <span class="sysman-dash-stat-label"><?php esc_html_e( 'Errores', 'sysman-suite' ); ?></span>
            </div>
        </div>

        <?php if ( $last_import ) : ?>
        <div class="sysman-dash-stat">
            <div class="sysman-dash-stat-icon sysman-dash-stat-icon--warning">
                <span class="dashicons dashicons-clock" aria-hidden="true"></span>
            </div>
            <div class="sysman-dash-stat-body">
                <span class="sysman-dash-stat-value" style="font-size:14px;"><?php echo esc_html( $last_import['date'] ); ?></span>
                <span class="sysman-dash-stat-label"><?php esc_html_e( 'Última Importación', 'sysman-suite' ); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <div class="sysman-dash-stat">
            <div class="sysman-dash-stat-icon sysman-dash-stat-icon--primary">
                <span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
            </div>
            <div class="sysman-dash-stat-body">
                <span class="sysman-dash-stat-value" style="font-size:14px;">
                    <?php
                    $next = wp_next_scheduled( 'sysman_scheduled_import' );
                    echo $next
                        ? esc_html( date_i18n( 'Y-m-d H:i:s', $next ) )
                        : esc_html__( 'No programada', 'sysman-suite' );
                    ?>
                </span>
                <span class="sysman-dash-stat-label"><?php esc_html_e( 'Próxima Importación', 'sysman-suite' ); ?></span>
            </div>
        </div>
    </div>

    <!-- Log Viewer -->
    <div class="sysman-card">
        <div class="sysman-card-header">
            <h2>
                <span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
                <?php esc_html_e( 'Registro de Actividad', 'sysman-suite' ); ?>
            </h2>
            <form method="post" style="display:inline;">
                <?php wp_nonce_field( 'sysman_clear_log_action' ); ?>
                <button type="submit" name="sysman_clear_log" class="button" onclick="return confirm('<?php esc_attr_e( '¿Limpiar el log?', 'sysman-suite' ); ?>');">
                    <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                    <?php esc_html_e( 'Limpiar', 'sysman-suite' ); ?>
                </button>
            </form>
        </div>
        <div class="sysman-card-body" style="padding: 0;">
            <div class="sysman-log-viewer">
                <pre class="sysman-log-content" role="log"><?php
                    if ( $log ) {
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
                        echo esc_html__( 'El log está vacío. Realice una importación para ver la actividad.', 'sysman-suite' );
                    }
                ?></pre>
            </div>
        </div>
    </div>

    <!-- System Info -->
    <div class="sysman-card">
        <div class="sysman-card-header">
            <h2>
                <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                <?php esc_html_e( 'Información del Sistema', 'sysman-suite' ); ?>
            </h2>
        </div>
        <div class="sysman-card-body">
            <div class="sysman-sysinfo-grid">
                <div class="sysman-sysinfo-item">
                    <span class="sysman-sysinfo-label"><?php esc_html_e( 'Versión del Plugin', 'sysman-suite' ); ?></span>
                    <span class="sysman-sysinfo-value"><?php echo esc_html( SYSMAN_SUITE_VERSION ); ?></span>
                </div>
                <div class="sysman-sysinfo-item">
                    <span class="sysman-sysinfo-label"><?php esc_html_e( 'WordPress', 'sysman-suite' ); ?></span>
                    <span class="sysman-sysinfo-value"><?php echo esc_html( get_bloginfo( 'version' ) ); ?></span>
                </div>
                <div class="sysman-sysinfo-item">
                    <span class="sysman-sysinfo-label"><?php esc_html_e( 'PHP', 'sysman-suite' ); ?></span>
                    <span class="sysman-sysinfo-value"><?php echo esc_html( PHP_VERSION ); ?></span>
                </div>
                <div class="sysman-sysinfo-item">
                    <span class="sysman-sysinfo-label"><?php esc_html_e( 'MySQL', 'sysman-suite' ); ?></span>
                    <span class="sysman-sysinfo-value"><?php echo esc_html( $GLOBALS['wpdb']->db_version() ); ?></span>
                </div>
                <div class="sysman-sysinfo-item">
                    <span class="sysman-sysinfo-label"><?php esc_html_e( 'Memoria PHP', 'sysman-suite' ); ?></span>
                    <span class="sysman-sysinfo-value"><?php echo esc_html( ini_get( 'memory_limit' ) ); ?></span>
                </div>
                <div class="sysman-sysinfo-item">
                    <span class="sysman-sysinfo-label"><?php esc_html_e( 'Timeout PHP', 'sysman-suite' ); ?></span>
                    <span class="sysman-sysinfo-value"><?php echo esc_html( ini_get( 'max_execution_time' ) . 's' ); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>
