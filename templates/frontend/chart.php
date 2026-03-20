<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$plugin = Sisman_Suite::instance();
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

<div class="sisman-chart-wrapper"
     id="sisman-chart-<?php echo esc_attr( $id ); ?>"
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
     aria-label="<?php echo esc_attr( sprintf( __( 'Gráfico: %s', 'sisman-suite' ), $title ) ); ?>">

    <?php if ( $show_toolbar ) : ?>
    <!-- Toolbar -->
    <div class="sisman-chart-toolbar" role="toolbar" aria-label="<?php esc_attr_e( 'Herramientas del gráfico', 'sisman-suite' ); ?>">
        <h3 class="sisman-chart-title"><?php echo esc_html( $title ); ?></h3>
        <div class="sisman-chart-actions">
            <?php if ( $meta['toolbar_detail'] ) : ?>
            <button type="button" class="sisman-btn sisman-btn-data" data-chart-id="<?php echo esc_attr( $id ); ?>" aria-label="<?php esc_attr_e( 'Ver datos', 'sisman-suite' ); ?>" title="<?php esc_attr_e( 'Ver datos', 'sisman-suite' ); ?>">
                <span aria-hidden="true" class="dashicons dashicons-editor-table"></span>
            </button>
            <?php endif; ?>

            <?php if ( $meta['toolbar_csv'] ) : ?>
            <button type="button" class="sisman-btn sisman-btn-download" data-chart-id="<?php echo esc_attr( $id ); ?>" aria-label="<?php esc_attr_e( 'Descargar CSV', 'sisman-suite' ); ?>" title="<?php esc_attr_e( 'Descargar CSV', 'sisman-suite' ); ?>">
                <span aria-hidden="true" class="dashicons dashicons-download"></span>
            </button>
            <?php endif; ?>

            <?php if ( $meta['toolbar_image'] ) : ?>
            <button type="button" class="sisman-btn sisman-btn-image" data-chart-id="<?php echo esc_attr( $id ); ?>" aria-label="<?php esc_attr_e( 'Descargar imagen', 'sisman-suite' ); ?>" title="<?php esc_attr_e( 'Descargar imagen', 'sisman-suite' ); ?>">
                <span aria-hidden="true" class="dashicons dashicons-format-image"></span>
            </button>
            <?php endif; ?>

            <?php if ( $meta['toolbar_share'] ) : ?>
            <button type="button" class="sisman-btn sisman-btn-share" data-chart-id="<?php echo esc_attr( $id ); ?>" aria-label="<?php esc_attr_e( 'Compartir', 'sisman-suite' ); ?>" title="<?php esc_attr_e( 'Compartir', 'sisman-suite' ); ?>">
                <span aria-hidden="true" class="dashicons dashicons-share"></span>
            </button>
            <?php endif; ?>

            <button type="button" class="sisman-btn sisman-btn-fullscreen" data-chart-id="<?php echo esc_attr( $id ); ?>" aria-label="<?php esc_attr_e( 'Pantalla completa', 'sisman-suite' ); ?>" title="<?php esc_attr_e( 'Pantalla completa', 'sisman-suite' ); ?>">
                <span aria-hidden="true" class="dashicons dashicons-fullscreen-alt"></span>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Chart Container -->
    <div class="sisman-chart-container" style="height: <?php echo esc_attr( $chart_height ); ?>px;">
        <div class="sisman-chart-loading" aria-live="polite">
            <div class="sisman-spinner"></div>
            <p><?php esc_html_e( 'Cargando gráfico...', 'sisman-suite' ); ?></p>
        </div>
        <div class="sisman-chart-canvas"></div>
    </div>

    <!-- Data Table Modal -->
    <div class="sisman-chart-modal sisman-data-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="sisman-data-title-<?php echo esc_attr( $id ); ?>">
        <div class="sisman-modal-overlay"></div>
        <div class="sisman-modal-content">
            <div class="sisman-modal-header">
                <h3 id="sisman-data-title-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Datos del Gráfico', 'sisman-suite' ); ?></h3>
                <button type="button" class="sisman-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'sisman-suite' ); ?>">&times;</button>
            </div>
            <div class="sisman-modal-body">
                <table class="sisman-data-table" role="grid">
                    <thead><tr><th><?php esc_html_e( 'Etiqueta', 'sisman-suite' ); ?></th><th><?php esc_html_e( 'Valor', 'sisman-suite' ); ?></th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Share Modal -->
    <div class="sisman-chart-modal sisman-share-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="sisman-share-title-<?php echo esc_attr( $id ); ?>">
        <div class="sisman-modal-overlay"></div>
        <div class="sisman-modal-content">
            <div class="sisman-modal-header">
                <h3 id="sisman-share-title-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Compartir Gráfico', 'sisman-suite' ); ?></h3>
                <button type="button" class="sisman-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'sisman-suite' ); ?>">&times;</button>
            </div>
            <div class="sisman-modal-body">
                <div class="sisman-share-buttons">
                    <a href="#" class="sisman-share-btn sisman-share-facebook" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'Compartir en Facebook', 'sisman-suite' ); ?>">
                        Facebook
                    </a>
                    <a href="#" class="sisman-share-btn sisman-share-twitter" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'Compartir en X/Twitter', 'sisman-suite' ); ?>">
                        X / Twitter
                    </a>
                    <a href="#" class="sisman-share-btn sisman-share-linkedin" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'Compartir en LinkedIn', 'sisman-suite' ); ?>">
                        LinkedIn
                    </a>
                    <a href="#" class="sisman-share-btn sisman-share-whatsapp" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'Compartir en WhatsApp', 'sisman-suite' ); ?>">
                        WhatsApp
                    </a>
                </div>
                <div class="sisman-share-link">
                    <label for="sisman-share-url-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Enlace directo:', 'sisman-suite' ); ?></label>
                    <input type="text" id="sisman-share-url-<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( get_permalink() ); ?>" readonly>
                    <button type="button" class="button sisman-copy-link" data-target="sisman-share-url-<?php echo esc_attr( $id ); ?>">
                        <?php esc_html_e( 'Copiar', 'sisman-suite' ); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Source attribution -->
    <div class="sisman-chart-footer">
        <p class="sisman-chart-source">
            <?php esc_html_e( 'Fuente: Sistema SISMAN - Gobernación de Nariño', 'sisman-suite' ); ?>
        </p>
    </div>
</div>
