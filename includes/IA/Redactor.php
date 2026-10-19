<?php
namespace SysmanSuite\IA;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Redacta con IA los análisis de los módulos Gastos e Ingresos.
 *
 * El modelo no consulta la base de datos ni calcula nada: recibe el párrafo
 * que ya produce Presupuesto\Analysis (cifras verificadas) y los datos de la
 * vista, y solo mejora la redacción. Antes de publicar se comprueba que cada
 * importe y porcentaje del texto esté en esos datos; si aparece una cifra que
 * no estaba, o si el proveedor falla, se publica el texto por reglas.
 */
class Redactor {

    private const PREFIJO_CACHE = 'sysman_ia_txt_';

    /** Minutos que se espera tras un fallo antes de volver a llamar al proveedor. */
    private const MINUTOS_TRAS_FALLO = 10;

    private ?Proveedor $proveedor;

    public function __construct( ?Proveedor $proveedor = null ) {
        $this->proveedor = $proveedor ?? self::proveedor_configurado();
    }

    public static function proveedor_configurado(): ?Proveedor {
        if ( ! Config::activo() ) {
            return null;
        }
        $p = Config::proveedor();
        return 'openai' === $p
            ? new OpenAICliente( Config::clave( 'openai' ), Config::modelo( 'openai' ) )
            : new ClaudeCliente( Config::clave( 'anthropic' ), Config::modelo( 'anthropic' ), Config::esfuerzo() );
    }

    /**
     * @param array $analisis Resultado de Presupuesto\Analysis::generar().
     * @param array $hechos   Datos de la vista: tipo, vista, modulo, periodo,
     *                        campo, total y filas [{nombre, valor, participacion|porcentaje}].
     * @return array El análisis con `parrafos` redactados y `fuente` = ia|reglas.
     */
    public function redactar( array $analisis, array $hechos ): array {
        $base = $analisis + [ 'parrafos' => [] ];
        $base['fuente'] = 'reglas';

        $texto_base = trim( implode( ' ', (array) $base['parrafos'] ) );
        if ( null === $this->proveedor || '' === $texto_base || empty( $hechos['filas'] ) ) {
            return $base;
        }

        [ $sistema, $usuario ] = self::prompt( $texto_base, $hechos );

        $clave = self::PREFIJO_CACHE . md5( wp_json_encode( [
            $this->proveedor->nombre(), $this->proveedor->modelo(), Config::esfuerzo(), $sistema, $usuario,
        ] ) );

        $cache = get_transient( $clave );
        if ( is_array( $cache ) ) {
            return 'ia' === ( $cache['fuente'] ?? '' ) ? array_merge( $base, $cache ) : $base;
        }

        // Una sola generación a la vez por texto, y un tope por hora en el sitio.
        if ( get_transient( $clave . '_l' ) || ! self::consumir_cupo() ) {
            return $base;
        }
        set_transient( $clave . '_l', 1, 2 * MINUTE_IN_SECONDS );

        $res = $this->proveedor->generar( $sistema, $usuario );
        if ( ! is_wp_error( $res ) ) {
            $res = self::validar( $res['texto'], $texto_base . ' ' . wp_json_encode( $hechos, JSON_UNESCAPED_UNICODE ) );
        }

        delete_transient( $clave . '_l' );

        if ( is_wp_error( $res ) ) {
            self::log( 'Análisis con IA descartado, se usa el texto por reglas: ' . $res->get_error_message() );
            set_transient( $clave, [ 'fuente' => 'reglas' ], self::MINUTOS_TRAS_FALLO * MINUTE_IN_SECONDS );
            return $base;
        }

        $guardar = [
            'parrafos' => [ $res ],
            'fuente'   => 'ia',
            'modelo'   => $this->proveedor->modelo(),
        ];
        set_transient( $clave, $guardar, Config::horas_cache() * HOUR_IN_SECONDS );

        return array_merge( $base, $guardar );
    }

    /**
     * Instrucciones y datos para el modelo.
     *
     * @return array{0:string, 1:string}
     */
    public static function prompt( string $texto_base, array $hechos ): array {
        $tipo   = (string) ( $hechos['tipo'] ?? 'descripcion' );
        $avance = 'avance' === ( $hechos['vista'] ?? '' );

        $enfoque = [
            'descripcion'  => 'Describe qué muestran las cifras: el total, cómo se reparte y quién concentra la mayor parte.',
            'cualitativo'  => 'Interpreta en lenguaje sencillo qué significa la distribución para la ciudadanía (concentración, equilibrio, diferencias notables), sin emitir juicios sobre la gestión ni atribuir causas que los datos no muestran.',
            'cuantitativo' => 'Presenta las cifras estadísticas más relevantes de los datos y explica qué indican, con precisión y sin tecnicismos innecesarios.',
        ][ $tipo ] ?? '';

        $sistema = implode( "\n", array_filter( [
            'Redactas textos para el portal de transparencia presupuestal de la Gobernación de Nariño (Colombia). Los lee la ciudadanía, no especialistas: el tono es institucional, cercano y claro.',
            'Escribe un único párrafo en español, de entre 60 y 150 palabras, en tercera persona institucional. Sin títulos, listas, viñetas, markdown, emojis ni comillas alrededor del texto.',
            'Usa únicamente la información de <texto_base> y <datos>. No agregues cifras, fechas, nombres, causas, comparaciones con otros años ni recomendaciones que no estén allí.',
            'Copia cada importe exactamente como aparece en los datos (con el signo $ y los puntos de miles) y cada porcentaje con la misma cifra. No redondees, no conviertas a millones ni calcules cifras nuevas.',
            $avance ? 'Los porcentajes son de avance (ejecución o recaudo frente a lo presupuestado). El porcentaje global ya viene ponderado: nunca lo describas como un promedio de los porcentajes, y no hables de «total» ni de «concentración» sobre los porcentajes.' : '',
            $enfoque,
            'Responde solo con el párrafo.',
        ] ) );

        $usuario = "<texto_base>\n{$texto_base}\n</texto_base>\n\n<datos>\n"
            . wp_json_encode( $hechos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT )
            . "\n</datos>\n\nRedacta el párrafo.";

        return [ $sistema, $usuario ];
    }

    /**
     * Limpia el texto del modelo y comprueba que no traiga cifras ajenas.
     *
     * @return string|\WP_Error
     */
    public static function validar( string $texto, string $fuente ) {
        $texto = wp_strip_all_tags( $texto );
        $texto = preg_replace( '/^\s*(#+|[-*•]|\d+\.)\s+/mu', '', $texto );
        $texto = str_replace( [ '**', '__', '`' ], '', $texto );
        $texto = trim( preg_replace( '/\s+/u', ' ', $texto ), " \t\n\r\0\x0B\"“”«»" );

        $largo = mb_strlen( $texto );
        if ( $largo < 80 || $largo > 2000 ) {
            return new \WP_Error( 'ia_largo', "Longitud fuera de rango ({$largo} caracteres)." );
        }

        $permitidas = array_flip( self::cifras( $fuente ) );
        foreach ( self::cifras( $texto ) as $cifra ) {
            if ( ! isset( $permitidas[ $cifra ] ) ) {
                return new \WP_Error( 'ia_cifra', "El texto trae una cifra que no está en los datos: {$cifra}." );
            }
        }

        return $texto;
    }

    /**
     * Importes ($1.234.567) y porcentajes (53,7 %) de un texto, normalizados.
     *
     * @return string[]
     */
    public static function cifras( string $texto ): array {
        preg_match_all( '/\$\s?\d[\d.]*(?:,\d+)?|\d+(?:[.,]\d+)?\s?%/u', $texto, $m );
        return array_values( array_unique( array_map(
            static fn( $c ) => rtrim( preg_replace( '/\s+/u', '', $c ), '.' ),
            $m[0]
        ) ) );
    }

    /** Cuenta un texto nuevo contra el tope horario. */
    private static function consumir_cupo(): bool {
        $limite = Config::limite_hora();
        if ( $limite <= 0 ) {
            return false;
        }
        $clave = 'sysman_ia_cupo_' . gmdate( 'YmdH' );
        $usado = (int) get_transient( $clave );
        if ( $usado >= $limite ) {
            return false;
        }
        set_transient( $clave, $usado + 1, HOUR_IN_SECONDS );
        return true;
    }

    /** Borra los textos redactados (al importar datos nuevos o cambiar de proveedor). */
    public static function limpiar_cache(): void {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_sysman\\_ia\\_txt\\_%' "
            . "OR option_name LIKE '\\_transient\\_timeout\\_sysman\\_ia\\_txt\\_%'"
        );
    }

    private static function log( string $mensaje ): void {
        if ( class_exists( '\SysmanSuite\Logger' ) ) {
            ( new \SysmanSuite\Logger() )->log( $mensaje, 'warning' );
        }
    }
}
