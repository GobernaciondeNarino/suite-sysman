<?php
namespace SysmanSuite;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Visualizer {

    private Database $database;

    private const ALLOWED_AGGREGATES = [ 'SUM', 'COUNT', 'AVG', 'MAX', 'MIN' ];
    private const ALLOWED_OPERATORS  = [ '=', '!=', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN' ];

    public function __construct( Database $database ) {
        $this->database = $database;

        add_action( 'init', [ $this, 'register_post_type' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
        add_action( 'save_post_sysman_chart', [ $this, 'save_meta' ] );
        add_shortcode( 'sysman_chart', [ $this, 'render_shortcode' ] );

        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );

        // Custom columns for chart list table
        add_filter( 'manage_sysman_chart_posts_columns', [ $this, 'set_chart_columns' ] );
        add_action( 'manage_sysman_chart_posts_custom_column', [ $this, 'render_chart_column' ], 10, 2 );

        // AJAX endpoint for admin chart preview (builds query from POST data)
        add_action( 'wp_ajax_sysman_preview_chart', [ $this, 'ajax_preview_chart' ] );
    }

    /**
     * Register the chart custom post type.
     */
    public function register_post_type(): void {
        register_post_type( 'sysman_chart', [
            'labels'       => [
                'name'               => __( 'Gráficos SYSMAN', 'sysman-suite' ),
                'singular_name'      => __( 'Gráfico SYSMAN', 'sysman-suite' ),
                'add_new'            => __( 'Nuevo Gráfico', 'sysman-suite' ),
                'add_new_item'       => __( 'Agregar Nuevo Gráfico', 'sysman-suite' ),
                'edit_item'          => __( 'Editar Gráfico', 'sysman-suite' ),
                'view_item'          => __( 'Ver Gráfico', 'sysman-suite' ),
                'all_items'          => __( 'Gráficos', 'sysman-suite' ),
                'search_items'       => __( 'Buscar Gráficos', 'sysman-suite' ),
                'not_found'          => __( 'No se encontraron gráficos', 'sysman-suite' ),
                'not_found_in_trash' => __( 'No se encontraron gráficos en la papelera', 'sysman-suite' ),
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => 'sysman-suite',
            'supports'     => [ 'title' ],
            'has_archive'  => false,
            'rewrite'      => false,
        ] );
    }

    /**
     * Add meta boxes for chart configuration.
     */
    public function add_meta_boxes(): void {
        add_meta_box(
            'sysman_chart_config',
            __( 'Configuración de la Gráfica', 'sysman-suite' ),
            [ $this, 'render_chart_config' ],
            'sysman_chart',
            'normal',
            'high'
        );

        add_meta_box(
            'sysman_chart_preview',
            __( 'Vista Previa', 'sysman-suite' ),
            [ $this, 'render_chart_preview' ],
            'sysman_chart',
            'normal',
            'default'
        );

        add_meta_box(
            'sysman_chart_shortcode',
            __( 'Shortcode', 'sysman-suite' ),
            [ $this, 'render_shortcode_info' ],
            'sysman_chart',
            'side',
            'high'
        );
    }

    /**
     * Render chart configuration metabox.
     */
    public function render_chart_config( \WP_Post $post ): void {
        include SYSMAN_SUITE_PATH . 'templates/admin/chart-config.php';
    }

    /**
     * Render chart preview metabox.
     */
    public function render_chart_preview( \WP_Post $post ): void {
        ?>
        <div class="sysman-preview-container">
            <div style="display:flex;gap:8px;align-items:center;margin-bottom:12px;">
                <button type="button" id="sysman-refresh-preview" class="button button-primary">
                    <span class="dashicons dashicons-update" aria-hidden="true" style="margin-top:3px;"></span>
                    <?php esc_html_e( 'Actualizar Vista Previa', 'sysman-suite' ); ?>
                </button>
                <span id="sysman-preview-status" style="color:var(--sysman-gray-600,#6c757d);font-size:12px;"></span>
            </div>
            <div id="sysman-chart-preview-area" class="sysman-chart-preview-area" style="min-height:350px;background:#fff;border:1px solid #e2e4e7;border-radius:6px;overflow:hidden;">
                <p class="sysman-preview-placeholder" style="text-align:center;padding:80px 20px;color:#999;">
                    <?php esc_html_e( 'Configure la gráfica y haga clic en "Actualizar Vista Previa" para ver el gráfico D3plus.', 'sysman-suite' ); ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Render shortcode info metabox.
     */
    public function render_shortcode_info( \WP_Post $post ): void {
        echo '<p>' . esc_html__( 'Usa este shortcode para mostrar el gráfico:', 'sysman-suite' ) . '</p>';
        echo '<code>[sysman_chart id="' . esc_attr( $post->ID ) . '"]</code>';
        echo '<p class="description">' . esc_html__( 'Copia y pega este shortcode en cualquier página o entrada.', 'sysman-suite' ) . '</p>';
    }

    /**
     * Save chart meta data.
     */
    public function save_meta( int $post_id ): void {
        if ( ! isset( $_POST['sysman_chart_nonce'] ) || ! wp_verify_nonce( $_POST['sysman_chart_nonce'], 'sysman_chart_save' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $fields = [
            'chart_type'              => 'sanitize_text_field',
            'data_source_mode'        => 'sanitize_text_field',
            'data_table'              => 'sanitize_text_field',
            'group_column'            => 'sanitize_text_field',
            'color_column'            => 'sanitize_text_field',
            'aggregate'               => 'sanitize_text_field',
            'orientation'             => 'sanitize_text_field',
            'vista_type'              => 'sanitize_text_field',
            'vista_dependencia'       => 'sanitize_text_field',
            'vista_compania'          => 'sanitize_text_field',
            'filter_anio'             => 'absint',
            'filter_mes'              => 'absint',
            'filter_destino'          => 'sanitize_text_field',
            'chart_height'            => 'absint',
            'chart_colors'            => 'sanitize_text_field',
            'show_legend'             => 'sanitize_text_field',
            'show_labels'             => 'sanitize_text_field',
            'number_format'           => 'sanitize_text_field',
            'y_axis_title'            => 'sanitize_text_field',
            'x_axis_title'            => 'sanitize_text_field',
            'tooltip_label_category'  => 'sanitize_text_field',
            'tooltip_label_value'     => 'sanitize_text_field',
            'tooltip_label_series'    => 'sanitize_text_field',
            'show_timeline'           => 'sanitize_text_field',
            'show_toolbar'            => 'sanitize_text_field',
            'toolbar_detail'          => 'sanitize_text_field',
            'toolbar_share'           => 'sanitize_text_field',
            'toolbar_data'            => 'sanitize_text_field',
            'toolbar_image'           => 'sanitize_text_field',
            'toolbar_csv'             => 'sanitize_text_field',
        ];

        foreach ( $fields as $field => $sanitize ) {
            if ( isset( $_POST[ "sysman_{$field}" ] ) ) {
                $value = call_user_func( $sanitize, $_POST[ "sysman_{$field}" ] );
                update_post_meta( $post_id, "_sysman_{$field}", $value );
            } else {
                delete_post_meta( $post_id, "_sysman_{$field}" );
            }
        }

        // Handle value_columns array (multiple Y columns)
        if ( isset( $_POST['sysman_value_columns'] ) && is_array( $_POST['sysman_value_columns'] ) ) {
            $value_columns = array_values( array_filter( array_map( 'sanitize_text_field', $_POST['sysman_value_columns'] ) ) );
            update_post_meta( $post_id, '_sysman_value_columns', $value_columns );
        } else {
            delete_post_meta( $post_id, '_sysman_value_columns' );
        }

        // Handle custom query (sanitize but allow SQL)
        if ( isset( $_POST['sysman_custom_query'] ) ) {
            $query = sanitize_textarea_field( $_POST['sysman_custom_query'] );
            if ( ! empty( $query ) ) {
                update_post_meta( $post_id, '_sysman_custom_query', $query );
            } else {
                delete_post_meta( $post_id, '_sysman_custom_query' );
            }
        }

        // Handle filters array
        if ( isset( $_POST['sysman_filters'] ) && is_array( $_POST['sysman_filters'] ) ) {
            $filters = [];
            foreach ( $_POST['sysman_filters'] as $filter ) {
                if ( ! empty( $filter['column'] ) && ! empty( $filter['operator'] ) ) {
                    $filters[] = [
                        'column'   => sanitize_text_field( $filter['column'] ),
                        'operator' => sanitize_text_field( $filter['operator'] ),
                        'value'    => sanitize_text_field( $filter['value'] ?? '' ),
                    ];
                }
            }
            update_post_meta( $post_id, '_sysman_filters', $filters );
        } else {
            delete_post_meta( $post_id, '_sysman_filters' );
        }
    }

    /**
     * Build WHERE clause parts from chart filters.
     */
    private function build_where_from_meta( int $chart_id, string $table ): array {
        global $wpdb;

        $where   = [];
        $prepare = [];

        $filter_anio = (int) get_post_meta( $chart_id, '_sysman_filter_anio', true );
        $filter_mes  = (int) get_post_meta( $chart_id, '_sysman_filter_mes', true );

        if ( $filter_anio > 0 ) {
            $where[]   = 'anio = %d';
            $prepare[] = $filter_anio;
        }
        if ( $filter_mes > 0 ) {
            $where[]   = 'mes = %d';
            $prepare[] = $filter_mes;
        }

        $filter_destino = get_post_meta( $chart_id, '_sysman_filter_destino', true );
        if ( ! empty( $filter_destino ) ) {
            $where[]   = 'destino = %s';
            $prepare[] = $filter_destino;
        }

        $filters = get_post_meta( $chart_id, '_sysman_filters', true ) ?: [];
        foreach ( $filters as $filter ) {
            $col = $filter['column'] ?? '';
            $op  = strtoupper( $filter['operator'] ?? '=' );
            $val = $filter['value'] ?? '';

            if ( ! $this->database->validate_column( $table, $col ) ) {
                continue;
            }
            if ( ! in_array( $op, self::ALLOWED_OPERATORS, true ) ) {
                continue;
            }

            if ( $op === 'LIKE' || $op === 'NOT LIKE' ) {
                $where[]   = "`{$col}` {$op} %s";
                $prepare[] = '%' . $wpdb->esc_like( $val ) . '%';
            } elseif ( $op === 'IN' || $op === 'NOT IN' ) {
                $values       = array_map( 'trim', explode( ',', $val ) );
                $placeholders = implode( ', ', array_fill( 0, count( $values ), '%s' ) );
                $where[]      = "`{$col}` {$op} ({$placeholders})";
                $prepare      = array_merge( $prepare, $values );
            } else {
                $where[]   = "`{$col}` {$op} %s";
                $prepare[] = $val;
            }
        }

        return [ $where, $prepare ];
    }

    /**
     * Build a secure SQL query from chart configuration.
     * Supports multiple Y-value columns via UNION ALL.
     */
    public function build_chart_query( int $chart_id ): ?string {
        global $wpdb;

        // Check for custom query first
        $custom_query = get_post_meta( $chart_id, '_sysman_custom_query', true );
        if ( ! empty( $custom_query ) ) {
            return $custom_query;
        }

        // Check data source mode
        $mode = get_post_meta( $chart_id, '_sysman_data_source_mode', true ) ?: 'table';
        if ( 'vista' === $mode ) {
            return $this->build_vista_query( $chart_id );
        }

        $table      = get_post_meta( $chart_id, '_sysman_data_table', true );
        $group_col  = get_post_meta( $chart_id, '_sysman_group_column', true );
        $value_cols = get_post_meta( $chart_id, '_sysman_value_columns', true ) ?: [];
        $color_col  = get_post_meta( $chart_id, '_sysman_color_column', true );
        $aggregate  = strtoupper( get_post_meta( $chart_id, '_sysman_aggregate', true ) ?: 'SUM' );

        if ( ! $this->database->validate_table( $table ) ) {
            return null;
        }
        if ( ! $this->database->validate_column( $table, $group_col ) ) {
            return null;
        }
        if ( ! in_array( $aggregate, self::ALLOWED_AGGREGATES, true ) ) {
            $aggregate = 'SUM';
        }

        // Validate value columns
        $valid_value_cols = [];
        foreach ( $value_cols as $vc ) {
            if ( $this->database->validate_column( $table, $vc ) ) {
                $valid_value_cols[] = $vc;
            }
        }
        if ( empty( $valid_value_cols ) ) {
            return null;
        }

        // Build WHERE
        [ $where, $prepare ] = $this->build_where_from_meta( $chart_id, $table );
        $where_clause = $where ? ' WHERE ' . implode( ' AND ', $where ) : '';

        // Multi-column Y: each column becomes a series via UNION ALL
        if ( count( $valid_value_cols ) > 1 ) {
            return $this->build_multi_y_query( $table, $group_col, $valid_value_cols, $aggregate, $where_clause, $prepare );
        }

        // Single Y column
        $value_col = $valid_value_cols[0];
        $has_color = ! empty( $color_col ) && $this->database->validate_column( $table, $color_col );

        if ( $has_color ) {
            $query = "SELECT `{$group_col}` AS label, {$aggregate}(`{$value_col}`) AS value, `{$color_col}` AS `group` FROM `{$table}`{$where_clause} GROUP BY `{$group_col}`, `{$color_col}` ORDER BY `{$group_col}`, value DESC LIMIT 500";
        } else {
            $query = "SELECT `{$group_col}` AS label, {$aggregate}(`{$value_col}`) AS value FROM `{$table}`{$where_clause} GROUP BY `{$group_col}` ORDER BY value DESC LIMIT 100";
        }

        if ( $prepare ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            return $wpdb->prepare( $query, ...$prepare );
        }

        return $query;
    }

    /**
     * Build UNION ALL query for multiple Y-value columns.
     * Each column becomes a named series (group).
     */
    private function build_multi_y_query( string $table, string $group_col, array $value_cols, string $aggregate, string $where_clause, array $prepare ): ?string {
        global $wpdb;

        $column_labels = [
            'apropiacioninicial'      => 'Apropiacion Inicial',
            'adicion'                 => 'Adicion',
            'reduccion'              => 'Reduccion',
            'credito'                => 'Credito',
            'contracredito'          => 'Contracredito',
            'aplazamiento'           => 'Aplazamiento',
            'desplazamiento'         => 'Desplazamiento',
            'apropiacionvigente'     => 'Apropiacion Vigente',
            'disponibilidades'       => 'Disponibilidades',
            'saldodisponible'        => 'Saldo Disponible',
            'compromisos'            => 'Compromisos',
            'disponibilidadesabiertas' => 'Disponibilidades Abiertas',
            'obligacion'             => 'Obligacion',
            'pagos'                  => 'Pagos',
            'obligacionesporpagar'   => 'Obligaciones por Pagar',
            'valordebito'            => 'Valor Debito',
            'valorcredito'           => 'Valor Credito',
            'saldoporejecutaresp'    => 'Saldo por Ejecutar',
            // Personal
            'salariobaseibc'         => 'Salario Base IBC',
            // Ingresos
            'apropiado'              => 'Apropiado',
            'modificaciones'         => 'Modificaciones',
            'totalpresupuesto'       => 'Total Presupuesto',
            'recaudosanteriores'     => 'Recaudos Anteriores',
            'recaudosmes'            => 'Recaudos Mes',
            'recaudosacumulados'     => 'Recaudos Acumulados',
            'porrecaudar'            => 'Por Recaudar',
            'porcrecaudado'          => '% Recaudado',
        ];

        $unions      = [];
        $all_prepare = [];

        foreach ( $value_cols as $col ) {
            $label = $column_labels[ $col ] ?? ucwords( str_replace( '_', ' ', $col ) );
            $sub   = "SELECT `{$group_col}` AS label, {$aggregate}(`{$col}`) AS value, '{$label}' AS `group` FROM `{$table}`{$where_clause} GROUP BY `{$group_col}`";
            $unions[] = "({$sub})";
            $all_prepare = array_merge( $all_prepare, $prepare );
        }

        $query = implode( ' UNION ALL ', $unions ) . " ORDER BY label, `group` LIMIT 1000";

        if ( $all_prepare ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            return $wpdb->prepare( $query, ...$all_prepare );
        }

        return $query;
    }

    /**
     * Build a Vista query (pre-built JOINs between plan_presupuestal and ejecucion_gastos).
     */
    private function build_vista_query( int $chart_id ): ?string {
        global $wpdb;

        $vista_type    = get_post_meta( $chart_id, '_sysman_vista_type', true ) ?: 'ejecucion_dependencia';
        $dependencia   = get_post_meta( $chart_id, '_sysman_vista_dependencia', true ) ?: '';
        $compania      = get_post_meta( $chart_id, '_sysman_vista_compania', true ) ?: '001';
        $filter_anio   = (int) get_post_meta( $chart_id, '_sysman_filter_anio', true );
        $filter_mes    = (int) get_post_meta( $chart_id, '_sysman_filter_mes', true );
        $aggregate     = strtoupper( get_post_meta( $chart_id, '_sysman_aggregate', true ) ?: 'SUM' );
        $value_cols    = get_post_meta( $chart_id, '_sysman_value_columns', true ) ?: [];

        if ( ! in_array( $aggregate, self::ALLOWED_AGGREGATES, true ) ) {
            $aggregate = 'SUM';
        }

        $pp_table = $wpdb->prefix . 'sysman_plan_presupuestal';
        $eg_table = $wpdb->prefix . 'sysman_ejecucion_gastos';

        $allowed_vista_cols = [
            'apropiacioninicial', 'adicion', 'reduccion', 'credito', 'contracredito',
            'aplazamiento', 'desplazamiento', 'apropiacionvigente', 'disponibilidades',
            'saldodisponible', 'compromisos', 'disponibilidadesabiertas',
            'obligacion', 'pagos', 'obligacionesporpagar',
        ];

        $valid_cols = [];
        foreach ( $value_cols as $vc ) {
            if ( in_array( $vc, $allowed_vista_cols, true ) ) {
                $valid_cols[] = $vc;
            }
        }

        if ( empty( $valid_cols ) ) {
            $valid_cols = [ 'apropiacionvigente', 'compromisos', 'pagos' ];
        }

        $where   = [ "pp.movimiento = 'SI'", 'pp.compania = %s' ];
        $prepare = [ $compania ];

        if ( ! empty( $dependencia ) ) {
            $where[]   = 'pp.nombredependencia = %s';
            $prepare[] = $dependencia;
        }

        if ( $filter_anio > 0 ) {
            $where[]   = 'pp.anio = %d';
            $prepare[] = $filter_anio;
        }

        if ( $filter_mes > 0 ) {
            $where[]   = 'pp.mes = %d';
            $prepare[] = $filter_mes;
        }

        $where_clause = ' WHERE ' . implode( ' AND ', $where );

        $join = "FROM `{$pp_table}` pp INNER JOIN `{$eg_table}` eg "
              . 'ON pp.codigo = eg.codigocuenta AND pp.compania = eg.compania AND pp.anio = eg.anio AND pp.mes = eg.mes';

        $column_labels = [
            'apropiacioninicial'      => 'Apropiacion Inicial',
            'adicion'                 => 'Adicion',
            'reduccion'              => 'Reduccion',
            'credito'                => 'Credito',
            'contracredito'          => 'Contracredito',
            'aplazamiento'           => 'Aplazamiento',
            'desplazamiento'         => 'Desplazamiento',
            'apropiacionvigente'     => 'Apropiacion Vigente',
            'disponibilidades'       => 'Disponibilidades',
            'saldodisponible'        => 'Saldo Disponible',
            'compromisos'            => 'Compromisos',
            'disponibilidadesabiertas' => 'Disponibilidades Abiertas',
            'obligacion'             => 'Obligacion',
            'pagos'                  => 'Pagos',
            'obligacionesporpagar'   => 'Obligaciones por Pagar',
        ];

        if ( count( $valid_cols ) > 1 ) {
            $unions      = [];
            $all_prepare = [];

            foreach ( $valid_cols as $col ) {
                $label     = $column_labels[ $col ] ?? ucwords( str_replace( '_', ' ', $col ) );
                $sub       = "SELECT pp.nombre AS label, {$aggregate}(eg.`{$col}`) AS value, '{$label}' AS `group` {$join}{$where_clause} GROUP BY pp.nombre";
                $unions[]  = "({$sub})";
                $all_prepare = array_merge( $all_prepare, $prepare );
            }

            $query = implode( ' UNION ALL ', $unions ) . " ORDER BY label, `group` LIMIT 1000";

            if ( $all_prepare ) {
                return $wpdb->prepare( $query, ...$all_prepare );
            }
            return $query;
        }

        $col   = $valid_cols[0];
        $query = "SELECT pp.nombre AS label, {$aggregate}(eg.`{$col}`) AS value {$join}{$where_clause} GROUP BY pp.nombre ORDER BY value DESC LIMIT 100";

        if ( $prepare ) {
            return $wpdb->prepare( $query, ...$prepare );
        }
        return $query;
    }

    /**
     * Get distinct dependencias from plan_presupuestal for a given compania/anio/mes.
     */
    public function get_dependencias( string $compania = '001', int $anio = 0, int $mes = 0 ): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sysman_plan_presupuestal';

        $where   = [ 'compania = %s', "nombredependencia != ''" ];
        $prepare = [ $compania ];

        if ( $anio > 0 ) {
            $where[]   = 'anio = %d';
            $prepare[] = $anio;
        }
        if ( $mes > 0 ) {
            $where[]   = 'mes = %d';
            $prepare[] = $mes;
        }

        $where_clause = ' WHERE ' . implode( ' AND ', $where );
        $query = "SELECT DISTINCT nombredependencia FROM `{$table}`{$where_clause} ORDER BY nombredependencia";

        return $wpdb->get_col( $wpdb->prepare( $query, ...$prepare ) ) ?: [];
    }

    /**
     * Get chart data.
     */
    public function get_chart_data( int $chart_id ): array {
        global $wpdb;

        $query = $this->build_chart_query( $chart_id );
        if ( ! $query ) {
            return [];
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_results( $query, ARRAY_A );

        return $results ?: [];
    }

    /**
     * Get all chart meta for frontend rendering.
     */
    public function get_chart_meta( int $chart_id ): array {
        return [
            'title'          => get_the_title( $chart_id ),
            'chart_type'     => get_post_meta( $chart_id, '_sysman_chart_type', true ) ?: 'bar',
            'chart_height'   => (int) ( get_post_meta( $chart_id, '_sysman_chart_height', true ) ?: 400 ),
            'chart_colors'   => get_post_meta( $chart_id, '_sysman_chart_colors', true ) ?: '#844e80,#ff7300,#ffc53b,#3eba6a,#0080c3,#e74c3c,#9b59b6,#1abc9c',
            'show_legend'    => get_post_meta( $chart_id, '_sysman_show_legend', true ) === 'yes',
            'show_labels'    => get_post_meta( $chart_id, '_sysman_show_labels', true ) !== 'no',
            'number_format'  => get_post_meta( $chart_id, '_sysman_number_format', true ) ?: 'colombian',
            'y_axis_title'   => get_post_meta( $chart_id, '_sysman_y_axis_title', true ) ?: '',
            'x_axis_title'   => get_post_meta( $chart_id, '_sysman_x_axis_title', true ) ?: '',
            'has_groups'              => count( get_post_meta( $chart_id, '_sysman_value_columns', true ) ?: [] ) > 1 || ! empty( get_post_meta( $chart_id, '_sysman_color_column', true ) ),
            'tooltip_label_category' => get_post_meta( $chart_id, '_sysman_tooltip_label_category', true ) ?: '',
            'tooltip_label_value'    => get_post_meta( $chart_id, '_sysman_tooltip_label_value', true ) ?: '',
            'tooltip_label_series'   => get_post_meta( $chart_id, '_sysman_tooltip_label_series', true ) ?: '',
            'show_timeline'  => get_post_meta( $chart_id, '_sysman_show_timeline', true ) === 'yes',
            'show_toolbar'   => get_post_meta( $chart_id, '_sysman_show_toolbar', true ) !== '',
            'toolbar_detail' => get_post_meta( $chart_id, '_sysman_toolbar_detail', true ) === 'yes',
            'toolbar_share'  => get_post_meta( $chart_id, '_sysman_toolbar_share', true ) === 'yes',
            'toolbar_data'   => get_post_meta( $chart_id, '_sysman_toolbar_data', true ) === 'yes',
            'toolbar_image'  => get_post_meta( $chart_id, '_sysman_toolbar_image', true ) === 'yes',
            'toolbar_csv'    => get_post_meta( $chart_id, '_sysman_toolbar_csv', true ) === 'yes',
        ];
    }

    /**
     * Render the chart shortcode.
     */
    public function render_shortcode( array $atts ): string {
        $atts = shortcode_atts( [ 'id' => 0 ], $atts, 'sysman_chart' );
        $id   = absint( $atts['id'] );

        if ( ! $id || 'sysman_chart' !== get_post_type( $id ) ) {
            return '<p class="sysman-error">' . esc_html__( 'Gráfico no encontrado.', 'sysman-suite' ) . '</p>';
        }

        $post = get_post( $id );
        if ( ! $post || 'publish' !== $post->post_status ) {
            return '';
        }

        ob_start();
        include SYSMAN_SUITE_PATH . 'templates/frontend/chart.php';
        return ob_get_clean();
    }

    /**
     * Define custom columns for chart list table.
     */
    public function set_chart_columns( array $columns ): array {
        $new = [];
        $new['cb']             = $columns['cb'];
        $new['title']          = __( 'Nombre', 'sysman-suite' );
        $new['chart_type']     = __( 'Tipo', 'sysman-suite' );
        $new['chart_shortcode'] = __( 'Shortcode', 'sysman-suite' );
        $new['date']           = __( 'Fecha', 'sysman-suite' );
        return $new;
    }

    /**
     * Render custom column content for chart list table.
     */
    public function render_chart_column( string $column, int $post_id ): void {
        switch ( $column ) {
            case 'chart_type':
                $type   = get_post_meta( $post_id, '_sysman_chart_type', true ) ?: 'bar';
                $labels = [
                    'bar'            => __( 'Barras', 'sysman-suite' ),
                    'horizontal_bar' => __( 'Barras Horizontales', 'sysman-suite' ),
                    'line'           => __( 'Líneas', 'sysman-suite' ),
                    'area'           => __( 'Área', 'sysman-suite' ),
                    'stacked_area'   => __( 'Área Apilada', 'sysman-suite' ),
                    'pie'            => __( 'Pie / Torta', 'sysman-suite' ),
                    'donut'          => __( 'Donut', 'sysman-suite' ),
                    'treemap'        => __( 'Treemap', 'sysman-suite' ),
                    'stacked_bar'    => __( 'Barras Apiladas', 'sysman-suite' ),
                    'grouped_bar'    => __( 'Barras Agrupadas', 'sysman-suite' ),
                    'radar'          => __( 'Radar', 'sysman-suite' ),
                ];
                $icons = [
                    'bar' => 'chart-bar', 'horizontal_bar' => 'chart-bar',
                    'line' => 'chart-line', 'area' => 'chart-area', 'stacked_area' => 'chart-area',
                    'pie' => 'chart-pie', 'donut' => 'chart-pie', 'treemap' => 'screenoptions',
                    'stacked_bar' => 'chart-bar', 'grouped_bar' => 'chart-bar',
                    'radar' => 'performance',
                ];
                $icon = $icons[ $type ] ?? 'chart-bar';
                echo '<span class="dashicons dashicons-' . esc_attr( $icon ) . '" style="color:var(--sysman-primary,#1a5632);vertical-align:middle;margin-right:4px;"></span>';
                echo esc_html( $labels[ $type ] ?? $type );
                break;

            case 'chart_shortcode':
                echo '<code style="background:#f0f0f0;padding:3px 8px;border-radius:3px;font-size:12px;user-select:all;">[sysman_chart id="' . esc_attr( $post_id ) . '"]</code>';
                break;
        }
    }

    /**
     * AJAX handler for admin chart preview.
     * Builds query from POST data without requiring the post to be saved.
     */
    public function ajax_preview_chart(): void {
        check_ajax_referer( 'sysman_chart_preview', 'preview_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Sin permisos' ], 403 );
        }

        global $wpdb;

        $custom_query    = sanitize_textarea_field( $_POST['custom_query'] ?? '' );
        $data_source_mode = sanitize_text_field( $_POST['data_source_mode'] ?? 'table' );

        if ( ! empty( $custom_query ) ) {
            $query = $custom_query;
        } elseif ( 'vista' === $data_source_mode ) {
            $query = $this->build_vista_preview_query( $_POST );
        } else {
            $table     = sanitize_text_field( $_POST['data_table'] ?? '' );
            $group_col = sanitize_text_field( $_POST['group_column'] ?? '' );
            $aggregate = strtoupper( sanitize_text_field( $_POST['aggregate'] ?? 'SUM' ) );

            if ( ! $this->database->validate_table( $table ) ) {
                wp_send_json_error( [ 'message' => 'Tabla no válida' ] );
            }
            if ( ! $this->database->validate_column( $table, $group_col ) ) {
                wp_send_json_error( [ 'message' => 'Columna de agrupación no válida' ] );
            }
            if ( ! in_array( $aggregate, self::ALLOWED_AGGREGATES, true ) ) {
                $aggregate = 'SUM';
            }

            $raw_value_cols = $_POST['value_columns'] ?? [];
            if ( ! is_array( $raw_value_cols ) ) {
                $raw_value_cols = [ $raw_value_cols ];
            }
            $value_cols = [];
            foreach ( $raw_value_cols as $vc ) {
                $vc = sanitize_text_field( $vc );
                if ( $vc && $this->database->validate_column( $table, $vc ) ) {
                    $value_cols[] = $vc;
                }
            }
            if ( empty( $value_cols ) ) {
                wp_send_json_error( [ 'message' => 'Seleccione al menos una columna de valor' ] );
            }

            $where   = [];
            $prepare = [];

            $filter_anio = absint( $_POST['filter_anio'] ?? 0 );
            $filter_mes  = absint( $_POST['filter_mes'] ?? 0 );
            if ( $filter_anio > 0 ) {
                $where[]   = 'anio = %d';
                $prepare[] = $filter_anio;
            }
            if ( $filter_mes > 0 ) {
                $where[]   = 'mes = %d';
                $prepare[] = $filter_mes;
            }

            $filter_destino = sanitize_text_field( $_POST['filter_destino'] ?? '' );
            if ( ! empty( $filter_destino ) ) {
                $where[]   = 'destino = %s';
                $prepare[] = $filter_destino;
            }

            $where_clause = $where ? ' WHERE ' . implode( ' AND ', $where ) : '';

            if ( count( $value_cols ) > 1 ) {
                $query = $this->build_multi_y_query( $table, $group_col, $value_cols, $aggregate, $where_clause, $prepare );
            } else {
                $value_col = $value_cols[0];
                $color_col = sanitize_text_field( $_POST['color_column'] ?? '' );
                $has_color = ! empty( $color_col ) && $this->database->validate_column( $table, $color_col );

                if ( $has_color ) {
                    $query = "SELECT `{$group_col}` AS label, {$aggregate}(`{$value_col}`) AS value, `{$color_col}` AS `group` FROM `{$table}`{$where_clause} GROUP BY `{$group_col}`, `{$color_col}` ORDER BY `{$group_col}`, value DESC LIMIT 500";
                } else {
                    $query = "SELECT `{$group_col}` AS label, {$aggregate}(`{$value_col}`) AS value FROM `{$table}`{$where_clause} GROUP BY `{$group_col}` ORDER BY value DESC LIMIT 100";
                }

                if ( $prepare ) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                    $query = $wpdb->prepare( $query, ...$prepare );
                }
            }
        }

        if ( ! $query ) {
            wp_send_json_error( [ 'message' => 'Error al construir la consulta' ] );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_results( $query, ARRAY_A ) ?: [];

        $meta = [
            'chart_type'   => sanitize_text_field( $_POST['chart_type'] ?? 'bar' ),
            'chart_height' => absint( $_POST['chart_height'] ?? 400 ),
            'chart_colors' => sanitize_text_field( $_POST['chart_colors'] ?? '#844e80,#ff7300,#ffc53b,#3eba6a,#0080c3,#e74c3c,#9b59b6,#1abc9c' ),
            'show_legend'  => ( $_POST['show_legend'] ?? '' ) === 'yes',
            'y_axis_title' => sanitize_text_field( $_POST['y_axis_title'] ?? '' ),
            'x_axis_title' => sanitize_text_field( $_POST['x_axis_title'] ?? '' ),
        ];

        wp_send_json_success( [ 'data' => $results, 'meta' => $meta ] );
    }

    /**
     * Build a Vista query from AJAX POST data (preview mode).
     */
    private function build_vista_preview_query( array $post ): ?string {
        global $wpdb;

        $dependencia = sanitize_text_field( $post['vista_dependencia'] ?? '' );
        $compania    = sanitize_text_field( $post['vista_compania'] ?? '001' );
        $filter_anio = absint( $post['filter_anio'] ?? 0 );
        $filter_mes  = absint( $post['filter_mes'] ?? 0 );
        $aggregate   = strtoupper( sanitize_text_field( $post['aggregate'] ?? 'SUM' ) );

        if ( ! in_array( $aggregate, self::ALLOWED_AGGREGATES, true ) ) {
            $aggregate = 'SUM';
        }

        $raw_value_cols = $post['value_columns'] ?? [];
        if ( ! is_array( $raw_value_cols ) ) {
            $raw_value_cols = [ $raw_value_cols ];
        }

        $allowed = [
            'apropiacioninicial', 'adicion', 'reduccion', 'credito', 'contracredito',
            'aplazamiento', 'desplazamiento', 'apropiacionvigente', 'disponibilidades',
            'saldodisponible', 'compromisos', 'disponibilidadesabiertas',
            'obligacion', 'pagos', 'obligacionesporpagar',
        ];

        $valid_cols = [];
        foreach ( $raw_value_cols as $vc ) {
            $vc = sanitize_text_field( $vc );
            if ( in_array( $vc, $allowed, true ) ) {
                $valid_cols[] = $vc;
            }
        }
        if ( empty( $valid_cols ) ) {
            $valid_cols = [ 'apropiacionvigente', 'compromisos', 'pagos' ];
        }

        $pp_table = $wpdb->prefix . 'sysman_plan_presupuestal';
        $eg_table = $wpdb->prefix . 'sysman_ejecucion_gastos';

        $where   = [ "pp.movimiento = 'SI'", 'pp.compania = %s' ];
        $prepare = [ $compania ];

        if ( ! empty( $dependencia ) ) {
            $where[]   = 'pp.nombredependencia = %s';
            $prepare[] = $dependencia;
        }
        if ( $filter_anio > 0 ) {
            $where[]   = 'pp.anio = %d';
            $prepare[] = $filter_anio;
        }
        if ( $filter_mes > 0 ) {
            $where[]   = 'pp.mes = %d';
            $prepare[] = $filter_mes;
        }

        $where_clause = ' WHERE ' . implode( ' AND ', $where );
        $join = "FROM `{$pp_table}` pp INNER JOIN `{$eg_table}` eg "
              . 'ON pp.codigo = eg.codigocuenta AND pp.compania = eg.compania AND pp.anio = eg.anio AND pp.mes = eg.mes';

        $column_labels = [
            'apropiacioninicial'      => 'Apropiacion Inicial',
            'adicion'                 => 'Adicion',
            'reduccion'              => 'Reduccion',
            'credito'                => 'Credito',
            'contracredito'          => 'Contracredito',
            'aplazamiento'           => 'Aplazamiento',
            'desplazamiento'         => 'Desplazamiento',
            'apropiacionvigente'     => 'Apropiacion Vigente',
            'disponibilidades'       => 'Disponibilidades',
            'saldodisponible'        => 'Saldo Disponible',
            'compromisos'            => 'Compromisos',
            'disponibilidadesabiertas' => 'Disponibilidades Abiertas',
            'obligacion'             => 'Obligacion',
            'pagos'                  => 'Pagos',
            'obligacionesporpagar'   => 'Obligaciones por Pagar',
        ];

        if ( count( $valid_cols ) > 1 ) {
            $unions      = [];
            $all_prepare = [];

            foreach ( $valid_cols as $col ) {
                $label     = $column_labels[ $col ] ?? ucwords( str_replace( '_', ' ', $col ) );
                $sub       = "SELECT pp.nombre AS label, {$aggregate}(eg.`{$col}`) AS value, '{$label}' AS `group` {$join}{$where_clause} GROUP BY pp.nombre";
                $unions[]  = "({$sub})";
                $all_prepare = array_merge( $all_prepare, $prepare );
            }

            $query = implode( ' UNION ALL ', $unions ) . " ORDER BY label, `group` LIMIT 1000";
            return $all_prepare ? $wpdb->prepare( $query, ...$all_prepare ) : $query;
        }

        $col   = $valid_cols[0];
        $query = "SELECT pp.nombre AS label, {$aggregate}(eg.`{$col}`) AS value {$join}{$where_clause} GROUP BY pp.nombre ORDER BY value DESC LIMIT 100";
        return $prepare ? $wpdb->prepare( $query, ...$prepare ) : $query;
    }

    /**
     * Enqueue frontend assets (only when shortcode is present).
     */
    public function enqueue_frontend_assets(): void {
        global $post;

        if ( ! $post || ! has_shortcode( $post->post_content, 'sysman_chart' ) ) {
            return;
        }

        // D3.js and D3plus (URLs from settings)
        $d3_url     = get_option( 'sysman_d3_cdn_url', 'https://d3js.org/d3.v5.min.js' );
        $d3plus_url = get_option( 'sysman_d3plus_cdn_url', 'https://cdn.jsdelivr.net/npm/d3plus@2.0.2/build/d3plus.full.min.js' );
        wp_enqueue_script( 'd3-v5', $d3_url, [], '5.16.0', true );
        wp_enqueue_script( 'd3plus', $d3plus_url, [ 'd3-v5' ], '2.0.0', true );

        wp_enqueue_style(
            'sysman-frontend',
            SYSMAN_SUITE_URL . 'assets/css/frontend.css',
            [],
            SYSMAN_SUITE_VERSION
        );

        wp_enqueue_script(
            'sysman-frontend',
            SYSMAN_SUITE_URL . 'assets/js/frontend.js',
            [ 'd3-v5', 'd3plus' ],
            SYSMAN_SUITE_VERSION,
            true
        );
    }
}
