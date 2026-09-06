<?php
namespace SysmanSuite\Presupuesto;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Queries for the Ingresos side of the Presupuesto module.
 *
 * Income has no dependencias: SYSMAN organises it as
 * tipo de recurso → fuente de recurso → cuenta. The first two work as the
 * grouping dimension and the cuenta is the leaf, so the same components used
 * for Gastos can drive this view by swapping the dimension.
 */
class IngresosRepository {

    /**
     * Metric columns that can be summed. `porcrecaudado` is deliberately out:
     * it is a percentage per row and adding those up is meaningless — the
     * module derives the real percentage from recaudos / total presupuesto.
     */
    public const CAMPOS = [
        'apropiado'          => 'Apropiado',
        'modificaciones'     => 'Modificaciones',
        'totalpresupuesto'   => 'Total Presupuesto',
        'recaudosanteriores' => 'Recaudos Anteriores',
        'recaudosmes'        => 'Recaudos del Mes',
        'recaudosacumulados' => 'Recaudos Acumulados',
        'porrecaudar'        => 'Por Recaudar',
    ];

    /**
     * Agrupaciones disponibles. `rubro` no es una columna: es el prefijo del
     * código de cuenta (por defecto 13 caracteres, «1.1.01.02.105»), que es
     * como el área financiera lee el presupuesto de ingresos cuando el tipo y
     * la fuente de recurso no vienen diligenciados.
     */
    public const DIMENSIONES = [
        'tiporecurso'   => 'Tipo de recurso',
        'fuenterecurso' => 'Fuente de recurso',
        'rubro'         => 'Rubro',
    ];

    /** Columna que respalda cada agrupación. */
    private const DIMENSION_COLUMNA = [
        'tiporecurso'   => 'tiporecurso',
        'fuenterecurso' => 'fuenterecurso',
        'rubro'         => 'cuenta',
    ];

    /** Caracteres del código de cuenta que identifican un rubro. */
    public const LONGITUD_RUBRO = 13;

    /**
     * Plurals written out instead of appending an "s": "tipo de recurso"
     * pluraliza el sustantivo de cabeza, no el final de la frase.
     */
    public const DIMENSIONES_PLURAL = [
        'tiporecurso'   => 'tipos de recurso',
        'fuenterecurso' => 'fuentes de recurso',
        'rubro'         => 'rubros',
    ];

    /** "Todos los …" / "Todas las …" según el género de la dimensión. */
    public const DIMENSIONES_TODAS = [
        'tiporecurso'   => 'Todos los tipos de recurso',
        'fuenterecurso' => 'Todas las fuentes de recurso',
        'rubro'         => 'Todos los rubros',
    ];

    /** Género gramatical de cada dimensión, para la concordancia del análisis. */
    public const DIMENSIONES_FEMENINO = [
        'tiporecurso'   => false,   // "los tipos de recurso"
        'fuenterecurso' => true,    // "las fuentes de recurso"
        'rubro'         => false,   // "los rubros"
    ];

    /**
     * Etiqueta para las filas cuya dimensión viene vacía.
     *
     * SYSMAN no siempre diligencia el tipo de recurso; si esas filas se
     * descartaran, la vista diría "no hay datos" teniendo la tabla llena.
     */
    public const SIN_CLASIFICAR = 'Sin clasificar';

    private const CACHE_TTL = 5 * MINUTE_IN_SECONDS;

    private static ?self $instance = null;

    public static function instance(): self {
        return self::$instance ??= new self();
    }

    public static function validar_campo( string $campo ): string {
        return array_key_exists( $campo, self::CAMPOS ) ? $campo : 'totalpresupuesto';
    }

    public static function etiqueta_campo( string $campo ): string {
        return self::CAMPOS[ $campo ] ?? $campo;
    }

    public static function validar_dimension( string $dimension ): string {
        return array_key_exists( $dimension, self::DIMENSIONES ) ? $dimension : 'tiporecurso';
    }

    public static function etiqueta_dimension( string $dimension ): string {
        return self::DIMENSIONES[ $dimension ] ?? $dimension;
    }

    public static function etiqueta_plural( string $dimension ): string {
        return self::DIMENSIONES_PLURAL[ self::validar_dimension( $dimension ) ];
    }

    public static function etiqueta_todas( string $dimension ): string {
        return self::DIMENSIONES_TODAS[ self::validar_dimension( $dimension ) ];
    }

    public static function es_femenino( string $dimension ): bool {
        return self::DIMENSIONES_FEMENINO[ self::validar_dimension( $dimension ) ];
    }

    /** Longitud de rubro válida: entre 1 y el ancho de la columna `cuenta`. */
    public static function validar_longitud( $longitud ): int {
        $n = (int) $longitud;
        return $n >= 1 && $n <= 50 ? $n : self::LONGITUD_RUBRO;
    }

    /**
     * Expresión SQL de la dimensión, con las filas sin valor agrupadas bajo una
     * etiqueta en lugar de quedar fuera de la consulta.
     *
     * Para `rubro` recorta el código de cuenta y quita el separador que quede
     * suelto al final, para que se lea «1.2.10.02-16» y no «1.2.10.02-16-».
     * Se evita TRIM(TRAILING …) a propósito: no es portable entre motores.
     */
    private function expr_dimension( string $dimension, int $longitud = self::LONGITUD_RUBRO ): string {
        $columna = self::DIMENSION_COLUMNA[ self::validar_dimension( $dimension ) ];

        if ( 'rubro' === $dimension ) {
            $n     = self::validar_longitud( $longitud );
            $corte = "SUBSTR(`{$columna}`, 1, {$n})";
            $base  = "CASE WHEN {$corte} LIKE '%.' OR {$corte} LIKE '%-'
                           THEN SUBSTR(`{$columna}`, 1, {$n} - 1)
                           ELSE {$corte} END";
        } else {
            $base = "TRIM(`{$columna}`)";
        }

        return "COALESCE(NULLIF({$base}, ''), '" . self::SIN_CLASIFICAR . "')";
    }

    /**
     * La dimensión que de verdad sirve para agrupar en este periodo.
     *
     * Si la pedida está vacía en todas las filas —pasa con `tiporecurso`— pero
     * la otra sí tiene valores, se usa la otra: mejor agrupar por fuente de
     * recurso que mostrar la página en blanco.
     */
    public function dimension_util( array $ctx, string $preferida = 'tiporecurso', int $longitud = self::LONGITUD_RUBRO ): string {
        global $wpdb;

        $preferida = self::validar_dimension( $preferida );
        $longitud  = self::validar_longitud( $longitud );
        $cache_key = 'sysman_pre_ing_dimutil_' . md5( wp_json_encode( [ $ctx, $preferida, $longitud ] ) );
        $cached    = get_transient( $cache_key );
        if ( is_string( $cached ) && '' !== $cached ) {
            return $cached;
        }

        $tabla   = $this->tabla();
        $elegida = $preferida;

        foreach ( array_unique( array_merge( [ $preferida ], array_keys( self::DIMENSIONES ) ) ) as $dim ) {
            $expr = $this->expr_dimension( $dim, $longitud );

            // Se piden dos valores distintos, no uno: una columna con el mismo
            // código en todas las filas —o toda vacía— no agrupa nada, y la
            // vista quedaría con un único bloque que ocupa el 100%.
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $distintos = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(DISTINCT {$expr}) FROM `{$tabla}`
                 WHERE compania = %s AND anio = %d AND mes = %d
                   AND movimiento = 'SI'
                   AND {$expr} <> '" . self::SIN_CLASIFICAR . "'",
                $ctx['compania'],
                $ctx['anio'],
                $ctx['mes']
            ) );

            if ( $distintos > 1 ) {
                $elegida = $dim;
                break;
            }
        }

        set_transient( $cache_key, $elegida, self::CACHE_TTL );
        return $elegida;
    }

    private function tabla(): string {
        global $wpdb;
        return $wpdb->prefix . 'sysman_ejecucion_ingresos';
    }

    /**
     * Normalize the period, defaulting to the latest one with income data.
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

    public function ultimo_periodo( string $compania = '001' ): array {
        global $wpdb;

        $cache_key = 'sysman_pre_ing_ultimo_' . md5( $compania );
        $cached    = get_transient( $cache_key );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $tabla = $this->tabla();
        $row   = $wpdb->get_row( $wpdb->prepare(
            "SELECT anio, mes FROM `{$tabla}` WHERE compania = %s AND movimiento = 'SI'
             ORDER BY anio DESC, mes DESC LIMIT 1",
            $compania
        ), ARRAY_A );

        $periodo = [ 'anio' => (int) ( $row['anio'] ?? 0 ), 'mes' => (int) ( $row['mes'] ?? 0 ) ];
        set_transient( $cache_key, $periodo, self::CACHE_TTL );
        return $periodo;
    }

    /**
     * Aggregated metric per dimension value (tipo o fuente de recurso).
     *
     * @return array<int, array{label:string, value:float, rubros:int}>
     */
    public function dimensiones( array $ctx, string $campo = 'totalpresupuesto', int $limite = 0, string $dimension = 'tiporecurso', array $extra = [], int $longitud = self::LONGITUD_RUBRO ): array {
        global $wpdb;

        $campo     = self::validar_campo( $campo );
        $dimension = self::validar_dimension( $dimension );
        $longitud  = self::validar_longitud( $longitud );
        $extra     = $this->validar_extra( $extra );

        $cache_key = 'sysman_pre_ing_dim_' . md5( wp_json_encode( [ $ctx, $campo, $limite, $dimension, $extra, $longitud ] ) );
        $cached    = get_transient( $cache_key );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $tabla = $this->tabla();
        $cols  = '';
        foreach ( $extra as $c ) {
            $cols .= ", SUM(`{$c}`) AS `{$c}`";
        }

        $expr = $this->expr_dimension( $dimension, $longitud );

        // MIN(nombre) da un nombre representativo del grupo: las cuentas de un
        // mismo rubro comparten el encabezado del concepto. Sin él, la lista de
        // rubros serían solo códigos.
        $sql = "SELECT {$expr} AS label,
                       MIN(nombre) AS nombre,
                       SUM(`{$campo}`) AS value,
                       COUNT(DISTINCT codigo) AS rubros,
                       SUM(totalpresupuesto) AS totalpresupuesto_base,
                       SUM(recaudosacumulados) AS recaudos_base
                       {$cols}
                FROM `{$tabla}`
                WHERE compania = %s AND anio = %d AND mes = %d
                  AND movimiento = 'SI'
                GROUP BY {$expr}
                HAVING value <> 0
                ORDER BY value DESC";

        if ( $limite > 0 ) {
            $sql .= ' LIMIT ' . (int) $limite;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $ctx['compania'], $ctx['anio'], $ctx['mes'] ), ARRAY_A ) ?: [];
        $out  = array_map( fn( $r ) => $this->fila_dimension( $r, $extra ), $rows );

        set_transient( $cache_key, $out, self::CACHE_TTL );
        return $out;
    }

    /**
     * Collection rate: how much of the budget is already collected.
     *
     * El gemelo del avance de gastos: sin valor agrupa por la dimensión, con
     * valor baja a sus cuentas.
     *
     * @return array<int, array{label:string, codigo:string, base:float, ejecutado:float, porcentaje:float|null}>
     */
    public function avance(
        array $ctx,
        string $valor = '',
        int $limite = 0,
        string $dimension = 'tiporecurso',
        string $numerador = 'recaudosacumulados',
        string $denominador = 'totalpresupuesto',
        int $longitud = self::LONGITUD_RUBRO
    ): array {
        global $wpdb;

        $dimension   = self::validar_dimension( $dimension );
        $longitud    = self::validar_longitud( $longitud );
        $numerador   = self::validar_campo( $numerador );
        $denominador = self::validar_campo( $denominador );

        $cache_key = 'sysman_pre_ing_avance_' . md5( wp_json_encode( [ $ctx, $valor, $limite, $dimension, $numerador, $denominador, $longitud ] ) );
        $cached    = get_transient( $cache_key );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $tabla     = $this->tabla();
        $por_cuenta = '' !== $valor;

        $where  = "compania = %s AND anio = %d AND mes = %d AND movimiento = 'SI'";
        $params = [ $ctx['compania'], $ctx['anio'], $ctx['mes'] ];

        $expr = $this->expr_dimension( $dimension, $longitud );

        if ( $por_cuenta ) {
            $where   .= " AND {$expr} = %s";
            $params[] = $valor;
            $etiqueta = 'nombre';
            $codigo   = 'codigo';
            $grupo    = 'codigo, nombre';
        } else {
            $etiqueta = $expr;
            $codigo   = "''";
            $grupo    = $expr;
        }

        // El nombre representativo del grupo: útil cuando la etiqueta es un
        // código de rubro y no dice nada por sí sola.
        $nombre = $por_cuenta ? 'nombre' : 'MIN(nombre)';

        $sql = "SELECT {$etiqueta} AS label,
                       {$nombre} AS nombre,
                       {$codigo} AS codigo,
                       SUM(`{$denominador}`) AS base,
                       SUM(`{$numerador}`)   AS ejecutado
                FROM `{$tabla}`
                WHERE {$where}
                GROUP BY {$grupo}
                HAVING base <> 0
                ORDER BY base DESC";

        if ( $limite > 0 ) {
            $sql .= ' LIMIT ' . (int) $limite;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) ?: [];

        $out = array_map( static function ( $r ) {
            $base      = (float) $r['base'];
            $ejecutado = (float) $r['ejecutado'];

            return [
                'label'      => (string) $r['label'],
                'nombre'     => (string) ( $r['nombre'] ?? '' ),
                'codigo'     => (string) $r['codigo'],
                'base'       => $base,
                'ejecutado'  => $ejecutado,
                'porcentaje' => 0.0 !== $base ? $ejecutado / $base : null,
                'value'      => 0.0 !== $base ? $ejecutado / $base : 0.0,
            ];
        }, $rows );

        set_transient( $cache_key, $out, self::CACHE_TTL );
        return $out;
    }

    /**
     * Income accounts inside one dimension value.
     */
    public function detalle( array $ctx, string $valor, string $campo = 'totalpresupuesto', string $dimension = 'tiporecurso', int $longitud = self::LONGITUD_RUBRO ): array {
        global $wpdb;

        $campo     = self::validar_campo( $campo );
        $dimension = self::validar_dimension( $dimension );
        $longitud  = self::validar_longitud( $longitud );

        $cache_key = 'sysman_pre_ing_det_' . md5( wp_json_encode( [ $ctx, $valor, $campo, $dimension, $longitud ] ) );
        $cached    = get_transient( $cache_key );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $tabla  = $this->tabla();
        $where  = "compania = %s AND anio = %d AND mes = %d AND movimiento = 'SI'";
        $params = [ $ctx['compania'], $ctx['anio'], $ctx['mes'] ];

        if ( '' !== $valor ) {
            $where   .= ' AND ' . $this->expr_dimension( $dimension, $longitud ) . ' = %s';
            $params[] = $valor;
        }

        $sql = "SELECT codigo, nombre, cuenta, tiporecurso, fuenterecurso,
                       SUM(`{$campo}`) AS value,
                       SUM(apropiado) AS apropiado,
                       SUM(modificaciones) AS modificaciones,
                       SUM(totalpresupuesto) AS totalpresupuesto,
                       SUM(recaudosanteriores) AS recaudosanteriores,
                       SUM(recaudosmes) AS recaudosmes,
                       SUM(recaudosacumulados) AS recaudosacumulados,
                       SUM(porrecaudar) AS porrecaudar
                FROM `{$tabla}`
                WHERE {$where}
                GROUP BY codigo, nombre, cuenta, tiporecurso, fuenterecurso
                ORDER BY value DESC, codigo
                LIMIT 500";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) ?: [];

        $numericas = array_keys( self::CAMPOS );
        $numericas[] = 'value';
        foreach ( $rows as &$r ) {
            foreach ( $numericas as $k ) {
                $r[ $k ] = (float) ( $r[ $k ] ?? 0 );
            }
            $r['porcentaje_recaudado'] = $r['totalpresupuesto'] > 0
                ? $r['recaudosacumulados'] / $r['totalpresupuesto']
                : null;
        }
        unset( $r );

        set_transient( $cache_key, $rows, self::CACHE_TTL );
        return $rows;
    }

    /**
     * Detail of a single income account: consolidated figures plus the
     * breakdown of what has been collected. There is no document chain here —
     * income is reported as accumulated balances, not as CDP/RP documents.
     */
    public function item( array $ctx, string $codigo ): array {
        global $wpdb;

        $tabla = $this->tabla();
        $cols  = implode( ', ', array_map(
            static fn( $c ) => "SUM(`{$c}`) AS `{$c}`",
            array_keys( self::CAMPOS )
        ) );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT {$cols}, MAX(nombre) AS nombre, MAX(cuenta) AS cuenta,
                    MAX(tiporecurso) AS tiporecurso, MAX(fuenterecurso) AS fuenterecurso
             FROM `{$tabla}`
             WHERE compania = %s AND anio = %d AND mes = %d AND codigo = %s AND movimiento = 'SI'",
            $ctx['compania'], $ctx['anio'], $ctx['mes'], $codigo
        ), ARRAY_A );

        if ( ! $row || null === $row['totalpresupuesto'] ) {
            return [ 'consolidado' => [], 'recaudo' => [], 'clasificacion' => [] ];
        }

        $consolidado = [];
        foreach ( array_keys( self::CAMPOS ) as $c ) {
            $consolidado[ $c ] = (float) $row[ $c ];
        }

        $total = $consolidado['totalpresupuesto'];
        $consolidado['porcentaje_recaudado'] = $total > 0 ? $consolidado['recaudosacumulados'] / $total : null;

        // Composition of what has been collected, for the progress readout.
        $recaudo = [
            [ 'label' => 'Recaudos anteriores', 'value' => $consolidado['recaudosanteriores'] ],
            [ 'label' => 'Recaudos del mes',    'value' => $consolidado['recaudosmes'] ],
            [ 'label' => 'Recaudos acumulados', 'value' => $consolidado['recaudosacumulados'] ],
            [ 'label' => 'Por recaudar',        'value' => $consolidado['porrecaudar'] ],
        ];

        return [
            'consolidado'   => $consolidado,
            'recaudo'       => $recaudo,
            'clasificacion' => [
                'cuenta'        => (string) $row['cuenta'],
                'nombre'        => (string) $row['nombre'],
                'tiporecurso'   => (string) $row['tiporecurso'],
                'fuenterecurso' => (string) $row['fuenterecurso'],
            ],
        ];
    }

    /**
     * Aggregated totals for the period (feeds the analysis engine).
     */
    public function totales( array $ctx, string $valor = '', string $dimension = 'tiporecurso', int $longitud = self::LONGITUD_RUBRO ): array {
        global $wpdb;

        $dimension = self::validar_dimension( $dimension );
        $longitud  = self::validar_longitud( $longitud );
        $tabla     = $this->tabla();

        $where  = "compania = %s AND anio = %d AND mes = %d AND movimiento = 'SI'";
        $params = [ $ctx['compania'], $ctx['anio'], $ctx['mes'] ];

        if ( '' !== $valor ) {
            $where   .= ' AND ' . $this->expr_dimension( $dimension, $longitud ) . ' = %s';
            $params[] = $valor;
        }

        $cols = implode( ', ', array_map(
            static fn( $c ) => "SUM(`{$c}`) AS `{$c}`",
            array_keys( self::CAMPOS )
        ) );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT {$cols}, COUNT(DISTINCT codigo) AS rubros FROM `{$tabla}` WHERE {$where}",
            $params
        ), ARRAY_A );

        if ( ! $row ) {
            return [];
        }

        $out = array_map( 'floatval', $row );
        $out['rubros'] = (int) $row['rubros'];
        return $out;
    }

    /**
     * Keep only whitelisted metric columns from a caller-supplied list.
     */
    private function validar_extra( array $campos ): array {
        $out = [];
        foreach ( $campos as $c ) {
            $c = sanitize_text_field( (string) $c );
            if ( array_key_exists( $c, self::CAMPOS ) && ! in_array( $c, $out, true ) ) {
                $out[] = $c;
            }
        }
        return $out;
    }

    private function fila_dimension( array $r, array $extra ): array {
        $total    = (float) ( $r['totalpresupuesto_base'] ?? 0 );
        $recaudos = (float) ( $r['recaudos_base'] ?? 0 );

        $fila = [
            'label'                => (string) $r['label'],
            'nombre'               => (string) ( $r['nombre'] ?? '' ),
            'value'                => (float) $r['value'],
            'rubros'               => (int) $r['rubros'],
            'porcentaje_recaudado' => $total > 0 ? $recaudos / $total : null,
        ];
        foreach ( $extra as $c ) {
            $fila[ $c ] = (float) ( $r[ $c ] ?? 0 );
        }
        return $fila;
    }
}
