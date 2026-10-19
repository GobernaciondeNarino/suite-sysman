<?php
namespace SysmanSuite\IA;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Un modelo de lenguaje que redacta texto a partir de instrucciones.
 */
interface Proveedor {

    /** Identificador del proveedor: anthropic | openai. */
    public function nombre(): string;

    public function modelo(): string;

    /**
     * @return array{texto:string, modelo:string}|\WP_Error
     */
    public function generar( string $sistema, string $usuario );
}
