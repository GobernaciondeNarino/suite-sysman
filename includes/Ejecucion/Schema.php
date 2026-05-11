<?php
namespace SysmanSuite\Ejecucion;

if ( ! defined( 'ABSPATH' ) ) exit;

class Schema {

    const VERSION = '5.0.1';

    public static function run(): void {
        $current = get_option( 'gn_sisman_schema_version', '0' );
        if ( version_compare( $current, self::VERSION, '>=' ) ) {
            return;
        }

        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $table_pp = $wpdb->prefix . 'sysman_plan_presupuestal';
        $table_ac = $wpdb->prefix . 'sysman_auxiliar_cuentas';
        $table_eg = $wpdb->prefix . 'sysman_ejecucion_gastos';

        // Create tables only if they don't exist (never DROP existing data)
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_pp ) ) !== $table_pp ) {
            self::create_table_raw( $wpdb, self::plan_presupuestal_sql( $wpdb->prefix, $charset ) );
        }

        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_ac ) ) !== $table_ac ) {
            self::create_table_raw( $wpdb, self::auxiliar_cuentas_sql( $wpdb->prefix, $charset ) );
        }

        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_eg ) ) !== $table_eg ) {
            self::create_table_raw( $wpdb, self::ejecucion_gastos_sql( $wpdb->prefix, $charset ) );
        }

        // v5.0.0 migration: fix column name mismatches
        self::migrate_column_names( $wpdb );

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
            KEY idx_unique_lookup (compania, anio, mes, tipocpte, numero)
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
            KEY idx_dependencia (dependencianombre(100))
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
            KEY idx_codigo (codigo)
        ) {$charset};";
    }
}
