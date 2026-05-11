<?php
namespace SysmanSuite\Ejecucion;

if ( ! defined( 'ABSPATH' ) ) exit;

class RestController {

    private Repository $repo;

    public function __construct( ?Repository $repo = null ) {
        $this->repo = $repo ?? Repository::instance();
    }

    public function register(): void {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        $ns = 'gn-sisman/v1';

        register_rest_route( $ns, '/dependencias', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_dependencias' ],
            'permission_callback' => [ $this, 'check_admin' ],
            'args' => [
                'anio' => [ 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ],
                'mes'  => [ 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ],
                'compania' => [ 'required' => false, 'type' => 'string', 'default' => '001', 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );

        register_rest_route( $ns, '/ejecucion/(?P<post_id>\d+)/rubros', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_rubros' ],
            'permission_callback' => '__return_true',
            'args' => [
                'post_id' => [ 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ],
            ],
        ] );

        register_rest_route( $ns, '/ejecucion/(?P<post_id>\d+)/consolidado', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_consolidado' ],
            'permission_callback' => '__return_true',
            'args' => [
                'post_id' => [ 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ],
                'codigo'  => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );

        register_rest_route( $ns, '/ejecucion/(?P<post_id>\d+)/dis', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_dis' ],
            'permission_callback' => '__return_true',
            'args' => [
                'post_id'      => [ 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ],
                'codigocuenta' => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );

        register_rest_route( $ns, '/ejecucion/(?P<post_id>\d+)/res', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_res' ],
            'permission_callback' => '__return_true',
            'args' => [
                'post_id'    => [ 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ],
                'numero_dis' => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                'rubro'      => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );

        register_rest_route( $ns, '/ejecucion/(?P<post_id>\d+)/proyecto', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_proyecto' ],
            'permission_callback' => '__return_true',
            'args' => [
                'post_id'    => [ 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ],
                'codigobpin' => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );
    }

    public function check_admin(): bool {
        return current_user_can( 'edit_posts' );
    }

    public function get_dependencias( \WP_REST_Request $request ): \WP_REST_Response {
        $anio     = $request->get_param( 'anio' );
        $mes      = $request->get_param( 'mes' );
        $compania = $request->get_param( 'compania' );

        return new \WP_REST_Response( $this->repo->get_dependencias( $anio, $mes, $compania ) );
    }

    public function get_rubros( \WP_REST_Request $request ): \WP_REST_Response {
        $post_id = (int) $request->get_param( 'post_id' );
        return new \WP_REST_Response( $this->repo->get_rubros( $post_id ) );
    }

    public function get_consolidado( \WP_REST_Request $request ): \WP_REST_Response {
        $post_id = (int) $request->get_param( 'post_id' );
        $codigo  = $request->get_param( 'codigo' );
        $result  = $this->repo->get_consolidado( $post_id, $codigo );
        return new \WP_REST_Response( $result ?? [] );
    }

    public function get_dis( \WP_REST_Request $request ): \WP_REST_Response {
        $post_id      = (int) $request->get_param( 'post_id' );
        $codigocuenta = $request->get_param( 'codigocuenta' );
        return new \WP_REST_Response( $this->repo->get_disponibilidades( $post_id, $codigocuenta ) );
    }

    public function get_res( \WP_REST_Request $request ): \WP_REST_Response {
        $post_id    = (int) $request->get_param( 'post_id' );
        $numero_dis = $request->get_param( 'numero_dis' );
        $rubro      = $request->get_param( 'rubro' );
        return new \WP_REST_Response( $this->repo->get_reservas( $post_id, $numero_dis, $rubro ) );
    }

    public function get_proyecto( \WP_REST_Request $request ): \WP_REST_Response {
        $codigobpin = $request->get_param( 'codigobpin' );
        return new \WP_REST_Response( $this->repo->get_proyecto_bpin( $codigobpin ) );
    }
}
