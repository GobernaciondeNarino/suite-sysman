<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$ejecucion_posts = get_posts( [
    'post_type'      => 'gn_ejecucion',
    'posts_per_page' => -1,
    'post_status'    => 'publish',
    'orderby'        => 'title',
    'order'          => 'ASC',
] );

$current_year = (int) current_time( 'Y' );
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
    <div class="sysman-card" style="margin-bottom:1.25rem;">
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

    <!-- ================================================================
         Filtros dinamicos
         ================================================================ -->
    <div class="sysman-card" style="margin-bottom:1.25rem;">
        <div class="sysman-card-body" style="padding:1.25rem;">

            <div style="display:flex;align-items:center;gap:10px;margin-bottom:1rem;">
                <span class="dashicons dashicons-filter" style="color:#7c3aed;font-size:22px;width:22px;height:22px;"></span>
                <strong style="color:#7c3aed;font-size:1.05rem;"><?php esc_html_e( 'Filtros Dinamicos en la API', 'sysman-suite' ); ?></strong>
            </div>

            <p style="font-size:0.88rem;color:#6b7280;margin:0 0 0.75rem;">
                <?php esc_html_e( 'Los endpoints de export y reporte/disponibilidades aceptan parametros adicionales para filtrar, buscar, ordenar y paginar los resultados.', 'sysman-suite' ); ?>
            </p>

            <div style="background:#faf5ff;border:1px solid #e9d5ff;border-radius:6px;padding:1rem;margin-bottom:1rem;">
                <strong style="font-size:0.88rem;color:#1f2937;display:block;margin-bottom:6px;">
                    <?php esc_html_e( 'Ejemplo:', 'sysman-suite' ); ?>
                </strong>
                <code style="font-size:0.82rem;word-break:break-all;display:block;background:#fff;padding:8px 12px;border-radius:4px;border:1px solid #e5e7eb;">
                    <?php echo esc_html( $site_url . 'reporte/disponibilidades?anio=2026&mes=5&numero=2026040001' ); ?>
                </code>
                <p style="font-size:0.82rem;color:#6b7280;margin:6px 0 0;">
                    <?php esc_html_e( 'Filtra las disponibilidades del 2026-05 donde el numero sea exactamente "2026040001".', 'sysman-suite' ); ?>
                </p>
            </div>

            <table class="widefat striped" style="margin-bottom:1rem;">
                <thead>
                    <tr>
                        <th style="width:160px;"><?php esc_html_e( 'Parametro', 'sysman-suite' ); ?></th>
                        <th style="width:90px;"><?php esc_html_e( 'Tipo', 'sysman-suite' ); ?></th>
                        <th><?php esc_html_e( 'Descripcion', 'sysman-suite' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="background:#f0f9ff;">
                        <td colspan="3"><strong style="color:#1e40af;"><?php esc_html_e( 'Parametros generales (ambos endpoints)', 'sysman-suite' ); ?></strong></td>
                    </tr>
                    <tr>
                        <td><code>buscar</code></td>
                        <td>LIKE</td>
                        <td><?php esc_html_e( 'Busqueda global en todos los campos de texto (nombre, descripcion, tercero, etc.)', 'sysman-suite' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>per_page</code></td>
                        <td>int</td>
                        <td><?php esc_html_e( 'Registros por pagina (1–1000). Activa la paginacion en la respuesta.', 'sysman-suite' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>pagina</code></td>
                        <td>int</td>
                        <td><?php esc_html_e( 'Numero de pagina (por defecto: 1). Requiere per_page.', 'sysman-suite' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>orderby</code></td>
                        <td>string</td>
                        <td><?php esc_html_e( 'Campo para ordenar (debe ser un nombre de filtro valido).', 'sysman-suite' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>order</code></td>
                        <td>string</td>
                        <td><?php esc_html_e( 'Direccion del orden: ASC o DESC (por defecto: ASC).', 'sysman-suite' ); ?></td>
                    </tr>
                    <tr style="background:#f0fdf4;">
                        <td colspan="3"><strong style="color:#166534;"><?php esc_html_e( 'Filtros — reporte/disponibilidades', 'sysman-suite' ); ?></strong></td>
                    </tr>
                    <?php
                    $dis_filters = [
                        'numero'            => [ 'exacto', 'Numero de disponibilidad' ],
                        'tercero'           => [ 'exacto', 'Codigo del tercero' ],
                        'nombretercero'     => [ 'contiene', 'Nombre del tercero' ],
                        'rubro'             => [ 'exacto', 'Codigo del rubro' ],
                        'nombrerubro'       => [ 'contiene', 'Nombre del rubro' ],
                        'descripcion'       => [ 'contiene', 'Descripcion del registro' ],
                        'nrodocumento'      => [ 'exacto', 'Numero de documento' ],
                        'cmpteafectado'     => [ 'exacto', 'Comprobante afectado' ],
                        'fecha'             => [ 'exacto', 'Fecha (YYYY-MM-DD)' ],
                        'nombredependencia' => [ 'contiene', 'Nombre de la dependencia' ],
                        'destino'           => [ 'exacto', 'Destino del gasto' ],
                        'naturaleza'        => [ 'exacto', 'Naturaleza' ],
                        'sector'            => [ 'contiene', 'Sector' ],
                        'programa'          => [ 'contiene', 'Programa' ],
                        'subprograma'       => [ 'contiene', 'Subprograma' ],
                        'codigoproducto'    => [ 'exacto', 'Codigo del producto' ],
                        'codigobpin'        => [ 'exacto', 'Codigo BPIN del proyecto' ],
                    ];
                    foreach ( $dis_filters as $param => $info ) : ?>
                        <tr>
                            <td><code><?php echo esc_html( $param ); ?></code></td>
                            <td><span style="font-size:0.8rem;color:<?php echo 'exacto' === $info[0] ? '#dc2626' : '#2563eb'; ?>;"><?php echo esc_html( $info[0] ); ?></span></td>
                            <td style="font-size:0.85rem;"><?php echo esc_html( $info[1] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="background:#fffbeb;">
                        <td colspan="3"><strong style="color:#92400e;"><?php esc_html_e( 'Filtros — ejecucion/{id}/export', 'sysman-suite' ); ?></strong></td>
                    </tr>
                    <?php
                    $export_filters = [
                        'codigo'         => [ 'exacto', 'Codigo del rubro' ],
                        'nombre'         => [ 'contiene', 'Nombre del rubro' ],
                        'destino'        => [ 'exacto', 'Destino del gasto' ],
                        'naturaleza'     => [ 'exacto', 'Naturaleza' ],
                        'sector'         => [ 'contiene', 'Sector' ],
                        'programa'       => [ 'contiene', 'Programa' ],
                        'subprograma'    => [ 'contiene', 'Subprograma' ],
                        'codigoproducto' => [ 'exacto', 'Codigo del producto' ],
                        'codigobpin'     => [ 'exacto', 'Codigo BPIN del proyecto' ],
                    ];
                    foreach ( $export_filters as $param => $info ) : ?>
                        <tr>
                            <td><code><?php echo esc_html( $param ); ?></code></td>
                            <td><span style="font-size:0.8rem;color:<?php echo 'exacto' === $info[0] ? '#dc2626' : '#2563eb'; ?>;"><?php echo esc_html( $info[0] ); ?></span></td>
                            <td style="font-size:0.85rem;"><?php echo esc_html( $info[1] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="background:#f0f9ff;border:1px solid #bfdbfe;border-radius:6px;padding:1rem;">
                <strong style="font-size:0.88rem;color:#1e40af;display:block;margin-bottom:6px;">
                    <?php esc_html_e( 'Respuesta con paginacion:', 'sysman-suite' ); ?>
                </strong>
                <pre style="font-size:0.8rem;margin:0;background:#fff;padding:10px;border-radius:4px;border:1px solid #e5e7eb;overflow-x:auto;">{
  "meta": { ... },
  "total": 500,
  "pagina": 1,
  "per_page": 50,
  "paginas": 10,
  "filtros": { "numero": "2026040001" },
  "data": [ ... ]
}</pre>
            </div>

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
