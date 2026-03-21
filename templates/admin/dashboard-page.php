<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$plugin      = Sysman_Suite::instance();
$stats       = $plugin->database->get_stats();
$last_import = get_option( 'sysman_last_import', null );
$total_records = 0;
foreach ( $stats as $info ) {
    $total_records += $info['count'];
}

$db_status = $plugin->database->check_tables_status();
$log_errors = $plugin->logger->count_errors();
$next_cron  = wp_next_scheduled( 'sysman_scheduled_import' );
?>
<div class="wrap sysman-admin-wrap">

    <!-- Header -->
    <div class="sysman-page-header">
        <div class="sysman-page-header-content">
            <div class="sysman-header-logo">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                    <rect width="40" height="40" rx="8" fill="#1a5632"/>
                    <path d="M10 28V18l5-6 5 4 5-8 5 6v14H10z" fill="#fff" opacity="0.9"/>
                    <path d="M10 28l5-10 5 4 5-8 5 6" stroke="#ffc53b" stroke-width="2" fill="none"/>
                </svg>
            </div>
            <div>
                <h1 class="sysman-page-title"><?php esc_html_e( 'SYSMAN Suite', 'sysman-suite' ); ?></h1>
                <p class="sysman-page-subtitle"><?php esc_html_e( 'Sistema de Gestión Presupuestal - Gobernación de Nariño', 'sysman-suite' ); ?></p>
            </div>
        </div>
        <div class="sysman-header-actions">
            <span class="sysman-version-badge">v<?php echo esc_html( SYSMAN_SUITE_VERSION ); ?></span>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=sysman-import' ) ); ?>" class="button button-primary">
                <span class="dashicons dashicons-download" aria-hidden="true"></span>
                <?php esc_html_e( 'Importar Datos', 'sysman-suite' ); ?>
            </a>
        </div>
    </div>

    <!-- Status Banner -->
    <?php if ( ! $db_status['all_exist'] ) : ?>
    <div class="sysman-alert sysman-alert-warning">
        <span class="dashicons dashicons-warning" aria-hidden="true"></span>
        <div>
            <strong><?php esc_html_e( 'Tablas pendientes de creación', 'sysman-suite' ); ?></strong>
            <p><?php esc_html_e( 'Algunas tablas no existen en la base de datos. Desactive y reactive el plugin, o haga clic en el botón a continuación.', 'sysman-suite' ); ?></p>
            <form method="post" style="display:inline;">
                <?php wp_nonce_field( 'sysman_create_tables_action' ); ?>
                <button type="submit" name="sysman_create_tables" class="button"><?php esc_html_e( 'Crear Tablas Ahora', 'sysman-suite' ); ?></button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php
    // Handle table creation request
    if ( isset( $_POST['sysman_create_tables'] ) && check_admin_referer( 'sysman_create_tables_action' ) ) {
        $plugin->database->create_tables();
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Tablas creadas correctamente. Recargue la página.', 'sysman-suite' ) . '</p></div>';
    }
    ?>

    <!-- Quick Stats -->
    <div class="sysman-dashboard-stats">
        <div class="sysman-dash-stat">
            <div class="sysman-dash-stat-icon sysman-dash-stat-icon--primary">
                <span class="dashicons dashicons-database" aria-hidden="true"></span>
            </div>
            <div class="sysman-dash-stat-body">
                <span class="sysman-dash-stat-value"><?php echo esc_html( number_format( $total_records ) ); ?></span>
                <span class="sysman-dash-stat-label"><?php esc_html_e( 'Total Registros', 'sysman-suite' ); ?></span>
            </div>
        </div>

        <div class="sysman-dash-stat">
            <div class="sysman-dash-stat-icon sysman-dash-stat-icon--success">
                <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
            </div>
            <div class="sysman-dash-stat-body">
                <span class="sysman-dash-stat-value"><?php echo esc_html( $db_status['existing_count'] ); ?>/3</span>
                <span class="sysman-dash-stat-label"><?php esc_html_e( 'Tablas Activas', 'sysman-suite' ); ?></span>
            </div>
        </div>

        <div class="sysman-dash-stat">
            <div class="sysman-dash-stat-icon <?php echo $log_errors > 0 ? 'sysman-dash-stat-icon--danger' : 'sysman-dash-stat-icon--info'; ?>">
                <span class="dashicons dashicons-<?php echo $log_errors > 0 ? 'warning' : 'shield'; ?>" aria-hidden="true"></span>
            </div>
            <div class="sysman-dash-stat-body">
                <span class="sysman-dash-stat-value"><?php echo esc_html( $log_errors ); ?></span>
                <span class="sysman-dash-stat-label"><?php esc_html_e( 'Errores en Log', 'sysman-suite' ); ?></span>
            </div>
        </div>

        <div class="sysman-dash-stat">
            <div class="sysman-dash-stat-icon sysman-dash-stat-icon--warning">
                <span class="dashicons dashicons-clock" aria-hidden="true"></span>
            </div>
            <div class="sysman-dash-stat-body">
                <span class="sysman-dash-stat-value" style="font-size:14px;">
                    <?php
                    if ( $last_import ) {
                        echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $last_import['date'] ) ) );
                    } else {
                        esc_html_e( 'Nunca', 'sysman-suite' );
                    }
                    ?>
                </span>
                <span class="sysman-dash-stat-label"><?php esc_html_e( 'Última Importación', 'sysman-suite' ); ?></span>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="sysman-dashboard-grid">

        <!-- Tables Overview -->
        <div class="sysman-card">
            <div class="sysman-card-header">
                <h2>
                    <span class="dashicons dashicons-editor-table" aria-hidden="true"></span>
                    <?php esc_html_e( 'Estado de las Tablas', 'sysman-suite' ); ?>
                </h2>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=sysman-records' ) ); ?>" class="sysman-card-link">
                    <?php esc_html_e( 'Ver registros', 'sysman-suite' ); ?> &rarr;
                </a>
            </div>
            <div class="sysman-card-body">
                <div class="sysman-table-status-list">
                    <?php foreach ( $stats as $table => $info ) :
                        $exists = $db_status['tables'][ $table ] ?? false;
                        $short_name = str_replace( $GLOBALS['wpdb']->prefix . 'sysman_', '', $table );
                    ?>
                    <div class="sysman-table-status-item">
                        <div class="sysman-table-status-left">
                            <span class="sysman-status-dot <?php echo $exists ? 'sysman-status-dot--ok' : 'sysman-status-dot--error'; ?>"></span>
                            <div>
                                <strong><?php echo esc_html( $info['label'] ); ?></strong>
                                <span class="sysman-table-name"><?php echo esc_html( $short_name ); ?></span>
                            </div>
                        </div>
                        <div class="sysman-table-status-right">
                            <span class="sysman-record-count"><?php echo esc_html( number_format( $info['count'] ) ); ?></span>
                            <span class="sysman-record-label"><?php esc_html_e( 'registros', 'sysman-suite' ); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Last Import Results -->
        <div class="sysman-card">
            <div class="sysman-card-header">
                <h2>
                    <span class="dashicons dashicons-migrate" aria-hidden="true"></span>
                    <?php esc_html_e( 'Última Importación', 'sysman-suite' ); ?>
                </h2>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=sysman-import' ) ); ?>" class="sysman-card-link">
                    <?php esc_html_e( 'Importar', 'sysman-suite' ); ?> &rarr;
                </a>
            </div>
            <div class="sysman-card-body">
                <?php if ( $last_import ) : ?>
                <div class="sysman-last-import-info">
                    <div class="sysman-import-meta">
                        <span><strong><?php esc_html_e( 'Fecha:', 'sysman-suite' ); ?></strong> <?php echo esc_html( $last_import['date'] ); ?></span>
                        <span><strong><?php esc_html_e( 'Año:', 'sysman-suite' ); ?></strong> <?php echo esc_html( $last_import['anio'] ); ?></span>
                        <span><strong><?php esc_html_e( 'Mes:', 'sysman-suite' ); ?></strong> <?php echo esc_html( $last_import['mes'] ); ?></span>
                        <span><strong><?php esc_html_e( 'Compañía:', 'sysman-suite' ); ?></strong> <?php echo esc_html( $last_import['compania'] ); ?></span>
                    </div>
                    <?php if ( ! empty( $last_import['results'] ) ) : ?>
                    <table class="sysman-mini-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Informe', 'sysman-suite' ); ?></th>
                                <th><?php esc_html_e( 'Estado', 'sysman-suite' ); ?></th>
                                <th><?php esc_html_e( 'Registros', 'sysman-suite' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $report_labels = [
                                'ejecucion' => 'Ejecución Presupuestal',
                                'auxiliar'  => 'Auxiliar por Cuentas',
                                'plan'      => 'Plan Presupuestal',
                            ];
                            foreach ( $last_import['results'] as $key => $r ) :
                            ?>
                            <tr>
                                <td><?php echo esc_html( $report_labels[ $key ] ?? $key ); ?></td>
                                <td>
                                    <?php if ( ! empty( $r['success'] ) ) : ?>
                                        <span class="sysman-badge sysman-badge--success"><?php esc_html_e( 'OK', 'sysman-suite' ); ?></span>
                                    <?php else : ?>
                                        <span class="sysman-badge sysman-badge--error"><?php esc_html_e( 'Error', 'sysman-suite' ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo isset( $r['imported'] ) ? esc_html( number_format( $r['imported'] ) ) : '-'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
                <?php else : ?>
                <div class="sysman-empty-state">
                    <span class="dashicons dashicons-cloud-upload" aria-hidden="true"></span>
                    <p><?php esc_html_e( 'No se ha realizado ninguna importación.', 'sysman-suite' ); ?></p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=sysman-import' ) ); ?>" class="button button-primary">
                        <?php esc_html_e( 'Importar Datos', 'sysman-suite' ); ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="sysman-card">
            <div class="sysman-card-header">
                <h2>
                    <span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>
                    <?php esc_html_e( 'Acciones Rápidas', 'sysman-suite' ); ?>
                </h2>
            </div>
            <div class="sysman-card-body">
                <div class="sysman-quick-actions">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=sysman-import' ) ); ?>" class="sysman-quick-action">
                        <span class="dashicons dashicons-download" aria-hidden="true"></span>
                        <span><?php esc_html_e( 'Importar Datos', 'sysman-suite' ); ?></span>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=sysman-records' ) ); ?>" class="sysman-quick-action">
                        <span class="dashicons dashicons-list-view" aria-hidden="true"></span>
                        <span><?php esc_html_e( 'Ver Registros', 'sysman-suite' ); ?></span>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=sysman_chart' ) ); ?>" class="sysman-quick-action">
                        <span class="dashicons dashicons-chart-pie" aria-hidden="true"></span>
                        <span><?php esc_html_e( 'Crear Gráfico', 'sysman-suite' ); ?></span>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=sysman-logs' ) ); ?>" class="sysman-quick-action">
                        <span class="dashicons dashicons-media-text" aria-hidden="true"></span>
                        <span><?php esc_html_e( 'Ver Logs', 'sysman-suite' ); ?></span>
                    </a>
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
                        <span class="sysman-sysinfo-label"><?php esc_html_e( 'Plugin', 'sysman-suite' ); ?></span>
                        <span class="sysman-sysinfo-value">v<?php echo esc_html( SYSMAN_SUITE_VERSION ); ?></span>
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
                        <span class="sysman-sysinfo-label"><?php esc_html_e( 'Prefijo BD', 'sysman-suite' ); ?></span>
                        <span class="sysman-sysinfo-value"><?php echo esc_html( $GLOBALS['wpdb']->prefix ); ?></span>
                    </div>
                    <div class="sysman-sysinfo-item">
                        <span class="sysman-sysinfo-label"><?php esc_html_e( 'Próx. Cron', 'sysman-suite' ); ?></span>
                        <span class="sysman-sysinfo-value">
                            <?php echo $next_cron ? esc_html( wp_date( 'd/m/Y H:i', $next_cron ) ) : esc_html__( 'No programado', 'sysman-suite' ); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- API Documentation -->
    <div class="sysman-card sysman-card-full">
        <div class="sysman-card-header sysman-card-header--collapsible" id="sysman-api-docs-toggle">
            <h2>
                <span class="dashicons dashicons-rest-api" aria-hidden="true"></span>
                <?php esc_html_e( 'Documentación de la API SYSMAN', 'sysman-suite' ); ?>
            </h2>
            <button type="button" class="button button-small sysman-toggle-btn"><?php esc_html_e( 'Expandir', 'sysman-suite' ); ?></button>
        </div>
        <div class="sysman-card-body sysman-collapsible-content" style="display:none;">
            <div class="sysman-api-docs">
                <h3><?php esc_html_e( 'Informes Disponibles', 'sysman-suite' ); ?></h3>
                <table class="sysman-mini-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Informe', 'sysman-suite' ); ?></th>
                            <th><?php esc_html_e( 'Parámetro', 'sysman-suite' ); ?></th>
                            <th><?php esc_html_e( 'Descripción', 'sysman-suite' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php esc_html_e( 'Ejecución Presupuestal de Gastos', 'sysman-suite' ); ?></td>
                            <td><code>numinforme=1</code></td>
                            <td><?php esc_html_e( 'Datos acumulados de ejecución presupuestal por rubro', 'sysman-suite' ); ?></td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e( 'Auxiliar Presupuestal por Cuentas', 'sysman-suite' ); ?></td>
                            <td><code>numinforme=2</code></td>
                            <td><?php esc_html_e( 'Detalle de comprobantes presupuestales por cuenta', 'sysman-suite' ); ?></td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e( 'Plan Presupuestal', 'sysman-suite' ); ?></td>
                            <td><code>numinforme=4</code></td>
                            <td><?php esc_html_e( 'Estructura del plan presupuestal con apropiaciones y ejecución', 'sysman-suite' ); ?></td>
                        </tr>
                    </tbody>
                </table>

                <h3><?php esc_html_e( 'URL Base', 'sysman-suite' ); ?></h3>
                <code class="sysman-code-block">https://narino-gob.sysman.com.co/sysmanApi/autoservicio/v1/informesGobNar</code>

                <h3><?php esc_html_e( 'Parámetros', 'sysman-suite' ); ?></h3>
                <table class="sysman-mini-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Parámetro', 'sysman-suite' ); ?></th>
                            <th><?php esc_html_e( 'Tipo', 'sysman-suite' ); ?></th>
                            <th><?php esc_html_e( 'Descripción', 'sysman-suite' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>compania</code></td><td>String</td><td><?php esc_html_e( 'Código de la compañía', 'sysman-suite' ); ?></td></tr>
                        <tr><td><code>anio</code></td><td>Integer</td><td><?php esc_html_e( 'Año fiscal', 'sysman-suite' ); ?></td></tr>
                        <tr><td><code>mes</code></td><td>Integer</td><td><?php esc_html_e( 'Mes de corte (1-12)', 'sysman-suite' ); ?></td></tr>
                        <tr><td><code>numinforme</code></td><td>Integer</td><td><?php esc_html_e( 'Número del informe (1, 2 o 4)', 'sysman-suite' ); ?></td></tr>
                        <tr><td><code>tipo_cpte</code></td><td>String</td><td><?php esc_html_e( 'Tipo de comprobante (solo para informe 2)', 'sysman-suite' ); ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
