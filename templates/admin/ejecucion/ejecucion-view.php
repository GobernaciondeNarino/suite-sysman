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

    <div class="sysman-card" style="overflow:visible;">
        <div class="sysman-card-body" style="padding:0;">
            <?php echo \SysmanSuite\Ejecucion\AccordionRenderer::render( $post_id ); ?>
        </div>
    </div>
</div>
