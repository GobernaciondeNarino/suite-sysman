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
        'sysman_plan_presupuestal'  => [ 'compania', 'anio', 'mes', 'codigo' ],
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
}
