<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$api_base_url   = get_option( 'sysman_api_base_url', 'https://narino-gob.sysman.com.co/sysmanApi/autoservicio/v1/informesGobNar' );
$github_repo    = get_option( 'sysman_github_repo', 'GobernaciondeNarino/sysman-suite' );
$d3plus_cdn_url = get_option( 'sysman_d3plus_cdn_url', SYSMAN_SUITE_D3PLUS_CDN );
$compania       = get_option( 'sysman_api_compania', '001' );
$anio           = get_option( 'sysman_api_anio', (int) current_time( 'Y' ) );
$mes            = get_option( 'sysman_api_mes', (int) current_time( 'n' ) );
$frequency      = get_option( 'sysman_import_frequency', 'daily' );
?>
<div class="wrap sysman-admin-wrap">

    <!-- Header -->
    <div class="sysman-page-header">
        <div class="sysman-page-header-content">
            <div class="sysman-header-logo">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                    <rect width="40" height="40" rx="8" fill="#1a5632"/>
                    <path d="M12 12h6v6h-6zM22 12h6v6h-6zM12 22h6v6h-6zM22 22h6v6h-6z" fill="#fff" opacity="0.9"/>
                </svg>
            </div>
            <div>
                <h1 class="sysman-page-title"><?php esc_html_e( 'Configuración', 'sysman-suite' ); ?></h1>
                <p class="sysman-page-subtitle"><?php esc_html_e( 'Rutas de APIs, CDN y parámetros de importación', 'sysman-suite' ); ?></p>
            </div>
        </div>
        <div class="sysman-header-actions">
            <span class="sysman-version-badge">v<?php echo esc_html( SYSMAN_SUITE_VERSION ); ?></span>
        </div>
    </div>

    <form method="post" action="options.php">
        <?php settings_fields( 'sysman_settings' ); ?>

        <!-- API SYSMAN -->
        <div class="sysman-card">
            <div class="sysman-card-header">
                <h2>
                    <span class="dashicons dashicons-rest-api" aria-hidden="true"></span>
                    <?php esc_html_e( 'API SYSMAN', 'sysman-suite' ); ?>
                </h2>
            </div>
            <div class="sysman-card-body">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="sysman_api_base_url"><?php esc_html_e( 'URL Base de la API', 'sysman-suite' ); ?></label>
                        </th>
                        <td>
                            <input type="url" id="sysman_api_base_url" name="sysman_api_base_url"
                                   value="<?php echo esc_attr( $api_base_url ); ?>"
                                   class="large-text" placeholder="https://narino-gob.sysman.com.co/sysmanApi/autoservicio/v1/informesGobNar">
                            <p class="description">
                                <?php esc_html_e( 'URL principal del servicio SYSMAN para la importación de datos presupuestales. Modificar solo si la ruta del API cambia.', 'sysman-suite' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sysman_api_compania"><?php esc_html_e( 'Código de Compañía', 'sysman-suite' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="sysman_api_compania" name="sysman_api_compania"
                                   value="<?php echo esc_attr( $compania ); ?>"
                                   class="regular-text" placeholder="001">
                            <p class="description">
                                <?php esc_html_e( 'Código de la compañía en el sistema SYSMAN.', 'sysman-suite' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sysman_api_anio"><?php esc_html_e( 'Año Fiscal', 'sysman-suite' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="sysman_api_anio" name="sysman_api_anio"
                                   value="<?php echo esc_attr( $anio ); ?>"
                                   class="small-text" min="2000" max="2100">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sysman_api_mes"><?php esc_html_e( 'Mes de Corte', 'sysman-suite' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="sysman_api_mes" name="sysman_api_mes"
                                   value="<?php echo esc_attr( $mes ); ?>"
                                   class="small-text" min="1" max="12">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sysman_import_frequency"><?php esc_html_e( 'Frecuencia de Importación', 'sysman-suite' ); ?></label>
                        </th>
                        <td>
                            <select id="sysman_import_frequency" name="sysman_import_frequency">
                                <option value="hourly" <?php selected( $frequency, 'hourly' ); ?>><?php esc_html_e( 'Cada hora', 'sysman-suite' ); ?></option>
                                <option value="twicedaily" <?php selected( $frequency, 'twicedaily' ); ?>><?php esc_html_e( 'Dos veces al día', 'sysman-suite' ); ?></option>
                                <option value="daily" <?php selected( $frequency, 'daily' ); ?>><?php esc_html_e( 'Diario', 'sysman-suite' ); ?></option>
                                <option value="weekly" <?php selected( $frequency, 'weekly' ); ?>><?php esc_html_e( 'Semanal', 'sysman-suite' ); ?></option>
                            </select>
                            <p class="description">
                                <?php esc_html_e( 'Frecuencia con la que se ejecuta la importación automática de datos.', 'sysman-suite' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- GitHub Updates -->
        <div class="sysman-card">
            <div class="sysman-card-header">
                <h2>
                    <span class="dashicons dashicons-update" aria-hidden="true"></span>
                    <?php esc_html_e( 'Actualizaciones (GitHub)', 'sysman-suite' ); ?>
                </h2>
            </div>
            <div class="sysman-card-body">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="sysman_github_repo"><?php esc_html_e( 'Repositorio GitHub', 'sysman-suite' ); ?></label>
                        </th>
                        <td>
                            <div style="display:flex;align-items:center;gap:4px;">
                                <span style="color:#666;">https://github.com/</span>
                                <input type="text" id="sysman_github_repo" name="sysman_github_repo"
                                       value="<?php echo esc_attr( $github_repo ); ?>"
                                       class="regular-text" placeholder="GobernaciondeNarino/sysman-suite">
                            </div>
                            <p class="description">
                                <?php esc_html_e( 'Repositorio desde donde se verifican actualizaciones del plugin. Formato: propietario/repositorio.', 'sysman-suite' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- CDN Libraries -->
        <div class="sysman-card">
            <div class="sysman-card-header">
                <h2>
                    <span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span>
                    <?php esc_html_e( 'Librerías CDN (Visualización)', 'sysman-suite' ); ?>
                </h2>
            </div>
            <div class="sysman-card-body">
                <div class="sysman-alert sysman-alert-info" style="margin-bottom:16px;">
                    <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                    <span><?php esc_html_e( 'Desde la versión 5.10.0 el plugin usa D3plus v4 (paquete @d3plus/core), que ya incluye los módulos de D3 necesarios: no se carga D3 por separado. Solo modifique esta URL si necesita un CDN alternativo o fijar otra versión.', 'sysman-suite' ); ?></span>
                </div>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="sysman_d3plus_cdn_url"><?php esc_html_e( 'D3Plus CDN', 'sysman-suite' ); ?></label>
                        </th>
                        <td>
                            <input type="url" id="sysman_d3plus_cdn_url" name="sysman_d3plus_cdn_url"
                                   value="<?php echo esc_attr( $d3plus_cdn_url ); ?>"
                                   class="large-text" placeholder="<?php echo esc_attr( SYSMAN_SUITE_D3PLUS_CDN ); ?>">
                            <p class="description">
                                <?php esc_html_e( 'URL del bundle UMD de D3plus v4 (barras, líneas, áreas, tortas, treemaps, radar, etc.).', 'sysman-suite' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Connection Test -->
        <div class="sysman-card">
            <div class="sysman-card-header">
                <h2>
                    <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                    <?php esc_html_e( 'Verificación de Conexión', 'sysman-suite' ); ?>
                </h2>
            </div>
            <div class="sysman-card-body">
                <p class="description" style="margin-bottom:12px;">
                    <?php esc_html_e( 'Verifique que las URLs configuradas sean accesibles antes de guardar.', 'sysman-suite' ); ?>
                </p>
                <button type="button" id="sysman-test-connections" class="button button-secondary">
                    <span class="dashicons dashicons-admin-links" aria-hidden="true" style="vertical-align:middle;margin-top:-2px;"></span>
                    <?php esc_html_e( 'Probar Conexiones', 'sysman-suite' ); ?>
                </button>
                <div id="sysman-connection-results" style="margin-top:12px;"></div>
            </div>
        </div>

        <?php submit_button( __( 'Guardar Configuración', 'sysman-suite' ) ); ?>

    </form>

</div>

<script>
jQuery(function($) {
    $('#sysman-test-connections').on('click', function() {
        var $btn = $(this);
        var $results = $('#sysman-connection-results');
        $btn.prop('disabled', true);
        $results.html('<span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span><?php echo esc_js( __( 'Verificando conexiones...', 'sysman-suite' ) ); ?>');

        $.post(ajaxurl, {
            action: 'sysman_test_connections',
            _wpnonce: '<?php echo wp_create_nonce( 'sysman_test_connections' ); ?>',
            api_url: $('#sysman_api_base_url').val(),
            d3plus_url: $('#sysman_d3plus_cdn_url').val(),
            github_repo: $('#sysman_github_repo').val()
        }, function(response) {
            $btn.prop('disabled', false);
            if (response.success && response.data) {
                var html = '<div class="sysman-connection-list">';
                response.data.forEach(function(item) {
                    var icon = item.ok ? '&#10004;' : '&#10008;';
                    var cls = item.ok ? 'sysman-conn-ok' : 'sysman-conn-fail';
                    html += '<div class="' + cls + '" style="padding:6px 0;display:flex;align-items:center;gap:8px;">';
                    html += '<span style="font-size:16px;' + (item.ok ? 'color:#2ecc71' : 'color:#e74c3c') + ';">' + icon + '</span>';
                    html += '<strong>' + item.label + '</strong>';
                    html += '<span style="color:#666;">— ' + item.message + '</span>';
                    html += '</div>';
                });
                html += '</div>';
                $results.html(html);
            } else {
                $results.html('<span style="color:#e74c3c;"><?php echo esc_js( __( 'Error al verificar las conexiones.', 'sysman-suite' ) ); ?></span>');
            }
        }).fail(function() {
            $btn.prop('disabled', false);
            $results.html('<span style="color:#e74c3c;"><?php echo esc_js( __( 'Error de comunicación con el servidor.', 'sysman-suite' ) ); ?></span>');
        });
    });
});
</script>
