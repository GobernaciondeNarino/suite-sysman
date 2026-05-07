<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$post_id = absint( $_GET['id'] ?? 0 );
$is_new  = ( 0 === $post_id );

$title       = '';
$dependencia = '';
$anio        = (int) date( 'Y' );
$mes         = (int) date( 'n' );
$compania    = get_option( 'sysman_api_compania', '001' );

if ( ! $is_new && $post_id ) {
    $post = get_post( $post_id );
    if ( $post && 'gn_ejecucion' === $post->post_type ) {
        $title       = $post->post_title;
        $dependencia = get_post_meta( $post_id, '_gn_dependencia', true );
        $anio        = (int) get_post_meta( $post_id, '_gn_anio', true );
        $mes         = (int) get_post_meta( $post_id, '_gn_mes', true );
        $compania    = get_post_meta( $post_id, '_gn_compania', true ) ?: '001';
    }
}

$meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
];

$current_year = (int) date( 'Y' );
?>
<div class="wrap sysman-admin-wrap">

    <div class="sysman-page-header">
        <div class="sysman-page-header-content">
            <div class="sysman-header-logo">
                <span class="dashicons dashicons-edit-large" aria-hidden="true" style="font-size:32px;width:32px;height:32px;color:#1a5632;"></span>
            </div>
            <div>
                <h1 class="sysman-page-title">
                    <?php echo $is_new
                        ? esc_html__( 'Nuevo Seguimiento de Ejecución', 'sysman-suite' )
                        : esc_html__( 'Editar Seguimiento', 'sysman-suite' ); ?>
                </h1>
                <p class="sysman-page-subtitle"><?php esc_html_e( 'Configure la dependencia y periodo de seguimiento', 'sysman-suite' ); ?></p>
            </div>
        </div>
        <div class="sysman-header-actions">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=sysman-ejecucion' ) ); ?>" class="button">
                &larr; <?php esc_html_e( 'Volver al listado', 'sysman-suite' ); ?>
            </a>
        </div>
    </div>

    <!-- Edit Form -->
    <div class="sysman-card">
        <div class="sysman-card-header">
            <h2>
                <span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
                <?php esc_html_e( 'Datos del Seguimiento', 'sysman-suite' ); ?>
            </h2>
        </div>
        <div class="sysman-card-body">
            <div id="gn-ejec-form">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="gn-ejec-title"><?php esc_html_e( 'Título', 'sysman-suite' ); ?></label></th>
                        <td>
                            <input type="text" id="gn-ejec-title" value="<?php echo esc_attr( $title ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'Ej: Seguimiento TIC Mayo 2026', 'sysman-suite' ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gn-ejec-compania"><?php esc_html_e( 'Compañía', 'sysman-suite' ); ?></label></th>
                        <td>
                            <select id="gn-ejec-compania">
                                <option value="001" <?php selected( $compania, '001' ); ?>>001 - <?php esc_html_e( 'Gobernación de Nariño', 'sysman-suite' ); ?></option>
                                <option value="007" <?php selected( $compania, '007' ); ?>>007 - <?php esc_html_e( 'SED (Secretaría de Educación)', 'sysman-suite' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gn-ejec-anio"><?php esc_html_e( 'Año', 'sysman-suite' ); ?></label></th>
                        <td>
                            <select id="gn-ejec-anio">
                                <?php for ( $y = $current_year; $y >= $current_year - 5; $y-- ) : ?>
                                <option value="<?php echo esc_attr( $y ); ?>" <?php selected( $anio, $y ); ?>><?php echo esc_html( $y ); ?></option>
                                <?php endfor; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gn-ejec-mes"><?php esc_html_e( 'Mes', 'sysman-suite' ); ?></label></th>
                        <td>
                            <select id="gn-ejec-mes">
                                <?php foreach ( $meses as $num => $nombre ) : ?>
                                <option value="<?php echo esc_attr( $num ); ?>" <?php selected( $mes, $num ); ?>><?php echo esc_html( $nombre ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gn-ejec-dependencia"><?php esc_html_e( 'Dependencia', 'sysman-suite' ); ?></label></th>
                        <td>
                            <select id="gn-ejec-dependencia" style="width:100%;max-width:600px;">
                                <option value=""><?php esc_html_e( 'Cargando dependencias...', 'sysman-suite' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'Se actualiza automáticamente al cambiar año/mes. Debe sincronizar los datos primero.', 'sysman-suite' ); ?></p>
                        </td>
                    </tr>
                </table>

                <div style="padding:1rem 0;display:flex;gap:12px;flex-wrap:wrap;">
                    <button type="button" id="gn-ejec-save" class="button button-primary button-hero">
                        <span class="dashicons dashicons-saved" aria-hidden="true" style="vertical-align:middle;margin-top:-2px;"></span>
                        <?php esc_html_e( 'Guardar Seguimiento', 'sysman-suite' ); ?>
                    </button>
                    <button type="button" id="gn-ejec-sync" class="button button-secondary button-hero">
                        <span class="dashicons dashicons-update" aria-hidden="true" style="vertical-align:middle;margin-top:-2px;"></span>
                        <?php esc_html_e( 'Sincronizar Datos Ahora', 'sysman-suite' ); ?>
                    </button>
                </div>

                <div id="gn-ejec-message" style="display:none;margin-top:12px;"></div>
                <div id="gn-ejec-sync-results" style="display:none;margin-top:12px;"></div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(function($) {
    var postId = <?php echo (int) $post_id; ?>;
    var savedDep = <?php echo wp_json_encode( $dependencia ); ?>;

    function loadDependencias() {
        var anio = $('#gn-ejec-anio').val();
        var mes  = $('#gn-ejec-mes').val();
        var comp = $('#gn-ejec-compania').val();

        var $sel = $('#gn-ejec-dependencia');
        $sel.html('<option value=""><?php echo esc_js( __( 'Cargando...', 'sysman-suite' ) ); ?></option>');

        $.ajax({
            url: gnEjecucion.restUrl + 'dependencias',
            data: { anio: anio, mes: mes, compania: comp },
            beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', gnEjecucion.restNonce); },
            success: function(data) {
                $sel.empty();
                if (!data || data.length === 0) {
                    $sel.append('<option value=""><?php echo esc_js( __( 'Sin dependencias — sincronice primero', 'sysman-suite' ) ); ?></option>');
                    return;
                }
                $sel.append('<option value=""><?php echo esc_js( __( 'Seleccione una dependencia', 'sysman-suite' ) ); ?></option>');
                data.forEach(function(dep) {
                    var selected = (dep === savedDep) ? ' selected' : '';
                    $sel.append('<option value="' + $('<span>').text(dep).html() + '"' + selected + '>' + $('<span>').text(dep).html() + '</option>');
                });
            },
            error: function() {
                $sel.html('<option value=""><?php echo esc_js( __( 'Error al cargar dependencias', 'sysman-suite' ) ); ?></option>');
            }
        });
    }

    loadDependencias();
    $('#gn-ejec-anio, #gn-ejec-mes, #gn-ejec-compania').on('change', loadDependencias);

    // Save
    $('#gn-ejec-save').on('click', function() {
        var $btn = $(this).prop('disabled', true);
        var $msg = $('#gn-ejec-message');

        $.post(gnEjecucion.ajaxUrl, {
            action: 'gn_ejecucion_save',
            nonce: gnEjecucion.nonce,
            post_id: postId,
            title: $('#gn-ejec-title').val(),
            dependencia: $('#gn-ejec-dependencia').val(),
            anio: $('#gn-ejec-anio').val(),
            mes: $('#gn-ejec-mes').val(),
            compania: $('#gn-ejec-compania').val()
        }, function(resp) {
            $btn.prop('disabled', false);
            if (resp.success) {
                postId = resp.data.post_id;
                savedDep = $('#gn-ejec-dependencia').val();
                $msg.html('<div class="notice notice-success inline"><p><?php echo esc_js( __( 'Seguimiento guardado exitosamente.', 'sysman-suite' ) ); ?></p></div>').show();
                history.replaceState(null, '', '<?php echo esc_url( admin_url( 'admin.php?page=sysman-ejecucion&action=edit&id=' ) ); ?>' + postId);
            } else {
                $msg.html('<div class="notice notice-error inline"><p>' + (resp.data || 'Error') + '</p></div>').show();
            }
        });
    });

    // Sync
    $('#gn-ejec-sync').on('click', function() {
        var $btn = $(this).prop('disabled', true);
        var $res = $('#gn-ejec-sync-results');
        $res.html('<p><span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span><?php echo esc_js( __( 'Sincronizando datos desde SYSMAN (esto puede tomar varios minutos)...', 'sysman-suite' ) ); ?></p>').show();

        $.post(gnEjecucion.ajaxUrl, {
            action: 'gn_ejecucion_sync',
            nonce: gnEjecucion.nonce,
            compania: $('#gn-ejec-compania').val(),
            anio: $('#gn-ejec-anio').val(),
            mes: $('#gn-ejec-mes').val()
        }, function(resp) {
            $btn.prop('disabled', false);
            if (resp.success) {
                var d = resp.data;
                var html = '<div class="notice notice-success inline"><p><strong><?php echo esc_js( __( 'Sincronización completada', 'sysman-suite' ) ); ?></strong></p><ul style="margin:0.5rem 0 0 1.5rem;list-style:disc;">';
                html += '<li>Plan Presupuestal: ' + (d.plan.inserted || 0) + ' registros</li>';
                html += '<li>Ejecución Gastos: ' + (d.ejecucion.inserted || 0) + ' registros</li>';
                html += '<li>Disponibilidades (DIS): ' + (d.dis.inserted || 0) + ' registros</li>';
                html += '<li>Reservas (RES): ' + (d.res.inserted || 0) + ' registros</li>';
                html += '</ul></div>';
                $res.html(html);
                loadDependencias();
            } else {
                $res.html('<div class="notice notice-error inline"><p><?php echo esc_js( __( 'Error durante la sincronización.', 'sysman-suite' ) ); ?></p></div>');
            }
        }).fail(function() {
            $btn.prop('disabled', false);
            $res.html('<div class="notice notice-error inline"><p><?php echo esc_js( __( 'Error de comunicación con el servidor.', 'sysman-suite' ) ); ?></p></div>');
        });
    });
});
</script>
