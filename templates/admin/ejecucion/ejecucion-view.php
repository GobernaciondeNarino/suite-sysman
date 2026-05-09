<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$post_id = absint( $_GET['id'] ?? 0 );
$post    = $post_id ? get_post( $post_id ) : null;

if ( ! $post || 'gn_ejecucion' !== $post->post_type ) {
    echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Seguimiento no encontrado.', 'sysman-suite' ) . '</p></div></div>';
    return;
}

$dependencia = get_post_meta( $post_id, '_gn_dependencia', true );
$anio        = get_post_meta( $post_id, '_gn_anio', true );
$mes         = (int) get_post_meta( $post_id, '_gn_mes', true );
$compania    = get_post_meta( $post_id, '_gn_compania', true ) ?: '001';

$meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
];
$mes_nombre = $meses[ $mes ] ?? $mes;
?>
<div class="wrap sysman-admin-wrap">

    <div class="sysman-page-header">
        <div class="sysman-page-header-content">
            <div class="sysman-header-logo">
                <span class="dashicons dashicons-chart-line" aria-hidden="true" style="font-size:32px;width:32px;height:32px;color:#1a5632;"></span>
            </div>
            <div>
                <h1 class="sysman-page-title"><?php echo esc_html( $post->post_title ); ?></h1>
                <p class="sysman-page-subtitle">
                    <?php echo esc_html( $dependencia ); ?> &mdash; <?php echo esc_html( $mes_nombre . ' ' . $anio ); ?> &mdash; <?php esc_html_e( 'Compañía', 'sysman-suite' ); ?> <?php echo esc_html( $compania ); ?>
                </p>
            </div>
        </div>
        <div class="sysman-header-actions">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=sysman-ejecucion&action=edit&id=' . $post_id ) ); ?>" class="button">
                <span class="dashicons dashicons-edit" aria-hidden="true" style="vertical-align:middle;margin-top:-2px;"></span>
                <?php esc_html_e( 'Editar', 'sysman-suite' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=sysman-ejecucion' ) ); ?>" class="button">
                &larr; <?php esc_html_e( 'Volver', 'sysman-suite' ); ?>
            </a>
        </div>
    </div>

    <div class="sysman-card" style="margin-bottom:1rem;">
        <div class="sysman-card-body" style="padding:0.75rem 1rem;display:flex;align-items:center;gap:12px;flex-wrap:wrap;background:#f0f6ff;border:1px solid #c3dafe;border-radius:6px;">
            <span class="dashicons dashicons-shortcode" style="color:#1a5276;font-size:20px;width:20px;height:20px;"></span>
            <span style="font-weight:600;color:#1a5276;"><?php esc_html_e( 'Shortcode:', 'sysman-suite' ); ?></span>
            <code id="gn-view-shortcode" style="padding:6px 12px;background:#fff;border:1px solid #d1d5db;border-radius:4px;font-size:13px;user-select:all;">[gn_ejecucion id="<?php echo esc_attr( $post_id ); ?>"]</code>
            <button type="button" id="gn-view-copy-shortcode" class="button button-small">
                <span class="dashicons dashicons-clipboard" style="vertical-align:middle;margin-top:-2px;"></span>
                <?php esc_html_e( 'Copiar', 'sysman-suite' ); ?>
            </button>
            <span class="description"><?php esc_html_e( 'Pegue este shortcode en cualquier página o entrada para mostrar este seguimiento.', 'sysman-suite' ); ?></span>
        </div>
    </div>

    <div class="sysman-card" style="overflow:visible;">
        <div class="sysman-card-body" style="padding:0;">
            <?php echo \SysmanSuite\Ejecucion\AccordionRenderer::render( $post_id ); ?>
        </div>
    </div>
</div>
<script>
jQuery(function($){
    $('#gn-view-copy-shortcode').on('click', function(){
        var text = $('#gn-view-shortcode').text();
        if (navigator.clipboard) {
            var $btn = $(this);
            navigator.clipboard.writeText(text).then(function(){
                $btn.find('.dashicons').removeClass('dashicons-clipboard').addClass('dashicons-yes');
                setTimeout(function(){ $btn.find('.dashicons').removeClass('dashicons-yes').addClass('dashicons-clipboard'); }, 1500);
            });
        }
    });
});
</script>
