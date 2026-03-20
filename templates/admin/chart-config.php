<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$plugin = Sisman_Suite::instance();
$tables = $plugin->database->get_available_tables();

$chart_type     = get_post_meta( $post->ID, '_sisman_chart_type', true ) ?: 'bar';
$data_table     = get_post_meta( $post->ID, '_sisman_data_table', true ) ?: '';
$group_column   = get_post_meta( $post->ID, '_sisman_group_column', true ) ?: '';
$value_column   = get_post_meta( $post->ID, '_sisman_value_column', true ) ?: '';
$aggregate      = get_post_meta( $post->ID, '_sisman_aggregate', true ) ?: 'SUM';
$filter_anio    = get_post_meta( $post->ID, '_sisman_filter_anio', true ) ?: 0;
$filter_mes     = get_post_meta( $post->ID, '_sisman_filter_mes', true ) ?: 0;
$filter_destino = get_post_meta( $post->ID, '_sisman_filter_destino', true ) ?: '';
$chart_height   = get_post_meta( $post->ID, '_sisman_chart_height', true ) ?: 400;
$chart_colors   = get_post_meta( $post->ID, '_sisman_chart_colors', true ) ?: '#844e80,#ff7300,#ffc53b,#3eba6a,#0080c3,#e74c3c,#9b59b6,#1abc9c';
$show_legend    = get_post_meta( $post->ID, '_sisman_show_legend', true ) ?: '';
$show_labels    = get_post_meta( $post->ID, '_sisman_show_labels', true ) ?: 'yes';
$number_format  = get_post_meta( $post->ID, '_sisman_number_format', true ) ?: 'colombian';
$y_axis_title   = get_post_meta( $post->ID, '_sisman_y_axis_title', true ) ?: '';
$x_axis_title   = get_post_meta( $post->ID, '_sisman_x_axis_title', true ) ?: '';
$show_timeline  = get_post_meta( $post->ID, '_sisman_show_timeline', true ) ?: '';
$show_toolbar   = get_post_meta( $post->ID, '_sisman_show_toolbar', true ) ?: 'yes';
$toolbar_detail = get_post_meta( $post->ID, '_sisman_toolbar_detail', true ) ?: 'yes';
$toolbar_share  = get_post_meta( $post->ID, '_sisman_toolbar_share', true ) ?: 'yes';
$toolbar_data   = get_post_meta( $post->ID, '_sisman_toolbar_data', true ) ?: 'yes';
$toolbar_image  = get_post_meta( $post->ID, '_sisman_toolbar_image', true ) ?: 'yes';
$toolbar_csv    = get_post_meta( $post->ID, '_sisman_toolbar_csv', true ) ?: 'yes';
$custom_query   = get_post_meta( $post->ID, '_sisman_custom_query', true ) ?: '';
$filters        = get_post_meta( $post->ID, '_sisman_filters', true ) ?: [];

wp_nonce_field( 'sisman_chart_save', 'sisman_chart_nonce' );
?>

<div class="sisman-chart-config" role="form" aria-label="<?php esc_attr_e( 'Configuración de la Gráfica', 'sisman-suite' ); ?>">

    <!-- Chart Type -->
    <div class="sisman-config-section">
        <h3>
            <span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>
            <?php esc_html_e( 'Tipo de Gráfica', 'sisman-suite' ); ?>
        </h3>
        <div class="sisman-chart-types" role="radiogroup" aria-label="<?php esc_attr_e( 'Seleccione el tipo de gráfico', 'sisman-suite' ); ?>">
            <?php
            $chart_types = [
                'bar'         => [ 'label' => __( 'Barras', 'sisman-suite' ), 'svg' => '<svg viewBox="0 0 40 40"><rect x="4" y="20" width="8" height="16" fill="#3498db"/><rect x="16" y="10" width="8" height="26" fill="#e67e22"/><rect x="28" y="5" width="8" height="31" fill="#2ecc71"/></svg>' ],
                'line'        => [ 'label' => __( 'Líneas', 'sisman-suite' ), 'svg' => '<svg viewBox="0 0 40 40"><polyline points="4,32 14,18 24,24 36,8" fill="none" stroke="#3498db" stroke-width="2.5"/></svg>' ],
                'area'        => [ 'label' => __( 'Área', 'sisman-suite' ), 'svg' => '<svg viewBox="0 0 40 40"><polygon points="4,36 4,28 14,16 24,22 36,8 36,36" fill="#3498db" opacity="0.3"/><polyline points="4,28 14,16 24,22 36,8" fill="none" stroke="#3498db" stroke-width="2"/></svg>' ],
                'pie'         => [ 'label' => __( 'Pie / Torta', 'sisman-suite' ), 'svg' => '<svg viewBox="0 0 40 40"><circle cx="20" cy="20" r="16" fill="#3498db"/><path d="M20,20 L20,4 A16,16 0 0,1 34.5,28Z" fill="#e67e22"/><path d="M20,20 L34.5,28 A16,16 0 0,1 12,34Z" fill="#2ecc71"/></svg>' ],
                'donut'       => [ 'label' => __( 'Donut', 'sisman-suite' ), 'svg' => '<svg viewBox="0 0 40 40"><circle cx="20" cy="20" r="16" fill="#e67e22"/><path d="M20,20 L20,4 A16,16 0 0,1 36,20Z" fill="#3498db"/><path d="M20,20 L8,30 A16,16 0 0,1 4,20Z" fill="#2ecc71"/><circle cx="20" cy="20" r="8" fill="white"/></svg>' ],
                'treemap'     => [ 'label' => __( 'Treemap', 'sisman-suite' ), 'svg' => '<svg viewBox="0 0 40 40"><rect x="2" y="2" width="22" height="22" fill="#3498db"/><rect x="26" y="2" width="12" height="14" fill="#e67e22"/><rect x="26" y="18" width="12" height="6" fill="#2ecc71"/><rect x="2" y="26" width="14" height="12" fill="#9b59b6"/><rect x="18" y="26" width="20" height="12" fill="#f39c12"/></svg>' ],
                'stacked_bar' => [ 'label' => __( 'Barras Apiladas', 'sisman-suite' ), 'svg' => '<svg viewBox="0 0 40 40"><rect x="4" y="24" width="8" height="12" fill="#3498db"/><rect x="4" y="14" width="8" height="10" fill="#e67e22"/><rect x="16" y="18" width="8" height="18" fill="#3498db"/><rect x="16" y="6" width="8" height="12" fill="#e67e22"/><rect x="28" y="20" width="8" height="16" fill="#3498db"/><rect x="28" y="10" width="8" height="10" fill="#e67e22"/></svg>' ],
                'grouped_bar' => [ 'label' => __( 'Barras Agrupadas', 'sisman-suite' ), 'svg' => '<svg viewBox="0 0 40 40"><rect x="2" y="18" width="5" height="18" fill="#3498db"/><rect x="8" y="12" width="5" height="24" fill="#e67e22"/><rect x="16" y="10" width="5" height="26" fill="#3498db"/><rect x="22" y="6" width="5" height="30" fill="#e67e22"/><rect x="30" y="22" width="5" height="14" fill="#3498db"/><rect x="36" y="16" width="5" height="20" fill="#e67e22"/></svg>' ],
            ];
            foreach ( $chart_types as $type => $info ) :
            ?>
            <label class="sisman-chart-type-option <?php echo $chart_type === $type ? 'active' : ''; ?>">
                <input type="radio" name="sisman_chart_type" value="<?php echo esc_attr( $type ); ?>" <?php checked( $chart_type, $type ); ?>>
                <div class="sisman-chart-type-icon"><?php echo $info['svg']; ?></div>
                <span class="sisman-chart-type-label"><?php echo esc_html( $info['label'] ); ?></span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Data Source -->
    <div class="sisman-config-section">
        <h3>
            <span class="dashicons dashicons-database" aria-hidden="true"></span>
            <?php esc_html_e( 'Fuente de Datos', 'sisman-suite' ); ?>
        </h3>

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
        <h3>
            <span class="dashicons dashicons-filter" aria-hidden="true"></span>
            <?php esc_html_e( 'Filtros', 'sisman-suite' ); ?>
        </h3>

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
        <h3>
            <span class="dashicons dashicons-art" aria-hidden="true"></span>
            <?php esc_html_e( 'Apariencia', 'sisman-suite' ); ?>
        </h3>

        <table class="form-table sisman-appearance-table">
            <tr>
                <th scope="row">
                    <label for="sisman_chart_height"><?php esc_html_e( 'Altura de la Gráfica', 'sisman-suite' ); ?></label>
                </th>
                <td>
                    <input type="number" id="sisman_chart_height" name="sisman_chart_height" value="<?php echo esc_attr( $chart_height ); ?>" min="200" max="1000" class="small-text"> px
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sisman_y_axis_title"><?php esc_html_e( 'Título Eje Y', 'sisman-suite' ); ?></label>
                </th>
                <td>
                    <input type="text" id="sisman_y_axis_title" name="sisman_y_axis_title" value="<?php echo esc_attr( $y_axis_title ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Valor en Pesos Colombianos', 'sisman-suite' ); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sisman_x_axis_title"><?php esc_html_e( 'Título Eje X', 'sisman-suite' ); ?></label>
                </th>
                <td>
                    <input type="text" id="sisman_x_axis_title" name="sisman_x_axis_title" value="<?php echo esc_attr( $x_axis_title ); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sisman_number_format"><?php esc_html_e( 'Formato de Números', 'sisman-suite' ); ?></label>
                </th>
                <td>
                    <select id="sisman_number_format" name="sisman_number_format">
                        <option value="colombian" <?php selected( $number_format, 'colombian' ); ?>><?php esc_html_e( 'Colombiano (1.000.000)', 'sisman-suite' ); ?></option>
                        <option value="international" <?php selected( $number_format, 'international' ); ?>><?php esc_html_e( 'Internacional (1,000,000)', 'sisman-suite' ); ?></option>
                        <option value="abbreviated" <?php selected( $number_format, 'abbreviated' ); ?>><?php esc_html_e( 'Abreviado (1.2M)', 'sisman-suite' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sisman_chart_colors"><?php esc_html_e( 'Paleta de Colores', 'sisman-suite' ); ?></label>
                </th>
                <td>
                    <input type="text" id="sisman_chart_colors" name="sisman_chart_colors" value="<?php echo esc_attr( $chart_colors ); ?>" class="large-text">
                    <p class="description"><?php esc_html_e( 'Colores hexadecimales separados por coma', 'sisman-suite' ); ?></p>
                    <div id="sisman-color-preview" class="sisman-color-preview"></div>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Opciones de Visualización', 'sisman-suite' ); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="sisman_show_legend" value="yes" <?php checked( $show_legend, 'yes' ); ?>>
                            <?php esc_html_e( 'Mostrar leyenda', 'sisman-suite' ); ?>
                        </label>
                        <br>
                        <label>
                            <input type="checkbox" name="sisman_show_timeline" value="yes" <?php checked( $show_timeline, 'yes' ); ?>>
                            <?php esc_html_e( 'Mostrar línea de tiempo interactiva', 'sisman-suite' ); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
        </table>
    </div>

    <!-- Toolbar Configuration -->
    <div class="sisman-config-section">
        <h3>
            <span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>
            <?php esc_html_e( 'Barra de Herramientas', 'sisman-suite' ); ?>
        </h3>

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Mostrar Barra', 'sisman-suite' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="sisman_show_toolbar" value="yes" <?php checked( $show_toolbar, 'yes' ); ?>>
                        <?php esc_html_e( 'Mostrar barra de herramientas superior', 'sisman-suite' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Opciones a Mostrar', 'sisman-suite' ); ?></th>
                <td>
                    <fieldset class="sisman-toolbar-options">
                        <label>
                            <input type="checkbox" name="sisman_toolbar_detail" value="yes" <?php checked( $toolbar_detail, 'yes' ); ?>>
                            <?php esc_html_e( 'Detalle (info)', 'sisman-suite' ); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="sisman_toolbar_share" value="yes" <?php checked( $toolbar_share, 'yes' ); ?>>
                            <?php esc_html_e( 'Compartir', 'sisman-suite' ); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="sisman_toolbar_data" value="yes" <?php checked( $toolbar_data, 'yes' ); ?>>
                            <?php esc_html_e( 'Ver Datos', 'sisman-suite' ); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="sisman_toolbar_image" value="yes" <?php checked( $toolbar_image, 'yes' ); ?>>
                            <?php esc_html_e( 'Guardar Imagen', 'sisman-suite' ); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="sisman_toolbar_csv" value="yes" <?php checked( $toolbar_csv, 'yes' ); ?>>
                            <?php esc_html_e( 'Descargar CSV', 'sisman-suite' ); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
        </table>
    </div>

    <!-- Custom Query (Advanced) -->
    <div class="sisman-config-section sisman-collapsible">
        <div class="sisman-collapsible-header">
            <span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
            <strong><?php esc_html_e( 'Query Personalizada (Avanzado)', 'sisman-suite' ); ?></strong>
            <button type="button" class="button button-small sisman-toggle-section"><?php esc_html_e( 'Expandir', 'sisman-suite' ); ?></button>
        </div>
        <div class="sisman-collapsible-body" style="display:none;">
            <p class="description"><?php esc_html_e( 'Si necesita una consulta SQL personalizada, puede escribirla aquí. Esta consulta reemplazará la generada automáticamente. Debe retornar columnas "label" y "value".', 'sisman-suite' ); ?></p>
            <textarea id="sisman_custom_query" name="sisman_custom_query" class="large-text code" rows="6" placeholder="SELECT columna AS label, SUM(valor) AS value FROM tabla WHERE ... GROUP BY columna"><?php echo esc_textarea( $custom_query ); ?></textarea>
            <p class="description">
                <span class="dashicons dashicons-warning" aria-hidden="true" style="color:#dba617;"></span>
                <?php esc_html_e( 'Advertencia: Las consultas personalizadas no se validan automáticamente. Use con precaución.', 'sisman-suite' ); ?>
            </p>
        </div>
    </div>

    <!-- Hidden fields for JS to populate -->
    <input type="hidden" id="sisman-saved-group-column" value="<?php echo esc_attr( $group_column ); ?>">
    <input type="hidden" id="sisman-saved-value-column" value="<?php echo esc_attr( $value_column ); ?>">
    <input type="hidden" id="sisman-saved-filters" value="<?php echo esc_attr( wp_json_encode( $filters ) ); ?>">
</div>
