<?php
namespace SysmanSuite\IA;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Cliente mínimo de Chat Completions de OpenAI (ChatGPT), por HTTP directo.
 */
class OpenAICliente implements Proveedor {

    public const URL = 'https://api.openai.com/v1/chat/completions';

    /** Los modelos de razonamiento cuentan el razonamiento dentro de este tope. */
    public const MAX_TOKENS = 6000;

    private string $clave;
    private string $modelo;

    public function __construct( string $clave, string $modelo ) {
        $this->clave  = $clave;
        $this->modelo = $modelo;
    }

    public function nombre(): string {
        return 'openai';
    }

    public function modelo(): string {
        return $this->modelo;
    }

    public function cuerpo( string $sistema, string $usuario ): array {
        return [
            'model'                 => $this->modelo,
            'max_completion_tokens' => self::MAX_TOKENS,
            'messages'              => [
                [ 'role' => 'system', 'content' => $sistema ],
                [ 'role' => 'user', 'content' => $usuario ],
            ],
        ];
    }

    public function generar( string $sistema, string $usuario ) {
        $resp = wp_remote_post( self::URL, [
            'timeout' => 90,
            'headers' => [
                'content-type'  => 'application/json',
                'authorization' => 'Bearer ' . $this->clave,
            ],
            'body'    => wp_json_encode( $this->cuerpo( $sistema, $usuario ) ),
        ] );

        if ( is_wp_error( $resp ) ) {
            return $resp;
        }

        $codigo = (int) wp_remote_retrieve_response_code( $resp );
        $json   = json_decode( (string) wp_remote_retrieve_body( $resp ), true );

        if ( $codigo < 200 || $codigo >= 300 ) {
            $msg = is_array( $json ) ? (string) ( $json['error']['message'] ?? '' ) : '';
            return new \WP_Error( 'ia_http', "OpenAI respondió {$codigo}" . ( '' !== $msg ? ": {$msg}" : '' ) );
        }

        return self::leer_respuesta( is_array( $json ) ? $json : [] );
    }

    /**
     * @return array{texto:string, modelo:string}|\WP_Error
     */
    public static function leer_respuesta( array $json ) {
        $opcion  = $json['choices'][0] ?? [];
        $mensaje = $opcion['message'] ?? [];

        if ( ! empty( $mensaje['refusal'] ) ) {
            return new \WP_Error( 'ia_rechazo', 'OpenAI declinó redactar este texto.' );
        }
        if ( 'length' === ( $opcion['finish_reason'] ?? '' ) ) {
            return new \WP_Error( 'ia_incompleto', 'La respuesta de OpenAI quedó cortada.' );
        }

        $texto = trim( (string) ( $mensaje['content'] ?? '' ) );
        if ( '' === $texto ) {
            return new \WP_Error( 'ia_vacio', 'OpenAI no devolvió texto.' );
        }

        return [ 'texto' => $texto, 'modelo' => (string) ( $json['model'] ?? '' ) ];
    }
}
