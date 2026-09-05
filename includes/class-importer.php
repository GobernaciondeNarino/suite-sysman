<?php
namespace SysmanSuite;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Importer {

    private const DEFAULT_API_URL = 'https://narino-gob.sysman.com.co/sysmanApi/autoservicio/v1/informesGobNar';
    private const TIMEOUT        = 300;

    private Database $database;
    private Logger   $logger;

    /**
     * Document types of the budget execution chain, in order.
     * numinforme=2 exposes each one through the tipo_cpte parameter.
     */
    public const CADENA_AUXILIAR = [
        'DIS' => 'Disponibilidades (CDP)',
        'RES' => 'Registros de compromiso (RP)',
        'OBL' => 'Obligaciones',
        'EGR' => 'Egresos (pagos)',
    ];

    private const REPORT_LABELS = [
        'ejecucion' => 'Ejecución Presupuestal de Gastos',
        'auxiliar'   => 'Auxiliar Presupuestal por Cuentas',
        'plan'       => 'Plan Presupuestal',
        'personal'   => 'Personal Activo de Nómina',
        'ingresos'   => 'Ejecución de Ingresos',
    ];

    public function __construct( Database $database, Logger $logger ) {
        $this->database = $database;
        $this->logger   = $logger;

        add_action( 'wp_ajax_sysman_start_import', [ $this, 'ajax_start_import' ] );
        add_action( 'wp_ajax_sysman_import_status', [ $this, 'ajax_import_status' ] );
        add_action( 'wp_ajax_sysman_check_duplicates', [ $this, 'ajax_check_duplicates' ] );
    }

    /** Nombre de la opción que hace de cerrojo entre importaciones. */
    private const LOCK_OPTION = 'sysman_import_lock';

    /** Un cerrojo más viejo que esto se considera huérfano y se recupera. */
    private const LOCK_TTL = 1800;

    /**
     * Take the import lock, so two imports can never overlap.
     *
     * Dos importaciones simultáneas (el cron mientras alguien importa a mano,
     * o un doble clic) hacen su DELETE antes de que la otra inserte, y las
     * filas de ambas acaban conviviendo: el periodo queda duplicado y las
     * cifras salen infladas. `add_option()` es atómico —option_name es único—
     * así que sirve de cerrojo sin condiciones de carrera.
     */
    public function adquirir_bloqueo( string $quien ): bool {
        $ahora = time();

        if ( add_option( self::LOCK_OPTION, [ 'quien' => $quien, 'desde' => $ahora ], '', false ) ) {
            return true;
        }

        $actual = get_option( self::LOCK_OPTION );
        $desde  = (int) ( $actual['desde'] ?? 0 );

        // Un proceso que murió a media importación dejaría el cerrojo puesto
        // para siempre: pasado el TTL se recupera.
        if ( $ahora - $desde > self::LOCK_TTL ) {
            $this->logger->log(
                'Cerrojo de importación huérfano (' . ( $actual['quien'] ?? '?' ) . "), se recupera para {$quien}.",
                'warning'
            );
            update_option( self::LOCK_OPTION, [ 'quien' => $quien, 'desde' => $ahora ], false );
            return true;
        }

        $this->logger->log(
            'Importación rechazada: ya hay otra en curso (' . ( $actual['quien'] ?? '?' ) . ').',
            'warning'
        );
        return false;
    }

    public function liberar_bloqueo(): void {
        delete_option( self::LOCK_OPTION );
    }

    /** Quién tiene el cerrojo ahora mismo, si alguien lo tiene. */
    public function importacion_en_curso(): array {
        $actual = get_option( self::LOCK_OPTION );
        return is_array( $actual ) ? $actual : [];
    }

    /** Qué tablas limpia cada informe. */
    private const TABLAS_POR_INFORME = [
        'ejecucion' => [ 'sysman_ejecucion_gastos' ],
        'auxiliar'  => [ 'sysman_auxiliar_cuentas' ],
        'plan'      => [ 'sysman_plan_presupuestal' ],
        'personal'  => [ 'sysman_personal_nomina' ],
        'ingresos'  => [ 'sysman_ejecucion_ingresos' ],
    ];

    /**
     * Wipe the period before importing it, so a re-import starts from zero.
     *
     * Solo se limpian las tablas del informe que se va a importar: borrar las
     * cinco para reimportar una sola dejaría las demás vacías.
     *
     * @param string $informe 'all' o la clave de un informe concreto.
     */
    public function limpiar_periodo( string $compania, int $anio, int $mes = 0, string $informe = 'all' ): array {
        $tablas = self::TABLAS_POR_INFORME[ $informe ] ?? [];
        return $this->database->purge_period( $compania, $anio, $mes, $tablas );
    }

    /**
     * Build the API URL.
     */
    private function build_url( string $compania, int $anio, ?int $mes, int $numinforme, array $extra = [] ): string {
        $params = [
            'compania'   => $compania,
            'anio'       => $anio,
            'numinforme' => $numinforme,
        ];
        if ( null !== $mes ) {
            $params['mes'] = $mes;
        }
        $params = array_merge( $params, $extra );

        $base_url = get_option( 'sysman_api_base_url', self::DEFAULT_API_URL );
        return $base_url . '?' . http_build_query( $params );
    }

    /**
     * Fetch data from the SYSMAN API.
     */
    private function fetch_api( string $url ): array {
        $this->logger->log( "Consultando API: {$url}" );
        $start = microtime( true );

        /**
         * SSL verification is on by default. If the SYSMAN endpoint uses a
         * certificate the server cannot validate, disable it explicitly with:
         * add_filter( 'sysman_suite_sslverify', '__return_false' );
         */
        $response = wp_remote_get( $url, [
            'timeout'   => self::TIMEOUT,
            'sslverify' => (bool) apply_filters( 'sysman_suite_sslverify', true ),
            'headers'   => [
                'Accept' => 'application/json',
            ],
        ] );

        $elapsed = round( microtime( true ) - $start, 2 );

        if ( is_wp_error( $response ) ) {
            $error = $response->get_error_message();
            $this->logger->log( "ERROR de conexión ({$elapsed}s): {$error}", 'error' );
            return [ 'success' => false, 'error' => $error, 'data' => [] ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( 200 !== $code ) {
            $this->logger->log( "ERROR: Respuesta HTTP {$code} ({$elapsed}s)", 'error' );
            return [ 'success' => false, 'error' => "HTTP {$code}", 'data' => [] ];
        }

        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $this->logger->log( 'ERROR al decodificar JSON: ' . json_last_error_msg(), 'error' );
            return [ 'success' => false, 'error' => 'JSON inválido', 'data' => [] ];
        }

        // SYSMAN API returns { codigo: 0, mensaje: "OK", cuerpo: [...] }
        if ( ! isset( $data['codigo'] ) || (int) $data['codigo'] !== 0 ) {
            $msg = $data['mensaje'] ?? 'Error desconocido';
            $this->logger->log( "ERROR API SYSMAN: {$msg}", 'error' );
            return [ 'success' => false, 'error' => $msg, 'data' => [] ];
        }

        $records = $data['cuerpo'] ?? [];
        $this->logger->log( "Registros obtenidos: " . count( $records ) . " ({$elapsed}s)" );

        return [ 'success' => true, 'error' => '', 'data' => $records ];
    }

    /**
     * Update the import status transient.
     */
    private function update_status( int $step, int $total, string $message, string $report_key = '' ): void {
        set_transient( 'sysman_import_status', [
            'running'      => true,
            'step'         => $step,
            'total'        => $total,
            'message'      => $message,
            'report_label' => self::REPORT_LABELS[ $report_key ] ?? '',
        ], HOUR_IN_SECONDS );
    }

    /**
     * Import Ejecución Presupuestal (numinforme=1).
     */
    public function import_ejecucion( string $compania, int $anio, int $mes ): array {
        global $wpdb;

        $this->logger->log( '--- Importando: ' . self::REPORT_LABELS['ejecucion'] . ' ---' );

        $url    = $this->build_url( $compania, $anio, $mes, 1 );
        $result = $this->fetch_api( $url );

        if ( ! $result['success'] ) {
            return $result;
        }

        $table    = $wpdb->prefix . 'sysman_ejecucion_gastos';
        $inserted = $this->database->insert_ejecucion_records( $result['data'], $table, $anio, $mes, $compania );

        $this->logger->log( "Resultado: {$inserted}/" . count( $result['data'] ) . " registros importados en ejecución." );

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
        $this->logger->log( '--- Importando: ' . self::REPORT_LABELS['auxiliar'] . " (tipo_cpte={$tipo_cpte}) ---" );

        $url    = $this->build_url( $compania, $anio, $mes, 2, [ 'tipo_cpte' => $tipo_cpte ] );
        $result = $this->fetch_api( $url );

        if ( ! $result['success'] ) {
            return $result;
        }

        $inserted = $this->database->insert_auxiliar_records( $result['data'], $anio, $mes, $compania, $tipo_cpte );

        $this->logger->log( "Resultado: {$inserted}/" . count( $result['data'] ) . " registros importados en auxiliar." );

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
        $this->logger->log( '--- Importando: ' . self::REPORT_LABELS['plan'] . ' ---' );

        $url    = $this->build_url( $compania, $anio, $mes, 4 );
        $result = $this->fetch_api( $url );

        if ( ! $result['success'] ) {
            return $result;
        }

        $inserted = $this->database->insert_plan_records( $result['data'], $anio, $mes, $compania );

        $this->logger->log( "Resultado: {$inserted}/" . count( $result['data'] ) . " registros importados en plan." );

        return [
            'success'  => true,
            'imported' => $inserted,
            'total'    => count( $result['data'] ),
            'report'   => 'plan',
        ];
    }

    /**
     * Import Personal Activo de Nómina (numinforme=5).
     * Note: This report does NOT use the 'mes' parameter.
     */
    public function import_personal( string $compania, int $anio ): array {
        $this->logger->log( '--- Importando: ' . self::REPORT_LABELS['personal'] . ' ---' );

        // This report does not use the 'mes' parameter.
        $url    = $this->build_url( $compania, $anio, null, 5 );
        $result = $this->fetch_api( $url );

        if ( ! $result['success'] ) {
            return $result;
        }

        $inserted = $this->database->insert_personal_records( $result['data'], $anio, $compania );

        $this->logger->log( "Resultado: {$inserted}/" . count( $result['data'] ) . " registros importados en personal." );

        return [
            'success'  => true,
            'imported' => $inserted,
            'total'    => count( $result['data'] ),
            'report'   => 'personal',
        ];
    }

    /**
     * Import Ejecución de Ingresos (numinforme=6).
     */
    public function import_ingresos( string $compania, int $anio, int $mes ): array {
        $this->logger->log( '--- Importando: ' . self::REPORT_LABELS['ingresos'] . ' ---' );

        $url    = $this->build_url( $compania, $anio, $mes, 6 );
        $result = $this->fetch_api( $url );

        if ( ! $result['success'] ) {
            return $result;
        }

        $inserted = $this->database->insert_ingresos_records( $result['data'], $anio, $mes, $compania );

        $this->logger->log( "Resultado: {$inserted}/" . count( $result['data'] ) . " registros importados en ingresos." );

        return [
            'success'  => true,
            'imported' => $inserted,
            'total'    => count( $result['data'] ),
            'report'   => 'ingresos',
        ];
    }

    /**
     * Run all imports.
     */
    public function import_all( string $compania, int $anio, int $mes ): array {
        $results = [];

        $total = 8;

        $this->update_status( 1, $total, 'Conectando con API SYSMAN...', 'ejecucion' );
        $results['ejecucion'] = $this->import_ejecucion( $compania, $anio, $mes );

        // Full execution chain: CDP (DIS) -> RP (RES) -> Obligacion (OBL) -> Pago (EGR).
        $paso = 2;
        foreach ( self::CADENA_AUXILIAR as $tipo => $etiqueta ) {
            $this->update_status( $paso, $total, "Importando {$etiqueta}...", 'auxiliar' );
            $results[ 'auxiliar_' . strtolower( $tipo ) ] = $this->import_auxiliar( $compania, $anio, $mes, $tipo );
            $paso++;
        }

        $this->update_status( $paso++, $total, 'Conectando con API SYSMAN...', 'plan' );
        $results['plan'] = $this->import_plan( $compania, $anio, $mes );

        $this->update_status( $paso++, $total, 'Conectando con API SYSMAN...', 'personal' );
        $results['personal'] = $this->import_personal( $compania, $anio );

        $this->update_status( $paso, $total, 'Conectando con API SYSMAN...', 'ingresos' );
        $results['ingresos'] = $this->import_ingresos( $compania, $anio, $mes );

        delete_transient( 'sysman_import_status' );
        $this->save_last_import( $compania, $anio, $mes, $results );

        return $results;
    }

    /**
     * Persist the summary of the most recent import.
     */
    private function save_last_import( string $compania, int $anio, int $mes, array $results ): void {
        update_option( 'sysman_last_import', [
            'date'     => current_time( 'mysql' ),
            'compania' => $compania,
            'anio'     => $anio,
            'mes'      => $mes,
            'results'  => $results,
        ] );
    }

    /**
     * AJAX: Start import.
     */
    public function ajax_start_import(): void {
        check_ajax_referer( 'sysman_import_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permisos insuficientes.' ] );
        }

        $compania = sanitize_text_field( $_POST['compania'] ?? '001' );
        $anio     = absint( $_POST['anio'] ?? current_time( 'Y' ) );
        $mes      = absint( $_POST['mes'] ?? current_time( 'n' ) );
        $report   = sanitize_text_field( $_POST['report'] ?? 'all' );

        if ( $anio < 2000 || $anio > 2100 ) {
            wp_send_json_error( [ 'message' => 'Año no válido.' ] );
        }

        if ( $mes < 1 || $mes > 12 ) {
            wp_send_json_error( [ 'message' => 'Mes no válido.' ] );
        }

        if ( ! $this->adquirir_bloqueo( 'admin:' . wp_get_current_user()->user_login ) ) {
            wp_send_json_error( [
                'message' => 'Ya hay una importación en curso. Espere a que termine para no duplicar los datos.',
            ] );
        }

        // Limpieza previa opcional: borra el periodo completo en las cinco
        // tablas antes de importar. Es la salida cuando ya hay duplicados.
        $limpiar = ! empty( $_POST['limpiar'] ) && 'no' !== $_POST['limpiar'];
        if ( $limpiar ) {
            $this->limpiar_periodo( $compania, $anio, $mes, $report );
        }

        // Log import start
        $this->logger->log( '======================================================', 'info' );
        $this->logger->log( 'INICIO DE IMPORTACIÓN', 'info' );
        $this->logger->log( "Parámetros: Compañía={$compania}, Año={$anio}, Mes={$mes}, Informe={$report}", 'info' );
        $this->logger->log( 'Usuario: ' . wp_get_current_user()->user_login, 'info' );
        $this->logger->log( '======================================================', 'info' );

        $start_time = microtime( true );
        $error_count = 0;

        $results = [];

        try {
        switch ( $report ) {
            case 'ejecucion':
                $this->update_status( 1, 1, 'Importando...', 'ejecucion' );
                $results = [ 'ejecucion' => $this->import_ejecucion( $compania, $anio, $mes ) ];
                if ( ! $results['ejecucion']['success'] ) $error_count++;
                break;
            case 'auxiliar':
                $tipo_cpte = sanitize_text_field( $_POST['tipo_cpte'] ?? 'RES' );
                // Al limpiar se borra el auxiliar completo del periodo, así que
                // hay que traer los cuatro tipos o los otros tres quedarían vacíos.
                $tipos = $limpiar ? array_keys( self::CADENA_AUXILIAR ) : [ $tipo_cpte ];
                $paso_aux = 1;
                foreach ( $tipos as $tipo ) {
                    $this->update_status( $paso_aux, count( $tipos ), 'Importando...', 'auxiliar' );
                    $clave = 1 === count( $tipos ) ? 'auxiliar' : 'auxiliar_' . strtolower( $tipo );
                    $results[ $clave ] = $this->import_auxiliar( $compania, $anio, $mes, $tipo );
                    if ( ! $results[ $clave ]['success'] ) $error_count++;
                    $paso_aux++;
                }
                break;
            case 'plan':
                $this->update_status( 1, 1, 'Importando...', 'plan' );
                $results = [ 'plan' => $this->import_plan( $compania, $anio, $mes ) ];
                if ( ! $results['plan']['success'] ) $error_count++;
                break;
            case 'personal':
                $this->update_status( 1, 1, 'Importando...', 'personal' );
                $results = [ 'personal' => $this->import_personal( $compania, $anio ) ];
                if ( ! $results['personal']['success'] ) $error_count++;
                break;
            case 'ingresos':
                $this->update_status( 1, 1, 'Importando...', 'ingresos' );
                $results = [ 'ingresos' => $this->import_ingresos( $compania, $anio, $mes ) ];
                if ( ! $results['ingresos']['success'] ) $error_count++;
                break;
            default:
                $results = $this->import_all( $compania, $anio, $mes );
                foreach ( $results as $r ) {
                    if ( ! $r['success'] ) $error_count++;
                }
                break;
        }

        } catch ( \Throwable $e ) {
            // Un fallo a media importación no puede dejar el cerrojo puesto.
            $this->liberar_bloqueo();
            delete_transient( 'sysman_import_status' );
            $this->logger->log( 'Importación interrumpida: ' . $e->getMessage(), 'error' );
            wp_send_json_error( [ 'message' => 'La importación falló: ' . $e->getMessage() ] );
        }

        delete_transient( 'sysman_import_status' );
        $this->save_last_import( $compania, $anio, $mes, $results );

        // Log import end
        $elapsed = round( microtime( true ) - $start_time, 2 );
        $total_imported = 0;
        $total_records  = 0;
        foreach ( $results as $r ) {
            if ( isset( $r['imported'] ) ) $total_imported += $r['imported'];
            if ( isset( $r['total'] ) ) $total_records += $r['total'];
        }

        $this->logger->log( '======================================================', 'info' );
        $this->logger->log( 'FIN DE IMPORTACIÓN', 'info' );
        $this->logger->log( "Duración: {$elapsed} segundos", 'info' );
        $this->logger->log( "Registros importados: {$total_imported} / {$total_records}", 'info' );
        $this->logger->log( "Errores: {$error_count}", $error_count > 0 ? 'warning' : 'info' );
        $this->logger->log( '======================================================', 'info' );

        $this->liberar_bloqueo();

        wp_send_json_success( [
            'message' => 'Importación completada.',
            'results' => $results,
        ] );
    }

    /**
     * AJAX: integrity report — rows vs. distinct records per table and period.
     */
    public function ajax_check_duplicates(): void {
        check_ajax_referer( 'sysman_import_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permisos insuficientes.' ] );
        }

        $anio = absint( $_POST['anio'] ?? 0 );

        wp_send_json_success( [
            'anio'     => $anio,
            'periodos' => $this->database->duplicate_report( $anio ),
        ] );
    }

    /**
     * AJAX: Check import status.
     */
    public function ajax_import_status(): void {
        check_ajax_referer( 'sysman_import_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permisos insuficientes.' ] );
        }

        $status = get_transient( 'sysman_import_status' );

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
        $compania = get_option( 'sysman_api_compania', '001' );
        $anio     = (int) get_option( 'sysman_api_anio', current_time( 'Y' ) );
        $mes      = (int) get_option( 'sysman_api_mes', current_time( 'n' ) );

        // Si alguien está importando a mano, el cron se salta este turno: dos
        // importaciones a la vez duplican el periodo.
        if ( ! $this->adquirir_bloqueo( 'cron' ) ) {
            return;
        }

        $this->logger->log( '======================================================', 'info' );
        $this->logger->log( 'INICIO DE IMPORTACIÓN PROGRAMADA (CRON)', 'info' );
        $this->logger->log( '======================================================', 'info' );

        $start = microtime( true );

        try {
            $this->import_all( $compania, $anio, $mes );
        } finally {
            $this->liberar_bloqueo();
        }

        $elapsed = round( microtime( true ) - $start, 2 );
        $this->logger->log( '======================================================', 'info' );
        $this->logger->log( "FIN DE IMPORTACIÓN PROGRAMADA - Duración: {$elapsed}s", 'info' );
        $this->logger->log( '======================================================', 'info' );
    }
}
