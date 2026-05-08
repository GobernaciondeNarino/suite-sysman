<?php
namespace SysmanSuite\Ejecucion;

if ( ! defined( 'ABSPATH' ) ) exit;

class SysmanClient {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function get_base_url(): string {
        return get_option(
            'sysman_api_base_url',
            'https://narino-gob.sysman.com.co/sysmanApi/autoservicio/v1/informesGobNar'
        );
    }

    public function fetch( array $args = [] ): array|\WP_Error {
        $url = add_query_arg( $args, $this->get_base_url() );

        $response = wp_remote_get( $url, [
            'timeout'   => 300,
            'sslverify' => false,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            return new \WP_Error( 'sysman_http_' . $code, "HTTP $code" );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $body ) ) {
            return new \WP_Error( 'sysman_json', 'JSON inválido' );
        }

        // SYSMAN API envelope: { codigo: 0, mensaje: "OK", cuerpo: [...] }
        if ( isset( $body['codigo'] ) ) {
            if ( (int) $body['codigo'] !== 0 ) {
                $msg = $body['mensaje'] ?? 'Error desconocido';
                return new \WP_Error( 'sysman_api', $msg );
            }
            return $body['cuerpo'] ?? [];
        }

        return $body;
    }
}
