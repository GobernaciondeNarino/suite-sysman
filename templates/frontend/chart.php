<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$plugin = Sysman_Suite::instance();
$meta   = $plugin->visualizer->get_chart_meta( $id );

$chart_type    = $meta['chart_type'];
$chart_height  = $meta['chart_height'];
$chart_colors  = $meta['chart_colors'];
$show_legend   = $meta['show_legend'];
$show_labels   = $meta['show_labels'];
$number_format = $meta['number_format'];
$y_axis_title  = $meta['y_axis_title'];
$x_axis_title  = $meta['x_axis_title'];
$show_timeline = $meta['show_timeline'];
$show_toolbar  = $meta['show_toolbar'];
$title         = $meta['title'];
?>

<div class="sysman-chart-wrapper"
     id="sysman-chart-<?php echo esc_attr( $id ); ?>"
     data-chart-id="<?php echo esc_attr( $id ); ?>"
     data-chart-type="<?php echo esc_attr( $chart_type ); ?>"
     data-chart-height="<?php echo esc_attr( $chart_height ); ?>"
     data-chart-colors="<?php echo esc_attr( $chart_colors ); ?>"
     data-show-legend="<?php echo esc_attr( $show_legend ? 'true' : 'false' ); ?>"
     data-show-labels="<?php echo esc_attr( $show_labels ? 'true' : 'false' ); ?>"
     data-number-format="<?php echo esc_attr( $number_format ); ?>"
     data-y-axis-title="<?php echo esc_attr( $y_axis_title ); ?>"
     data-x-axis-title="<?php echo esc_attr( $x_axis_title ); ?>"
     data-show-timeline="<?php echo esc_attr( $show_timeline ? 'true' : 'false' ); ?>"
     role="figure"
     aria-label="<?php echo esc_attr( sprintf( __( 'Gráfico: %s', 'sysman-suite' ), $title ) ); ?>">

    <?php if ( $show_toolbar ) : ?>
    <!-- Toolbar -->
    <div class="sysman-chart-toolbar" role="toolbar" aria-label="<?php esc_attr_e( 'Herramientas del gráfico', 'sysman-suite' ); ?>">
        <h3 class="sysman-chart-title"><?php echo esc_html( $title ); ?></h3>
        <div class="sysman-chart-actions">
            <?php if ( $meta['toolbar_detail'] ) : ?>
            <button type="button" class="sysman-btn sysman-btn-data" data-chart-id="<?php echo esc_attr( $id ); ?>" aria-label="<?php esc_attr_e( 'Ver datos', 'sysman-suite' ); ?>" title="<?php esc_attr_e( 'Ver datos', 'sysman-suite' ); ?>">
                <span aria-hidden="true" class="dashicons dashicons-editor-table"></span>
            </button>
            <?php endif; ?>

            <?php if ( $meta['toolbar_csv'] ) : ?>
            <button type="button" class="sysman-btn sysman-btn-download" data-chart-id="<?php echo esc_attr( $id ); ?>" aria-label="<?php esc_attr_e( 'Descargar CSV', 'sysman-suite' ); ?>" title="<?php esc_attr_e( 'Descargar CSV', 'sysman-suite' ); ?>">
                <span aria-hidden="true" class="dashicons dashicons-download"></span>
            </button>
            <?php endif; ?>

            <?php if ( $meta['toolbar_image'] ) : ?>
            <button type="button" class="sysman-btn sysman-btn-image" data-chart-id="<?php echo esc_attr( $id ); ?>" aria-label="<?php esc_attr_e( 'Descargar imagen', 'sysman-suite' ); ?>" title="<?php esc_attr_e( 'Descargar imagen', 'sysman-suite' ); ?>">
                <span aria-hidden="true" class="dashicons dashicons-format-image"></span>
            </button>
            <?php endif; ?>

            <?php if ( $meta['toolbar_share'] ) : ?>
            <button type="button" class="sysman-btn sysman-btn-share" data-chart-id="<?php echo esc_attr( $id ); ?>" aria-label="<?php esc_attr_e( 'Compartir', 'sysman-suite' ); ?>" title="<?php esc_attr_e( 'Compartir', 'sysman-suite' ); ?>">
                <span aria-hidden="true" class="dashicons dashicons-share"></span>
            </button>
            <?php endif; ?>

            <button type="button" class="sysman-btn sysman-btn-fullscreen" data-chart-id="<?php echo esc_attr( $id ); ?>" aria-label="<?php esc_attr_e( 'Pantalla completa', 'sysman-suite' ); ?>" title="<?php esc_attr_e( 'Pantalla completa', 'sysman-suite' ); ?>">
                <span aria-hidden="true" class="dashicons dashicons-fullscreen-alt"></span>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Chart Container -->
    <div class="sysman-chart-container" style="height: <?php echo esc_attr( $chart_height ); ?>px;">
        <div class="sysman-chart-loading" aria-live="polite">
            <div class="sysman-spinner"></div>
            <p><?php esc_html_e( 'Cargando gráfico...', 'sysman-suite' ); ?></p>
        </div>
        <div class="sysman-chart-canvas"></div>
    </div>

    <!-- Data Table Modal -->
    <div class="sysman-chart-modal sysman-data-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="sysman-data-title-<?php echo esc_attr( $id ); ?>">
        <div class="sysman-modal-overlay"></div>
        <div class="sysman-modal-content">
            <div class="sysman-modal-header">
                <h3 id="sysman-data-title-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Datos del Gráfico', 'sysman-suite' ); ?></h3>
                <button type="button" class="sysman-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'sysman-suite' ); ?>">&times;</button>
            </div>
            <div class="sysman-modal-body">
                <table class="sysman-data-table" role="grid">
                    <thead><tr><th><?php esc_html_e( 'Etiqueta', 'sysman-suite' ); ?></th><th><?php esc_html_e( 'Valor', 'sysman-suite' ); ?></th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Share Modal -->
    <div class="sysman-chart-modal sysman-share-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="sysman-share-title-<?php echo esc_attr( $id ); ?>">
        <div class="sysman-modal-overlay"></div>
        <div class="sysman-modal-content">
            <div class="sysman-modal-header">
                <h3 id="sysman-share-title-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Compartir Gráfico', 'sysman-suite' ); ?></h3>
                <button type="button" class="sysman-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'sysman-suite' ); ?>">&times;</button>
            </div>
            <div class="sysman-modal-body">
                <div class="sysman-share-buttons">
                    <a href="#" class="sysman-share-btn sysman-share-facebook" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'Compartir en Facebook', 'sysman-suite' ); ?>">
                        Facebook
                    </a>
                    <a href="#" class="sysman-share-btn sysman-share-twitter" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'Compartir en X/Twitter', 'sysman-suite' ); ?>">
                        X / Twitter
                    </a>
                    <a href="#" class="sysman-share-btn sysman-share-linkedin" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'Compartir en LinkedIn', 'sysman-suite' ); ?>">
                        LinkedIn
                    </a>
                    <a href="#" class="sysman-share-btn sysman-share-whatsapp" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'Compartir en WhatsApp', 'sysman-suite' ); ?>">
                        WhatsApp
                    </a>
                </div>
                <div class="sysman-share-link">
                    <label for="sysman-share-url-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Enlace directo:', 'sysman-suite' ); ?></label>
                    <input type="text" id="sysman-share-url-<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( get_permalink() ); ?>" readonly>
                    <button type="button" class="button sysman-copy-link" data-target="sysman-share-url-<?php echo esc_attr( $id ); ?>">
                        <?php esc_html_e( 'Copiar', 'sysman-suite' ); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Source attribution -->
    <div class="sysman-chart-footer">
        <p class="sysman-chart-source">
            <?php esc_html_e( 'Fuente: Sistema SYSMAN - Gobernación de Nariño', 'sysman-suite' ); ?>
        </p>
    </div>
</div>
