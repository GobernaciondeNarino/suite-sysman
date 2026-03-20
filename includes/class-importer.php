<?php
namespace SismanSuite;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Importer {

    private const API_BASE_URL = 'https://narino-gob.sysman.com.co/sysmanApi/autoservicio/v1/informesGobNar';
    private const TIMEOUT      = 60;

    private Database $database;
    private Logger   $logger;

    public function __construct( Database $database, Logger $logger ) {
        $this->database = $database;
        $this->logger   = $logger;

        add_action( 'wp_ajax_sisman_start_import', [ $this, 'ajax_start_import' ] );
        add_action( 'wp_ajax_sisman_import_status', [ $this, 'ajax_import_status' ] );
    }

    /**
     * Build the API URL.
     */
    private function build_url( string $compania, int $anio, int $mes, int $numinforme, array $extra = [] ): string {
        $params = array_merge( [
            'compania'   => $compania,
            'anio'       => $anio,
            'mes'        => $mes,
            'numinforme' => $numinforme,
        ], $extra );

        return self::API_BASE_URL . '?' . http_build_query( $params );
    }

    /**
     * Fetch data from the SISMAN API.
     */
    private function fetch_api( string $url ): array {
        $this->logger->log( "Consultando API: {$url}" );

        $response = wp_remote_get( $url, [
            'timeout'   => self::TIMEOUT,
            'sslverify' => true,
            'headers'   => [
                'Accept' => 'application/json',
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            $error = $response->get_error_message();
            $this->logger->log( "Error de conexión: {$error}" );
            return [ 'success' => false, 'error' => $error, 'data' => [] ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( 200 !== $code ) {
            $this->logger->log( "Respuesta HTTP {$code}" );
            return [ 'success' => false, 'error' => "HTTP {$code}", 'data' => [] ];
        }

        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $this->logger->log( 'Error al decodificar JSON: ' . json_last_error_msg() );
            return [ 'success' => false, 'error' => 'JSON inválido', 'data' => [] ];
        }

        // SISMAN API returns { codigo: 0, mensaje: "OK", cuerpo: [...] }
        if ( ! isset( $data['codigo'] ) || (int) $data['codigo'] !== 0 ) {
            $msg = $data['mensaje'] ?? 'Error desconocido';
            $this->logger->log( "Error API SISMAN: {$msg}" );
            return [ 'success' => false, 'error' => $msg, 'data' => [] ];
        }

        $records = $data['cuerpo'] ?? [];
        $this->logger->log( 'Registros obtenidos: ' . count( $records ) );

        return [ 'success' => true, 'error' => '', 'data' => $records ];
    }

    /**
     * Import Ejecución Presupuestal (numinforme=1).
     */
    public function import_ejecucion( string $compania, int $anio, int $mes ): array {
        global $wpdb;

        $url    = $this->build_url( $compania, $anio, $mes, 1 );
        $result = $this->fetch_api( $url );

        if ( ! $result['success'] ) {
            return $result;
        }

        $table    = $wpdb->prefix . 'sisman_ejecucion_gastos';
        $inserted = $this->database->insert_ejecucion_records( $result['data'], $table, $anio, $mes, $compania );

        return [
            'success'  => true,
            'imported' => $inserted,
            'total'    => count( $result['data'] ),
            'report'   => 'ejecucion',
        ];
    }

    /**
     * Import Auxiliar Presupuestal (numinforme=2).
     */
    public function import_auxiliar( string $compania, int $anio, int $mes, string $tipo_cpte = 'RES' ): array {
        $url    = $this->build_url( $compania, $anio, $mes, 2, [ 'tipo_cpte' => $tipo_cpte ] );
        $result = $this->fetch_api( $url );

        if ( ! $result['success'] ) {
            return $result;
        }

        $inserted = $this->database->insert_auxiliar_records( $result['data'], $anio, $mes, $compania, $tipo_cpte );

        return [
            'success'  => true,
            'imported' => $inserted,
            'total'    => count( $result['data'] ),
            'report'   => 'auxiliar',
        ];
    }

    /**
     * Import Plan Presupuestal (numinforme=4).
     */
    public function import_plan( string $compania, int $anio, int $mes ): array {
        global $wpdb;

        $url    = $this->build_url( $compania, $anio, $mes, 4 );
        $result = $this->fetch_api( $url );

        if ( ! $result['success'] ) {
            return $result;
        }

        $table    = $wpdb->prefix . 'sisman_plan_presupuestal';
        $inserted = $this->database->insert_ejecucion_records( $result['data'], $table, $anio, $mes, $compania );

        return [
            'success'  => true,
            'imported' => $inserted,
            'total'    => count( $result['data'] ),
            'report'   => 'plan',
        ];
    }

    /**
     * Run all imports.
     */
    public function import_all( string $compania, int $anio, int $mes ): array {
        $results = [];

        set_transient( 'sisman_import_status', [
            'running' => true,
            'step'    => 1,
            'total'   => 3,
            'message' => 'Importando Ejecución Presupuestal de Gastos...',
        ], HOUR_IN_SECONDS );

        $results['ejecucion'] = $this->import_ejecucion( $compania, $anio, $mes );

        set_transient( 'sisman_import_status', [
            'running' => true,
            'step'    => 2,
            'total'   => 3,
            'message' => 'Importando Auxiliar Presupuestal por Cuentas...',
        ], HOUR_IN_SECONDS );

        $results['auxiliar'] = $this->import_auxiliar( $compania, $anio, $mes );

        set_transient( 'sisman_import_status', [
            'running' => true,
            'step'    => 3,
            'total'   => 3,
            'message' => 'Importando Plan Presupuestal...',
        ], HOUR_IN_SECONDS );

        $results['plan'] = $this->import_plan( $compania, $anio, $mes );

        delete_transient( 'sisman_import_status' );

        // Save last import info
        update_option( 'sisman_last_import', [
            'date'     => current_time( 'mysql' ),
            'compania' => $compania,
            'anio'     => $anio,
            'mes'      => $mes,
            'results'  => $results,
        ] );

        $this->logger->log( 'Importación completa finalizada.' );

        return $results;
    }

    /**
     * AJAX: Start import.
     */
    public function ajax_start_import(): void {
        check_ajax_referer( 'sisman_import_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permisos insuficientes.' ] );
        }

        $compania = sanitize_text_field( $_POST['compania'] ?? '001' );
        $anio     = absint( $_POST['anio'] ?? date( 'Y' ) );
        $mes      = absint( $_POST['mes'] ?? date( 'n' ) );
        $report   = sanitize_text_field( $_POST['report'] ?? 'all' );

        if ( $anio < 2000 || $anio > 2100 ) {
            wp_send_json_error( [ 'message' => 'Año no válido.' ] );
        }

        if ( $mes < 1 || $mes > 12 ) {
            wp_send_json_error( [ 'message' => 'Mes no válido.' ] );
        }

        $this->logger->log( "Iniciando importación: Compañía={$compania}, Año={$anio}, Mes={$mes}, Informe={$report}" );

        switch ( $report ) {
            case 'ejecucion':
                $results = [ 'ejecucion' => $this->import_ejecucion( $compania, $anio, $mes ) ];
                break;
            case 'auxiliar':
                $tipo_cpte = sanitize_text_field( $_POST['tipo_cpte'] ?? 'RES' );
                $results = [ 'auxiliar' => $this->import_auxiliar( $compania, $anio, $mes, $tipo_cpte ) ];
                break;
            case 'plan':
                $results = [ 'plan' => $this->import_plan( $compania, $anio, $mes ) ];
                break;
            default:
                $results = $this->import_all( $compania, $anio, $mes );
                break;
        }

        wp_send_json_success( [
            'message' => 'Importación completada.',
            'results' => $results,
        ] );
    }

    /**
     * AJAX: Check import status.
     */
    public function ajax_import_status(): void {
        check_ajax_referer( 'sisman_import_nonce', 'nonce' );

        $status = get_transient( 'sisman_import_status' );

        if ( ! $status ) {
            wp_send_json_success( [
                'running' => false,
                'message' => 'No hay importación en curso.',
            ] );
        }

        wp_send_json_success( $status );
    }

    /**
     * Scheduled import via WP Cron.
     */
    public function run_scheduled_import(): void {
        $compania = get_option( 'sisman_api_compania', '001' );
        $anio     = (int) get_option( 'sisman_api_anio', date( 'Y' ) );
        $mes      = (int) get_option( 'sisman_api_mes', date( 'n' ) );

        $this->logger->log( 'Iniciando importación programada (cron).' );
        $this->import_all( $compania, $anio, $mes );
    }
}
