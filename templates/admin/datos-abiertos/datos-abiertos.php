<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$ejecucion_posts = get_posts( [
    'post_type'      => 'gn_ejecucion',
    'posts_per_page' => -1,
    'post_status'    => 'publish',
    'orderby'        => 'title',
    'order'          => 'ASC',
] );

$current_year = (int) date( 'Y' );
$site_url     = rest_url( 'gn-sisman/v1/' );
?>
<div class="wrap sysman-admin-wrap">

    <div class="sysman-page-header">
        <div class="sysman-page-header-content">
            <div class="sysman-header-logo">
                <span class="dashicons dashicons-open-folder" aria-hidden="true" style="font-size:32px;width:32px;height:32px;color:#1a5632;"></span>
            </div>
            <div>
                <h1 class="sysman-page-title"><?php esc_html_e( 'Datos Abiertos', 'sysman-suite' ); ?></h1>
                <p class="sysman-page-subtitle"><?php esc_html_e( 'Shortcodes para publicar datos presupuestales en su sitio web. Copie y pegue en cualquier pagina o entrada.', 'sysman-suite' ); ?></p>
            </div>
        </div>
    </div>

    <!-- ================================================================
         CARD 1: Ejecucion Presupuestal
         ================================================================ -->
    <div class="sysman-card" style="margin-bottom:1.25rem;">
        <div class="sysman-card-body" style="padding:1.25rem;">

            <div style="display:flex;align-items:center;gap:10px;margin-bottom:1rem;">
                <span class="dashicons dashicons-chart-line" style="color:#1a5276;font-size:22px;width:22px;height:22px;"></span>
                <div style="flex:1;">
                    <strong style="color:#1a5276;font-size:1.05rem;"><?php esc_html_e( 'Ejecucion Presupuestal', 'sysman-suite' ); ?></strong>
                    <p style="margin:2px 0 0;font-size:0.85rem;color:#6b7280;">
                        <?php esc_html_e( 'Exporta los rubros con datos de ejecucion consolidada (apropiaciones, compromisos, obligaciones, pagos) para un seguimiento especifico.', 'sysman-suite' ); ?>
                    </p>
                </div>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=sysman-ejecucion' ) ); ?>" class="button button-small" style="white-space:nowrap;">
                    <span class="dashicons dashicons-admin-settings" style="vertical-align:middle;margin-top:-2px;"></span>
                    <?php esc_html_e( 'Gestionar seguimientos', 'sysman-suite' ); ?>
                </a>
            </div>

            <table class="widefat striped" style="margin-bottom:1rem;">
                <thead>
                    <tr>
                        <th style="width:140px;"><?php esc_html_e( 'Atributo', 'sysman-suite' ); ?></th>
                        <th style="width:100px;"><?php esc_html_e( 'Requerido', 'sysman-suite' ); ?></th>
                        <th><?php esc_html_e( 'Descripcion', 'sysman-suite' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>id</code></td>
                        <td><strong style="color:#dc2626;"><?php esc_html_e( 'Si', 'sysman-suite' ); ?></strong></td>
                        <td><?php esc_html_e( 'ID del seguimiento de ejecucion (post type gn_ejecucion)', 'sysman-suite' ); ?></td>
                    </tr>
                </tbody>
            </table>

            <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:6px;padding:1rem;">
                <label style="font-weight:600;font-size:0.88rem;color:#1f2937;display:block;margin-bottom:6px;">
                    <?php esc_html_e( 'Seleccione un seguimiento:', 'sysman-suite' ); ?>
                </label>
                <select id="gn-da-ejec-select" style="width:100%;max-width:500px;margin-bottom:0.75rem;">
                    <option value="">— <?php esc_html_e( 'Seleccionar', 'sysman-suite' ); ?> —</option>
                    <?php foreach ( $ejecucion_posts as $ep ) : ?>
                        <option value="<?php echo esc_attr( $ep->ID ); ?>">
                            <?php echo esc_html( $ep->post_title . ' (ID: ' . $ep->ID . ')' ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <span style="font-weight:600;color:#1a5276;font-size:0.85rem;">Shortcode:</span>
                    <code id="gn-da-ejec-shortcode" style="flex:1;min-width:200px;padding:6px 12px;background:#fff;border:1px solid #d1d5db;border-radius:4px;font-size:13px;color:#6b7280;user-select:all;">
                        <?php esc_html_e( 'Seleccione un seguimiento arriba', 'sysman-suite' ); ?>
                    </code>
                    <button type="button" id="gn-da-ejec-copy" class="button button-small" disabled>
                        <span class="dashicons dashicons-clipboard" style="vertical-align:middle;margin-top:-2px;"></span>
                        <?php esc_html_e( 'Copiar', 'sysman-suite' ); ?>
                    </button>
                </div>
            </div>

        </div>
    </div>

    <!-- ================================================================
         CARD 2: Reporte Disponibilidades (DIS)
         ================================================================ -->
    <div class="sysman-card" style="margin-bottom:1.25rem;">
        <div class="sysman-card-body" style="padding:1.25rem;">

            <div style="display:flex;align-items:center;gap:10px;margin-bottom:1rem;">
                <span class="dashicons dashicons-list-view" style="color:#1a5632;font-size:22px;width:22px;height:22px;"></span>
                <div>
                    <strong style="color:#1a5632;font-size:1.05rem;"><?php esc_html_e( 'Reporte Disponibilidades (DIS)', 'sysman-suite' ); ?></strong>
                    <p style="margin:2px 0 0;font-size:0.85rem;color:#6b7280;">
                        <?php esc_html_e( 'Exporta los registros de disponibilidades presupuestales (DIS) cruzados con la informacion del plan presupuestal (rubro, dependencia, sector).', 'sysman-suite' ); ?>
                    </p>
                </div>
            </div>

            <table class="widefat striped" style="margin-bottom:1rem;">
                <thead>
                    <tr>
                        <th style="width:140px;"><?php esc_html_e( 'Atributo', 'sysman-suite' ); ?></th>
                        <th style="width:100px;"><?php esc_html_e( 'Requerido', 'sysman-suite' ); ?></th>
                        <th><?php esc_html_e( 'Descripcion', 'sysman-suite' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>anio</code></td>
                        <td><strong style="color:#dc2626;"><?php esc_html_e( 'Si', 'sysman-suite' ); ?></strong></td>
                        <td><?php esc_html_e( 'Ano fiscal (ej: 2025)', 'sysman-suite' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>mes</code></td>
                        <td><strong style="color:#dc2626;"><?php esc_html_e( 'Si', 'sysman-suite' ); ?></strong></td>
                        <td><?php esc_html_e( 'Mes (1–12)', 'sysman-suite' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>compania</code></td>
                        <td style="color:#6b7280;"><?php esc_html_e( 'No', 'sysman-suite' ); ?></td>
                        <td><?php esc_html_e( 'Codigo de compania (por defecto: 001)', 'sysman-suite' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>dependencia</code></td>
                        <td style="color:#6b7280;"><?php esc_html_e( 'No', 'sysman-suite' ); ?></td>
                        <td><?php esc_html_e( 'Nombre de la dependencia para filtrar (si se omite muestra todas)', 'sysman-suite' ); ?></td>
                    </tr>
                </tbody>
            </table>

            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:1rem;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:0.75rem;max-width:500px;">
                    <div>
                        <label for="gn-da-dis-anio" style="font-weight:600;font-size:0.82rem;color:#1f2937;display:block;margin-bottom:3px;">
                            <?php esc_html_e( 'Ano:', 'sysman-suite' ); ?>
                        </label>
                        <input type="number" id="gn-da-dis-anio" value="<?php echo esc_attr( $current_year ); ?>" min="2020" max="2099" class="regular-text" style="width:100%;">
                    </div>
                    <div>
                        <label for="gn-da-dis-mes" style="font-weight:600;font-size:0.82rem;color:#1f2937;display:block;margin-bottom:3px;">
                            <?php esc_html_e( 'Mes:', 'sysman-suite' ); ?>
                        </label>
                        <select id="gn-da-dis-mes" class="regular-text" style="width:100%;">
                            <?php for ( $m = 1; $m <= 12; $m++ ) : ?>
                                <option value="<?php echo $m; ?>" <?php selected( (int) date('n'), $m ); ?>>
                                    <?php echo esc_html( $m . ' — ' . [ 1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre' ][ $m ] ); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:0.75rem;max-width:500px;">
                    <div>
                        <label for="gn-da-dis-compania" style="font-weight:600;font-size:0.82rem;color:#1f2937;display:block;margin-bottom:3px;">
                            <?php esc_html_e( 'Compania:', 'sysman-suite' ); ?>
                        </label>
                        <input type="text" id="gn-da-dis-compania" value="001" class="regular-text" style="width:100%;">
                    </div>
                    <div>
                        <label for="gn-da-dis-dep" style="font-weight:600;font-size:0.82rem;color:#1f2937;display:block;margin-bottom:3px;">
                            <?php esc_html_e( 'Dependencia (opcional):', 'sysman-suite' ); ?>
                        </label>
                        <input type="text" id="gn-da-dis-dep" value="" placeholder="Todas" class="regular-text" style="width:100%;">
                    </div>
                </div>

                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <span style="font-weight:600;color:#1a5632;font-size:0.85rem;">Shortcode:</span>
                    <code id="gn-da-dis-shortcode" style="flex:1;min-width:200px;padding:6px 12px;background:#fff;border:1px solid #d1d5db;border-radius:4px;font-size:13px;user-select:all;word-break:break-all;"></code>
                    <button type="button" id="gn-da-dis-copy" class="button button-small">
                        <span class="dashicons dashicons-clipboard" style="vertical-align:middle;margin-top:-2px;"></span>
                        <?php esc_html_e( 'Copiar', 'sysman-suite' ); ?>
                    </button>
                </div>
            </div>

        </div>
    </div>

    <!-- ================================================================
         API Endpoints Reference
         ================================================================ -->
    <div class="sysman-card">
        <div class="sysman-card-body" style="padding:1.25rem;">

            <div style="display:flex;align-items:center;gap:10px;margin-bottom:1rem;">
                <span class="dashicons dashicons-rest-api" style="color:#003087;font-size:22px;width:22px;height:22px;"></span>
                <strong style="color:#003087;font-size:1.05rem;"><?php esc_html_e( 'API JSON — Endpoints Publicos', 'sysman-suite' ); ?></strong>
            </div>

            <p style="font-size:0.88rem;color:#6b7280;margin:0 0 1rem;">
                <?php esc_html_e( 'Estos endpoints REST estan disponibles sin autenticacion para integraciones externas y reutilizacion de datos.', 'sysman-suite' ); ?>
            </p>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th style="width:100px;"><?php esc_html_e( 'Metodo', 'sysman-suite' ); ?></th>
                        <th><?php esc_html_e( 'Endpoint', 'sysman-suite' ); ?></th>
                        <th><?php esc_html_e( 'Descripcion', 'sysman-suite' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code style="background:#dbeafe;color:#1e40af;padding:2px 6px;border-radius:3px;">GET</code></td>
                        <td><code style="font-size:0.82rem;"><?php echo esc_html( $site_url . 'ejecucion/{id}/export' ); ?></code></td>
                        <td><?php esc_html_e( 'Rubros + ejecucion consolidada de un seguimiento', 'sysman-suite' ); ?></td>
                    </tr>
                    <tr>
                        <td><code style="background:#dbeafe;color:#1e40af;padding:2px 6px;border-radius:3px;">GET</code></td>
                        <td><code style="font-size:0.82rem;"><?php echo esc_html( $site_url . 'reporte/disponibilidades?anio=X&mes=X' ); ?></code></td>
                        <td><?php esc_html_e( 'Disponibilidades (DIS) cruzadas con plan presupuestal', 'sysman-suite' ); ?></td>
                    </tr>
                    <tr>
                        <td><code style="background:#dbeafe;color:#1e40af;padding:2px 6px;border-radius:3px;">GET</code></td>
                        <td><code style="font-size:0.82rem;"><?php echo esc_html( $site_url . 'ejecucion/{id}/rubros' ); ?></code></td>
                        <td><?php esc_html_e( 'Lista de rubros de un seguimiento', 'sysman-suite' ); ?></td>
                    </tr>
                    <tr>
                        <td><code style="background:#dbeafe;color:#1e40af;padding:2px 6px;border-radius:3px;">GET</code></td>
                        <td><code style="font-size:0.82rem;"><?php echo esc_html( $site_url . 'ejecucion/{id}/consolidado?codigo=X' ); ?></code></td>
                        <td><?php esc_html_e( 'Ejecucion consolidada de un rubro especifico', 'sysman-suite' ); ?></td>
                    </tr>
                    <tr>
                        <td><code style="background:#dbeafe;color:#1e40af;padding:2px 6px;border-radius:3px;">GET</code></td>
                        <td><code style="font-size:0.82rem;"><?php echo esc_html( $site_url . 'ejecucion/{id}/dis?codigocuenta=X' ); ?></code></td>
                        <td><?php esc_html_e( 'Disponibilidades por rubro', 'sysman-suite' ); ?></td>
                    </tr>
                    <tr>
                        <td><code style="background:#dbeafe;color:#1e40af;padding:2px 6px;border-radius:3px;">GET</code></td>
                        <td><code style="font-size:0.82rem;"><?php echo esc_html( $site_url . 'ejecucion/{id}/res?numero_dis=X&rubro=X' ); ?></code></td>
                        <td><?php esc_html_e( 'Registros de compromiso (RES) por disponibilidad', 'sysman-suite' ); ?></td>
                    </tr>
                </tbody>
            </table>

        </div>
    </div>

</div>

<script>
jQuery(function($) {

    function copyText($btn, text) {
        if (!navigator.clipboard || !text) return;
        navigator.clipboard.writeText(text).then(function() {
            $btn.find('.dashicons').removeClass('dashicons-clipboard').addClass('dashicons-yes');
            setTimeout(function() {
                $btn.find('.dashicons').removeClass('dashicons-yes').addClass('dashicons-clipboard');
            }, 1500);
        });
    }

    // ── Card 1: Ejecucion ──
    $('#gn-da-ejec-select').on('change', function() {
        var id = $(this).val();
        var $code = $('#gn-da-ejec-shortcode');
        var $btn  = $('#gn-da-ejec-copy');
        if (id) {
            $code.text('[gn_ejecucion_export id="' + id + '"]').css('color', '#1f2937');
            $btn.prop('disabled', false);
        } else {
            $code.text('<?php echo esc_js( __( 'Seleccione un seguimiento arriba', 'sysman-suite' ) ); ?>').css('color', '#6b7280');
            $btn.prop('disabled', true);
        }
    });

    $('#gn-da-ejec-copy').on('click', function() {
        var text = $('#gn-da-ejec-shortcode').text();
        if (text && !$(this).prop('disabled')) {
            copyText($(this), text);
        }
    });

    // ── Card 2: Reporte DIS ──
    function updateDisShortcode() {
        var anio = $('#gn-da-dis-anio').val();
        var mes  = $('#gn-da-dis-mes').val();
        var comp = $('#gn-da-dis-compania').val();
        var dep  = $('#gn-da-dis-dep').val().trim();

        var sc = '[gn_reporte_dis anio="' + anio + '" mes="' + mes + '"';
        if (comp && comp !== '001') {
            sc += ' compania="' + comp + '"';
        }
        if (dep) {
            sc += ' dependencia="' + dep + '"';
        }
        sc += ']';
        $('#gn-da-dis-shortcode').text(sc);
    }

    $('#gn-da-dis-anio, #gn-da-dis-mes, #gn-da-dis-compania, #gn-da-dis-dep').on('input change', updateDisShortcode);
    updateDisShortcode();

    $('#gn-da-dis-copy').on('click', function() {
        copyText($(this), $('#gn-da-dis-shortcode').text());
    });
});
</script>
