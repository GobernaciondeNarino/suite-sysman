<?php
namespace SismanSuite;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Logger {

    private string $log_file;

    public function __construct() {
        $this->log_file = SISMAN_SUITE_PATH . 'logs/import.log';
    }

    /**
     * Write a log message.
     */
    public function log( string $message ): void {
        $timestamp = current_time( 'Y-m-d H:i:s' );
        $entry     = "[{$timestamp}] {$message}" . PHP_EOL;

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        file_put_contents( $this->log_file, $entry, FILE_APPEND | LOCK_EX );
    }

    /**
     * Get log contents.
     */
    public function get_log( int $lines = 200 ): string {
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
