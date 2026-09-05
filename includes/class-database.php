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
     * Uses Schema class as single source of truth for all DDL.
     */
    public function create_tables(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $prefix  = $wpdb->prefix;

        $tables = [
            'sysman_ejecucion_gastos'  => \SysmanSuite\Ejecucion\Schema::ejecucion_gastos_sql( $prefix, $charset ),
            'sysman_auxiliar_cuentas'  => \SysmanSuite\Ejecucion\Schema::auxiliar_cuentas_sql( $prefix, $charset ),
            'sysman_plan_presupuestal' => \SysmanSuite\Ejecucion\Schema::plan_presupuestal_sql( $prefix, $charset ),
            'sysman_personal_nomina'   => \SysmanSuite\Ejecucion\Schema::personal_nomina_sql( $prefix, $charset ),
            'sysman_ejecucion_ingresos' => \SysmanSuite\Ejecucion\Schema::ejecucion_ingresos_sql( $prefix, $charset ),
        ];

        foreach ( $tables as $name => $sql ) {
            $full_name = $prefix . $name;
            if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $full_name ) ) !== $full_name ) {
                $wpdb->query( $sql );
                if ( $wpdb->last_error ) {
                    $this->logger->log( "Error al crear tabla {$full_name}: {$wpdb->last_error}", 'error' );
                }
            }
        }

        // Run column-rename migrations for tables that may have stale column names from older versions.
        \SysmanSuite\Ejecucion\Schema::run();

        $this->logger->log( 'Tablas de base de datos verificadas correctamente.' );
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
        return in_array( $column, $this->get_table_columns( $table ), true );
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
     * Get the field map for plan presupuestal table (API field name => DB column).
     */
    public function get_plan_field_map(): array {
        return [
            'codigo'                => 'codigo',
            'nombre'                => 'nombre',
            'destino'               => 'destino',
            'naturaleza'            => 'naturaleza',
            'movimiento'            => 'movimiento',
            'tipovigencia'          => 'tipovigencia',
            'sector'                => 'sector',
            'programa'              => 'programa',
            'subPrograma'           => 'subprograma',
            'codigoProducto'        => 'codigoproducto',
            'codigoBPIN'            => 'codigobpin',
            'codigoCCPET'           => 'codigoccpet',
            'codigoCPCDANE'         => 'codigocpcdane',
            'codigoUnidadEjecutora' => 'codigounidadejecutora',
            'codigoFuente'          => 'codigofuente',
            'codigoCCPETRegalias'   => 'codigoccpetregalias',
            'politicaPublica'       => 'politicapublica',
            'detalleSectorial'      => 'detallesectorial',
            'tipoRecurso'           => 'tiporecurso',
            'codigoSIA'             => 'codigosia',
            'dependencia'           => 'dependencia',
            'nombreDependencia'     => 'nombredependencia',
            'codigoEquiv'           => 'codigoequiv',
        ];
    }

    /**
     * Clear every cached dependencias transient (all compania/anio/mes keys).
     */
    private function flush_dependencias_cache(): void {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_gn\\_sisman\\_pp\\_dependencias\\_%' "
            . "OR option_name LIKE '\\_transient\\_timeout\\_gn\\_sisman\\_pp\\_dependencias\\_%'"
        );
    }

    /**
     * Transactional, batched replace of a scope of records.
     *
     * Deletes the scope and bulk-inserts the new rows inside a transaction so
     * a mid-import failure never leaves the scope half-empty (best effort:
     * requires InnoDB; on MyISAM the statements simply run sequentially).
     * Shared by all five report importers.
     *
     * @param string $table_name    Full table name (already validated by caller).
     * @param array  $records       Decoded API records.
     * @param array  $base          Fixed columns for every row (col => value).
     * @param string $delete_sql    WHERE clause for the scope delete (with placeholders).
     * @param array  $delete_params Values for $delete_sql.
     * @param array  $field_map     API field => DB column.
     * @param array  $numeric_cols  DB columns stored as floats.
     * @param string $label         Report label for logging.
     */
    private function replace_records( string $table_name, array $records, array $base, string $delete_sql, array $delete_params, array $field_map, array $numeric_cols, string $label ): int {
        global $wpdb;

        if ( ! $this->ensure_table_exists( $table_name ) ) {
            $this->logger->log( "La tabla no existe en la base de datos: {$table_name}" );
            return 0;
        }

        // Uniform column list: base + mapped columns that exist in the table.
        $existing = $this->get_table_columns( $table_name );
        $mapped   = array_values( array_unique( array_values( $field_map ) ) );
        if ( ! empty( $existing ) ) {
            $mapped = array_values( array_intersect( $mapped, $existing ) );
        }
        $columns  = array_merge( array_keys( $base ), $mapped );
        $col_list = '`' . implode( '`, `', $columns ) . '`';

        $base_ph = [];
        foreach ( $base as $value ) {
            $base_ph[] = is_int( $value ) ? '%d' : '%s';
        }

        $row_ph = $base_ph;
        foreach ( $mapped as $col ) {
            $row_ph[] = in_array( $col, $numeric_cols, true ) ? '%f' : '%s';
        }
        $row_ph = '(' . implode( ',', $row_ph ) . ')';

        $wpdb->query( 'START TRANSACTION' );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query( $wpdb->prepare( "DELETE FROM `{$table_name}` WHERE {$delete_sql}", $delete_params ) );

        if ( empty( $records ) ) {
            $wpdb->query( 'COMMIT' );
            $this->logger->log( "Insertados 0 registros en {$table_name} ({$label}: la API no devolvió datos)." );
            return 0;
        }

        $first = reset( $records );
        if ( is_array( $first ) ) {
            $this->logger->log( "Claves del primer registro API ({$label}): " . implode( ', ', array_keys( $first ) ) );
        }

        $inserted = 0;
        foreach ( array_chunk( $records, 500 ) as $batch ) {
            $placeholders = [];
            $values       = [];

            foreach ( $batch as $record ) {
                $data = [];
                foreach ( $field_map as $api_key => $db_col ) {
                    if ( isset( $record[ $api_key ] ) ) {
                        $data[ $db_col ] = in_array( $db_col, $numeric_cols, true )
                            ? floatval( $record[ $api_key ] )
                            : sanitize_text_field( (string) $record[ $api_key ] );
                    }
                }

                $vals = array_values( $base );
                foreach ( $mapped as $col ) {
                    $vals[] = $data[ $col ] ?? ( in_array( $col, $numeric_cols, true ) ? 0.0 : '' );
                }

                $placeholders[] = $row_ph;
                $values         = array_merge( $values, $vals );
            }

            $sql = "INSERT INTO `{$table_name}` ({$col_list}) VALUES " . implode( ',', $placeholders );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $result = $wpdb->query( $wpdb->prepare( $sql, $values ) );

            if ( false === $result ) {
                $this->logger->log( "Error al insertar registros en {$table_name}: {$wpdb->last_error}. Se revierte la importación.", 'error' );
                $wpdb->query( 'ROLLBACK' );
                return 0;
            }
            $inserted += count( $batch );
        }

        $wpdb->query( 'COMMIT' );

        // Any import invalidates the Presupuesto module's cached aggregates.
        \SysmanSuite\Presupuesto\Repository::limpiar_cache();

        $this->logger->log( "Insertados {$inserted}/" . count( $records ) . " registros en {$table_name} ({$label})." );
        return $inserted;
    }

    /**
     * Insert records into plan presupuestal table (numinforme=4).
     */
    public function insert_plan_records( array $records, int $anio, int $mes, string $compania ): int {
        global $wpdb;

        $inserted = $this->replace_records(
            $wpdb->prefix . 'sysman_plan_presupuestal',
            $records,
            [ 'compania' => $compania, 'anio' => $anio, 'mes' => $mes ],
            'compania = %s AND anio = %d AND mes = %d',
            [ $compania, $anio, $mes ],
            $this->get_plan_field_map(),
            [],
            "plan, Año: {$anio}, Mes: {$mes}"
        );

        $this->flush_dependencias_cache();
        return $inserted;
    }

    /**
     * Insert records into ejecucion table (numinforme=1).
     */
    public function insert_ejecucion_records( array $records, string $table_name, int $anio, int $mes, string $compania ): int {
        if ( ! $this->validate_table( $table_name ) ) {
            $this->logger->log( "Tabla no válida: {$table_name}" );
            return 0;
        }

        return $this->replace_records(
            $table_name,
            $records,
            [ 'anio' => $anio, 'mes' => $mes, 'compania' => $compania ],
            'compania = %s AND anio = %d AND mes = %d',
            [ $compania, $anio, $mes ],
            $this->get_ejecucion_field_map(),
            [
                'apropiacioninicial', 'adicion', 'reduccion', 'credito', 'contracredito',
                'aplazamiento', 'desplazamiento', 'apropiacionvigente', 'disponibilidades',
                'saldodisponible', 'compromisos', 'disponibilidadesabiertas', 'obligacion',
                'pagos', 'obligacionesporpagar',
            ],
            "ejecucion, Año: {$anio}, Mes: {$mes}"
        );
    }

    /**
     * Insert records into auxiliar table (numinforme=2).
     */
    public function insert_auxiliar_records( array $records, int $anio, int $mes, string $compania, string $tipo_cpte ): int {
        global $wpdb;

        return $this->replace_records(
            $wpdb->prefix . 'sysman_auxiliar_cuentas',
            $records,
            [ 'anio' => $anio, 'mes' => $mes, 'compania' => $compania ],
            'compania = %s AND anio = %d AND mes = %d AND tipocpte = %s',
            [ $compania, $anio, $mes, $tipo_cpte ],
            $this->get_auxiliar_field_map(),
            [
                'valordebito', 'valorcredito', 'debitoafectado', 'creditoafectado',
                'modificaciondebito', 'modificacioncredito', 'saldoporejecutaresp',
            ],
            "auxiliar, Año: {$anio}, Mes: {$mes}, Tipo: {$tipo_cpte}"
        );
    }

    /**
     * Insert records into personal_nomina table (numinforme=5).
     * Note: Personal data has no 'mes' field.
     */
    public function insert_personal_records( array $records, int $anio, string $compania ): int {
        global $wpdb;

        return $this->replace_records(
            $wpdb->prefix . 'sysman_personal_nomina',
            $records,
            [ 'anio' => $anio, 'compania' => $compania ],
            'anio = %d AND compania = %s',
            [ $anio, $compania ],
            $this->get_personal_field_map(),
            [ 'salariobaseibc' ],
            "personal, Año: {$anio}, Compañía: {$compania}"
        );
    }

    /**
     * Insert records into ejecucion_ingresos table (numinforme=6).
     */
    public function insert_ingresos_records( array $records, int $anio, int $mes, string $compania ): int {
        global $wpdb;

        return $this->replace_records(
            $wpdb->prefix . 'sysman_ejecucion_ingresos',
            $records,
            [ 'anio' => $anio, 'mes' => $mes, 'compania' => $compania ],
            'compania = %s AND anio = %d AND mes = %d',
            [ $compania, $anio, $mes ],
            $this->get_ingresos_field_map(),
            [ 'apropiado', 'modificaciones', 'totalpresupuesto', 'recaudosanteriores', 'recaudosmes', 'recaudosacumulados', 'porrecaudar', 'porcrecaudado' ],
            "ingresos, Año: {$anio}, Mes: {$mes}"
        );
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
