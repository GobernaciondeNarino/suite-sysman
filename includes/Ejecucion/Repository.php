<?php
namespace SysmanSuite\Ejecucion;

if ( ! defined( 'ABSPATH' ) ) exit;

class Repository {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get_dependencias( int $anio, int $mes, string $compania = '001' ): array {
        $cache_key = "gn_sisman_pp_dependencias_{$compania}_{$anio}_{$mes}";
        $cached = get_transient( $cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sysman_plan_presupuestal';

        $results = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT nombredependencia FROM {$table}
             WHERE compania = %s AND anio = %d AND mes = %d AND nombredependencia != ''
             ORDER BY nombredependencia",
            $compania, $anio, $mes
        ) );

        set_transient( $cache_key, $results, 12 * HOUR_IN_SECONDS );
        return $results;
    }

    public function get_rubros( int $post_id ): array {
        $meta = $this->get_post_meta( $post_id );
        if ( ! $meta ) {
            return [];
        }

        $cache_key = 'gn_sisman_ejec_rubros_' . md5( wp_json_encode( $meta ) );
        $cached = get_transient( $cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sysman_plan_presupuestal';

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT codigo, nombre, destino, naturaleza, codigobpin, sector, programa, subprograma, codigoproducto, movimiento
             FROM {$table}
             WHERE compania = %s AND anio = %d AND mes = %d AND nombredependencia = %s AND movimiento = 'SI'
             ORDER BY codigo",
            $meta['compania'], $meta['anio'], $meta['mes'], $meta['dependencia']
        ), ARRAY_A );

        set_transient( $cache_key, $results, 5 * MINUTE_IN_SECONDS );
        return $results;
    }

    public function get_consolidado( int $post_id, string $codigo ): ?array {
        $meta = $this->get_post_meta( $post_id );
        if ( ! $meta ) {
            return null;
        }

        $cache_key = 'gn_sisman_ejec_consol_' . md5( $post_id . $codigo );
        $cached = get_transient( $cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sysman_ejecucion_gastos';

        $result = $wpdb->get_row( $wpdb->prepare(
            "SELECT apropiacioninicial, adicion, reduccion, credito, contracredito,
                    aplazamiento, desplazaminento, apropiacionvigente, disponibilidades,
                    saldodisponible, compromisos, disponibilidadesabiertas, obligacion,
                    pagos, obligacionesporpagar
             FROM {$table}
             WHERE compania = %s AND anio = %d AND mes = %d AND codigocuenta = %s
             LIMIT 1",
            $meta['compania'], $meta['anio'], $meta['mes'], $codigo
        ), ARRAY_A );

        if ( $result ) {
            set_transient( $cache_key, $result, 5 * MINUTE_IN_SECONDS );
        }
        return $result;
    }

    public function get_disponibilidades( int $post_id, string $codigocuenta ): array {
        $meta = $this->get_post_meta( $post_id );
        if ( ! $meta ) {
            return [];
        }

        $cache_key = 'gn_sisman_ejec_dis_' . md5( $post_id . $codigocuenta );
        $cached = get_transient( $cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sysman_auxiliar_cuentas';

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT numero, nombreplan, nombretercero, valordebito, saldoporejecutaresp, fecha, descripcion, nrodocumento
             FROM {$table}
             WHERE compania = %s AND anio = %d AND mes = %d AND tipocpte = 'DIS' AND rubro = %s
             ORDER BY fecha, numero",
            $meta['compania'], $meta['anio'], $meta['mes'], $codigocuenta
        ), ARRAY_A );

        set_transient( $cache_key, $results, 5 * MINUTE_IN_SECONDS );
        return $results;
    }

    public function get_reservas( int $post_id, string $numero_dis ): array {
        $meta = $this->get_post_meta( $post_id );
        if ( ! $meta ) {
            return [];
        }

        $cache_key = 'gn_sisman_ejec_res_' . md5( $post_id . $numero_dis );
        $cached = get_transient( $cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sysman_auxiliar_cuentas';

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT numero, nombretercero, descripcion, nrodocumento, valordebito, saldoporejecutaresp, fecha
             FROM {$table}
             WHERE compania = %s AND anio = %d AND mes = %d AND tipocpte = 'RES' AND cmpteafectado = %s
             ORDER BY fecha, numero",
            $meta['compania'], $meta['anio'], $meta['mes'], $numero_dis
        ), ARRAY_A );

        set_transient( $cache_key, $results, 5 * MINUTE_IN_SECONDS );
        return $results;
    }

    public function get_proyecto_bpin( string $codigobpin ): ?array {
        if ( empty( $codigobpin ) ) {
            return null;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bpid_suite_contratos';

        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) !== $table ) {
            return null;
        }

        $result = $wpdb->get_row( $wpdb->prepare(
            "SELECT nombre_proyecto, metas, odss
             FROM {$table}
             WHERE numero_proyecto = %s
             LIMIT 1",
            $codigobpin
        ), ARRAY_A );

        return $result ?: null;
    }

    private function get_post_meta( int $post_id ): ?array {
        $post = get_post( $post_id );
        if ( ! $post || 'gn_ejecucion' !== $post->post_type ) {
            return null;
        }

        return [
            'dependencia' => get_post_meta( $post_id, '_gn_dependencia', true ),
            'anio'        => (int) get_post_meta( $post_id, '_gn_anio', true ),
            'mes'         => (int) get_post_meta( $post_id, '_gn_mes', true ),
            'compania'    => get_post_meta( $post_id, '_gn_compania', true ) ?: '001',
        ];
    }
}
