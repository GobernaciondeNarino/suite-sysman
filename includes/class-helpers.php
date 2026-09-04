<?php
namespace SysmanSuite;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shared helpers used across modules.
 */
class Helpers {

    public const MESES = [
        1  => 'Enero',
        2  => 'Febrero',
        3  => 'Marzo',
        4  => 'Abril',
        5  => 'Mayo',
        6  => 'Junio',
        7  => 'Julio',
        8  => 'Agosto',
        9  => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre',
    ];

    /**
     * Get the Spanish name for a month number (1-12).
     * Falls back to the raw number when out of range.
     */
    public static function month_name( int $mes ): string {
        return self::MESES[ $mes ] ?? (string) $mes;
    }

    /**
     * Best-effort client IP for rate limiting (unauthenticated endpoints).
     */
    public static function client_ip(): string {
        $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
        return $ip ?: 'unknown';
    }

    /**
     * Sliding-window rate limiter backed by transients.
     *
     * Returns true when the request is within the allowed budget, false when
     * the caller should be throttled. Authenticated users with manage_options
     * are never throttled. The limit can be tuned (or disabled by returning 0)
     * via the `sysman_suite_rate_limit` filter.
     *
     * @param string $bucket Logical endpoint group (e.g. 'export', 'chart').
     * @param int    $max    Maximum requests per window.
     * @param int    $window Window length in seconds.
     */
    public static function rate_limit_check( string $bucket, int $max = 30, int $window = 60 ): bool {
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        /**
         * Filter the per-IP request budget for a public endpoint bucket.
         * Return 0 (or less) to disable rate limiting for that bucket.
         */
        $max = (int) apply_filters( 'sysman_suite_rate_limit', $max, $bucket, $window );
        if ( $max <= 0 ) {
            return true;
        }

        $key   = 'sysman_rl_' . md5( $bucket . '|' . self::client_ip() );
        $count = (int) get_transient( $key );

        if ( $count >= $max ) {
            return false;
        }

        // Note: the window resets $window seconds after the first hit.
        set_transient( $key, $count + 1, $window );
        return true;
    }

    /**
     * Send common security headers for file downloads.
     */
    public static function download_headers( string $content_type, string $filename ): void {
        nocache_headers();
        header( 'Content-Type: ' . $content_type );
        header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
        header( 'X-Content-Type-Options: nosniff' );
    }
}
