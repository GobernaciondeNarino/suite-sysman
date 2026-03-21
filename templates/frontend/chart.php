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
$unique_id     = 'sysman-chart-' . $id;
?>

<div class="sysman-chart-container" id="<?php echo esc_attr( $unique_id ); ?>">

    <?php if ( $show_toolbar ) : ?>
    <!-- Toolbar -->
    <div class="sysman-toolbar" role="toolbar" aria-label="<?php esc_attr_e( 'Herramientas de la gráfica', 'sysman-suite' ); ?>">

        <?php if ( $meta['toolbar_detail'] ) : ?>
        <button type="button" class="sysman-toolbar-btn" data-action="detail">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="16" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
            <span><?php esc_html_e( 'Detalle', 'sysman-suite' ); ?></span>
        </button>
        <?php endif; ?>

        <?php if ( $meta['toolbar_share'] ) : ?>
        <button type="button" class="sysman-toolbar-btn" data-action="share">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="18" cy="5" r="3"></circle>
                <circle cx="6" cy="12" r="3"></circle>
                <circle cx="18" cy="19" r="3"></circle>
                <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
            </svg>
            <span><?php esc_html_e( 'Compartir', 'sysman-suite' ); ?></span>
        </button>
        <?php endif; ?>

        <?php if ( $meta['toolbar_data'] ) : ?>
        <button type="button" class="sysman-toolbar-btn" data-action="data">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="3" y1="9" x2="21" y2="9"></line>
                <line x1="3" y1="15" x2="21" y2="15"></line>
                <line x1="9" y1="3" x2="9" y2="21"></line>
                <line x1="15" y1="3" x2="15" y2="21"></line>
            </svg>
            <span><?php esc_html_e( 'Datos', 'sysman-suite' ); ?></span>
        </button>
        <?php endif; ?>

        <?php if ( $meta['toolbar_image'] ) : ?>
        <button type="button" class="sysman-toolbar-btn" data-action="image">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                <polyline points="21 15 16 10 5 21"></polyline>
            </svg>
            <span><?php esc_html_e( 'Imagen', 'sysman-suite' ); ?></span>
        </button>
        <?php endif; ?>

        <?php if ( $meta['toolbar_csv'] ) : ?>
        <button type="button" class="sysman-toolbar-btn" data-action="download">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10 12 15 17 10"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            <span><?php esc_html_e( 'Descarga', 'sysman-suite' ); ?></span>
        </button>
        <?php endif; ?>

    </div>
    <?php endif; ?>

    <!-- Chart Render Area -->
    <div class="sysman-chart-wrapper" style="height: <?php echo esc_attr( $chart_height ); ?>px;">
        <div class="sysman-loading">
            <div class="sysman-spinner"></div>
            <p><?php esc_html_e( 'Cargando datos...', 'sysman-suite' ); ?></p>
        </div>
        <div class="sysman-chart-render"></div>
        <div class="sysman-error-message" style="display: none;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="48" height="48">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <p><?php esc_html_e( 'Error al cargar los datos', 'sysman-suite' ); ?></p>
        </div>
    </div>

    <!-- Data Table Modal -->
    <div class="sysman-modal sysman-data-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="sysman-data-title-<?php echo esc_attr( $id ); ?>">
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
                <div style="text-align:right;margin-top:10px;">
                    <button type="button" class="sysman-toolbar-btn sysman-btn-csv-export" style="border:1px solid #dee2e6;">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                        <span><?php esc_html_e( 'Exportar CSV', 'sysman-suite' ); ?></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Share Modal -->
    <div class="sysman-modal sysman-share-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="sysman-share-title-<?php echo esc_attr( $id ); ?>">
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
                    <button type="button" class="sysman-copy-link" data-target="sysman-share-url-<?php echo esc_attr( $id ); ?>">
                        <?php esc_html_e( 'Copiar', 'sysman-suite' ); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Modal -->
    <div class="sysman-modal sysman-detail-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="sysman-detail-title-<?php echo esc_attr( $id ); ?>">
        <div class="sysman-modal-overlay"></div>
        <div class="sysman-modal-content">
            <div class="sysman-modal-header">
                <h3 id="sysman-detail-title-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Detalle del Gráfico', 'sysman-suite' ); ?></h3>
                <button type="button" class="sysman-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'sysman-suite' ); ?>">&times;</button>
            </div>
            <div class="sysman-modal-body">
                <table class="sysman-detail-table">
                    <tr><th><?php esc_html_e( 'Título', 'sysman-suite' ); ?></th><td><?php echo esc_html( $title ); ?></td></tr>
                    <tr><th><?php esc_html_e( 'Tipo', 'sysman-suite' ); ?></th><td><?php echo esc_html( $chart_type ); ?></td></tr>
                    <?php if ( $y_axis_title ) : ?>
                    <tr><th><?php esc_html_e( 'Eje Y', 'sysman-suite' ); ?></th><td><?php echo esc_html( $y_axis_title ); ?></td></tr>
                    <?php endif; ?>
                    <?php if ( $x_axis_title ) : ?>
                    <tr><th><?php esc_html_e( 'Eje X', 'sysman-suite' ); ?></th><td><?php echo esc_html( $x_axis_title ); ?></td></tr>
                    <?php endif; ?>
                    <tr><th><?php esc_html_e( 'Fuente', 'sysman-suite' ); ?></th><td><?php esc_html_e( 'Sistema SYSMAN - Gobernación de Nariño', 'sysman-suite' ); ?></td></tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Chart Config (JSON for JS) -->
    <script type="application/json" id="<?php echo esc_attr( $unique_id ); ?>-config"><?php
        echo wp_json_encode( [
            'chartId'       => $id,
            'type'          => $chart_type,
            'colors'        => $chart_colors,
            'height'        => $chart_height,
            'showLegend'    => $show_legend,
            'showLabels'    => $show_labels,
            'showTimeline'  => $show_timeline,
            'numberFormat'  => $number_format,
            'yAxisTitle'    => $y_axis_title,
            'xAxisTitle'    => $x_axis_title,
            'hasGroups'     => $meta['has_groups'] ?? false,
            'title'         => $title,
            'restUrl'       => rest_url( 'sysman-suite/v1/' ),
            'nonce'         => wp_create_nonce( 'wp_rest' ),
        ] );
    ?></script>

</div>
