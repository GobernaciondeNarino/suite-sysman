<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$plugin     = Sysman_Suite::instance();
$last_import = get_option( 'sysman_last_import', null );

$compania  = get_option( 'sysman_api_compania', '001' );
$anio      = get_option( 'sysman_api_anio', current_time( 'Y' ) );
$mes       = get_option( 'sysman_api_mes', current_time( 'n' ) );
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
                        <select id="sysman-compania" aria-describedby="compania-desc">
                            <option value="001" <?php selected( $compania, '001' ); ?>>001 - <?php esc_html_e( 'Gobernación de Nariño', 'sysman-suite' ); ?></option>
                            <option value="007" <?php selected( $compania, '007' ); ?>>007 - <?php esc_html_e( 'SED (Secretaría de Educación)', 'sysman-suite' ); ?></option>
                            <option value="custom"><?php esc_html_e( 'Otra...', 'sysman-suite' ); ?></option>
                        </select>
                        <input type="text" id="sysman-compania-custom" value="" class="regular-text" placeholder="<?php esc_attr_e( 'Código de compañía', 'sysman-suite' ); ?>" style="display:none;margin-top:6px;">
                        <p id="compania-desc" class="description"><?php esc_html_e( 'Entidad para la importación de datos', 'sysman-suite' ); ?></p>
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
                            <option value="personal"><?php esc_html_e( 'Personal Activo de Nómina', 'sysman-suite' ); ?></option>
                            <option value="ingresos"><?php esc_html_e( 'Ejecución de Ingresos', 'sysman-suite' ); ?></option>
                        </select>
                        <p id="report-desc" class="description"><?php esc_html_e( 'Tipo de informe a importar', 'sysman-suite' ); ?></p>
                    </div>
                </div>

                <div class="sysman-form-row sysman-auxiliar-options" style="display:none;">
                    <div class="sysman-form-group">
                        <label for="sysman-tipo-cpte"><?php esc_html_e( 'Tipo de Comprobante', 'sysman-suite' ); ?></label>
                        <select id="sysman-tipo-cpte">
                            <option value="DIS"><?php esc_html_e( 'DIS - Disponibilidades (CDP)', 'sysman-suite' ); ?></option>
                            <option value="RES"><?php esc_html_e( 'RES - Reservas (Registro Presupuestal)', 'sysman-suite' ); ?></option>
                            <option value="OBL"><?php esc_html_e( 'OBL - Obligaciones', 'sysman-suite' ); ?></option>
                            <option value="EGR"><?php esc_html_e( 'EGR - Egresos (Pagos)', 'sysman-suite' ); ?></option>
                        </select>
                    </div>
                </div>

                <div class="sysman-form-row">
                    <div class="sysman-form-group">
                        <label for="sysman-limpiar">
                            <input type="checkbox" id="sysman-limpiar" aria-describedby="limpiar-desc">
                            <?php esc_html_e( 'Limpiar el periodo antes de importar', 'sysman-suite' ); ?>
                        </label>
                        <p id="limpiar-desc" class="description">
                            <?php esc_html_e( 'Borra el periodo completo (año y mes seleccionados) en las cinco tablas antes de traer los datos. Úselo si sospecha que las cifras están duplicadas o infladas.', 'sysman-suite' ); ?>
                        </p>
                    </div>
                </div>

                <div class="sysman-import-notice">
                    <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                    <?php esc_html_e( 'Cada importación reemplaza únicamente el periodo seleccionado (compañía, año y mes); los demás periodos no se tocan. No se puede importar dos veces a la vez: mientras una importación está en curso, las demás —incluida la programada— esperan su turno.', 'sysman-suite' ); ?>
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
                        <span class="sysman-step-label"><?php esc_html_e( 'Ejecución Gastos', 'sysman-suite' ); ?></span>
                    </div>
                    <div class="sysman-step-connector"></div>
                    <div class="sysman-step" data-step="2">
                        <div class="sysman-step-indicator">2</div>
                        <span class="sysman-step-label"><?php esc_html_e( 'Disponibilidades', 'sysman-suite' ); ?></span>
                    </div>
                    <div class="sysman-step-connector"></div>
                    <div class="sysman-step" data-step="3">
                        <div class="sysman-step-indicator">3</div>
                        <span class="sysman-step-label"><?php esc_html_e( 'Compromisos', 'sysman-suite' ); ?></span>
                    </div>
                    <div class="sysman-step-connector"></div>
                    <div class="sysman-step" data-step="4">
                        <div class="sysman-step-indicator">4</div>
                        <span class="sysman-step-label"><?php esc_html_e( 'Obligaciones', 'sysman-suite' ); ?></span>
                    </div>
                    <div class="sysman-step-connector"></div>
                    <div class="sysman-step" data-step="5">
                        <div class="sysman-step-indicator">5</div>
                        <span class="sysman-step-label"><?php esc_html_e( 'Egresos', 'sysman-suite' ); ?></span>
                    </div>
                    <div class="sysman-step-connector"></div>
                    <div class="sysman-step" data-step="6">
                        <div class="sysman-step-indicator">6</div>
                        <span class="sysman-step-label"><?php esc_html_e( 'Plan Presupuestal', 'sysman-suite' ); ?></span>
                    </div>
                    <div class="sysman-step-connector"></div>
                    <div class="sysman-step" data-step="7">
                        <div class="sysman-step-indicator">7</div>
                        <span class="sysman-step-label"><?php esc_html_e( 'Personal Nómina', 'sysman-suite' ); ?></span>
                    </div>
                    <div class="sysman-step-connector"></div>
                    <div class="sysman-step" data-step="8">
                        <div class="sysman-step-indicator">8</div>
                        <span class="sysman-step-label"><?php esc_html_e( 'Ingresos', 'sysman-suite' ); ?></span>
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

    <!-- Integridad de datos -->
    <div class="sysman-card">
        <div class="sysman-card-header">
            <h2>
                <span class="dashicons dashicons-shield" aria-hidden="true"></span>
                <?php esc_html_e( 'Integridad de los datos', 'sysman-suite' ); ?>
            </h2>
        </div>
        <div class="sysman-card-body">
            <p class="description">
                <?php esc_html_e( 'Compara, para cada tabla y periodo, cuántas filas hay frente a cuántos registros distintos representan. Si sobran filas, el periodo se importó dos veces y las cifras salen infladas: la solución es reimportar ese periodo con «Limpiar el periodo antes de importar».', 'sysman-suite' ); ?>
            </p>
            <p>
                <button type="button" id="sysman-check-dupes" class="button">
                    <span class="dashicons dashicons-search" aria-hidden="true"></span>
                    <?php esc_html_e( 'Verificar duplicados', 'sysman-suite' ); ?>
                </button>
            </p>
            <div id="sysman-dupes-result" aria-live="polite"></div>
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
