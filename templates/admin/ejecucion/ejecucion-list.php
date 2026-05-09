<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$posts = get_posts( [
    'post_type'      => 'gn_ejecucion',
    'posts_per_page' => -1,
    'post_status'    => 'publish',
    'orderby'        => 'modified',
    'order'          => 'DESC',
] );

$meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
];
?>
<div class="wrap sysman-admin-wrap">

    <div class="sysman-page-header">
        <div class="sysman-page-header-content">
            <div class="sysman-header-logo">
                <span class="dashicons dashicons-chart-line" aria-hidden="true" style="font-size:32px;width:32px;height:32px;color:#1a5632;"></span>
            </div>
            <div>
                <h1 class="sysman-page-title"><?php esc_html_e( 'Ejecución Presupuestal', 'sysman-suite' ); ?></h1>
                <p class="sysman-page-subtitle"><?php esc_html_e( 'Seguimientos de ejecución por dependencia', 'sysman-suite' ); ?></p>
            </div>
        </div>
        <div class="sysman-header-actions">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=sysman-ejecucion&action=new' ) ); ?>" class="button button-primary">
                <span class="dashicons dashicons-plus-alt2" aria-hidden="true" style="vertical-align:middle;margin-top:-2px;"></span>
                <?php esc_html_e( 'Nuevo Seguimiento', 'sysman-suite' ); ?>
            </a>
        </div>
    </div>

    <div class="sysman-card">
        <div class="sysman-card-body">
            <?php if ( empty( $posts ) ) : ?>
                <div style="text-align:center;padding:3rem 1rem;color:#6b7280;">
                    <span class="dashicons dashicons-chart-line" style="font-size:48px;width:48px;height:48px;display:block;margin:0 auto 1rem;opacity:0.3;"></span>
                    <p style="font-size:1.1rem;margin-bottom:0.5rem;"><?php esc_html_e( 'No hay seguimientos de ejecución', 'sysman-suite' ); ?></p>
                    <p><?php esc_html_e( 'Crea un nuevo seguimiento para comenzar a explorar la trazabilidad presupuestal.', 'sysman-suite' ); ?></p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=sysman-ejecucion&action=new' ) ); ?>" class="button button-primary" style="margin-top:1rem;">
                        <?php esc_html_e( 'Crear Primer Seguimiento', 'sysman-suite' ); ?>
                    </a>
                </div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:25%;"><?php esc_html_e( 'Título', 'sysman-suite' ); ?></th>
                            <th><?php esc_html_e( 'Dependencia', 'sysman-suite' ); ?></th>
                            <th style="width:120px;"><?php esc_html_e( 'Periodo', 'sysman-suite' ); ?></th>
                            <th style="width:80px;"><?php esc_html_e( 'Compañía', 'sysman-suite' ); ?></th>
                            <th style="width:220px;"><?php esc_html_e( 'Shortcode', 'sysman-suite' ); ?></th>
                            <th style="width:160px;"><?php esc_html_e( 'Última Actualización', 'sysman-suite' ); ?></th>
                            <th style="width:180px;"><?php esc_html_e( 'Acciones', 'sysman-suite' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $posts as $p ) :
                            $dep  = get_post_meta( $p->ID, '_gn_dependencia', true );
                            $anio = get_post_meta( $p->ID, '_gn_anio', true );
                            $mes  = (int) get_post_meta( $p->ID, '_gn_mes', true );
                            $comp = get_post_meta( $p->ID, '_gn_compania', true ) ?: '001';
                            $mes_name = $meses[ $mes ] ?? $mes;
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html( $p->post_title ); ?></strong></td>
                            <td><?php echo esc_html( $dep ); ?></td>
                            <td><?php echo esc_html( $mes_name . ' ' . $anio ); ?></td>
                            <td><?php echo esc_html( $comp ); ?></td>
                            <td>
                                <code class="gn-shortcode-copy" title="<?php esc_attr_e( 'Clic para copiar', 'sysman-suite' ); ?>" style="cursor:pointer;padding:4px 8px;background:#f0f0f1;border-radius:3px;font-size:12px;user-select:all;">[gn_ejecucion id="<?php echo esc_attr( $p->ID ); ?>"]</code>
                            </td>
                            <td><?php echo esc_html( get_the_modified_date( 'd/m/Y H:i', $p ) ); ?></td>
                            <td>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=sysman-ejecucion&action=view&id=' . $p->ID ) ); ?>" class="button button-small" title="<?php esc_attr_e( 'Ver', 'sysman-suite' ); ?>">
                                    <span class="dashicons dashicons-visibility" style="vertical-align:middle;margin-top:-2px;"></span>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=sysman-ejecucion&action=edit&id=' . $p->ID ) ); ?>" class="button button-small" title="<?php esc_attr_e( 'Editar', 'sysman-suite' ); ?>">
                                    <span class="dashicons dashicons-edit" style="vertical-align:middle;margin-top:-2px;"></span>
                                </a>
                                <button type="button" class="button button-small gn-ejec-delete-btn" data-id="<?php echo esc_attr( $p->ID ); ?>" title="<?php esc_attr_e( 'Eliminar', 'sysman-suite' ); ?>">
                                    <span class="dashicons dashicons-trash" style="vertical-align:middle;margin-top:-2px;color:#dc2626;"></span>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
jQuery(function($) {
    $('.gn-shortcode-copy').on('click', function() {
        var $el = $(this);
        var text = $el.text();
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() {
                var orig = $el.text();
                $el.text('<?php echo esc_js( __( '¡Copiado!', 'sysman-suite' ) ); ?>').css('background', '#d1fae5');
                setTimeout(function(){ $el.text(orig).css('background', ''); }, 1500);
            });
        }
    });

    $('.gn-ejec-delete-btn').on('click', function() {
        if (!confirm('<?php echo esc_js( __( '¿Eliminar este seguimiento?', 'sysman-suite' ) ); ?>')) return;
        var $btn = $(this);
        var postId = $btn.data('id');
        $.post(gnEjecucion.ajaxUrl, {
            action: 'gn_ejecucion_delete',
            nonce: gnEjecucion.nonce,
            post_id: postId
        }, function(resp) {
            if (resp.success) {
                $btn.closest('tr').fadeOut(300, function(){ $(this).remove(); });
            }
        });
    });
});
</script>
