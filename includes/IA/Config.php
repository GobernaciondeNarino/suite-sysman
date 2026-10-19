<?php
namespace SysmanSuite\IA;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Ajustes de inteligencia artificial: proveedor para redactar los análisis
 * (Claude u OpenAI) y agente de voz de ElevenLabs para la ciudadanía.
 *
 * Las claves de API pueden definirse como constantes en wp-config.php
 * (recomendado: no quedan en la base de datos ni en las copias de seguridad);
 * si existe la constante, manda sobre la opción guardada desde el panel.
 */
class Config {

    public const PROVEEDORES = [
        'ninguno'   => 'Ninguno (solo redacción automática por reglas)',
        'anthropic' => 'Claude (Anthropic)',
        'openai'    => 'ChatGPT (OpenAI)',
    ];

    public const MODELOS_CLAUDE = [
        'claude-opus-5-5'   => 'Claude Opus 5.5 (máxima calidad)',
        'claude-sonnet-5-5' => 'Claude Sonnet 5.5 (equilibrado)',
        'claude-haiku-5-5'  => 'Claude Haiku 5.5 (rápido y económico)',
    ];

    public const MODELO_CLAUDE_DEFECTO = 'claude-opus-5-5';
    public const MODELO_OPENAI_DEFECTO = 'gpt-5-mini';

    public const ESFUERZOS = [ 'low' => 'Bajo', 'medium' => 'Medio', 'high' => 'Alto' ];

    /** Constante de wp-config.php => opción, por clave secreta. */
    public const CLAVES = [
        'anthropic'  => [ 'SYSMAN_ANTHROPIC_API_KEY', 'sysman_ia_anthropic_key' ],
        'openai'     => [ 'SYSMAN_OPENAI_API_KEY', 'sysman_ia_openai_key' ],
        'elevenlabs' => [ 'SYSMAN_ELEVENLABS_API_KEY', 'sysman_elevenlabs_api_key' ],
    ];

    public const WIDGET_JS_DEFECTO = 'https://unpkg.com/@elevenlabs/convai-widget-embed';

    public static function proveedor(): string {
        $p = (string) get_option( 'sysman_ia_proveedor', 'ninguno' );
        return isset( self::PROVEEDORES[ $p ] ) ? $p : 'ninguno';
    }

    /** Si hay un proveedor elegido y con clave. */
    public static function activo(): bool {
        $p = self::proveedor();
        return 'ninguno' !== $p && '' !== self::clave( $p );
    }

    public static function clave( string $servicio ): string {
        if ( ! isset( self::CLAVES[ $servicio ] ) ) {
            return '';
        }
        [ $constante, $opcion ] = self::CLAVES[ $servicio ];
        if ( defined( $constante ) && '' !== (string) constant( $constante ) ) {
            return trim( (string) constant( $constante ) );
        }
        return trim( (string) get_option( $opcion, '' ) );
    }

    /** Si la clave viene de wp-config.php (el panel no puede cambiarla). */
    public static function clave_en_constante( string $servicio ): bool {
        $constante = self::CLAVES[ $servicio ][0] ?? '';
        return '' !== $constante && defined( $constante ) && '' !== (string) constant( $constante );
    }

    /** «••••1a2b» para mostrar que hay clave sin revelarla. */
    public static function clave_enmascarada( string $servicio ): string {
        $clave = self::clave( $servicio );
        return '' === $clave ? '' : '••••' . substr( $clave, -4 );
    }

    public static function modelo( string $proveedor ): string {
        if ( 'openai' === $proveedor ) {
            return self::validar_modelo_openai( (string) get_option( 'sysman_ia_openai_modelo', self::MODELO_OPENAI_DEFECTO ) );
        }
        $m = (string) get_option( 'sysman_ia_anthropic_modelo', self::MODELO_CLAUDE_DEFECTO );
        return isset( self::MODELOS_CLAUDE[ $m ] ) ? $m : self::MODELO_CLAUDE_DEFECTO;
    }

    public static function validar_modelo_openai( string $m ): string {
        $m = trim( $m );
        return preg_match( '/^[A-Za-z0-9._:\-]{2,64}$/', $m ) ? $m : self::MODELO_OPENAI_DEFECTO;
    }

    public static function esfuerzo(): string {
        $e = (string) get_option( 'sysman_ia_esfuerzo', 'medium' );
        return isset( self::ESFUERZOS[ $e ] ) ? $e : 'medium';
    }

    /** Horas que se reutiliza un texto redactado mientras los datos no cambien. */
    public static function horas_cache(): int {
        return max( 1, min( 720, (int) get_option( 'sysman_ia_cache_horas', 24 ) ) );
    }

    /** Tope de textos nuevos por hora en todo el sitio (protege el costo). */
    public static function limite_hora(): int {
        return max( 0, min( 1000, (int) get_option( 'sysman_ia_limite_hora', 30 ) ) );
    }

    // ─── ElevenLabs ──────────────────────────────────────────────

    public static function agente_id(): string {
        $id = trim( (string) get_option( 'sysman_elevenlabs_agent_id', '' ) );
        return preg_match( '/^[A-Za-z0-9_\-]{6,80}$/', $id ) ? $id : '';
    }

    /** Agente privado: el navegador recibe una URL firmada, nunca la clave. */
    public static function agente_privado(): bool {
        return (bool) get_option( 'sysman_elevenlabs_privado', false ) && '' !== self::clave( 'elevenlabs' );
    }

    public static function widget_js(): string {
        $url = (string) get_option( 'sysman_elevenlabs_widget_js', self::WIDGET_JS_DEFECTO );
        return 0 === strpos( $url, 'https://' ) ? $url : self::WIDGET_JS_DEFECTO;
    }

    public static function url_privacidad(): string {
        return (string) get_option( 'sysman_ia_url_privacidad', '' );
    }

    /**
     * Secreto compartido con la herramienta de servidor del agente
     * (cabecera X-Sysman-Secreto). Se genera la primera vez que se pide.
     */
    public static function secreto_herramienta(): string {
        $s = (string) get_option( 'sysman_elevenlabs_secreto', '' );
        if ( strlen( $s ) < 32 ) {
            $s = wp_generate_password( 48, false, false );
            update_option( 'sysman_elevenlabs_secreto', $s, false );
        }
        return $s;
    }

    /**
     * Sanitizador de los campos de clave: vacío conserva la clave guardada
     * (el formulario nunca la muestra), «borrar» la elimina.
     */
    public static function sanitizar_clave( string $opcion ): callable {
        return static function ( $valor ) use ( $opcion ) {
            $valor = trim( (string) $valor );
            if ( '' === $valor ) {
                return (string) get_option( $opcion, '' );
            }
            if ( 'borrar' === strtolower( $valor ) ) {
                return '';
            }
            return preg_replace( '/[^A-Za-z0-9_\-\.]/', '', $valor );
        };
    }
}
