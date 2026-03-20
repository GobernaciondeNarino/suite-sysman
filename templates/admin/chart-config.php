<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$plugin = Sisman_Suite::instance();
$tables = $plugin->database->get_available_tables();

$chart_type   = get_post_meta( $post->ID, '_sisman_chart_type', true ) ?: 'bar';
$data_table   = get_post_meta( $post->ID, '_sisman_data_table', true ) ?: '';
$group_column = get_post_meta( $post->ID, '_sisman_group_column', true ) ?: '';
$value_column = get_post_meta( $post->ID, '_sisman_value_column', true ) ?: '';
$aggregate    = get_post_meta( $post->ID, '_sisman_aggregate', true ) ?: 'SUM';
$filter_anio  = get_post_meta( $post->ID, '_sisman_filter_anio', true ) ?: 0;
$filter_mes   = get_post_meta( $post->ID, '_sisman_filter_mes', true ) ?: 0;
$filter_destino = get_post_meta( $post->ID, '_sisman_filter_destino', true ) ?: '';
$chart_height = get_post_meta( $post->ID, '_sisman_chart_height', true ) ?: 400;
$show_legend  = get_post_meta( $post->ID, '_sisman_show_legend', true ) ?: 'yes';
$show_labels  = get_post_meta( $post->ID, '_sisman_show_labels', true ) ?: 'yes';
$number_format = get_post_meta( $post->ID, '_sisman_number_format', true ) ?: 'colombian';
$filters      = get_post_meta( $post->ID, '_sisman_filters', true ) ?: [];

wp_nonce_field( 'sisman_chart_save', 'sisman_chart_nonce' );
?>

<div class="sisman-chart-config" role="form" aria-label="<?php esc_attr_e( 'Configuración del gráfico', 'sisman-suite' ); ?>">

    <!-- Chart Type -->
    <div class="sisman-config-section">
        <h3><?php esc_html_e( 'Tipo de Gráfico', 'sisman-suite' ); ?></h3>
        <div class="sisman-chart-types" role="radiogroup" aria-label="<?php esc_attr_e( 'Seleccione el tipo de gráfico', 'sisman-suite' ); ?>">
            <?php
            $chart_types = [
                'bar'         => [ 'label' => __( 'Barras', 'sisman-suite' ), 'icon' => 'chart-bar' ],
                'line'        => [ 'label' => __( 'Líneas', 'sisman-suite' ), 'icon' => 'chart-line' ],
                'pie'         => [ 'label' => __( 'Circular', 'sisman-suite' ), 'icon' => 'chart-pie' ],
                'donut'       => [ 'label' => __( 'Dona', 'sisman-suite' ), 'icon' => 'chart-pie' ],
                'area'        => [ 'label' => __( 'Área', 'sisman-suite' ), 'icon' => 'chart-area' ],
                'treemap'     => [ 'label' => __( 'Treemap', 'sisman-suite' ), 'icon' => 'screenoptions' ],
                'stacked_bar' => [ 'label' => __( 'Barras Apiladas', 'sisman-suite' ), 'icon' => 'chart-bar' ],
                'grouped_bar' => [ 'label' => __( 'Barras Agrupadas', 'sisman-suite' ), 'icon' => 'chart-bar' ],
            ];
            foreach ( $chart_types as $type => $info ) :
            ?>
            <label class="sisman-chart-type-option <?php echo $chart_type === $type ? 'active' : ''; ?>">
                <input type="radio" name="sisman_chart_type" value="<?php echo esc_attr( $type ); ?>" <?php checked( $chart_type, $type ); ?>>
                <span aria-hidden="true" class="dashicons dashicons-<?php echo esc_attr( $info['icon'] ); ?>"></span>
                <span class="sisman-chart-type-label"><?php echo esc_html( $info['label'] ); ?></span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Data Source -->
    <div class="sisman-config-section">
        <h3><?php esc_html_e( 'Fuente de Datos', 'sisman-suite' ); ?></h3>

        <div class="sisman-form-row">
            <div class="sisman-form-group">
                <label for="sisman_data_table"><?php esc_html_e( 'Tabla', 'sisman-suite' ); ?></label>
                <select id="sisman_data_table" name="sisman_data_table">
                    <option value=""><?php esc_html_e( '-- Seleccionar tabla --', 'sisman-suite' ); ?></option>
                    <?php foreach ( $tables as $table_name => $label ) : ?>
                    <option value="<?php echo esc_attr( $table_name ); ?>" <?php selected( $data_table, $table_name ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sisman-form-group">
                <label for="sisman_group_column"><?php esc_html_e( 'Columna de Agrupación (Eje X / Etiquetas)', 'sisman-suite' ); ?></label>
                <select id="sisman_group_column" name="sisman_group_column">
                    <option value=""><?php esc_html_e( '-- Seleccionar columna --', 'sisman-suite' ); ?></option>
                </select>
            </div>

            <div class="sisman-form-group">
                <label for="sisman_value_column"><?php esc_html_e( 'Columna de Valor (Eje Y)', 'sisman-suite' ); ?></label>
                <select id="sisman_value_column" name="sisman_value_column">
                    <option value=""><?php esc_html_e( '-- Seleccionar columna --', 'sisman-suite' ); ?></option>
                </select>
            </div>

            <div class="sisman-form-group">
                <label for="sisman_aggregate"><?php esc_html_e( 'Función de Agregación', 'sisman-suite' ); ?></label>
                <select id="sisman_aggregate" name="sisman_aggregate">
                    <option value="SUM" <?php selected( $aggregate, 'SUM' ); ?>>SUM</option>
                    <option value="COUNT" <?php selected( $aggregate, 'COUNT' ); ?>>COUNT</option>
                    <option value="AVG" <?php selected( $aggregate, 'AVG' ); ?>>AVG</option>
                    <option value="MAX" <?php selected( $aggregate, 'MAX' ); ?>>MAX</option>
                    <option value="MIN" <?php selected( $aggregate, 'MIN' ); ?>>MIN</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="sisman-config-section">
        <h3><?php esc_html_e( 'Filtros', 'sisman-suite' ); ?></h3>

        <div class="sisman-form-row">
            <div class="sisman-form-group">
                <label for="sisman_filter_anio"><?php esc_html_e( 'Año', 'sisman-suite' ); ?></label>
                <input type="number" id="sisman_filter_anio" name="sisman_filter_anio" value="<?php echo esc_attr( $filter_anio ); ?>" min="0" max="2100" class="small-text">
                <p class="description"><?php esc_html_e( 'Dejar en 0 para todos los años', 'sisman-suite' ); ?></p>
            </div>

            <div class="sisman-form-group">
                <label for="sisman_filter_mes"><?php esc_html_e( 'Mes', 'sisman-suite' ); ?></label>
                <input type="number" id="sisman_filter_mes" name="sisman_filter_mes" value="<?php echo esc_attr( $filter_mes ); ?>" min="0" max="12" class="small-text">
                <p class="description"><?php esc_html_e( 'Dejar en 0 para todos los meses', 'sisman-suite' ); ?></p>
            </div>

            <div class="sisman-form-group">
                <label for="sisman_filter_destino"><?php esc_html_e( 'Destino', 'sisman-suite' ); ?></label>
                <select id="sisman_filter_destino" name="sisman_filter_destino">
                    <option value="" <?php selected( $filter_destino, '' ); ?>><?php esc_html_e( 'Todos', 'sisman-suite' ); ?></option>
                    <option value="FUNCIONAMIENTO" <?php selected( $filter_destino, 'FUNCIONAMIENTO' ); ?>>FUNCIONAMIENTO</option>
                    <option value="INVERSION" <?php selected( $filter_destino, 'INVERSION' ); ?>>INVERSION</option>
                </select>
            </div>
        </div>

        <!-- Custom Filters -->
        <div id="sisman-custom-filters">
            <h4><?php esc_html_e( 'Filtros Adicionales', 'sisman-suite' ); ?></h4>
            <div id="sisman-filters-list">
                <?php if ( ! empty( $filters ) ) : ?>
                    <?php foreach ( $filters as $i => $filter ) : ?>
                    <div class="sisman-filter-row" data-index="<?php echo esc_attr( $i ); ?>">
                        <select name="sisman_filters[<?php echo esc_attr( $i ); ?>][column]" class="sisman-filter-column">
                            <option value=""><?php esc_html_e( 'Columna', 'sisman-suite' ); ?></option>
                        </select>
                        <select name="sisman_filters[<?php echo esc_attr( $i ); ?>][operator]">
                            <option value="=" <?php selected( $filter['operator'], '=' ); ?>>=</option>
                            <option value="!=" <?php selected( $filter['operator'], '!=' ); ?>>!=</option>
                            <option value=">" <?php selected( $filter['operator'], '>' ); ?>>&gt;</option>
                            <option value="<" <?php selected( $filter['operator'], '<' ); ?>>&lt;</option>
                            <option value=">=" <?php selected( $filter['operator'], '>=' ); ?>>&gt;=</option>
                            <option value="<=" <?php selected( $filter['operator'], '<=' ); ?>>&lt;=</option>
                            <option value="LIKE" <?php selected( $filter['operator'], 'LIKE' ); ?>>LIKE</option>
                        </select>
                        <input type="text" name="sisman_filters[<?php echo esc_attr( $i ); ?>][value]" value="<?php echo esc_attr( $filter['value'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Valor', 'sisman-suite' ); ?>">
                        <button type="button" class="button sisman-remove-filter" aria-label="<?php esc_attr_e( 'Eliminar filtro', 'sisman-suite' ); ?>">&times;</button>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button type="button" id="sisman-add-filter" class="button">
                <span aria-hidden="true" class="dashicons dashicons-plus-alt2"></span>
                <?php esc_html_e( 'Agregar Filtro', 'sisman-suite' ); ?>
            </button>
        </div>
    </div>

    <!-- Appearance -->
    <div class="sisman-config-section">
        <h3><?php esc_html_e( 'Apariencia', 'sisman-suite' ); ?></h3>

        <div class="sisman-form-row">
            <div class="sisman-form-group">
                <label for="sisman_chart_height"><?php esc_html_e( 'Altura (px)', 'sisman-suite' ); ?></label>
                <input type="number" id="sisman_chart_height" name="sisman_chart_height" value="<?php echo esc_attr( $chart_height ); ?>" min="200" max="1000" class="small-text">
            </div>

            <div class="sisman-form-group">
                <label>
                    <input type="checkbox" name="sisman_show_legend" value="yes" <?php checked( $show_legend, 'yes' ); ?>>
                    <?php esc_html_e( 'Mostrar leyenda', 'sisman-suite' ); ?>
                </label>
            </div>

            <div class="sisman-form-group">
                <label>
                    <input type="checkbox" name="sisman_show_labels" value="yes" <?php checked( $show_labels, 'yes' ); ?>>
                    <?php esc_html_e( 'Mostrar etiquetas', 'sisman-suite' ); ?>
                </label>
            </div>

            <div class="sisman-form-group">
                <label for="sisman_number_format"><?php esc_html_e( 'Formato numérico', 'sisman-suite' ); ?></label>
                <select id="sisman_number_format" name="sisman_number_format">
                    <option value="colombian" <?php selected( $number_format, 'colombian' ); ?>><?php esc_html_e( 'Colombiano (1.234.567,89)', 'sisman-suite' ); ?></option>
                    <option value="international" <?php selected( $number_format, 'international' ); ?>><?php esc_html_e( 'Internacional (1,234,567.89)', 'sisman-suite' ); ?></option>
                    <option value="abbreviated" <?php selected( $number_format, 'abbreviated' ); ?>><?php esc_html_e( 'Abreviado (1.2M)', 'sisman-suite' ); ?></option>
                </select>
            </div>
        </div>
    </div>

    <!-- Hidden fields for JS to populate -->
    <input type="hidden" id="sisman-saved-group-column" value="<?php echo esc_attr( $group_column ); ?>">
    <input type="hidden" id="sisman-saved-value-column" value="<?php echo esc_attr( $value_column ); ?>">
    <input type="hidden" id="sisman-saved-filters" value="<?php echo esc_attr( wp_json_encode( $filters ) ); ?>">
</div>
