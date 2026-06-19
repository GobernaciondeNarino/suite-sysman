<?php
namespace SysmanSuite\Ejecucion;

if ( ! defined( 'ABSPATH' ) ) exit;

class Repository {

    public const DIS_FILTERS = [
        'numero'            => [ 'ac.numero', 'exact' ],
        'tercero'           => [ 'ac.tercero', 'exact' ],
        'nombretercero'     => [ 'ac.nombretercero', 'like' ],
        'rubro'             => [ 'ac.rubro', 'exact' ],
        'nombrerubro'       => [ 'pp.nombre', 'like' ],
        'descripcion'       => [ 'ac.descripcion', 'like' ],
        'nrodocumento'      => [ 'ac.nrodocumento', 'exact' ],
        'cmpteafectado'     => [ 'ac.cmpteafectado', 'exact' ],
        'fecha'             => [ 'ac.fecha', 'exact' ],
        'nombredependencia' => [ 'pp.nombredependencia', 'like' ],
        'destino'           => [ 'pp.destino', 'exact' ],
        'naturaleza'        => [ 'pp.naturaleza', 'exact' ],
        'sector'            => [ 'pp.sector', 'like' ],
        'programa'          => [ 'pp.programa', 'like' ],
        'subprograma'       => [ 'pp.subprograma', 'like' ],
        'codigoproducto'    => [ 'pp.codigoproducto', 'exact' ],
        'codigobpin'        => [ 'pp.codigobpin', 'exact' ],
    ];

    public const EXPORT_FILTERS = [
        'codigo'         => [ 'pp.codigo', 'exact' ],
        'nombre'         => [ 'pp.nombre', 'like' ],
        'destino'        => [ 'pp.destino', 'exact' ],
        'naturaleza'     => [ 'pp.naturaleza', 'exact' ],
        'sector'         => [ 'pp.sector', 'like' ],
        'programa'       => [ 'pp.programa', 'like' ],
        'subprograma'    => [ 'pp.subprograma', 'like' ],
        'codigoproducto' => [ 'pp.codigoproducto', 'exact' ],
        'codigobpin'     => [ 'pp.codigobpin', 'exact' ],
    ];

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

    public function get_vigencias( int $anio, int $mes, string $compania = '001' ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'sysman_plan_presupuestal';

        return $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT tipovigencia FROM {$table}
             WHERE compania = %s AND anio = %d AND mes = %d AND tipovigencia != ''
             ORDER BY tipovigencia",
            $compania, $anio, $mes
        ) ) ?: [];
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

        $sql = "SELECT codigo, nombre, destino, naturaleza, codigobpin, sector, programa, subprograma, codigoproducto, movimiento
                FROM {$table}
                WHERE compania = %s AND anio = %d AND mes = %d AND nombredependencia = %s AND movimiento = 'SI'";
        $params = [ $meta['compania'], $meta['anio'], $meta['mes'], $meta['dependencia'] ];

        if ( ! empty( $meta['vigencia'] ) ) {
            $sql .= " AND tipovigencia = %s";
            $params[] = $meta['vigencia'];
        }

        $sql .= " ORDER BY codigo";

        $results = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

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
                    aplazamiento, desplazamiento, apropiacionvigente, disponibilidades,
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

    public function get_reservas( int $post_id, string $numero_dis, string $rubro = '' ): array {
        $meta = $this->get_post_meta( $post_id );
        if ( ! $meta ) {
            return [];
        }

        $cache_key = 'gn_sisman_ejec_res_' . md5( $post_id . $numero_dis . $rubro );
        $cached = get_transient( $cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sysman_auxiliar_cuentas';

        $sql = "SELECT numero, nombretercero, descripcion, nrodocumento, valordebito, saldoporejecutaresp, fecha
                FROM {$table}
                WHERE compania = %s AND anio = %d AND mes = %d AND tipocpte = 'RES' AND cmpteafectado = %s";
        $params = [ $meta['compania'], $meta['anio'], $meta['mes'], $numero_dis ];

        if ( '' !== $rubro ) {
            $sql .= " AND rubro = %s";
            $params[] = $rubro;
        }

        $sql .= " ORDER BY fecha, numero";

        $results = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

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

    public function get_export_data( int $post_id, array $options = [] ): array {
        $meta = $this->get_post_meta( $post_id );
        if ( ! $meta ) {
            return [];
        }

        $has_options = ! empty( $options );

        if ( ! $has_options ) {
            $cache_key = 'gn_sisman_ejec_export_' . md5( wp_json_encode( $meta ) );
            $cached    = get_transient( $cache_key );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        global $wpdb;
        $pp = $wpdb->prefix . 'sysman_plan_presupuestal';
        $eg = $wpdb->prefix . 'sysman_ejecucion_gastos';

        $select = "pp.codigo, pp.nombre, pp.destino, pp.naturaleza, pp.sector, pp.programa,
                   pp.subprograma, pp.codigoproducto, pp.codigobpin,
                   COALESCE(eg.apropiacioninicial, 0) AS apropiacioninicial,
                   COALESCE(eg.adicion, 0) AS adicion,
                   COALESCE(eg.reduccion, 0) AS reduccion,
                   COALESCE(eg.credito, 0) AS credito,
                   COALESCE(eg.contracredito, 0) AS contracredito,
                   COALESCE(eg.apropiacionvigente, 0) AS apropiacionvigente,
                   COALESCE(eg.disponibilidades, 0) AS disponibilidades,
                   COALESCE(eg.saldodisponible, 0) AS saldodisponible,
                   COALESCE(eg.compromisos, 0) AS compromisos,
                   COALESCE(eg.disponibilidadesabiertas, 0) AS disponibilidadesabiertas,
                   COALESCE(eg.obligacion, 0) AS obligacion,
                   COALESCE(eg.pagos, 0) AS pagos,
                   COALESCE(eg.obligacionesporpagar, 0) AS obligacionesporpagar";

        $from = "{$pp} pp
                 LEFT JOIN {$eg} eg ON pp.codigo = eg.codigocuenta
                     AND eg.compania = pp.compania AND eg.anio = pp.anio AND eg.mes = pp.mes";

        $where  = "pp.compania = %s AND pp.anio = %d AND pp.mes = %d
                   AND pp.nombredependencia = %s AND pp.movimiento = 'SI'";
        $params = [ $meta['compania'], $meta['anio'], $meta['mes'], $meta['dependencia'] ];

        if ( ! empty( $meta['vigencia'] ) ) {
            $where   .= " AND pp.tipovigencia = %s";
            $params[] = $meta['vigencia'];
        }

        $filters = $options['filtros'] ?? [];
        [ $f_clauses, $f_params ] = $this->build_filters( self::EXPORT_FILTERS, $filters );
        foreach ( $f_clauses as $c ) {
            $where .= " AND {$c}";
        }
        $params = array_merge( $params, $f_params );

        if ( ! empty( $options['buscar'] ) ) {
            [ $s_clauses, $s_params ] = $this->build_search( self::EXPORT_FILTERS, $options['buscar'] );
            foreach ( $s_clauses as $c ) {
                $where .= " AND {$c}";
            }
            $params = array_merge( $params, $s_params );
        }

        $has_pagination = isset( $options['per_page'] );
        $total = 0;

        if ( $has_pagination ) {
            $total = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$from} WHERE {$where}",
                $params
            ) );
        }

        $orderby = $options['orderby'] ?? '';
        if ( $orderby && isset( self::EXPORT_FILTERS[ $orderby ] ) ) {
            $dir          = strtoupper( $options['order'] ?? 'ASC' ) === 'DESC' ? 'DESC' : 'ASC';
            $order_clause = ' ORDER BY ' . self::EXPORT_FILTERS[ $orderby ][0] . " {$dir}";
        } else {
            $order_clause = ' ORDER BY pp.codigo';
        }

        $sql         = "SELECT {$select} FROM {$from} WHERE {$where}{$order_clause}";
        $data_params = $params;

        if ( $has_pagination ) {
            $per_page      = max( 1, min( 1000, (int) $options['per_page'] ) );
            $pagina        = max( 1, (int) ( $options['pagina'] ?? 1 ) );
            $offset        = ( $pagina - 1 ) * $per_page;
            $sql          .= ' LIMIT %d OFFSET %d';
            $data_params[] = $per_page;
            $data_params[] = $offset;
        }

        $results = $wpdb->get_results( $wpdb->prepare( $sql, $data_params ), ARRAY_A ) ?: [];

        if ( ! $has_options ) {
            set_transient( $cache_key, $results, 5 * MINUTE_IN_SECONDS );
        }

        if ( $has_pagination ) {
            $per_page = max( 1, min( 1000, (int) $options['per_page'] ) );
            $pagina   = max( 1, (int) ( $options['pagina'] ?? 1 ) );
            return [
                'data'     => $results,
                'total'    => $total,
                'pagina'   => $pagina,
                'per_page' => $per_page,
                'paginas'  => (int) ceil( $total / max( 1, $per_page ) ),
            ];
        }

        return $results;
    }

    public function get_disponibilidades_report( int $anio, int $mes, string $compania = '001', string $dependencia = '', array $options = [] ): array {
        $has_options = ! empty( $options );

        if ( ! $has_options ) {
            $cache_key = 'gn_sisman_dis_report_' . md5( $compania . '_' . $anio . '_' . $mes . '_' . $dependencia );
            $cached    = get_transient( $cache_key );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        global $wpdb;
        $ac = $wpdb->prefix . 'sysman_auxiliar_cuentas';
        $pp = $wpdb->prefix . 'sysman_plan_presupuestal';

        $select = "ac.numero, ac.tercero, ac.nombretercero, ac.rubro, ac.descripcion,
                   ac.nrodocumento, ac.valordebito, ac.valorcredito, ac.saldoporejecutaresp,
                   ac.cmpteafectado, ac.fecha, ac.anio, ac.mes,
                   pp.nombre AS nombrerubro, pp.dependencia, pp.nombredependencia,
                   pp.destino, pp.naturaleza, pp.sector, pp.programa, pp.subprograma,
                   pp.codigoproducto, pp.codigobpin";

        $from = "{$ac} ac
                 INNER JOIN {$pp} pp ON ac.rubro = pp.codigo
                     AND pp.compania = ac.compania AND pp.anio = ac.anio AND pp.mes = ac.mes";

        $where  = "ac.tipocpte = 'DIS' AND ac.compania = %s AND ac.anio = %d AND ac.mes = %d
                   AND pp.movimiento = 'SI'";
        $params = [ $compania, $anio, $mes ];

        if ( '' !== $dependencia ) {
            $where   .= " AND pp.nombredependencia = %s";
            $params[] = $dependencia;
        }

        $filters = $options['filtros'] ?? [];
        [ $f_clauses, $f_params ] = $this->build_filters( self::DIS_FILTERS, $filters );
        foreach ( $f_clauses as $c ) {
            $where .= " AND {$c}";
        }
        $params = array_merge( $params, $f_params );

        if ( ! empty( $options['buscar'] ) ) {
            [ $s_clauses, $s_params ] = $this->build_search( self::DIS_FILTERS, $options['buscar'] );
            foreach ( $s_clauses as $c ) {
                $where .= " AND {$c}";
            }
            $params = array_merge( $params, $s_params );
        }

        $has_pagination = isset( $options['per_page'] );
        $total = 0;

        if ( $has_pagination ) {
            $total = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$from} WHERE {$where}",
                $params
            ) );
        }

        $orderby = $options['orderby'] ?? '';
        if ( $orderby && isset( self::DIS_FILTERS[ $orderby ] ) ) {
            $dir          = strtoupper( $options['order'] ?? 'ASC' ) === 'DESC' ? 'DESC' : 'ASC';
            $order_clause = ' ORDER BY ' . self::DIS_FILTERS[ $orderby ][0] . " {$dir}";
        } else {
            $order_clause = ' ORDER BY ac.fecha, ac.numero';
        }

        $sql         = "SELECT {$select} FROM {$from} WHERE {$where}{$order_clause}";
        $data_params = $params;

        if ( $has_pagination ) {
            $per_page      = max( 1, min( 1000, (int) $options['per_page'] ) );
            $pagina        = max( 1, (int) ( $options['pagina'] ?? 1 ) );
            $offset        = ( $pagina - 1 ) * $per_page;
            $sql          .= ' LIMIT %d OFFSET %d';
            $data_params[] = $per_page;
            $data_params[] = $offset;
        }

        $results = $wpdb->get_results( $wpdb->prepare( $sql, $data_params ), ARRAY_A ) ?: [];

        if ( ! $has_options ) {
            set_transient( $cache_key, $results, 5 * MINUTE_IN_SECONDS );
        }

        if ( $has_pagination ) {
            $per_page = max( 1, min( 1000, (int) $options['per_page'] ) );
            $pagina   = max( 1, (int) ( $options['pagina'] ?? 1 ) );
            return [
                'data'     => $results,
                'total'    => $total,
                'pagina'   => $pagina,
                'per_page' => $per_page,
                'paginas'  => (int) ceil( $total / max( 1, $per_page ) ),
            ];
        }

        return $results;
    }

    public function get_contract_urls( array $nrodocumentos ): array {
        if ( empty( $nrodocumentos ) ) {
            return [];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'secop_contracts';

        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) !== $table ) {
            return [];
        }

        $placeholders = implode( ',', array_fill( 0, count( $nrodocumentos ), '%s' ) );
        $sql = "SELECT numero_de_proceso, url_contrato FROM {$table} WHERE numero_de_proceso IN ({$placeholders})";

        $results = $wpdb->get_results( $wpdb->prepare( $sql, $nrodocumentos ), ARRAY_A );

        $map = [];
        foreach ( $results as $row ) {
            $map[ $row['numero_de_proceso'] ] = $row['url_contrato'];
        }
        return $map;
    }

    private function build_filters( array $definitions, array $filters ): array {
        global $wpdb;
        $clauses = [];
        $params  = [];

        foreach ( $filters as $key => $value ) {
            if ( ! isset( $definitions[ $key ] ) || '' === (string) $value ) {
                continue;
            }
            [ $column, $type ] = $definitions[ $key ];
            if ( 'like' === $type ) {
                $clauses[] = "{$column} LIKE %s";
                $params[]  = '%' . $wpdb->esc_like( (string) $value ) . '%';
            } else {
                $clauses[] = "{$column} = %s";
                $params[]  = (string) $value;
            }
        }

        return [ $clauses, $params ];
    }

    private function build_search( array $definitions, string $term ): array {
        global $wpdb;
        $or      = [];
        $params  = [];
        $escaped = '%' . $wpdb->esc_like( $term ) . '%';

        foreach ( $definitions as [ $column, $type ] ) {
            if ( 'like' === $type ) {
                $or[]     = "{$column} LIKE %s";
                $params[] = $escaped;
            }
        }

        if ( empty( $or ) ) {
            return [ [], [] ];
        }

        return [ [ '(' . implode( ' OR ', $or ) . ')' ], $params ];
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
            'vigencia'    => get_post_meta( $post_id, '_gn_vigencia', true ),
        ];
    }
}
