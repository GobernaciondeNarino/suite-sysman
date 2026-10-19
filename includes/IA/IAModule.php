<?php
namespace SysmanSuite\IA;

use SysmanSuite\Helpers;
use SysmanSuite\Presupuesto\Analysis;
use SysmanSuite\Presupuesto\IngresosRepository;
use SysmanSuite\Presupuesto\Repository;
use SysmanSuite\Presupuesto\RestController as PresupuestoRest;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Inteligencia artificial del plugin:
 *
 *  - Ajustes de Claude / OpenAI para redactar los análisis (ver Redactor).
 *  - Asistente de voz de ElevenLabs para dialogar con la ciudadanía:
 *    shortcode [sysman_asistente], sesión firmada para agentes privados y
 *    una herramienta de servidor que el agente consulta para responder con
 *    las cifras de SYSMAN. Solo expone presupuesto agregado: nunca datos de
 *    personal de nómina ni de terceros.
 */
class IAModule {

    private const NS = 'sysman-suite/v1';

    public const TEMAS = [
        'resumen'         => 'Resumen del presupuesto de gastos e ingresos',
        'gastos'          => 'Gastos por dependencia',
        'dependencia'     => 'Gastos de una dependencia (requiere nombre)',
        'avance_gastos'   => 'Porcentaje de ejecución de los gastos',
        'ingresos'        => 'Ingresos por tipo o fuente de recurso',
        'avance_ingresos' => 'Porcentaje de recaudo de los ingresos',
    ];

    private const VARIANTES = [ 'compact', 'expanded', 'full' ];

    private static ?self $instance = null;

    public static function instance(): self {
        return self::$instance ??= new self();
    }

    public function boot(): void {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_menu', [ $this, 'admin_menu' ], 40 );
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
        add_action( 'wp_ajax_sysman_ia_probar', [ $this, 'ajax_probar' ] );
        add_shortcode( 'sysman_asistente', [ $this, 'shortcode' ] );

        // Cambiar de proveedor o de modelo invalida los textos ya redactados.
        foreach ( [ 'sysman_ia_proveedor', 'sysman_ia_anthropic_modelo', 'sysman_ia_openai_modelo' ] as $opcion ) {
            add_action( "update_option_{$opcion}", [ Redactor::class, 'limpiar_cache' ] );
        }
    }

    // ─── Ajustes ─────────────────────────────────────────────────

    public function register_settings(): void {
        $g = 'sysman_ia';

        register_setting( $g, 'sysman_ia_proveedor', [
            'type'              => 'string',
            'default'           => 'ninguno',
            'sanitize_callback' => static fn( $v ) => isset( Config::PROVEEDORES[ $v ] ) ? $v : 'ninguno',
        ] );
        register_setting( $g, 'sysman_ia_anthropic_key', [
            'type'              => 'string',
            'sanitize_callback' => Config::sanitizar_clave( 'sysman_ia_anthropic_key' ),
        ] );
        register_setting( $g, 'sysman_ia_anthropic_modelo', [
            'type'              => 'string',
            'default'           => Config::MODELO_CLAUDE_DEFECTO,
            'sanitize_callback' => static fn( $v ) => isset( Config::MODELOS_CLAUDE[ $v ] ) ? $v : Config::MODELO_CLAUDE_DEFECTO,
        ] );
        register_setting( $g, 'sysman_ia_esfuerzo', [
            'type'              => 'string',
            'default'           => 'medium',
            'sanitize_callback' => static fn( $v ) => isset( Config::ESFUERZOS[ $v ] ) ? $v : 'medium',
        ] );
        register_setting( $g, 'sysman_ia_openai_key', [
            'type'              => 'string',
            'sanitize_callback' => Config::sanitizar_clave( 'sysman_ia_openai_key' ),
        ] );
        register_setting( $g, 'sysman_ia_openai_modelo', [
            'type'              => 'string',
            'default'           => Config::MODELO_OPENAI_DEFECTO,
            'sanitize_callback' => [ Config::class, 'validar_modelo_openai' ],
        ] );
        register_setting( $g, 'sysman_ia_cache_horas', [
            'type'              => 'integer',
            'default'           => 24,
            'sanitize_callback' => static fn( $v ) => max( 1, min( 720, absint( $v ) ) ),
        ] );
        register_setting( $g, 'sysman_ia_limite_hora', [
            'type'              => 'integer',
            'default'           => 30,
            'sanitize_callback' => static fn( $v ) => min( 1000, absint( $v ) ),
        ] );
        register_setting( $g, 'sysman_elevenlabs_agent_id', [
            'type'              => 'string',
            'sanitize_callback' => static fn( $v ) => preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $v ),
        ] );
        register_setting( $g, 'sysman_elevenlabs_privado', [
            'type'              => 'boolean',
            'default'           => false,
            'sanitize_callback' => static fn( $v ) => ! empty( $v ),
        ] );
        register_setting( $g, 'sysman_elevenlabs_api_key', [
            'type'              => 'string',
            'sanitize_callback' => Config::sanitizar_clave( 'sysman_elevenlabs_api_key' ),
        ] );
        register_setting( $g, 'sysman_elevenlabs_widget_js', [
            'type'              => 'string',
            'default'           => Config::WIDGET_JS_DEFECTO,
            'sanitize_callback' => static fn( $v ) => 0 === strpos( (string) $v, 'https://' ) ? esc_url_raw( $v ) : Config::WIDGET_JS_DEFECTO,
        ] );
        register_setting( $g, 'sysman_ia_url_privacidad', [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
        ] );
    }

    public function admin_menu(): void {
        add_submenu_page(
            'sysman-suite',
            __( 'Inteligencia artificial', 'sysman-suite' ),
            __( 'Inteligencia artificial', 'sysman-suite' ),
            'manage_options',
            'sysman-ia',
            [ $this, 'render_admin' ]
        );
    }

    public function render_admin(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        wp_enqueue_style( 'sysman-admin', SYSMAN_SUITE_URL . 'assets/css/admin.css', [], SYSMAN_SUITE_VERSION );

        $url_consulta = rest_url( self::NS . '/asistente/consulta' );
        $secreto      = Config::secreto_herramienta();
        $temas        = self::TEMAS;
        $nonce        = wp_create_nonce( 'sysman_ia_probar' );
        include SYSMAN_SUITE_PATH . 'templates/admin/ia-page.php';
    }

    /**
     * AJAX: prueba la conexión con el proveedor configurado redactando un
     * texto de ejemplo. No guarda nada.
     */
    public function ajax_probar(): void {
        check_ajax_referer( 'sysman_ia_probar', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permisos insuficientes.' ] );
        }

        $prov = Redactor::proveedor_configurado();
        if ( null === $prov ) {
            wp_send_json_error( [ 'message' => 'Elija un proveedor y guarde su clave de API primero.' ] );
        }

        $res = $prov->generar(
            'Respondes en español con una sola frase.',
            'Confirma en una frase que la conexión con el portal de transparencia presupuestal de la Gobernación de Nariño funciona.'
        );

        if ( is_wp_error( $res ) ) {
            wp_send_json_error( [ 'message' => $res->get_error_message() ] );
        }
        wp_send_json_success( [
            'proveedor' => $prov->nombre(),
            'modelo'    => $res['modelo'] ?: $prov->modelo(),
            'texto'     => wp_strip_all_tags( $res['texto'] ),
        ] );
    }

    // ─── Shortcode del asistente de voz ──────────────────────────

    public function shortcode( $atts ): string {
        $a = shortcode_atts( [
            'agente'     => '',
            'variante'   => '',
            'invitacion' => '¿Tiene preguntas sobre el presupuesto de Nariño?',
            'iniciar'    => 'Hablar con el asistente',
            'terminar'   => 'Terminar',
            'escuchando' => 'Escuchando…',
            'hablando'   => 'Respondiendo…',
            'color1'     => '',
            'color2'     => '',
            'avatar'     => '',
            'contexto'   => 'si',
            'aviso'      => 'si',
            'compania'   => '001',
        ], (array) $atts, 'sysman_asistente' );

        $agente = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $a['agente'] ) ?: Config::agente_id();
        if ( '' === $agente ) {
            return current_user_can( 'manage_options' )
                ? '<p class="sysman-asistente__error">' . esc_html__( 'Asistente de voz: configure el ID del agente de ElevenLabs en SYSMAN Suite → Inteligencia artificial.', 'sysman-suite' ) . '</p>'
                : '';
        }

        $privado = Config::agente_privado() && '' === (string) $a['agente'];

        $attrs = [
            'action-text'     => $a['invitacion'],
            'start-call-text' => $a['iniciar'],
            'end-call-text'   => $a['terminar'],
            'listening-text'  => $a['escuchando'],
            'speaking-text'   => $a['hablando'],
        ];
        if ( ! $privado ) {
            $attrs['agent-id'] = $agente;
        }
        if ( in_array( $a['variante'], self::VARIANTES, true ) ) {
            $attrs['variant'] = $a['variante'];
        }
        foreach ( [ 'color1' => 'avatar-orb-color-1', 'color2' => 'avatar-orb-color-2' ] as $k => $attr ) {
            $color = sanitize_hex_color( (string) $a[ $k ] );
            if ( $color ) {
                $attrs[ $attr ] = $color;
            }
        }
        if ( '' !== $a['avatar'] ) {
            $attrs['avatar-image-url'] = esc_url_raw( $a['avatar'] );
        }
        if ( in_array( strtolower( (string) $a['contexto'] ), [ 'si', 'sí', '1', 'yes' ], true ) ) {
            $attrs['dynamic-variables'] = wp_json_encode(
                $this->variables_dinamicas( sanitize_text_field( $a['compania'] ) ?: '001' ),
                JSON_UNESCAPED_UNICODE
            );
        }

        $html_attrs = '';
        foreach ( $attrs as $k => $v ) {
            $html_attrs .= ' ' . $k . '="' . esc_attr( (string) $v ) . '"';
        }

        wp_enqueue_style( 'sysman-presupuesto', SYSMAN_SUITE_URL . 'assets/css/presupuesto.css', [], SYSMAN_SUITE_VERSION );

        if ( $privado ) {
            // La URL firmada caduca: se pide al cargar la página y solo
            // entonces se carga el widget.
            wp_enqueue_script( 'sysman-asistente', SYSMAN_SUITE_URL . 'assets/js/asistente.js', [], SYSMAN_SUITE_VERSION, true );
            wp_localize_script( 'sysman-asistente', 'sysmanAsistente', [
                'sesionUrl' => esc_url_raw( rest_url( self::NS . '/asistente/sesion' ) ),
                'widgetJs'  => Config::widget_js(),
            ] );
        } else {
            // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- script de terceros con su propio versionado.
            wp_enqueue_script( 'elevenlabs-convai', Config::widget_js(), [], null, [ 'in_footer' => true, 'strategy' => 'async' ] );
        }

        $aviso = '';
        if ( 'no' !== strtolower( (string) $a['aviso'] ) ) {
            $url   = Config::url_privacidad();
            $aviso = '<p class="sysman-asistente__aviso">'
                . esc_html__( 'El asistente responde con las cifras oficiales del sistema SYSMAN. Su voz y sus mensajes son procesados por ElevenLabs para poder responderle: no comparta datos personales. Tratamiento de datos conforme a la Ley 1581 de 2012.', 'sysman-suite' )
                . ( '' !== $url ? ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Política de tratamiento de datos', 'sysman-suite' ) . '</a>' : '' )
                . '</p>';
        }

        return '<div class="sysman-asistente"' . ( $privado ? ' data-sysman-asistente-privado="1"' : '' ) . '>'
            . '<elevenlabs-convai' . $html_attrs . '></elevenlabs-convai>'
            . $aviso
            . '</div>';
    }

    /**
     * Contexto que el agente recibe al iniciar la conversación. En el prompt
     * del agente se usan como {{entidad}}, {{periodo}}, {{resumen_gastos}} y
     * {{resumen_ingresos}}.
     */
    public function variables_dinamicas( string $compania ): array {
        $gastos  = $this->consulta( 'resumen', '', $compania );
        $periodo  = (string) ( $gastos['periodo'] ?? '' );

        return [
            'entidad'          => 'Gobernación de Nariño',
            'periodo'          => $periodo,
            'resumen_gastos'   => (string) ( $gastos['gastos'] ?? '' ),
            'resumen_ingresos' => (string) ( $gastos['ingresos'] ?? '' ),
        ];
    }

    // ─── REST ────────────────────────────────────────────────────

    public function register_routes(): void {
        register_rest_route( self::NS, '/asistente/sesion', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'rest_sesion' ],
            'permission_callback' => static fn() => Helpers::rate_limit_check( 'asistente_sesion', 10 )
                ? true
                : new \WP_Error( 'rest_rate_limited', __( 'Demasiadas solicitudes. Intente de nuevo en un minuto.', 'sysman-suite' ), [ 'status' => 429 ] ),
        ] );

        register_rest_route( self::NS, '/asistente/consulta', [
            'methods'             => [ 'GET', 'POST' ],
            'callback'            => [ $this, 'rest_consulta' ],
            'permission_callback' => [ $this, 'permiso_herramienta' ],
            'args'                => [
                'tema'     => [ 'default' => 'resumen', 'sanitize_callback' => 'sanitize_key' ],
                'nombre'   => [ 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
                'compania' => [ 'default' => '001', 'sanitize_callback' => 'sanitize_text_field' ],
                'anio'     => [ 'default' => 0, 'sanitize_callback' => 'absint' ],
                'mes'      => [ 'default' => 0, 'sanitize_callback' => 'absint' ],
            ],
        ] );
    }

    /**
     * La herramienta de servidor solo atiende al agente: exige el secreto
     * compartido en la cabecera X-Sysman-Secreto.
     *
     * @return true|\WP_Error
     */
    public function permiso_herramienta( \WP_REST_Request $r ) {
        $enviado = (string) $r->get_header( 'x_sysman_secreto' );
        if ( '' === $enviado || ! hash_equals( Config::secreto_herramienta(), $enviado ) ) {
            return new \WP_Error( 'rest_forbidden', 'Secreto de la herramienta inválido.', [ 'status' => 401 ] );
        }
        if ( ! Helpers::rate_limit_check( 'asistente_consulta', 120 ) ) {
            return new \WP_Error( 'rest_rate_limited', 'Demasiadas consultas.', [ 'status' => 429 ] );
        }
        return true;
    }

    public function rest_sesion(): \WP_REST_Response {
        $agente = Config::agente_id();
        if ( '' === $agente ) {
            return new \WP_REST_Response( [ 'error' => 'Asistente no configurado.' ], 404 );
        }
        if ( ! Config::agente_privado() ) {
            return new \WP_REST_Response( [ 'agent_id' => $agente ] );
        }

        $resp = wp_remote_get(
            'https://api.elevenlabs.io/v1/convai/conversation/get-signed-url?agent_id=' . rawurlencode( $agente ),
            [ 'timeout' => 15, 'headers' => [ 'xi-api-key' => Config::clave( 'elevenlabs' ) ] ]
        );

        $json = is_wp_error( $resp ) ? null : json_decode( (string) wp_remote_retrieve_body( $resp ), true );
        $url  = is_array( $json ) ? (string) ( $json['signed_url'] ?? '' ) : '';

        if ( 0 !== strpos( $url, 'wss://' ) && 0 !== strpos( $url, 'https://' ) ) {
            $detalle = is_wp_error( $resp ) ? $resp->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $resp );
            ( new \SysmanSuite\Logger() )->log( "ElevenLabs: no se obtuvo la URL firmada ({$detalle}).", 'warning' );
            return new \WP_REST_Response( [ 'error' => 'No se pudo iniciar el asistente.' ], 502 );
        }

        $out = new \WP_REST_Response( [ 'signed_url' => $url ] );
        $out->header( 'Cache-Control', 'no-store' );
        return $out;
    }

    public function rest_consulta( \WP_REST_Request $r ): \WP_REST_Response {
        $tema = (string) $r->get_param( 'tema' );
        if ( ! isset( self::TEMAS[ $tema ] ) ) {
            return new \WP_REST_Response( [
                'error'         => 'Tema no reconocido.',
                'temas_validos' => array_keys( self::TEMAS ),
            ], 400 );
        }

        return new \WP_REST_Response( $this->consulta(
            $tema,
            (string) $r->get_param( 'nombre' ),
            (string) $r->get_param( 'compania' ) ?: '001',
            (int) $r->get_param( 'anio' ),
            (int) $r->get_param( 'mes' )
        ) );
    }

    /**
     * Respuesta en lenguaje llano para el agente, con las cifras listas para
     * leer en voz alta. Usa la misma redacción por reglas que los shortcodes:
     * rápida, sin costo y sin riesgo de cifras inventadas.
     */
    public function consulta( string $tema, string $nombre, string $compania, int $anio = 0, int $mes = 0 ): array {
        $rest = new PresupuestoRest();
        $base = [ 'compania' => $compania, 'anio' => $anio, 'mes' => $mes ];

        $texto = static fn( array $analisis ) => trim( implode( ' ', (array) ( $analisis['parrafos'] ?? [] ) ) );

        $periodo = static fn( array $ctx ) => $ctx['mes'] > 0
            ? mb_strtolower( Helpers::month_name( (int) $ctx['mes'] ) ) . ' de ' . (int) $ctx['anio']
            : '';

        $ingresos_params = static function () use ( $base ): array {
            $repo = IngresosRepository::instance();
            $ctx  = $repo->contexto( $base );
            return $base + [
                'modulo'    => 'ingresos',
                'dimension' => $repo->dimension_util( $ctx, 'tiporecurso', IngresosRepository::LONGITUD_RUBRO ),
            ];
        };

        switch ( $tema ) {
            case 'gastos':
            case 'avance_gastos':
                [ $ctx, $an, $filas ] = $rest->analisis_interno( $base + [ 'vista' => 'avance_gastos' === $tema ? 'avance' : 'dimensiones' ] );
                return [
                    'tema'      => $tema,
                    'periodo'   => $periodo( $ctx ),
                    'respuesta' => $texto( $an ),
                    'datos'     => self::filas_legibles( $filas, 'avance_gastos' === $tema ),
                ];

            case 'ingresos':
            case 'avance_ingresos':
                [ $ctx, $an, $filas ] = $rest->analisis_interno( $ingresos_params() + [ 'vista' => 'avance_ingresos' === $tema ? 'avance' : 'dimensiones' ] );
                return [
                    'tema'      => $tema,
                    'periodo'   => $periodo( $ctx ),
                    'respuesta' => $texto( $an ),
                    'datos'     => self::filas_legibles( $filas, 'avance_ingresos' === $tema ),
                ];

            case 'dependencia':
                $ctx   = Repository::instance()->contexto( $base );
                $deps  = array_column( Repository::instance()->dependencias( $ctx ), 'label' );
                $match = self::buscar_nombre( $nombre, $deps );
                if ( null === $match ) {
                    return [
                        'tema'        => $tema,
                        'periodo'     => $periodo( $ctx ),
                        'respuesta'   => '' === trim( $nombre )
                            ? 'Indique el nombre de la dependencia que desea consultar.'
                            : 'No se encontró una dependencia con ese nombre en el presupuesto de ' . $periodo( $ctx ) . '.',
                        'dependencias_disponibles' => $deps,
                    ];
                }
                [ $ctx, $an ] = $rest->analisis_interno( $base + [ 'vista' => 'detalle', 'valor' => $match ] );
                $tot = Repository::instance()->totales( $ctx, $match );
                $ap  = (float) ( $tot['apropiacionvigente'] ?? 0 );
                $co  = (float) ( $tot['compromisos'] ?? 0 );
                return [
                    'tema'        => $tema,
                    'periodo'     => $periodo( $ctx ),
                    'dependencia' => $match,
                    'respuesta'   => $texto( $an ),
                    'datos'       => [
                        'apropiacion_vigente' => Analysis::moneda( $ap ),
                        'compromisos'         => Analysis::moneda( $co ),
                        'obligaciones'        => Analysis::moneda( (float) ( $tot['obligacion'] ?? 0 ) ),
                        'pagos'               => Analysis::moneda( (float) ( $tot['pagos'] ?? 0 ) ),
                        'porcentaje_ejecucion' => $ap > 0 ? Analysis::porcentaje( $co / $ap ) : 'sin apropiación',
                    ],
                ];

            default: // resumen
                [ $ctx_g, $an_g ] = $rest->analisis_interno( $base );
                [ , $an_i ]       = $rest->analisis_interno( $ingresos_params() );
                return [
                    'tema'     => 'resumen',
                    'periodo'  => $periodo( $ctx_g ),
                    'gastos'   => $texto( $an_g ),
                    'ingresos' => $texto( $an_i ),
                ];
        }
    }

    /**
     * Filas de una vista en texto listo para leer (máximo 12).
     */
    public static function filas_legibles( array $filas, bool $avance ): array {
        $out = [];
        foreach ( array_slice( $filas, 0, 12 ) as $f ) {
            $out[] = $avance
                ? [
                    'nombre'     => (string) ( $f['label'] ?? '' ),
                    'porcentaje' => null !== ( $f['porcentaje'] ?? null )
                        ? Analysis::porcentaje( (float) $f['porcentaje'] )
                        : 'sin presupuesto',
                    'ejecutado'  => Analysis::moneda( (float) ( $f['ejecutado'] ?? 0 ) ),
                    'base'       => Analysis::moneda( (float) ( $f['base'] ?? 0 ) ),
                ]
                : [
                    'nombre' => (string) ( $f['label'] ?? '' ),
                    'valor'  => Analysis::moneda( (float) ( $f['value'] ?? 0 ) ),
                ];
        }
        return $out;
    }

    /**
     * Busca un nombre dicho de viva voz entre las opciones: sin tildes ni
     * mayúsculas, primero exacto, luego «contiene», luego por palabras.
     */
    public static function buscar_nombre( string $buscado, array $opciones ): ?string {
        $norm = static fn( string $t ) => trim( preg_replace( '/\s+/', ' ', strtolower( remove_accents( $t ) ) ) );
        $b    = $norm( $buscado );
        if ( '' === $b ) {
            return null;
        }

        foreach ( $opciones as $o ) {
            if ( $norm( $o ) === $b ) {
                return $o;
            }
        }
        foreach ( $opciones as $o ) {
            if ( str_contains( $norm( $o ), $b ) ) {
                return $o;
            }
        }

        // Por palabras significativas: «secretaría de salud» → «salud».
        $vacias   = [ 'de', 'del', 'la', 'el', 'los', 'las', 'y', 'secretaria', 'oficina', 'departamental', 'despacho' ];
        $palabras = array_diff( explode( ' ', $b ), $vacias );
        if ( empty( $palabras ) ) {
            return null;
        }
        $mejor = null;
        $max   = 0;
        foreach ( $opciones as $o ) {
            $n        = $norm( $o );
            $aciertos = count( array_filter( $palabras, static fn( $p ) => mb_strlen( $p ) > 2 && str_contains( $n, $p ) ) );
            if ( $aciertos > $max ) {
                $max   = $aciertos;
                $mejor = $o;
            }
        }
        return $mejor;
    }
}
