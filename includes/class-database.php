<?php
namespace SysmanSuite;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Database {

    private Logger $logger;

    /** Cache for column validation */
    private array $column_cache = [];

    public function __construct( Logger $logger ) {
        $this->logger = $logger;
    }

    /**
     * Create the plugin database tables.
     */
    public function create_tables(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Table 1: Ejecución Presupuestal de Gastos (numinforme=1)
        $table_ejecucion = $wpdb->prefix . 'sysman_ejecucion_gastos';
        $sql_ejecucion = "CREATE TABLE {$table_ejecucion} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            anio INT NOT NULL DEFAULT 0,
            mes INT NOT NULL DEFAULT 0,
            compania VARCHAR(10) NOT NULL DEFAULT '001',
            codigocuenta VARCHAR(50) NOT NULL DEFAULT '',
            nombrerubro VARCHAR(500) NOT NULL DEFAULT '',
            movimiento VARCHAR(10) NOT NULL DEFAULT '',
            destino VARCHAR(100) NOT NULL DEFAULT '',
            bpid VARCHAR(50) NOT NULL DEFAULT '0',
            apropiacioninicial DECIMAL(20,2) NOT NULL DEFAULT 0,
            adicion DECIMAL(20,2) NOT NULL DEFAULT 0,
            reduccion DECIMAL(20,2) NOT NULL DEFAULT 0,
            credito DECIMAL(20,2) NOT NULL DEFAULT 0,
            contracredito DECIMAL(20,2) NOT NULL DEFAULT 0,
            aplazamiento DECIMAL(20,2) NOT NULL DEFAULT 0,
            desplazamiento DECIMAL(20,2) NOT NULL DEFAULT 0,
            apropiacionvigente DECIMAL(20,2) NOT NULL DEFAULT 0,
            disponibilidades DECIMAL(20,2) NOT NULL DEFAULT 0,
            saldodisponible DECIMAL(20,2) NOT NULL DEFAULT 0,
            compromisos DECIMAL(20,2) NOT NULL DEFAULT 0,
            disponibilidadesabiertas DECIMAL(20,2) NOT NULL DEFAULT 0,
            obligacion DECIMAL(20,2) NOT NULL DEFAULT 0,
            pagos DECIMAL(20,2) NOT NULL DEFAULT 0,
            obligacionesporpagar DECIMAL(20,2) NOT NULL DEFAULT 0,
            fecha_importacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_anio_mes (anio, mes),
            KEY idx_codigocuenta (codigocuenta),
            KEY idx_destino (destino),
            KEY idx_compania (compania)
        ) {$charset};";

        dbDelta( $sql_ejecucion );

        // Table 2: Auxiliar Presupuestal por Cuentas (numinforme=2)
        $table_auxiliar = $wpdb->prefix . 'sysman_auxiliar_cuentas';
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_auxiliar ) ) !== $table_auxiliar ) {
            $wpdb->query( \SysmanSuite\Ejecucion\Schema::auxiliar_cuentas_sql( $wpdb->prefix, $charset ) );
        }

        // Table 3: Plan Presupuestal (numinforme=4)
        $table_plan = $wpdb->prefix . 'sysman_plan_presupuestal';
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_plan ) ) !== $table_plan ) {
            $wpdb->query( \SysmanSuite\Ejecucion\Schema::plan_presupuestal_sql( $wpdb->prefix, $charset ) );
        }

        // Table 4: Personal Activo de Nómina (numinforme=5)
        $table_personal = $wpdb->prefix . 'sysman_personal_nomina';
        $sql_personal = "CREATE TABLE {$table_personal} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            anio INT NOT NULL DEFAULT 0,
            compania VARCHAR(10) NOT NULL DEFAULT '001',
            iddeempleado VARCHAR(20) NOT NULL DEFAULT '',
            apellido1 VARCHAR(200) NOT NULL DEFAULT '',
            apellido2 VARCHAR(200) NOT NULL DEFAULT '',
            nombres VARCHAR(200) NOT NULL DEFAULT '',
            numerodcto VARCHAR(30) NOT NULL DEFAULT '',
            expedida VARCHAR(50) NOT NULL DEFAULT '',
            fechancto VARCHAR(20) NOT NULL DEFAULT '',
            fechadeingreso VARCHAR(20) NOT NULL DEFAULT '',
            fechaderetiro VARCHAR(20) NOT NULL DEFAULT '',
            iddecargo VARCHAR(20) NOT NULL DEFAULT '',
            nombredelcargo VARCHAR(300) NOT NULL DEFAULT '',
            iddecategoria VARCHAR(20) NOT NULL DEFAULT '',
            nombrecategoria VARCHAR(200) NOT NULL DEFAULT '',
            escalafon VARCHAR(10) NOT NULL DEFAULT '',
            nombreescalafon VARCHAR(100) NOT NULL DEFAULT '',
            grado VARCHAR(10) NOT NULL DEFAULT '',
            decarrera VARCHAR(10) NOT NULL DEFAULT '',
            salariobaseibc DECIMAL(20,2) NOT NULL DEFAULT 0,
            dependencianombre VARCHAR(500) NOT NULL DEFAULT '',
            emailcorporativo VARCHAR(200) NOT NULL DEFAULT '',
            emailpersonal VARCHAR(200) NOT NULL DEFAULT '',
            direccion VARCHAR(500) NOT NULL DEFAULT '',
            telefonos VARCHAR(200) NOT NULL DEFAULT '',
            fechacumplimientobonificacion VARCHAR(20) NOT NULL DEFAULT '',
            fecha_importacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_anio (anio),
            KEY idx_compania (compania),
            KEY idx_numerodcto (numerodcto),
            KEY idx_nombredelcargo (nombredelcargo(100)),
            KEY idx_dependencia (dependencianombre(100))
        ) {$charset};";

        dbDelta( $sql_personal );

        // Table 5: Ejecución de Ingresos (numinforme=6)
        $table_ingresos = $wpdb->prefix . 'sysman_ejecucion_ingresos';
        $sql_ingresos = "CREATE TABLE {$table_ingresos} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            anio INT NOT NULL DEFAULT 0,
            mes INT NOT NULL DEFAULT 0,
            compania VARCHAR(10) NOT NULL DEFAULT '001',
            cuenta VARCHAR(50) NOT NULL DEFAULT '',
            codigo VARCHAR(50) NOT NULL DEFAULT '',
            nombre VARCHAR(500) NOT NULL DEFAULT '',
            movimiento VARCHAR(10) NOT NULL DEFAULT '',
            tiporecurso VARCHAR(100) NOT NULL DEFAULT '',
            fuenterecurso VARCHAR(100) NOT NULL DEFAULT '',
            apropiado DECIMAL(20,2) NOT NULL DEFAULT 0,
            modificaciones DECIMAL(20,2) NOT NULL DEFAULT 0,
            totalpresupuesto DECIMAL(20,2) NOT NULL DEFAULT 0,
            recaudosanteriores DECIMAL(20,2) NOT NULL DEFAULT 0,
            recaudosmes DECIMAL(20,2) NOT NULL DEFAULT 0,
            recaudosacumulados DECIMAL(20,2) NOT NULL DEFAULT 0,
            porrecaudar DECIMAL(20,2) NOT NULL DEFAULT 0,
            porcrecaudado DECIMAL(10,2) NOT NULL DEFAULT 0,
            fecha_importacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_anio_mes (anio, mes),
            KEY idx_compania (compania),
            KEY idx_cuenta (cuenta),
            KEY idx_codigo (codigo)
        ) {$charset};";

        dbDelta( $sql_ingresos );

        $this->logger->log( 'Tablas de base de datos creadas/actualizadas correctamente.' );
    }

    /**
     * Get the list of available tables for visualization.
     */
    public function get_available_tables(): array {
        global $wpdb;
        return [
            $wpdb->prefix . 'sysman_ejecucion_gastos'    => __( 'Ejecución Presupuestal de Gastos', 'sysman-suite' ),
            $wpdb->prefix . 'sysman_auxiliar_cuentas'     => __( 'Auxiliar Presupuestal por Cuentas', 'sysman-suite' ),
            $wpdb->prefix . 'sysman_plan_presupuestal'    => __( 'Plan Presupuestal', 'sysman-suite' ),
            $wpdb->prefix . 'sysman_personal_nomina'      => __( 'Personal Activo de Nómina', 'sysman-suite' ),
            $wpdb->prefix . 'sysman_ejecucion_ingresos'   => __( 'Ejecución de Ingresos', 'sysman-suite' ),
        ];
    }

    /**
     * Validate a table name against the whitelist.
     */
    public function validate_table( string $table ): bool {
        return array_key_exists( $table, $this->get_available_tables() );
    }

    /**
     * Validate a column exists in the given table.
     */
    public function validate_column( string $table, string $column ): bool {
        if ( ! $this->validate_table( $table ) ) {
            return false;
        }

        if ( ! isset( $this->column_cache[ $table ] ) ) {
            global $wpdb;
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $columns = $wpdb->get_col( "DESCRIBE `{$table}`", 0 );
            $this->column_cache[ $table ] = $columns ?: [];
        }

        return in_array( $column, $this->column_cache[ $table ], true );
    }

    /**
     * Get columns for a table.
     */
    public function get_table_columns( string $table ): array {
        if ( ! $this->validate_table( $table ) ) {
            return [];
        }

        if ( ! isset( $this->column_cache[ $table ] ) ) {
            global $wpdb;
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $columns = $wpdb->get_col( "DESCRIBE `{$table}`", 0 );
            $this->column_cache[ $table ] = $columns ?: [];
        }

        return $this->column_cache[ $table ];
    }

    /**
     * Check if a table actually exists in the database.
     */
    public function ensure_table_exists( string $table ): bool {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $found = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
        return ( null !== $found );
    }

    /**
     * Get the field map for ejecucion/plan tables (API field name => DB column).
     */
    public function get_ejecucion_field_map(): array {
        return [
            'codigocuenta'           => 'codigocuenta',
            'nombrerubro'            => 'nombrerubro',
            'movimiento'             => 'movimiento',
            'destino'                => 'destino',
            'bpid'                   => 'bpid',
            'apropiacioninicial'     => 'apropiacioninicial',
            'adicion'                => 'adicion',
            'reduccion'              => 'reduccion',
            'credito'                => 'credito',
            'contracredito'          => 'contracredito',
            'aplazamiento'           => 'aplazamiento',
            'desplazaminento'        => 'desplazamiento', // Note: API typo
            'desplazamiento'         => 'desplazamiento',
            'apropiacionvigente'     => 'apropiacionvigente',
            'disponibilidades'       => 'disponibilidades',
            'saldodisponible'        => 'saldodisponible',
            'compromisos'            => 'compromisos',
            'disponibilidadesabiertas' => 'disponibilidadesabiertas',
            'obligacion'             => 'obligacion',
            'pagos'                  => 'pagos',
            'obligacionesporpagar'   => 'obligacionesporpagar',
        ];
    }

    /**
     * Get the field map for auxiliar table.
     */
    public function get_auxiliar_field_map(): array {
        return [
            'numero'              => 'numero',
            'nombrepred'          => 'nombrepred',
            'idprede'             => 'idprede',
            'nombreplan'          => 'nombreplan',
            'rubro'               => 'rubro',
            'fecha'               => 'fecha',
            'tipocpte'            => 'tipocpte',
            'tercero'             => 'tercero',
            'nombretercero'       => 'nombretercero',
            'descripcion'         => 'descripcion',
            'nrodocumento'        => 'nrodocumento',
            'valordebito'         => 'valordebito',
            'valorcredito'        => 'valorcredito',
            'debitoafectado'      => 'debitoafectado',
            'creditoafectado'     => 'creditoafectado',
            'modificaciondebito'  => 'modificaciondebito',
            'modificacioncredito' => 'modificacioncredito',
            'saldoporejecutaresp' => 'saldoporejecutaresp',
            'tipocpteafect'       => 'tipocpteafect',
            'cmpteafectado'       => 'cmpteafectado',
        ];
    }

    /**
     * Get the field map for personal/nomina table (API field name => DB column).
     */
    public function get_personal_field_map(): array {
        return [
            'iddeempleado'                  => 'iddeempleado',
            'apellido1'                     => 'apellido1',
            'apellido2'                     => 'apellido2',
            'nombres'                       => 'nombres',
            'numerodcto'                    => 'numerodcto',
            'expedida'                      => 'expedida',
            'fechancto'                     => 'fechancto',
            'fechadeingreso'                => 'fechadeingreso',
            'fechaderetiro'                 => 'fechaderetiro',
            'iddecargo'                     => 'iddecargo',
            'nombredelcargo'                => 'nombredelcargo',
            'iddecategoria'                 => 'iddecategoria',
            'nombrecategoria'               => 'nombrecategoria',
            'escalafon'                     => 'escalafon',
            'nombreescalafon'               => 'nombreescalafon',
            'grado'                         => 'grado',
            'decarrera'                     => 'decarrera',
            'salariobaseibc'                => 'salariobaseibc',
            'dependenciaNombre'             => 'dependencianombre',
            'emailcorporativo'              => 'emailcorporativo',
            'emailpersonal'                 => 'emailpersonal',
            'direccion'                     => 'direccion',
            'telefonos'                     => 'telefonos',
            'fechacumplimientobonificacion' => 'fechacumplimientobonificacion',
        ];
    }

    /**
     * Get the field map for ingresos table (API field name => DB column).
     */
    public function get_ingresos_field_map(): array {
        return [
            'cuenta'             => 'cuenta',
            'codigo'             => 'codigo',
            'nombre'             => 'nombre',
            'movimiento'         => 'movimiento',
            'tipoRecurso'        => 'tiporecurso',
            'fuenteRecurso'      => 'fuenterecurso',
            'apropiado'          => 'apropiado',
            'modificaciones'     => 'modificaciones',
            'totalPresupuesto'   => 'totalpresupuesto',
            'recaudosAnteriores' => 'recaudosanteriores',
            'recaudosMes'        => 'recaudosmes',
            'recaudosAcumulados' => 'recaudosacumulados',
            'porRecaudar'        => 'porrecaudar',
            'porcRecaudado'      => 'porcrecaudado',
        ];
    }

    /**
     * Insert records into ejecucion or plan table.
     */
    public function insert_ejecucion_records( array $records, string $table_name, int $anio, int $mes, string $compania ): int {
        global $wpdb;

        if ( ! $this->validate_table( $table_name ) ) {
            $this->logger->log( "Tabla no válida: {$table_name}" );
            return 0;
        }

        if ( ! $this->ensure_table_exists( $table_name ) ) {
            $this->logger->log( "La tabla no existe en la base de datos: {$table_name}" );
            return 0;
        }

        $field_map = $this->get_ejecucion_field_map();
        $inserted  = 0;

        // Delete existing records for same year and company to avoid duplicates
        $wpdb->delete( $table_name, [
            'anio'     => $anio,
            'compania' => $compania,
        ], [ '%d', '%s' ] );

        foreach ( $records as $index => $record ) {
            // Log the first record's keys for debugging field mapping
            if ( 0 === $index ) {
                $record_keys = implode( ', ', array_keys( $record ) );
                $this->logger->log( "Claves del primer registro API (ejecucion): {$record_keys}" );
            }

            $data = [
                'anio'     => $anio,
                'mes'      => $mes,
                'compania' => $compania,
            ];

            foreach ( $field_map as $api_field => $db_column ) {
                if ( isset( $record[ $api_field ] ) ) {
                    $value = $record[ $api_field ];
                    // Convert numeric strings
                    if ( in_array( $db_column, [
                        'apropiacioninicial', 'adicion', 'reduccion', 'credito', 'contracredito',
                        'aplazamiento', 'desplazamiento', 'apropiacionvigente', 'disponibilidades',
                        'saldodisponible', 'compromisos', 'disponibilidadesabiertas', 'obligacion',
                        'pagos', 'obligacionesporpagar',
                    ], true ) ) {
                        $data[ $db_column ] = floatval( $value );
                    } else {
                        $data[ $db_column ] = sanitize_text_field( $value );
                    }
                }
            }

            $result = $wpdb->insert( $table_name, $data );
            if ( false === $result ) {
                $this->logger->log( "Error al insertar registro en {$table_name}: {$wpdb->last_error}" );
                break;
            }
            $inserted++;
        }

        $this->logger->log( "Insertados {$inserted} registros en {$table_name} (Año: {$anio}, Mes: {$mes})." );
        return $inserted;
    }

    /**
     * Insert records into auxiliar table.
     */
    public function insert_auxiliar_records( array $records, int $anio, int $mes, string $compania, string $tipo_cpte ): int {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sysman_auxiliar_cuentas';
        $field_map  = $this->get_auxiliar_field_map();
        $numeric_cols = [
            'valordebito', 'valorcredito', 'debitoafectado', 'creditoafectado',
            'modificaciondebito', 'modificacioncredito', 'saldoporejecutaresp',
        ];
        $inserted   = 0;

        if ( ! $this->ensure_table_exists( $table_name ) ) {
            $this->logger->log( "La tabla no existe en la base de datos: {$table_name}" );
            return 0;
        }

        // Delete only records matching year, month, company AND tipo_cpte
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$table_name} WHERE compania = %s AND anio = %d AND mes = %d AND tipocpte = %s",
            $compania, $anio, $mes, $tipo_cpte
        ) );

        foreach ( $records as $index => $record ) {
            if ( 0 === $index ) {
                $record_keys = implode( ', ', array_keys( $record ) );
                $this->logger->log( "Claves del primer registro API (auxiliar): {$record_keys}" );
            }

            $data = [
                'anio'     => $anio,
                'mes'      => $mes,
                'compania' => $compania,
            ];

            foreach ( $field_map as $api_field => $db_column ) {
                if ( isset( $record[ $api_field ] ) ) {
                    $value = $record[ $api_field ];
                    if ( in_array( $db_column, $numeric_cols, true ) ) {
                        $data[ $db_column ] = floatval( $value );
                    } else {
                        $data[ $db_column ] = sanitize_text_field( $value );
                    }
                }
            }

            $result = $wpdb->insert( $table_name, $data );
            if ( false === $result ) {
                $this->logger->log( "Error al insertar registro en {$table_name}: {$wpdb->last_error}" );
                break;
            }
            $inserted++;
        }

        $this->logger->log( "Insertados {$inserted} registros auxiliares (Año: {$anio}, Mes: {$mes}, Tipo: {$tipo_cpte})." );
        return $inserted;
    }

    /**
     * Insert records into personal_nomina table (numinforme=5).
     * Note: Personal data has no 'mes' field.
     */
    public function insert_personal_records( array $records, int $anio, string $compania ): int {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sysman_personal_nomina';
        $field_map  = $this->get_personal_field_map();
        $inserted   = 0;

        if ( ! $this->ensure_table_exists( $table_name ) ) {
            $this->logger->log( "La tabla no existe en la base de datos: {$table_name}" );
            return 0;
        }

        // Delete existing records for same year and company
        $wpdb->delete( $table_name, [
            'anio'     => $anio,
            'compania' => $compania,
        ], [ '%d', '%s' ] );

        foreach ( $records as $index => $record ) {
            if ( 0 === $index ) {
                $record_keys = implode( ', ', array_keys( $record ) );
                $this->logger->log( "Claves del primer registro API (personal): {$record_keys}" );
            }

            $data = [
                'anio'     => $anio,
                'compania' => $compania,
            ];

            foreach ( $field_map as $api_field => $db_column ) {
                if ( isset( $record[ $api_field ] ) ) {
                    $value = $record[ $api_field ];
                    if ( 'salariobaseibc' === $db_column ) {
                        $data[ $db_column ] = floatval( $value );
                    } else {
                        $data[ $db_column ] = sanitize_text_field( $value );
                    }
                }
            }

            $result = $wpdb->insert( $table_name, $data );
            if ( false === $result ) {
                $this->logger->log( "Error al insertar registro en {$table_name}: {$wpdb->last_error}" );
                break;
            }
            $inserted++;
        }

        $this->logger->log( "Insertados {$inserted} registros de personal (Año: {$anio}, Compañía: {$compania})." );
        return $inserted;
    }

    /**
     * Insert records into ejecucion_ingresos table (numinforme=6).
     */
    public function insert_ingresos_records( array $records, int $anio, int $mes, string $compania ): int {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sysman_ejecucion_ingresos';
        $field_map  = $this->get_ingresos_field_map();
        $numeric_cols = [ 'apropiado', 'modificaciones', 'totalpresupuesto', 'recaudosanteriores', 'recaudosmes', 'recaudosacumulados', 'porrecaudar', 'porcrecaudado' ];
        $inserted   = 0;

        if ( ! $this->ensure_table_exists( $table_name ) ) {
            $this->logger->log( "La tabla no existe en la base de datos: {$table_name}" );
            return 0;
        }

        // Delete existing records for same year and company
        $wpdb->delete( $table_name, [
            'anio'     => $anio,
            'compania' => $compania,
        ], [ '%d', '%s' ] );

        foreach ( $records as $index => $record ) {
            if ( 0 === $index ) {
                $record_keys = implode( ', ', array_keys( $record ) );
                $this->logger->log( "Claves del primer registro API (ingresos): {$record_keys}" );
            }

            $data = [
                'anio'     => $anio,
                'mes'      => $mes,
                'compania' => $compania,
            ];

            foreach ( $field_map as $api_field => $db_column ) {
                if ( isset( $record[ $api_field ] ) ) {
                    $value = $record[ $api_field ];
                    if ( in_array( $db_column, $numeric_cols, true ) ) {
                        $data[ $db_column ] = floatval( $value );
                    } else {
                        $data[ $db_column ] = sanitize_text_field( $value ?? '' );
                    }
                }
            }

            $result = $wpdb->insert( $table_name, $data );
            if ( false === $result ) {
                $this->logger->log( "Error al insertar registro en {$table_name}: {$wpdb->last_error}" );
                break;
            }
            $inserted++;
        }

        $this->logger->log( "Insertados {$inserted} registros de ingresos (Año: {$anio}, Mes: {$mes})." );
        return $inserted;
    }

    /**
     * Check which tables actually exist in the database.
     */
    public function check_tables_status(): array {
        $result = [
            'all_exist'      => true,
            'existing_count' => 0,
            'tables'         => [],
        ];

        foreach ( array_keys( $this->get_available_tables() ) as $table ) {
            $exists = $this->ensure_table_exists( $table );
            $result['tables'][ $table ] = $exists;
            if ( $exists ) {
                $result['existing_count']++;
            } else {
                $result['all_exist'] = false;
            }
        }

        return $result;
    }

    /**
     * Get record counts for all tables.
     */
    public function get_stats(): array {
        global $wpdb;

        $stats = [];
        foreach ( $this->get_available_tables() as $table => $label ) {
            $count = 0;
            if ( $this->ensure_table_exists( $table ) ) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
            }
            $stats[ $table ] = [
                'label' => $label,
                'count' => $count,
            ];
        }

        return $stats;
    }

    /**
     * Get records with pagination and filters.
     */
    public function get_records( string $table, array $args = [] ): array {
        global $wpdb;

        if ( ! $this->validate_table( $table ) ) {
            return [ 'records' => [], 'total' => 0 ];
        }

        $defaults = [
            'page'     => 1,
            'per_page' => 20,
            'orderby'  => 'id',
            'order'    => 'DESC',
            'search'   => '',
            'anio'     => 0,
            'mes'      => 0,
        ];

        $args = wp_parse_args( $args, $defaults );

        // Validate orderby column
        if ( ! $this->validate_column( $table, $args['orderby'] ) ) {
            $args['orderby'] = 'id';
        }
        $order = in_array( strtoupper( $args['order'] ), [ 'ASC', 'DESC' ], true ) ? strtoupper( $args['order'] ) : 'DESC';

        $where   = [];
        $prepare = [];

        if ( ! empty( $args['anio'] ) ) {
            $where[]   = 'anio = %d';
            $prepare[] = $args['anio'];
        }

        if ( ! empty( $args['mes'] ) ) {
            $where[]   = 'mes = %d';
            $prepare[] = $args['mes'];
        }

        if ( ! empty( $args['search'] ) ) {
            $search_like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            // Search in named text columns
            $search_columns = [
                'nombrerubro', 'nombrepred', 'tercero', 'descripcion', 'codigocuenta', 'numero', 'rubro',
                'nombres', 'apellido1', 'apellido2', 'numerodcto', 'nombredelcargo', 'dependencianombre',
                'nombre', 'cuenta', 'codigo',
            ];
            $search_parts   = [];
            foreach ( $search_columns as $col ) {
                if ( $this->validate_column( $table, $col ) ) {
                    $search_parts[] = "`{$col}` LIKE %s";
                    $prepare[]      = $search_like;
                }
            }
            if ( $search_parts ) {
                $where[] = '(' . implode( ' OR ', $search_parts ) . ')';
            }
        }

        $where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
        $offset    = ( $args['page'] - 1 ) * $args['per_page'];

        $orderby_escaped = esc_sql( $args['orderby'] );

        // Count total
        $count_sql = "SELECT COUNT(*) FROM `{$table}` {$where_sql}";
        if ( $prepare ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$prepare ) );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $total = (int) $wpdb->get_var( $count_sql );
        }

        // Get records
        $query = "SELECT * FROM `{$table}` {$where_sql} ORDER BY `{$orderby_escaped}` {$order} LIMIT %d OFFSET %d";
        $query_params = array_merge( $prepare, [ $args['per_page'], $offset ] );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $records = $wpdb->get_results( $wpdb->prepare( $query, ...$query_params ), ARRAY_A );

        return [
            'records' => $records ?: [],
            'total'   => $total,
        ];
    }

    /**
     * Get available years from a table.
     */
    public function get_available_years( string $table ): array {
        global $wpdb;
        if ( ! $this->validate_table( $table ) ) {
            return [];
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_col( "SELECT DISTINCT anio FROM `{$table}` ORDER BY anio DESC" );
    }

    /**
     * Drop all plugin tables.
     */
    public function drop_tables(): void {
        global $wpdb;
        foreach ( array_keys( $this->get_available_tables() ) as $table ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
        }
        $this->logger->log( 'Todas las tablas del plugin han sido eliminadas.' );
    }
}
