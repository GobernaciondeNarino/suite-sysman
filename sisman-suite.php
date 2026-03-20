<?php
/**
 * Plugin Name: SISMAN Suite
 * Plugin URI:  https://github.com/GobernaciondeNarino/sisman-suite
 * Description: Plugin para importar, almacenar y visualizar datos presupuestales desde el sistema SISMAN de la Gobernación de Nariño.
 * Version:     1.0.0
 * Author:      Gobernación de Nariño
 * Author URI:  https://narino.gov.co
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sisman-suite
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SISMAN_SUITE_VERSION', '1.0.0' );
define( 'SISMAN_SUITE_FILE', __FILE__ );
define( 'SISMAN_SUITE_PATH', plugin_dir_path( __FILE__ ) );
define( 'SISMAN_SUITE_URL', plugin_dir_url( __FILE__ ) );
define( 'SISMAN_SUITE_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader for plugin classes.
 */
spl_autoload_register( function ( $class ) {
    $namespace = 'SismanSuite\\';
    if ( strpos( $class, $namespace ) !== 0 ) {
        return;
    }
    $relative = substr( $class, strlen( $namespace ) );
    $file     = SISMAN_SUITE_PATH . 'includes/class-' . strtolower( str_replace( '_', '-', $relative ) ) . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
    }
});

/**
 * Main Plugin class (Singleton).
 */
final class Sisman_Suite {

    private static ?self $instance = null;

    public \SismanSuite\Database   $database;
    public \SismanSuite\Importer   $importer;
    public \SismanSuite\Visualizer $visualizer;
    public \SismanSuite\Rest_Api   $rest_api;
    public \SismanSuite\Logger     $logger;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->logger     = new \SismanSuite\Logger();
        $this->database   = new \SismanSuite\Database( $this->logger );
        $this->importer   = new \SismanSuite\Importer( $this->database, $this->logger );
        $this->visualizer = new \SismanSuite\Visualizer( $this->database );
        $this->rest_api   = new \SismanSuite\Rest_Api( $this->database );

        $this->register_hooks();
    }

    private function register_hooks(): void {
        register_activation_hook( SISMAN_SUITE_FILE, [ $this, 'activate' ] );
        register_deactivation_hook( SISMAN_SUITE_FILE, [ $this, 'deactivate' ] );

        add_action( 'admin_menu', [ $this, 'admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );

        // Cron
        add_action( 'sisman_scheduled_import', [ $this->importer, 'run_scheduled_import' ] );
        add_filter( 'cron_schedules', [ $this, 'add_cron_schedules' ] );

        // WP-CLI
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            \WP_CLI::add_command( 'sisman', \SismanSuite\Cli::class );
        }
    }

    public function activate(): void {
        $this->database->create_tables();
        if ( ! wp_next_scheduled( 'sisman_scheduled_import' ) ) {
            $frequency = get_option( 'sisman_import_frequency', 'daily' );
            wp_schedule_event( time(), $frequency, 'sisman_scheduled_import' );
        }
        flush_rewrite_rules();
    }

    public function deactivate(): void {
        wp_clear_scheduled_hook( 'sisman_scheduled_import' );
        flush_rewrite_rules();
    }

    public function add_cron_schedules( array $schedules ): array {
        $schedules['weekly'] = [
            'interval' => WEEK_IN_SECONDS,
            'display'  => __( 'Semanalmente', 'sisman-suite' ),
        ];
        $schedules['monthly'] = [
            'interval' => MONTH_IN_SECONDS,
            'display'  => __( 'Mensualmente', 'sisman-suite' ),
        ];
        return $schedules;
    }

    public function admin_menu(): void {
        add_menu_page(
            __( 'SISMAN Suite', 'sisman-suite' ),
            __( 'SISMAN Suite', 'sisman-suite' ),
            'manage_options',
            'sisman-suite',
            [ $this, 'render_import_page' ],
            'dashicons-chart-area',
            30
        );

        add_submenu_page(
            'sisman-suite',
            __( 'Importar Datos', 'sisman-suite' ),
            __( 'Importar Datos', 'sisman-suite' ),
            'manage_options',
            'sisman-suite',
            [ $this, 'render_import_page' ]
        );

        add_submenu_page(
            'sisman-suite',
            __( 'Registros', 'sisman-suite' ),
            __( 'Registros', 'sisman-suite' ),
            'manage_options',
            'sisman-records',
            [ $this, 'render_records_page' ]
        );

        add_submenu_page(
            'sisman-suite',
            __( 'Registros', 'sisman-suite' ),
            __( 'Logs', 'sisman-suite' ),
            'manage_options',
            'sisman-logs',
            [ $this, 'render_logs_page' ]
        );
    }

    public function render_import_page(): void {
        include SISMAN_SUITE_PATH . 'templates/admin/import-page.php';
    }

    public function render_records_page(): void {
        include SISMAN_SUITE_PATH . 'templates/admin/records-page.php';
    }

    public function render_logs_page(): void {
        include SISMAN_SUITE_PATH . 'templates/admin/logs-page.php';
    }

    public function admin_assets( string $hook ): void {
        $plugin_pages = [
            'toplevel_page_sisman-suite',
            'sisman-suite_page_sisman-records',
            'sisman-suite_page_sisman-logs',
        ];

        if ( ! in_array( $hook, $plugin_pages, true ) ) {
            $screen = get_current_screen();
            if ( $screen && 'sisman_chart' !== $screen->post_type ) {
                return;
            }
        }

        wp_enqueue_style(
            'sisman-admin',
            SISMAN_SUITE_URL . 'assets/css/admin.css',
            [],
            SISMAN_SUITE_VERSION
        );

        wp_enqueue_script(
            'sisman-admin-import',
            SISMAN_SUITE_URL . 'assets/js/admin-import.js',
            [ 'jquery' ],
            SISMAN_SUITE_VERSION,
            true
        );

        wp_localize_script( 'sisman-admin-import', 'sismanAdmin', [
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'restUrl'  => rest_url( 'sisman-suite/v1/' ),
            'nonce'    => wp_create_nonce( 'sisman_import_nonce' ),
            'restNonce' => wp_create_nonce( 'wp_rest' ),
            'strings'  => [
                'confirmImport' => __( '¿Iniciar importación de datos desde SISMAN?', 'sisman-suite' ),
                'importing'     => __( 'Importando...', 'sisman-suite' ),
                'complete'      => __( 'Importación completada', 'sisman-suite' ),
                'error'         => __( 'Error durante la importación', 'sisman-suite' ),
                'noData'        => __( 'No se encontraron datos', 'sisman-suite' ),
            ],
        ] );

        // Chart config assets for CPT edit screen
        $screen = get_current_screen();
        if ( $screen && 'sisman_chart' === $screen->post_type ) {
            wp_enqueue_script(
                'sisman-admin-charts',
                SISMAN_SUITE_URL . 'assets/js/admin-charts.js',
                [ 'jquery' ],
                SISMAN_SUITE_VERSION,
                true
            );
            wp_localize_script( 'sisman-admin-charts', 'sismanCharts', [
                'restUrl'   => rest_url( 'sisman-suite/v1/' ),
                'restNonce' => wp_create_nonce( 'wp_rest' ),
            ] );
        }
    }

    public function register_settings(): void {
        register_setting( 'sisman_settings', 'sisman_api_compania', [
            'type'              => 'string',
            'default'           => '001',
            'sanitize_callback' => 'sanitize_text_field',
        ] );
        register_setting( 'sisman_settings', 'sisman_api_anio', [
            'type'              => 'integer',
            'default'           => (int) date( 'Y' ),
            'sanitize_callback' => 'absint',
        ] );
        register_setting( 'sisman_settings', 'sisman_api_mes', [
            'type'              => 'integer',
            'default'           => (int) date( 'n' ),
            'sanitize_callback' => 'absint',
        ] );
        register_setting( 'sisman_settings', 'sisman_import_frequency', [
            'type'              => 'string',
            'default'           => 'daily',
            'sanitize_callback' => 'sanitize_text_field',
        ] );
    }
}

// Initialize
add_action( 'plugins_loaded', function () {
    Sisman_Suite::instance();
} );
