<?php
namespace SysmanSuite\Presupuesto;

use SysmanSuite\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Public REST endpoints for the Presupuesto module (Gastos e Ingresos).
 *
 * Both sides expose the same three shapes — dimensión, detalle e item — so a
 * single set of frontend components can drive either one by passing `modulo`.
 * The endpoints serve open budget data: readable without authentication but
 * rate limited per IP like the rest of the plugin's public surface.
 */
class RestController {

    private const NS = 'sysman-suite/v1';

    public function register(): void {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Arguments shared by every endpoint.
     */
    private function args_base(): array {
        return [
            'compania'  => [ 'default' => '001', 'sanitize_callback' => 'sanitize_text_field' ],
            'anio'      => [ 'default' => 0, 'sanitize_callback' => 'absint' ],
            'mes'       => [ 'default' => 0, 'sanitize_callback' => 'absint' ],
            'modulo'    => [ 'default' => 'gastos', 'sanitize_callback' => 'sanitize_text_field' ],
            'dimension' => [ 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
            'campo'     => [ 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
            'tooltip'   => [ 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
            // Caracteres del código de cuenta que forman un rubro de ingresos.
            'longitud'  => [ 'default' => 0, 'sanitize_callback' => 'absint' ],
        ];
    }

    public function register_routes(): void {
        $rutas = [
            'periodos'     => [ 'get_periodos', 'pre_lectura', 120 ],
            // `dependencias` keeps its old name so existing pages keep working;
            // for Ingresos it returns tipos/fuentes de recurso instead.
            'dependencias' => [ 'get_dimensiones', 'pre_lectura', 120 ],
            'dimensiones'  => [ 'get_dimensiones', 'pre_lectura', 120 ],
            'rubros'       => [ 'get_detalle', 'pre_lectura', 120 ],
            'detalle'      => [ 'get_detalle', 'pre_lectura', 120 ],
            'rubro'        => [ 'get_item', 'pre_detalle', 120 ],
            'item'         => [ 'get_item', 'pre_detalle', 120 ],
            // Porcentaje de ejecución (gastos) o de recaudo (ingresos).
            'avance'       => [ 'get_avance', 'pre_lectura', 120 ],
            'analisis'     => [ 'get_analisis', 'pre_analisis', 60 ],
            // Mismo análisis redactado con IA (si está configurada). Cupo propio:
            // cada texto nuevo cuesta una llamada al proveedor.
            'analisis-ia'  => [ 'get_analisis_ia', 'pre_analisis_ia', 20 ],
        ];

        foreach ( $rutas as $ruta => $cfg ) {
            [ $callback, $bucket, $max ] = $cfg;

            $args = $this->args_base();
            if ( 'avance' === $ruta ) {
                $args['limite']      = [ 'default' => 0, 'sanitize_callback' => 'absint' ];
                $args['valor']       = [ 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ];
                $args['dependencia'] = [ 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ];
            } elseif ( in_array( $ruta, [ 'rubros', 'detalle' ], true ) ) {
                $args['valor']       = [ 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ];
                $args['dependencia'] = [ 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ];
            } elseif ( in_array( $ruta, [ 'rubro', 'item' ], true ) ) {
                $args['codigo'] = [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ];
            } elseif ( in_array( $ruta, [ 'analisis', 'analisis-ia' ], true ) ) {
                $args['vista']       = [ 'default' => 'dimensiones', 'sanitize_callback' => 'sanitize_text_field' ];
                $args['tipo']        = [ 'default' => 'descripcion', 'sanitize_callback' => 'sanitize_text_field' ];
                $args['valor']       = [ 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ];
                $args['dependencia'] = [ 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ];
            } else {
                $args['limite'] = [ 'default' => 0, 'sanitize_callback' => 'absint' ];
            }

            register_rest_route( self::NS, '/presupuesto/' . $ruta, [
                'methods'             => 'GET',
                'callback'            => [ $this, $callback ],
                'permission_callback' => fn() => $this->permiso( $bucket, $max ),
                'args'                => $args,
            ] );
        }
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

    // ─── Resolución de módulo ────────────────────────────────────

    private function es_ingresos( \WP_REST_Request $r ): bool {
        return 'ingresos' === $r->get_param( 'modulo' );
    }

    private function repo( \WP_REST_Request $r ) {
        return $this->es_ingresos( $r ) ? IngresosRepository::instance() : Repository::instance();
    }

    private function ctx( \WP_REST_Request $r ): array {
        return $this->repo( $r )->contexto( [
            'compania' => $r->get_param( 'compania' ),
            'anio'     => $r->get_param( 'anio' ),
            'mes'      => $r->get_param( 'mes' ),
        ] );
    }

    private function campo( \WP_REST_Request $r ): string {
        $campo = (string) $r->get_param( 'campo' );
        return $this->es_ingresos( $r )
            ? IngresosRepository::validar_campo( $campo ?: 'totalpresupuesto' )
            : Repository::validar_campo( $campo ?: 'apropiacionvigente' );
    }

    private function etiqueta_campo( \WP_REST_Request $r, string $campo ): string {
        return $this->es_ingresos( $r )
            ? IngresosRepository::etiqueta_campo( $campo )
            : Repository::etiqueta_campo( $campo );
    }

    /** Extra metric columns requested for the tooltip. */
    private function tooltip( \WP_REST_Request $r ): array {
        $bruto = array_filter( array_map( 'trim', explode( ',', (string) $r->get_param( 'tooltip' ) ) ) );
        return $this->es_ingresos( $r )
            ? array_values( array_intersect( $bruto, array_keys( IngresosRepository::CAMPOS ) ) )
            : Repository::validar_extra( $bruto );
    }

    /** The grouping value: `valor` is the generic name, `dependencia` the legacy one. */
    private function valor( \WP_REST_Request $r ): string {
        $valor = (string) $r->get_param( 'valor' );
        return '' !== $valor ? $valor : (string) $r->get_param( 'dependencia' );
    }

    private function longitud( \WP_REST_Request $r ): int {
        $pedida = (int) $r->get_param( 'longitud' );
        return $pedida > 0 ? IngresosRepository::validar_longitud( $pedida ) : IngresosRepository::LONGITUD_RUBRO;
    }

    private function dimension( \WP_REST_Request $r ): string {
        if ( ! $this->es_ingresos( $r ) ) {
            return 'dependencia';
        }
        return IngresosRepository::validar_dimension( (string) $r->get_param( 'dimension' ) );
    }

    private function meta( \WP_REST_Request $r, array $ctx, array $extra = [] ): array {
        return array_merge( [
            'compania'        => $ctx['compania'],
            'anio'            => $ctx['anio'],
            'mes'             => $ctx['mes'],
            'mes_nombre'      => Helpers::month_name( (int) $ctx['mes'] ),
            'modulo'          => $this->es_ingresos( $r ) ? 'ingresos' : 'gastos',
            'dimension'       => $this->dimension( $r ),
            'dimension_label' => $this->es_ingresos( $r )
                ? IngresosRepository::etiqueta_dimension( $this->dimension( $r ) )
                : 'Dependencia',
        ], $extra );
    }

    // ─── Endpoints ───────────────────────────────────────────────

    public function get_periodos( \WP_REST_Request $r ): \WP_REST_Response {
        $compania = $r->get_param( 'compania' );
        $repo     = $this->repo( $r );

        // Only Gastos keeps a full period index; Ingresos reports its latest.
        $periodos = method_exists( $repo, 'periodos' )
            ? $repo->periodos( $compania )
            : [ $repo->ultimo_periodo( $compania ) ];

        return new \WP_REST_Response( [
            'periodos' => array_map(
                static fn( $p ) => $p + [ 'mes_nombre' => Helpers::month_name( (int) $p['mes'] ) ],
                $periodos
            ),
        ] );
    }

    public function get_dimensiones( \WP_REST_Request $r ): \WP_REST_Response {
        $ctx     = $this->ctx( $r );
        $campo   = $this->campo( $r );
        $tooltip = $this->tooltip( $r );
        $limite  = (int) $r->get_param( 'limite' );

        $filas = $this->es_ingresos( $r )
            ? IngresosRepository::instance()->dimensiones( $ctx, $campo, $limite, $this->dimension( $r ), $tooltip, $this->longitud( $r ) )
            : Repository::instance()->dependencias( $ctx, $campo, $limite, $tooltip );

        return new \WP_REST_Response( [
            'meta'  => $this->meta( $r, $ctx, [
                'campo'         => $campo,
                'campo_label'   => $this->etiqueta_campo( $r, $campo ),
                'tooltip'       => $tooltip,
                'tooltip_label' => $this->etiquetas( $r, $tooltip ),
                'total'         => array_sum( array_column( $filas, 'value' ) ),
            ] ),
            'data'  => $filas,
            'total' => count( $filas ),
        ] );
    }

    public function get_detalle( \WP_REST_Request $r ): \WP_REST_Response {
        $ctx   = $this->ctx( $r );
        $campo = $this->campo( $r );
        $valor = $this->valor( $r );

        $filas = $this->es_ingresos( $r )
            ? IngresosRepository::instance()->detalle( $ctx, $valor, $campo, $this->dimension( $r ), $this->longitud( $r ) )
            : Repository::instance()->rubros( $ctx, $valor, $campo );

        return new \WP_REST_Response( [
            'meta'  => $this->meta( $r, $ctx, [
                'campo'       => $campo,
                'campo_label' => $this->etiqueta_campo( $r, $campo ),
                'valor'       => $valor,
                'dependencia' => $valor,
            ] ),
            'data'  => $filas,
            'total' => count( $filas ),
        ] );
    }

    /**
     * Execution (or collection) rate per dependencia, or per rubro when one
     * dependencia is selected.
     */
    public function get_avance( \WP_REST_Request $r ): \WP_REST_Response {
        $ctx      = $this->ctx( $r );
        $valor    = $this->valor( $r );
        $limite   = (int) $r->get_param( 'limite' );
        $ingresos = $this->es_ingresos( $r );

        $filas = $ingresos
            ? IngresosRepository::instance()->avance( $ctx, $valor, $limite, $this->dimension( $r ), 'recaudosacumulados', 'totalpresupuesto', $this->longitud( $r ) )
            : Repository::instance()->avance( $ctx, $valor, $limite );

        return new \WP_REST_Response( [
            'meta'  => $this->meta( $r, $ctx, self::totales_avance( $filas ) + [
                'valor'            => $valor,
                'dependencia'      => $valor,
                'base_label'       => $ingresos ? 'Total Presupuesto' : 'Apropiación Vigente',
                'ejecutado_label'  => $ingresos ? 'Recaudos Acumulados' : 'Compromisos',
                'porcentaje_label' => $ingresos ? '% Recaudado' : '% Ejecución',
            ] ),
            'data'  => $filas,
            'total' => count( $filas ),
        ] );
    }

    /**
     * Aggregate rate: the ratio of the sums, not the average of the ratios.
     *
     * Promediar porcentajes daría el mismo peso a una dependencia de mil
     * millones que a una de un millón.
     */
    private static function totales_avance( array $filas ): array {
        $base      = array_sum( array_column( $filas, 'base' ) );
        $ejecutado = array_sum( array_column( $filas, 'ejecutado' ) );

        return [
            'base'       => $base,
            'ejecutado'  => $ejecutado,
            'porcentaje' => $base > 0 ? $ejecutado / $base : null,
        ];
    }

    public function get_item( \WP_REST_Request $r ): \WP_REST_Response {
        $ctx    = $this->ctx( $r );
        $codigo = (string) $r->get_param( 'codigo' );

        if ( '' === $codigo ) {
            return new \WP_REST_Response( [ 'error' => 'Falta el código' ], 400 );
        }

        $data = $this->es_ingresos( $r )
            ? IngresosRepository::instance()->item( $ctx, $codigo )
            : Repository::instance()->rubro( $ctx, $codigo );

        return new \WP_REST_Response( [
            'meta' => $this->meta( $r, $ctx, [ 'codigo' => $codigo ] ),
            'data' => $data,
        ] );
    }

    public function get_analisis( \WP_REST_Request $r ): \WP_REST_Response {
        [ $ctx, $analisis ] = $this->analizar( $r );

        return new \WP_REST_Response( [
            'meta' => $this->meta( $r, $ctx, [ 'tipo' => $r->get_param( 'tipo' ), 'campo' => $this->campo( $r ), 'valor' => $this->valor( $r ) ] ),
            'data' => $analisis + [ 'fuente' => 'reglas' ],
        ] );
    }

    /**
     * El análisis redactado con IA. Si no hay proveedor configurado, si falla
     * o si el texto trae una cifra que no está en los datos, devuelve el
     * mismo texto por reglas (`fuente` = reglas).
     */
    public function get_analisis_ia( \WP_REST_Request $r ): \WP_REST_Response {
        [ $ctx, $analisis, $filas, $totales, $opciones ] = $this->analizar( $r );

        $hechos   = self::hechos( $ctx, (string) $r->get_param( 'tipo' ), $filas, $totales, $opciones );
        $analisis = ( new \SysmanSuite\IA\Redactor() )->redactar( $analisis, $hechos );

        return new \WP_REST_Response( [
            'meta' => $this->meta( $r, $ctx, [ 'tipo' => $r->get_param( 'tipo' ), 'campo' => $this->campo( $r ), 'valor' => $this->valor( $r ) ] ),
            'data' => $analisis,
        ] );
    }

    /**
     * Datos de la vista en forma legible, para que la IA redacte sobre las
     * mismas cifras que el texto por reglas (ya formateadas: no calcula nada).
     */
    public static function hechos( array $ctx, string $tipo, array $filas, array $totales, array $opciones ): array {
        $avance = ! empty( $opciones['avance'] );
        $pct    = static fn( $x ) => Analysis::porcentaje( (float) $x );

        $hechos = [
            'entidad'    => 'Gobernación de Nariño',
            'modulo'     => $opciones['modulo'] ?? 'gastos',
            'tipo'       => in_array( $tipo, [ 'descripcion', 'cualitativo', 'cuantitativo' ], true ) ? $tipo : 'descripcion',
            'vista'      => $avance ? 'avance' : 'importes',
            'periodo'    => mb_strtolower( Helpers::month_name( (int) $ctx['mes'] ) ) . ' de ' . (int) $ctx['anio'],
            'agrupacion' => '' !== ( $opciones['valor'] ?? '' )
                ? 'detalle de ' . $opciones['valor']
                : (string) ( $opciones['dimension_plural'] ?? 'dependencias' ),
            'filas'      => [],
        ];

        if ( $avance ) {
            $a = $opciones['avance'];
            $hechos['medida']          = 'porcentaje ' . ( $opciones['ejecutado_label'] ?? 'comprometido' ) . ' frente a ' . ( $opciones['base_label'] ?? 'apropiación vigente' );
            $hechos['base_total']      = Analysis::moneda( (float) ( $a['base'] ?? 0 ) );
            $hechos['ejecutado_total'] = Analysis::moneda( (float) ( $a['ejecutado'] ?? 0 ) );
            $hechos['porcentaje_global_ponderado'] = null !== ( $a['porcentaje'] ?? null ) ? $pct( $a['porcentaje'] ) : 'sin dato';
            foreach ( array_slice( $filas, 0, 20 ) as $f ) {
                $hechos['filas'][] = [
                    'nombre'     => (string) ( $f['label'] ?? '' ),
                    'base'       => Analysis::moneda( (float) ( $f['base'] ?? 0 ) ),
                    'ejecutado'  => Analysis::moneda( (float) ( $f['ejecutado'] ?? 0 ) ),
                    'porcentaje' => null !== ( $f['porcentaje'] ?? null ) ? $pct( $f['porcentaje'] ) : 'sin presupuesto',
                ];
            }
            $hechos['cantidad'] = count( $filas );
            return $hechos;
        }

        $total = array_sum( array_map( static fn( $f ) => (float) ( $f['value'] ?? 0 ), $filas ) );
        $hechos['medida']   = (string) ( $opciones['campo_label'] ?? $opciones['campo'] ?? '' );
        $hechos['total']    = Analysis::moneda( $total );
        $hechos['cantidad'] = count( $filas );

        foreach ( array_slice( $filas, 0, 15 ) as $f ) {
            $v = (float) ( $f['value'] ?? 0 );
            $hechos['filas'][] = [
                'nombre'        => (string) ( $f['label'] ?? $f['nombre'] ?? '' ),
                'valor'         => Analysis::moneda( $v ),
                'participacion' => 0.0 !== $total ? $pct( $v / $total ) : $pct( 0 ),
            ];
        }
        if ( count( $filas ) > 15 ) {
            $resto = $total - array_sum( array_map( static fn( $f ) => (float) ( $f['value'] ?? 0 ), array_slice( $filas, 0, 15 ) ) );
            $hechos['resto'] = [
                'cantidad'      => count( $filas ) - 15,
                'valor'         => Analysis::moneda( $resto ),
                'participacion' => 0.0 !== $total ? $pct( $resto / $total ) : $pct( 0 ),
            ];
        }

        return $hechos;
    }

    /**
     * Análisis por reglas para uso interno (asistente de voz), sin pasar por
     * el despachador REST ni por su límite por IP.
     *
     * @param array $params Mismos parámetros que el endpoint `analisis`.
     * @return array{0:array, 1:array, 2:array, 3:array, 4:array}
     */
    public function analisis_interno( array $params ): array {
        $r = new \WP_REST_Request( 'GET', '/' . self::NS . '/presupuesto/analisis' );
        $defectos = [
            'compania' => '001', 'anio' => 0, 'mes' => 0, 'modulo' => 'gastos', 'dimension' => '',
            'campo' => '', 'longitud' => 0, 'vista' => 'dimensiones', 'tipo' => 'descripcion',
            'valor' => '', 'dependencia' => '',
        ];
        foreach ( array_merge( $defectos, array_intersect_key( $params, $defectos ) ) as $k => $v ) {
            $r->set_param( $k, $v );
        }
        return $this->analizar( $r );
    }

    /**
     * Calcula el análisis por reglas de una vista.
     *
     * @return array{0:array, 1:array, 2:array, 3:array, 4:array} ctx, análisis, filas, totales, opciones.
     */
    private function analizar( \WP_REST_Request $r ): array {
        $ctx        = $this->ctx( $r );
        $campo      = $this->campo( $r );
        $valor      = $this->valor( $r );
        $ingresos   = $this->es_ingresos( $r );
        $dimension  = $this->dimension( $r );
        $vista_attr = (string) $r->get_param( 'vista' );
        $es_avance  = 'avance' === $vista_attr;
        $es_detalle = ! $es_avance && ( in_array( $vista_attr, [ 'rubros', 'detalle' ], true ) || '' !== $valor );

        // La vista de avance analiza porcentajes, no importes: su serie es la
        // del mismo endpoint que alimenta la gráfica.
        $vista_pedida = $es_avance ? 'avance' : ( $es_detalle ? 'detalle' : 'dimensiones' );

        if ( $ingresos ) {
            $repo = IngresosRepository::instance();
            $longitud = $this->longitud( $r );
            if ( $es_avance ) {
                $filas = $repo->avance( $ctx, $valor, 0, $dimension, 'recaudosacumulados', 'totalpresupuesto', $longitud );
            } else {
                $filas = $es_detalle
                    ? $repo->detalle( $ctx, $valor, $campo, $dimension, $longitud )
                    : $repo->dimensiones( $ctx, $campo, 0, $dimension, [], $longitud );
            }
            $totales = $repo->totales( $ctx, $valor, $dimension, $longitud );
        } else {
            $repo = Repository::instance();
            if ( $es_avance ) {
                $filas = $repo->avance( $ctx, $valor );
            } else {
                $filas = $es_detalle
                    ? $repo->rubros( $ctx, $valor, $campo )
                    : $repo->dependencias( $ctx, $campo );
            }
            $totales = $repo->totales( $ctx, $valor );
        }

        $opciones = [
            'campo'              => $campo,
            'campo_label'        => $this->etiqueta_campo( $r, $campo ),
            'valor'              => $valor,
            'modulo'             => $ingresos ? 'ingresos' : 'gastos',
            'dimension_label'    => $ingresos ? IngresosRepository::etiqueta_dimension( $dimension ) : 'dependencia',
            'dimension_plural'   => $ingresos ? IngresosRepository::etiqueta_plural( $dimension ) : 'dependencias',
            'dimension_femenino' => $ingresos ? IngresosRepository::es_femenino( $dimension ) : true,
            'base_label'         => $ingresos ? 'presupuesto definitivo' : 'apropiación vigente',
            'ejecutado_label'    => $ingresos ? 'recaudado' : 'comprometido',
            'avance'             => $es_avance ? self::totales_avance( $filas ) : [],
        ];

        $analisis = Analysis::generar(
            (string) $r->get_param( 'tipo' ),
            $vista_pedida,
            $ctx,
            [ 'filas' => $filas, 'totales' => $totales ],
            $opciones
        );

        return [ $ctx, $analisis, $filas, $totales, $opciones ];
    }

    /**
     * Human labels for a list of metric columns.
     */
    private function etiquetas( \WP_REST_Request $r, array $campos ): array {
        $out = [];
        foreach ( $campos as $c ) {
            $out[ $c ] = $this->etiqueta_campo( $r, $c );
        }
        return $out;
    }
}
