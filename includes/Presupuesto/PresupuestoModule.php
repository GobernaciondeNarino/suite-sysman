<?php
namespace SysmanSuite\Presupuesto;

use SysmanSuite\Visualizer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Presupuesto module: pre-designed budget views exposed as shortcodes.
 *
 * Every shortcode renders a container plus a JSON config block; the rendering
 * and data fetching happen in assets/js/presupuesto.js against the module's
 * REST endpoints. Shortcodes that opt into `enlazar` share a page-level filter
 * so clicking one updates the others.
 */
class PresupuestoModule {

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

        foreach ( [ 'treemap', 'lista', 'ejecucion', 'explora', 'analisis', 'selector' ] as $vista ) {
            add_shortcode( "sysman_pre_{$vista}", [ $this, "shortcode_{$vista}" ] );
        }
    }

    // ─── Admin ───────────────────────────────────────────────────

    public function admin_menu(): void {
        add_submenu_page(
            'sysman-suite',
            __( 'Presupuesto', 'sysman-suite' ),
            __( 'Presupuesto', 'sysman-suite' ),
            'manage_options',
            'sysman-presupuesto',
            [ $this, 'render_admin' ]
        );
    }

    public function render_admin(): void {
        include SYSMAN_SUITE_PATH . 'templates/admin/presupuesto/catalogo.php';
    }

    public function admin_assets( string $hook ): void {
        if ( 'sysman-suite_page_sysman-presupuesto' !== $hook ) {
            return;
        }
        wp_enqueue_style( 'sysman-admin', SYSMAN_SUITE_URL . 'assets/css/admin.css', [], SYSMAN_SUITE_VERSION );
        wp_enqueue_style( 'sysman-presupuesto', SYSMAN_SUITE_URL . 'assets/css/presupuesto.css', [], SYSMAN_SUITE_VERSION );
    }

    // ─── Assets del frontend ─────────────────────────────────────

    /**
     * Enqueue the module's assets. Called from the shortcodes themselves so
     * they also work inside widgets and page builders.
     *
     * @param bool $con_graficos Whether D3plus is needed on this page.
     */
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
            'restUrl' => esc_url_raw( rest_url( 'sysman-suite/v1/presupuesto/' ) ),
            'campos'  => Repository::CAMPOS,
            'cadena'  => Repository::CADENA,
        ] );
    }

    // ─── Atributos comunes ───────────────────────────────────────

    /**
     * Normalize the attributes every shortcode shares.
     */
    private function base( array $atts, array $extra = [] ): array {
        $defaults = array_merge( [
            'anio'     => '',
            'mes'      => '',
            'compania' => '001',
            'campo'    => 'apropiacionvigente',
            'enlazar'  => 'si',
            'grupo'    => 'principal',
            'altura'   => '',
            'limite'   => '0',
            'titulo'   => '',
        ], $extra );

        $a   = shortcode_atts( $defaults, $atts );
        $ctx = Repository::instance()->contexto( [
            'compania' => $a['compania'],
            'anio'     => $a['anio'],
            'mes'      => $a['mes'],
        ] );

        return [
            'atts'   => $a,
            'config' => [
                'compania' => $ctx['compania'],
                'anio'     => $ctx['anio'],
                'mes'      => $ctx['mes'],
                'campo'    => Repository::validar_campo( $a['campo'] ),
                // Cross-filtering is opt-out: "no" / "0" / "false" disconnect it.
                'enlazar'  => self::es_si( $a['enlazar'] ),
                'grupo'    => sanitize_key( $a['grupo'] ?: 'principal' ),
                'limite'   => absint( $a['limite'] ),
                'titulo'   => sanitize_text_field( $a['titulo'] ),
            ],
        ];
    }

    /**
     * Accept the usual affirmative spellings for a boolean attribute.
     */
    private static function es_si( $valor ): bool {
        return in_array( strtolower( trim( (string) $valor ) ), [ 'si', 'sí', 'yes', '1', 'true', 'on' ], true );
    }

    /**
     * Wrap a component in its container with the JSON config attached.
     */
    private function contenedor( string $tipo, array $config, string $clase_extra = '', string $interior = '' ): string {
        static $n = 0;
        $n++;
        $id = 'sysman-pre-' . $tipo . '-' . $n;

        $estilo = '';
        if ( ! empty( $config['altura'] ) ) {
            $estilo = ' style="min-height:' . absint( $config['altura'] ) . 'px;"';
        }

        $html  = '<div class="sysman-pre sysman-pre--' . esc_attr( $tipo )
               . ( $clase_extra ? ' ' . esc_attr( $clase_extra ) : '' ) . '"'
               . ' id="' . esc_attr( $id ) . '"'
               . ' data-sysman-pre="' . esc_attr( $tipo ) . '"'
               . ' data-grupo="' . esc_attr( $config['grupo'] ) . '"'
               . $estilo . '>';

        if ( ! empty( $config['titulo'] ) ) {
            $html .= '<h3 class="sysman-pre__titulo">' . esc_html( $config['titulo'] ) . '</h3>';
        }

        $html .= $interior;
        $html .= '<div class="sysman-pre__cuerpo" data-rol="cuerpo">'
               . '<p class="sysman-pre__cargando">' . esc_html__( 'Cargando datos…', 'sysman-suite' ) . '</p>'
               . '</div>';

        $html .= '<script type="application/json" data-rol="config">' . wp_json_encode( $config ) . '</script>';
        $html .= '</div>';

        return $html;
    }

    // ─── Shortcodes ──────────────────────────────────────────────

    /**
     * [sysman_pre_treemap] — treemap de dependencias por el campo elegido.
     */
    public function shortcode_treemap( $atts ): string {
        $b = $this->base( (array) $atts, [ 'altura' => '520', 'colores' => '' ] );
        $this->enqueue_frontend( true );

        $config             = $b['config'];
        $config['altura']   = absint( $b['atts']['altura'] ) ?: 520;
        $config['colores']  = sanitize_text_field( $b['atts']['colores'] );

        return $this->contenedor( 'treemap', $config );
    }

    /**
     * [sysman_pre_lista] — lista de dependencias con nº de rubros y valor.
     */
    public function shortcode_lista( $atts ): string {
        $b = $this->base( (array) $atts, [ 'altura' => '480', 'buscador' => 'si' ] );
        $this->enqueue_frontend();

        $config              = $b['config'];
        $config['altura']    = absint( $b['atts']['altura'] ) ?: 480;
        $config['buscador']  = self::es_si( $b['atts']['buscador'] );

        return $this->contenedor( 'lista', $config );
    }

    /**
     * [sysman_pre_ejecucion] — ejecución de una dependencia por rubro:
     * consolidado, modificaciones y cadena DIS → RES → OBL → EGR.
     */
    public function shortcode_ejecucion( $atts ): string {
        $b = $this->base( (array) $atts, [ 'altura' => '620', 'dependencia' => '' ] );
        $this->enqueue_frontend();

        $config                = $b['config'];
        $config['altura']      = absint( $b['atts']['altura'] ) ?: 620;
        $config['dependencia'] = sanitize_text_field( $b['atts']['dependencia'] );

        return $this->contenedor( 'ejecucion', $config );
    }

    /**
     * [sysman_pre_explora] — maestro-detalle: dependencias a la izquierda,
     * su ejecución a la derecha. Equivale a colocar lista + ejecucion enlazadas.
     */
    public function shortcode_explora( $atts ): string {
        $b = $this->base( (array) $atts, [ 'altura' => '640', 'buscador' => 'si' ] );
        $this->enqueue_frontend();

        $config             = $b['config'];
        $config['altura']   = absint( $b['atts']['altura'] ) ?: 640;
        $config['buscador'] = self::es_si( $b['atts']['buscador'] );

        return $this->contenedor( 'explora', $config );
    }

    /**
     * [sysman_pre_analisis] — descripción, análisis cualitativo o cuantitativo.
     */
    public function shortcode_analisis( $atts ): string {
        $b = $this->base( (array) $atts, [ 'vista' => 'dependencias', 'tipo' => 'descripcion', 'dependencia' => '' ] );
        $this->enqueue_frontend();

        $tipo = strtolower( trim( (string) $b['atts']['tipo'] ) );
        $config                = $b['config'];
        $config['vista']       = 'rubros' === $b['atts']['vista'] ? 'rubros' : 'dependencias';
        $config['tipo']        = in_array( $tipo, [ 'descripcion', 'cualitativo', 'cuantitativo' ], true ) ? $tipo : 'descripcion';
        $config['dependencia'] = sanitize_text_field( $b['atts']['dependencia'] );

        return $this->contenedor( 'analisis', $config );
    }

    /**
     * [sysman_pre_selector] — desplegable que fija el filtro compartido.
     */
    public function shortcode_selector( $atts ): string {
        $b = $this->base( (array) $atts, [ 'etiqueta' => 'Dependencia:', 'todas' => '— Todas —' ] );
        $this->enqueue_frontend();

        $config             = $b['config'];
        $config['etiqueta'] = sanitize_text_field( $b['atts']['etiqueta'] );
        $config['todas']    = sanitize_text_field( $b['atts']['todas'] );

        return $this->contenedor( 'selector', $config );
    }
}
