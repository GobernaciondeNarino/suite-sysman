<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$plugin     = Sysman_Suite::instance();
$stats      = $plugin->database->get_stats();
$last_import = get_option( 'sysman_last_import', null );

$compania  = get_option( 'sysman_api_compania', '001' );
$anio      = get_option( 'sysman_api_anio', date( 'Y' ) );
$mes       = get_option( 'sysman_api_mes', date( 'n' ) );
$frequency = get_option( 'sysman_import_frequency', 'daily' );

$meses = [
    1  => 'Enero',
    2  => 'Febrero',
    3  => 'Marzo',
    4  => 'Abril',
    5  => 'Mayo',
    6  => 'Junio',
    7  => 'Julio',
    8  => 'Agosto',
    9  => 'Septiembre',
    10 => 'Octubre',
    11 => 'Noviembre',
    12 => 'Diciembre',
];
?>
<div class="wrap sysman-admin-wrap">
    <h1 class="sysman-title">
        <span aria-hidden="true" class="dashicons dashicons-chart-area"></span>
        <?php esc_html_e( 'SYSMAN Suite - Panel de Control', 'sysman-suite' ); ?>
    </h1>

    <!-- Stats Cards -->
    <div class="sysman-stats-grid" role="region" aria-label="<?php esc_attr_e( 'Estadísticas de datos', 'sysman-suite' ); ?>">
        <?php foreach ( $stats as $table => $info ) : ?>
        <div class="sysman-stat-card">
            <div class="sysman-stat-icon">
                <span aria-hidden="true" class="dashicons dashicons-database"></span>
            </div>
            <div class="sysman-stat-content">
                <h3><?php echo esc_html( $info['label'] ); ?></h3>
                <p class="sysman-stat-number"><?php echo esc_html( number_format( $info['count'] ) ); ?></p>
                <p class="sysman-stat-label"><?php esc_html_e( 'registros', 'sysman-suite' ); ?></p>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if ( $last_import ) : ?>
        <div class="sysman-stat-card sysman-stat-card--info">
            <div class="sysman-stat-icon">
                <span aria-hidden="true" class="dashicons dashicons-clock"></span>
            </div>
            <div class="sysman-stat-content">
                <h3><?php esc_html_e( 'Última Importación', 'sysman-suite' ); ?></h3>
                <p class="sysman-stat-number"><?php echo esc_html( $last_import['date'] ); ?></p>
                <p class="sysman-stat-label">
                    <?php echo esc_html( sprintf( 'Año %d - Mes %d', $last_import['anio'], $last_import['mes'] ) ); ?>
                </p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Import Controls -->
    <div class="sysman-panel" role="region" aria-label="<?php esc_attr_e( 'Controles de importación', 'sysman-suite' ); ?>">
        <h2 class="sysman-panel-title">
            <span aria-hidden="true" class="dashicons dashicons-download"></span>
            <?php esc_html_e( 'Importar Datos', 'sysman-suite' ); ?>
        </h2>

        <div class="sysman-import-form">
            <div class="sysman-form-row">
                <div class="sysman-form-group">
                    <label for="sysman-compania"><?php esc_html_e( 'Compañía', 'sysman-suite' ); ?></label>
                    <input type="text" id="sysman-compania" value="<?php echo esc_attr( $compania ); ?>" class="regular-text" aria-describedby="compania-desc">
                    <p id="compania-desc" class="description"><?php esc_html_e( 'Código de la compañía (ej: 001)', 'sysman-suite' ); ?></p>
                </div>

                <div class="sysman-form-group">
                    <label for="sysman-anio"><?php esc_html_e( 'Año', 'sysman-suite' ); ?></label>
                    <input type="number" id="sysman-anio" value="<?php echo esc_attr( $anio ); ?>" min="2000" max="2100" class="small-text" aria-describedby="anio-desc">
                    <p id="anio-desc" class="description"><?php esc_html_e( 'Año fiscal', 'sysman-suite' ); ?></p>
                </div>

                <div class="sysman-form-group">
                    <label for="sysman-mes"><?php esc_html_e( 'Mes', 'sysman-suite' ); ?></label>
                    <select id="sysman-mes" aria-describedby="mes-desc">
                        <?php foreach ( $meses as $num => $nombre ) : ?>
                        <option value="<?php echo esc_attr( $num ); ?>" <?php selected( $mes, $num ); ?>>
                            <?php echo esc_html( $nombre ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p id="mes-desc" class="description"><?php esc_html_e( 'Mes de corte', 'sysman-suite' ); ?></p>
                </div>

                <div class="sysman-form-group">
                    <label for="sysman-report"><?php esc_html_e( 'Informe', 'sysman-suite' ); ?></label>
                    <select id="sysman-report" aria-describedby="report-desc">
                        <option value="all"><?php esc_html_e( 'Todos los informes', 'sysman-suite' ); ?></option>
                        <option value="ejecucion"><?php esc_html_e( 'Ejecución Presupuestal de Gastos', 'sysman-suite' ); ?></option>
                        <option value="auxiliar"><?php esc_html_e( 'Auxiliar Presupuestal por Cuentas', 'sysman-suite' ); ?></option>
                        <option value="plan"><?php esc_html_e( 'Plan Presupuestal', 'sysman-suite' ); ?></option>
                    </select>
                    <p id="report-desc" class="description"><?php esc_html_e( 'Tipo de informe a importar', 'sysman-suite' ); ?></p>
                </div>
            </div>

            <div class="sysman-form-row sysman-auxiliar-options" style="display:none;">
                <div class="sysman-form-group">
                    <label for="sysman-tipo-cpte"><?php esc_html_e( 'Tipo de Comprobante', 'sysman-suite' ); ?></label>
                    <select id="sysman-tipo-cpte">
                        <option value="RES">RES - Resolución</option>
                        <option value="CDP">CDP - Certificado Disponibilidad</option>
                        <option value="RP">RP - Registro Presupuestal</option>
                        <option value="OBL">OBL - Obligación</option>
                        <option value="PAG">PAG - Pago</option>
                    </select>
                </div>
            </div>

            <div class="sysman-form-actions">
                <button type="button" id="sysman-import-btn" class="button button-primary button-hero" aria-live="polite">
                    <span aria-hidden="true" class="dashicons dashicons-download"></span>
                    <?php esc_html_e( 'Iniciar Importación', 'sysman-suite' ); ?>
                </button>
            </div>

            <!-- Step indicators -->
            <div id="sysman-import-steps" class="sysman-import-steps" style="display:none;">
                <div class="sysman-step" data-step="1">
                    <div class="sysman-step-indicator">1</div>
                    <span class="sysman-step-label"><?php esc_html_e( 'Ejecución Presupuestal', 'sysman-suite' ); ?></span>
                </div>
                <div class="sysman-step-connector"></div>
                <div class="sysman-step" data-step="2">
                    <div class="sysman-step-indicator">2</div>
                    <span class="sysman-step-label"><?php esc_html_e( 'Auxiliar Presupuestal', 'sysman-suite' ); ?></span>
                </div>
                <div class="sysman-step-connector"></div>
                <div class="sysman-step" data-step="3">
                    <div class="sysman-step-indicator">3</div>
                    <span class="sysman-step-label"><?php esc_html_e( 'Plan Presupuestal', 'sysman-suite' ); ?></span>
                </div>
            </div>

            <!-- Progress -->
            <div id="sysman-import-progress" class="sysman-progress-container" style="display:none;" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                <div class="sysman-progress-header">
                    <span class="sysman-progress-text"><?php esc_html_e( 'Preparando importación...', 'sysman-suite' ); ?></span>
                    <span class="sysman-progress-percent">0%</span>
                </div>
                <div class="sysman-progress-bar">
                    <div class="sysman-progress-fill" style="width:0%"></div>
                </div>
            </div>

            <!-- Results -->
            <div id="sysman-import-results" class="sysman-results-container" style="display:none;" role="alert" aria-live="polite">
            </div>
        </div>
    </div>

    <!-- Settings -->
    <div class="sysman-panel" role="region" aria-label="<?php esc_attr_e( 'Configuración', 'sysman-suite' ); ?>">
        <h2 class="sysman-panel-title">
            <span aria-hidden="true" class="dashicons dashicons-admin-settings"></span>
            <?php esc_html_e( 'Configuración', 'sysman-suite' ); ?>
        </h2>

        <form method="post" action="options.php">
            <?php settings_fields( 'sysman_settings' ); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="sysman_api_compania"><?php esc_html_e( 'Compañía por defecto', 'sysman-suite' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="sysman_api_compania" name="sysman_api_compania" value="<?php echo esc_attr( $compania ); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="sysman_api_anio"><?php esc_html_e( 'Año por defecto', 'sysman-suite' ); ?></label>
                    </th>
                    <td>
                        <input type="number" id="sysman_api_anio" name="sysman_api_anio" value="<?php echo esc_attr( $anio ); ?>" min="2000" max="2100" class="small-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="sysman_api_mes"><?php esc_html_e( 'Mes por defecto', 'sysman-suite' ); ?></label>
                    </th>
                    <td>
                        <select id="sysman_api_mes" name="sysman_api_mes">
                            <?php foreach ( $meses as $num => $nombre ) : ?>
                            <option value="<?php echo esc_attr( $num ); ?>" <?php selected( $mes, $num ); ?>>
                                <?php echo esc_html( $nombre ); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="sysman_import_frequency"><?php esc_html_e( 'Frecuencia de importación', 'sysman-suite' ); ?></label>
                    </th>
                    <td>
                        <select id="sysman_import_frequency" name="sysman_import_frequency">
                            <option value="hourly" <?php selected( $frequency, 'hourly' ); ?>><?php esc_html_e( 'Cada hora', 'sysman-suite' ); ?></option>
                            <option value="twicedaily" <?php selected( $frequency, 'twicedaily' ); ?>><?php esc_html_e( 'Dos veces al día', 'sysman-suite' ); ?></option>
                            <option value="daily" <?php selected( $frequency, 'daily' ); ?>><?php esc_html_e( 'Diariamente', 'sysman-suite' ); ?></option>
                            <option value="weekly" <?php selected( $frequency, 'weekly' ); ?>><?php esc_html_e( 'Semanalmente', 'sysman-suite' ); ?></option>
                            <option value="monthly" <?php selected( $frequency, 'monthly' ); ?>><?php esc_html_e( 'Mensualmente', 'sysman-suite' ); ?></option>
                        </select>
                    </td>
                </tr>
            </table>

            <?php submit_button( __( 'Guardar Configuración', 'sysman-suite' ) ); ?>
        </form>
    </div>

    <!-- API Documentation -->
    <div class="sysman-panel" role="region" aria-label="<?php esc_attr_e( 'Documentación API', 'sysman-suite' ); ?>">
        <h2 class="sysman-panel-title">
            <span aria-hidden="true" class="dashicons dashicons-rest-api"></span>
            <?php esc_html_e( 'Documentación de la API SYSMAN', 'sysman-suite' ); ?>
        </h2>

        <div class="sysman-api-docs">
            <h3><?php esc_html_e( 'Informes Disponibles', 'sysman-suite' ); ?></h3>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'Informe', 'sysman-suite' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Número', 'sysman-suite' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Descripción', 'sysman-suite' ); ?></th>
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

            <h3><?php esc_html_e( 'URL Base de la API', 'sysman-suite' ); ?></h3>
            <code class="sysman-code-block">https://narino-gob.sysman.com.co/sysmanApi/autoservicio/v1/informesGobNar</code>

            <h3><?php esc_html_e( 'Parámetros', 'sysman-suite' ); ?></h3>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'Parámetro', 'sysman-suite' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Tipo', 'sysman-suite' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Descripción', 'sysman-suite' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>compania</code></td>
                        <td>String</td>
                        <td><?php esc_html_e( 'Código de la compañía', 'sysman-suite' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>anio</code></td>
                        <td>Integer</td>
                        <td><?php esc_html_e( 'Año fiscal', 'sysman-suite' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>mes</code></td>
                        <td>Integer</td>
                        <td><?php esc_html_e( 'Mes de corte (1-12)', 'sysman-suite' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>numinforme</code></td>
                        <td>Integer</td>
                        <td><?php esc_html_e( 'Número del informe (1, 2 o 4)', 'sysman-suite' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>tipo_cpte</code></td>
                        <td>String</td>
                        <td><?php esc_html_e( 'Tipo de comprobante (solo para informe 2)', 'sysman-suite' ); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
