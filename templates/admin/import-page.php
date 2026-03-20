<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$plugin     = Sisman_Suite::instance();
$stats      = $plugin->database->get_stats();
$last_import = get_option( 'sisman_last_import', null );

$compania  = get_option( 'sisman_api_compania', '001' );
$anio      = get_option( 'sisman_api_anio', date( 'Y' ) );
$mes       = get_option( 'sisman_api_mes', date( 'n' ) );
$frequency = get_option( 'sisman_import_frequency', 'daily' );

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
<div class="wrap sisman-admin-wrap">
    <h1 class="sisman-title">
        <span aria-hidden="true" class="dashicons dashicons-chart-area"></span>
        <?php esc_html_e( 'SISMAN Suite - Panel de Control', 'sisman-suite' ); ?>
    </h1>

    <!-- Stats Cards -->
    <div class="sisman-stats-grid" role="region" aria-label="<?php esc_attr_e( 'Estadísticas de datos', 'sisman-suite' ); ?>">
        <?php foreach ( $stats as $table => $info ) : ?>
        <div class="sisman-stat-card">
            <div class="sisman-stat-icon">
                <span aria-hidden="true" class="dashicons dashicons-database"></span>
            </div>
            <div class="sisman-stat-content">
                <h3><?php echo esc_html( $info['label'] ); ?></h3>
                <p class="sisman-stat-number"><?php echo esc_html( number_format( $info['count'] ) ); ?></p>
                <p class="sisman-stat-label"><?php esc_html_e( 'registros', 'sisman-suite' ); ?></p>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if ( $last_import ) : ?>
        <div class="sisman-stat-card sisman-stat-card--info">
            <div class="sisman-stat-icon">
                <span aria-hidden="true" class="dashicons dashicons-clock"></span>
            </div>
            <div class="sisman-stat-content">
                <h3><?php esc_html_e( 'Última Importación', 'sisman-suite' ); ?></h3>
                <p class="sisman-stat-number"><?php echo esc_html( $last_import['date'] ); ?></p>
                <p class="sisman-stat-label">
                    <?php echo esc_html( sprintf( 'Año %d - Mes %d', $last_import['anio'], $last_import['mes'] ) ); ?>
                </p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Import Controls -->
    <div class="sisman-panel" role="region" aria-label="<?php esc_attr_e( 'Controles de importación', 'sisman-suite' ); ?>">
        <h2 class="sisman-panel-title">
            <span aria-hidden="true" class="dashicons dashicons-download"></span>
            <?php esc_html_e( 'Importar Datos', 'sisman-suite' ); ?>
        </h2>

        <div class="sisman-import-form">
            <div class="sisman-form-row">
                <div class="sisman-form-group">
                    <label for="sisman-compania"><?php esc_html_e( 'Compañía', 'sisman-suite' ); ?></label>
                    <input type="text" id="sisman-compania" value="<?php echo esc_attr( $compania ); ?>" class="regular-text" aria-describedby="compania-desc">
                    <p id="compania-desc" class="description"><?php esc_html_e( 'Código de la compañía (ej: 001)', 'sisman-suite' ); ?></p>
                </div>

                <div class="sisman-form-group">
                    <label for="sisman-anio"><?php esc_html_e( 'Año', 'sisman-suite' ); ?></label>
                    <input type="number" id="sisman-anio" value="<?php echo esc_attr( $anio ); ?>" min="2000" max="2100" class="small-text" aria-describedby="anio-desc">
                    <p id="anio-desc" class="description"><?php esc_html_e( 'Año fiscal', 'sisman-suite' ); ?></p>
                </div>

                <div class="sisman-form-group">
                    <label for="sisman-mes"><?php esc_html_e( 'Mes', 'sisman-suite' ); ?></label>
                    <select id="sisman-mes" aria-describedby="mes-desc">
                        <?php foreach ( $meses as $num => $nombre ) : ?>
                        <option value="<?php echo esc_attr( $num ); ?>" <?php selected( $mes, $num ); ?>>
                            <?php echo esc_html( $nombre ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p id="mes-desc" class="description"><?php esc_html_e( 'Mes de corte', 'sisman-suite' ); ?></p>
                </div>

                <div class="sisman-form-group">
                    <label for="sisman-report"><?php esc_html_e( 'Informe', 'sisman-suite' ); ?></label>
                    <select id="sisman-report" aria-describedby="report-desc">
                        <option value="all"><?php esc_html_e( 'Todos los informes', 'sisman-suite' ); ?></option>
                        <option value="ejecucion"><?php esc_html_e( 'Ejecución Presupuestal de Gastos', 'sisman-suite' ); ?></option>
                        <option value="auxiliar"><?php esc_html_e( 'Auxiliar Presupuestal por Cuentas', 'sisman-suite' ); ?></option>
                        <option value="plan"><?php esc_html_e( 'Plan Presupuestal', 'sisman-suite' ); ?></option>
                    </select>
                    <p id="report-desc" class="description"><?php esc_html_e( 'Tipo de informe a importar', 'sisman-suite' ); ?></p>
                </div>
            </div>

            <div class="sisman-form-row sisman-auxiliar-options" style="display:none;">
                <div class="sisman-form-group">
                    <label for="sisman-tipo-cpte"><?php esc_html_e( 'Tipo de Comprobante', 'sisman-suite' ); ?></label>
                    <select id="sisman-tipo-cpte">
                        <option value="RES">RES - Resolución</option>
                        <option value="CDP">CDP - Certificado Disponibilidad</option>
                        <option value="RP">RP - Registro Presupuestal</option>
                        <option value="OBL">OBL - Obligación</option>
                        <option value="PAG">PAG - Pago</option>
                    </select>
                </div>
            </div>

            <div class="sisman-form-actions">
                <button type="button" id="sisman-import-btn" class="button button-primary button-hero" aria-live="polite">
                    <span aria-hidden="true" class="dashicons dashicons-download"></span>
                    <?php esc_html_e( 'Iniciar Importación', 'sisman-suite' ); ?>
                </button>
            </div>

            <!-- Step indicators -->
            <div id="sisman-import-steps" class="sisman-import-steps" style="display:none;">
                <div class="sisman-step" data-step="1">
                    <div class="sisman-step-indicator">1</div>
                    <span class="sisman-step-label"><?php esc_html_e( 'Ejecución Presupuestal', 'sisman-suite' ); ?></span>
                </div>
                <div class="sisman-step-connector"></div>
                <div class="sisman-step" data-step="2">
                    <div class="sisman-step-indicator">2</div>
                    <span class="sisman-step-label"><?php esc_html_e( 'Auxiliar Presupuestal', 'sisman-suite' ); ?></span>
                </div>
                <div class="sisman-step-connector"></div>
                <div class="sisman-step" data-step="3">
                    <div class="sisman-step-indicator">3</div>
                    <span class="sisman-step-label"><?php esc_html_e( 'Plan Presupuestal', 'sisman-suite' ); ?></span>
                </div>
            </div>

            <!-- Progress -->
            <div id="sisman-import-progress" class="sisman-progress-container" style="display:none;" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                <div class="sisman-progress-header">
                    <span class="sisman-progress-text"><?php esc_html_e( 'Preparando importación...', 'sisman-suite' ); ?></span>
                    <span class="sisman-progress-percent">0%</span>
                </div>
                <div class="sisman-progress-bar">
                    <div class="sisman-progress-fill" style="width:0%"></div>
                </div>
            </div>

            <!-- Results -->
            <div id="sisman-import-results" class="sisman-results-container" style="display:none;" role="alert" aria-live="polite">
            </div>
        </div>
    </div>

    <!-- Settings -->
    <div class="sisman-panel" role="region" aria-label="<?php esc_attr_e( 'Configuración', 'sisman-suite' ); ?>">
        <h2 class="sisman-panel-title">
            <span aria-hidden="true" class="dashicons dashicons-admin-settings"></span>
            <?php esc_html_e( 'Configuración', 'sisman-suite' ); ?>
        </h2>

        <form method="post" action="options.php">
            <?php settings_fields( 'sisman_settings' ); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="sisman_api_compania"><?php esc_html_e( 'Compañía por defecto', 'sisman-suite' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="sisman_api_compania" name="sisman_api_compania" value="<?php echo esc_attr( $compania ); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="sisman_api_anio"><?php esc_html_e( 'Año por defecto', 'sisman-suite' ); ?></label>
                    </th>
                    <td>
                        <input type="number" id="sisman_api_anio" name="sisman_api_anio" value="<?php echo esc_attr( $anio ); ?>" min="2000" max="2100" class="small-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="sisman_api_mes"><?php esc_html_e( 'Mes por defecto', 'sisman-suite' ); ?></label>
                    </th>
                    <td>
                        <select id="sisman_api_mes" name="sisman_api_mes">
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
                        <label for="sisman_import_frequency"><?php esc_html_e( 'Frecuencia de importación', 'sisman-suite' ); ?></label>
                    </th>
                    <td>
                        <select id="sisman_import_frequency" name="sisman_import_frequency">
                            <option value="hourly" <?php selected( $frequency, 'hourly' ); ?>><?php esc_html_e( 'Cada hora', 'sisman-suite' ); ?></option>
                            <option value="twicedaily" <?php selected( $frequency, 'twicedaily' ); ?>><?php esc_html_e( 'Dos veces al día', 'sisman-suite' ); ?></option>
                            <option value="daily" <?php selected( $frequency, 'daily' ); ?>><?php esc_html_e( 'Diariamente', 'sisman-suite' ); ?></option>
                            <option value="weekly" <?php selected( $frequency, 'weekly' ); ?>><?php esc_html_e( 'Semanalmente', 'sisman-suite' ); ?></option>
                            <option value="monthly" <?php selected( $frequency, 'monthly' ); ?>><?php esc_html_e( 'Mensualmente', 'sisman-suite' ); ?></option>
                        </select>
                    </td>
                </tr>
            </table>

            <?php submit_button( __( 'Guardar Configuración', 'sisman-suite' ) ); ?>
        </form>
    </div>

    <!-- API Documentation -->
    <div class="sisman-panel" role="region" aria-label="<?php esc_attr_e( 'Documentación API', 'sisman-suite' ); ?>">
        <h2 class="sisman-panel-title">
            <span aria-hidden="true" class="dashicons dashicons-rest-api"></span>
            <?php esc_html_e( 'Documentación de la API SISMAN', 'sisman-suite' ); ?>
        </h2>

        <div class="sisman-api-docs">
            <h3><?php esc_html_e( 'Informes Disponibles', 'sisman-suite' ); ?></h3>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'Informe', 'sisman-suite' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Número', 'sisman-suite' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Descripción', 'sisman-suite' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php esc_html_e( 'Ejecución Presupuestal de Gastos', 'sisman-suite' ); ?></td>
                        <td><code>numinforme=1</code></td>
                        <td><?php esc_html_e( 'Datos acumulados de ejecución presupuestal por rubro', 'sisman-suite' ); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'Auxiliar Presupuestal por Cuentas', 'sisman-suite' ); ?></td>
                        <td><code>numinforme=2</code></td>
                        <td><?php esc_html_e( 'Detalle de comprobantes presupuestales por cuenta', 'sisman-suite' ); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'Plan Presupuestal', 'sisman-suite' ); ?></td>
                        <td><code>numinforme=4</code></td>
                        <td><?php esc_html_e( 'Estructura del plan presupuestal con apropiaciones y ejecución', 'sisman-suite' ); ?></td>
                    </tr>
                </tbody>
            </table>

            <h3><?php esc_html_e( 'URL Base de la API', 'sisman-suite' ); ?></h3>
            <code class="sisman-code-block">https://narino-gob.sysman.com.co/sysmanApi/autoservicio/v1/informesGobNar</code>

            <h3><?php esc_html_e( 'Parámetros', 'sisman-suite' ); ?></h3>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'Parámetro', 'sisman-suite' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Tipo', 'sisman-suite' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Descripción', 'sisman-suite' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>compania</code></td>
                        <td>String</td>
                        <td><?php esc_html_e( 'Código de la compañía', 'sisman-suite' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>anio</code></td>
                        <td>Integer</td>
                        <td><?php esc_html_e( 'Año fiscal', 'sisman-suite' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>mes</code></td>
                        <td>Integer</td>
                        <td><?php esc_html_e( 'Mes de corte (1-12)', 'sisman-suite' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>numinforme</code></td>
                        <td>Integer</td>
                        <td><?php esc_html_e( 'Número del informe (1, 2 o 4)', 'sisman-suite' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>tipo_cpte</code></td>
                        <td>String</td>
                        <td><?php esc_html_e( 'Tipo de comprobante (solo para informe 2)', 'sisman-suite' ); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
