<?php
namespace SysmanSuite;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Ámbito de una importación: qué tablas gestiona el importador, qué identifica
 * un registro dentro de cada una y qué filas hay que borrar antes de insertar.
 *
 * Vive aparte de Database —y sin tocar $wpdb— porque es la pieza que evita
 * duplicar datos: cada importación borra su periodo y vuelve a insertarlo, así
 * que si el ámbito del borrado no coincide con el de la inserción, las filas
 * viejas sobreviven y las cifras salen infladas.
 */
class Import_Scope {

    /**
     * Columnas que identifican un registro del informe de origen. Dos filas con
     * la misma clave en el mismo periodo son, en la práctica, la misma fila
     * importada dos veces.
     */
    public const CLAVES_NATURALES = [
        'sysman_ejecucion_gastos'   => [ 'compania', 'anio', 'mes', 'codigocuenta' ],
        // El plan repite el código en cada tipo de vigencia (actual, reservas,
        // vigencias futuras): sin tipovigencia esas filas legítimas contarían
        // como duplicados.
        'sysman_plan_presupuestal'  => [ 'compania', 'anio', 'mes', 'codigo', 'tipovigencia' ],
        'sysman_ejecucion_ingresos' => [ 'compania', 'anio', 'mes', 'cuenta', 'codigo' ],
        // El auxiliar admite varias líneas por comprobante, así que la clave
        // incluye tercero, documento y valores: dos filas idénticas en todo eso
        // son la misma línea importada dos veces.
        'sysman_auxiliar_cuentas'   => [
            'compania', 'anio', 'mes', 'tipocpte', 'numero', 'rubro',
            'tercero', 'nrodocumento', 'valordebito', 'valorcredito',
            'tipocpteafect', 'cmpteafectado',
        ],
        'sysman_personal_nomina'    => [ 'compania', 'anio', 'numerodcto' ],
    ];

    /** Tablas que gestiona el importador. */
    public static function tablas(): array {
        return array_keys( self::CLAVES_NATURALES );
    }

    /** Columnas que identifican un registro de la tabla dada. */
    public static function clave_natural( string $tabla ): array {
        return self::CLAVES_NATURALES[ $tabla ] ?? [];
    }

    /** Si la tabla guarda el mes. Personal de nómina es anual. */
    public static function tiene_mes( string $tabla ): bool {
        return in_array( 'mes', self::clave_natural( $tabla ), true );
    }

    /**
     * Tablas que guardan un corte por mes. Borrar «un mes» solo tiene sentido
     * en estas: en nómina, que es anual, el mismo borrado se llevaría el año.
     */
    public static function tablas_mensuales(): array {
        return array_values( array_filter( self::tablas(), [ self::class, 'tiene_mes' ] ) );
    }

    /**
     * WHERE + parámetros para borrar un periodo completo de una tabla.
     *
     * `personal_nomina` no tiene columna `mes`, así que siempre se borra por año
     * aunque se pida un mes concreto.
     *
     * @param int $mes 0 = el año entero.
     * @return array{0:string, 1:array}
     */
    public static function scope_borrado( string $tabla, string $compania, int $anio, int $mes = 0 ): array {
        return $mes > 0 && self::tiene_mes( $tabla )
            ? [ 'compania = %s AND anio = %d AND mes = %d', [ $compania, $anio, $mes ] ]
            : [ 'compania = %s AND anio = %d', [ $compania, $anio ] ];
    }

    /** Columnas que pone el importador y no vienen del informe de origen. */
    public const COLUMNAS_DE_CONTROL = [ 'id', 'fecha_importacion' ];

    /**
     * Separación mínima, en segundos, entre dos filas idénticas para tratarlas
     * como de importaciones distintas. Una importación inserta su periodo en
     * lotes seguidos (menos de un segundo cada uno) y otra importación del
     * mismo periodo tiene que volver a consultar la API, que tarda bastante
     * más; por debajo de este margen, dos filas iguales se consideran de la
     * misma importación.
     */
    public const SEGUNDOS_MISMA_IMPORTACION = 5;

    /**
     * Expresión SQL con la huella de una fila: el MD5 de todas sus columnas
     * de datos (todas salvo las de control). Dos filas con la misma huella son
     * idénticas en lo que trae el informe de origen. COALESCE mantiene la
     * posición de cada columna aunque venga NULL. Vacío si no hay columnas.
     *
     * @param string[] $columnas Columnas reales de la tabla.
     */
    public static function huella_sql( array $columnas ): string {
        $datos = array_values( array_filter(
            array_diff( $columnas, self::COLUMNAS_DE_CONTROL ),
            static fn( $c ) => is_string( $c ) && 1 === preg_match( '/^[a-z0-9_]+$/i', $c )
        ) );

        if ( empty( $datos ) ) {
            return '';
        }

        return "MD5(CONCAT_WS('|~|', COALESCE(`" . implode( "`, ''), COALESCE(`", $datos ) . "`, '')))";
    }

    /**
     * Separa, entre filas idénticas, las que sobran de las que se conservan.
     *
     * Las filas idénticas no son siempre duplicación: SYSMAN entrega a veces
     * un comprobante con dos líneas iguales en todo lo que exporta, y esas
     * llegan juntas en la misma importación. Lo que sí sobra es una fila que
     * tiene otra idéntica importada después: viene de una importación anterior
     * del mismo periodo. De cada grupo se conserva la última importación
     * completa, con todas sus repeticiones.
     *
     * @param array<int, array{id:int|string, fecha:string, huella:string}> $filas
     * @return array{sobrantes:int[], repetidas:int} Ids que sobran y cuántas
     *         filas de más llegaron repetidas dentro de una misma importación.
     */
    public static function clasificar_copias( array $filas ): array {
        $grupos = [];
        foreach ( $filas as $f ) {
            $grupos[ (string) $f['huella'] ][] = [ (int) $f['id'], (int) strtotime( (string) $f['fecha'] ) ];
        }

        $sobrantes = [];
        $repetidas = 0;
        foreach ( $grupos as $grupo ) {
            if ( count( $grupo ) < 2 ) {
                continue;
            }

            $ultima    = max( array_column( $grupo, 1 ) );
            $conserva  = 0;
            foreach ( $grupo as [ $id, $momento ] ) {
                if ( $ultima - $momento >= self::SEGUNDOS_MISMA_IMPORTACION ) {
                    $sobrantes[] = $id;
                } else {
                    $conserva++;
                }
            }
            $repetidas += $conserva - 1;
        }

        sort( $sobrantes );
        return [ 'sobrantes' => $sobrantes, 'repetidas' => $repetidas ];
    }
}
