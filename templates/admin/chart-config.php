<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$plugin = Sysman_Suite::instance();
$tables = $plugin->database->get_available_tables();

$chart_type     = get_post_meta( $post->ID, '_sysman_chart_type', true ) ?: 'bar';
$data_table     = get_post_meta( $post->ID, '_sysman_data_table', true ) ?: '';
$group_column   = get_post_meta( $post->ID, '_sysman_group_column', true ) ?: '';
$value_columns  = get_post_meta( $post->ID, '_sysman_value_columns', true ) ?: [];
$color_column   = get_post_meta( $post->ID, '_sysman_color_column', true ) ?: '';
$aggregate      = get_post_meta( $post->ID, '_sysman_aggregate', true ) ?: 'SUM';
$orientation    = get_post_meta( $post->ID, '_sysman_orientation', true ) ?: 'vertical';
$filter_anio    = get_post_meta( $post->ID, '_sysman_filter_anio', true ) ?: 0;
$filter_mes     = get_post_meta( $post->ID, '_sysman_filter_mes', true ) ?: 0;
$filter_destino = get_post_meta( $post->ID, '_sysman_filter_destino', true ) ?: '';
$chart_height   = get_post_meta( $post->ID, '_sysman_chart_height', true ) ?: 400;
$chart_colors   = get_post_meta( $post->ID, '_sysman_chart_colors', true ) ?: '#844e80,#ff7300,#ffc53b,#3eba6a,#0080c3,#e74c3c,#9b59b6,#1abc9c';
$show_legend    = get_post_meta( $post->ID, '_sysman_show_legend', true ) ?: '';
$show_labels    = get_post_meta( $post->ID, '_sysman_show_labels', true ) ?: 'yes';
$number_format  = get_post_meta( $post->ID, '_sysman_number_format', true ) ?: 'colombian';
$y_axis_title   = get_post_meta( $post->ID, '_sysman_y_axis_title', true ) ?: '';
$x_axis_title   = get_post_meta( $post->ID, '_sysman_x_axis_title', true ) ?: '';
$show_timeline  = get_post_meta( $post->ID, '_sysman_show_timeline', true ) ?: '';
$show_toolbar   = get_post_meta( $post->ID, '_sysman_show_toolbar', true ) ?: 'yes';
$toolbar_detail = get_post_meta( $post->ID, '_sysman_toolbar_detail', true ) ?: 'yes';
$toolbar_share  = get_post_meta( $post->ID, '_sysman_toolbar_share', true ) ?: 'yes';
$toolbar_data   = get_post_meta( $post->ID, '_sysman_toolbar_data', true ) ?: 'yes';
$toolbar_image  = get_post_meta( $post->ID, '_sysman_toolbar_image', true ) ?: 'yes';
$toolbar_csv    = get_post_meta( $post->ID, '_sysman_toolbar_csv', true ) ?: 'yes';
$custom_query   = get_post_meta( $post->ID, '_sysman_custom_query', true ) ?: '';
$filters        = get_post_meta( $post->ID, '_sysman_filters', true ) ?: [];

$data_source_mode        = get_post_meta( $post->ID, '_sysman_data_source_mode', true ) ?: 'table';
$vista_type              = get_post_meta( $post->ID, '_sysman_vista_type', true ) ?: 'ejecucion_dependencia';
$vista_dependencia       = get_post_meta( $post->ID, '_sysman_vista_dependencia', true ) ?: '';
$vista_compania          = get_post_meta( $post->ID, '_sysman_vista_compania', true ) ?: '001';
$tooltip_label_category  = get_post_meta( $post->ID, '_sysman_tooltip_label_category', true ) ?: '';
$tooltip_label_value     = get_post_meta( $post->ID, '_sysman_tooltip_label_value', true ) ?: '';
$tooltip_label_series    = get_post_meta( $post->ID, '_sysman_tooltip_label_series', true ) ?: '';

wp_nonce_field( 'sysman_chart_save', 'sysman_chart_nonce' );
?>

<div class="sysman-chart-config" role="form" aria-label="<?php esc_attr_e( 'Configuración de la Gráfica', 'sysman-suite' ); ?>">

    <!-- Chart Type -->
    <div class="sysman-config-section">
        <h3>
            <span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>
            <?php esc_html_e( 'Tipo de Gráfica', 'sysman-suite' ); ?>
        </h3>
        <div class="sysman-chart-types" role="radiogroup" aria-label="<?php esc_attr_e( 'Seleccione el tipo de gráfico', 'sysman-suite' ); ?>">
            <?php
            $chart_types = [
                'bar'          => [ 'label' => __( 'Barras', 'sysman-suite' ), 'svg' => '<svg viewBox="0 0 40 40"><rect x="4" y="20" width="8" height="16" fill="#3498db"/><rect x="16" y="10" width="8" height="26" fill="#e67e22"/><rect x="28" y="5" width="8" height="31" fill="#2ecc71"/></svg>' ],
                'horizontal_bar' => [ 'label' => __( 'Barras Horizontales', 'sysman-suite' ), 'svg' => '<svg viewBox="0 0 40 40"><rect x="4" y="4" width="32" height="7" fill="#3498db"/><rect x="4" y="14" width="24" height="7" fill="#e67e22"/><rect x="4" y="24" width="16" height="7" fill="#2ecc71"/><rect x="4" y="34" width="20" height="5" fill="#9b59b6"/></svg>' ],
                'stacked_bar'  => [ 'label' => __( 'Barras Apiladas', 'sysman-suite' ), 'svg' => '<svg viewBox="0 0 40 40"><rect x="4" y="24" width="8" height="12" fill="#3498db"/><rect x="4" y="14" width="8" height="10" fill="#e67e22"/><rect x="16" y="18" width="8" height="18" fill="#3498db"/><rect x="16" y="6" width="8" height="12" fill="#e67e22"/><rect x="28" y="20" width="8" height="16" fill="#3498db"/><rect x="28" y="10" width="8" height="10" fill="#e67e22"/></svg>' ],
                'grouped_bar'  => [ 'label' => __( 'Barras Agrupadas', 'sysman-suite' ), 'svg' => '<svg viewBox="0 0 40 40"><rect x="2" y="18" width="5" height="18" fill="#3498db"/><rect x="8" y="12" width="5" height="24" fill="#e67e22"/><rect x="16" y="10" width="5" height="26" fill="#3498db"/><rect x="22" y="6" width="5" height="30" fill="#e67e22"/><rect x="30" y="22" width="5" height="14" fill="#3498db"/><rect x="36" y="16" width="5" height="20" fill="#e67e22"/></svg>' ],
                'line'         => [ 'label' => __( 'Líneas', 'sysman-suite' ), 'svg' => '<svg viewBox="0 0 40 40"><polyline points="4,32 14,18 24,24 36,8" fill="none" stroke="#3498db" stroke-width="2.5"/><polyline points="4,28 14,22 24,14 36,18" fill="none" stroke="#e67e22" stroke-width="2.5"/></svg>' ],
                'area'         => [ 'label' => __( 'Área', 'sysman-suite' ), 'svg' => '<svg viewBox="0 0 40 40"><polygon points="4,36 4,28 14,16 24,22 36,8 36,36" fill="#3498db" opacity="0.3"/><polyline points="4,28 14,16 24,22 36,8" fill="none" stroke="#3498db" stroke-width="2"/></svg>' ],
                'stacked_area' => [ 'label' => __( 'Área Apilada', 'sysman-suite' ), 'svg' => '<svg viewBox="0 0 40 40"><polygon points="4,36 4,24 14,14 24,18 36,8 36,36" fill="#3498db" opacity="0.25"/><polygon points="4,36 4,30 14,22 24,26 36,16 36,36" fill="#e67e22" opacity="0.35"/><polyline points="4,24 14,14 24,18 36,8" fill="none" stroke="#3498db" stroke-width="1.5"/><polyline points="4,30 14,22 24,26 36,16" fill="none" stroke="#e67e22" stroke-width="1.5"/></svg>' ],
                'pie'          => [ 'label' => __( 'Pie / Torta', 'sysman-suite' ), 'svg' => '<svg viewBox="0 0 40 40"><circle cx="20" cy="20" r="16" fill="#3498db"/><path d="M20,20 L20,4 A16,16 0 0,1 34.5,28Z" fill="#e67e22"/><path d="M20,20 L34.5,28 A16,16 0 0,1 12,34Z" fill="#2ecc71"/></svg>' ],
                'donut'        => [ 'label' => __( 'Donut', 'sysman-suite' ), 'svg' => '<svg viewBox="0 0 40 40"><circle cx="20" cy="20" r="16" fill="#e67e22"/><path d="M20,20 L20,4 A16,16 0 0,1 36,20Z" fill="#3498db"/><path d="M20,20 L8,30 A16,16 0 0,1 4,20Z" fill="#2ecc71"/><circle cx="20" cy="20" r="8" fill="white"/></svg>' ],
                'treemap'      => [ 'label' => __( 'Treemap', 'sysman-suite' ), 'svg' => '<svg viewBox="0 0 40 40"><rect x="2" y="2" width="22" height="22" fill="#3498db"/><rect x="26" y="2" width="12" height="14" fill="#e67e22"/><rect x="26" y="18" width="12" height="6" fill="#2ecc71"/><rect x="2" y="26" width="14" height="12" fill="#9b59b6"/><rect x="18" y="26" width="20" height="12" fill="#f39c12"/></svg>' ],
                'radar'        => [ 'label' => __( 'Radar', 'sysman-suite' ), 'svg' => '<svg viewBox="0 0 40 40"><polygon points="20,4 35,14 32,32 8,32 5,14" fill="none" stroke="#ccc" stroke-width="0.5"/><polygon points="20,10 30,17 28,29 12,29 10,17" fill="#3498db" opacity="0.3" stroke="#3498db" stroke-width="1.5"/></svg>' ],
            ];
            foreach ( $chart_types as $type => $info ) :
            ?>
            <label class="sysman-chart-type-option <?php echo $chart_type === $type ? 'active' : ''; ?>">
                <input type="radio" name="sysman_chart_type" value="<?php echo esc_attr( $type ); ?>" <?php checked( $chart_type, $type ); ?>>
                <div class="sysman-chart-type-icon"><?php echo $info['svg']; ?></div>
                <span class="sysman-chart-type-label"><?php echo esc_html( $info['label'] ); ?></span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Data Source -->
    <div class="sysman-config-section">
        <h3>
            <span class="dashicons dashicons-database" aria-hidden="true"></span>
            <?php esc_html_e( 'Fuente de Datos', 'sysman-suite' ); ?>
        </h3>

        <!-- Data Source Tabs -->
        <div class="sysman-source-tabs" role="tablist">
            <button type="button" class="sysman-source-tab <?php echo 'table' === $data_source_mode ? 'active' : ''; ?>" data-tab="table" role="tab" aria-selected="<?php echo 'table' === $data_source_mode ? 'true' : 'false'; ?>">
                <span class="dashicons dashicons-editor-table" aria-hidden="true"></span>
                <?php esc_html_e( 'Tablas', 'sysman-suite' ); ?>
            </button>
            <button type="button" class="sysman-source-tab <?php echo 'vista' === $data_source_mode ? 'active' : ''; ?>" data-tab="vista" role="tab" aria-selected="<?php echo 'vista' === $data_source_mode ? 'true' : 'false'; ?>">
                <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                <?php esc_html_e( 'Vistas', 'sysman-suite' ); ?>
            </button>
        </div>
        <input type="hidden" id="sysman_data_source_mode" name="sysman_data_source_mode" value="<?php echo esc_attr( $data_source_mode ); ?>">

        <!-- Tab: Tablas -->
        <div class="sysman-source-panel" id="sysman-panel-table" style="<?php echo 'table' !== $data_source_mode ? 'display:none;' : ''; ?>">

            <!-- Field guidance hint -->
            <div id="sysman-field-guidance" class="sysman-field-guidance" style="display:none;">
                <span class="dashicons dashicons-lightbulb" aria-hidden="true" style="color:var(--sysman-warning,#f39c12);"></span>
                <span id="sysman-field-guidance-text"></span>
            </div>

            <div class="sysman-form-stack">
                <div class="sysman-form-group">
                    <label for="sysman_data_table"><?php esc_html_e( 'Tabla de Datos', 'sysman-suite' ); ?></label>
                    <select id="sysman_data_table" name="sysman_data_table">
                        <option value=""><?php esc_html_e( '-- Seleccionar tabla --', 'sysman-suite' ); ?></option>
                        <?php foreach ( $tables as $table_name => $label ) : ?>
                        <option value="<?php echo esc_attr( $table_name ); ?>" <?php selected( $data_table, $table_name ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sysman-form-group">
                    <label for="sysman_group_column">
                        <?php esc_html_e( 'Columna de Agrupación (Eje X / Etiquetas)', 'sysman-suite' ); ?>
                    </label>
                    <select id="sysman_group_column" name="sysman_group_column">
                        <option value=""><?php esc_html_e( '-- Seleccionar columna --', 'sysman-suite' ); ?></option>
                    </select>
                    <p class="description sysman-column-hint" id="sysman-group-hint"></p>
                </div>

                <!-- Dynamic Y-value columns -->
                <div class="sysman-form-group">
                    <label>
                        <?php esc_html_e( 'Columnas de Valor (Eje Y)', 'sysman-suite' ); ?>
                    </label>
                    <p class="description sysman-column-hint" id="sysman-value-hint"></p>
                    <div id="sysman-value-columns-list">
                        <!-- JS will populate saved values here -->
                    </div>
                    <button type="button" id="sysman-add-value-column" class="button" style="margin-top:8px;">
                        <span class="dashicons dashicons-plus-alt2" aria-hidden="true" style="vertical-align:middle;margin-top:-2px;"></span>
                        <?php esc_html_e( 'Agregar Valor Y', 'sysman-suite' ); ?>
                    </button>
                    <p class="description" style="margin-top:6px;">
                        <?php esc_html_e( 'Cada columna de valor genera una serie independiente en el gráfico. Ej: agregar "Apropiación Vigente" y "Apropiación Inicial" para comparar ambas métricas por año.', 'sysman-suite' ); ?>
                    </p>
                </div>

                <!-- Color/Series column -->
                <div class="sysman-form-group sysman-field-color-column" id="sysman-color-column-wrap" style="display:none;">
                    <label for="sysman_color_column">
                        <?php esc_html_e( 'Columna de Serie / Color (agrupación secundaria)', 'sysman-suite' ); ?>
                    </label>
                    <select id="sysman_color_column" name="sysman_color_column">
                        <option value=""><?php esc_html_e( '-- Sin serie adicional --', 'sysman-suite' ); ?></option>
                    </select>
                    <p class="description" id="sysman-color-hint">
                        <?php esc_html_e( 'Solo visible si usa 1 valor Y. Define series por una columna categórica (ej: destino). Si usa múltiples valores Y, las series se crean automáticamente.', 'sysman-suite' ); ?>
                    </p>
                </div>

                <div class="sysman-form-row-inline">
                    <div class="sysman-form-group">
                        <label for="sysman_aggregate"><?php esc_html_e( 'Función de Agregación', 'sysman-suite' ); ?></label>
                        <select id="sysman_aggregate" name="sysman_aggregate">
                            <option value="SUM" <?php selected( $aggregate, 'SUM' ); ?>>SUM - <?php esc_html_e( 'Suma', 'sysman-suite' ); ?></option>
                            <option value="COUNT" <?php selected( $aggregate, 'COUNT' ); ?>>COUNT - <?php esc_html_e( 'Contar', 'sysman-suite' ); ?></option>
                            <option value="AVG" <?php selected( $aggregate, 'AVG' ); ?>>AVG - <?php esc_html_e( 'Promedio', 'sysman-suite' ); ?></option>
                            <option value="MAX" <?php selected( $aggregate, 'MAX' ); ?>>MAX - <?php esc_html_e( 'Máximo', 'sysman-suite' ); ?></option>
                            <option value="MIN" <?php selected( $aggregate, 'MIN' ); ?>>MIN - <?php esc_html_e( 'Mínimo', 'sysman-suite' ); ?></option>
                        </select>
                    </div>

                    <!-- Orientation -->
                    <div class="sysman-form-group sysman-field-orientation" id="sysman-orientation-wrap" style="display:none;">
                        <label for="sysman_orientation"><?php esc_html_e( 'Orientación', 'sysman-suite' ); ?></label>
                        <select id="sysman_orientation" name="sysman_orientation">
                            <option value="vertical" <?php selected( $orientation, 'vertical' ); ?>><?php esc_html_e( 'Vertical', 'sysman-suite' ); ?></option>
                            <option value="horizontal" <?php selected( $orientation, 'horizontal' ); ?>><?php esc_html_e( 'Horizontal', 'sysman-suite' ); ?></option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab: Vistas -->
        <div class="sysman-source-panel" id="sysman-panel-vista" style="<?php echo 'vista' !== $data_source_mode ? 'display:none;' : ''; ?>">
            <div class="sysman-field-guidance" style="margin-bottom:12px;">
                <span class="dashicons dashicons-info-outline" aria-hidden="true" style="color:var(--sysman-primary,#1a5276);"></span>
                <span><?php esc_html_e( 'Las Vistas combinan automáticamente las tablas Plan Presupuestal y Ejecución de Gastos mediante JOINs, mostrando la ejecución por dependencia.', 'sysman-suite' ); ?></span>
            </div>

            <div class="sysman-form-stack">
                <div class="sysman-form-group">
                    <label for="sysman_vista_type"><?php esc_html_e( 'Tipo de Vista', 'sysman-suite' ); ?></label>
                    <select id="sysman_vista_type" name="sysman_vista_type">
                        <option value="ejecucion_dependencia" <?php selected( $vista_type, 'ejecucion_dependencia' ); ?>>
                            <?php esc_html_e( 'Ejecución por Dependencia', 'sysman-suite' ); ?>
                        </option>
                    </select>
                    <p class="description"><?php esc_html_e( 'JOIN entre Plan Presupuestal (pp.codigo = eg.codigocuenta) y Ejecución de Gastos, agrupado por rubro (pp.nombre).', 'sysman-suite' ); ?></p>
                </div>

                <div class="sysman-form-row">
                    <div class="sysman-form-group">
                        <label for="sysman_vista_compania"><?php esc_html_e( 'Compañía', 'sysman-suite' ); ?></label>
                        <input type="text" id="sysman_vista_compania" name="sysman_vista_compania" value="<?php echo esc_attr( $vista_compania ); ?>" class="small-text" placeholder="001">
                    </div>

                    <div class="sysman-form-group" style="flex:2;">
                        <label for="sysman_vista_dependencia"><?php esc_html_e( 'Dependencia', 'sysman-suite' ); ?></label>
                        <select id="sysman_vista_dependencia" name="sysman_vista_dependencia">
                            <option value=""><?php esc_html_e( '-- Todas las dependencias --', 'sysman-suite' ); ?></option>
                            <?php if ( $vista_dependencia ) : ?>
                            <option value="<?php echo esc_attr( $vista_dependencia ); ?>" selected><?php echo esc_html( $vista_dependencia ); ?></option>
                            <?php endif; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Filtrar por dependencia específica. Las opciones se cargan según la compañía, año y mes seleccionados.', 'sysman-suite' ); ?></p>
                    </div>
                </div>

                <!-- Vista Y-value columns (reuses the ejecucion_gastos numeric columns).
                     These use their own field names (sysman_vista_*) so the two panels
                     never overwrite each other; PHP picks the set that matches the
                     active data source mode. -->
                <div class="sysman-form-group">
                    <label><?php esc_html_e( 'Columnas de Valor (Eje Y)', 'sysman-suite' ); ?></label>
                    <p class="description"><?php esc_html_e( 'Seleccione las métricas de ejecución a visualizar. Cada una genera una serie en el gráfico.', 'sysman-suite' ); ?></p>
                    <div id="sysman-vista-value-columns-list">
                        <!-- JS populates the saved (or default) columns here -->
                    </div>
                    <button type="button" id="sysman-vista-add-value-column" class="button" style="margin-top:8px;">
                        <span class="dashicons dashicons-plus-alt2" aria-hidden="true" style="vertical-align:middle;margin-top:-2px;"></span>
                        <?php esc_html_e( 'Agregar Valor Y', 'sysman-suite' ); ?>
                    </button>
                </div>

                <div class="sysman-form-group">
                    <label for="sysman_aggregate_vista"><?php esc_html_e( 'Función de Agregación', 'sysman-suite' ); ?></label>
                    <select id="sysman_aggregate_vista" name="sysman_vista_aggregate">
                        <option value="SUM" <?php selected( $aggregate, 'SUM' ); ?>>SUM - <?php esc_html_e( 'Suma', 'sysman-suite' ); ?></option>
                        <option value="COUNT" <?php selected( $aggregate, 'COUNT' ); ?>>COUNT - <?php esc_html_e( 'Contar', 'sysman-suite' ); ?></option>
                        <option value="AVG" <?php selected( $aggregate, 'AVG' ); ?>>AVG - <?php esc_html_e( 'Promedio', 'sysman-suite' ); ?></option>
                        <option value="MAX" <?php selected( $aggregate, 'MAX' ); ?>>MAX - <?php esc_html_e( 'Máximo', 'sysman-suite' ); ?></option>
                        <option value="MIN" <?php selected( $aggregate, 'MIN' ); ?>>MIN - <?php esc_html_e( 'Mínimo', 'sysman-suite' ); ?></option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="sysman-config-section">
        <h3>
            <span class="dashicons dashicons-filter" aria-hidden="true"></span>
            <?php esc_html_e( 'Filtros', 'sysman-suite' ); ?>
        </h3>

        <div class="sysman-form-row">
            <div class="sysman-form-group">
                <label for="sysman_filter_anio"><?php esc_html_e( 'Año', 'sysman-suite' ); ?></label>
                <input type="number" id="sysman_filter_anio" name="sysman_filter_anio" value="<?php echo esc_attr( $filter_anio ); ?>" min="0" max="2100" class="small-text">
                <p class="description"><?php esc_html_e( 'Dejar en 0 para todos los años', 'sysman-suite' ); ?></p>
            </div>

            <div class="sysman-form-group">
                <label for="sysman_filter_mes"><?php esc_html_e( 'Mes', 'sysman-suite' ); ?></label>
                <input type="number" id="sysman_filter_mes" name="sysman_filter_mes" value="<?php echo esc_attr( $filter_mes ); ?>" min="0" max="12" class="small-text">
                <p class="description"><?php esc_html_e( 'Dejar en 0 para todos los meses', 'sysman-suite' ); ?></p>
            </div>

            <div class="sysman-form-group">
                <label for="sysman_filter_destino"><?php esc_html_e( 'Destino', 'sysman-suite' ); ?></label>
                <select id="sysman_filter_destino" name="sysman_filter_destino">
                    <option value="" <?php selected( $filter_destino, '' ); ?>><?php esc_html_e( 'Todos', 'sysman-suite' ); ?></option>
                    <option value="FUNCIONAMIENTO" <?php selected( $filter_destino, 'FUNCIONAMIENTO' ); ?>>FUNCIONAMIENTO</option>
                    <option value="INVERSION" <?php selected( $filter_destino, 'INVERSION' ); ?>>INVERSION</option>
                </select>
            </div>
        </div>

        <!-- Custom Filters -->
        <div id="sysman-custom-filters">
            <h4><?php esc_html_e( 'Filtros Adicionales', 'sysman-suite' ); ?></h4>
            <div id="sysman-filters-list">
                <?php if ( ! empty( $filters ) ) : ?>
                    <?php foreach ( $filters as $i => $filter ) : ?>
                    <div class="sysman-filter-row" data-index="<?php echo esc_attr( $i ); ?>">
                        <select name="sysman_filters[<?php echo esc_attr( $i ); ?>][column]" class="sysman-filter-column">
                            <option value=""><?php esc_html_e( 'Columna', 'sysman-suite' ); ?></option>
                        </select>
                        <select name="sysman_filters[<?php echo esc_attr( $i ); ?>][operator]">
                            <option value="=" <?php selected( $filter['operator'], '=' ); ?>>=</option>
                            <option value="!=" <?php selected( $filter['operator'], '!=' ); ?>>!=</option>
                            <option value=">" <?php selected( $filter['operator'], '>' ); ?>>&gt;</option>
                            <option value="<" <?php selected( $filter['operator'], '<' ); ?>>&lt;</option>
                            <option value=">=" <?php selected( $filter['operator'], '>=' ); ?>>&gt;=</option>
                            <option value="<=" <?php selected( $filter['operator'], '<=' ); ?>>&lt;=</option>
                            <option value="LIKE" <?php selected( $filter['operator'], 'LIKE' ); ?>>LIKE</option>
                        </select>
                        <input type="text" name="sysman_filters[<?php echo esc_attr( $i ); ?>][value]" value="<?php echo esc_attr( $filter['value'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Valor', 'sysman-suite' ); ?>">
                        <button type="button" class="button sysman-remove-filter" aria-label="<?php esc_attr_e( 'Eliminar filtro', 'sysman-suite' ); ?>">&times;</button>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button type="button" id="sysman-add-filter" class="button">
                <span aria-hidden="true" class="dashicons dashicons-plus-alt2"></span>
                <?php esc_html_e( 'Agregar Filtro', 'sysman-suite' ); ?>
            </button>
        </div>
    </div>

    <!-- Appearance -->
    <div class="sysman-config-section">
        <h3>
            <span class="dashicons dashicons-art" aria-hidden="true"></span>
            <?php esc_html_e( 'Apariencia', 'sysman-suite' ); ?>
        </h3>

        <table class="form-table sysman-appearance-table">
            <tr>
                <th scope="row">
                    <label for="sysman_chart_height"><?php esc_html_e( 'Altura de la Gráfica', 'sysman-suite' ); ?></label>
                </th>
                <td>
                    <input type="number" id="sysman_chart_height" name="sysman_chart_height" value="<?php echo esc_attr( $chart_height ); ?>" min="200" max="1000" class="small-text"> px
                </td>
            </tr>
            <tr class="sysman-field-axes">
                <th scope="row">
                    <label for="sysman_y_axis_title"><?php esc_html_e( 'Título Eje Y', 'sysman-suite' ); ?></label>
                </th>
                <td>
                    <input type="text" id="sysman_y_axis_title" name="sysman_y_axis_title" value="<?php echo esc_attr( $y_axis_title ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Valor en Pesos Colombianos', 'sysman-suite' ); ?>">
                </td>
            </tr>
            <tr class="sysman-field-axes">
                <th scope="row">
                    <label for="sysman_x_axis_title"><?php esc_html_e( 'Título Eje X', 'sysman-suite' ); ?></label>
                </th>
                <td>
                    <input type="text" id="sysman_x_axis_title" name="sysman_x_axis_title" value="<?php echo esc_attr( $x_axis_title ); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sysman_number_format"><?php esc_html_e( 'Formato de Números', 'sysman-suite' ); ?></label>
                </th>
                <td>
                    <select id="sysman_number_format" name="sysman_number_format">
                        <option value="colombian" <?php selected( $number_format, 'colombian' ); ?>><?php esc_html_e( 'Colombiano (1.000.000)', 'sysman-suite' ); ?></option>
                        <option value="international" <?php selected( $number_format, 'international' ); ?>><?php esc_html_e( 'Internacional (1,000,000)', 'sysman-suite' ); ?></option>
                        <option value="abbreviated" <?php selected( $number_format, 'abbreviated' ); ?>><?php esc_html_e( 'Abreviado (1.2M)', 'sysman-suite' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sysman_chart_colors"><?php esc_html_e( 'Paleta de Colores', 'sysman-suite' ); ?></label>
                </th>
                <td>
                    <input type="text" id="sysman_chart_colors" name="sysman_chart_colors" value="<?php echo esc_attr( $chart_colors ); ?>" class="large-text">
                    <p class="description"><?php esc_html_e( 'Colores hexadecimales separados por coma', 'sysman-suite' ); ?></p>
                    <div id="sysman-color-preview" class="sysman-color-preview"></div>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Opciones de Visualización', 'sysman-suite' ); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="sysman_show_legend" value="yes" <?php checked( $show_legend, 'yes' ); ?>>
                            <?php esc_html_e( 'Mostrar leyenda', 'sysman-suite' ); ?>
                        </label>
                        <br>
                        <label>
                            <input type="checkbox" name="sysman_show_timeline" value="yes" <?php checked( $show_timeline, 'yes' ); ?>>
                            <?php esc_html_e( 'Mostrar línea de tiempo interactiva', 'sysman-suite' ); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
        </table>
    </div>

    <!-- Tooltip Configuration -->
    <div class="sysman-config-section">
        <h3>
            <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
            <?php esc_html_e( 'Tooltip (Información al pasar el mouse)', 'sysman-suite' ); ?>
        </h3>

        <p class="description" style="margin-bottom:12px;">
            <?php esc_html_e( 'Personalice las etiquetas que aparecen en el tooltip al pasar el mouse sobre los datos de la gráfica. Déjelo vacío para usar los valores por defecto.', 'sysman-suite' ); ?>
        </p>

        <table class="form-table sysman-appearance-table">
            <tr>
                <th scope="row">
                    <label for="sysman_tooltip_label_category"><?php esc_html_e( 'Etiqueta de Categoría', 'sysman-suite' ); ?></label>
                </th>
                <td>
                    <input type="text" id="sysman_tooltip_label_category" name="sysman_tooltip_label_category" value="<?php echo esc_attr( $tooltip_label_category ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Categoría (o Título Eje X)', 'sysman-suite' ); ?>">
                    <p class="description"><?php esc_html_e( 'Nombre para el campo de categoría/etiqueta en el tooltip. Ej: "Rubro", "Dependencia", "Año".', 'sysman-suite' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sysman_tooltip_label_value"><?php esc_html_e( 'Etiqueta de Valor', 'sysman-suite' ); ?></label>
                </th>
                <td>
                    <input type="text" id="sysman_tooltip_label_value" name="sysman_tooltip_label_value" value="<?php echo esc_attr( $tooltip_label_value ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Valor (o Título Eje Y)', 'sysman-suite' ); ?>">
                    <p class="description"><?php esc_html_e( 'Nombre para el campo de valor numérico. Ej: "Monto", "Presupuesto", "Total".', 'sysman-suite' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sysman_tooltip_label_series"><?php esc_html_e( 'Etiqueta de Serie', 'sysman-suite' ); ?></label>
                </th>
                <td>
                    <input type="text" id="sysman_tooltip_label_series" name="sysman_tooltip_label_series" value="<?php echo esc_attr( $tooltip_label_series ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Serie', 'sysman-suite' ); ?>">
                    <p class="description"><?php esc_html_e( 'Nombre para la serie/grupo en el tooltip. Ej: "Concepto", "Tipo", "Métrica".', 'sysman-suite' ); ?></p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Toolbar Configuration -->
    <div class="sysman-config-section">
        <h3>
            <span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>
            <?php esc_html_e( 'Barra de Herramientas', 'sysman-suite' ); ?>
        </h3>

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Mostrar Barra', 'sysman-suite' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="sysman_show_toolbar" value="yes" <?php checked( $show_toolbar, 'yes' ); ?>>
                        <?php esc_html_e( 'Mostrar barra de herramientas superior', 'sysman-suite' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Opciones a Mostrar', 'sysman-suite' ); ?></th>
                <td>
                    <fieldset class="sysman-toolbar-options">
                        <label>
                            <input type="checkbox" name="sysman_toolbar_detail" value="yes" <?php checked( $toolbar_detail, 'yes' ); ?>>
                            <?php esc_html_e( 'Detalle (info)', 'sysman-suite' ); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="sysman_toolbar_share" value="yes" <?php checked( $toolbar_share, 'yes' ); ?>>
                            <?php esc_html_e( 'Compartir', 'sysman-suite' ); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="sysman_toolbar_data" value="yes" <?php checked( $toolbar_data, 'yes' ); ?>>
                            <?php esc_html_e( 'Ver Datos', 'sysman-suite' ); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="sysman_toolbar_image" value="yes" <?php checked( $toolbar_image, 'yes' ); ?>>
                            <?php esc_html_e( 'Guardar Imagen', 'sysman-suite' ); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="sysman_toolbar_csv" value="yes" <?php checked( $toolbar_csv, 'yes' ); ?>>
                            <?php esc_html_e( 'Descargar CSV', 'sysman-suite' ); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
        </table>
    </div>

    <!-- Custom Query (Advanced) -->
    <div class="sysman-config-section sysman-collapsible">
        <div class="sysman-collapsible-header">
            <span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
            <strong><?php esc_html_e( 'Query Personalizada (Avanzado)', 'sysman-suite' ); ?></strong>
            <button type="button" class="button button-small sysman-toggle-section"><?php esc_html_e( 'Expandir', 'sysman-suite' ); ?></button>
        </div>
        <div class="sysman-collapsible-body" style="display:none;">
            <p class="description"><?php esc_html_e( 'Si necesita una consulta SQL personalizada, puede escribirla aquí. Esta consulta reemplazará la generada automáticamente. Debe retornar columnas "label" y "value" (y opcionalmente "group" para gráficos multi-serie).', 'sysman-suite' ); ?></p>
            <textarea id="sysman_custom_query" name="sysman_custom_query" class="large-text code" rows="6" placeholder="SELECT columna AS label, SUM(valor) AS value, serie AS group FROM tabla WHERE ... GROUP BY columna, serie"><?php echo esc_textarea( $custom_query ); ?></textarea>
            <p class="description">
                <span class="dashicons dashicons-warning" aria-hidden="true" style="color:#dba617;"></span>
                <?php esc_html_e( 'Advertencia: Las consultas personalizadas no se validan automáticamente. Use con precaución.', 'sysman-suite' ); ?>
            </p>
        </div>
    </div>

    <!-- Hidden fields for JS to populate -->
    <input type="hidden" id="sysman-saved-group-column" value="<?php echo esc_attr( $group_column ); ?>">
    <input type="hidden" id="sysman-saved-value-columns" value="<?php echo esc_attr( wp_json_encode( $value_columns ) ); ?>">
    <input type="hidden" id="sysman-saved-color-column" value="<?php echo esc_attr( $color_column ); ?>">
    <input type="hidden" id="sysman-saved-filters" value="<?php echo esc_attr( wp_json_encode( $filters ) ); ?>">
    <input type="hidden" id="sysman-saved-vista-dependencia" value="<?php echo esc_attr( $vista_dependencia ); ?>">
    <?php
    // Y columns the Vista panel should start with: the saved ones when this
    // chart was stored as a Vista, otherwise the three usual execution metrics.
    $vista_saved_columns = ( 'vista' === $data_source_mode && ! empty( $value_columns ) )
        ? $value_columns
        : [ 'apropiacionvigente', 'compromisos', 'pagos' ];
    ?>
    <input type="hidden" id="sysman-saved-vista-value-columns" value="<?php echo esc_attr( wp_json_encode( array_values( (array) $vista_saved_columns ) ) ); ?>">
</div>
