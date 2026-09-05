<?php
/**
 * Standalone test bootstrap: minimal WordPress stubs so the plugin classes
 * under test can load without a WordPress install. Run via tests/run-tests.php.
 */

define( 'ABSPATH', sys_get_temp_dir() . '/' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );

// ─── Fake $wpdb ──────────────────────────────────────────────────
class Fake_Wpdb {
    public string $prefix = 'wp_';
}
$GLOBALS['wpdb'] = new Fake_Wpdb();

// ─── Hook / i18n stubs ───────────────────────────────────────────
function add_action( ...$args ) {}
function add_filter( ...$args ) {}
function add_shortcode( ...$args ) {}
function __( $text, $domain = null ) {
    return $text;
}
function apply_filters( $tag, $value, ...$args ) {
    return $value;
}
function current_user_can( $cap ) {
    return $GLOBALS['__test_user_can'] ?? false;
}

// ─── Transients (in-memory) ──────────────────────────────────────
$GLOBALS['__test_transients'] = [];
function get_transient( $key ) {
    return $GLOBALS['__test_transients'][ $key ] ?? false;
}
function set_transient( $key, $value, $expiration = 0 ) {
    $GLOBALS['__test_transients'][ $key ] = $value;
    return true;
}

// ─── Post meta (in-memory) ───────────────────────────────────────
$GLOBALS['__test_meta'] = [];
function update_post_meta( $post_id, $key, $value ) {
    $GLOBALS['__test_meta'][ $post_id ][ $key ] = $value;
    return true;
}
function delete_post_meta( $post_id, $key ) {
    unset( $GLOBALS['__test_meta'][ $post_id ][ $key ] );
    return true;
}
function get_post_meta( $post_id, $key = '', $single = true ) {
    return $GLOBALS['__test_meta'][ $post_id ][ $key ] ?? '';
}
function wp_verify_nonce( $nonce, $action = -1 ) {
    return 'valid-nonce' === $nonce ? 1 : false;
}

// ─── Sanitizers ──────────────────────────────────────────────────
function sanitize_text_field( $str ) {
    return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $str ) ) );
}
function sanitize_textarea_field( $str ) {
    return trim( strip_tags( (string) $str ) );
}
function absint( $v ) {
    return abs( (int) $v );
}
function sanitize_file_name( $name ) {
    return preg_replace( '/[^A-Za-z0-9._-]/', '-', (string) $name );
}
function wp_unslash( $value ) {
    return is_string( $value ) ? stripslashes( $value ) : $value;
}
function current_time( $format ) {
    return date( $format );
}
function number_format_i18n( $number, $decimals = 0 ) {
    return number_format( (float) $number, (int) $decimals, ',', '.' );
}
function esc_url_raw( $url ) { return $url; }
function rest_url( $path = '' ) { return 'https://ejemplo.test/wp-json/' . ltrim( $path, '/' ); }
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }

// ─── Fake Database with the plugin's table whitelist ─────────────
// (Must be defined before class-visualizer.php references it; the real
// class-database.php is intentionally NOT loaded in unit tests.)
eval( '
namespace SysmanSuite;
class Database {
    public function get_available_tables(): array {
        $p = $GLOBALS["wpdb"]->prefix;
        return [
            $p . "sysman_ejecucion_gastos"   => "Gastos",
            $p . "sysman_auxiliar_cuentas"   => "Auxiliar",
            $p . "sysman_plan_presupuestal"  => "Plan",
            $p . "sysman_personal_nomina"    => "Personal",
            $p . "sysman_ejecucion_ingresos" => "Ingresos",
        ];
    }
    public function validate_table( string $t ): bool {
        return isset( $this->get_available_tables()[ $t ] );
    }
    public function validate_column( string $t, string $c ): bool { return true; }
    public function get_table_columns( string $t ): array { return []; }
}
' );

require_once dirname( __DIR__ ) . '/includes/class-helpers.php';
require_once dirname( __DIR__ ) . '/includes/class-visualizer.php';
require_once dirname( __DIR__ ) . '/includes/DatosAbiertos/DatosAbiertosModule.php';
require_once dirname( __DIR__ ) . '/includes/Presupuesto/Repository.php';
require_once dirname( __DIR__ ) . '/includes/Presupuesto/Analysis.php';
