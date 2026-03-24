<?php
/**
 * Plugin Name: SYSMAN Suite
 * Plugin URI:  https://github.com/GobernaciondeNarino/sysman-suite
 * Description: Plugin para importar, almacenar y visualizar datos presupuestales desde el sistema SYSMAN de la Gobernación de Nariño.
 * Version:     2.2.0
 * Author:      Gobernación de Nariño
 * Author URI:  https://narino.gov.co
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sysman-suite
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SYSMAN_SUITE_VERSION', '2.2.0' );
define( 'SYSMAN_SUITE_FILE', __FILE__ );
define( 'SYSMAN_SUITE_PATH', plugin_dir_path( __FILE__ ) );
define( 'SYSMAN_SUITE_URL', plugin_dir_url( __FILE__ ) );
define( 'SYSMAN_SUITE_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader for plugin classes.
 */
spl_autoload_register( function ( $class ) {
    $namespace = 'SysmanSuite\\';
    if ( strpos( $class, $namespace ) !== 0 ) {
        return;
    }
    $relative = substr( $class, strlen( $namespace ) );
    $file     = SYSMAN_SUITE_PATH . 'includes/class-' . strtolower( str_replace( '_', '-', $relative ) ) . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
    }
});

/**
 * Main Plugin class (Singleton).
 */
final class Sysman_Suite {

    private static ?self $instance = null;

    public \SysmanSuite\Database   $database;
    public \SysmanSuite\Importer   $importer;
    public \SysmanSuite\Visualizer $visualizer;
    public \SysmanSuite\Rest_Api   $rest_api;
    public \SysmanSuite\Logger     $logger;
    public \SysmanSuite\Updater    $updater;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->logger     = new \SysmanSuite\Logger();
        $this->database   = new \SysmanSuite\Database( $this->logger );
        $this->importer   = new \SysmanSuite\Importer( $this->database, $this->logger );
        $this->visualizer = new \SysmanSuite\Visualizer( $this->database );
        $this->rest_api   = new \SysmanSuite\Rest_Api( $this->database );
        $this->updater    = new \SysmanSuite\Updater();

        $this->register_hooks();
    }

    private function register_hooks(): void {
        register_activation_hook( SYSMAN_SUITE_FILE, [ $this, 'activate' ] );
        register_deactivation_hook( SYSMAN_SUITE_FILE, [ $this, 'deactivate' ] );

        add_action( 'admin_menu', [ $this, 'admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_init', [ $this, 'maybe_create_tables' ] );

        // Settings connection test
        add_action( 'wp_ajax_sysman_test_connections', [ $this, 'ajax_test_connections' ] );

        // Cron
        add_action( 'sysman_scheduled_import', [ $this->importer, 'run_scheduled_import' ] );
        add_filter( 'cron_schedules', [ $this, 'add_cron_schedules' ] );

        // WP-CLI
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            \WP_CLI::add_command( 'sysman', \SysmanSuite\Cli::class );
        }
    }

    public function activate(): void {
        $this->database->create_tables();
        if ( ! wp_next_scheduled( 'sysman_scheduled_import' ) ) {
            $frequency = get_option( 'sysman_import_frequency', 'daily' );
            wp_schedule_event( time(), $frequency, 'sysman_scheduled_import' );
        }
        update_option( 'sysman_db_version', SYSMAN_SUITE_VERSION );
        flush_rewrite_rules();
    }

    public function deactivate(): void {
        wp_clear_scheduled_hook( 'sysman_scheduled_import' );
        flush_rewrite_rules();
    }

    /**
     * Ensure tables exist on every admin load if they're missing.
     */
    public function maybe_create_tables(): void {
        $db_version = get_option( 'sysman_db_version', '0' );
        if ( version_compare( $db_version, SYSMAN_SUITE_VERSION, '<' ) ) {
            $this->database->create_tables();
            update_option( 'sysman_db_version', SYSMAN_SUITE_VERSION );
        }
    }

    public function add_cron_schedules( array $schedules ): array {
        $schedules['weekly'] = [
            'interval' => WEEK_IN_SECONDS,
            'display'  => __( 'Semanalmente', 'sysman-suite' ),
        ];
        $schedules['monthly'] = [
            'interval' => MONTH_IN_SECONDS,
            'display'  => __( 'Mensualmente', 'sysman-suite' ),
        ];
        return $schedules;
    }

    public function admin_menu(): void {
        add_menu_page(
            __( 'SYSMAN Suite', 'sysman-suite' ),
            __( 'SYSMAN Suite', 'sysman-suite' ),
            'manage_options',
            'sysman-suite',
            [ $this, 'render_dashboard_page' ],
            'dashicons-chart-area',
            30
        );

        add_submenu_page(
            'sysman-suite',
            __( 'Panel de Control', 'sysman-suite' ),
            __( 'Panel de Control', 'sysman-suite' ),
            'manage_options',
            'sysman-suite',
            [ $this, 'render_dashboard_page' ]
        );

        add_submenu_page(
            'sysman-suite',
            __( 'Importar Datos', 'sysman-suite' ),
            __( 'Importar Datos', 'sysman-suite' ),
            'manage_options',
            'sysman-import',
            [ $this, 'render_import_page' ]
        );

        add_submenu_page(
            'sysman-suite',
            __( 'Registros', 'sysman-suite' ),
            __( 'Registros', 'sysman-suite' ),
            'manage_options',
            'sysman-records',
            [ $this, 'render_records_page' ]
        );

        add_submenu_page(
            'sysman-suite',
            __( 'Logs', 'sysman-suite' ),
            __( 'Logs', 'sysman-suite' ),
            'manage_options',
            'sysman-logs',
            [ $this, 'render_logs_page' ]
        );

        add_submenu_page(
            'sysman-suite',
            __( 'Configuración', 'sysman-suite' ),
            __( 'Configuración', 'sysman-suite' ),
            'manage_options',
            'sysman-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    public function render_dashboard_page(): void {
        include SYSMAN_SUITE_PATH . 'templates/admin/dashboard-page.php';
    }

    public function render_import_page(): void {
        include SYSMAN_SUITE_PATH . 'templates/admin/import-page.php';
    }

    public function render_records_page(): void {
        include SYSMAN_SUITE_PATH . 'templates/admin/records-page.php';
    }

    public function render_logs_page(): void {
        include SYSMAN_SUITE_PATH . 'templates/admin/logs-page.php';
    }

    public function render_settings_page(): void {
        include SYSMAN_SUITE_PATH . 'templates/admin/settings-page.php';
    }

    public function admin_assets( string $hook ): void {
        $plugin_pages = [
            'toplevel_page_sysman-suite',
            'sysman-suite_page_sysman-import',
            'sysman-suite_page_sysman-records',
            'sysman-suite_page_sysman-logs',
            'sysman-suite_page_sysman-settings',
        ];

        if ( ! in_array( $hook, $plugin_pages, true ) ) {
            $screen = get_current_screen();
            if ( $screen && 'sysman_chart' !== $screen->post_type ) {
                return;
            }
        }

        wp_enqueue_style(
            'sysman-admin',
            SYSMAN_SUITE_URL . 'assets/css/admin.css',
            [],
            SYSMAN_SUITE_VERSION
        );

        wp_enqueue_script(
            'sysman-admin-import',
            SYSMAN_SUITE_URL . 'assets/js/admin-import.js',
            [ 'jquery' ],
            SYSMAN_SUITE_VERSION,
            true
        );

        wp_localize_script( 'sysman-admin-import', 'sysmanAdmin', [
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'restUrl'  => rest_url( 'sysman-suite/v1/' ),
            'nonce'    => wp_create_nonce( 'sysman_import_nonce' ),
            'restNonce' => wp_create_nonce( 'wp_rest' ),
            'strings'  => [
                'confirmImport' => __( '¿Iniciar importación de datos desde SYSMAN?', 'sysman-suite' ),
                'importing'     => __( 'Importando...', 'sysman-suite' ),
                'complete'      => __( 'Importación completada', 'sysman-suite' ),
                'error'         => __( 'Error durante la importación', 'sysman-suite' ),
                'noData'        => __( 'No se encontraron datos', 'sysman-suite' ),
            ],
        ] );

        // Chart config assets for CPT edit screen
        $screen = get_current_screen();
        if ( $screen && 'sysman_chart' === $screen->post_type ) {
            // D3.js and D3plus for live admin preview (URLs from settings)
            $d3_url     = get_option( 'sysman_d3_cdn_url', 'https://d3js.org/d3.v5.min.js' );
            $d3plus_url = get_option( 'sysman_d3plus_cdn_url', 'https://d3plus.org/js/d3plus.v2.0.full.min.js' );
            wp_enqueue_script( 'd3-v5', $d3_url, [], '5.16.0', true );
            wp_enqueue_script( 'd3plus', $d3plus_url, [ 'd3-v5' ], '2.0.0', true );

            wp_enqueue_script(
                'sysman-admin-charts',
                SYSMAN_SUITE_URL . 'assets/js/admin-charts.js',
                [ 'jquery', 'd3-v5', 'd3plus' ],
                SYSMAN_SUITE_VERSION,
                true
            );
            wp_localize_script( 'sysman-admin-charts', 'sysmanCharts', [
                'restUrl'      => rest_url( 'sysman-suite/v1/' ),
                'restNonce'    => wp_create_nonce( 'wp_rest' ),
                'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
                'previewNonce' => wp_create_nonce( 'sysman_chart_preview' ),
            ] );
        }
    }

    /**
     * AJAX: Test configured connections.
     */
    public function ajax_test_connections(): void {
        check_ajax_referer( 'sysman_test_connections' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $results = [];

        // Test SYSMAN API
        $api_url = sanitize_url( $_POST['api_url'] ?? '' );
        if ( $api_url ) {
            $test_url  = $api_url . '?compania=001&anio=' . date( 'Y' ) . '&mes=1&numinforme=1';
            $response  = wp_remote_get( $test_url, [ 'timeout' => 15, 'sslverify' => false ] );
            $results[] = [
                'label'   => 'API SYSMAN',
                'ok'      => ! is_wp_error( $response ) && in_array( wp_remote_retrieve_response_code( $response ), [ 200, 201 ], true ),
                'message' => is_wp_error( $response )
                    ? $response->get_error_message()
                    : 'HTTP ' . wp_remote_retrieve_response_code( $response ),
            ];
        }

        // Test D3.js CDN
        $d3_url = sanitize_url( $_POST['d3_url'] ?? '' );
        if ( $d3_url ) {
            $response  = wp_remote_head( $d3_url, [ 'timeout' => 10 ] );
            $results[] = [
                'label'   => 'D3.js CDN',
                'ok'      => ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ),
                'message' => is_wp_error( $response )
                    ? $response->get_error_message()
                    : 'HTTP ' . wp_remote_retrieve_response_code( $response ),
            ];
        }

        // Test D3Plus CDN
        $d3plus_url = sanitize_url( $_POST['d3plus_url'] ?? '' );
        if ( $d3plus_url ) {
            $response  = wp_remote_head( $d3plus_url, [ 'timeout' => 10 ] );
            $results[] = [
                'label'   => 'D3Plus CDN',
                'ok'      => ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ),
                'message' => is_wp_error( $response )
                    ? $response->get_error_message()
                    : 'HTTP ' . wp_remote_retrieve_response_code( $response ),
            ];
        }

        // Test GitHub API
        $github_repo = sanitize_text_field( $_POST['github_repo'] ?? '' );
        if ( $github_repo ) {
            $gh_url    = 'https://api.github.com/repos/' . $github_repo;
            $response  = wp_remote_get( $gh_url, [
                'timeout' => 10,
                'headers' => [ 'Accept' => 'application/vnd.github.v3+json', 'User-Agent' => 'SYSMAN-Suite/' . SYSMAN_SUITE_VERSION ],
            ] );
            $results[] = [
                'label'   => 'GitHub (' . $github_repo . ')',
                'ok'      => ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ),
                'message' => is_wp_error( $response )
                    ? $response->get_error_message()
                    : 'HTTP ' . wp_remote_retrieve_response_code( $response ),
            ];
        }

        wp_send_json_success( $results );
    }

    public function register_settings(): void {
        register_setting( 'sysman_settings', 'sysman_api_compania', [
            'type'              => 'string',
            'default'           => '001',
            'sanitize_callback' => 'sanitize_text_field',
        ] );
        register_setting( 'sysman_settings', 'sysman_api_anio', [
            'type'              => 'integer',
            'default'           => (int) date( 'Y' ),
            'sanitize_callback' => 'absint',
        ] );
        register_setting( 'sysman_settings', 'sysman_api_mes', [
            'type'              => 'integer',
            'default'           => (int) date( 'n' ),
            'sanitize_callback' => 'absint',
        ] );
        register_setting( 'sysman_settings', 'sysman_import_frequency', [
            'type'              => 'string',
            'default'           => 'daily',
            'sanitize_callback' => 'sanitize_text_field',
        ] );

        // API & CDN URL settings
        register_setting( 'sysman_settings', 'sysman_api_base_url', [
            'type'              => 'string',
            'default'           => 'https://narino-gob.sysman.com.co/sysmanApi/autoservicio/v1/informesGobNar',
            'sanitize_callback' => 'esc_url_raw',
        ] );
        register_setting( 'sysman_settings', 'sysman_github_repo', [
            'type'              => 'string',
            'default'           => 'GobernaciondeNarino/sysman-suite',
            'sanitize_callback' => 'sanitize_text_field',
        ] );
        register_setting( 'sysman_settings', 'sysman_d3_cdn_url', [
            'type'              => 'string',
            'default'           => 'https://d3js.org/d3.v5.min.js',
            'sanitize_callback' => 'esc_url_raw',
        ] );
        register_setting( 'sysman_settings', 'sysman_d3plus_cdn_url', [
            'type'              => 'string',
            'default'           => 'https://d3plus.org/js/d3plus.v2.0.full.min.js',
            'sanitize_callback' => 'esc_url_raw',
        ] );
    }
}

// Initialize
add_action( 'plugins_loaded', function () {
    Sysman_Suite::instance();
} );
