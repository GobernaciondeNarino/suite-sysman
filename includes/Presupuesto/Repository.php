<?php
namespace SysmanSuite\Presupuesto;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Queries for the Presupuesto module.
 *
 * Everything is scoped by a period context (compania/anio/mes) instead of a
 * seguimiento post, so the shortcodes work standalone on any page.
 */
class Repository {

    /**
     * Numeric columns of ejecucion_gastos usable as a metric.
     * Whitelist: the key is what a shortcode's `campo` attribute accepts.
     */
    public const CAMPOS = [
        'apropiacioninicial'       => 'Apropiación Inicial',
        'adicion'                  => 'Adición',
        'reduccion'                => 'Reducción',
        'credito'                  => 'Crédito',
        'contracredito'            => 'Contracrédito',
        'aplazamiento'             => 'Aplazamiento',
        'desplazamiento'           => 'Desplazamiento',
        'apropiacionvigente'       => 'Apropiación Vigente',
        'disponibilidades'         => 'Disponibilidades',
        'saldodisponible'          => 'Saldo Disponible',
        'compromisos'              => 'Compromisos',
        'disponibilidadesabiertas' => 'Disponibilidades Abiertas',
        'obligacion'               => 'Obligación',
        'pagos'                    => 'Pagos',
        'obligacionesporpagar'     => 'Obligaciones por Pagar',
    ];

    /** Columns that represent budget modifications of a rubro. */
    public const CAMPOS_MODIFICACION = [
        'adicion'        => 'Adición',
        'reduccion'      => 'Reducción',
        'credito'        => 'Crédito',
        'contracredito'  => 'Contracrédito',
        'aplazamiento'   => 'Aplazamiento',
        'desplazamiento' => 'Desplazamiento',
    ];

    /** Document chain: each type points at the one it affects. */
    public const CADENA = [
        'DIS' => 'Disponibilidad (CDP)',
        'RES' => 'Registro de compromiso (RP)',
        'OBL' => 'Obligación',
        'EGR' => 'Egreso (pago)',
    ];

    private const CACHE_TTL = 5 * MINUTE_IN_SECONDS;

    private static ?self $instance = null;

    public static function instance(): self {
        return self::$instance ??= new self();
    }

    /**
     * Validate a metric column name against the whitelist.
     */
    public static function validar_campo( string $campo ): string {
        return array_key_exists( $campo, self::CAMPOS ) ? $campo : 'apropiacionvigente';
    }

    public static function etiqueta_campo( string $campo ): string {
        return self::CAMPOS[ $campo ] ?? $campo;
    }

    /**
     * Normalize a period context, filling anio/mes from the latest period
     * that actually has data when the caller left them at 0.
     */
    public function contexto( array $args = [] ): array {
        $compania = sanitize_text_field( (string) ( $args['compania'] ?? '001' ) ) ?: '001';
        $anio     = (int) ( $args['anio'] ?? 0 );
        $mes      = (int) ( $args['mes'] ?? 0 );

        if ( $anio <= 0 || $mes <= 0 ) {
            $ultimo = $this->ultimo_periodo( $compania );
            $anio   = $anio > 0 ? $anio : $ultimo['anio'];
            $mes    = $mes > 0 ? $mes : $ultimo['mes'];
        }

        return [ 'compania' => $compania, 'anio' => $anio, 'mes' => $mes ];
    }

    /**
     * Most recent period with execution data for a company.
     */
    public function ultimo_periodo( string $compania = '001' ): array {
        global $wpdb;

        $cache_key = 'sysman_pre_ultimo_' . md5( $compania );
        $cached    = get_transient( $cache_key );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $eg  = $wpdb->prefix . 'sysman_ejecucion_gastos';
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT anio, mes FROM `{$eg}` WHERE compania = %s AND movimiento = 'SI'
             ORDER BY anio DESC, mes DESC LIMIT 1",
            $compania
        ), ARRAY_A );

        $periodo = [
            'anio' => (int) ( $row['anio'] ?? 0 ),
            'mes'  => (int) ( $row['mes'] ?? 0 ),
        ];

        set_transient( $cache_key, $periodo, self::CACHE_TTL );
        return $periodo;
    }

    /**
     * Every period that has execution data, newest first.
     *
     * @return array<int, array{anio:int, mes:int}>
     */
    public function periodos( string $compania = '001' ): array {
        global $wpdb;
        $eg = $wpdb->prefix . 'sysman_ejecucion_gastos';

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT DISTINCT anio, mes FROM `{$eg}` WHERE compania = %s AND movimiento = 'SI'
             ORDER BY anio DESC, mes DESC LIMIT 60",
            $compania
        ), ARRAY_A ) ?: [];

        return array_map(
            static fn( $r ) => [ 'anio' => (int) $r['anio'], 'mes' => (int) $r['mes'] ],
            $rows
        );
    }

    /**
     * Aggregated metric per dependencia — the treemap and the master list.
     *
     * @return array<int, array{label:string, value:float, rubros:int}>
     */
    public function dependencias( array $ctx, string $campo = 'apropiacionvigente', int $limite = 0 ): array {
        global $wpdb;

        $campo     = self::validar_campo( $campo );
        $cache_key = 'sysman_pre_deps_' . md5( wp_json_encode( [ $ctx, $campo, $limite ] ) );
        $cached    = get_transient( $cache_key );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $pp = $wpdb->prefix . 'sysman_plan_presupuestal';
        $eg = $wpdb->prefix . 'sysman_ejecucion_gastos';

        $sql = "SELECT pp.nombredependencia AS label,
                       SUM(eg.`{$campo}`) AS value,
                       COUNT(DISTINCT pp.codigo) AS rubros
                FROM `{$pp}` pp
                INNER JOIN `{$eg}` eg
                    ON pp.codigo = eg.codigocuenta AND pp.compania = eg.compania
                   AND pp.anio = eg.anio AND pp.mes = eg.mes
                WHERE pp.compania = %s AND pp.anio = %d AND pp.mes = %d
                  AND pp.movimiento = 'SI' AND eg.movimiento = 'SI'
                  AND pp.nombredependencia <> ''
                GROUP BY pp.nombredependencia
                HAVING value <> 0
                ORDER BY value DESC";

        if ( $limite > 0 ) {
            $sql .= ' LIMIT ' . (int) $limite;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $ctx['compania'], $ctx['anio'], $ctx['mes'] ), ARRAY_A ) ?: [];

        $out = array_map( static fn( $r ) => [
            'label'  => (string) $r['label'],
            'value'  => (float) $r['value'],
            'rubros' => (int) $r['rubros'],
        ], $rows );

        set_transient( $cache_key, $out, self::CACHE_TTL );
        return $out;
    }

    /**
     * Rubros of a dependencia with the headline execution figures.
     */
    public function rubros( array $ctx, string $dependencia, string $campo = 'apropiacionvigente' ): array {
        global $wpdb;

        $campo     = self::validar_campo( $campo );
        $cache_key = 'sysman_pre_rubros_' . md5( wp_json_encode( [ $ctx, $dependencia, $campo ] ) );
        $cached    = get_transient( $cache_key );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $pp = $wpdb->prefix . 'sysman_plan_presupuestal';
        $eg = $wpdb->prefix . 'sysman_ejecucion_gastos';

        $where  = "pp.compania = %s AND pp.anio = %d AND pp.mes = %d
                   AND pp.movimiento = 'SI' AND eg.movimiento = 'SI'";
        $params = [ $ctx['compania'], $ctx['anio'], $ctx['mes'] ];

        if ( '' !== $dependencia ) {
            $where   .= ' AND pp.nombredependencia = %s';
            $params[] = $dependencia;
        }

        $sql = "SELECT pp.codigo, pp.nombre, pp.destino, pp.naturaleza, pp.codigobpin,
                       pp.nombredependencia,
                       SUM(eg.`{$campo}`) AS value,
                       SUM(eg.apropiacionvigente) AS apropiacionvigente,
                       SUM(eg.disponibilidades)   AS disponibilidades,
                       SUM(eg.compromisos)        AS compromisos,
                       SUM(eg.obligacion)         AS obligacion,
                       SUM(eg.pagos)              AS pagos,
                       SUM(eg.saldodisponible)    AS saldodisponible
                FROM `{$pp}` pp
                INNER JOIN `{$eg}` eg
                    ON pp.codigo = eg.codigocuenta AND pp.compania = eg.compania
                   AND pp.anio = eg.anio AND pp.mes = eg.mes
                WHERE {$where}
                GROUP BY pp.codigo, pp.nombre, pp.destino, pp.naturaleza, pp.codigobpin, pp.nombredependencia
                ORDER BY value DESC, pp.codigo
                LIMIT 500";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) ?: [];

        $numericas = [ 'value', 'apropiacionvigente', 'disponibilidades', 'compromisos', 'obligacion', 'pagos', 'saldodisponible' ];
        foreach ( $rows as &$r ) {
            foreach ( $numericas as $k ) {
                $r[ $k ] = (float) $r[ $k ];
            }
        }
        unset( $r );

        set_transient( $cache_key, $rows, self::CACHE_TTL );
        return $rows;
    }

    /**
     * Aggregated execution totals for a period, optionally for one dependencia.
     * Feeds the ratios used by the analysis engine.
     */
    public function totales( array $ctx, string $dependencia = '' ): array {
        global $wpdb;

        $cache_key = 'sysman_pre_totales_' . md5( wp_json_encode( [ $ctx, $dependencia ] ) );
        $cached    = get_transient( $cache_key );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $pp = $wpdb->prefix . 'sysman_plan_presupuestal';
        $eg = $wpdb->prefix . 'sysman_ejecucion_gastos';

        $where  = "pp.compania = %s AND pp.anio = %d AND pp.mes = %d
                   AND pp.movimiento = 'SI' AND eg.movimiento = 'SI'";
        $params = [ $ctx['compania'], $ctx['anio'], $ctx['mes'] ];

        if ( '' !== $dependencia ) {
            $where   .= ' AND pp.nombredependencia = %s';
            $params[] = $dependencia;
        }

        $cols = implode( ', ', array_map(
            static fn( $c ) => "SUM(eg.`{$c}`) AS `{$c}`",
            array_keys( self::CAMPOS )
        ) );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT {$cols}, COUNT(DISTINCT pp.codigo) AS rubros,
                    COUNT(DISTINCT pp.nombredependencia) AS dependencias
             FROM `{$pp}` pp
             INNER JOIN `{$eg}` eg
                 ON pp.codigo = eg.codigocuenta AND pp.compania = eg.compania
                AND pp.anio = eg.anio AND pp.mes = eg.mes
             WHERE {$where}",
            $params
        ), ARRAY_A );

        if ( ! $row ) {
            return [];
        }

        $out = array_map( 'floatval', $row );
        $out['rubros']       = (int) $row['rubros'];
        $out['dependencias'] = (int) $row['dependencias'];

        set_transient( $cache_key, $out, self::CACHE_TTL );
        return $out;
    }

    /**
     * Full detail of one rubro: consolidated figures, budget modifications and
     * the document chain (DIS → RES → OBL → EGR).
     */
    public function rubro( array $ctx, string $codigo ): array {
        return [
            'consolidado'    => $this->consolidado( $ctx, $codigo ),
            'modificaciones' => $this->modificaciones( $ctx, $codigo ),
            'cadena'         => $this->cadena( $ctx, $codigo ),
        ];
    }

    /**
     * Consolidated execution figures of a rubro.
     */
    public function consolidado( array $ctx, string $codigo ): array {
        global $wpdb;
        $eg = $wpdb->prefix . 'sysman_ejecucion_gastos';

        $cols = implode( ', ', array_map(
            static fn( $c ) => "SUM(`{$c}`) AS `{$c}`",
            array_keys( self::CAMPOS )
        ) );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT {$cols} FROM `{$eg}`
             WHERE compania = %s AND anio = %d AND mes = %d AND codigocuenta = %s AND movimiento = 'SI'",
            $ctx['compania'], $ctx['anio'], $ctx['mes'], $codigo
        ), ARRAY_A );

        if ( ! $row ) {
            return [];
        }

        return array_map( 'floatval', $row );
    }

    /**
     * Budget modifications of a rubro, as label/value pairs (zeros dropped).
     */
    public function modificaciones( array $ctx, string $codigo ): array {
        $consolidado = $this->consolidado( $ctx, $codigo );
        if ( empty( $consolidado ) ) {
            return [];
        }

        $out = [];
        foreach ( self::CAMPOS_MODIFICACION as $campo => $etiqueta ) {
            $valor = (float) ( $consolidado[ $campo ] ?? 0 );
            if ( abs( $valor ) > 0.005 ) {
                $out[] = [ 'campo' => $campo, 'label' => $etiqueta, 'value' => $valor ];
            }
        }
        return $out;
    }

    /**
     * Document chain of a rubro, nested DIS → RES → OBL → EGR.
     *
     * All rows are fetched in a single query and linked in PHP through
     * (tipocpteafect, cmpteafectado), which is how SYSMAN points each document
     * at the one it affects. Documents whose parent is missing from the period
     * are returned under `huerfanos` so nothing is silently dropped.
     */
    public function cadena( array $ctx, string $codigo ): array {
        global $wpdb;

        $cache_key = 'sysman_pre_cadena_' . md5( wp_json_encode( [ $ctx, $codigo ] ) );
        $cached    = get_transient( $cache_key );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $ac   = $wpdb->prefix . 'sysman_auxiliar_cuentas';
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT numero, tipocpte, tipocpteafect, cmpteafectado, fecha, tercero, nombretercero,
                    descripcion, nrodocumento, valordebito, valorcredito, saldoporejecutaresp
             FROM `{$ac}`
             WHERE compania = %s AND anio = %d AND mes = %d AND rubro = %s
             ORDER BY fecha, numero
             LIMIT 2000",
            $ctx['compania'], $ctx['anio'], $ctx['mes'], $codigo
        ), ARRAY_A ) ?: [];

        $resultado = $this->armar_cadena( $rows );
        set_transient( $cache_key, $resultado, self::CACHE_TTL );
        return $resultado;
    }

    /**
     * Build the nested chain from a flat list of auxiliar rows.
     * Kept separate from the query so it can be unit-tested.
     */
    public function armar_cadena( array $rows ): array {
        $normalizar = static function ( array $r ): array {
            return [
                'numero'        => (string) $r['numero'],
                'tipo'          => (string) $r['tipocpte'],
                'tipo_label'    => self::CADENA[ $r['tipocpte'] ] ?? (string) $r['tipocpte'],
                'fecha'         => (string) $r['fecha'],
                'tercero'       => (string) $r['tercero'],
                'nombretercero' => (string) $r['nombretercero'],
                'descripcion'   => (string) $r['descripcion'],
                'nrodocumento'  => (string) $r['nrodocumento'],
                'valor'         => (float) $r['valordebito'],
                'saldo'         => (float) $r['saldoporejecutaresp'],
                'hijos'         => [],
            ];
        };

        // Index children by the document they affect.
        $hijos_de = [];
        $porTipo  = [];
        foreach ( $rows as $r ) {
            $tipo = (string) $r['tipocpte'];
            $porTipo[ $tipo ][] = $r;

            $padre_tipo = (string) $r['tipocpteafect'];
            $padre_num  = (string) $r['cmpteafectado'];
            if ( '' !== $padre_num ) {
                $hijos_de[ $padre_tipo . '|' . $padre_num ][] = $r;
            }
        }

        $usados = [];
        $anidar = function ( array $fila ) use ( &$anidar, &$hijos_de, &$usados, $normalizar ): array {
            $nodo  = $normalizar( $fila );
            $clave = $fila['tipocpte'] . '|' . $fila['numero'];
            $usados[ $clave ] = true;

            foreach ( $hijos_de[ $clave ] ?? [] as $hijo ) {
                $clave_hijo = $hijo['tipocpte'] . '|' . $hijo['numero'];
                if ( isset( $usados[ $clave_hijo ] ) ) {
                    continue; // Defensive: never loop on malformed data.
                }
                $nodo['hijos'][] = $anidar( $hijo );
            }
            return $nodo;
        };

        $raiz = [];
        foreach ( $porTipo['DIS'] ?? [] as $dis ) {
            $raiz[] = $anidar( $dis );
        }

        // Documents whose parent is not in this period would otherwise vanish.
        $huerfanos = [];
        foreach ( $rows as $r ) {
            if ( 'DIS' === $r['tipocpte'] ) {
                continue;
            }
            $clave = $r['tipocpte'] . '|' . $r['numero'];
            if ( ! isset( $usados[ $clave ] ) ) {
                $huerfanos[] = $anidar( $r );
            }
        }

        $conteo = [];
        foreach ( array_keys( self::CADENA ) as $tipo ) {
            $conteo[ $tipo ] = count( $porTipo[ $tipo ] ?? [] );
        }

        return [
            'documentos' => $raiz,
            'huerfanos'  => $huerfanos,
            'conteo'     => $conteo,
        ];
    }

    /**
     * Clear every cached Presupuesto query (called after an import).
     */
    public static function limpiar_cache(): void {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_sysman\\_pre\\_%' "
            . "OR option_name LIKE '\\_transient\\_timeout\\_sysman\\_pre\\_%'"
        );
    }
}
