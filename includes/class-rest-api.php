<?php
namespace SysmanSuite;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Rest_Api {

    private Database $database;

    public function __construct( Database $database ) {
        $this->database = $database;
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        $namespace = 'sysman-suite/v1';

        // Records endpoint (paginated)
        register_rest_route( $namespace, '/records/(?P<table>[\w]+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_records' ],
            'permission_callback' => [ $this, 'admin_permission' ],
            'args'                => [
                'table'    => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
                'page'     => [ 'default' => 1, 'sanitize_callback' => 'absint' ],
                'per_page' => [ 'default' => 20, 'sanitize_callback' => 'absint' ],
                'search'   => [ 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
                'orderby'  => [ 'default' => 'id', 'sanitize_callback' => 'sanitize_text_field' ],
                'order'    => [ 'default' => 'DESC', 'sanitize_callback' => 'sanitize_text_field' ],
                'anio'     => [ 'default' => 0, 'sanitize_callback' => 'absint' ],
                'mes'      => [ 'default' => 0, 'sanitize_callback' => 'absint' ],
            ],
        ] );

        // Stats endpoint
        register_rest_route( $namespace, '/stats', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_stats' ],
            'permission_callback' => [ $this, 'admin_permission' ],
        ] );

        // Tables list endpoint
        register_rest_route( $namespace, '/tables', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_tables' ],
            'permission_callback' => [ $this, 'admin_permission' ],
        ] );

        // Table columns endpoint
        register_rest_route( $namespace, '/columns/(?P<table>[\w]+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_columns' ],
            'permission_callback' => [ $this, 'admin_permission' ],
            'args'                => [
                'table' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );

        // Chart data endpoint (public)
        register_rest_route( $namespace, '/chart/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_chart_data' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [ 'required' => true, 'sanitize_callback' => 'absint' ],
            ],
        ] );

        // Chart CSV download (public)
        register_rest_route( $namespace, '/chart/(?P<id>\d+)/csv', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'download_chart_csv' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [ 'required' => true, 'sanitize_callback' => 'absint' ],
            ],
        ] );

        // Available years for a table
        register_rest_route( $namespace, '/years/(?P<table>[\w]+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_years' ],
            'permission_callback' => [ $this, 'admin_permission' ],
            'args'                => [
                'table' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );

        // Dependencias for Vista mode
        register_rest_route( $namespace, '/dependencias', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_dependencias' ],
            'permission_callback' => [ $this, 'admin_permission' ],
            'args'                => [
                'compania' => [ 'default' => '001', 'sanitize_callback' => 'sanitize_text_field' ],
                'anio'     => [ 'default' => 0, 'sanitize_callback' => 'absint' ],
                'mes'      => [ 'default' => 0, 'sanitize_callback' => 'absint' ],
            ],
        ] );
    }

    public function admin_permission(): bool {
        return current_user_can( 'manage_options' );
    }

    public function get_records( \WP_REST_Request $request ): \WP_REST_Response {
        global $wpdb;
        $table_key = $request->get_param( 'table' );
        $table     = $wpdb->prefix . $table_key;

        if ( ! $this->database->validate_table( $table ) ) {
            return new \WP_REST_Response( [ 'error' => 'Tabla no válida' ], 400 );
        }

        $result = $this->database->get_records( $table, [
            'page'     => $request->get_param( 'page' ),
            'per_page' => min( $request->get_param( 'per_page' ), 100 ),
            'search'   => $request->get_param( 'search' ),
            'orderby'  => $request->get_param( 'orderby' ),
            'order'    => $request->get_param( 'order' ),
            'anio'     => $request->get_param( 'anio' ),
            'mes'      => $request->get_param( 'mes' ),
        ] );

        $response = new \WP_REST_Response( $result );
        $response->header( 'X-WP-Total', $result['total'] );
        $response->header( 'X-WP-TotalPages', ceil( $result['total'] / max( 1, $request->get_param( 'per_page' ) ) ) );

        return $response;
    }

    public function get_stats(): \WP_REST_Response {
        return new \WP_REST_Response( $this->database->get_stats() );
    }

    public function get_tables(): \WP_REST_Response {
        $tables = [];
        foreach ( $this->database->get_available_tables() as $table => $label ) {
            $tables[] = [
                'name'  => $table,
                'key'   => str_replace( $GLOBALS['wpdb']->prefix, '', $table ),
                'label' => $label,
            ];
        }
        return new \WP_REST_Response( $tables );
    }

    public function get_columns( \WP_REST_Request $request ): \WP_REST_Response {
        global $wpdb;
        $table_key = $request->get_param( 'table' );
        $table     = $wpdb->prefix . $table_key;

        if ( ! $this->database->validate_table( $table ) ) {
            return new \WP_REST_Response( [ 'error' => 'Tabla no válida' ], 400 );
        }

        $columns = $this->database->get_table_columns( $table );
        return new \WP_REST_Response( $columns );
    }

    public function get_chart_data( \WP_REST_Request $request ): \WP_REST_Response {
        $id = $request->get_param( 'id' );

        if ( 'sysman_chart' !== get_post_type( $id ) ) {
            return new \WP_REST_Response( [ 'error' => 'Gráfico no encontrado' ], 404 );
        }

        $plugin = \Sysman_Suite::instance();
        $data   = $plugin->visualizer->get_chart_data( $id );
        $meta   = $plugin->visualizer->get_chart_meta( $id );

        if ( ! empty( $data ) && isset( $data[0]['group'] ) ) {
            $meta['has_groups'] = true;
        }

        return new \WP_REST_Response( [
            'data' => $data,
            'meta' => $meta,
        ] );
    }

    public function download_chart_csv( \WP_REST_Request $request ): \WP_REST_Response {
        $id = $request->get_param( 'id' );

        if ( 'sysman_chart' !== get_post_type( $id ) ) {
            return new \WP_REST_Response( [ 'error' => 'Gráfico no encontrado' ], 404 );
        }

        $plugin = \Sysman_Suite::instance();
        $data   = $plugin->visualizer->get_chart_data( $id );

        $has_group = ! empty( $data ) && isset( $data[0]['group'] );

        if ( $has_group ) {
            $csv = "Serie,Etiqueta,Valor\n";
            foreach ( $data as $row ) {
                $group = str_replace( '"', '""', $row['group'] ?? '' );
                $label = str_replace( '"', '""', $row['label'] ?? '' );
                $value = $row['value'] ?? 0;
                $csv  .= "\"{$group}\",\"{$label}\",{$value}\n";
            }
        } else {
            $csv = "Etiqueta,Valor\n";
            foreach ( $data as $row ) {
                $label = str_replace( '"', '""', $row['label'] ?? '' );
                $value = $row['value'] ?? 0;
                $csv  .= "\"{$label}\",{$value}\n";
            }
        }

        $response = new \WP_REST_Response( $csv );
        $response->header( 'Content-Type', 'text/csv; charset=UTF-8' );
        $response->header( 'Content-Disposition', 'attachment; filename="sysman-chart-' . $id . '.csv"' );

        return $response;
    }

    public function get_years( \WP_REST_Request $request ): \WP_REST_Response {
        global $wpdb;
        $table_key = $request->get_param( 'table' );
        $table     = $wpdb->prefix . $table_key;

        if ( ! $this->database->validate_table( $table ) ) {
            return new \WP_REST_Response( [ 'error' => 'Tabla no válida' ], 400 );
        }

        $years = $this->database->get_available_years( $table );
        return new \WP_REST_Response( $years );
    }

    public function get_dependencias( \WP_REST_Request $request ): \WP_REST_Response {
        $compania = $request->get_param( 'compania' ) ?: '001';
        $anio     = (int) $request->get_param( 'anio' );
        $mes      = (int) $request->get_param( 'mes' );

        $plugin = \Sysman_Suite::instance();
        $deps   = $plugin->visualizer->get_dependencias( $compania, $anio, $mes );

        return new \WP_REST_Response( $deps );
    }
}
