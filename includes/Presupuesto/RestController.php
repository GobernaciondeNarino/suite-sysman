<?php
namespace SysmanSuite\Presupuesto;

use SysmanSuite\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Public REST endpoints for the Presupuesto module.
 *
 * These serve open budget data, so they are readable without authentication
 * but rate limited per IP like the rest of the plugin's public surface.
 */
class RestController {

    private const NS = 'sysman-suite/v1';

    private Repository $repo;

    public function __construct( ?Repository $repo = null ) {
        $this->repo = $repo ?? Repository::instance();
    }

    public function register(): void {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Arguments shared by every endpoint.
     */
    private function args_periodo(): array {
        return [
            'compania' => [ 'default' => '001', 'sanitize_callback' => 'sanitize_text_field' ],
            'anio'     => [ 'default' => 0, 'sanitize_callback' => 'absint' ],
            'mes'      => [ 'default' => 0, 'sanitize_callback' => 'absint' ],
        ];
    }

    public function register_routes(): void {
        register_rest_route( self::NS, '/presupuesto/periodos', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_periodos' ],
            'permission_callback' => fn() => $this->permiso( 'pre_lectura', 120 ),
            'args'                => [ 'compania' => [ 'default' => '001', 'sanitize_callback' => 'sanitize_text_field' ] ],
        ] );

        register_rest_route( self::NS, '/presupuesto/dependencias', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_dependencias' ],
            'permission_callback' => fn() => $this->permiso( 'pre_lectura', 120 ),
            'args'                => $this->args_periodo() + [
                'campo'  => [ 'default' => 'apropiacionvigente', 'sanitize_callback' => 'sanitize_text_field' ],
                'limite' => [ 'default' => 0, 'sanitize_callback' => 'absint' ],
            ],
        ] );

        register_rest_route( self::NS, '/presupuesto/rubros', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_rubros' ],
            'permission_callback' => fn() => $this->permiso( 'pre_lectura', 120 ),
            'args'                => $this->args_periodo() + [
                'dependencia' => [ 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
                'campo'       => [ 'default' => 'apropiacionvigente', 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );

        register_rest_route( self::NS, '/presupuesto/rubro', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_rubro' ],
            'permission_callback' => fn() => $this->permiso( 'pre_detalle', 120 ),
            'args'                => $this->args_periodo() + [
                'codigo' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );

        register_rest_route( self::NS, '/presupuesto/analisis', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_analisis' ],
            'permission_callback' => fn() => $this->permiso( 'pre_analisis', 60 ),
            'args'                => $this->args_periodo() + [
                'vista'       => [ 'default' => 'dependencias', 'sanitize_callback' => 'sanitize_text_field' ],
                'tipo'        => [ 'default' => 'descripcion', 'sanitize_callback' => 'sanitize_text_field' ],
                'campo'       => [ 'default' => 'apropiacionvigente', 'sanitize_callback' => 'sanitize_text_field' ],
                'dependencia' => [ 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );
    }

    /**
     * @return true|\WP_Error
     */
    public function permiso( string $bucket, int $max ) {
        if ( ! Helpers::rate_limit_check( $bucket, $max ) ) {
            return new \WP_Error(
                'rest_rate_limited',
                __( 'Demasiadas solicitudes. Intente de nuevo en un minuto.', 'sysman-suite' ),
                [ 'status' => 429 ]
            );
        }
        return true;
    }

    private function ctx( \WP_REST_Request $r ): array {
        return $this->repo->contexto( [
            'compania' => $r->get_param( 'compania' ),
            'anio'     => $r->get_param( 'anio' ),
            'mes'      => $r->get_param( 'mes' ),
        ] );
    }

    /**
     * Period metadata echoed with every payload so the UI can label itself.
     */
    private function meta( array $ctx, array $extra = [] ): array {
        return array_merge( [
            'compania'   => $ctx['compania'],
            'anio'       => $ctx['anio'],
            'mes'        => $ctx['mes'],
            'mes_nombre' => Helpers::month_name( (int) $ctx['mes'] ),
        ], $extra );
    }

    public function get_periodos( \WP_REST_Request $r ): \WP_REST_Response {
        $compania = $r->get_param( 'compania' );
        return new \WP_REST_Response( [
            'periodos' => array_map(
                static fn( $p ) => $p + [ 'mes_nombre' => Helpers::month_name( $p['mes'] ) ],
                $this->repo->periodos( $compania )
            ),
        ] );
    }

    public function get_dependencias( \WP_REST_Request $r ): \WP_REST_Response {
        $ctx   = $this->ctx( $r );
        $campo = Repository::validar_campo( $r->get_param( 'campo' ) );
        $filas = $this->repo->dependencias( $ctx, $campo, (int) $r->get_param( 'limite' ) );

        return new \WP_REST_Response( [
            'meta'  => $this->meta( $ctx, [
                'campo'        => $campo,
                'campo_label'  => Repository::etiqueta_campo( $campo ),
                'total'        => array_sum( array_column( $filas, 'value' ) ),
            ] ),
            'data'  => $filas,
            'total' => count( $filas ),
        ] );
    }

    public function get_rubros( \WP_REST_Request $r ): \WP_REST_Response {
        $ctx   = $this->ctx( $r );
        $campo = Repository::validar_campo( $r->get_param( 'campo' ) );
        $dep   = (string) $r->get_param( 'dependencia' );
        $filas = $this->repo->rubros( $ctx, $dep, $campo );

        return new \WP_REST_Response( [
            'meta'  => $this->meta( $ctx, [
                'campo'       => $campo,
                'campo_label' => Repository::etiqueta_campo( $campo ),
                'dependencia' => $dep,
            ] ),
            'data'  => $filas,
            'total' => count( $filas ),
        ] );
    }

    public function get_rubro( \WP_REST_Request $r ): \WP_REST_Response {
        $ctx    = $this->ctx( $r );
        $codigo = (string) $r->get_param( 'codigo' );

        if ( '' === $codigo ) {
            return new \WP_REST_Response( [ 'error' => 'Falta el código del rubro' ], 400 );
        }

        return new \WP_REST_Response( [
            'meta' => $this->meta( $ctx, [ 'codigo' => $codigo ] ),
            'data' => $this->repo->rubro( $ctx, $codigo ),
        ] );
    }

    public function get_analisis( \WP_REST_Request $r ): \WP_REST_Response {
        $ctx   = $this->ctx( $r );
        $vista = 'rubros' === $r->get_param( 'vista' ) ? 'rubros' : 'dependencias';
        $tipo  = (string) $r->get_param( 'tipo' );
        $campo = Repository::validar_campo( $r->get_param( 'campo' ) );
        $dep   = (string) $r->get_param( 'dependencia' );

        $filas = 'rubros' === $vista
            ? $this->repo->rubros( $ctx, $dep, $campo )
            : $this->repo->dependencias( $ctx, $campo );

        $analisis = Analysis::generar(
            $tipo,
            $vista,
            $ctx,
            [ 'filas' => $filas, 'totales' => $this->repo->totales( $ctx, $dep ) ],
            [ 'campo' => $campo, 'dependencia' => $dep ]
        );

        return new \WP_REST_Response( [
            'meta' => $this->meta( $ctx, [ 'vista' => $vista, 'tipo' => $tipo, 'campo' => $campo, 'dependencia' => $dep ] ),
            'data' => $analisis,
        ] );
    }
}
