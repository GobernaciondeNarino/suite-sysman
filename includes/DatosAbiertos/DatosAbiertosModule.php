<?php
namespace SysmanSuite\DatosAbiertos;

use SysmanSuite\Ejecucion\Repository;

if ( ! defined( 'ABSPATH' ) ) exit;

class DatosAbiertosModule {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function boot(): void {
        add_action( 'admin_menu', [ $this, 'admin_menu' ], 25 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        add_action( 'wp_ajax_gn_ejecucion_export', [ $this, 'ajax_ejecucion_export' ] );
        add_action( 'wp_ajax_nopriv_gn_ejecucion_export', [ $this, 'ajax_ejecucion_export' ] );

        add_action( 'wp_ajax_gn_reporte_dis_export', [ $this, 'ajax_reporte_dis_export' ] );
        add_action( 'wp_ajax_nopriv_gn_reporte_dis_export', [ $this, 'ajax_reporte_dis_export' ] );

        add_shortcode( 'gn_ejecucion_export', [ $this, 'shortcode_ejecucion_export' ] );
        add_shortcode( 'gn_reporte_dis', [ $this, 'shortcode_reporte_dis' ] );
    }

    public function admin_menu(): void {
        add_submenu_page(
            'sysman-suite',
            __( 'Datos Abiertos', 'sysman-suite' ),
            __( 'Datos Abiertos', 'sysman-suite' ),
            'manage_options',
            'sysman-datos-abiertos',
            [ $this, 'render_page' ]
        );
    }

    public function render_page(): void {
        include SYSMAN_SUITE_PATH . 'templates/admin/datos-abiertos/datos-abiertos.php';
    }

    public function enqueue_assets( string $hook ): void {
        if ( 'sysman-suite_page_sysman-datos-abiertos' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'sysman-admin',
            SYSMAN_SUITE_URL . 'assets/css/admin.css',
            [],
            SYSMAN_SUITE_VERSION
        );
    }

    // ─── Frontend helpers ────────────────────────────────────────

    private function enqueue_frontend(): void {
        wp_enqueue_style(
            'gn-datos-abiertos',
            SYSMAN_SUITE_URL . 'assets/css/datos-abiertos.css',
            [],
            filemtime( SYSMAN_SUITE_PATH . 'assets/css/datos-abiertos.css' )
        );
        wp_enqueue_script(
            'gn-datos-abiertos',
            SYSMAN_SUITE_URL . 'assets/js/datos-abiertos.js',
            [],
            filemtime( SYSMAN_SUITE_PATH . 'assets/js/datos-abiertos.js' ),
            true
        );
    }

    // ─── Shortcode: [gn_ejecucion_export id="X"] ────────────────

    public function shortcode_ejecucion_export( $atts ): string {
        $atts    = shortcode_atts( [ 'id' => 0 ], $atts, 'gn_ejecucion_export' );
        $post_id = absint( $atts['id'] );

        if ( ! $post_id ) {
            return '';
        }

        $post = get_post( $post_id );
        if ( ! $post || 'gn_ejecucion' !== $post->post_type ) {
            return '';
        }

        $this->enqueue_frontend();

        $csv_url  = admin_url( 'admin-ajax.php?action=gn_ejecucion_export&id=' . $post_id . '&format=csv' );
        $txt_url  = admin_url( 'admin-ajax.php?action=gn_ejecucion_export&id=' . $post_id . '&format=txt' );
        $json_url = rest_url( 'gn-sisman/v1/ejecucion/' . $post_id . '/export' );

        $dependencia = get_post_meta( $post_id, '_gn_dependencia', true );
        $anio = get_post_meta( $post_id, '_gn_anio', true );
        $mes  = (int) get_post_meta( $post_id, '_gn_mes', true );

        return $this->render_card( [
            'title'    => __( 'Ejecucion Presupuestal', 'sysman-suite' ),
            'meta'     => esc_html( $dependencia . ' — ' . \SysmanSuite\Helpers::month_name( $mes ) . ' ' . $anio ),
            'desc'     => __( 'Datos de ejecucion presupuestal consolidados por rubro: apropiaciones, adiciones, creditos, compromisos, obligaciones y pagos.', 'sysman-suite' ),
            'csv_url'  => $csv_url,
            'txt_url'  => $txt_url,
            'json_url' => $json_url,
            'variant'  => 'ejec',
        ] );
    }

    // ─── Shortcode: [gn_reporte_dis anio="" mes=""] ──────────────

    public function shortcode_reporte_dis( $atts ): string {
        $atts = shortcode_atts( [
            'anio'        => '',
            'mes'         => '',
            'compania'    => '001',
            'dependencia' => '',
        ], $atts, 'gn_reporte_dis' );

        $anio     = absint( $atts['anio'] );
        $mes      = absint( $atts['mes'] );
        $compania = sanitize_text_field( $atts['compania'] );
        $dep      = sanitize_text_field( $atts['dependencia'] );

        if ( ! $anio || ! $mes ) {
            return '<p style="color:#dc2626;">Shortcode <code>[gn_reporte_dis]</code>: atributos <strong>anio</strong> y <strong>mes</strong> son requeridos.</p>';
        }

        $this->enqueue_frontend();

        $base_params = 'anio=' . $anio . '&mes=' . $mes . '&compania=' . rawurlencode( $compania );
        if ( '' !== $dep ) {
            $base_params .= '&dependencia=' . rawurlencode( $dep );
        }

        $csv_url  = admin_url( 'admin-ajax.php?action=gn_reporte_dis_export&format=csv&' . $base_params );
        $txt_url  = admin_url( 'admin-ajax.php?action=gn_reporte_dis_export&format=txt&' . $base_params );
        $json_url = rest_url( 'gn-sisman/v1/reporte/disponibilidades?' . $base_params );

        $subtitle = \SysmanSuite\Helpers::month_name( $mes ) . ' ' . $anio;
        if ( '' !== $dep ) {
            $subtitle = $dep . ' — ' . $subtitle;
        }

        return $this->render_card( [
            'title'    => __( 'Reporte Disponibilidades (DIS)', 'sysman-suite' ),
            'meta'     => esc_html( $subtitle ),
            'desc'     => __( 'Registros de disponibilidades presupuestales con informacion del tercero, rubro, dependencia y documentos asociados.', 'sysman-suite' ),
            'csv_url'  => $csv_url,
            'txt_url'  => $txt_url,
            'json_url' => $json_url,
            'variant'  => 'dis',
        ] );
    }

    // ─── Shared card renderer ────────────────────────────────────

    private function render_card( array $args ): string {
        $html  = '<div class="gn-da-card gn-da-card--' . esc_attr( $args['variant'] ) . '">';
        $html .= '<div class="gn-da-card__header">';
        $html .= '<h3 class="gn-da-card__title">' . esc_html( $args['title'] ) . '</h3>';
        $html .= '<span class="gn-da-card__meta">' . $args['meta'] . '</span>';
        $html .= '</div>';
        $html .= '<div class="gn-da-card__body">';
        $html .= '<p class="gn-da-card__desc">' . esc_html( $args['desc'] ) . '</p>';
        $html .= '<div class="gn-da-card__actions">';
        $html .= '<a href="' . esc_url( $args['csv_url'] ) . '" class="gn-da-card__btn gn-da-card__btn--csv">CSV</a>';
        $html .= '<a href="' . esc_url( $args['txt_url'] ) . '" class="gn-da-card__btn gn-da-card__btn--txt">TXT</a>';
        $html .= '</div>';
        $html .= '<div class="gn-da-card__api">';
        $html .= '<span class="gn-da-card__api-label">JSON API</span>';
        $html .= '<span class="gn-da-card__api-url">' . esc_url( $args['json_url'] ) . '</span>';
        $html .= '<button type="button" class="gn-da-card__copy" data-copy="' . esc_attr( $args['json_url'] ) . '" title="Copiar URL">&#128203;</button>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="gn-da-card__footer">Datos Abiertos &mdash; Gobernacion de Narino</div>';
        $html .= '</div>';

        return $html;
    }

    // ─── AJAX filter extraction ─────────────────────────────────

    private function extract_ajax_options( array $filter_defs ): array {
        $options = [];

        $filtros = [];
        foreach ( array_keys( $filter_defs ) as $key ) {
            $val = $_GET[ $key ] ?? null;
            if ( null !== $val && '' !== (string) $val ) {
                $filtros[ $key ] = sanitize_text_field( (string) $val );
            }
        }
        if ( ! empty( $filtros ) ) {
            $options['filtros'] = $filtros;
        }

        $buscar = sanitize_text_field( $_GET['buscar'] ?? '' );
        if ( '' !== $buscar ) {
            $options['buscar'] = $buscar;
        }

        return $options;
    }

    // ─── AJAX: Ejecucion export (CSV / TXT) ─────────────────────

    public function ajax_ejecucion_export(): void {
        $this->enforce_rate_limit();
        $post_id = absint( $_GET['id'] ?? 0 );
        $format  = sanitize_text_field( $_GET['format'] ?? 'csv' );

        $post = $post_id ? get_post( $post_id ) : null;
        if ( ! $post || 'gn_ejecucion' !== $post->post_type ) {
            wp_die( 'Seguimiento no encontrado.', 'Error', [ 'response' => 404 ] );
        }

        $options = $this->extract_ajax_options( Repository::EXPORT_FILTERS );
        $repo    = Repository::instance();
        $result  = $repo->get_export_data( $post_id, $options );
        $data    = isset( $result['data'] ) ? $result['data'] : $result;

        $filename = sanitize_file_name( $post->post_title ) . '_' . gmdate( 'Y-m-d' );

        $col_headers = [
            'Codigo', 'Nombre', 'Destino', 'Naturaleza', 'Sector', 'Programa',
            'Subprograma', 'Codigo Producto', 'Codigo BPIN',
            'Aprob. Inicial', 'Adicion', 'Reduccion', 'Credito', 'Contracredito',
            'Aprob. Vigente', 'Disponibilidades', 'Saldo Disponible', 'Compromisos',
            'Disp. Abiertas', 'Obligacion', 'Pagos', 'Oblig. por Pagar',
        ];

        $keys = [
            'codigo', 'nombre', 'destino', 'naturaleza', 'sector', 'programa',
            'subprograma', 'codigoproducto', 'codigobpin',
            'apropiacioninicial', 'adicion', 'reduccion', 'credito', 'contracredito',
            'apropiacionvigente', 'disponibilidades', 'saldodisponible', 'compromisos',
            'disponibilidadesabiertas', 'obligacion', 'pagos', 'obligacionesporpagar',
        ];

        $this->stream_download( $data, $col_headers, $keys, $filename, $format );
    }

    // ─── AJAX: Reporte DIS export (CSV / TXT) ───────────────────

    public function ajax_reporte_dis_export(): void {
        $this->enforce_rate_limit();
        $anio        = absint( $_GET['anio'] ?? 0 );
        $mes         = absint( $_GET['mes'] ?? 0 );
        $compania    = sanitize_text_field( $_GET['compania'] ?? '001' );
        $dependencia = sanitize_text_field( $_GET['dependencia'] ?? '' );
        $format      = sanitize_text_field( $_GET['format'] ?? 'csv' );

        if ( ! $anio || ! $mes ) {
            wp_die( 'Parametros anio y mes son requeridos.', 'Error', [ 'response' => 400 ] );
        }

        $options = $this->extract_ajax_options( Repository::DIS_FILTERS );
        $repo    = Repository::instance();
        $result  = $repo->get_disponibilidades_report( $anio, $mes, $compania, $dependencia, $options );
        $data    = isset( $result['data'] ) ? $result['data'] : $result;

        $filename = 'DIS_' . $compania . '_' . $anio . '_' . $mes . '_' . gmdate( 'Y-m-d' );

        $col_headers = [
            'Numero', 'Tercero', 'Nombre Tercero', 'Rubro', 'Nombre Rubro', 'Descripcion',
            'Nro Documento', 'Valor Debito', 'Valor Credito', 'Saldo por Ejecutar',
            'Cpte Afectado', 'Fecha', 'Anio', 'Mes',
            'Dependencia', 'Nombre Dependencia', 'Destino', 'Naturaleza',
            'Sector', 'Programa', 'Subprograma', 'Codigo Producto', 'Codigo BPIN',
        ];

        $keys = [
            'numero', 'tercero', 'nombretercero', 'rubro', 'nombrerubro', 'descripcion',
            'nrodocumento', 'valordebito', 'valorcredito', 'saldoporejecutaresp',
            'cmpteafectado', 'fecha', 'anio', 'mes',
            'dependencia', 'nombredependencia', 'destino', 'naturaleza',
            'sector', 'programa', 'subprograma', 'codigoproducto', 'codigobpin',
        ];

        $this->stream_download( $data, $col_headers, $keys, $filename, $format );
    }

    // ─── Download streamer ───────────────────────────────────────

    /**
     * Throttle unauthenticated export downloads per IP (open data stays
     * public by design; this only limits abuse of the heavy JOIN queries).
     */
    private function enforce_rate_limit(): void {
        if ( ! \SysmanSuite\Helpers::rate_limit_check( 'da_export', 20 ) ) {
            wp_die(
                esc_html__( 'Demasiadas solicitudes. Intente de nuevo en un minuto.', 'sysman-suite' ),
                esc_html__( 'Límite de solicitudes', 'sysman-suite' ),
                [ 'response' => 429 ]
            );
        }
    }

    private function stream_download( array $data, array $col_headers, array $keys, string $filename, string $format ): void {
        @set_time_limit( 300 );

        // Sanitize filename for the Content-Disposition header (no quotes/CRLF).
        $filename = sanitize_file_name( $filename );
        $format   = ( 'txt' === $format ) ? 'txt' : 'csv';

        if ( 'txt' === $format ) {
            \SysmanSuite\Helpers::download_headers( 'text/plain; charset=utf-8', $filename . '.txt' );

            echo implode( "\t", array_map( [ $this, 'sanitize_txt_cell' ], $col_headers ) ) . "\n";
            foreach ( $data as $row ) {
                $values = [];
                foreach ( $keys as $k ) {
                    $values[] = $this->sanitize_txt_cell( $row[ $k ] ?? '' );
                }
                echo implode( "\t", $values ) . "\n";
            }
        } else {
            \SysmanSuite\Helpers::download_headers( 'text/csv; charset=utf-8', $filename . '.csv' );

            $output = fopen( 'php://output', 'w' );
            fprintf( $output, "\xEF\xBB\xBF" );
            fputcsv( $output, array_map( [ $this, 'sanitize_csv_cell' ], $col_headers ), ';' );
            foreach ( $data as $row ) {
                $values = [];
                foreach ( $keys as $k ) {
                    $values[] = $this->sanitize_csv_cell( $row[ $k ] ?? '' );
                }
                fputcsv( $output, $values, ';' );
            }
            fclose( $output );
        }

        exit;
    }

    /**
     * Neutralize CSV formula injection: prefix values that a spreadsheet
     * could interpret as a formula (=, +, -, @, tab, CR) with an apostrophe.
     */
    private function sanitize_csv_cell( $value ): string {
        $value = (string) $value;
        if ( '' !== $value && in_array( $value[0], [ '=', '+', '-', '@', "\t", "\r" ], true ) ) {
            $value = "'" . $value;
        }
        return $value;
    }

    /**
     * For tab-delimited TXT: collapse tabs/newlines so they cannot break the
     * row/column structure, then apply the formula-injection guard.
     */
    private function sanitize_txt_cell( $value ): string {
        $value = str_replace( [ "\t", "\r\n", "\r", "\n" ], ' ', (string) $value );
        return $this->sanitize_csv_cell( $value );
    }
}
