<?php
namespace SysmanSuite\Presupuesto;

use SysmanSuite\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Generates the description, qualitative and quantitative readings that
 * accompany each Presupuesto view.
 *
 * Every sentence is derived from the figures actually returned by the query —
 * nothing is asserted that the data does not support. When a ratio cannot be
 * computed (no denominator) the corresponding sentence is simply omitted.
 */
class Analysis {

    /** Share of the total held by the top 3 above which we call it concentrated. */
    private const UMBRAL_CONCENTRACION = 0.60;

    /**
     * @param string $tipo  descripcion | cualitativo | cuantitativo
     * @return array{titulo:string, parrafos:string[], metricas:array}
     */
    public static function generar( string $tipo, string $vista, array $ctx, array $datos, array $opciones = [] ): array {
        $tipo = in_array( $tipo, [ 'descripcion', 'cualitativo', 'cuantitativo' ], true ) ? $tipo : 'descripcion';

        return match ( $tipo ) {
            'cualitativo'  => self::cualitativo( $vista, $ctx, $datos, $opciones ),
            'cuantitativo' => self::cuantitativo( $vista, $ctx, $datos, $opciones ),
            default        => self::descripcion( $vista, $ctx, $datos, $opciones ),
        };
    }

    // ─── Descripción ─────────────────────────────────────────────

    private static function descripcion( string $vista, array $ctx, array $datos, array $opciones ): array {
        $campo    = Repository::etiqueta_campo( $opciones['campo'] ?? 'apropiacionvigente' );
        $periodo  = self::periodo( $ctx );
        $filas    = $datos['filas'] ?? [];
        $total    = self::sumar( $filas );
        $n        = count( $filas );

        $parrafos = [];

        if ( 0 === $n ) {
            $parrafos[] = sprintf(
                'No hay registros de %s para %s. Verifique que los informes de Plan Presupuestal y Ejecución de Gastos estén importados para ese periodo.',
                mb_strtolower( $campo ),
                $periodo
            );
            return [ 'titulo' => 'Descripción', 'parrafos' => $parrafos, 'metricas' => [] ];
        }

        if ( 'dependencias' === $vista ) {
            $parrafos[] = sprintf(
                'La vista presenta %s de la entidad en %s, distribuida entre %d %s. El valor total asciende a %s.',
                mb_strtolower( $campo ),
                $periodo,
                $n,
                1 === $n ? 'dependencia' : 'dependencias',
                self::moneda( $total )
            );

            $mayor = $filas[0] ?? null;
            if ( $mayor && $total > 0 ) {
                $parrafos[] = sprintf(
                    'La dependencia con mayor valor es %s, con %s (%s del total).',
                    $mayor['label'],
                    self::moneda( (float) $mayor['value'] ),
                    self::porcentaje( (float) $mayor['value'] / $total )
                );
            }
        } else {
            $dependencia = $opciones['dependencia'] ?? '';
            $parrafos[]  = sprintf(
                'La vista detalla %d %s de %s en %s, por un valor de %s en %s.',
                $n,
                1 === $n ? 'rubro' : 'rubros',
                '' !== $dependencia ? $dependencia : 'todas las dependencias',
                $periodo,
                self::moneda( $total ),
                mb_strtolower( $campo )
            );
        }

        $parrafos[] = 'Solo se incluyen rubros marcados con movimiento en SYSMAN. Fuente: sistema SYSMAN — Gobernación de Nariño.';

        return [ 'titulo' => 'Descripción', 'parrafos' => $parrafos, 'metricas' => [] ];
    }

    // ─── Análisis cualitativo ────────────────────────────────────

    private static function cualitativo( string $vista, array $ctx, array $datos, array $opciones ): array {
        $filas = $datos['filas'] ?? [];
        $n     = count( $filas );
        $total = self::sumar( $filas );

        if ( 0 === $n || $total <= 0 ) {
            return [
                'titulo'   => 'Análisis cualitativo',
                'parrafos' => [ 'No hay datos suficientes para interpretar esta vista.' ],
                'metricas' => [],
            ];
        }

        $valores = array_map( static fn( $f ) => (float) $f['value'], $filas );
        rsort( $valores );

        $parrafos = [];
        $es_dep   = 'dependencias' === $vista;
        $etiqueta = $es_dep ? 'dependencias' : 'rubros';
        // Concordancia de genero: "las tres primeras dependencias" / "los tres primeros rubros".
        $primeras = $es_dep ? 'las tres primeras' : 'los tres primeros';
        $las_los  = $es_dep ? 'las' : 'los';

        // Concentración (participación del top 3).
        $top3 = array_sum( array_slice( $valores, 0, 3 ) ) / $total;
        $nombres_top = array_map(
            static fn( $f ) => $f['label'] ?? ( $f['nombre'] ?? '' ),
            array_slice( $filas, 0, 3 )
        );

        if ( $n >= 3 ) {
            $parrafos[] = $top3 >= self::UMBRAL_CONCENTRACION
                ? sprintf(
                    'El presupuesto está concentrado: %s %s (%s) reúnen %s del total. Una variación en cualquiera de %s mueve de forma apreciable la cifra global.',
                    $primeras,
                    $etiqueta,
                    implode( ', ', array_filter( $nombres_top ) ),
                    self::porcentaje( $top3 ),
                    $es_dep ? 'ellas' : 'ellos'
                )
                : sprintf(
                    'El presupuesto está repartido: %s %s concentran %s del total, de modo que ninguno domina la cifra global.',
                    $primeras,
                    $etiqueta,
                    self::porcentaje( $top3 )
                );
        }

        // Pareto: cuántas explican el 80 %.
        $acumulado = 0.0;
        $pareto    = 0;
        foreach ( $valores as $v ) {
            $acumulado += $v;
            $pareto++;
            if ( $acumulado / $total >= 0.80 ) {
                break;
            }
        }
        if ( $n > 3 ) {
            $parrafos[] = sprintf(
                '%d de %d %s (%s) explican el 80%% del valor; el resto aporta de forma marginal.',
                $pareto,
                $n,
                $etiqueta,
                self::porcentaje( $pareto / $n )
            );
        }

        // Niveles de ejecución sobre los totales del periodo.
        $totales = $datos['totales'] ?? [];
        $apr     = (float) ( $totales['apropiacionvigente'] ?? 0 );
        if ( $apr > 0 ) {
            $comp = (float) ( $totales['compromisos'] ?? 0 ) / $apr;
            $obl  = (float) ( $totales['obligacion'] ?? 0 ) / $apr;
            $pag  = (float) ( $totales['pagos'] ?? 0 ) / $apr;

            $parrafos[] = sprintf(
                'Frente a la apropiación vigente, se ha comprometido %s, obligado %s y pagado %s. %s',
                self::porcentaje( $comp ),
                self::porcentaje( $obl ),
                self::porcentaje( $pag ),
                self::juicio_ejecucion( $comp, $pag )
            );

            $brecha = $comp - $pag;
            if ( $brecha > 0.25 ) {
                $parrafos[] = sprintf(
                    'Hay una brecha de %s entre lo comprometido y lo pagado: recursos ya afectados por contratos que aún no se han desembolsado.',
                    self::porcentaje( $brecha )
                );
            }
        }

        // Dispersión.
        $media = $total / $n;
        $cv    = self::coeficiente_variacion( $valores, $media );
        if ( $cv > 1.5 && $n >= 5 ) {
            $parrafos[] = sprintf(
                'Los valores son muy desiguales entre sí (coeficiente de variación %.2f): conviven %s de magnitud muy distinta, por lo que el promedio no representa bien al conjunto.',
                $cv,
                $las_los . ' ' . $etiqueta
            );
        }

        return [ 'titulo' => 'Análisis cualitativo', 'parrafos' => $parrafos, 'metricas' => [] ];
    }

    /**
     * Plain-language verdict on the execution level, tied to how far the year
     * has advanced.
     */
    private static function juicio_ejecucion( float $comprometido, float $pagado ): string {
        if ( $comprometido >= 0.90 ) {
            return 'El nivel de compromiso es alto: la mayor parte del presupuesto ya está afectada.';
        }
        if ( $comprometido >= 0.60 ) {
            return 'El nivel de compromiso es intermedio.';
        }
        if ( $comprometido >= 0.30 ) {
            return 'El nivel de compromiso es bajo frente a la apropiación disponible.';
        }
        return 'El nivel de compromiso es muy bajo: queda una porción amplia del presupuesto sin afectar.';
    }

    // ─── Análisis cuantitativo ───────────────────────────────────

    private static function cuantitativo( string $vista, array $ctx, array $datos, array $opciones ): array {
        $filas = $datos['filas'] ?? [];
        $n     = count( $filas );

        if ( 0 === $n ) {
            return [
                'titulo'   => 'Análisis cuantitativo',
                'parrafos' => [ 'Sin registros para calcular estadísticas.' ],
                'metricas' => [],
            ];
        }

        $valores = array_map( static fn( $f ) => (float) $f['value'], $filas );
        sort( $valores );

        $total = array_sum( $valores );
        $media = $total / $n;

        $metricas = [
            [ 'label' => 'Registros', 'valor' => number_format_i18n( $n ), 'crudo' => $n ],
            [ 'label' => 'Total', 'valor' => self::moneda( $total ), 'crudo' => $total ],
            [ 'label' => 'Promedio', 'valor' => self::moneda( $media ), 'crudo' => $media ],
            [ 'label' => 'Mediana', 'valor' => self::moneda( self::mediana( $valores ) ), 'crudo' => self::mediana( $valores ) ],
            [ 'label' => 'Máximo', 'valor' => self::moneda( end( $valores ) ), 'crudo' => end( $valores ) ],
            [ 'label' => 'Mínimo', 'valor' => self::moneda( $valores[0] ), 'crudo' => $valores[0] ],
        ];

        if ( $n > 1 ) {
            $desv = self::desviacion( $valores, $media );
            $metricas[] = [ 'label' => 'Desviación estándar', 'valor' => self::moneda( $desv ), 'crudo' => $desv ];
            if ( $media > 0 ) {
                $metricas[] = [ 'label' => 'Coef. de variación', 'valor' => number_format_i18n( $desv / $media, 2 ), 'crudo' => $desv / $media ];
            }
        }

        // Ratios de ejecución.
        $totales = $datos['totales'] ?? [];
        $apr     = (float) ( $totales['apropiacionvigente'] ?? 0 );
        if ( $apr > 0 ) {
            foreach ( [
                'compromisos' => '% Comprometido',
                'obligacion'  => '% Obligado',
                'pagos'       => '% Pagado',
            ] as $campo => $label ) {
                $metricas[] = [
                    'label' => $label,
                    'valor' => self::porcentaje( (float) ( $totales[ $campo ] ?? 0 ) / $apr ),
                    'crudo' => (float) ( $totales[ $campo ] ?? 0 ) / $apr,
                ];
            }
        }

        $parrafos = [ sprintf(
            'Estadísticas sobre %s en %s. La mediana (%s) frente al promedio (%s) indica una distribución %s.',
            mb_strtolower( Repository::etiqueta_campo( $opciones['campo'] ?? 'apropiacionvigente' ) ),
            self::periodo( $ctx ),
            self::moneda( self::mediana( $valores ) ),
            self::moneda( $media ),
            self::mediana( $valores ) < $media * 0.6 ? 'muy sesgada hacia unos pocos valores altos' : 'relativamente equilibrada'
        ) ];

        return [ 'titulo' => 'Análisis cuantitativo', 'parrafos' => $parrafos, 'metricas' => $metricas ];
    }

    // ─── Utilidades ──────────────────────────────────────────────

    private static function sumar( array $filas ): float {
        return array_sum( array_map( static fn( $f ) => (float) ( $f['value'] ?? 0 ), $filas ) );
    }

    private static function mediana( array $ordenados ): float {
        $n = count( $ordenados );
        if ( 0 === $n ) {
            return 0.0;
        }
        $mitad = intdiv( $n, 2 );
        return 0 === $n % 2
            ? ( $ordenados[ $mitad - 1 ] + $ordenados[ $mitad ] ) / 2
            : $ordenados[ $mitad ];
    }

    private static function desviacion( array $valores, float $media ): float {
        $n = count( $valores );
        if ( $n < 2 ) {
            return 0.0;
        }
        $suma = 0.0;
        foreach ( $valores as $v ) {
            $suma += ( $v - $media ) ** 2;
        }
        return sqrt( $suma / ( $n - 1 ) );
    }

    private static function coeficiente_variacion( array $valores, float $media ): float {
        return $media > 0 ? self::desviacion( $valores, $media ) / $media : 0.0;
    }

    public static function moneda( float $valor ): string {
        return '$' . number_format_i18n( $valor, 0 );
    }

    private static function porcentaje( float $fraccion ): string {
        return number_format_i18n( $fraccion * 100, 1 ) . '%';
    }

    private static function periodo( array $ctx ): string {
        $mes = (int) ( $ctx['mes'] ?? 0 );
        return $mes > 0
            ? Helpers::month_name( $mes ) . ' de ' . (int) ( $ctx['anio'] ?? 0 )
            : 'la vigencia ' . (int) ( $ctx['anio'] ?? 0 );
    }
}
