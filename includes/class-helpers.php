<?php
namespace SysmanSuite;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shared helpers used across modules.
 */
class Helpers {

    public const MESES = [
        1  => 'Enero',
        2  => 'Febrero',
        3  => 'Marzo',
        4  => 'Abril',
        5  => 'Mayo',
        6  => 'Junio',
        7  => 'Julio',
        8  => 'Agosto',
        9  => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre',
    ];

    /**
     * Get the Spanish name for a month number (1-12).
     * Falls back to the raw number when out of range.
     */
    public static function month_name( int $mes ): string {
        return self::MESES[ $mes ] ?? (string) $mes;
    }
}
