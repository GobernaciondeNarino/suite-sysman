<?php
namespace SismanSuite;

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
        add_action( 'save_post_sisman_chart', [ $this, 'save_meta' ] );
        add_shortcode( 'sisman_chart', [ $this, 'render_shortcode' ] );

        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
    }

    /**
     * Register the chart custom post type.
     */
    public function register_post_type(): void {
        register_post_type( 'sisman_chart', [
            'labels'       => [
                'name'               => __( 'Gráficos SISMAN', 'sisman-suite' ),
                'singular_name'      => __( 'Gráfico SISMAN', 'sisman-suite' ),
                'add_new'            => __( 'Nuevo Gráfico', 'sisman-suite' ),
                'add_new_item'       => __( 'Agregar Nuevo Gráfico', 'sisman-suite' ),
                'edit_item'          => __( 'Editar Gráfico', 'sisman-suite' ),
                'view_item'          => __( 'Ver Gráfico', 'sisman-suite' ),
                'all_items'          => __( 'Gráficos', 'sisman-suite' ),
                'search_items'       => __( 'Buscar Gráficos', 'sisman-suite' ),
                'not_found'          => __( 'No se encontraron gráficos', 'sisman-suite' ),
                'not_found_in_trash' => __( 'No se encontraron gráficos en la papelera', 'sisman-suite' ),
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => 'sisman-suite',
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
            'sisman_chart_config',
            __( 'Configuración de la Gráfica', 'sisman-suite' ),
            [ $this, 'render_chart_config' ],
            'sisman_chart',
            'normal',
            'high'
        );

        add_meta_box(
            'sisman_chart_preview',
            __( 'Vista Previa', 'sisman-suite' ),
            [ $this, 'render_chart_preview' ],
            'sisman_chart',
            'normal',
            'default'
        );

        add_meta_box(
            'sisman_chart_shortcode',
            __( 'Shortcode', 'sisman-suite' ),
            [ $this, 'render_shortcode_info' ],
            'sisman_chart',
            'side',
            'high'
        );
    }

    /**
     * Render chart configuration metabox.
     */
    public function render_chart_config( \WP_Post $post ): void {
        include SISMAN_SUITE_PATH . 'templates/admin/chart-config.php';
    }

    /**
     * Render chart preview metabox.
     */
    public function render_chart_preview( \WP_Post $post ): void {
        ?>
        <div class="sisman-preview-container">
            <button type="button" id="sisman-refresh-preview" class="button button-primary">
                <span class="dashicons dashicons-update" aria-hidden="true"></span>
                <?php esc_html_e( 'Actualizar Vista Previa', 'sisman-suite' ); ?>
            </button>
            <div id="sisman-chart-preview-area" class="sisman-chart-preview-area">
                <p class="sisman-preview-placeholder">
                    <?php esc_html_e( 'Configure la gráfica y haga clic en "Actualizar Vista Previa"', 'sisman-suite' ); ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Render shortcode info metabox.
     */
    public function render_shortcode_info( \WP_Post $post ): void {
        echo '<p>' . esc_html__( 'Usa este shortcode para mostrar el gráfico:', 'sisman-suite' ) . '</p>';
        echo '<code>[sisman_chart id="' . esc_attr( $post->ID ) . '"]</code>';
        echo '<p class="description">' . esc_html__( 'Copia y pega este shortcode en cualquier página o entrada.', 'sisman-suite' ) . '</p>';
    }

    /**
     * Save chart meta data.
     */
    public function save_meta( int $post_id ): void {
        if ( ! isset( $_POST['sisman_chart_nonce'] ) || ! wp_verify_nonce( $_POST['sisman_chart_nonce'], 'sisman_chart_save' ) ) {
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
            if ( isset( $_POST[ "sisman_{$field}" ] ) ) {
                $value = call_user_func( $sanitize, $_POST[ "sisman_{$field}" ] );
                update_post_meta( $post_id, "_sisman_{$field}", $value );
            } else {
                delete_post_meta( $post_id, "_sisman_{$field}" );
            }
        }

        // Handle custom query (sanitize but allow SQL)
        if ( isset( $_POST['sisman_custom_query'] ) ) {
            $query = sanitize_textarea_field( $_POST['sisman_custom_query'] );
            if ( ! empty( $query ) ) {
                update_post_meta( $post_id, '_sisman_custom_query', $query );
            } else {
                delete_post_meta( $post_id, '_sisman_custom_query' );
            }
        }

        // Handle filters array
        if ( isset( $_POST['sisman_filters'] ) && is_array( $_POST['sisman_filters'] ) ) {
            $filters = [];
            foreach ( $_POST['sisman_filters'] as $filter ) {
                if ( ! empty( $filter['column'] ) && ! empty( $filter['operator'] ) ) {
                    $filters[] = [
                        'column'   => sanitize_text_field( $filter['column'] ),
                        'operator' => sanitize_text_field( $filter['operator'] ),
                        'value'    => sanitize_text_field( $filter['value'] ?? '' ),
                    ];
                }
            }
            update_post_meta( $post_id, '_sisman_filters', $filters );
        } else {
            delete_post_meta( $post_id, '_sisman_filters' );
        }
    }

    /**
     * Build a secure SQL query from chart configuration.
     */
    public function build_chart_query( int $chart_id ): ?string {
        global $wpdb;

        // Check for custom query first
        $custom_query = get_post_meta( $chart_id, '_sisman_custom_query', true );
        if ( ! empty( $custom_query ) ) {
            return $custom_query;
        }

        $table     = get_post_meta( $chart_id, '_sisman_data_table', true );
        $group_col = get_post_meta( $chart_id, '_sisman_group_column', true );
        $value_col = get_post_meta( $chart_id, '_sisman_value_column', true );
        $aggregate = strtoupper( get_post_meta( $chart_id, '_sisman_aggregate', true ) ?: 'SUM' );
        $filters   = get_post_meta( $chart_id, '_sisman_filters', true ) ?: [];

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
        $filter_anio = (int) get_post_meta( $chart_id, '_sisman_filter_anio', true );
        $filter_mes  = (int) get_post_meta( $chart_id, '_sisman_filter_mes', true );

        if ( $filter_anio > 0 ) {
            $where[]   = 'anio = %d';
            $prepare[] = $filter_anio;
        }
        if ( $filter_mes > 0 ) {
            $where[]   = 'mes = %d';
            $prepare[] = $filter_mes;
        }

        $filter_destino = get_post_meta( $chart_id, '_sisman_filter_destino', true );
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
            'chart_type'     => get_post_meta( $chart_id, '_sisman_chart_type', true ) ?: 'bar',
            'chart_height'   => (int) ( get_post_meta( $chart_id, '_sisman_chart_height', true ) ?: 400 ),
            'chart_colors'   => get_post_meta( $chart_id, '_sisman_chart_colors', true ) ?: '#844e80,#ff7300,#ffc53b,#3eba6a,#0080c3,#e74c3c,#9b59b6,#1abc9c',
            'show_legend'    => get_post_meta( $chart_id, '_sisman_show_legend', true ) === 'yes',
            'show_labels'    => get_post_meta( $chart_id, '_sisman_show_labels', true ) !== 'no',
            'number_format'  => get_post_meta( $chart_id, '_sisman_number_format', true ) ?: 'colombian',
            'y_axis_title'   => get_post_meta( $chart_id, '_sisman_y_axis_title', true ) ?: '',
            'x_axis_title'   => get_post_meta( $chart_id, '_sisman_x_axis_title', true ) ?: '',
            'show_timeline'  => get_post_meta( $chart_id, '_sisman_show_timeline', true ) === 'yes',
            'show_toolbar'   => get_post_meta( $chart_id, '_sisman_show_toolbar', true ) !== '',
            'toolbar_detail' => get_post_meta( $chart_id, '_sisman_toolbar_detail', true ) === 'yes',
            'toolbar_share'  => get_post_meta( $chart_id, '_sisman_toolbar_share', true ) === 'yes',
            'toolbar_data'   => get_post_meta( $chart_id, '_sisman_toolbar_data', true ) === 'yes',
            'toolbar_image'  => get_post_meta( $chart_id, '_sisman_toolbar_image', true ) === 'yes',
            'toolbar_csv'    => get_post_meta( $chart_id, '_sisman_toolbar_csv', true ) === 'yes',
        ];
    }

    /**
     * Render the chart shortcode.
     */
    public function render_shortcode( array $atts ): string {
        $atts = shortcode_atts( [ 'id' => 0 ], $atts, 'sisman_chart' );
        $id   = absint( $atts['id'] );

        if ( ! $id || 'sisman_chart' !== get_post_type( $id ) ) {
            return '<p class="sisman-error">' . esc_html__( 'Gráfico no encontrado.', 'sisman-suite' ) . '</p>';
        }

        $post = get_post( $id );
        if ( ! $post || 'publish' !== $post->post_status ) {
            return '';
        }

        ob_start();
        include SISMAN_SUITE_PATH . 'templates/frontend/chart.php';
        return ob_get_clean();
    }

    /**
     * Enqueue frontend assets (only when shortcode is present).
     */
    public function enqueue_frontend_assets(): void {
        global $post;

        if ( ! $post || ! has_shortcode( $post->post_content, 'sisman_chart' ) ) {
            return;
        }

        // D3.js and D3plus
        wp_enqueue_script( 'd3-v5', 'https://d3js.org/d3.v5.min.js', [], '5.16.0', true );
        wp_enqueue_script( 'd3plus', 'https://d3plus.org/js/d3plus.v2.0.full.min.js', [ 'd3-v5' ], '2.0.0', true );

        wp_enqueue_style(
            'sisman-frontend',
            SISMAN_SUITE_URL . 'assets/css/frontend.css',
            [],
            SISMAN_SUITE_VERSION
        );

        wp_enqueue_script(
            'sisman-frontend',
            SISMAN_SUITE_URL . 'assets/js/frontend.js',
            [ 'd3-v5', 'd3plus' ],
            SISMAN_SUITE_VERSION,
            true
        );

        wp_localize_script( 'sisman-frontend', 'sismanFrontend', [
            'restUrl'  => rest_url( 'sisman-suite/v1/' ),
            'restNonce' => wp_create_nonce( 'wp_rest' ),
        ] );
    }
}
