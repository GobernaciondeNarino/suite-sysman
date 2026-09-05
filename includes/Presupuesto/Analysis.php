<?php
namespace SysmanSuite\Presupuesto;

use SysmanSuite\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Generates the description, qualitative and quantitative readings that
 * accompany each Presupuesto view, for both Gastos and Ingresos.
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
     * @param string $vista dimensiones | detalle
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

    /**
     * Vocabulary for the module and level being analysed, so the wording reads
     * naturally in Spanish (gender agreement included).
     */
    private static function vocabulario( string $vista, array $opciones ): array {
        $ingresos   = 'ingresos' === ( $opciones['modulo'] ?? 'gastos' );
        $es_detalle = 'detalle' === $vista;

        if ( $ingresos ) {
            // El plural y el género llegan resueltos desde IngresosRepository:
            // en español no basta con añadir "s" ("tipo de recurso" → "tipos de
            // recurso") ni el género es fijo ("los tipos" / "las fuentes").
            $grupo  = mb_strtolower( $opciones['dimension_label'] ?? 'tipo de recurso' );
            $plural = mb_strtolower( $opciones['dimension_plural'] ?? $grupo . 's' );
            $fem    = ! empty( $opciones['dimension_femenino'] );

            return [
                'ingresos'   => true,
                'es_detalle' => $es_detalle,
                'plural'     => $es_detalle ? 'cuentas de ingreso' : $plural,
                'singular'   => $es_detalle ? 'cuenta de ingreso' : $grupo,
                'femenino'   => $es_detalle ? true : $fem,   // "cuentas" siempre es femenino
                'ambito'     => $es_detalle ? 'las cuentas' : ( $fem ? 'las ' : 'los ' ) . $plural,
            ];
        }

        return [
            'ingresos'   => false,
            'es_detalle' => $es_detalle,
            'plural'     => $es_detalle ? 'rubros' : 'dependencias',
            'singular'   => $es_detalle ? 'rubro' : 'dependencia',
            'femenino'   => ! $es_detalle,
            'ambito'     => $es_detalle ? 'los rubros' : 'las dependencias',
        ];
    }

    // ─── Descripción ─────────────────────────────────────────────

    private static function descripcion( string $vista, array $ctx, array $datos, array $opciones ): array {
        $v       = self::vocabulario( $vista, $opciones );
        $campo   = mb_strtolower( $opciones['campo_label'] ?? 'valor' );
        $periodo = self::periodo( $ctx );
        $filas   = $datos['filas'] ?? [];
        $total   = self::sumar( $filas );
        $n       = count( $filas );

        if ( 0 === $n ) {
            $informe = $v['ingresos'] ? 'Ejecución de Ingresos' : 'Plan Presupuestal y Ejecución de Gastos';
            return [
                'titulo'   => 'Descripción',
                'parrafos' => [ sprintf(
                    'No hay registros de %s para %s. Verifique que el informe de %s esté importado para ese periodo.',
                    $campo, $periodo, $informe
                ) ],
                'metricas' => [],
            ];
        }

        $parrafos = [];
        $valor    = $opciones['valor'] ?? '';

        if ( $v['es_detalle'] ) {
            $parrafos[] = sprintf(
                'Esta vista detalla %d %s de %s en %s, por un valor de %s en %s.',
                $n,
                1 === $n ? $v['singular'] : $v['plural'],
                '' !== $valor ? $valor : 'toda la entidad',
                $periodo,
                self::moneda( $total ),
                $campo
            );
        } else {
            // "el total de …, repartido entre …": la concordancia va con
            // "el total", así que no depende del género de la métrica.
            $parrafos[] = sprintf(
                'Esta vista presenta las cifras de %s de la entidad en %s, repartidas entre %d %s. El total asciende a %s.',
                $campo,
                $periodo,
                $n,
                1 === $n ? $v['singular'] : $v['plural'],
                self::moneda( $total )
            );

            $mayor = $filas[0] ?? null;
            if ( $mayor && $total > 0 ) {
                $parrafos[] = sprintf(
                    '%s con mayor valor es %s, con %s (%s del total).',
                    ucfirst( ( $v['femenino'] ? 'la ' : 'el ' ) . $v['singular'] ),
                    $mayor['label'] ?? '',
                    self::moneda( (float) $mayor['value'] ),
                    self::porcentaje( (float) $mayor['value'] / $total )
                );
            }
        }

        $parrafos[] = 'Solo se incluyen registros marcados con movimiento en SYSMAN. Fuente: sistema SYSMAN — Gobernación de Nariño.';

        return [ 'titulo' => 'Descripción', 'parrafos' => $parrafos, 'metricas' => [] ];
    }

    /**
     * Sitúa al lector en una sola frase: qué métrica, de quién y cuándo.
     *
     * Las vistas se publican como prosa continua —sin título ni etiqueta de
     * ámbito— así que el alcance tiene que ir dentro del propio texto.
     */
    private static function contexto_prosa( array $ctx, array $opciones ): string {
        $valor = trim( (string) ( $opciones['valor'] ?? '' ) );

        return sprintf(
            '%s de %s en %s',
            mb_strtolower( $opciones['campo_label'] ?? 'el valor' ),
            '' !== $valor ? $valor : 'la entidad',
            self::periodo( $ctx )
        );
    }

    // ─── Análisis cualitativo ────────────────────────────────────

    private static function cualitativo( string $vista, array $ctx, array $datos, array $opciones ): array {
        $v     = self::vocabulario( $vista, $opciones );
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

        $parrafos = [ sprintf(
            'Esta lectura interpreta cómo se reparten las cifras de %s entre %d %s.',
            self::contexto_prosa( $ctx, $opciones ),
            $n,
            1 === $n ? $v['singular'] : $v['plural']
        ) ];
        $primeros = $v['femenino'] ? 'las tres primeras' : 'los tres primeros';

        // Concentración (participación del top 3).
        $top3 = array_sum( array_slice( $valores, 0, 3 ) ) / $total;
        $nombres_top = array_filter( array_map(
            static fn( $f ) => $f['label'] ?? ( $f['nombre'] ?? '' ),
            array_slice( $filas, 0, 3 )
        ) );

        if ( $n >= 3 ) {
            $parrafos[] = $top3 >= self::UMBRAL_CONCENTRACION
                ? sprintf(
                    'El presupuesto está concentrado: %s %s (%s) reúnen el %s del total. Una variación en cualquiera de %s mueve de forma apreciable la cifra global.',
                    $primeros, $v['plural'], implode( ', ', $nombres_top ),
                    self::porcentaje( $top3 ),
                    $v['femenino'] ? 'ellas' : 'ellos'
                )
                : sprintf(
                    'El presupuesto está repartido: %s %s concentran el %s del total, de modo que ningun%s domina la cifra global.',
                    $primeros, $v['plural'], self::porcentaje( $top3 ),
                    $v['femenino'] ? 'a' : 'o'
                );
        }

        // Pareto: cuántos explican el 80 %.
        $acumulado = 0.0;
        $pareto    = 0;
        foreach ( $valores as $val ) {
            $acumulado += $val;
            $pareto++;
            if ( $acumulado / $total >= 0.80 ) {
                break;
            }
        }
        if ( $n > 3 ) {
            $parrafos[] = sprintf(
                '%d de %d %s (el %s) explican el 80%% del valor; el resto aporta de forma marginal.',
                $pareto, $n, $v['plural'], self::porcentaje( $pareto / $n )
            );
        }

        $totales = $datos['totales'] ?? [];

        if ( $v['ingresos'] ) {
            $presupuesto = (float) ( $totales['totalpresupuesto'] ?? 0 );
            if ( $presupuesto > 0 ) {
                $recaudado = (float) ( $totales['recaudosacumulados'] ?? 0 ) / $presupuesto;
                $del_mes   = (float) ( $totales['recaudosmes'] ?? 0 ) / $presupuesto;

                $parrafos[] = sprintf(
                    'Frente al presupuesto definitivo de ingresos se ha recaudado el %s de forma acumulada, del cual el %s corresponde al mes analizado. %s',
                    self::porcentaje( $recaudado ),
                    self::porcentaje( $del_mes ),
                    self::juicio_recaudo( $recaudado )
                );

                $porrecaudar = (float) ( $totales['porrecaudar'] ?? 0 );
                if ( $porrecaudar > 0 ) {
                    $parrafos[] = sprintf(
                        'Queda por recaudar %s (el %s del presupuesto definitivo).',
                        self::moneda( $porrecaudar ),
                        self::porcentaje( $porrecaudar / $presupuesto )
                    );
                }
            }
        } else {
            $apr = (float) ( $totales['apropiacionvigente'] ?? 0 );
            if ( $apr > 0 ) {
                $comp = (float) ( $totales['compromisos'] ?? 0 ) / $apr;
                $obl  = (float) ( $totales['obligacion'] ?? 0 ) / $apr;
                $pag  = (float) ( $totales['pagos'] ?? 0 ) / $apr;

                $parrafos[] = sprintf(
                    'Frente a la apropiación vigente se ha comprometido el %s, obligado el %s y pagado el %s. %s',
                    self::porcentaje( $comp ), self::porcentaje( $obl ), self::porcentaje( $pag ),
                    self::juicio_ejecucion( $comp )
                );

                $brecha = $comp - $pag;
                if ( $brecha > 0.25 ) {
                    $parrafos[] = sprintf(
                        'Hay una brecha del %s entre lo comprometido y lo pagado: recursos ya afectados por contratos que aún no se han desembolsado.',
                        self::porcentaje( $brecha )
                    );
                }
            }
        }

        // Dispersión.
        $media = $total / $n;
        $cv    = self::coeficiente_variacion( $valores, $media );
        if ( $cv > 1.5 && $n >= 5 ) {
            $parrafos[] = sprintf(
                'Los valores son muy desiguales entre sí (coeficiente de variación %.2f): conviven %s de magnitud muy distinta, por lo que el promedio no representa bien al conjunto.',
                $cv, $v['plural']
            );
        }

        return [ 'titulo' => 'Análisis cualitativo', 'parrafos' => $parrafos, 'metricas' => [] ];
    }

    private static function juicio_ejecucion( float $comprometido ): string {
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

    private static function juicio_recaudo( float $recaudado ): string {
        if ( $recaudado >= 0.95 ) {
            return 'El recaudo prácticamente alcanza lo presupuestado.';
        }
        if ( $recaudado >= 0.70 ) {
            return 'El recaudo avanza a buen ritmo frente a lo presupuestado.';
        }
        if ( $recaudado >= 0.40 ) {
            return 'El recaudo va a media marcha frente a lo presupuestado.';
        }
        return 'El recaudo está rezagado frente a lo presupuestado.';
    }

    // ─── Análisis cuantitativo ───────────────────────────────────

    /**
     * Traduce el coeficiente de variación a una palabra que se entienda sin
     * saber estadística.
     */
    private static function juicio_dispersion( float $cv ): string {
        if ( $cv >= 1.0 ) {
            return 'muy desigual';
        }
        if ( $cv >= 0.5 ) {
            return 'moderada';
        }
        return 'baja';
    }

    private static function cuantitativo( string $vista, array $ctx, array $datos, array $opciones ): array {
        $v     = self::vocabulario( $vista, $opciones );
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

        $total   = array_sum( $valores );
        $media   = $total / $n;
        $mediana = self::mediana( $valores );

        $metricas = [
            [ 'label' => 'Registros', 'valor' => number_format_i18n( $n ), 'crudo' => $n ],
            [ 'label' => 'Total', 'valor' => self::moneda( $total ), 'crudo' => $total ],
            [ 'label' => 'Promedio', 'valor' => self::moneda( $media ), 'crudo' => $media ],
            [ 'label' => 'Mediana', 'valor' => self::moneda( $mediana ), 'crudo' => $mediana ],
            [ 'label' => 'Máximo', 'valor' => self::moneda( end( $valores ) ), 'crudo' => end( $valores ) ],
            [ 'label' => 'Mínimo', 'valor' => self::moneda( $valores[0] ), 'crudo' => $valores[0] ],
        ];

        $desv = $n > 1 ? self::desviacion( $valores, $media ) : null;
        $cv   = ( null !== $desv && $media > 0 ) ? $desv / $media : null;

        if ( null !== $desv ) {
            $metricas[] = [ 'label' => 'Desviación estándar', 'valor' => self::moneda( $desv ), 'crudo' => $desv ];
            if ( null !== $cv ) {
                $metricas[] = [ 'label' => 'Coef. de variación', 'valor' => number_format_i18n( $cv, 2 ), 'crudo' => $cv ];
            }
        }

        $totales = $datos['totales'] ?? [];
        // Los mismos porcentajes viajan como métrica (para quien consuma el
        // REST) y como texto, porque la vista se publica en prosa continua.
        $ratios = [];

        if ( $v['ingresos'] ) {
            $presupuesto = (float) ( $totales['totalpresupuesto'] ?? 0 );
            if ( $presupuesto > 0 ) {
                foreach ( [
                    'recaudosacumulados' => '% Recaudado',
                    'recaudosmes'        => '% Recaudado en el mes',
                    'porrecaudar'        => '% Por recaudar',
                ] as $campo => $label ) {
                    $fraccion = (float) ( $totales[ $campo ] ?? 0 ) / $presupuesto;
                    $metricas[] = [
                        'label' => $label,
                        'valor' => self::porcentaje( $fraccion ),
                        'crudo' => $fraccion,
                    ];
                    $ratios[ $campo ] = self::porcentaje( $fraccion );
                }
            }
        } else {
            $apr = (float) ( $totales['apropiacionvigente'] ?? 0 );
            if ( $apr > 0 ) {
                foreach ( [
                    'compromisos' => '% Comprometido',
                    'obligacion'  => '% Obligado',
                    'pagos'       => '% Pagado',
                ] as $campo => $label ) {
                    $fraccion = (float) ( $totales[ $campo ] ?? 0 ) / $apr;
                    $metricas[] = [
                        'label' => $label,
                        'valor' => self::porcentaje( $fraccion ),
                        'crudo' => $fraccion,
                    ];
                    $ratios[ $campo ] = self::porcentaje( $fraccion );
                }
            }
        }

        $plural = 1 === $n ? $v['singular'] : $v['plural'];

        $parrafos = [ sprintf(
            'Estas son las estadísticas de %s. La mediana (%s) frente al promedio (%s) indica una distribución %s.',
            self::contexto_prosa( $ctx, $opciones ),
            self::moneda( $mediana ),
            self::moneda( $media ),
            $mediana < $media * 0.6 ? 'muy sesgada hacia unos pocos valores altos' : 'relativamente equilibrada'
        ) ];

        $parrafos[] = sprintf(
            'En conjunto son %s %s que suman %s, con un promedio de %s y una mediana de %s. El valor más alto llega a %s y el más bajo a %s.',
            number_format_i18n( $n ),
            $plural,
            self::moneda( $total ),
            self::moneda( $media ),
            self::moneda( $mediana ),
            self::moneda( end( $valores ) ),
            self::moneda( $valores[0] )
        );

        if ( null !== $desv ) {
            $parrafos[] = null !== $cv
                ? sprintf(
                    'La desviación estándar es de %s y el coeficiente de variación, de %s, lo que refleja una dispersión %s entre %s.',
                    self::moneda( $desv ),
                    number_format_i18n( $cv, 2 ),
                    self::juicio_dispersion( $cv ),
                    $plural
                )
                : sprintf( 'La desviación estándar es de %s.', self::moneda( $desv ) );
        }

        if ( $v['ingresos'] && isset( $ratios['recaudosacumulados'] ) ) {
            $parrafos[] = sprintf(
                'Del presupuesto total se ha recaudado el %s —el %s durante el mes— y queda por recaudar el %s.',
                $ratios['recaudosacumulados'],
                $ratios['recaudosmes'],
                $ratios['porrecaudar']
            );
        } elseif ( isset( $ratios['compromisos'] ) ) {
            $parrafos[] = sprintf(
                'Del total apropiado se ha comprometido el %s, obligado el %s y pagado el %s.',
                $ratios['compromisos'],
                $ratios['obligacion'],
                $ratios['pagos']
            );
        }

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
