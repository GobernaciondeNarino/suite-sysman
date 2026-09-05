<?php
namespace SysmanSuite\Presupuesto;

use SysmanSuite\Visualizer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Presupuesto module: pre-designed budget views exposed as shortcodes.
 *
 * Two sides share the same six components:
 *   - Gastos   [sysman_gastos_*]   — agrupa por dependencia, detalle por rubro
 *                                     con la cadena DIS → RES → OBL → EGR.
 *   - Ingresos [sysman_ingresos_*] — no hay dependencias: agrupa por tipo o
 *                                     fuente de recurso y detalla por cuenta.
 *
 * Every shortcode renders a container plus a JSON config block; the rendering
 * happens in assets/js/presupuesto.js against the module's REST endpoints.
 * Shortcodes that opt into `enlazar` share a page-level filter.
 */
class PresupuestoModule {

    /** Component names, shared by both sides. */
    private const VISTAS = [ 'treemap', 'lista', 'ejecucion', 'explora', 'analisis', 'selector' ];

    private static ?self $instance = null;

    private RestController $rest;

    public static function instance(): self {
        return self::$instance ??= new self();
    }

    private function __construct() {
        $this->rest = new RestController();
    }

    public function boot(): void {
        $this->rest->register();

        add_action( 'admin_menu', [ $this, 'admin_menu' ], 30 );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );

        foreach ( self::VISTAS as $vista ) {
            add_shortcode( "sysman_gastos_{$vista}", fn( $atts ) => $this->render( $vista, (array) $atts, 'gastos' ) );
            add_shortcode( "sysman_ingresos_{$vista}", fn( $atts ) => $this->render( $vista, (array) $atts, 'ingresos' ) );
            // Alias heredado de la primera versión del módulo.
            add_shortcode( "sysman_pre_{$vista}", fn( $atts ) => $this->render( $vista, (array) $atts, 'gastos' ) );
        }
    }

    // ─── Admin ───────────────────────────────────────────────────

    public function admin_menu(): void {
        add_submenu_page(
            'sysman-suite',
            __( 'Gastos', 'sysman-suite' ),
            __( 'Gastos', 'sysman-suite' ),
            'manage_options',
            'sysman-gastos',
            [ $this, 'render_admin_gastos' ]
        );

        add_submenu_page(
            'sysman-suite',
            __( 'Ingresos', 'sysman-suite' ),
            __( 'Ingresos', 'sysman-suite' ),
            'manage_options',
            'sysman-ingresos',
            [ $this, 'render_admin_ingresos' ]
        );
    }

    public function render_admin_gastos(): void {
        $modulo = 'gastos';
        include SYSMAN_SUITE_PATH . 'templates/admin/presupuesto/catalogo.php';
    }

    public function render_admin_ingresos(): void {
        $modulo = 'ingresos';
        include SYSMAN_SUITE_PATH . 'templates/admin/presupuesto/catalogo.php';
    }

    public function admin_assets( string $hook ): void {
        if ( ! in_array( $hook, [ 'sysman-suite_page_sysman-gastos', 'sysman-suite_page_sysman-ingresos' ], true ) ) {
            return;
        }
        wp_enqueue_style( 'sysman-admin', SYSMAN_SUITE_URL . 'assets/css/admin.css', [], SYSMAN_SUITE_VERSION );
        wp_enqueue_style( 'sysman-presupuesto', SYSMAN_SUITE_URL . 'assets/css/presupuesto.css', [], SYSMAN_SUITE_VERSION );
    }

    // ─── Assets del frontend ─────────────────────────────────────

    private function enqueue_frontend( bool $con_graficos = false ): void {
        wp_enqueue_style(
            'sysman-presupuesto',
            SYSMAN_SUITE_URL . 'assets/css/presupuesto.css',
            [],
            SYSMAN_SUITE_VERSION
        );

        $deps = [];
        if ( $con_graficos ) {
            Visualizer::enqueue_d3plus();
            $deps[] = 'd3plus';
        }

        wp_enqueue_script(
            'sysman-presupuesto',
            SYSMAN_SUITE_URL . 'assets/js/presupuesto.js',
            $deps,
            SYSMAN_SUITE_VERSION,
            true
        );

        wp_localize_script( 'sysman-presupuesto', 'sysmanPresupuesto', [
            'restUrl'  => esc_url_raw( rest_url( 'sysman-suite/v1/presupuesto/' ) ),
            'campos'   => [
                'gastos'   => Repository::CAMPOS,
                'ingresos' => IngresosRepository::CAMPOS,
            ],
            'cadena'   => Repository::CADENA,
        ] );
    }

    // ─── Render ──────────────────────────────────────────────────

    /**
     * Accept the usual affirmative spellings for a boolean attribute.
     */
    private static function es_si( $valor ): bool {
        return in_array( strtolower( trim( (string) $valor ) ), [ 'si', 'sí', 'yes', '1', 'true', 'on' ], true );
    }

    /**
     * Build the config for one component and wrap it in its container.
     */
    private function render( string $vista, array $atts, string $modulo ): string {
        $ingresos = 'ingresos' === $modulo;

        $defaults = [
            'anio'        => '',
            'mes'         => '',
            'compania'    => '001',
            'campo'       => $ingresos ? 'totalpresupuesto' : 'apropiacionvigente',
            'dimension'   => $ingresos ? 'tiporecurso' : 'dependencia',
            'enlazar'     => 'si',
            'grupo'       => 'principal',
            'altura'      => '',
            'limite'      => '0',
            'titulo'      => '',
            // Campos adicionales a mostrar en el tooltip del gráfico.
            'tooltip'     => '',
            'colores'     => '',
            'buscador'    => 'si',
            'valor'       => '',
            'dependencia' => '',
            'vista'       => 'dimensiones',
            'tipo'        => 'descripcion',
            'etiqueta'    => '',
            'todas'       => '— Todas —',
        ];

        $a    = shortcode_atts( $defaults, $atts );
        $repo = $ingresos ? IngresosRepository::instance() : Repository::instance();
        $ctx  = $repo->contexto( [
            'compania' => $a['compania'],
            'anio'     => $a['anio'],
            'mes'      => $a['mes'],
        ] );

        // El valor de agrupación admite el nombre genérico y el heredado.
        $valor = sanitize_text_field( $a['valor'] ?: $a['dependencia'] );
        $tipo  = strtolower( trim( (string) $a['tipo'] ) );

        $config = [
            'modulo'      => $ingresos ? 'ingresos' : 'gastos',
            'compania'    => $ctx['compania'],
            'anio'        => $ctx['anio'],
            'mes'         => $ctx['mes'],
            'campo'       => $ingresos
                ? IngresosRepository::validar_campo( $a['campo'] )
                : Repository::validar_campo( $a['campo'] ),
            'dimension'   => $ingresos ? IngresosRepository::validar_dimension( $a['dimension'] ) : 'dependencia',
            'enlazar'     => self::es_si( $a['enlazar'] ),
            'grupo'       => sanitize_key( $a['grupo'] ?: 'principal' ),
            'limite'      => absint( $a['limite'] ),
            'titulo'      => sanitize_text_field( $a['titulo'] ),
            'tooltip'     => $this->parsear_tooltip( $a['tooltip'], $ingresos ),
            'valor'       => $valor,
            'buscador'    => self::es_si( $a['buscador'] ),
            'altura'      => absint( $a['altura'] ) ?: $this->altura_por_defecto( $vista ),
            'colores'     => sanitize_text_field( $a['colores'] ),
            'vista'       => in_array( $a['vista'], [ 'detalle', 'rubros' ], true ) ? 'detalle' : 'dimensiones',
            'tipo'        => in_array( $tipo, [ 'descripcion', 'cualitativo', 'cuantitativo' ], true ) ? $tipo : 'descripcion',
            'etiqueta'    => sanitize_text_field( $a['etiqueta'] ) ?: $this->etiqueta_selector( $ingresos, $a['dimension'] ),
            'todas'       => sanitize_text_field( $a['todas'] ),
            'etiqueta_dimension' => $ingresos
                ? IngresosRepository::etiqueta_dimension( $a['dimension'] )
                : __( 'Dependencia', 'sysman-suite' ),
            'etiqueta_plural'    => $ingresos
                ? IngresosRepository::etiqueta_plural( $a['dimension'] )
                : __( 'dependencias', 'sysman-suite' ),
            'etiqueta_todas'     => $ingresos
                ? IngresosRepository::etiqueta_todas( $a['dimension'] )
                : __( 'Todas las dependencias', 'sysman-suite' ),
        ];

        $this->enqueue_frontend( 'treemap' === $vista );

        return $this->contenedor( $vista, $config );
    }

    /**
     * Parse the `tooltip` attribute into a list of valid metric columns.
     */
    private function parsear_tooltip( string $bruto, bool $ingresos ): array {
        $campos = array_filter( array_map( 'trim', explode( ',', $bruto ) ) );
        if ( empty( $campos ) ) {
            return [];
        }
        return $ingresos
            ? array_values( array_intersect( $campos, array_keys( IngresosRepository::CAMPOS ) ) )
            : Repository::validar_extra( $campos );
    }

    private function altura_por_defecto( string $vista ): int {
        return match ( $vista ) {
            'treemap'   => 520,
            'lista'     => 480,
            'ejecucion' => 620,
            'explora'   => 640,
            default     => 0,
        };
    }

    private function etiqueta_selector( bool $ingresos, string $dimension ): string {
        if ( ! $ingresos ) {
            return __( 'Dependencia:', 'sysman-suite' );
        }
        return IngresosRepository::etiqueta_dimension( $dimension ) . ':';
    }

    /**
     * Wrap a component in its container with the JSON config attached.
     */
    private function contenedor( string $tipo, array $config ): string {
        static $n = 0;
        $n++;
        $id = 'sysman-pre-' . $tipo . '-' . $n;

        $estilo = ! empty( $config['altura'] )
            ? ' style="min-height:' . absint( $config['altura'] ) . 'px;"'
            : '';

        $html  = '<div class="sysman-pre sysman-pre--' . esc_attr( $tipo )
               . ' sysman-pre--mod-' . esc_attr( $config['modulo'] ) . '"'
               . ' id="' . esc_attr( $id ) . '"'
               . ' data-sysman-pre="' . esc_attr( $tipo ) . '"'
               . ' data-grupo="' . esc_attr( $config['grupo'] ) . '"'
               . $estilo . '>';

        if ( ! empty( $config['titulo'] ) ) {
            $html .= '<h3 class="sysman-pre__titulo">' . esc_html( $config['titulo'] ) . '</h3>';
        }

        $html .= '<div class="sysman-pre__cuerpo" data-rol="cuerpo">'
               . '<p class="sysman-pre__cargando">' . esc_html__( 'Cargando datos…', 'sysman-suite' ) . '</p>'
               . '</div>';

        $html .= '<script type="application/json" data-rol="config">' . wp_json_encode( $config ) . '</script>';
        $html .= '</div>';

        return $html;
    }
}
