<?php
namespace SysmanSuite\IA;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Cliente mínimo de la API de mensajes de Claude (Anthropic).
 *
 * Usa HTTP directo con wp_remote_post(): el plugin se instala como ZIP sin
 * Composer, y el SDK oficial de PHP necesitaría distribuir su carpeta vendor.
 */
class ClaudeCliente implements Proveedor {

    public const URL     = 'https://api.anthropic.com/v1/messages';
    public const VERSION = '2023-06-01';

    /** Reintento del lado del servidor cuando el clasificador rechaza la solicitud. */
    public const BETA_FALLBACK = 'server-side-fallback-2026-07-01';

    /** Incluye el pensamiento adaptativo, que cuenta dentro de max_tokens. */
    public const MAX_TOKENS = 6000;

    private string $clave;
    private string $modelo;
    private string $esfuerzo;

    public function __construct( string $clave, string $modelo, string $esfuerzo = 'medium' ) {
        $this->clave    = $clave;
        $this->modelo   = $modelo;
        $this->esfuerzo = $esfuerzo;
    }

    public function nombre(): string {
        return 'anthropic';
    }

    public function modelo(): string {
        return $this->modelo;
    }

    /** Haiku no tiene reintento del lado del servidor; Opus y Fable sí. */
    public static function admite_fallback( string $modelo ): bool {
        return 0 === strpos( $modelo, 'claude-opus-' ) || 0 === strpos( $modelo, 'claude-fable-' );
    }

    public function cabeceras(): array {
        $h = [
            'content-type'      => 'application/json',
            'x-api-key'         => $this->clave,
            'anthropic-version' => self::VERSION,
        ];
        if ( self::admite_fallback( $this->modelo ) ) {
            $h['anthropic-beta'] = self::BETA_FALLBACK;
        }
        return $h;
    }

    public function cuerpo( string $sistema, string $usuario ): array {
        $cuerpo = [
            'model'         => $this->modelo,
            'max_tokens'    => self::MAX_TOKENS,
            'system'        => $sistema,
            'messages'      => [
                [ 'role' => 'user', 'content' => $usuario ],
            ],
            // El pensamiento es adaptativo; el esfuerzo regula cuánto piensa.
            'output_config' => [ 'effort' => $this->esfuerzo ],
        ];
        if ( self::admite_fallback( $this->modelo ) ) {
            $cuerpo['fallbacks'] = 'default';
        }
        return $cuerpo;
    }

    public function generar( string $sistema, string $usuario ) {
        $resp = wp_remote_post( self::URL, [
            'timeout' => 90,
            'headers' => $this->cabeceras(),
            'body'    => wp_json_encode( $this->cuerpo( $sistema, $usuario ) ),
        ] );

        if ( is_wp_error( $resp ) ) {
            return $resp;
        }

        $codigo = (int) wp_remote_retrieve_response_code( $resp );
        $json   = json_decode( (string) wp_remote_retrieve_body( $resp ), true );

        if ( $codigo < 200 || $codigo >= 300 ) {
            $msg = is_array( $json ) ? (string) ( $json['error']['message'] ?? '' ) : '';
            return new \WP_Error( 'ia_http', "Claude respondió {$codigo}" . ( '' !== $msg ? ": {$msg}" : '' ) );
        }

        return self::leer_respuesta( is_array( $json ) ? $json : [] );
    }

    /**
     * Texto de la respuesta, o error si fue rechazada o vino incompleta.
     *
     * @return array{texto:string, modelo:string}|\WP_Error
     */
    public static function leer_respuesta( array $json ) {
        $stop = (string) ( $json['stop_reason'] ?? '' );

        // Un rechazo llega como HTTP 200: hay que mirarlo antes del contenido.
        if ( 'refusal' === $stop ) {
            return new \WP_Error( 'ia_rechazo', 'Claude declinó redactar este texto.' );
        }
        if ( 'max_tokens' === $stop ) {
            return new \WP_Error( 'ia_incompleto', 'La respuesta de Claude quedó cortada.' );
        }

        $texto = '';
        foreach ( (array) ( $json['content'] ?? [] ) as $bloque ) {
            if ( 'text' === ( $bloque['type'] ?? '' ) ) {
                $texto .= (string) ( $bloque['text'] ?? '' );
            }
        }

        if ( '' === trim( $texto ) ) {
            return new \WP_Error( 'ia_vacio', 'Claude no devolvió texto.' );
        }

        return [ 'texto' => trim( $texto ), 'modelo' => (string) ( $json['model'] ?? '' ) ];
    }
}
