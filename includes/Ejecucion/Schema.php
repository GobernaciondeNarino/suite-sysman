<?php
namespace SysmanSuite\Ejecucion;

if ( ! defined( 'ABSPATH' ) ) exit;

class Schema {

    const VERSION = '5.21.0';

    /** Tabla (sin prefijo) => método que devuelve su DDL canónico. */
    const TABLAS = [
        'sysman_plan_presupuestal'  => 'plan_presupuestal_sql',
        'sysman_auxiliar_cuentas'   => 'auxiliar_cuentas_sql',
        'sysman_ejecucion_gastos'   => 'ejecucion_gastos_sql',
        'sysman_personal_nomina'    => 'personal_nomina_sql',
        'sysman_ejecucion_ingresos' => 'ejecucion_ingresos_sql',
    ];

    /**
     * Columnas por las que se cruzan plan, ejecución y auxiliar de gastos
     * (pp.codigo = eg.codigocuenta = ac.rubro). Deben compartir intercalación.
     */
    const COLUMNAS_CRUCE = [
        'sysman_plan_presupuestal' => [ 'compania', 'codigo' ],
        'sysman_ejecucion_gastos'  => [ 'compania', 'codigocuenta' ],
        'sysman_auxiliar_cuentas'  => [ 'compania', 'rubro' ],
    ];

    public static function run(): void {
        $current = get_option( 'gn_sisman_schema_version', '0' );
        if ( version_compare( $current, self::VERSION, '>=' ) ) {
            return;
        }

        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        // Create tables only if they don't exist (never DROP existing data)
        foreach ( self::TABLAS as $nombre => $metodo ) {
            $tabla = $wpdb->prefix . $nombre;
            if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $tabla ) ) !== $tabla ) {
                self::create_table_raw( $wpdb, self::$metodo( $wpdb->prefix, $charset ) );
            }
        }

        // v5.0.0 migration: fix column name mismatches
        self::migrate_column_names( $wpdb );

        // CREATE TABLE IF NOT EXISTS no toca una tabla que ya existe: las
        // columnas e índices añadidos en versiones posteriores nunca llegarían
        // a las instalaciones antiguas, y el importador descartaría en
        // silencio los campos que no encuentra (p. ej. tipovigencia).
        self::reconciliar();

        update_option( 'gn_sisman_schema_version', self::VERSION );

        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_gn\\_sisman\\_pp\\_dependencias\\_%' "
            . "OR option_name LIKE '\\_transient\\_timeout\\_gn\\_sisman\\_pp\\_dependencias\\_%'"
        );
    }

    private static function get_columns( $wpdb, string $table ): array {
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) !== $table ) {
            return [];
        }
        $cols = $wpdb->get_col( "DESCRIBE `{$table}`", 0 );
        return is_array( $cols ) ? $cols : [];
    }

    private static function migrate_column_names( $wpdb ): void {
        // --- ejecucion_gastos: fix desplazaminento typo + synced_at → fecha_importacion
        $table_eg = $wpdb->prefix . 'sysman_ejecucion_gastos';
        $columns  = self::get_columns( $wpdb, $table_eg );

        if ( ! empty( $columns ) ) {
            // Fix: desplazaminento (typo) → desplazamiento
            $has_typo    = in_array( 'desplazaminento', $columns, true );
            $has_correct = in_array( 'desplazamiento', $columns, true );

            if ( $has_typo && ! $has_correct ) {
                $wpdb->query( "ALTER TABLE `{$table_eg}` CHANGE `desplazaminento` `desplazamiento` DECIMAL(20,2) NOT NULL DEFAULT 0" );
            } elseif ( $has_typo && $has_correct ) {
                $wpdb->query( "UPDATE `{$table_eg}` SET `desplazamiento` = `desplazaminento` WHERE `desplazamiento` = 0 AND `desplazaminento` != 0" );
                $wpdb->query( "ALTER TABLE `{$table_eg}` DROP COLUMN `desplazaminento`" );
            }

            // Refresh columns and handle timestamp column
            $columns = self::get_columns( $wpdb, $table_eg );
            self::migrate_timestamp_column( $wpdb, $table_eg, $columns );
        }

        // --- auxiliar_cuentas: synced_at → fecha_importacion
        $table_ac = $wpdb->prefix . 'sysman_auxiliar_cuentas';
        self::migrate_timestamp_column( $wpdb, $table_ac, self::get_columns( $wpdb, $table_ac ) );

        // --- plan_presupuestal: synced_at → fecha_importacion
        $table_pp = $wpdb->prefix . 'sysman_plan_presupuestal';
        self::migrate_timestamp_column( $wpdb, $table_pp, self::get_columns( $wpdb, $table_pp ) );
    }

    private static function migrate_timestamp_column( $wpdb, string $table, array $columns ): void {
        if ( empty( $columns ) ) {
            return;
        }
        $has_old = in_array( 'synced_at', $columns, true );
        $has_new = in_array( 'fecha_importacion', $columns, true );

        if ( $has_old && ! $has_new ) {
            $wpdb->query( "ALTER TABLE `{$table}` CHANGE `synced_at` `fecha_importacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP" );
        } elseif ( $has_old && $has_new ) {
            $wpdb->query( "ALTER TABLE `{$table}` DROP COLUMN `synced_at`" );
        }
    }

    private static function create_table_raw( $wpdb, string $sql ): void {
        $wpdb->query( $sql );
    }

    /**
     * Columnas e índices que define el DDL canónico de una tabla.
     *
     * @return array{columnas: array<string,string>, indices: array<string,string>}
     *         nombre => definición tal como va en el CREATE TABLE.
     */
    public static function definicion( string $nombre ): array {
        $metodo = self::TABLAS[ $nombre ] ?? null;
        if ( null === $metodo ) {
            return [ 'columnas' => [], 'indices' => [] ];
        }
        return self::parsear_ddl( self::$metodo( '', '' ) );
    }

    /**
     * Separa un CREATE TABLE en columnas e índices secundarios.
     */
    public static function parsear_ddl( string $ddl ): array {
        $inicio = strpos( $ddl, '(' );
        $fin    = strrpos( $ddl, ')' );
        $cuerpo = ( false === $inicio || false === $fin ) ? '' : substr( $ddl, $inicio + 1, $fin - $inicio - 1 );

        $columnas = [];
        $indices  = [];
        foreach ( preg_split( '/\R/', $cuerpo ) as $linea ) {
            $linea = rtrim( trim( $linea ), ',' );
            if ( '' === $linea || 0 === stripos( $linea, 'PRIMARY KEY' ) ) {
                continue;
            }
            if ( preg_match( '/^(?:UNIQUE\s+)?KEY\s+(\w+)\s*\(/i', $linea, $m ) ) {
                $indices[ $m[1] ] = $linea;
                continue;
            }
            if ( preg_match( '/^(\w+)\s+/', $linea, $m ) ) {
                $columnas[ $m[1] ] = $linea;
            }
        }

        return [ 'columnas' => $columnas, 'indices' => $indices ];
    }

    /**
     * Qué le falta a una tabla existente frente al DDL canónico.
     *
     * Solo se añade, nunca se borra: una columna de más (p. ej. un nombre
     * antiguo) se informa, pero quitarla podría destruir datos.
     *
     * @param string[] $columnas_reales
     * @param string[] $indices_reales
     */
    public static function diferencias( string $nombre, array $columnas_reales, array $indices_reales ): array {
        $def = self::definicion( $nombre );

        $reales_col = array_map( 'strtolower', $columnas_reales );
        $reales_idx = array_map( 'strtolower', $indices_reales );

        $faltan_col = array_diff_key( $def['columnas'], array_flip( $reales_col ) );
        $faltan_idx = array_diff_key( $def['indices'], array_flip( $reales_idx ) );
        $sobran_col = array_values( array_diff( $reales_col, array_keys( $def['columnas'] ) ) );

        return [
            'columnas_faltantes' => $faltan_col,
            'indices_faltantes'  => $faltan_idx,
            'columnas_sobrantes' => $sobran_col,
        ];
    }

    /**
     * Añade a las tablas existentes las columnas e índices que les falten.
     *
     * @return array<string, string[]> Cambios aplicados (o errores) por tabla.
     */
    public static function reconciliar(): array {
        global $wpdb;

        $cambios = [];
        foreach ( array_keys( self::TABLAS ) as $nombre ) {
            $tabla    = $wpdb->prefix . $nombre;
            $columnas = self::get_columns( $wpdb, $tabla );
            if ( empty( $columnas ) ) {
                continue;
            }

            $dif = self::diferencias( $nombre, $columnas, self::get_indexes( $wpdb, $tabla ) );

            foreach ( $dif['columnas_faltantes'] as $col => $definicion ) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- DDL propio, no entrada de usuario.
                $ok = $wpdb->query( "ALTER TABLE `{$tabla}` ADD COLUMN {$definicion}" );
                $cambios[ $nombre ][] = false === $ok
                    ? "ERROR columna {$col}: {$wpdb->last_error}"
                    : "columna {$col}";
            }
            foreach ( $dif['indices_faltantes'] as $idx => $definicion ) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- DDL propio, no entrada de usuario.
                $ok = $wpdb->query( "ALTER TABLE `{$tabla}` ADD {$definicion}" );
                $cambios[ $nombre ][] = false === $ok
                    ? "ERROR índice {$idx}: {$wpdb->last_error}"
                    : "índice {$idx}";
            }
        }

        if ( ! empty( $cambios ) && class_exists( '\SysmanSuite\Logger' ) ) {
            ( new \SysmanSuite\Logger() )->log(
                'Estructura de tablas actualizada: ' . wp_json_encode( $cambios, JSON_UNESCAPED_UNICODE ),
                'info'
            );
        }

        return $cambios;
    }

    /**
     * Longitud máxima de cada columna VARCHAR del DDL canónico.
     *
     * @return array<string,int>
     */
    public static function longitudes_varchar( string $nombre ): array {
        $longitudes = [];
        foreach ( self::definicion( $nombre )['columnas'] as $col => $definicion ) {
            if ( preg_match( '/^\w+\s+VARCHAR\((\d+)\)/i', $definicion, $m ) ) {
                $longitudes[ $col ] = (int) $m[1];
            }
        }
        return $longitudes;
    }

    /**
     * Informe de integridad estructural de las cinco tablas.
     *
     * Por tabla: si existe, filas, motor (sin InnoDB no hay transacciones y la
     * importación no puede revertir una duplicación), intercalación,
     * columnas/índices que faltan o sobran, filas con periodo inválido y
     * columnas cuyo valor más largo llena el campo (posible truncado).
     * Además, columnas de cruce con intercalaciones distintas entre tablas:
     * MySQL no usa el índice en ese JOIN o falla con «Illegal mix of collations».
     */
    public static function integridad(): array {
        global $wpdb;

        $tablas = [];
        $cruces = [];

        foreach ( array_keys( self::TABLAS ) as $nombre ) {
            $tabla = $wpdb->prefix . $nombre;
            $info  = [
                'tabla'  => $nombre,
                'existe' => false,
            ];

            $columnas = self::get_columns( $wpdb, $tabla );
            if ( empty( $columnas ) ) {
                $tablas[] = $info;
                continue;
            }

            $estado = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $tabla ), ARRAY_A ) ?: [];
            $dif    = self::diferencias( $nombre, $columnas, self::get_indexes( $wpdb, $tabla ) );

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $completas = isset( self::COLUMNAS_CRUCE[ $nombre ] )
                ? ( $wpdb->get_results( "SHOW FULL COLUMNS FROM `{$tabla}`", ARRAY_A ) ?: [] )
                : [];
            foreach ( $completas as $c ) {
                if ( in_array( $c['Field'], self::COLUMNAS_CRUCE[ $nombre ], true ) && ! empty( $c['Collation'] ) ) {
                    $cruces[ "{$nombre}.{$c['Field']}" ] = $c['Collation'];
                }
            }

            $tiene_mes = in_array( 'mes', $columnas, true );
            $invalido  = $tiene_mes ? 'anio < 2000 OR mes < 1 OR mes > 12' : 'anio < 2000';

            // Una sola pasada por la tabla para el valor más largo de cada VARCHAR.
            $longitudes = array_intersect_key( self::longitudes_varchar( $nombre ), array_flip( $columnas ) );
            $max_sql    = '';
            foreach ( array_keys( $longitudes ) as $col ) {
                $max_sql .= ", MAX(CHAR_LENGTH(`{$col}`)) AS `max_{$col}`";
            }
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- columnas del DDL propio.
            $agregado = $wpdb->get_row(
                "SELECT COUNT(*) AS filas, SUM(CASE WHEN {$invalido} THEN 1 ELSE 0 END) AS invalidas{$max_sql} FROM `{$tabla}`",
                ARRAY_A
            ) ?: [];

            $truncados = [];
            foreach ( $longitudes as $col => $max ) {
                $largo = (int) ( $agregado[ "max_{$col}" ] ?? 0 );
                if ( $largo >= $max ) {
                    $truncados[ $col ] = $max;
                }
            }

            $motor = (string) ( $estado['Engine'] ?? '' );

            $tablas[] = array_merge( $info, [
                'existe'             => true,
                'filas'              => (int) ( $agregado['filas'] ?? 0 ),
                'motor'              => $motor,
                'transaccional'      => 0 === strcasecmp( $motor, 'InnoDB' ),
                'intercalacion'      => (string) ( $estado['Collation'] ?? '' ),
                'columnas_faltantes' => array_keys( $dif['columnas_faltantes'] ),
                'indices_faltantes'  => array_keys( $dif['indices_faltantes'] ),
                'columnas_sobrantes' => $dif['columnas_sobrantes'],
                'filas_invalidas'    => (int) ( $agregado['invalidas'] ?? 0 ),
                'posible_truncado'   => $truncados,
            ] );
        }

        return [
            'tablas'                => $tablas,
            'intercalaciones_cruce' => $cruces,
            'intercalacion_mixta'   => count( array_unique( array_values( $cruces ) ) ) > 1,
        ];
    }

    private static function get_indexes( $wpdb, string $table ): array {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $filas = $wpdb->get_results( "SHOW INDEX FROM `{$table}`", ARRAY_A ) ?: [];
        return array_values( array_unique( array_column( $filas, 'Key_name' ) ) );
    }

    public static function plan_presupuestal_sql( string $prefix, string $charset ): string {
        $table = $prefix . 'sysman_plan_presupuestal';
        return "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            compania VARCHAR(10) NOT NULL DEFAULT '001',
            anio INT NOT NULL DEFAULT 0,
            mes INT NOT NULL DEFAULT 0,
            codigo VARCHAR(255) NOT NULL DEFAULT '',
            nombre TEXT,
            destino VARCHAR(64) NOT NULL DEFAULT '',
            naturaleza VARCHAR(64) NOT NULL DEFAULT '',
            movimiento VARCHAR(8) NOT NULL DEFAULT '',
            tipovigencia VARCHAR(64) NOT NULL DEFAULT '',
            sector VARCHAR(255) NOT NULL DEFAULT '',
            programa VARCHAR(255) NOT NULL DEFAULT '',
            subprograma VARCHAR(255) NOT NULL DEFAULT '',
            codigoproducto VARCHAR(64) NOT NULL DEFAULT '',
            codigobpin VARCHAR(64) NOT NULL DEFAULT '',
            codigoccpet VARCHAR(64) NOT NULL DEFAULT '',
            codigocpcdane VARCHAR(64) NOT NULL DEFAULT '',
            codigounidadejecutora VARCHAR(32) NOT NULL DEFAULT '',
            codigofuente VARCHAR(32) NOT NULL DEFAULT '',
            codigoccpetregalias VARCHAR(64) NOT NULL DEFAULT '',
            politicapublica VARCHAR(255) NOT NULL DEFAULT '',
            detallesectorial VARCHAR(255) NOT NULL DEFAULT '',
            tiporecurso VARCHAR(64) NOT NULL DEFAULT '',
            codigosia VARCHAR(64) NOT NULL DEFAULT '',
            dependencia VARCHAR(32) NOT NULL DEFAULT '',
            nombredependencia VARCHAR(255) NOT NULL DEFAULT '',
            codigoequiv VARCHAR(255) NOT NULL DEFAULT '',
            fecha_importacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_compania_anio_mes (compania, anio, mes),
            KEY idx_codigo (codigo(191)),
            KEY idx_destino (destino),
            KEY idx_codigobpin (codigobpin),
            KEY idx_dependencia (dependencia),
            KEY idx_nombredependencia (nombredependencia(191)),
            KEY idx_sector (sector(100)),
            KEY idx_lookup (compania, anio, mes, nombredependencia(100), codigo(100))
        ) {$charset};";
    }

    public static function auxiliar_cuentas_sql( string $prefix, string $charset ): string {
        $table = $prefix . 'sysman_auxiliar_cuentas';
        return "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            compania VARCHAR(10) NOT NULL DEFAULT '001',
            anio INT NOT NULL DEFAULT 0,
            mes INT NOT NULL DEFAULT 0,
            numero VARCHAR(32) NOT NULL DEFAULT '',
            nombrepred TEXT,
            idprede VARCHAR(64) NOT NULL DEFAULT '',
            nombreplan TEXT,
            rubro VARCHAR(255) NOT NULL DEFAULT '',
            fecha VARCHAR(20) NOT NULL DEFAULT '',
            tipocpte VARCHAR(8) NOT NULL DEFAULT '',
            tercero VARCHAR(64) NOT NULL DEFAULT '',
            nombretercero VARCHAR(255) NOT NULL DEFAULT '',
            descripcion TEXT,
            nrodocumento VARCHAR(64) NOT NULL DEFAULT '',
            valordebito DECIMAL(20,2) NOT NULL DEFAULT 0,
            valorcredito DECIMAL(20,2) NOT NULL DEFAULT 0,
            debitoafectado DECIMAL(20,2) NOT NULL DEFAULT 0,
            creditoafectado DECIMAL(20,2) NOT NULL DEFAULT 0,
            modificaciondebito DECIMAL(20,2) NOT NULL DEFAULT 0,
            modificacioncredito DECIMAL(20,2) NOT NULL DEFAULT 0,
            saldoporejecutaresp DECIMAL(20,2) NOT NULL DEFAULT 0,
            tipocpteafect VARCHAR(8) NOT NULL DEFAULT '',
            cmpteafectado VARCHAR(32) NOT NULL DEFAULT '',
            fecha_importacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_compania_anio_mes (compania, anio, mes),
            KEY idx_numero (numero),
            KEY idx_rubro (rubro(191)),
            KEY idx_tipocpte_rubro (tipocpte, rubro(191)),
            KEY idx_tipocpte_cmpte (tipocpte, cmpteafectado),
            KEY idx_unique_lookup (compania, anio, mes, tipocpte, numero),
            KEY idx_rubro_periodo (compania, anio, mes, rubro(100))
        ) {$charset};";
    }

    public static function ejecucion_gastos_sql( string $prefix, string $charset ): string {
        $table = $prefix . 'sysman_ejecucion_gastos';
        return "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            compania VARCHAR(10) NOT NULL DEFAULT '001',
            anio INT NOT NULL DEFAULT 0,
            mes INT NOT NULL DEFAULT 0,
            codigocuenta VARCHAR(255) NOT NULL DEFAULT '',
            nombrerubro TEXT,
            movimiento VARCHAR(10) NOT NULL DEFAULT '',
            destino VARCHAR(100) NOT NULL DEFAULT '',
            bpid VARCHAR(64) NOT NULL DEFAULT '0',
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
            KEY idx_codigocuenta (codigocuenta(191)),
            KEY idx_destino (destino),
            KEY idx_compania (compania),
            KEY idx_lookup (compania, anio, mes, codigocuenta(100))
        ) {$charset};";
    }

    public static function personal_nomina_sql( string $prefix, string $charset ): string {
        $table = $prefix . 'sysman_personal_nomina';
        return "CREATE TABLE IF NOT EXISTS {$table} (
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
            KEY idx_dependencia (dependencianombre(100)),
            KEY idx_compania_anio (compania, anio)
        ) {$charset};";
    }

    public static function ejecucion_ingresos_sql( string $prefix, string $charset ): string {
        $table = $prefix . 'sysman_ejecucion_ingresos';
        return "CREATE TABLE IF NOT EXISTS {$table} (
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
            KEY idx_codigo (codigo),
            KEY idx_compania_anio_mes (compania, anio, mes)
        ) {$charset};";
    }
}
