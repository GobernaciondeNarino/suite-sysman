<?php
namespace SysmanSuite\Ejecucion;

if ( ! defined( 'ABSPATH' ) ) exit;

class Schema {

    const VERSION = '3.0.0';

    public static function run(): void {
        $current = get_option( 'gn_sisman_schema_version', '0' );
        if ( version_compare( $current, self::VERSION, '>=' ) ) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        self::create_plan_presupuestal( $wpdb, $charset );
        self::create_ejecucion_gastos( $wpdb, $charset );
        self::create_auxiliar_cuentas( $wpdb, $charset );

        update_option( 'gn_sisman_schema_version', self::VERSION );
    }

    private static function create_plan_presupuestal( $wpdb, string $charset ): void {
        $table = $wpdb->prefix . 'sysman_plan_presupuestal';
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            compania VARCHAR(8) NOT NULL DEFAULT '001',
            anio SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            mes TINYINT UNSIGNED NOT NULL DEFAULT 0,
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
            synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_compania_anio_mes (compania, anio, mes),
            KEY idx_codigo (codigo),
            KEY idx_destino (destino),
            KEY idx_codigobpin (codigobpin),
            KEY idx_dependencia (dependencia),
            KEY idx_nombredependencia (nombredependencia),
            KEY idx_sector (sector(100)),
            KEY idx_lookup (compania, anio, mes, nombredependencia, codigo)
        ) {$charset};";
        dbDelta( $sql );
    }

    private static function create_ejecucion_gastos( $wpdb, string $charset ): void {
        $table = $wpdb->prefix . 'sysman_ejecucion_gastos';
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            compania VARCHAR(8) NOT NULL DEFAULT '001',
            anio SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            mes TINYINT UNSIGNED NOT NULL DEFAULT 0,
            codigocuenta VARCHAR(255) NOT NULL DEFAULT '',
            nombrerubro TEXT,
            movimiento VARCHAR(8) NOT NULL DEFAULT '',
            destino VARCHAR(64) NOT NULL DEFAULT '',
            bpid VARCHAR(64) NOT NULL DEFAULT '',
            apropiacioninicial DECIMAL(20,2) NOT NULL DEFAULT 0,
            adicion DECIMAL(20,2) NOT NULL DEFAULT 0,
            reduccion DECIMAL(20,2) NOT NULL DEFAULT 0,
            credito DECIMAL(20,2) NOT NULL DEFAULT 0,
            contracredito DECIMAL(20,2) NOT NULL DEFAULT 0,
            aplazamiento DECIMAL(20,2) NOT NULL DEFAULT 0,
            desplazaminento DECIMAL(20,2) NOT NULL DEFAULT 0,
            apropiacionvigente DECIMAL(20,2) NOT NULL DEFAULT 0,
            disponibilidades DECIMAL(20,2) NOT NULL DEFAULT 0,
            saldodisponible DECIMAL(20,2) NOT NULL DEFAULT 0,
            compromisos DECIMAL(20,2) NOT NULL DEFAULT 0,
            disponibilidadesabiertas DECIMAL(20,2) NOT NULL DEFAULT 0,
            obligacion DECIMAL(20,2) NOT NULL DEFAULT 0,
            pagos DECIMAL(20,2) NOT NULL DEFAULT 0,
            obligacionesporpagar DECIMAL(20,2) NOT NULL DEFAULT 0,
            synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_compania_anio_mes (compania, anio, mes),
            KEY idx_codigocuenta (codigocuenta),
            KEY idx_destino (destino),
            KEY idx_lookup (compania, anio, mes, codigocuenta)
        ) {$charset};";
        dbDelta( $sql );
    }

    private static function create_auxiliar_cuentas( $wpdb, string $charset ): void {
        $table = $wpdb->prefix . 'sysman_auxiliar_cuentas';
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            compania VARCHAR(8) NOT NULL DEFAULT '001',
            anio SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            mes TINYINT UNSIGNED NOT NULL DEFAULT 0,
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
            synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_compania_anio_mes (compania, anio, mes),
            KEY idx_numero (numero),
            KEY idx_rubro (rubro),
            KEY idx_tipocpte_rubro (tipocpte, rubro),
            KEY idx_tipocpte_cmpte (tipocpte, cmpteafectado),
            KEY idx_unique_lookup (compania, anio, mes, tipocpte, numero)
        ) {$charset};";
        dbDelta( $sql );
    }
}
