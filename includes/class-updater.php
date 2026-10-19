<?php
namespace SysmanSuite;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WordPress Plugin Updater
 * Checks GitHub releases for updates and integrates with WordPress update system.
 */
class Updater {

    private const DEFAULT_GITHUB_REPO = 'GobernaciondeNarino/suite-sysman';

    /** Nombre del repositorio antes del cambio; se migra solo. */
    private const LEGACY_GITHUB_REPO  = 'GobernaciondeNarino/sysman-suite';

    private const CACHE_KEY    = 'sysman_suite_update_info';
    private const CACHE_EXPIRY = 12 * HOUR_IN_SECONDS;

    /**
     * Carpeta en la que debe quedar el plugin dentro de wp-content/plugins.
     *
     * Ni el zipball de GitHub («GobernaciondeNarino-suite-sysman-1a2b3c») ni el
     * «Download ZIP» de una rama («suite-sysman-main») traen este nombre, así
     * que la carpeta extraída se renombra al instalar o actualizar.
     */
    public const SLUG = 'suite-sysman';

    private function get_github_repo(): string {
        $repo = (string) get_option( 'sysman_github_repo', self::DEFAULT_GITHUB_REPO );
        return self::LEGACY_GITHUB_REPO === $repo || '' === $repo ? self::DEFAULT_GITHUB_REPO : $repo;
    }

    /** Basename que tendrá el plugin una vez instalado en su carpeta canónica. */
    public static function basename_canonico(): string {
        return self::SLUG . '/' . basename( SYSMAN_SUITE_FILE );
    }

    public function __construct() {
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_updates' ] );
        add_filter( 'plugins_api', [ $this, 'plugin_info' ], 20, 3 );
        add_action( 'upgrader_process_complete', [ $this, 'clear_cache' ], 10, 2 );
        add_filter( 'plugin_row_meta', [ $this, 'plugin_row_meta' ], 10, 2 );

        // Carpeta canónica «suite-sysman», venga el zip de donde venga.
        add_filter( 'upgrader_source_selection', [ $this, 'renombrar_carpeta' ], 10, 4 );
        add_filter( 'upgrader_post_install', [ $this, 'conservar_activacion' ], 10, 3 );
        add_action( 'in_plugin_update_message-' . SYSMAN_SUITE_BASENAME, [ $this, 'update_message' ], 10, 2 );

        // Admin notice for major updates
        add_action( 'admin_notices', [ $this, 'update_notice' ] );
        add_action( 'wp_ajax_sysman_dismiss_update_notice', [ $this, 'dismiss_update_notice' ] );
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

        $current_version = SYSMAN_SUITE_VERSION;

        if ( version_compare( $current_version, $remote['version'], '<' ) ) {
            // Only reference the icon if the asset actually ships with the plugin.
            $icons = file_exists( SYSMAN_SUITE_PATH . 'assets/icon-128.png' )
                ? [ 'default' => SYSMAN_SUITE_URL . 'assets/icon-128.png' ]
                : [];

            $transient->response[ SYSMAN_SUITE_BASENAME ] = (object) [
                'slug'        => self::SLUG,
                'plugin'      => SYSMAN_SUITE_BASENAME,
                'new_version' => $remote['version'],
                'url'         => $remote['url'],
                'package'     => $remote['download_url'],
                'icons'       => $icons,
                'tested'      => $remote['tested'] ?? '',
                'requires'    => $remote['requires'] ?? '6.0',
                'requires_php' => $remote['requires_php'] ?? '8.1',
            ];
        } else {
            // Plugin is up to date
            $transient->no_update[ SYSMAN_SUITE_BASENAME ] = (object) [
                'slug'        => self::SLUG,
                'plugin'      => SYSMAN_SUITE_BASENAME,
                'new_version' => $current_version,
                'url'         => 'https://github.com/' . $this->get_github_repo(),
            ];
        }

        return $transient;
    }

    /**
     * Provide plugin info for the WordPress update screen.
     */
    public function plugin_info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action || ! in_array( $args->slug ?? '', [ self::SLUG, 'sysman-suite' ], true ) ) {
            return $result;
        }

        $remote = $this->get_remote_version();
        if ( ! $remote ) {
            return $result;
        }

        return (object) [
            'name'            => 'SYSMAN Suite',
            'slug'            => self::SLUG,
            'version'         => $remote['version'],
            'author'          => '<a href="https://narino.gov.co">Gobernación de Nariño</a>',
            'author_profile'  => 'https://narino.gov.co',
            'homepage'        => 'https://github.com/' . $this->get_github_repo(),
            'requires'        => $remote['requires'] ?? '6.0',
            'tested'          => $remote['tested'] ?? '',
            'requires_php'    => $remote['requires_php'] ?? '8.1',
            'download_link'   => $remote['download_url'],
            'trunk'           => $remote['download_url'],
            'last_updated'    => $remote['published_at'] ?? '',
            'sections'        => [
                'description'  => __( 'Plugin para importar, almacenar y visualizar datos presupuestales desde el sistema SYSMAN de la Gobernación de Nariño.', 'sysman-suite' ),
                'changelog'    => $this->format_changelog( $remote['changelog'] ?? '' ),
                'installation' => __( '<ol><li>Sube la carpeta del plugin a <code>/wp-content/plugins/</code></li><li>Activa el plugin en WordPress</li><li>Ve a <strong>SYSMAN Suite</strong> en el menú del admin</li></ol>', 'sysman-suite' ),
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
            'https://api.github.com/repos/' . $this->get_github_repo() . '/releases/latest',
            [
                'timeout' => 15,
                'headers' => [
                    'Accept'     => 'application/vnd.github.v3+json',
                    'User-Agent' => 'SYSMAN-Suite/' . SYSMAN_SUITE_VERSION,
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
            return '<p>' . esc_html__( 'No hay registro de cambios disponible.', 'sysman-suite' ) . '</p>';
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
     * ¿El paquete que se está instalando es este plugin?
     *
     * En una actualización WordPress lo dice en $hook_extra['plugin']; en una
     * instalación nueva desde un zip no hay más pista que el archivo principal.
     */
    private function es_este_plugin( string $source, array $hook_extra ): bool {
        if ( isset( $hook_extra['plugin'] ) ) {
            return SYSMAN_SUITE_BASENAME === $hook_extra['plugin'];
        }

        if ( ( $hook_extra['type'] ?? '' ) !== 'plugin' && ! isset( $hook_extra['action'] ) ) {
            return false;
        }

        global $wp_filesystem;
        $principal = trailingslashit( $source ) . basename( SYSMAN_SUITE_FILE );

        // Con FTP/SSH la ruta no es local: se pregunta al sistema de archivos de WP.
        return $wp_filesystem ? $wp_filesystem->exists( $principal ) : file_exists( $principal );
    }

    /**
     * Renombra la carpeta extraída a «suite-sysman».
     *
     * @param string|\WP_Error $source        Carpeta extraída.
     * @param string           $remote_source Carpeta temporal que la contiene.
     * @return string|\WP_Error
     */
    public function renombrar_carpeta( $source, $remote_source, $upgrader = null, $hook_extra = [] ) {
        global $wp_filesystem;

        if ( is_wp_error( $source ) || ! is_string( $source ) || ! $wp_filesystem ) {
            return $source;
        }

        if ( ! $this->es_este_plugin( $source, (array) $hook_extra ) ) {
            return $source;
        }

        $destino = trailingslashit( $remote_source ) . self::SLUG;
        if ( untrailingslashit( $source ) === $destino ) {
            return $source;
        }

        if ( $wp_filesystem->exists( $destino ) ) {
            $wp_filesystem->delete( $destino, true );
        }

        if ( ! $wp_filesystem->move( untrailingslashit( $source ), $destino ) ) {
            return new \WP_Error(
                'sysman_renombrar_carpeta',
                __( 'No se pudo renombrar la carpeta del plugin a «suite-sysman».', 'sysman-suite' )
            );
        }

        return trailingslashit( $destino );
    }

    /**
     * Si la actualización movió el plugin de carpeta, lo deja activo.
     *
     * WordPress guarda los plugins activos por «carpeta/archivo.php». Al pasar
     * de «sisman-suite/…» o «suite-sysman-main/…» a «suite-sysman/…» esa ruta
     * deja de existir y el plugin quedaría desactivado tras actualizar.
     */
    public function conservar_activacion( $response, $hook_extra, $result ) {
        if ( SYSMAN_SUITE_BASENAME !== ( $hook_extra['plugin'] ?? '' ) ) {
            return $response;
        }

        $anterior = SYSMAN_SUITE_BASENAME;
        $nuevo    = self::basename_canonico();
        if ( $anterior === $nuevo ) {
            return $response;
        }

        $activos = (array) get_option( 'active_plugins', [] );
        $pos     = array_search( $anterior, $activos, true );
        if ( false !== $pos ) {
            $activos[ $pos ] = $nuevo;
            update_option( 'active_plugins', array_values( array_unique( $activos ) ) );
        }

        if ( is_multisite() ) {
            $red = (array) get_site_option( 'active_sitewide_plugins', [] );
            if ( isset( $red[ $anterior ] ) ) {
                $red[ $nuevo ] = $red[ $anterior ];
                unset( $red[ $anterior ] );
                update_site_option( 'active_sitewide_plugins', $red );
            }
        }

        return $response;
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
        if ( SYSMAN_SUITE_BASENAME !== $file ) {
            return $links;
        }

        $links[] = '<a href="https://github.com/' . $this->get_github_repo() . '/releases">' .
            esc_html__( 'Registro de cambios', 'sysman-suite' ) . '</a>';
        $links[] = '<a href="https://github.com/' . $this->get_github_repo() . '/issues">' .
            esc_html__( 'Reportar problema', 'sysman-suite' ) . '</a>';

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
                '<br><span class="sysman-update-changelog">%s: %s <a href="%s" target="_blank">%s</a></span>',
                esc_html__( 'Novedades', 'sysman-suite' ),
                esc_html( $changelog ),
                esc_url( $remote['url'] ),
                esc_html__( 'Ver detalles completos', 'sysman-suite' )
            );
        }
    }

    /**
     * Display admin notice when an update is available.
     */
    public function update_notice(): void {
        // Only show on SYSMAN Suite pages
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'sysman' ) === false ) {
            return;
        }

        // Check if dismissed
        $dismissed = get_option( 'sysman_update_notice_dismissed', '' );

        $remote = $this->get_remote_version();
        if ( ! $remote ) {
            return;
        }

        if ( ! version_compare( SYSMAN_SUITE_VERSION, $remote['version'], '<' ) ) {
            return;
        }

        if ( $dismissed === $remote['version'] ) {
            return;
        }

        ?>
        <div class="notice notice-info is-dismissible sysman-update-notice" data-version="<?php echo esc_attr( $remote['version'] ); ?>">
            <p>
                <strong><?php esc_html_e( 'SYSMAN Suite', 'sysman-suite' ); ?></strong> &mdash;
                <?php
                printf(
                    /* translators: %s: new version number */
                    esc_html__( 'Hay una nueva versión disponible: %s. Actualice para obtener las últimas mejoras y correcciones.', 'sysman-suite' ),
                    '<strong>' . esc_html( $remote['version'] ) . '</strong>'
                );
                ?>
                <a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>" class="button button-small" style="margin-left:10px;">
                    <?php esc_html_e( 'Actualizar ahora', 'sysman-suite' ); ?>
                </a>
                <?php if ( ! empty( $remote['url'] ) ) : ?>
                <a href="<?php echo esc_url( $remote['url'] ); ?>" target="_blank" style="margin-left:5px;">
                    <?php esc_html_e( 'Ver cambios', 'sysman-suite' ); ?>
                </a>
                <?php endif; ?>
            </p>
        </div>
        <script>
        jQuery(function($) {
            $('.sysman-update-notice').on('click', '.notice-dismiss', function() {
                $.post(ajaxurl, {
                    action: 'sysman_dismiss_update_notice',
                    version: '<?php echo esc_js( $remote['version'] ); ?>',
                    nonce: '<?php echo esc_js( wp_create_nonce( 'sysman_dismiss_notice' ) ); ?>'
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
        check_ajax_referer( 'sysman_dismiss_notice', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( '', '', [ 'response' => 403 ] );
        }
        $version = sanitize_text_field( $_POST['version'] ?? '' );
        if ( $version ) {
            update_option( 'sysman_update_notice_dismissed', $version );
        }
        wp_die();
    }
}
