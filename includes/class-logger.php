<?php
namespace SismanSuite;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Logger {

    private string $log_file;

    private const LEVEL_ICONS = [
        'info'    => 'INFO',
        'warning' => 'WARN',
        'error'   => 'ERROR',
        'success' => 'OK',
    ];

    public function __construct() {
        $this->log_file = SISMAN_SUITE_PATH . 'logs/import.log';
    }

    /**
     * Write a log message with severity level.
     */
    public function log( string $message, string $level = 'info' ): void {
        $timestamp = current_time( 'Y-m-d H:i:s' );
        $level_tag = self::LEVEL_ICONS[ $level ] ?? 'INFO';
        $entry     = "[{$timestamp}] [{$level_tag}] {$message}" . PHP_EOL;

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        file_put_contents( $this->log_file, $entry, FILE_APPEND | LOCK_EX );
    }

    /**
     * Get log contents.
     */
    public function get_log( int $lines = 500 ): string {
        if ( ! file_exists( $this->log_file ) ) {
            return '';
        }

        $content = file_get_contents( $this->log_file );
        if ( ! $content ) {
            return '';
        }

        $all_lines = explode( PHP_EOL, trim( $content ) );
        $last      = array_slice( $all_lines, -$lines );

        return implode( PHP_EOL, $last );
    }

    /**
     * Get structured log entries (parsed).
     */
    public function get_log_entries( int $limit = 100, string $level_filter = '' ): array {
        if ( ! file_exists( $this->log_file ) ) {
            return [];
        }

        $content = file_get_contents( $this->log_file );
        if ( ! $content ) {
            return [];
        }

        $all_lines = explode( PHP_EOL, trim( $content ) );
        $entries   = [];

        foreach ( array_reverse( $all_lines ) as $line ) {
            if ( count( $entries ) >= $limit ) {
                break;
            }

            if ( preg_match( '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \[(\w+)\] (.+)$/', $line, $matches ) ) {
                $entry = [
                    'timestamp' => $matches[1],
                    'level'     => strtolower( $matches[2] ),
                    'message'   => $matches[3],
                ];

                if ( $level_filter && $entry['level'] !== $level_filter ) {
                    continue;
                }

                $entries[] = $entry;
            } elseif ( preg_match( '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (.+)$/', $line, $matches ) ) {
                // Legacy format (without level)
                $entry = [
                    'timestamp' => $matches[1],
                    'level'     => 'info',
                    'message'   => $matches[2],
                ];

                if ( $level_filter && $entry['level'] !== $level_filter ) {
                    continue;
                }

                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * Count errors in log.
     */
    public function count_errors(): int {
        if ( ! file_exists( $this->log_file ) ) {
            return 0;
        }

        $content = file_get_contents( $this->log_file );
        if ( ! $content ) {
            return 0;
        }

        return substr_count( $content, '[ERROR]' );
    }

    /**
     * Clear the log file.
     */
    public function clear(): void {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        file_put_contents( $this->log_file, '' );
    }

    /**
     * Get log file size.
     */
    public function get_size(): string {
        if ( ! file_exists( $this->log_file ) ) {
            return '0 B';
        }

        $size = filesize( $this->log_file );
        $units = [ 'B', 'KB', 'MB', 'GB' ];
        $i = 0;
        while ( $size >= 1024 && $i < 3 ) {
            $size /= 1024;
            $i++;
        }

        return round( $size, 2 ) . ' ' . $units[ $i ];
    }
}
