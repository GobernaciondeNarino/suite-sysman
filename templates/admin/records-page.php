<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$plugin = Sysman_Suite::instance();
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
<div class="wrap sysman-admin-wrap">
    <h1 class="sysman-title">
        <span aria-hidden="true" class="dashicons dashicons-list-view"></span>
        <?php esc_html_e( 'SYSMAN Suite - Registros', 'sysman-suite' ); ?>
    </h1>

    <!-- Table Selector and Filters -->
    <div class="sysman-panel" role="region" aria-label="<?php esc_attr_e( 'Filtros de registros', 'sysman-suite' ); ?>">
        <div class="sysman-filters-row">
            <div class="sysman-form-group">
                <label for="sysman-table-select"><?php esc_html_e( 'Tabla', 'sysman-suite' ); ?></label>
                <select id="sysman-table-select">
                    <?php foreach ( $tables as $table_name => $label ) : ?>
                    <option value="<?php echo esc_attr( str_replace( $GLOBALS['wpdb']->prefix, '', $table_name ) ); ?>">
                        <?php echo esc_html( $label ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sysman-form-group">
                <label for="sysman-filter-anio"><?php esc_html_e( 'Año', 'sysman-suite' ); ?></label>
                <select id="sysman-filter-anio">
                    <option value="0"><?php esc_html_e( 'Todos', 'sysman-suite' ); ?></option>
                </select>
            </div>

            <div class="sysman-form-group">
                <label for="sysman-filter-mes"><?php esc_html_e( 'Mes', 'sysman-suite' ); ?></label>
                <select id="sysman-filter-mes">
                    <?php foreach ( $meses as $num => $nombre ) : ?>
                    <option value="<?php echo esc_attr( $num ); ?>"><?php echo esc_html( $nombre ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sysman-form-group">
                <label for="sysman-filter-search"><?php esc_html_e( 'Buscar', 'sysman-suite' ); ?></label>
                <input type="search" id="sysman-filter-search" placeholder="<?php esc_attr_e( 'Buscar...', 'sysman-suite' ); ?>" class="regular-text">
            </div>

            <div class="sysman-form-group sysman-form-group--actions">
                <button type="button" id="sysman-filter-btn" class="button button-primary">
                    <span aria-hidden="true" class="dashicons dashicons-search"></span>
                    <?php esc_html_e( 'Filtrar', 'sysman-suite' ); ?>
                </button>
                <button type="button" id="sysman-export-csv-btn" class="button">
                    <span aria-hidden="true" class="dashicons dashicons-media-spreadsheet"></span>
                    <?php esc_html_e( 'Exportar CSV', 'sysman-suite' ); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Records Table -->
    <div class="sysman-panel" role="region" aria-label="<?php esc_attr_e( 'Tabla de registros', 'sysman-suite' ); ?>">
        <div id="sysman-records-loading" class="sysman-loading" style="display:none;" aria-live="polite">
            <span class="spinner is-active"></span>
            <span><?php esc_html_e( 'Cargando registros...', 'sysman-suite' ); ?></span>
        </div>

        <div id="sysman-records-container">
            <table class="widefat striped sysman-records-table" role="grid">
                <thead id="sysman-records-thead">
                </thead>
                <tbody id="sysman-records-tbody">
                    <tr>
                        <td colspan="20" class="sysman-empty-message">
                            <?php esc_html_e( 'Seleccione una tabla para ver los registros.', 'sysman-suite' ); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div id="sysman-pagination" class="sysman-pagination" style="display:none;" role="navigation" aria-label="<?php esc_attr_e( 'Paginación de registros', 'sysman-suite' ); ?>">
            <div class="sysman-pagination-info">
                <span id="sysman-pagination-text"></span>
            </div>
            <div class="sysman-pagination-controls">
                <button type="button" id="sysman-prev-page" class="button" disabled aria-label="<?php esc_attr_e( 'Página anterior', 'sysman-suite' ); ?>">
                    &laquo; <?php esc_html_e( 'Anterior', 'sysman-suite' ); ?>
                </button>
                <span id="sysman-page-info" class="sysman-page-info"></span>
                <button type="button" id="sysman-next-page" class="button" disabled aria-label="<?php esc_attr_e( 'Página siguiente', 'sysman-suite' ); ?>">
                    <?php esc_html_e( 'Siguiente', 'sysman-suite' ); ?> &raquo;
                </button>
            </div>
        </div>
    </div>

    <!-- Record Detail Modal -->
    <div id="sysman-record-modal" class="sysman-modal" role="dialog" aria-modal="true" aria-labelledby="sysman-modal-title" style="display:none;">
        <div class="sysman-modal-overlay"></div>
        <div class="sysman-modal-content">
            <div class="sysman-modal-header">
                <h2 id="sysman-modal-title"><?php esc_html_e( 'Detalle del Registro', 'sysman-suite' ); ?></h2>
                <button type="button" class="sysman-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'sysman-suite' ); ?>">&times;</button>
            </div>
            <div class="sysman-modal-body" id="sysman-modal-body">
            </div>
        </div>
    </div>
</div>
