<?php
namespace SysmanSuite\Ejecucion;

if ( ! defined( 'ABSPATH' ) ) exit;

class EjecucionModule {

    private static ?self $instance = null;

    private PostType       $post_type;
    private RestController $rest;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->post_type = new PostType();
        $this->rest      = new RestController();
    }

    public function boot(): void {
        Schema::run();

        $this->post_type->register();
        $this->rest->register();

        add_action( 'admin_menu', [ $this, 'admin_menu' ], 20 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        add_action( 'wp_ajax_gn_ejecucion_save', [ $this, 'ajax_save' ] );
        add_action( 'wp_ajax_gn_ejecucion_delete', [ $this, 'ajax_delete' ] );
        add_action( 'wp_ajax_gn_ejecucion_sync', [ $this, 'ajax_sync' ] );
        add_action( 'wp_ajax_gn_ejecucion_export', [ $this, 'ajax_export' ] );
        add_action( 'wp_ajax_nopriv_gn_ejecucion_export', [ $this, 'ajax_export' ] );

        add_action( 'wp_ajax_gn_reporte_dis_export', [ $this, 'ajax_reporte_dis_export' ] );
        add_action( 'wp_ajax_nopriv_gn_reporte_dis_export', [ $this, 'ajax_reporte_dis_export' ] );

        add_shortcode( 'gn_ejecucion', [ $this, 'shortcode' ] );
        add_shortcode( 'gn_ejecucion_export', [ $this, 'shortcode_export' ] );
        add_shortcode( 'gn_reporte_dis', [ $this, 'shortcode_reporte_dis' ] );
    }

    public function admin_menu(): void {
        add_submenu_page(
            'sysman-suite',
            __( 'Ejecución', 'sysman-suite' ),
            __( 'Ejecución', 'sysman-suite' ),
            'manage_options',
            'sysman-ejecucion',
            [ $this, 'render_page' ]
        );
    }

    public function render_page(): void {
        $action  = sanitize_text_field( $_GET['action'] ?? 'list' );
        $post_id = absint( $_GET['id'] ?? 0 );

        switch ( $action ) {
            case 'new':
            case 'edit':
                include SYSMAN_SUITE_PATH . 'templates/admin/ejecucion/ejecucion-edit.php';
                break;
            case 'view':
                include SYSMAN_SUITE_PATH . 'templates/admin/ejecucion/ejecucion-view.php';
                break;
            default:
                include SYSMAN_SUITE_PATH . 'templates/admin/ejecucion/ejecucion-list.php';
                break;
        }
    }

    public function enqueue_assets( string $hook ): void {
        if ( 'sysman-suite_page_sysman-ejecucion' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'sysman-admin',
            SYSMAN_SUITE_URL . 'assets/css/admin.css',
            [],
            SYSMAN_SUITE_VERSION
        );

        wp_enqueue_style(
            'gn-ejecucion',
            SYSMAN_SUITE_URL . 'assets/css/ejecucion.css',
            [],
            filemtime( SYSMAN_SUITE_PATH . 'assets/css/ejecucion.css' )
        );

        wp_enqueue_script(
            'gn-ejecucion',
            SYSMAN_SUITE_URL . 'assets/js/ejecucion.js',
            [ 'wp-api' ],
            filemtime( SYSMAN_SUITE_PATH . 'assets/js/ejecucion.js' ),
            true
        );

        wp_localize_script( 'gn-ejecucion', 'gnEjecucion', [
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'restUrl'  => rest_url( 'gn-sisman/v1/' ),
            'nonce'    => wp_create_nonce( 'gn_ejecucion_nonce' ),
            'restNonce' => wp_create_nonce( 'wp_rest' ),
            'currentYear' => (int) date( 'Y' ),
        ] );
    }

    public function ajax_save(): void {
        check_ajax_referer( 'gn_ejecucion_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $post_id     = absint( $_POST['post_id'] ?? 0 );
        $title       = sanitize_text_field( $_POST['title'] ?? '' );
        $dependencia = sanitize_text_field( $_POST['dependencia'] ?? '' );
        $anio        = absint( $_POST['anio'] ?? date( 'Y' ) );
        $mes         = absint( $_POST['mes'] ?? date( 'n' ) );
        $compania    = sanitize_text_field( $_POST['compania'] ?? '001' );
        $vigencia      = sanitize_text_field( $_POST['vigencia'] ?? '' );
        $agrupar_bpid  = sanitize_text_field( $_POST['agrupar_bpid'] ?? '0' );

        if ( empty( $title ) || empty( $dependencia ) ) {
            wp_send_json_error( __( 'Título y dependencia son requeridos.', 'sysman-suite' ) );
        }

        $post_data = [
            'post_type'   => 'gn_ejecucion',
            'post_title'  => $title,
            'post_status' => 'publish',
        ];

        if ( $post_id > 0 ) {
            $post_data['ID'] = $post_id;
            wp_update_post( $post_data );
        } else {
            $post_id = wp_insert_post( $post_data );
        }

        if ( is_wp_error( $post_id ) || ! $post_id ) {
            wp_send_json_error( __( 'Error al guardar.', 'sysman-suite' ) );
        }

        update_post_meta( $post_id, '_gn_dependencia', $dependencia );
        update_post_meta( $post_id, '_gn_anio', $anio );
        update_post_meta( $post_id, '_gn_mes', $mes );
        update_post_meta( $post_id, '_gn_compania', $compania );
        update_post_meta( $post_id, '_gn_vigencia', $vigencia );
        update_post_meta( $post_id, '_gn_agrupar_bpid', $agrupar_bpid );

        wp_send_json_success( [ 'post_id' => $post_id ] );
    }

    public function ajax_delete(): void {
        check_ajax_referer( 'gn_ejecucion_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $post_id = absint( $_POST['post_id'] ?? 0 );
        if ( $post_id && 'gn_ejecucion' === get_post_type( $post_id ) ) {
            wp_delete_post( $post_id, true );
            wp_send_json_success();
        }

        wp_send_json_error( __( 'Seguimiento no encontrado.', 'sysman-suite' ) );
    }

    public function ajax_sync(): void {
        check_ajax_referer( 'gn_ejecucion_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        @set_time_limit( 600 );

        $compania = sanitize_text_field( $_POST['compania'] ?? '001' );
        $anio     = absint( $_POST['anio'] ?? date( 'Y' ) );
        $mes      = absint( $_POST['mes'] ?? date( 'n' ) );

        $importer = \Sysman_Suite::instance()->importer;
        $results  = [];

        try {
            $r = $importer->import_plan( $compania, $anio, $mes );
            $results['plan'] = $r['success']
                ? [ 'inserted' => $r['imported'] ]
                : [ 'error' => $r['error'] ?? 'Error desconocido' ];
        } catch ( \Throwable $e ) {
            $results['plan'] = [ 'error' => $e->getMessage() ];
        }

        try {
            $r = $importer->import_ejecucion( $compania, $anio, $mes );
            $results['ejecucion'] = $r['success']
                ? [ 'inserted' => $r['imported'] ]
                : [ 'error' => $r['error'] ?? 'Error desconocido' ];
        } catch ( \Throwable $e ) {
            $results['ejecucion'] = [ 'error' => $e->getMessage() ];
        }

        try {
            $r = $importer->import_auxiliar( $compania, $anio, $mes, 'DIS' );
            $results['dis'] = $r['success']
                ? [ 'inserted' => $r['imported'] ]
                : [ 'error' => $r['error'] ?? 'Error desconocido' ];
        } catch ( \Throwable $e ) {
            $results['dis'] = [ 'error' => $e->getMessage() ];
        }

        try {
            $r = $importer->import_auxiliar( $compania, $anio, $mes, 'RES' );
            $results['res'] = $r['success']
                ? [ 'inserted' => $r['imported'] ]
                : [ 'error' => $r['error'] ?? 'Error desconocido' ];
        } catch ( \Throwable $e ) {
            $results['res'] = [ 'error' => $e->getMessage() ];
        }

        update_option( 'gn_sisman_last_sync_ejecucion_module', current_time( 'mysql' ) );

        wp_send_json_success( $results );
    }

    public function ajax_export(): void {
        $post_id = absint( $_GET['id'] ?? 0 );
        $format  = sanitize_text_field( $_GET['format'] ?? 'csv' );

        $post = $post_id ? get_post( $post_id ) : null;
        if ( ! $post || 'gn_ejecucion' !== $post->post_type ) {
            wp_die( 'Seguimiento no encontrado.', 'Error', [ 'response' => 404 ] );
        }

        $repo = Repository::instance();
        $data = $repo->get_export_data( $post_id );

        $slug     = sanitize_file_name( $post->post_title );
        $filename = $slug . '_' . gmdate( 'Y-m-d' );

        $headers = [
            'Codigo', 'Nombre', 'Destino', 'Naturaleza', 'Sector', 'Programa',
            'Subprograma', 'Codigo Producto', 'Codigo BPIN',
            'Aprob. Inicial', 'Adicion', 'Reduccion', 'Credito', 'Contracredito',
            'Aprob. Vigente', 'Disponibilidades', 'Saldo Disponible', 'Compromisos',
            'Disp. Abiertas', 'Obligacion', 'Pagos', 'Oblig. por Pagar',
        ];

        $keys = [
            'codigo', 'nombre', 'destino', 'naturaleza', 'sector', 'programa',
            'subprograma', 'codigoproducto', 'codigobpin',
            'apropiacioninicial', 'adicion', 'reduccion', 'credito', 'contracredito',
            'apropiacionvigente', 'disponibilidades', 'saldodisponible', 'compromisos',
            'disponibilidadesabiertas', 'obligacion', 'pagos', 'obligacionesporpagar',
        ];

        if ( 'txt' === $format ) {
            header( 'Content-Type: text/plain; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename="' . $filename . '.txt"' );

            echo implode( "\t", $headers ) . "\n";
            foreach ( $data as $row ) {
                $values = [];
                foreach ( $keys as $k ) {
                    $values[] = $row[ $k ] ?? '';
                }
                echo implode( "\t", $values ) . "\n";
            }
        } else {
            header( 'Content-Type: text/csv; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename="' . $filename . '.csv"' );

            $output = fopen( 'php://output', 'w' );
            fprintf( $output, "\xEF\xBB\xBF" );
            fputcsv( $output, $headers, ';' );
            foreach ( $data as $row ) {
                $values = [];
                foreach ( $keys as $k ) {
                    $values[] = $row[ $k ] ?? '';
                }
                fputcsv( $output, $values, ';' );
            }
            fclose( $output );
        }

        exit;
    }

    public function shortcode_export( $atts ): string {
        $atts    = shortcode_atts( [ 'id' => 0 ], $atts, 'gn_ejecucion_export' );
        $post_id = absint( $atts['id'] );

        if ( ! $post_id ) {
            return '';
        }

        $post = get_post( $post_id );
        if ( ! $post || 'gn_ejecucion' !== $post->post_type ) {
            return '';
        }

        wp_enqueue_style( 'gn-ejecucion', SYSMAN_SUITE_URL . 'assets/css/ejecucion.css', [], filemtime( SYSMAN_SUITE_PATH . 'assets/css/ejecucion.css' ) );

        $csv_url  = admin_url( 'admin-ajax.php?action=gn_ejecucion_export&id=' . $post_id . '&format=csv' );
        $txt_url  = admin_url( 'admin-ajax.php?action=gn_ejecucion_export&id=' . $post_id . '&format=txt' );
        $json_url = rest_url( 'gn-sisman/v1/ejecucion/' . $post_id . '/export' );

        $dependencia = get_post_meta( $post_id, '_gn_dependencia', true );
        $anio = get_post_meta( $post_id, '_gn_anio', true );
        $mes  = (int) get_post_meta( $post_id, '_gn_mes', true );
        $meses = [ 1=>'Ene',2=>'Feb',3=>'Mar',4=>'Abr',5=>'May',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dic' ];

        $html  = '<div class="gn-ejec-export">';
        $html .= '<div class="gn-ejec-export__header">';
        $html .= '<strong class="gn-ejec-export__title">' . esc_html__( 'Exportar Datos de Ejecucion', 'sysman-suite' ) . '</strong>';
        $html .= '<span class="gn-ejec-export__meta">' . esc_html( $dependencia . ' — ' . ( $meses[ $mes ] ?? $mes ) . ' ' . $anio ) . '</span>';
        $html .= '</div>';
        $html .= '<div class="gn-ejec-export__body">';
        $html .= '<a href="' . esc_url( $csv_url ) . '" class="gn-ejec-export__btn gn-ejec-export__btn--csv">';
        $html .= '<span class="gn-ejec-export__btn-icon">&#128196;</span> CSV</a>';
        $html .= '<a href="' . esc_url( $txt_url ) . '" class="gn-ejec-export__btn gn-ejec-export__btn--txt">';
        $html .= '<span class="gn-ejec-export__btn-icon">&#128203;</span> TXT</a>';
        $html .= '<a href="' . esc_url( $json_url ) . '" target="_blank" rel="noopener" class="gn-ejec-export__btn gn-ejec-export__btn--json">';
        $html .= '<span class="gn-ejec-export__btn-icon">&#128279;</span> JSON API</a>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    public function ajax_reporte_dis_export(): void {
        $anio        = absint( $_GET['anio'] ?? 0 );
        $mes         = absint( $_GET['mes'] ?? 0 );
        $compania    = sanitize_text_field( $_GET['compania'] ?? '001' );
        $dependencia = sanitize_text_field( $_GET['dependencia'] ?? '' );
        $format      = sanitize_text_field( $_GET['format'] ?? 'csv' );

        if ( ! $anio || ! $mes ) {
            wp_die( 'Parametros anio y mes son requeridos.', 'Error', [ 'response' => 400 ] );
        }

        $repo = Repository::instance();
        $data = $repo->get_disponibilidades_report( $anio, $mes, $compania, $dependencia );

        $filename = 'DIS_' . $compania . '_' . $anio . '_' . $mes . '_' . gmdate( 'Y-m-d' );

        $col_headers = [
            'Numero', 'Tercero', 'Nombre Tercero', 'Rubro', 'Nombre Rubro', 'Descripcion',
            'Nro Documento', 'Valor Debito', 'Valor Credito', 'Saldo por Ejecutar',
            'Cpte Afectado', 'Fecha', 'Anio', 'Mes',
            'Dependencia', 'Nombre Dependencia', 'Destino', 'Naturaleza',
            'Sector', 'Programa', 'Subprograma', 'Codigo Producto', 'Codigo BPIN',
        ];

        $keys = [
            'numero', 'tercero', 'nombretercero', 'rubro', 'nombrerubro', 'descripcion',
            'nrodocumento', 'valordebito', 'valorcredito', 'saldoporejecutaresp',
            'cmpteafectado', 'fecha', 'anio', 'mes',
            'dependencia', 'nombredependencia', 'destino', 'naturaleza',
            'sector', 'programa', 'subprograma', 'codigoproducto', 'codigobpin',
        ];

        if ( 'txt' === $format ) {
            header( 'Content-Type: text/plain; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename="' . $filename . '.txt"' );

            echo implode( "\t", $col_headers ) . "\n";
            foreach ( $data as $row ) {
                $values = [];
                foreach ( $keys as $k ) {
                    $values[] = $row[ $k ] ?? '';
                }
                echo implode( "\t", $values ) . "\n";
            }
        } else {
            header( 'Content-Type: text/csv; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename="' . $filename . '.csv"' );

            $output = fopen( 'php://output', 'w' );
            fprintf( $output, "\xEF\xBB\xBF" );
            fputcsv( $output, $col_headers, ';' );
            foreach ( $data as $row ) {
                $values = [];
                foreach ( $keys as $k ) {
                    $values[] = $row[ $k ] ?? '';
                }
                fputcsv( $output, $values, ';' );
            }
            fclose( $output );
        }

        exit;
    }

    public function shortcode_reporte_dis( $atts ): string {
        $atts = shortcode_atts( [
            'anio'        => '',
            'mes'         => '',
            'compania'    => '001',
            'dependencia' => '',
        ], $atts, 'gn_reporte_dis' );

        $anio     = absint( $atts['anio'] );
        $mes      = absint( $atts['mes'] );
        $compania = sanitize_text_field( $atts['compania'] );
        $dep      = sanitize_text_field( $atts['dependencia'] );

        if ( ! $anio || ! $mes ) {
            return '<p style="color:#dc2626;">Shortcode <code>[gn_reporte_dis]</code>: atributos <strong>anio</strong> y <strong>mes</strong> son requeridos.</p>';
        }

        wp_enqueue_style( 'gn-ejecucion', SYSMAN_SUITE_URL . 'assets/css/ejecucion.css', [], filemtime( SYSMAN_SUITE_PATH . 'assets/css/ejecucion.css' ) );

        $base_params = 'anio=' . $anio . '&mes=' . $mes . '&compania=' . rawurlencode( $compania );
        if ( '' !== $dep ) {
            $base_params .= '&dependencia=' . rawurlencode( $dep );
        }

        $csv_url  = admin_url( 'admin-ajax.php?action=gn_reporte_dis_export&format=csv&' . $base_params );
        $txt_url  = admin_url( 'admin-ajax.php?action=gn_reporte_dis_export&format=txt&' . $base_params );
        $json_url = rest_url( 'gn-sisman/v1/reporte/disponibilidades?' . $base_params );

        $meses = [ 1=>'Ene',2=>'Feb',3=>'Mar',4=>'Abr',5=>'May',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dic' ];
        $subtitle = ( $meses[ $mes ] ?? $mes ) . ' ' . $anio;
        if ( '' !== $dep ) {
            $subtitle = $dep . ' — ' . $subtitle;
        }

        $html  = '<div class="gn-ejec-export">';
        $html .= '<div class="gn-ejec-export__header">';
        $html .= '<strong class="gn-ejec-export__title">Reporte Disponibilidades (DIS)</strong>';
        $html .= '<span class="gn-ejec-export__meta">' . esc_html( $subtitle ) . '</span>';
        $html .= '</div>';
        $html .= '<div class="gn-ejec-export__body">';
        $html .= '<a href="' . esc_url( $csv_url ) . '" class="gn-ejec-export__btn gn-ejec-export__btn--csv">';
        $html .= '<span class="gn-ejec-export__btn-icon">&#128196;</span> CSV</a>';
        $html .= '<a href="' . esc_url( $txt_url ) . '" class="gn-ejec-export__btn gn-ejec-export__btn--txt">';
        $html .= '<span class="gn-ejec-export__btn-icon">&#128203;</span> TXT</a>';
        $html .= '<a href="' . esc_url( $json_url ) . '" target="_blank" rel="noopener" class="gn-ejec-export__btn gn-ejec-export__btn--json">';
        $html .= '<span class="gn-ejec-export__btn-icon">&#128279;</span> JSON API</a>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    public function shortcode( $atts ): string {
        $atts = shortcode_atts( [ 'id' => 0 ], $atts, 'gn_ejecucion' );
        $post_id = absint( $atts['id'] );

        if ( ! $post_id ) {
            return '';
        }

        wp_enqueue_style( 'gn-ejecucion', SYSMAN_SUITE_URL . 'assets/css/ejecucion.css', [], filemtime( SYSMAN_SUITE_PATH . 'assets/css/ejecucion.css' ) );
        wp_enqueue_script( 'gn-ejecucion', SYSMAN_SUITE_URL . 'assets/js/ejecucion.js', [], filemtime( SYSMAN_SUITE_PATH . 'assets/js/ejecucion.js' ), true );
        wp_localize_script( 'gn-ejecucion', 'gnEjecFront', [
            'restUrl' => esc_url_raw( rest_url( '/' ) ),
        ] );

        return AccordionRenderer::render( $post_id );
    }
}
