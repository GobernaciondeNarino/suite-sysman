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

        register_rest_route( $ns, '/vigencias', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_vigencias' ],
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
            'permission_callback' => fn() => $this->public_permission( 'gn_public', 120 ),
            'args' => [
                'post_id' => [ 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ],
            ],
        ] );

        register_rest_route( $ns, '/ejecucion/(?P<post_id>\d+)/consolidado', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_consolidado' ],
            'permission_callback' => fn() => $this->public_permission( 'gn_public', 120 ),
            'args' => [
                'post_id' => [ 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ],
                'codigo'  => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );

        register_rest_route( $ns, '/ejecucion/(?P<post_id>\d+)/dis', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_dis' ],
            'permission_callback' => fn() => $this->public_permission( 'gn_public', 120 ),
            'args' => [
                'post_id'      => [ 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ],
                'codigocuenta' => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );

        register_rest_route( $ns, '/ejecucion/(?P<post_id>\d+)/res', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_res' ],
            'permission_callback' => fn() => $this->public_permission( 'gn_public', 120 ),
            'args' => [
                'post_id'    => [ 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ],
                'numero_dis' => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                'rubro'      => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );

        register_rest_route( $ns, '/ejecucion/(?P<post_id>\d+)/export', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_export' ],
            'permission_callback' => fn() => $this->public_permission( 'gn_export', 30 ),
            'args' => [
                'post_id' => [ 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ],
            ],
        ] );

        register_rest_route( $ns, '/ejecucion/(?P<post_id>\d+)/proyecto', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_proyecto' ],
            'permission_callback' => fn() => $this->public_permission( 'gn_public', 120 ),
            'args' => [
                'post_id'    => [ 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ],
                'codigobpin' => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );
        register_rest_route( $ns, '/reporte/disponibilidades', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_reporte_dis' ],
            'permission_callback' => fn() => $this->public_permission( 'gn_export', 30 ),
            'args' => [
                'anio'        => [ 'required' => true,  'type' => 'integer', 'sanitize_callback' => 'absint' ],
                'mes'         => [ 'required' => true,  'type' => 'integer', 'sanitize_callback' => 'absint' ],
                'compania'    => [ 'required' => false, 'type' => 'string', 'default' => '001', 'sanitize_callback' => 'sanitize_text_field' ],
                'dependencia' => [ 'required' => false, 'type' => 'string', 'default' => '',    'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );
    }

    public function check_admin(): bool {
        return current_user_can( 'edit_posts' );
    }

    /**
     * Permission callback for public endpoints: allows access but throttles
     * abusive clients per IP.
     *
     * @return true|\WP_Error
     */
    public function public_permission( string $bucket, int $max ) {
        if ( ! \SysmanSuite\Helpers::rate_limit_check( $bucket, $max ) ) {
            return new \WP_Error(
                'rest_rate_limited',
                __( 'Demasiadas solicitudes. Intente de nuevo en un minuto.', 'sysman-suite' ),
                [ 'status' => 429 ]
            );
        }
        return true;
    }

    public function get_vigencias( \WP_REST_Request $request ): \WP_REST_Response {
        $anio     = $request->get_param( 'anio' );
        $mes      = $request->get_param( 'mes' );
        $compania = $request->get_param( 'compania' );

        return new \WP_REST_Response( $this->repo->get_vigencias( $anio, $mes, $compania ) );
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
        $rows = $this->repo->get_disponibilidades( $post_id, $codigocuenta );
        return new \WP_REST_Response( $this->enrich_with_contracts( $rows ) );
    }

    public function get_res( \WP_REST_Request $request ): \WP_REST_Response {
        $post_id    = (int) $request->get_param( 'post_id' );
        $numero_dis = $request->get_param( 'numero_dis' );
        $rubro      = $request->get_param( 'rubro' );
        $rows = $this->repo->get_reservas( $post_id, $numero_dis, $rubro );
        return new \WP_REST_Response( $this->enrich_with_contracts( $rows ) );
    }

    private function extract_options( \WP_REST_Request $request, array $filter_defs ): array {
        $options = [];

        $filtros = [];
        foreach ( array_keys( $filter_defs ) as $key ) {
            $val = $request->get_param( $key );
            if ( null !== $val && '' !== (string) $val ) {
                $filtros[ $key ] = sanitize_text_field( (string) $val );
            }
        }
        if ( ! empty( $filtros ) ) {
            $options['filtros'] = $filtros;
        }

        $buscar = $request->get_param( 'buscar' );
        if ( null !== $buscar && '' !== $buscar ) {
            $options['buscar'] = sanitize_text_field( $buscar );
        }

        $per_page = $request->get_param( 'per_page' );
        if ( null !== $per_page ) {
            $options['per_page'] = absint( $per_page );
            $options['pagina']   = max( 1, absint( $request->get_param( 'pagina' ) ?: 1 ) );
        }

        $orderby = $request->get_param( 'orderby' );
        if ( null !== $orderby && '' !== $orderby ) {
            $options['orderby'] = sanitize_text_field( $orderby );
            $options['order']   = sanitize_text_field( $request->get_param( 'order' ) ?: 'ASC' );
        }

        return $options;
    }

    private function enrich_with_contracts( array $rows ): array {
        $docs = array_filter( array_unique( array_column( $rows, 'nrodocumento' ) ) );
        if ( empty( $docs ) ) {
            return $rows;
        }
        $urls = $this->repo->get_contract_urls( array_values( $docs ) );
        if ( empty( $urls ) ) {
            return $rows;
        }
        foreach ( $rows as &$row ) {
            $doc = $row['nrodocumento'] ?? '';
            $row['contract_url'] = $urls[ $doc ] ?? '';
        }
        return $rows;
    }

    public function get_export( \WP_REST_Request $request ): \WP_REST_Response {
        $post_id = (int) $request->get_param( 'post_id' );
        $post    = get_post( $post_id );

        if ( ! $post || 'gn_ejecucion' !== $post->post_type ) {
            return new \WP_REST_Response( [ 'error' => 'Not found' ], 404 );
        }

        $options = $this->extract_options( $request, Repository::EXPORT_FILTERS );
        $result  = $this->repo->get_export_data( $post_id, $options );

        $meta = [
            'titulo'      => $post->post_title,
            'dependencia' => get_post_meta( $post_id, '_gn_dependencia', true ),
            'anio'        => (int) get_post_meta( $post_id, '_gn_anio', true ),
            'mes'         => (int) get_post_meta( $post_id, '_gn_mes', true ),
            'compania'    => get_post_meta( $post_id, '_gn_compania', true ) ?: '001',
            'vigencia'    => get_post_meta( $post_id, '_gn_vigencia', true ),
        ];

        $paginated = isset( $result['data'] );
        $rows      = $paginated ? $result['data'] : $result;

        $response = [
            'meta'  => $meta,
            'total' => $paginated ? $result['total'] : count( $rows ),
            'data'  => $rows,
        ];

        if ( $paginated ) {
            $response['pagina']   = $result['pagina'];
            $response['per_page'] = $result['per_page'];
            $response['paginas']  = $result['paginas'];
        }
        if ( ! empty( $options['filtros'] ) ) {
            $response['filtros'] = $options['filtros'];
        }
        if ( ! empty( $options['buscar'] ) ) {
            $response['buscar'] = $options['buscar'];
        }

        return new \WP_REST_Response( $response );
    }

    public function get_reporte_dis( \WP_REST_Request $request ): \WP_REST_Response {
        $anio        = (int) $request->get_param( 'anio' );
        $mes         = (int) $request->get_param( 'mes' );
        $compania    = $request->get_param( 'compania' );
        $dependencia = $request->get_param( 'dependencia' );

        $options = $this->extract_options( $request, Repository::DIS_FILTERS );
        $result  = $this->repo->get_disponibilidades_report( $anio, $mes, $compania, $dependencia, $options );

        $meta = [
            'anio'        => $anio,
            'mes'         => $mes,
            'mes_nombre'  => \SysmanSuite\Helpers::month_name( $mes ),
            'compania'    => $compania,
            'dependencia' => $dependencia,
        ];

        $paginated = isset( $result['data'] );
        $rows      = $paginated ? $result['data'] : $result;
        $enriched  = $this->enrich_with_contracts( $rows );

        $response = [
            'meta'  => $meta,
            'total' => $paginated ? $result['total'] : count( $enriched ),
            'data'  => $enriched,
        ];

        if ( $paginated ) {
            $response['pagina']   = $result['pagina'];
            $response['per_page'] = $result['per_page'];
            $response['paginas']  = $result['paginas'];
        }
        if ( ! empty( $options['filtros'] ) ) {
            $response['filtros'] = $options['filtros'];
        }
        if ( ! empty( $options['buscar'] ) ) {
            $response['buscar'] = $options['buscar'];
        }

        return new \WP_REST_Response( $response );
    }

    public function get_proyecto( \WP_REST_Request $request ): \WP_REST_Response {
        $codigobpin = $request->get_param( 'codigobpin' );
        return new \WP_REST_Response( $this->repo->get_proyecto_bpin( $codigobpin ) );
    }
}
