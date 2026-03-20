<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$plugin = Sisman_Suite::instance();
$tables = $plugin->database->get_available_tables();

$meses = [
    0  => 'Todos',
    1  => 'Enero',
    2  => 'Febrero',
    3  => 'Marzo',
    4  => 'Abril',
    5  => 'Mayo',
    6  => 'Junio',
    7  => 'Julio',
    8  => 'Agosto',
    9  => 'Septiembre',
    10 => 'Octubre',
    11 => 'Noviembre',
    12 => 'Diciembre',
];
?>
<div class="wrap sisman-admin-wrap">
    <h1 class="sisman-title">
        <span aria-hidden="true" class="dashicons dashicons-list-view"></span>
        <?php esc_html_e( 'SISMAN Suite - Registros', 'sisman-suite' ); ?>
    </h1>

    <!-- Table Selector and Filters -->
    <div class="sisman-panel" role="region" aria-label="<?php esc_attr_e( 'Filtros de registros', 'sisman-suite' ); ?>">
        <div class="sisman-filters-row">
            <div class="sisman-form-group">
                <label for="sisman-table-select"><?php esc_html_e( 'Tabla', 'sisman-suite' ); ?></label>
                <select id="sisman-table-select">
                    <?php foreach ( $tables as $table_name => $label ) : ?>
                    <option value="<?php echo esc_attr( str_replace( $GLOBALS['wpdb']->prefix, '', $table_name ) ); ?>">
                        <?php echo esc_html( $label ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sisman-form-group">
                <label for="sisman-filter-anio"><?php esc_html_e( 'Año', 'sisman-suite' ); ?></label>
                <select id="sisman-filter-anio">
                    <option value="0"><?php esc_html_e( 'Todos', 'sisman-suite' ); ?></option>
                </select>
            </div>

            <div class="sisman-form-group">
                <label for="sisman-filter-mes"><?php esc_html_e( 'Mes', 'sisman-suite' ); ?></label>
                <select id="sisman-filter-mes">
                    <?php foreach ( $meses as $num => $nombre ) : ?>
                    <option value="<?php echo esc_attr( $num ); ?>"><?php echo esc_html( $nombre ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sisman-form-group">
                <label for="sisman-filter-search"><?php esc_html_e( 'Buscar', 'sisman-suite' ); ?></label>
                <input type="search" id="sisman-filter-search" placeholder="<?php esc_attr_e( 'Buscar...', 'sisman-suite' ); ?>" class="regular-text">
            </div>

            <div class="sisman-form-group sisman-form-group--actions">
                <button type="button" id="sisman-filter-btn" class="button button-primary">
                    <span aria-hidden="true" class="dashicons dashicons-search"></span>
                    <?php esc_html_e( 'Filtrar', 'sisman-suite' ); ?>
                </button>
                <button type="button" id="sisman-export-csv-btn" class="button">
                    <span aria-hidden="true" class="dashicons dashicons-media-spreadsheet"></span>
                    <?php esc_html_e( 'Exportar CSV', 'sisman-suite' ); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Records Table -->
    <div class="sisman-panel" role="region" aria-label="<?php esc_attr_e( 'Tabla de registros', 'sisman-suite' ); ?>">
        <div id="sisman-records-loading" class="sisman-loading" style="display:none;" aria-live="polite">
            <span class="spinner is-active"></span>
            <span><?php esc_html_e( 'Cargando registros...', 'sisman-suite' ); ?></span>
        </div>

        <div id="sisman-records-container">
            <table class="widefat striped sisman-records-table" role="grid">
                <thead id="sisman-records-thead">
                </thead>
                <tbody id="sisman-records-tbody">
                    <tr>
                        <td colspan="20" class="sisman-empty-message">
                            <?php esc_html_e( 'Seleccione una tabla para ver los registros.', 'sisman-suite' ); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div id="sisman-pagination" class="sisman-pagination" style="display:none;" role="navigation" aria-label="<?php esc_attr_e( 'Paginación de registros', 'sisman-suite' ); ?>">
            <div class="sisman-pagination-info">
                <span id="sisman-pagination-text"></span>
            </div>
            <div class="sisman-pagination-controls">
                <button type="button" id="sisman-prev-page" class="button" disabled aria-label="<?php esc_attr_e( 'Página anterior', 'sisman-suite' ); ?>">
                    &laquo; <?php esc_html_e( 'Anterior', 'sisman-suite' ); ?>
                </button>
                <span id="sisman-page-info" class="sisman-page-info"></span>
                <button type="button" id="sisman-next-page" class="button" disabled aria-label="<?php esc_attr_e( 'Página siguiente', 'sisman-suite' ); ?>">
                    <?php esc_html_e( 'Siguiente', 'sisman-suite' ); ?> &raquo;
                </button>
            </div>
        </div>
    </div>

    <!-- Record Detail Modal -->
    <div id="sisman-record-modal" class="sisman-modal" role="dialog" aria-modal="true" aria-labelledby="sisman-modal-title" style="display:none;">
        <div class="sisman-modal-overlay"></div>
        <div class="sisman-modal-content">
            <div class="sisman-modal-header">
                <h2 id="sisman-modal-title"><?php esc_html_e( 'Detalle del Registro', 'sisman-suite' ); ?></h2>
                <button type="button" class="sisman-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'sisman-suite' ); ?>">&times;</button>
            </div>
            <div class="sisman-modal-body" id="sisman-modal-body">
            </div>
        </div>
    </div>
</div>
