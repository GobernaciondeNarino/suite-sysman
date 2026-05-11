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

        add_shortcode( 'gn_ejecucion', [ $this, 'shortcode' ] );
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

        $compania = sanitize_text_field( $_POST['compania'] ?? '001' );
        $anio     = absint( $_POST['anio'] ?? date( 'Y' ) );
        $mes      = absint( $_POST['mes'] ?? date( 'n' ) );

        $results = [];
        $client  = SysmanClient::instance();

        $plan_syncer = new PlanPresupuestalSyncer( $client );
        $result = $plan_syncer->sync( $compania, $anio, $mes );
        $results['plan'] = is_wp_error( $result )
            ? [ 'error' => $result->get_error_message() ]
            : $result;

        $ejec_syncer = new EjecucionConsolidadaSyncer( $client );
        $result = $ejec_syncer->sync( $compania, $anio, $mes );
        $results['ejecucion'] = is_wp_error( $result )
            ? [ 'error' => $result->get_error_message() ]
            : $result;

        $mov_syncer = new MovimientosSyncer( $client );

        $result = $mov_syncer->sync( $compania, $anio, $mes, 'DIS' );
        $results['dis'] = is_wp_error( $result )
            ? [ 'error' => $result->get_error_message() ]
            : $result;

        $result = $mov_syncer->sync( $compania, $anio, $mes, 'RES' );
        $results['res'] = is_wp_error( $result )
            ? [ 'error' => $result->get_error_message() ]
            : $result;

        update_option( 'gn_sisman_last_sync_ejecucion_module', current_time( 'mysql' ) );

        wp_send_json_success( $results );
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
