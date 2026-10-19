<?php
namespace SysmanSuite;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Sincronización entre tablas: si los informes importados por separado
 * cuentan la misma historia.
 *
 * Las vistas de Gastos cruzan Plan Presupuestal (vigencia actual) con
 * Ejecución de Gastos por código, compañía, año y mes. Lo que no cruza no
 * aparece en ninguna parte —ni da error—, así que este informe mide cuánto
 * presupuesto queda fuera y por qué. Solo lee; no modifica datos.
 */
class Sincronizacion {

    /** Tablas mensuales y la etiqueta con que se muestran. */
    public const TABLAS_MENSUALES = [
        'sysman_plan_presupuestal'  => 'Plan presupuestal',
        'sysman_ejecucion_gastos'   => 'Ejecución de gastos',
        'sysman_auxiliar_cuentas'   => 'Auxiliar',
        'sysman_ejecucion_ingresos' => 'Ejecución de ingresos',
    ];

    /**
     * Horas de diferencia entre la importación del plan y la de la ejecución
     * del mismo mes a partir de las cuales se avisa: es probable que una de
     * las dos ya no corresponda al corte de la otra.
     */
    public const HORAS_DESFASE = 24;

    /** Cuántos códigos de ejemplo se devuelven en cada hallazgo. */
    private const MUESTRA = 10;

    /**
     * Periodos importados por tabla, con filas y fecha de la última importación.
     *
     * @return array<int, array{anio:int, mes:int, tablas:array, avisos:string[]}>
     */
    public function cobertura( string $compania, int $anio = 0 ): array {
        global $wpdb;

        $periodos = [];
        foreach ( self::TABLAS_MENSUALES as $nombre => $etiqueta ) {
            $tabla = $wpdb->prefix . $nombre;
            if ( ! $this->existe( $tabla ) ) {
                continue;
            }

            $where  = 'compania = %s';
            $params = [ $compania ];
            if ( $anio > 0 ) {
                $where   .= ' AND anio = %d';
                $params[] = $anio;
            }

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- tabla fija.
            $filas = $wpdb->get_results( $wpdb->prepare(
                "SELECT anio, mes, COUNT(*) AS filas, MAX(fecha_importacion) AS ultima
                 FROM `{$tabla}` WHERE {$where}
                 GROUP BY anio, mes ORDER BY anio DESC, mes DESC LIMIT 60",
                $params
            ), ARRAY_A ) ?: [];

            foreach ( $filas as $f ) {
                $clave = (int) $f['anio'] * 100 + (int) $f['mes'];
                $periodos[ $clave ]['anio'] = (int) $f['anio'];
                $periodos[ $clave ]['mes']  = (int) $f['mes'];
                $periodos[ $clave ]['tablas'][ $nombre ] = [
                    'filas'  => (int) $f['filas'],
                    'ultima' => (string) $f['ultima'],
                ];
            }
        }

        krsort( $periodos );

        return array_values( array_map( [ $this, 'con_avisos' ], $periodos ) );
    }

    /**
     * Avisos de un periodo de la cobertura: tablas de la cadena de gastos que
     * faltan o que se importaron en momentos muy distintos.
     */
    public function con_avisos( array $p ): array {
        $t      = $p['tablas'] ?? [];
        $avisos = [];

        $plan = isset( $t['sysman_plan_presupuestal'] );
        $ejec = isset( $t['sysman_ejecucion_gastos'] );

        if ( $ejec && ! $plan ) {
            $avisos[] = 'Hay ejecución de gastos pero no plan presupuestal: las vistas de Gastos de este mes salen vacías.';
        } elseif ( $plan && ! $ejec ) {
            $avisos[] = 'Hay plan presupuestal pero no ejecución de gastos: las vistas de Gastos de este mes salen vacías.';
        }
        if ( ( $plan || $ejec ) && ! isset( $t['sysman_auxiliar_cuentas'] ) ) {
            $avisos[] = 'Falta el auxiliar: la cadena disponibilidad → registro → obligación → egreso no tendrá detalle.';
        }

        if ( $plan && $ejec ) {
            $a = strtotime( $t['sysman_plan_presupuestal']['ultima'] );
            $b = strtotime( $t['sysman_ejecucion_gastos']['ultima'] );
            if ( $a && $b && abs( $a - $b ) > self::HORAS_DESFASE * HOUR_IN_SECONDS ) {
                $avisos[] = sprintf(
                    'El plan y la ejecución se importaron con %d horas de diferencia: reimporte ambos para que correspondan al mismo corte.',
                    (int) round( abs( $a - $b ) / HOUR_IN_SECONDS )
                );
            }
        }

        $p['tablas'] = $t;
        $p['avisos'] = $avisos;
        return $p;
    }

    /**
     * Cruce Plan ↔ Ejecución ↔ Auxiliar de un periodo.
     *
     * @return array{
     *   ejecucion: array{filas:int, apropiacion:float, compromisos:float},
     *   en_vistas: array{filas:int, apropiacion:float, compromisos:float},
     *   fuera_de_vistas: float, porcentaje_fuera: float,
     *   ejecucion_sin_plan: array{filas:int, apropiacion:float, ejemplos:string[]},
     *   plan_sin_ejecucion: array{filas:int, ejemplos:string[]},
     *   plan_repetido: array{codigos:int, inflado:float, ejemplos:string[]},
     *   plan_por_vigencia: array<string,int>,
     *   plan_sin_tipovigencia: int,
     *   auxiliar_sin_plan: array{rubros:int, ejemplos:string[]},
     *   avisos: string[]
     * }
     */
    public function cruce( string $compania, int $anio, int $mes ): array {
        global $wpdb;

        $pp = $wpdb->prefix . 'sysman_plan_presupuestal';
        $eg = $wpdb->prefix . 'sysman_ejecucion_gastos';
        $ac = $wpdb->prefix . 'sysman_auxiliar_cuentas';

        $periodo = [ $compania, $anio, $mes ];
        [ $vig, $vig_params ] = Helpers::filtro_vigencia( 'pp' );
        $vig_sql = '' !== $vig ? " AND {$vig}" : '';

        // Plan elegible para las vistas: mismo periodo, con movimiento, vigencia actual.
        $plan_ok = "pp.compania = eg.compania AND pp.anio = eg.anio AND pp.mes = eg.mes
                    AND pp.codigo = eg.codigocuenta AND pp.movimiento = 'SI'{$vig_sql}";

        // 1. Toda la ejecución del periodo.
        $total = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(*) AS filas, COALESCE(SUM(apropiacionvigente), 0) AS apropiacion,
                    COALESCE(SUM(compromisos), 0) AS compromisos
             FROM `{$eg}` WHERE compania = %s AND anio = %d AND mes = %d AND movimiento = 'SI'",
            $periodo
        ), ARRAY_A ) ?: [];

        // 2. Lo que suman las vistas (mismo JOIN que Presupuesto\Repository).
        $vistas = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(*) AS filas, COALESCE(SUM(eg.apropiacionvigente), 0) AS apropiacion,
                    COALESCE(SUM(eg.compromisos), 0) AS compromisos
             FROM `{$pp}` pp INNER JOIN `{$eg}` eg ON {$plan_ok}
             WHERE eg.compania = %s AND eg.anio = %d AND eg.mes = %d AND eg.movimiento = 'SI'",
            array_merge( $vig_params, $periodo )
        ), ARRAY_A ) ?: [];

        // 3. Ejecución que no encuentra su rubro en el plan: queda fuera de las vistas.
        $sin_plan_where = "eg.compania = %s AND eg.anio = %d AND eg.mes = %d AND eg.movimiento = 'SI'
                           AND NOT EXISTS (SELECT 1 FROM `{$pp}` pp WHERE {$plan_ok})";
        $sin_plan = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(*) AS filas, COALESCE(SUM(eg.apropiacionvigente), 0) AS apropiacion
             FROM `{$eg}` eg WHERE {$sin_plan_where}",
            array_merge( $periodo, $vig_params )
        ), ARRAY_A ) ?: [];
        $sin_plan_ej = $wpdb->get_col( $wpdb->prepare(
            "SELECT eg.codigocuenta FROM `{$eg}` eg WHERE {$sin_plan_where}
             ORDER BY eg.apropiacionvigente DESC LIMIT " . self::MUESTRA,
            array_merge( $periodo, $vig_params )
        ) ) ?: [];

        // 4. Rubros del plan sin fila de ejecución (aparecen en cero).
        $sin_ejec_where = "pp.compania = %s AND pp.anio = %d AND pp.mes = %d AND pp.movimiento = 'SI'{$vig_sql}
                           AND NOT EXISTS (SELECT 1 FROM `{$eg}` eg
                               WHERE eg.compania = pp.compania AND eg.anio = pp.anio AND eg.mes = pp.mes
                                 AND eg.codigocuenta = pp.codigo AND eg.movimiento = 'SI')";
        $sin_ejec    = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM `{$pp}` pp WHERE {$sin_ejec_where}",
            array_merge( $periodo, $vig_params )
        ) );
        $sin_ejec_ej = $wpdb->get_col( $wpdb->prepare(
            "SELECT pp.codigo FROM `{$pp}` pp WHERE {$sin_ejec_where} LIMIT " . self::MUESTRA,
            array_merge( $periodo, $vig_params )
        ) ) ?: [];

        // 5. Códigos repetidos en el plan de la vigencia actual: el JOIN suma
        //    su ejecución una vez por cada repetición.
        $repetidos = $wpdb->get_results( $wpdb->prepare(
            "SELECT r.codigo, r.veces, COALESCE(SUM(eg.apropiacionvigente), 0) AS apropiacion
             FROM (SELECT pp.codigo, COUNT(*) AS veces FROM `{$pp}` pp
                   WHERE pp.compania = %s AND pp.anio = %d AND pp.mes = %d AND pp.movimiento = 'SI'{$vig_sql}
                   GROUP BY pp.codigo HAVING COUNT(*) > 1) r
             LEFT JOIN `{$eg}` eg ON eg.codigocuenta = r.codigo AND eg.compania = %s
                  AND eg.anio = %d AND eg.mes = %d AND eg.movimiento = 'SI'
             GROUP BY r.codigo, r.veces
             ORDER BY apropiacion DESC",
            array_merge( $periodo, $vig_params, $periodo )
        ), ARRAY_A ) ?: [];
        $inflado = 0.0;
        foreach ( $repetidos as $r ) {
            $inflado += ( (int) $r['veces'] - 1 ) * (float) $r['apropiacion'];
        }

        // 6. Cómo viene repartido el plan por tipo de vigencia.
        $por_vigencia = [];
        $vig_filas    = $wpdb->get_results( $wpdb->prepare(
            "SELECT TRIM(tipovigencia) AS tipo, COUNT(*) AS filas FROM `{$pp}`
             WHERE compania = %s AND anio = %d AND mes = %d GROUP BY TRIM(tipovigencia)",
            $periodo
        ), ARRAY_A ) ?: [];
        foreach ( $vig_filas as $v ) {
            $por_vigencia[ (string) $v['tipo'] ] = (int) $v['filas'];
        }
        $sin_tipo = (int) ( $por_vigencia[''] ?? 0 );

        // 7. Rubros del auxiliar que no existen en el plan del periodo.
        $aux_where = "ac.compania = %s AND ac.anio = %d AND ac.mes = %d AND ac.rubro <> ''
                      AND NOT EXISTS (SELECT 1 FROM `{$pp}` pp WHERE pp.compania = ac.compania
                          AND pp.anio = ac.anio AND pp.mes = ac.mes AND pp.codigo = ac.rubro)";
        $aux_n  = $this->existe( $ac ) ? (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT ac.rubro) FROM `{$ac}` ac WHERE {$aux_where}",
            $periodo
        ) ) : 0;
        $aux_ej = $aux_n > 0 ? ( $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT ac.rubro FROM `{$ac}` ac WHERE {$aux_where} LIMIT " . self::MUESTRA,
            $periodo
        ) ) ?: [] ) : [];

        $resultado = [
            'periodo'               => [ 'compania' => $compania, 'anio' => $anio, 'mes' => $mes ],
            'ejecucion'             => $this->cifras( $total ),
            'en_vistas'             => $this->cifras( $vistas ),
            'ejecucion_sin_plan'    => [
                'filas'       => (int) ( $sin_plan['filas'] ?? 0 ),
                'apropiacion' => (float) ( $sin_plan['apropiacion'] ?? 0 ),
                'ejemplos'    => array_map( 'strval', $sin_plan_ej ),
            ],
            'plan_sin_ejecucion'    => [ 'filas' => $sin_ejec, 'ejemplos' => array_map( 'strval', $sin_ejec_ej ) ],
            'plan_repetido'         => [
                'codigos'  => count( $repetidos ),
                'inflado'  => $inflado,
                'ejemplos' => array_slice( array_map( static fn( $r ) => (string) $r['codigo'], $repetidos ), 0, self::MUESTRA ),
            ],
            'plan_por_vigencia'     => $por_vigencia,
            'plan_sin_tipovigencia' => $sin_tipo,
            'auxiliar_sin_plan'     => [ 'rubros' => $aux_n, 'ejemplos' => array_map( 'strval', $aux_ej ) ],
        ];

        return $this->con_diagnostico( $resultado );
    }

    /**
     * Diferencia entre la ejecución total y lo que muestran las vistas, más
     * los avisos en lenguaje llano. Separado de cruce() para poder probarlo.
     */
    public function con_diagnostico( array $r ): array {
        $total  = (float) $r['ejecucion']['apropiacion'];
        $vistas = (float) $r['en_vistas']['apropiacion'];
        $fuera  = (float) $r['ejecucion_sin_plan']['apropiacion'];

        $r['fuera_de_vistas']  = $fuera;
        $r['porcentaje_fuera'] = 0.0 !== $total ? $fuera / $total : 0.0;

        $avisos = [];
        if ( 0 === (int) $r['ejecucion']['filas'] ) {
            $avisos[] = 'No hay ejecución de gastos importada para este periodo.';
        }
        if ( (int) $r['plan_sin_tipovigencia'] > 0 ) {
            $avisos[] = sprintf(
                '%d filas del plan no traen tipo de vigencia (se importaron antes de que existiera la columna): quedan fuera de las vistas. Reimporte el plan de este periodo.',
                (int) $r['plan_sin_tipovigencia']
            );
        }
        if ( (int) $r['ejecucion_sin_plan']['filas'] > 0 ) {
            $avisos[] = sprintf(
                '%d rubros de la ejecución no están en el plan de la vigencia actual y dejan %s de apropiación fuera de las vistas (%s del total).',
                (int) $r['ejecucion_sin_plan']['filas'],
                \SysmanSuite\Presupuesto\Analysis::moneda( $fuera ),
                number_format( $r['porcentaje_fuera'] * 100, 2, ',', '.' ) . ' %'
            );
        }
        if ( (int) $r['plan_repetido']['codigos'] > 0 ) {
            $avisos[] = sprintf(
                '%d códigos aparecen más de una vez en el plan de la vigencia actual: las vistas suman su ejecución repetida (%s de más).',
                (int) $r['plan_repetido']['codigos'],
                \SysmanSuite\Presupuesto\Analysis::moneda( (float) $r['plan_repetido']['inflado'] )
            );
        }
        if ( (int) $r['auxiliar_sin_plan']['rubros'] > 0 ) {
            $avisos[] = sprintf(
                '%d rubros del auxiliar no existen en el plan de este periodo: su cadena de comprobantes no se puede consultar desde las vistas.',
                (int) $r['auxiliar_sin_plan']['rubros']
            );
        }

        // Sin repetidos, lo que muestran las vistas más lo que queda fuera es
        // exactamente la ejecución total: si no cuadra, algo más está mal.
        if ( 0 === (int) $r['plan_repetido']['codigos'] && abs( $total - $vistas - $fuera ) > 0.5 ) {
            $avisos[] = 'Las cifras de las vistas no cuadran con la ejecución total del periodo: revise la integridad de las tablas.';
        }

        $r['avisos']       = $avisos;
        $r['sincronizado'] = empty( $avisos );
        return $r;
    }

    private function cifras( array $f ): array {
        return [
            'filas'       => (int) ( $f['filas'] ?? 0 ),
            'apropiacion' => (float) ( $f['apropiacion'] ?? 0 ),
            'compromisos' => (float) ( $f['compromisos'] ?? 0 ),
        ];
    }

    private function existe( string $tabla ): bool {
        global $wpdb;
        return $tabla === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tabla ) );
    }
}
