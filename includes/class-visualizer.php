<?php
namespace SysmanSuite;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Visualizer {

    private Database $database;

    private const ALLOWED_AGGREGATES = [ 'SUM', 'COUNT', 'AVG', 'MAX', 'MIN' ];
    private const ALLOWED_OPERATORS  = [ '=', '!=', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN' ];

    private const TABLES_REQUIRE_MOVIMIENTO = [
        'sysman_plan_presupuestal',
        'sysman_ejecucion_gastos',
        'sysman_ejecucion_ingresos',
    ];

    /** Columns allowed as Y-values in Vista (JOIN) queries. */
    private const ALLOWED_VISTA_COLS = [
        'apropiacioninicial', 'adicion', 'reduccion', 'credito', 'contracredito',
        'aplazamiento', 'desplazamiento', 'apropiacionvigente', 'disponibilidades',
        'saldodisponible', 'compromisos', 'disponibilidadesabiertas',
        'obligacion', 'pagos', 'obligacionesporpagar',
    ];

    /** Human-readable labels for value columns (series names). */
    private const COLUMN_LABELS = [
        'apropiacioninicial'       => 'Apropiacion Inicial',
        'adicion'                  => 'Adicion',
        'reduccion'                => 'Reduccion',
        'credito'                  => 'Credito',
        'contracredito'            => 'Contracredito',
        'aplazamiento'             => 'Aplazamiento',
        'desplazamiento'           => 'Desplazamiento',
        'apropiacionvigente'       => 'Apropiacion Vigente',
        'disponibilidades'         => 'Disponibilidades',
        'saldodisponible'          => 'Saldo Disponible',
        'compromisos'              => 'Compromisos',
        'disponibilidadesabiertas' => 'Disponibilidades Abiertas',
        'obligacion'               => 'Obligacion',
        'pagos'                    => 'Pagos',
        'obligacionesporpagar'     => 'Obligaciones por Pagar',
        'valordebito'              => 'Valor Debito',
        'valorcredito'             => 'Valor Credito',
        'saldoporejecutaresp'      => 'Saldo por Ejecutar',
        // Personal
        'salariobaseibc'           => 'Salario Base IBC',
        // Ingresos
        'apropiado'                => 'Apropiado',
        'modificaciones'           => 'Modificaciones',
        'totalpresupuesto'         => 'Total Presupuesto',
        'recaudosanteriores'       => 'Recaudos Anteriores',
        'recaudosmes'              => 'Recaudos Mes',
        'recaudosacumulados'       => 'Recaudos Acumulados',
        'porrecaudar'              => 'Por Recaudar',
        'porcrecaudado'            => '% Recaudado',
    ];

    /** Keywords that must never appear in a custom chart query. */
    private const FORBIDDEN_QUERY_TOKENS = [
        'insert', 'update', 'delete', 'drop', 'alter', 'create', 'truncate',
        'replace', 'grant', 'revoke', 'rename', 'call', 'handler', 'load_file',
        'outfile', 'dumpfile', 'information_schema', 'performance_schema',
        'benchmark', 'sleep', 'user()', 'load data',
    ];

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
            // Only administrators may manage charts: a chart can hold a custom
            // SQL query, so the default 'post' capabilities (any Author via
            // post-new.php?post_type=sysman_chart) are not acceptable.
            'capabilities' => self::admin_only_caps(),
        ] );
    }

    /**
     * Capability map that restricts a CPT to users with manage_options.
     */
    public static function admin_only_caps(): array {
        return array_fill_keys( [
            'edit_post', 'read_post', 'delete_post',
            'edit_posts', 'edit_others_posts', 'delete_posts',
            'delete_others_posts', 'delete_published_posts', 'delete_private_posts',
            'edit_published_posts', 'edit_private_posts',
            'publish_posts', 'read_private_posts', 'create_posts',
        ], 'manage_options' );
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

        // Sits in the side column with low priority so it renders directly
        // below the "Publicar" box.
        add_meta_box(
            'sysman_chart_data',
            __( 'Datos a Graficar', 'sysman-suite' ),
            [ $this, 'render_chart_data_panel' ],
            'sysman_chart',
            'side',
            'low'
        );
    }

    /**
     * Render the "Datos a Graficar" side panel: a live summary of the rows the
     * current configuration returns, so the editor can confirm the data before
     * publishing.
     */
    public function render_chart_data_panel( \WP_Post $post ): void {
        ?>
        <div class="sysman-data-panel" id="sysman-data-panel">
            <div class="sysman-data-panel__toolbar">
                <button type="button" class="button button-secondary sysman-data-panel__refresh" id="sysman-refresh-data">
                    <span class="dashicons dashicons-update" aria-hidden="true"></span>
                    <?php esc_html_e( 'Consultar datos', 'sysman-suite' ); ?>
                </button>
            </div>

            <div class="sysman-data-panel__summary" id="sysman-data-summary" hidden>
                <div class="sysman-data-stat">
                    <span class="sysman-data-stat__value" id="sysman-data-rows">0</span>
                    <span class="sysman-data-stat__label"><?php esc_html_e( 'Registros', 'sysman-suite' ); ?></span>
                </div>
                <div class="sysman-data-stat">
                    <span class="sysman-data-stat__value" id="sysman-data-series">0</span>
                    <span class="sysman-data-stat__label"><?php esc_html_e( 'Series', 'sysman-suite' ); ?></span>
                </div>
                <div class="sysman-data-stat">
                    <span class="sysman-data-stat__value" id="sysman-data-total">—</span>
                    <span class="sysman-data-stat__label"><?php esc_html_e( 'Total', 'sysman-suite' ); ?></span>
                </div>
            </div>

            <p class="sysman-data-panel__source" id="sysman-data-source" hidden></p>

            <div class="sysman-data-panel__table-wrap" id="sysman-data-table-wrap" hidden>
                <table class="sysman-data-panel__table">
                    <thead id="sysman-data-thead"></thead>
                    <tbody id="sysman-data-tbody"></tbody>
                </table>
            </div>

            <p class="sysman-data-panel__more" id="sysman-data-more" hidden></p>

            <p class="sysman-data-panel__msg" id="sysman-data-msg">
                <?php esc_html_e( 'Configure la fuente de datos y pulse «Consultar datos» para ver aquí los registros que alimentarán la gráfica.', 'sysman-suite' ); ?>
            </p>
        </div>
        <?php
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

        // The Tablas and Vistas panels each submit their own fields. Persist the
        // set belonging to the active mode into the canonical meta keys, so the
        // query builders keep reading a single source of truth.
        $is_vista       = 'vista' === sanitize_text_field( $_POST['sysman_data_source_mode'] ?? 'table' );
        $columns_field  = $is_vista ? 'sysman_vista_value_columns' : 'sysman_value_columns';
        $aggregate_field = $is_vista ? 'sysman_vista_aggregate' : 'sysman_aggregate';

        if ( isset( $_POST[ $columns_field ] ) && is_array( $_POST[ $columns_field ] ) ) {
            $value_columns = array_values( array_filter( array_map( 'sanitize_text_field', $_POST[ $columns_field ] ) ) );
            update_post_meta( $post_id, '_sysman_value_columns', $value_columns );
        } else {
            delete_post_meta( $post_id, '_sysman_value_columns' );
        }

        if ( isset( $_POST[ $aggregate_field ] ) ) {
            update_post_meta( $post_id, '_sysman_aggregate', sanitize_text_field( $_POST[ $aggregate_field ] ) );
        }

        // A Vista never groups by a table column: clear leftovers from the
        // Tablas panel so the saved config matches what the user sees.
        if ( $is_vista ) {
            delete_post_meta( $post_id, '_sysman_color_column' );
        }

        // Handle custom query. Only administrators may store SQL, and only
        // queries that pass validate_custom_query() are accepted.
        if ( isset( $_POST['sysman_custom_query'] ) && current_user_can( 'manage_options' ) ) {
            $query = sanitize_textarea_field( wp_unslash( $_POST['sysman_custom_query'] ) );
            if ( ! empty( $query ) && null !== $this->validate_custom_query( $query ) ) {
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
     * Get the display label for a value column.
     */
    private function column_label( string $col ): string {
        return self::COLUMN_LABELS[ $col ] ?? ucwords( str_replace( '_', ' ', $col ) );
    }

    /**
     * Validate a user-supplied custom chart query.
     *
     * Only a single read-only SELECT over the plugin (or integration) tables
     * is allowed. Returns the trimmed query on success or null when the query
     * must be rejected. This runs at execution time, so legacy stored queries
     * are also covered.
     */
    public function validate_custom_query( string $query ): ?string {
        global $wpdb;

        $query = trim( $query );
        if ( '' === $query ) {
            return null;
        }

        // Single statement, starting with SELECT, without comments.
        if ( ! preg_match( '/^select\s/i', $query )
            || str_contains( $query, ';' )
            || preg_match( '/(--|#|\/\*)/', $query ) ) {
            return null;
        }

        foreach ( self::FORBIDDEN_QUERY_TOKENS as $token ) {
            if ( preg_match( '/\b' . preg_quote( $token, '/' ) . '/i', $query ) ) {
                return null;
            }
        }

        // Every prefixed table referenced must belong to the allowed set.
        $allowed_tables = array_map( 'strtolower', array_keys( $this->database->get_available_tables() ) );
        $allowed_tables[] = strtolower( $wpdb->prefix . 'bpid_suite_contratos' );
        $allowed_tables[] = strtolower( $wpdb->prefix . 'secop_contracts' );

        $prefix_pattern = preg_quote( $wpdb->prefix, '/' );
        if ( ! preg_match_all( '/' . $prefix_pattern . '[a-z0-9_]+/i', $query, $matches ) ) {
            return null; // Must reference at least one plugin table.
        }

        foreach ( $matches[0] as $referenced ) {
            if ( ! in_array( strtolower( $referenced ), $allowed_tables, true ) ) {
                return null;
            }
        }

        // Cap the result size if the author did not.
        if ( ! preg_match( '/\blimit\s+\d+/i', $query ) ) {
            $query .= ' LIMIT 1000';
        }

        return $query;
    }

    private function requires_movimiento_filter( string $table ): bool {
        foreach ( self::TABLES_REQUIRE_MOVIMIENTO as $suffix ) {
            if ( str_ends_with( $table, $suffix ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Build WHERE clause parts from chart filters.
     */
    private function build_where_from_meta( int $chart_id, string $table ): array {
        global $wpdb;

        $where   = [];
        $prepare = [];

        if ( $this->requires_movimiento_filter( $table ) ) {
            $where[] = "movimiento = 'SI'";
        }

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

        // Check for custom query first (re-validated on every execution).
        $custom_query = get_post_meta( $chart_id, '_sysman_custom_query', true );
        if ( ! empty( $custom_query ) ) {
            return $this->validate_custom_query( $custom_query );
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

        $unions      = [];
        $all_prepare = [];

        foreach ( $value_cols as $col ) {
            $label = $this->column_label( $col );
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
        return $this->compose_vista_query( [
            'dependencia' => get_post_meta( $chart_id, '_sysman_vista_dependencia', true ) ?: '',
            'compania'    => get_post_meta( $chart_id, '_sysman_vista_compania', true ) ?: '001',
            'anio'        => (int) get_post_meta( $chart_id, '_sysman_filter_anio', true ),
            'mes'         => (int) get_post_meta( $chart_id, '_sysman_filter_mes', true ),
            'aggregate'   => strtoupper( get_post_meta( $chart_id, '_sysman_aggregate', true ) ?: 'SUM' ),
            'value_cols'  => get_post_meta( $chart_id, '_sysman_value_columns', true ) ?: [],
        ] );
    }

    /**
     * Compose the Vista query (JOIN plan_presupuestal ↔ ejecucion_gastos)
     * from a normalized config. Shared by saved charts and admin previews.
     */
    private function compose_vista_query( array $config ): ?string {
        global $wpdb;

        $aggregate = $config['aggregate'];
        if ( ! in_array( $aggregate, self::ALLOWED_AGGREGATES, true ) ) {
            $aggregate = 'SUM';
        }

        $valid_cols = array_values( array_intersect( $config['value_cols'], self::ALLOWED_VISTA_COLS ) );
        if ( empty( $valid_cols ) ) {
            $valid_cols = [ 'apropiacionvigente', 'compromisos', 'pagos' ];
        }

        $pp_table = $wpdb->prefix . 'sysman_plan_presupuestal';
        $eg_table = $wpdb->prefix . 'sysman_ejecucion_gastos';

        $where   = [ "pp.movimiento = 'SI'", "eg.movimiento = 'SI'", 'pp.compania = %s' ];
        $prepare = [ $config['compania'] ];

        if ( ! empty( $config['dependencia'] ) ) {
            $where[]   = 'pp.nombredependencia = %s';
            $prepare[] = $config['dependencia'];
        }
        if ( $config['anio'] > 0 ) {
            $where[]   = 'pp.anio = %d';
            $prepare[] = $config['anio'];
        }
        if ( $config['mes'] > 0 ) {
            $where[]   = 'pp.mes = %d';
            $prepare[] = $config['mes'];
        }

        $where_clause = ' WHERE ' . implode( ' AND ', $where );
        $join = "FROM `{$pp_table}` pp INNER JOIN `{$eg_table}` eg "
              . 'ON pp.codigo = eg.codigocuenta AND pp.compania = eg.compania AND pp.anio = eg.anio AND pp.mes = eg.mes';

        if ( count( $valid_cols ) > 1 ) {
            $unions      = [];
            $all_prepare = [];

            foreach ( $valid_cols as $col ) {
                $label       = $this->column_label( $col );
                $sub         = "SELECT pp.nombre AS label, {$aggregate}(eg.`{$col}`) AS value, '{$label}' AS `group` {$join}{$where_clause} GROUP BY pp.nombre";
                $unions[]    = "({$sub})";
                $all_prepare = array_merge( $all_prepare, $prepare );
            }

            $query = implode( ' UNION ALL ', $unions ) . " ORDER BY label, `group` LIMIT 1000";
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            return $all_prepare ? $wpdb->prepare( $query, ...$all_prepare ) : $query;
        }

        $col   = $valid_cols[0];
        $query = "SELECT pp.nombre AS label, {$aggregate}(eg.`{$col}`) AS value {$join}{$where_clause} GROUP BY pp.nombre ORDER BY value DESC LIMIT 100";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $prepare ? $wpdb->prepare( $query, ...$prepare ) : $query;
    }

    /**
     * Get distinct dependencias from plan_presupuestal for a given compania/anio/mes.
     * Delegates to the Ejecucion Repository (single implementation, cached).
     */
    public function get_dependencias( string $compania = '001', int $anio = 0, int $mes = 0 ): array {
        return \SysmanSuite\Ejecucion\Repository::instance()->get_dependencias( $anio, $mes, $compania );
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

        // Late enqueue: covers charts rendered from widgets or page builders
        // where has_shortcode() on post_content never matches.
        $this->enqueue_chart_assets();

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
            $query = $this->validate_custom_query( $custom_query );
            if ( ! $query ) {
                wp_send_json_error( [ 'message' => 'Query no permitida: solo se admite un único SELECT sobre las tablas del plugin.' ] );
            }
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

            if ( $this->requires_movimiento_filter( $table ) ) {
                $where[] = "movimiento = 'SI'";
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
        $raw_value_cols = $post['value_columns'] ?? [];
        if ( ! is_array( $raw_value_cols ) ) {
            $raw_value_cols = [ $raw_value_cols ];
        }

        return $this->compose_vista_query( [
            'dependencia' => sanitize_text_field( $post['vista_dependencia'] ?? '' ),
            'compania'    => sanitize_text_field( $post['vista_compania'] ?? '001' ),
            'anio'        => absint( $post['filter_anio'] ?? 0 ),
            'mes'         => absint( $post['filter_mes'] ?? 0 ),
            'aggregate'   => strtoupper( sanitize_text_field( $post['aggregate'] ?? 'SUM' ) ),
            'value_cols'  => array_map( 'sanitize_text_field', $raw_value_cols ),
        ] );
    }

    /**
     * Enqueue frontend assets (only when shortcode is present).
     */
    public function enqueue_frontend_assets(): void {
        global $post;

        if ( ! $post || ! has_shortcode( $post->post_content, 'sysman_chart' ) ) {
            return;
        }

        $this->enqueue_chart_assets();
    }

    /**
     * Enqueue D3/D3plus and the frontend chart assets. Called early when the
     * shortcode is detected in post_content, and again from the shortcode
     * itself so charts inside widgets or page builders also load (all
     * scripts print in the footer, so the late call still works).
     */
    public function enqueue_chart_assets(): void {
        self::enqueue_d3plus();

        wp_enqueue_style(
            'sysman-frontend',
            SYSMAN_SUITE_URL . 'assets/css/frontend.css',
            [],
            SYSMAN_SUITE_VERSION
        );

        wp_enqueue_script(
            'sysman-frontend',
            SYSMAN_SUITE_URL . 'assets/js/frontend.js',
            [ 'd3plus' ],
            SYSMAN_SUITE_VERSION,
            true
        );
    }

    /**
     * Enqueue the D3plus v4 bundle (@d3plus/core).
     *
     * Since v4 the library ships its own D3 modules, so the separate D3 script
     * the plugin used to load is no longer needed. v4 also calls
     * crypto.randomUUID(), which browsers only expose in a secure context, so
     * an inline polyfill runs first to keep plain-HTTP installs working.
     */
    public static function enqueue_d3plus(): void {
        $url = get_option( 'sysman_d3plus_cdn_url', SYSMAN_SUITE_D3PLUS_CDN );

        wp_register_script( 'd3plus', $url, [], SYSMAN_SUITE_D3PLUS_VERSION, true );

        wp_add_inline_script(
            'd3plus',
            'if(!(window.crypto&&typeof window.crypto.randomUUID==="function")){'
            . 'window.crypto=window.crypto||{};'
            . 'window.crypto.randomUUID=function(){'
            . 'return "10000000-1000-4000-8000-100000000000".replace(/[018]/g,function(c){'
            . 'return (c^(Math.random()*16)>>(c/4)).toString(16);});};}',
            'before'
        );

        wp_enqueue_script( 'd3plus' );
    }
}
