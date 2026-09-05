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

        // La vista de avance analiza porcentajes de ejecución, no importes, así
        // que tiene su propia redacción: hablar de "total" o de "concentración"
        // no significa nada sobre una serie de porcentajes.
        if ( 'avance' === $vista ) {
            return match ( $tipo ) {
                'cualitativo'  => self::avance_cualitativo( $ctx, $datos, $opciones ),
                'cuantitativo' => self::avance_cuantitativo( $ctx, $datos, $opciones ),
                default        => self::avance_descripcion( $ctx, $datos, $opciones ),
            };
        }

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

    // ─── Redacción ───────────────────────────────────────────────

    /** Entidad que encabeza la redacción; ajustable con un filtro. */
    private static function entidad(): string {
        return (string) apply_filters( 'sysman_suite_entidad', 'la Gobernación de Nariño' );
    }

    /** Conectores que van en minúscula dentro de un nombre propio. */
    private const CONECTORES = [
        'de', 'del', 'la', 'las', 'el', 'los', 'y', 'e', 'en', 'para',
        'por', 'con', 'a', 'al', 'un', 'una', 'sin', 'sobre',
    ];

    /** Siglas del sector público que deben seguir en mayúscula sostenida. */
    private const SIGLAS = [
        'SGP', 'SGR', 'SGSSS', 'ICBF', 'IDSN', 'SENA', 'DIAN', 'ESE', 'EPS',
        'IPS', 'TIC', 'UAE', 'CDP', 'IVA', 'POAI', 'PAE', 'UMATA', 'ESAP',
        'DPS', 'INVIAS', 'CAR', 'CRC', 'ANI', 'ANLA', 'IGAC', 'ART', 'OCAD',
        'BPIN', 'CTEI', 'SSF', 'CSF', 'FONPET', 'IDEAM', 'DANE',
    ];

    /**
     * SYSMAN entrega los nombres en mayúscula sostenida ("SECRETARIA DE
     * EDUCACION"), que en un párrafo se lee como un grito. Esto los pasa a
     * capitalización normal respetando conectores y siglas.
     *
     * No inventa tildes: la fuente no las trae y adivinarlas sería peor.
     */
    public static function nombre_legible( string $nombre ): string {
        $nombre = trim( $nombre );
        if ( '' === $nombre || preg_match( '/\p{Ll}/u', $nombre ) ) {
            return $nombre;   // Ya viene en mayúscula y minúscula: no se toca.
        }

        $piezas   = preg_split( '/([\s\-\/·,]+)/u', $nombre, -1, PREG_SPLIT_DELIM_CAPTURE );
        $primera  = true;
        $salida   = '';

        foreach ( $piezas as $pieza ) {
            if ( '' === $pieza || preg_match( '/^[\s\-\/·,]+$/u', $pieza ) ) {
                $salida .= $pieza;
                continue;
            }

            $limpia    = preg_replace( '/[^A-ZÁÉÍÓÚÑ]/u', '', mb_strtoupper( $pieza ) );
            $conector  = in_array( mb_strtolower( $pieza ), self::CONECTORES, true );
            $capitaliza = static fn( string $t ): string =>
                mb_strtoupper( mb_substr( $t, 0, 1 ) ) . mb_strtolower( mb_substr( $t, 1 ) );

            if ( $conector ) {
                // Va antes que la regla de siglas: "DE" y "Y" también son cortas.
                $palabra = $primera ? $capitaliza( $pieza ) : mb_strtolower( $pieza );
            } elseif ( in_array( mb_strtoupper( $pieza ), self::SIGLAS, true )
                || mb_strlen( $limpia ) <= 3
                || ! preg_match( '/[AEIOUÁÉÍÓÚ]/u', $limpia ) ) {
                // Siglas, números y abreviaturas se dejan como vienen.
                $palabra = $pieza;
            } else {
                $palabra = $capitaliza( $pieza );
            }

            $salida  .= $palabra;
            $primera  = false;
        }

        return (string) apply_filters( 'sysman_suite_nombre_legible', $salida, $nombre );
    }

    /** Recorta un nombre largo sin partir palabras (los rubros son enormes). */
    private static function recortar( string $texto, int $max = 90 ): string {
        $texto = trim( $texto );
        if ( mb_strlen( $texto ) <= $max ) {
            return $texto;
        }
        $corte = mb_substr( $texto, 0, $max );
        $ultimo = mb_strrpos( $corte, ' ' );
        return rtrim( false !== $ultimo ? mb_substr( $corte, 0, $ultimo ) : $corte, " ,;:.-" ) . '…';
    }

    /** Etiqueta legible de la fila con mayor valor (dimensión o rubro). */
    private static function nombre_fila( array $fila ): string {
        $etiqueta = (string) ( $fila['label'] ?? ( $fila['nombre'] ?? '' ) );
        return self::recortar( self::nombre_legible( $etiqueta ) );
    }

    /** "A, B y C": enumeración con la conjunción final que espera el lector. */
    private static function lista_y( array $items ): string {
        $items = array_values( array_filter( $items ) );
        $n     = count( $items );

        if ( $n <= 1 ) {
            return $items[0] ?? '';
        }
        $ultimo = array_pop( $items );

        // Si algún nombre ya lleva "y" dentro ("Infraestructura y Minas"), la
        // conjunción final confunde: se separa con punto y coma.
        $ambiguo = (bool) count( array_filter(
            array_merge( $items, [ $ultimo ] ),
            static fn( $t ) => (bool) preg_match( '/\sy\s/u', $t )
        ) );

        return $ambiguo
            ? implode( '; ', array_merge( $items, [ $ultimo ] ) )
            : implode( ', ', $items ) . ' y ' . $ultimo;
    }

    /** Une fragmentos en un solo párrafo y lo cierra con punto. */
    private static function parrafo( array $partes ): string {
        $texto = trim( implode( '', array_filter( $partes ) ) );
        return '' === $texto ? '' : rtrim( $texto, ' ,;' ) . '.';
    }

    // ─── Descripción ─────────────────────────────────────────────

    private static function descripcion( string $vista, array $ctx, array $datos, array $opciones ): array {
        $v       = self::vocabulario( $vista, $opciones );
        $campo   = mb_strtolower( $opciones['campo_label'] ?? 'valor' );
        $periodo = self::periodo( $ctx );
        $filas   = $datos['filas'] ?? [];
        $total   = self::sumar( $filas );
        $n       = count( $filas );
        $valor   = self::nombre_legible( (string) ( $opciones['valor'] ?? '' ) );

        if ( 0 === $n ) {
            $informe = $v['ingresos'] ? 'Ejecución de Ingresos' : 'Plan Presupuestal y Ejecución de Gastos';
            return [
                'titulo'   => 'Descripción',
                'parrafos' => [ sprintf(
                    'No hay registros de %s para %s; verifique que el informe de %s esté importado para ese periodo.',
                    $campo, $periodo, $informe
                ) ],
                'metricas' => [],
            ];
        }

        $plural = 1 === $n ? $v['singular'] : $v['plural'];
        $mayor  = $filas[0] ?? null;

        $partes = [ sprintf(
            '%s registra a %s un total de %s en %s%s, distribuido entre %s %s y considerando únicamente los registros con movimiento reportados en el sistema SYSMAN',
            ucfirst( self::entidad() ),
            $periodo,
            self::moneda( $total ),
            $campo,
            '' !== $valor ? ' de ' . $valor : '',
            number_format_i18n( $n ),
            $plural
        ) ];

        if ( $mayor && $total > 0 ) {
            $partes[] = sprintf(
                '; la mayor asignación corresponde a %s, con %s que equivalen al %s del total',
                self::nombre_fila( $mayor ),
                self::moneda( (float) ( $mayor['value'] ?? 0 ) ),
                self::porcentaje( (float) ( $mayor['value'] ?? 0 ) / $total )
            );
        }

        return [ 'titulo' => 'Descripción', 'parrafos' => [ self::parrafo( $partes ) ], 'metricas' => [] ];
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

        $campo   = mb_strtolower( $opciones['campo_label'] ?? 'valor' );
        $periodo = self::periodo( $ctx );
        $valor   = self::nombre_legible( (string) ( $opciones['valor'] ?? '' ) );
        $ambito  = '' !== $valor ? $valor : self::entidad();
        $plural  = 1 === $n ? $v['singular'] : $v['plural'];

        $valores = array_map( static fn( $f ) => (float) $f['value'], $filas );
        rsort( $valores );

        $top3        = array_sum( array_slice( $valores, 0, 3 ) ) / $total;
        $concentrado = $n >= 3 && $top3 >= self::UMBRAL_CONCENTRACION;
        $nombres_top = array_filter( array_map(
            static fn( $f ) => self::nombre_fila( $f ),
            array_slice( $filas, 0, 3 )
        ) );

        $partes = [ sprintf(
            'A %s, %s de %s se %s',
            $periodo,
            'las cifras de ' . $campo,
            $ambito,
            $concentrado
                ? 'concentran en ' . ( $v['femenino'] ? 'unas pocas ' : 'unos pocos ' ) . $plural
                : 'reparten entre ' . number_format_i18n( $n ) . ' ' . $plural
        ) ];

        if ( $n >= 3 ) {
            $partes[] = sprintf(
                ': %s —%s— %s el %s del total',
                $v['femenino'] ? 'las tres primeras' : 'los tres primeros',
                self::lista_y( $nombres_top ),
                $concentrado ? 'reúnen' : 'concentran',
                self::porcentaje( $top3 )
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
            $partes[] = sprintf(
                ' y %s de %s (el %s) explican el 80%% del valor, de modo que %s',
                number_format_i18n( $pareto ),
                number_format_i18n( $n ),
                self::porcentaje( $pareto / $n ),
                $concentrado
                    ? 'una variación en cualquiera de ' . ( $v['femenino'] ? 'ellas' : 'ellos' ) . ' mueve de forma apreciable la cifra global'
                    : 'ningun' . ( $v['femenino'] ? 'a' : 'o' ) . ' domina por sí sol' . ( $v['femenino'] ? 'a' : 'o' ) . ' la cifra global'
            );
        }

        $totales = $datos['totales'] ?? [];

        if ( $v['ingresos'] ) {
            $presupuesto = (float) ( $totales['totalpresupuesto'] ?? 0 );
            if ( $presupuesto > 0 ) {
                $recaudado = (float) ( $totales['recaudosacumulados'] ?? 0 ) / $presupuesto;
                $del_mes   = (float) ( $totales['recaudosmes'] ?? 0 ) / $presupuesto;

                $partes[] = sprintf(
                    '; frente al presupuesto definitivo se ha recaudado el %s de forma acumulada, del cual el %s corresponde al mes analizado, %s',
                    self::porcentaje( $recaudado ),
                    self::porcentaje( $del_mes ),
                    self::juicio_recaudo( $recaudado )
                );

                $porrecaudar = (float) ( $totales['porrecaudar'] ?? 0 );
                if ( $porrecaudar > 0 ) {
                    $partes[] = sprintf(
                        ', y quedan por recaudar %s (el %s del presupuesto definitivo)',
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

                $partes[] = sprintf(
                    '; frente a la apropiación vigente se ha comprometido el %s, obligado el %s y pagado el %s, %s',
                    self::porcentaje( $comp ),
                    self::porcentaje( $obl ),
                    self::porcentaje( $pag ),
                    self::juicio_ejecucion( $comp )
                );

                $brecha = $comp - $pag;
                if ( $brecha > 0.25 ) {
                    $partes[] = sprintf(
                        ', con una brecha del %s entre lo comprometido y lo pagado: recursos ya afectados por contratos que aún no se han desembolsado',
                        self::porcentaje( $brecha )
                    );
                }
            }
        }

        // Dispersión.
        $media = $total / $n;
        $cv    = self::coeficiente_variacion( $valores, $media );
        if ( $cv > 1.5 && $n >= 5 ) {
            $partes[] = sprintf(
                '; los valores son además muy desiguales entre sí (coeficiente de variación %s), por lo que el promedio no representa bien al conjunto',
                number_format_i18n( $cv, 2 )
            );
        }

        return [ 'titulo' => 'Análisis cualitativo', 'parrafos' => [ self::parrafo( $partes ) ], 'metricas' => [] ];
    }

    /** Fragmento que califica el nivel de compromiso, para encadenar en la frase. */
    private static function juicio_ejecucion( float $comprometido ): string {
        if ( $comprometido >= 0.90 ) {
            return 'un nivel de compromiso alto en el que la mayor parte del presupuesto ya está afectada';
        }
        if ( $comprometido >= 0.60 ) {
            return 'un nivel de compromiso intermedio';
        }
        if ( $comprometido >= 0.30 ) {
            return 'un nivel de compromiso bajo frente a la apropiación disponible';
        }
        return 'un nivel de compromiso muy bajo que deja una porción amplia del presupuesto sin afectar';
    }

    /** Fragmento que califica el avance del recaudo. */
    private static function juicio_recaudo( float $recaudado ): string {
        if ( $recaudado >= 0.95 ) {
            return 'de modo que el recaudo prácticamente alcanza lo presupuestado';
        }
        if ( $recaudado >= 0.70 ) {
            return 'un ritmo de recaudo favorable frente a lo presupuestado';
        }
        if ( $recaudado >= 0.40 ) {
            return 'un recaudo que avanza a media marcha frente a lo presupuestado';
        }
        return 'un recaudo rezagado frente a lo presupuestado';
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
        $maximo  = end( $valores );
        $minimo  = $valores[0];

        $metricas = [
            [ 'label' => 'Registros', 'valor' => number_format_i18n( $n ), 'crudo' => $n ],
            [ 'label' => 'Total', 'valor' => self::moneda( $total ), 'crudo' => $total ],
            [ 'label' => 'Promedio', 'valor' => self::moneda( $media ), 'crudo' => $media ],
            [ 'label' => 'Mediana', 'valor' => self::moneda( $mediana ), 'crudo' => $mediana ],
            [ 'label' => 'Máximo', 'valor' => self::moneda( $maximo ), 'crudo' => $maximo ],
            [ 'label' => 'Mínimo', 'valor' => self::moneda( $minimo ), 'crudo' => $minimo ],
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
                ] as $c => $label ) {
                    $fraccion   = (float) ( $totales[ $c ] ?? 0 ) / $presupuesto;
                    $metricas[] = [ 'label' => $label, 'valor' => self::porcentaje( $fraccion ), 'crudo' => $fraccion ];
                    $ratios[ $c ] = self::porcentaje( $fraccion );
                }
            }
        } else {
            $apr = (float) ( $totales['apropiacionvigente'] ?? 0 );
            if ( $apr > 0 ) {
                foreach ( [
                    'compromisos' => '% Comprometido',
                    'obligacion'  => '% Obligado',
                    'pagos'       => '% Pagado',
                ] as $c => $label ) {
                    $fraccion   = (float) ( $totales[ $c ] ?? 0 ) / $apr;
                    $metricas[] = [ 'label' => $label, 'valor' => self::porcentaje( $fraccion ), 'crudo' => $fraccion ];
                    $ratios[ $c ] = self::porcentaje( $fraccion );
                }
            }
        }

        $campo   = mb_strtolower( $opciones['campo_label'] ?? 'valor' );
        $valor   = self::nombre_legible( (string) ( $opciones['valor'] ?? '' ) );
        $ambito  = '' !== $valor ? $valor : self::entidad();
        $plural  = 1 === $n ? $v['singular'] : $v['plural'];

        $partes = [ sprintf(
            'En términos estadísticos, las cifras de %s de %s a %s suman %s entre %s %s, con un promedio de %s y una mediana de %s —una distribución %s—, un máximo de %s y un mínimo de %s',
            $campo,
            $ambito,
            self::periodo( $ctx ),
            self::moneda( $total ),
            number_format_i18n( $n ),
            $plural,
            self::moneda( $media ),
            self::moneda( $mediana ),
            $mediana < $media * 0.6 ? 'muy sesgada hacia unos pocos valores altos' : 'relativamente equilibrada',
            self::moneda( $maximo ),
            self::moneda( $minimo )
        ) ];

        if ( null !== $cv ) {
            $partes[] = sprintf(
                '; la desviación estándar es de %s y el coeficiente de variación, de %s, lo que refleja una dispersión %s',
                self::moneda( $desv ),
                number_format_i18n( $cv, 2 ),
                self::juicio_dispersion( $cv )
            );
        } elseif ( null !== $desv ) {
            $partes[] = sprintf( '; la desviación estándar es de %s', self::moneda( $desv ) );
        }

        if ( $v['ingresos'] && isset( $ratios['recaudosacumulados'] ) ) {
            $partes[] = sprintf(
                '; del presupuesto total se ha recaudado el %s —el %s durante el mes— y queda por recaudar el %s',
                $ratios['recaudosacumulados'],
                $ratios['recaudosmes'],
                $ratios['porrecaudar']
            );
        } elseif ( isset( $ratios['compromisos'] ) ) {
            $partes[] = sprintf(
                '; del total apropiado se ha comprometido el %s, obligado el %s y pagado el %s',
                $ratios['compromisos'],
                $ratios['obligacion'],
                $ratios['pagos']
            );
        }

        return [ 'titulo' => 'Análisis cuantitativo', 'parrafos' => [ self::parrafo( $partes ) ], 'metricas' => $metricas ];
    }
    // ─── Avance de ejecución (porcentajes) ───────────────────────

    /** Tramos en los que se agrupa el avance, para leerlo de un vistazo. */
    private const TRAMOS = [
        [ 0.75, 'por encima del 75%' ],
        [ 0.50, 'entre el 50% y el 75%' ],
        [ 0.25, 'entre el 25% y el 50%' ],
        [ 0.00, 'por debajo del 25%' ],
    ];

    /**
     * Cifras del avance: el ponderado (suma sobre suma) manda, porque promediar
     * porcentajes daría el mismo peso a una dependencia de mil millones que a
     * una de un millón.
     */
    private static function cifras_avance( array $filas ): array {
        $con_base = array_values( array_filter(
            $filas,
            static fn( $f ) => (float) ( $f['base'] ?? 0 ) > 0 && null !== ( $f['porcentaje'] ?? null )
        ) );

        $n = count( $con_base );
        if ( 0 === $n ) {
            return [ 'n' => 0 ];
        }

        $base      = array_sum( array_map( static fn( $f ) => (float) $f['base'], $con_base ) );
        $ejecutado = array_sum( array_map( static fn( $f ) => (float) $f['ejecutado'], $con_base ) );
        $tasas     = array_map( static fn( $f ) => (float) $f['porcentaje'], $con_base );

        // Orden por tasa para mediana, extremos y tramos.
        $ordenadas = $tasas;
        sort( $ordenadas );

        $por_tasa = $con_base;
        usort( $por_tasa, static fn( $a, $b ) => $b['porcentaje'] <=> $a['porcentaje'] );

        $ponderado = $base > 0 ? $ejecutado / $base : 0.0;
        $media     = array_sum( $tasas ) / $n;

        $tramos = [];
        foreach ( self::TRAMOS as [ $piso, $etiqueta ] ) {
            $tramos[ $etiqueta ] = 0;
        }
        foreach ( $tasas as $t ) {
            foreach ( self::TRAMOS as [ $piso, $etiqueta ] ) {
                if ( $t >= $piso ) {
                    $tramos[ $etiqueta ]++;
                    break;
                }
            }
        }

        return [
            'n'          => $n,
            'base'       => $base,
            'ejecutado'  => $ejecutado,
            'pendiente'  => $base - $ejecutado,
            'ponderado'  => $ponderado,
            'media'      => $media,
            'mediana'    => self::mediana( $ordenadas ),
            'maximo'     => $por_tasa[0],
            'minimo'     => $por_tasa[ $n - 1 ],
            'desviacion' => self::desviacion( $ordenadas, $media ),
            'sobre'      => count( array_filter( $tasas, static fn( $t ) => $t >= $ponderado ) ),
            'rezagadas'  => count( array_filter( $tasas, static fn( $t ) => $t < 0.30 ) ),
            'sin_iniciar' => count( array_filter( $tasas, static fn( $t ) => $t <= 0.0 ) ),
            'tramos'     => $tramos,
        ];
    }

    /** Vocabulario del avance: el ámbito manda sobre la vista. */
    private static function vocabulario_avance( array $opciones ): array {
        $vista = '' !== trim( (string) ( $opciones['valor'] ?? '' ) ) ? 'detalle' : 'dimensiones';
        return self::vocabulario( $vista, $opciones );
    }

    private static function sin_avance( string $titulo ): array {
        return [
            'titulo'   => $titulo,
            'parrafos' => [ 'No hay apropiación registrada en este periodo, así que no se puede calcular el porcentaje de ejecución.' ],
            'metricas' => [],
        ];
    }

    private static function avance_descripcion( array $ctx, array $datos, array $opciones ): array {
        $v = self::vocabulario_avance( $opciones );
        $c = self::cifras_avance( $datos['filas'] ?? [] );

        if ( 0 === $c['n'] ) {
            return self::sin_avance( 'Descripción' );
        }

        $valor  = self::nombre_legible( (string) ( $opciones['valor'] ?? '' ) );
        $ambito = '' !== $valor ? $valor : self::entidad();
        $plural = 1 === $c['n'] ? $v['singular'] : $v['plural'];

        $partes = [ sprintf(
            'A %s %s ha %s el %s de su %s: %s de %s, repartidos entre %s %s',
            self::periodo( $ctx ),
            $ambito,
            mb_strtolower( $opciones['ejecutado_label'] ?? 'comprometido' ),
            self::porcentaje( $c['ponderado'] ),
            mb_strtolower( $opciones['base_label'] ?? 'apropiación vigente' ),
            self::moneda( $c['ejecutado'] ),
            self::moneda( $c['base'] ),
            number_format_i18n( $c['n'] ),
            $plural
        ) ];

        if ( $c['n'] > 1 ) {
            $partes[] = sprintf(
                '; %s de mayor avance es %s, con el %s, y %s de menor, %s, con el %s',
                $v['femenino'] ? 'la' : 'el',
                self::nombre_fila( $c['maximo'] ),
                self::porcentaje( (float) $c['maximo']['porcentaje'] ),
                $v['femenino'] ? 'la' : 'el',
                self::nombre_fila( $c['minimo'] ),
                self::porcentaje( (float) $c['minimo']['porcentaje'] )
            );
        }

        return [ 'titulo' => 'Descripción', 'parrafos' => [ self::parrafo( $partes ) ], 'metricas' => [] ];
    }

    private static function avance_cualitativo( array $ctx, array $datos, array $opciones ): array {
        $v = self::vocabulario_avance( $opciones );
        $c = self::cifras_avance( $datos['filas'] ?? [] );

        if ( 0 === $c['n'] ) {
            return self::sin_avance( 'Análisis cualitativo' );
        }

        $ingresos = ! empty( $v['ingresos'] );
        $plural   = 1 === $c['n'] ? $v['singular'] : $v['plural'];
        $brecha   = (float) $c['maximo']['porcentaje'] - (float) $c['minimo']['porcentaje'];

        $partes = [ sprintf(
            'El avance %s: %s de %s %s superan el promedio ponderado (%s) y %s se %s',
            $c['desviacion'] >= 0.20
                ? ( $v['femenino'] ? 'es desigual entre unas y otras' : 'es desigual entre unos y otros' )
                : 'es parejo',
            number_format_i18n( $c['sobre'] ),
            number_format_i18n( $c['n'] ),
            $plural,
            self::porcentaje( $c['ponderado'] ),
            1 === $c['n'] - $c['sobre'] ? 'una' : number_format_i18n( $c['n'] - $c['sobre'] ),
            1 === $c['n'] - $c['sobre'] ? 'queda por debajo' : 'quedan por debajo'
        ) ];

        if ( $c['n'] > 1 ) {
            $partes[] = sprintf(
                '; entre %s de mayor y %s de menor avance hay %s puntos de diferencia',
                $v['femenino'] ? 'la' : 'el',
                $v['femenino'] ? 'la' : 'el',
                number_format_i18n( $brecha * 100, 1 )
            );
        }

        if ( $c['rezagadas'] > 0 ) {
            $partes[] = sprintf(
                ', y %s %s no %s del 30%%%s',
                number_format_i18n( $c['rezagadas'] ),
                1 === $c['rezagadas'] ? $v['singular'] : $v['plural'],
                1 === $c['rezagadas'] ? 'pasa' : 'pasan',
                $c['sin_iniciar'] > 0
                    ? sprintf(
                        ' (%s de %s sin %s alguno)',
                        number_format_i18n( $c['sin_iniciar'] ),
                        $v['femenino'] ? 'ellas' : 'ellos',
                        $ingresos ? 'recaudo' : 'compromiso'
                    )
                    : ''
            );
        }

        $partes[] = sprintf(
            '; queda %s sin %s, %s',
            self::moneda( $c['pendiente'] ),
            $ingresos ? 'recaudar' : 'comprometer',
            $ingresos ? self::juicio_recaudo( $c['ponderado'] ) : self::juicio_ejecucion( $c['ponderado'] )
        );

        return [ 'titulo' => 'Análisis cualitativo', 'parrafos' => [ self::parrafo( $partes ) ], 'metricas' => [] ];
    }

    private static function avance_cuantitativo( array $ctx, array $datos, array $opciones ): array {
        $v = self::vocabulario_avance( $opciones );
        $c = self::cifras_avance( $datos['filas'] ?? [] );

        if ( 0 === $c['n'] ) {
            return self::sin_avance( 'Análisis cuantitativo' );
        }

        $plural = 1 === $c['n'] ? $v['singular'] : $v['plural'];

        $metricas = [
            [ 'label' => 'Registros', 'valor' => number_format_i18n( $c['n'] ), 'crudo' => $c['n'] ],
            [ 'label' => '% Ponderado', 'valor' => self::porcentaje( $c['ponderado'] ), 'crudo' => $c['ponderado'] ],
            [ 'label' => '% Promedio simple', 'valor' => self::porcentaje( $c['media'] ), 'crudo' => $c['media'] ],
            [ 'label' => '% Mediana', 'valor' => self::porcentaje( $c['mediana'] ), 'crudo' => $c['mediana'] ],
            [ 'label' => '% Máximo', 'valor' => self::porcentaje( (float) $c['maximo']['porcentaje'] ), 'crudo' => (float) $c['maximo']['porcentaje'] ],
            [ 'label' => '% Mínimo', 'valor' => self::porcentaje( (float) $c['minimo']['porcentaje'] ), 'crudo' => (float) $c['minimo']['porcentaje'] ],
            [ 'label' => 'Desviación (puntos)', 'valor' => number_format_i18n( $c['desviacion'] * 100, 1 ), 'crudo' => $c['desviacion'] ],
            [ 'label' => 'Base', 'valor' => self::moneda( $c['base'] ), 'crudo' => $c['base'] ],
            [ 'label' => 'Ejecutado', 'valor' => self::moneda( $c['ejecutado'] ), 'crudo' => $c['ejecutado'] ],
            [ 'label' => 'Pendiente', 'valor' => self::moneda( $c['pendiente'] ), 'crudo' => $c['pendiente'] ],
        ];
        foreach ( $c['tramos'] as $etiqueta => $cuantas ) {
            $metricas[] = [ 'label' => ucfirst( $etiqueta ), 'valor' => number_format_i18n( $cuantas ), 'crudo' => $cuantas ];
        }

        $partes = [ sprintf(
            'En términos estadísticos, el avance ponderado es del %s y el promedio simple, del %s —la diferencia entre ambos indica que %s—, con una mediana del %s, un máximo del %s y un mínimo del %s',
            self::porcentaje( $c['ponderado'] ),
            self::porcentaje( $c['media'] ),
            $c['ponderado'] > $c['media']
                ? 'las de mayor presupuesto avanzan más rápido que el resto'
                : 'el peso lo llevan las de menor presupuesto',
            self::porcentaje( $c['mediana'] ),
            self::porcentaje( (float) $c['maximo']['porcentaje'] ),
            self::porcentaje( (float) $c['minimo']['porcentaje'] )
        ) ];

        $partes[] = sprintf(
            '; la desviación estándar es de %s puntos porcentuales',
            number_format_i18n( $c['desviacion'] * 100, 1 )
        );

        $tramos = [];
        foreach ( $c['tramos'] as $etiqueta => $cuantas ) {
            if ( $cuantas > 0 ) {
                $tramos[] = number_format_i18n( $cuantas ) . ' ' . $etiqueta;
            }
        }
        if ( ! empty( $tramos ) ) {
            $partes[] = sprintf( '. Por tramos de avance: %s, sobre %s %s con apropiación registrada',
                self::lista_y( $tramos ),
                number_format_i18n( $c['n'] ),
                $plural
            );
        }

        return [ 'titulo' => 'Análisis cuantitativo', 'parrafos' => [ self::parrafo( $partes ) ], 'metricas' => $metricas ];
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

    /** "septiembre de 2026": el mes va en minúscula porque siempre va dentro de una frase. */
    private static function periodo( array $ctx ): string {
        $mes = (int) ( $ctx['mes'] ?? 0 );
        return $mes > 0
            ? mb_strtolower( Helpers::month_name( $mes ) ) . ' de ' . (int) ( $ctx['anio'] ?? 0 )
            : 'la vigencia ' . (int) ( $ctx['anio'] ?? 0 );
    }
}
