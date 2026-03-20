<?php
namespace SismanSuite;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WordPress Plugin Updater
 * Checks GitHub releases for updates and integrates with WordPress update system.
 */
class Updater {

    private const GITHUB_REPO  = 'GobernaciondeNarino/sisman-suite';
    private const CACHE_KEY    = 'sisman_suite_update_info';
    private const CACHE_EXPIRY = 12 * HOUR_IN_SECONDS;

    public function __construct() {
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_updates' ] );
        add_filter( 'plugins_api', [ $this, 'plugin_info' ], 20, 3 );
        add_action( 'upgrader_process_complete', [ $this, 'clear_cache' ], 10, 2 );
        add_filter( 'plugin_row_meta', [ $this, 'plugin_row_meta' ], 10, 2 );
        add_action( 'in_plugin_update_message-' . SISMAN_SUITE_BASENAME, [ $this, 'update_message' ], 10, 2 );

        // Admin notice for major updates
        add_action( 'admin_notices', [ $this, 'update_notice' ] );
        add_action( 'wp_ajax_sisman_dismiss_update_notice', [ $this, 'dismiss_update_notice' ] );
    }

    /**
     * Check GitHub for updates.
     */
    public function check_for_updates( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $remote = $this->get_remote_version();
        if ( ! $remote ) {
            return $transient;
        }

        $current_version = SISMAN_SUITE_VERSION;

        if ( version_compare( $current_version, $remote['version'], '<' ) ) {
            $transient->response[ SISMAN_SUITE_BASENAME ] = (object) [
                'slug'        => 'sisman-suite',
                'plugin'      => SISMAN_SUITE_BASENAME,
                'new_version' => $remote['version'],
                'url'         => $remote['url'],
                'package'     => $remote['download_url'],
                'icons'       => [
                    'default' => SISMAN_SUITE_URL . 'assets/icon-128.png',
                ],
                'tested'      => $remote['tested'] ?? '',
                'requires'    => $remote['requires'] ?? '6.0',
                'requires_php' => $remote['requires_php'] ?? '8.1',
            ];
        } else {
            // Plugin is up to date
            $transient->no_update[ SISMAN_SUITE_BASENAME ] = (object) [
                'slug'        => 'sisman-suite',
                'plugin'      => SISMAN_SUITE_BASENAME,
                'new_version' => $current_version,
                'url'         => 'https://github.com/' . self::GITHUB_REPO,
            ];
        }

        return $transient;
    }

    /**
     * Provide plugin info for the WordPress update screen.
     */
    public function plugin_info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action || 'sisman-suite' !== ( $args->slug ?? '' ) ) {
            return $result;
        }

        $remote = $this->get_remote_version();
        if ( ! $remote ) {
            return $result;
        }

        return (object) [
            'name'            => 'SISMAN Suite',
            'slug'            => 'sisman-suite',
            'version'         => $remote['version'],
            'author'          => '<a href="https://narino.gov.co">Gobernación de Nariño</a>',
            'author_profile'  => 'https://narino.gov.co',
            'homepage'        => 'https://github.com/' . self::GITHUB_REPO,
            'requires'        => $remote['requires'] ?? '6.0',
            'tested'          => $remote['tested'] ?? '',
            'requires_php'    => $remote['requires_php'] ?? '8.1',
            'download_link'   => $remote['download_url'],
            'trunk'           => $remote['download_url'],
            'last_updated'    => $remote['published_at'] ?? '',
            'sections'        => [
                'description'  => __( 'Plugin para importar, almacenar y visualizar datos presupuestales desde el sistema SISMAN de la Gobernación de Nariño.', 'sisman-suite' ),
                'changelog'    => $this->format_changelog( $remote['changelog'] ?? '' ),
                'installation' => __( '<ol><li>Sube la carpeta del plugin a <code>/wp-content/plugins/</code></li><li>Activa el plugin en WordPress</li><li>Ve a <strong>SISMAN Suite</strong> en el menú del admin</li></ol>', 'sisman-suite' ),
            ],
            'banners'         => [],
        ];
    }

    /**
     * Fetch remote version info from GitHub releases.
     */
    private function get_remote_version(): ?array {
        $cached = get_transient( self::CACHE_KEY );
        if ( false !== $cached ) {
            return $cached;
        }

        $response = wp_remote_get(
            'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest',
            [
                'timeout' => 15,
                'headers' => [
                    'Accept'     => 'application/vnd.github.v3+json',
                    'User-Agent' => 'SISMAN-Suite/' . SISMAN_SUITE_VERSION,
                ],
            ]
        );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            return null;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! $body || ! isset( $body['tag_name'] ) ) {
            return null;
        }

        // Parse version from tag (remove 'v' prefix if present)
        $version = ltrim( $body['tag_name'], 'vV' );

        // Find the zip asset or use the zipball URL
        $download_url = $body['zipball_url'] ?? '';
        if ( ! empty( $body['assets'] ) ) {
            foreach ( $body['assets'] as $asset ) {
                if ( str_ends_with( $asset['name'], '.zip' ) ) {
                    $download_url = $asset['browser_download_url'];
                    break;
                }
            }
        }

        // Parse changelog from release body
        $changelog = $body['body'] ?? '';

        // Try to extract metadata from release body
        $requires     = '6.0';
        $tested       = '';
        $requires_php = '8.1';

        if ( preg_match( '/Requires at least:\s*(.+)/i', $changelog, $m ) ) {
            $requires = trim( $m[1] );
        }
        if ( preg_match( '/Tested up to:\s*(.+)/i', $changelog, $m ) ) {
            $tested = trim( $m[1] );
        }
        if ( preg_match( '/Requires PHP:\s*(.+)/i', $changelog, $m ) ) {
            $requires_php = trim( $m[1] );
        }

        $data = [
            'version'      => $version,
            'url'          => $body['html_url'] ?? '',
            'download_url' => $download_url,
            'changelog'    => $changelog,
            'published_at' => $body['published_at'] ?? '',
            'requires'     => $requires,
            'tested'       => $tested,
            'requires_php' => $requires_php,
        ];

        set_transient( self::CACHE_KEY, $data, self::CACHE_EXPIRY );

        return $data;
    }

    /**
     * Format the changelog from GitHub markdown to HTML.
     */
    private function format_changelog( string $markdown ): string {
        if ( empty( $markdown ) ) {
            return '<p>' . esc_html__( 'No hay registro de cambios disponible.', 'sisman-suite' ) . '</p>';
        }

        // Basic markdown to HTML conversion
        $html = esc_html( $markdown );
        $html = preg_replace( '/^### (.+)$/m', '<h4>$1</h4>', $html );
        $html = preg_replace( '/^## (.+)$/m', '<h3>$1</h3>', $html );
        $html = preg_replace( '/^# (.+)$/m', '<h2>$1</h2>', $html );
        $html = preg_replace( '/^\* (.+)$/m', '<li>$1</li>', $html );
        $html = preg_replace( '/^- (.+)$/m', '<li>$1</li>', $html );
        $html = preg_replace( '/(<li>.+<\/li>\n?)+/', '<ul>$0</ul>', $html );
        $html = nl2br( $html );

        return $html;
    }

    /**
     * Clear the update cache.
     */
    public function clear_cache( $upgrader, $options ): void {
        if ( 'update' === $options['action'] && 'plugin' === $options['type'] ) {
            delete_transient( self::CACHE_KEY );
        }
    }

    /**
     * Add custom links to the plugin row.
     */
    public function plugin_row_meta( array $links, string $file ): array {
        if ( SISMAN_SUITE_BASENAME !== $file ) {
            return $links;
        }

        $links[] = '<a href="https://github.com/' . self::GITHUB_REPO . '/releases">' .
            esc_html__( 'Registro de cambios', 'sisman-suite' ) . '</a>';
        $links[] = '<a href="https://github.com/' . self::GITHUB_REPO . '/issues">' .
            esc_html__( 'Reportar problema', 'sisman-suite' ) . '</a>';

        return $links;
    }

    /**
     * Show update message with changelog in the plugins list.
     */
    public function update_message( $plugin_data, $response ): void {
        $remote = $this->get_remote_version();
        if ( ! $remote || empty( $remote['changelog'] ) ) {
            return;
        }

        // Show a brief excerpt from the changelog
        $changelog = wp_trim_words( wp_strip_all_tags( $remote['changelog'] ), 30 );
        if ( $changelog ) {
            printf(
                '<br><span class="sisman-update-changelog">%s: %s <a href="%s" target="_blank">%s</a></span>',
                esc_html__( 'Novedades', 'sisman-suite' ),
                esc_html( $changelog ),
                esc_url( $remote['url'] ),
                esc_html__( 'Ver detalles completos', 'sisman-suite' )
            );
        }
    }

    /**
     * Display admin notice when an update is available.
     */
    public function update_notice(): void {
        // Only show on SISMAN Suite pages
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'sisman' ) === false ) {
            return;
        }

        // Check if dismissed
        $dismissed = get_option( 'sisman_update_notice_dismissed', '' );

        $remote = $this->get_remote_version();
        if ( ! $remote ) {
            return;
        }

        if ( ! version_compare( SISMAN_SUITE_VERSION, $remote['version'], '<' ) ) {
            return;
        }

        if ( $dismissed === $remote['version'] ) {
            return;
        }

        ?>
        <div class="notice notice-info is-dismissible sisman-update-notice" data-version="<?php echo esc_attr( $remote['version'] ); ?>">
            <p>
                <strong><?php esc_html_e( 'SISMAN Suite', 'sisman-suite' ); ?></strong> &mdash;
                <?php
                printf(
                    /* translators: %s: new version number */
                    esc_html__( 'Hay una nueva versión disponible: %s. Actualice para obtener las últimas mejoras y correcciones.', 'sisman-suite' ),
                    '<strong>' . esc_html( $remote['version'] ) . '</strong>'
                );
                ?>
                <a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>" class="button button-small" style="margin-left:10px;">
                    <?php esc_html_e( 'Actualizar ahora', 'sisman-suite' ); ?>
                </a>
                <?php if ( ! empty( $remote['url'] ) ) : ?>
                <a href="<?php echo esc_url( $remote['url'] ); ?>" target="_blank" style="margin-left:5px;">
                    <?php esc_html_e( 'Ver cambios', 'sisman-suite' ); ?>
                </a>
                <?php endif; ?>
            </p>
        </div>
        <script>
        jQuery(function($) {
            $('.sisman-update-notice').on('click', '.notice-dismiss', function() {
                $.post(ajaxurl, {
                    action: 'sisman_dismiss_update_notice',
                    version: '<?php echo esc_js( $remote['version'] ); ?>',
                    nonce: '<?php echo esc_js( wp_create_nonce( 'sisman_dismiss_notice' ) ); ?>'
                });
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX: Dismiss update notice for a specific version.
     */
    public function dismiss_update_notice(): void {
        check_ajax_referer( 'sisman_dismiss_notice', 'nonce' );
        $version = sanitize_text_field( $_POST['version'] ?? '' );
        if ( $version ) {
            update_option( 'sisman_update_notice_dismissed', $version );
        }
        wp_die();
    }
}
