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
            'chart_type'       => 'sanitize_text_field',
            'data_table'       => 'sanitize_text_field',
            'group_column'     => 'sanitize_text_field',
            'value_column'     => 'sanitize_text_field',
            'aggregate'        => 'sanitize_text_field',
            'filter_anio'      => 'absint',
            'filter_mes'       => 'absint',
            'filter_destino'   => 'sanitize_text_field',
            'chart_height'     => 'absint',
            'chart_colors'     => 'sanitize_text_field',
            'show_legend'      => 'sanitize_text_field',
            'show_labels'      => 'sanitize_text_field',
            'number_format'    => 'sanitize_text_field',
            'y_axis_title'     => 'sanitize_text_field',
            'x_axis_title'     => 'sanitize_text_field',
            'show_timeline'    => 'sanitize_text_field',
            'show_toolbar'     => 'sanitize_text_field',
            'toolbar_detail'   => 'sanitize_text_field',
            'toolbar_share'    => 'sanitize_text_field',
            'toolbar_data'     => 'sanitize_text_field',
            'toolbar_image'    => 'sanitize_text_field',
            'toolbar_csv'      => 'sanitize_text_field',
        ];

        foreach ( $fields as $field => $sanitize ) {
            if ( isset( $_POST[ "sysman_{$field}" ] ) ) {
                $value = call_user_func( $sanitize, $_POST[ "sysman_{$field}" ] );
                update_post_meta( $post_id, "_sysman_{$field}", $value );
            } else {
                delete_post_meta( $post_id, "_sysman_{$field}" );
            }
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
     * Build a secure SQL query from chart configuration.
     */
    public function build_chart_query( int $chart_id ): ?string {
        global $wpdb;

        // Check for custom query first
        $custom_query = get_post_meta( $chart_id, '_sysman_custom_query', true );
        if ( ! empty( $custom_query ) ) {
            return $custom_query;
        }

        $table     = get_post_meta( $chart_id, '_sysman_data_table', true );
        $group_col = get_post_meta( $chart_id, '_sysman_group_column', true );
        $value_col = get_post_meta( $chart_id, '_sysman_value_column', true );
        $aggregate = strtoupper( get_post_meta( $chart_id, '_sysman_aggregate', true ) ?: 'SUM' );
        $filters   = get_post_meta( $chart_id, '_sysman_filters', true ) ?: [];

        // Validate table
        if ( ! $this->database->validate_table( $table ) ) {
            return null;
        }

        // Validate columns
        if ( ! $this->database->validate_column( $table, $group_col ) ) {
            return null;
        }
        if ( ! $this->database->validate_column( $table, $value_col ) ) {
            return null;
        }

        // Validate aggregate
        if ( ! in_array( $aggregate, self::ALLOWED_AGGREGATES, true ) ) {
            $aggregate = 'SUM';
        }

        $query = "SELECT `{$group_col}` AS label, {$aggregate}(`{$value_col}`) AS value FROM `{$table}`";

        // Build WHERE
        $where   = [];
        $prepare = [];

        // Year/month filters
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

        // Custom filters
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
                $values  = array_map( 'trim', explode( ',', $val ) );
                $placeholders = implode( ', ', array_fill( 0, count( $values ), '%s' ) );
                $where[] = "`{$col}` {$op} ({$placeholders})";
                $prepare = array_merge( $prepare, $values );
            } else {
                $where[]   = "`{$col}` {$op} %s";
                $prepare[] = $val;
            }
        }

        if ( $where ) {
            $query .= ' WHERE ' . implode( ' AND ', $where );
        }

        $query .= " GROUP BY `{$group_col}` ORDER BY value DESC LIMIT 100";

        if ( $prepare ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            return $wpdb->prepare( $query, ...$prepare );
        }

        return $query;
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
                    'bar'         => __( 'Barras', 'sysman-suite' ),
                    'line'        => __( 'Líneas', 'sysman-suite' ),
                    'area'        => __( 'Área', 'sysman-suite' ),
                    'pie'         => __( 'Pie / Torta', 'sysman-suite' ),
                    'donut'       => __( 'Donut', 'sysman-suite' ),
                    'treemap'     => __( 'Treemap', 'sysman-suite' ),
                    'stacked_bar' => __( 'Barras Apiladas', 'sysman-suite' ),
                    'grouped_bar' => __( 'Barras Agrupadas', 'sysman-suite' ),
                ];
                $icons = [
                    'bar' => 'chart-bar', 'line' => 'chart-line', 'area' => 'chart-area',
                    'pie' => 'chart-pie', 'donut' => 'chart-pie', 'treemap' => 'screenoptions',
                    'stacked_bar' => 'chart-bar', 'grouped_bar' => 'chart-bar',
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
     * Enqueue frontend assets (only when shortcode is present).
     */
    public function enqueue_frontend_assets(): void {
        global $post;

        if ( ! $post || ! has_shortcode( $post->post_content, 'sysman_chart' ) ) {
            return;
        }

        // D3.js and D3plus
        wp_enqueue_script( 'd3-v5', 'https://d3js.org/d3.v5.min.js', [], '5.16.0', true );
        wp_enqueue_script( 'd3plus', 'https://d3plus.org/js/d3plus.v2.0.full.min.js', [ 'd3-v5' ], '2.0.0', true );

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

        wp_localize_script( 'sysman-frontend', 'sysmanFrontend', [
            'restUrl'  => rest_url( 'sysman-suite/v1/' ),
            'restNonce' => wp_create_nonce( 'wp_rest' ),
        ] );
    }
}
