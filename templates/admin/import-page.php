<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$plugin     = Sysman_Suite::instance();
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

    <!-- Header -->
    <div class="sysman-page-header">
        <div class="sysman-page-header-content">
            <div class="sysman-header-logo">
                <span class="dashicons dashicons-download" aria-hidden="true" style="font-size:32px;width:32px;height:32px;color:#1a5632;"></span>
            </div>
            <div>
                <h1 class="sysman-page-title"><?php esc_html_e( 'Importar Datos', 'sysman-suite' ); ?></h1>
                <p class="sysman-page-subtitle"><?php esc_html_e( 'Importar datos presupuestales desde la API SYSMAN', 'sysman-suite' ); ?></p>
            </div>
        </div>
    </div>

    <!-- Import Form -->
    <div class="sysman-card">
        <div class="sysman-card-header">
            <h2>
                <span class="dashicons dashicons-cloud-upload" aria-hidden="true"></span>
                <?php esc_html_e( 'Parámetros de Importación', 'sysman-suite' ); ?>
            </h2>
        </div>
        <div class="sysman-card-body">
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

                <div class="sysman-import-notice">
                    <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                    <?php esc_html_e( 'Al importar, se eliminarán todos los datos del año seleccionado y se reemplazarán con los nuevos datos de la API.', 'sysman-suite' ); ?>
                </div>

                <div class="sysman-form-actions">
                    <button type="button" id="sysman-import-btn" class="button button-primary button-hero" aria-live="polite">
                        <span class="dashicons dashicons-download" aria-hidden="true"></span>
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
    </div>

    <!-- Configuration -->
    <div class="sysman-card">
        <div class="sysman-card-header">
            <h2>
                <span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
                <?php esc_html_e( 'Configuración por Defecto', 'sysman-suite' ); ?>
            </h2>
        </div>
        <div class="sysman-card-body">
            <form method="post" action="options.php">
                <?php settings_fields( 'sysman_settings' ); ?>

                <div class="sysman-form-row">
                    <div class="sysman-form-group">
                        <label for="sysman_api_compania"><?php esc_html_e( 'Compañía por defecto', 'sysman-suite' ); ?></label>
                        <input type="text" id="sysman_api_compania" name="sysman_api_compania" value="<?php echo esc_attr( $compania ); ?>" class="regular-text">
                    </div>
                    <div class="sysman-form-group">
                        <label for="sysman_api_anio"><?php esc_html_e( 'Año por defecto', 'sysman-suite' ); ?></label>
                        <input type="number" id="sysman_api_anio" name="sysman_api_anio" value="<?php echo esc_attr( $anio ); ?>" min="2000" max="2100" class="small-text">
                    </div>
                    <div class="sysman-form-group">
                        <label for="sysman_api_mes"><?php esc_html_e( 'Mes por defecto', 'sysman-suite' ); ?></label>
                        <select id="sysman_api_mes" name="sysman_api_mes">
                            <?php foreach ( $meses as $num => $nombre ) : ?>
                            <option value="<?php echo esc_attr( $num ); ?>" <?php selected( $mes, $num ); ?>><?php echo esc_html( $nombre ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sysman-form-group">
                        <label for="sysman_import_frequency"><?php esc_html_e( 'Frecuencia de importación', 'sysman-suite' ); ?></label>
                        <select id="sysman_import_frequency" name="sysman_import_frequency">
                            <option value="hourly" <?php selected( $frequency, 'hourly' ); ?>><?php esc_html_e( 'Cada hora', 'sysman-suite' ); ?></option>
                            <option value="twicedaily" <?php selected( $frequency, 'twicedaily' ); ?>><?php esc_html_e( 'Dos veces al día', 'sysman-suite' ); ?></option>
                            <option value="daily" <?php selected( $frequency, 'daily' ); ?>><?php esc_html_e( 'Diariamente', 'sysman-suite' ); ?></option>
                            <option value="weekly" <?php selected( $frequency, 'weekly' ); ?>><?php esc_html_e( 'Semanalmente', 'sysman-suite' ); ?></option>
                            <option value="monthly" <?php selected( $frequency, 'monthly' ); ?>><?php esc_html_e( 'Mensualmente', 'sysman-suite' ); ?></option>
                        </select>
                    </div>
                </div>

                <?php submit_button( __( 'Guardar Configuración', 'sysman-suite' ) ); ?>
            </form>
        </div>
    </div>

</div>
