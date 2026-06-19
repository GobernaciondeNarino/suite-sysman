<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$post_id = absint( $_GET['id'] ?? 0 );
$post    = $post_id ? get_post( $post_id ) : null;

if ( ! $post || 'gn_ejecucion' !== $post->post_type ) {
    echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Seguimiento no encontrado.', 'sysman-suite' ) . '</p></div></div>';
    return;
}

$dependencia = get_post_meta( $post_id, '_gn_dependencia', true );
$vigencia    = get_post_meta( $post_id, '_gn_vigencia', true );
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
                    <?php echo esc_html( $dependencia ); ?> &mdash; <?php echo esc_html( $mes_nombre . ' ' . $anio ); ?>
                    <?php if ( $vigencia ) : ?> &mdash; <?php echo esc_html( $vigencia ); ?><?php endif; ?>
                    &mdash; <?php esc_html_e( 'Compañía', 'sysman-suite' ); ?> <?php echo esc_html( $compania ); ?>
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

    <div class="sysman-card" style="margin-bottom:1rem;">
        <div class="sysman-card-body" style="padding:1rem;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:0.85rem;">
                <span class="dashicons dashicons-download" style="color:#1a5276;font-size:22px;width:22px;height:22px;"></span>
                <strong style="color:#1a5276;font-size:1rem;"><?php esc_html_e( 'Exportar Datos', 'sysman-suite' ); ?></strong>
            </div>

            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:1rem;">
                <a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=gn_ejecucion_export&id=' . $post_id . '&format=csv' ) ); ?>" class="button">
                    <span class="dashicons dashicons-media-spreadsheet" style="vertical-align:middle;margin-top:-2px;"></span>
                    <?php esc_html_e( 'Descargar CSV', 'sysman-suite' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=gn_ejecucion_export&id=' . $post_id . '&format=txt' ) ); ?>" class="button">
                    <span class="dashicons dashicons-media-text" style="vertical-align:middle;margin-top:-2px;"></span>
                    <?php esc_html_e( 'Descargar TXT', 'sysman-suite' ); ?>
                </a>
            </div>

            <div style="display:flex;align-items:center;gap:10px;margin-bottom:0.75rem;background:#f8fafc;padding:0.6rem 0.85rem;border:1px solid #e5e7eb;border-radius:5px;">
                <span class="dashicons dashicons-rest-api" style="color:#003087;font-size:16px;width:16px;height:16px;flex-shrink:0;"></span>
                <span style="font-weight:600;color:#003087;font-size:0.85rem;white-space:nowrap;">API JSON:</span>
                <code id="gn-export-api-url" style="flex:1;padding:4px 8px;background:#fff;border:1px solid #d1d5db;border-radius:3px;font-size:12px;word-break:break-all;user-select:all;"><?php echo esc_url( rest_url( 'gn-sisman/v1/ejecucion/' . $post_id . '/export' ) ); ?></code>
                <button type="button" id="gn-copy-api-url" class="button button-small" title="<?php esc_attr_e( 'Copiar URL', 'sysman-suite' ); ?>">
                    <span class="dashicons dashicons-clipboard" style="vertical-align:middle;margin-top:-2px;"></span>
                </button>
            </div>

            <div style="display:flex;align-items:center;gap:10px;background:#f0f6ff;padding:0.6rem 0.85rem;border:1px solid #c3dafe;border-radius:5px;">
                <span class="dashicons dashicons-shortcode" style="color:#1a5276;font-size:16px;width:16px;height:16px;flex-shrink:0;"></span>
                <span style="font-weight:600;color:#1a5276;font-size:0.85rem;white-space:nowrap;">Shortcode:</span>
                <code id="gn-export-shortcode" style="padding:4px 8px;background:#fff;border:1px solid #d1d5db;border-radius:3px;font-size:12px;user-select:all;">[gn_ejecucion_export id="<?php echo esc_attr( $post_id ); ?>"]</code>
                <button type="button" id="gn-copy-export-shortcode" class="button button-small" title="<?php esc_attr_e( 'Copiar shortcode', 'sysman-suite' ); ?>">
                    <span class="dashicons dashicons-clipboard" style="vertical-align:middle;margin-top:-2px;"></span>
                </button>
                <span class="description" style="font-size:0.8rem;"><?php esc_html_e( 'Inserte en cualquier pagina para mostrar los botones de descarga.', 'sysman-suite' ); ?></span>
            </div>
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
    function copyToClipboard($btn, text) {
        if (!navigator.clipboard) return;
        navigator.clipboard.writeText(text).then(function(){
            $btn.find('.dashicons').removeClass('dashicons-clipboard').addClass('dashicons-yes');
            setTimeout(function(){ $btn.find('.dashicons').removeClass('dashicons-yes').addClass('dashicons-clipboard'); }, 1500);
        });
    }
    $('#gn-view-copy-shortcode').on('click', function(){
        copyToClipboard($(this), $('#gn-view-shortcode').text());
    });
    $('#gn-copy-api-url').on('click', function(){
        copyToClipboard($(this), $('#gn-export-api-url').text());
    });
    $('#gn-copy-export-shortcode').on('click', function(){
        copyToClipboard($(this), $('#gn-export-shortcode').text());
    });
});
</script>
