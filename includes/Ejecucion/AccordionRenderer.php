<?php
namespace SysmanSuite\Ejecucion;

if ( ! defined( 'ABSPATH' ) ) exit;

class AccordionRenderer {

    public static function render( int $post_id ): string {
        $post = get_post( $post_id );
        if ( ! $post || 'gn_ejecucion' !== $post->post_type ) {
            return '<p>' . esc_html__( 'Seguimiento no encontrado.', 'sysman-suite' ) . '</p>';
        }

        $dependencia   = get_post_meta( $post_id, '_gn_dependencia', true );
        $vigencia      = get_post_meta( $post_id, '_gn_vigencia', true );
        $anio          = (int) get_post_meta( $post_id, '_gn_anio', true );
        $mes           = (int) get_post_meta( $post_id, '_gn_mes', true );
        $compania      = get_post_meta( $post_id, '_gn_compania', true ) ?: '001';
        $agrupar_bpid  = get_post_meta( $post_id, '_gn_agrupar_bpid', true );

        $repo   = Repository::instance();
        $rubros = $repo->get_rubros( $post_id );

        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
        $mes_nombre = $meses[ $mes ] ?? $mes;

        $bpid_mode = ( '1' === $agrupar_bpid ) && ! empty( $rubros );
        $root_class = 'gn-ejec' . ( $bpid_mode ? ' gn-ejec--bpid-mode' : '' );

        ob_start();
        ?>
        <div class="<?php echo esc_attr( $root_class ); ?>" data-post-id="<?php echo esc_attr( $post_id ); ?>" data-anio="<?php echo esc_attr( $anio ); ?>" data-mes="<?php echo esc_attr( $mes ); ?>">
            <header class="gn-ejec__header">
                <h2><?php echo esc_html( $post->post_title ); ?></h2>
                <span class="gn-ejec__periodo"><?php echo esc_html( $dependencia ); ?> &mdash; <?php echo esc_html( $mes_nombre ); ?> <?php echo esc_html( $anio ); ?><?php if ( $vigencia ) : ?> &mdash; <?php echo esc_html( $vigencia ); ?><?php endif; ?></span>
            </header>

            <?php if ( empty( $rubros ) ) : ?>
                <div class="gn-ejec__empty">
                    <span class="dashicons dashicons-info-outline"></span>
                    <?php esc_html_e( 'No se encontraron rubros para esta dependencia y periodo. Verifique que los datos estén sincronizados.', 'sysman-suite' ); ?>
                </div>
            <?php elseif ( $bpid_mode ) : ?>
                <?php echo self::render_bpid_layout( $rubros, $repo ); ?>
            <?php else : ?>
                <div class="gn-ejec__count">
                    <?php printf( esc_html__( '%d rubros encontrados', 'sysman-suite' ), count( $rubros ) ); ?>
                </div>
                <?php echo self::render_rubros_list( $rubros ); ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function render_bpid_layout( array $rubros, Repository $repo ): string {
        $groups = [];
        foreach ( $rubros as $rubro ) {
            $bpid = ! empty( $rubro['codigobpin'] ) ? $rubro['codigobpin'] : '__none__';
            $groups[ $bpid ][] = $rubro;
        }

        $bpid_codes = array_keys( $groups );
        $project_names = [];
        foreach ( $bpid_codes as $code ) {
            if ( '__none__' === $code ) {
                $project_names[ $code ] = __( 'Sin BPIN asignado', 'sysman-suite' );
                continue;
            }
            $proyecto = $repo->get_proyecto_bpin( $code );
            $project_names[ $code ] = $proyecto['nombre_proyecto'] ?? $code;
        }

        $first_bpid = $bpid_codes[0] ?? '';

        ob_start();
        ?>
        <div class="gn-ejec__count">
            <?php printf( esc_html__( '%d rubros en %d proyectos BPIN', 'sysman-suite' ), count( $rubros ), count( $groups ) ); ?>
        </div>
        <div class="gn-ejec__bpid-layout">
            <aside class="gn-ejec__bpid-sidebar">
                <h3 class="gn-ejec__bpid-sidebar-title"><?php esc_html_e( 'Proyectos BPIN', 'sysman-suite' ); ?></h3>
                <ul class="gn-ejec__bpid-list">
                    <?php foreach ( $groups as $bpid => $grp_rubros ) : ?>
                    <li class="gn-ejec__bpid-item<?php echo $bpid === $first_bpid ? ' active' : ''; ?>" data-bpid="<?php echo esc_attr( $bpid ); ?>">
                        <span class="gn-ejec__bpid-code"><?php echo '__none__' === $bpid ? '—' : esc_html( $bpid ); ?></span>
                        <span class="gn-ejec__bpid-name"><?php echo esc_html( $project_names[ $bpid ] ); ?></span>
                        <span class="gn-ejec__bpid-badge"><?php echo count( $grp_rubros ); ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </aside>
            <div class="gn-ejec__bpid-content">
                <?php foreach ( $groups as $bpid => $grp_rubros ) : ?>
                <div class="gn-ejec__bpid-group<?php echo $bpid === $first_bpid ? ' active' : ''; ?>" data-bpid-group="<?php echo esc_attr( $bpid ); ?>">
                    <?php echo self::render_rubros_list( $grp_rubros ); ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function render_rubros_list( array $rubros ): string {
        ob_start();
        ?>
        <ul class="gn-ejec__rubros">
            <?php foreach ( $rubros as $rubro ) : ?>
            <li class="gn-ejec__rubro" data-codigo="<?php echo esc_attr( $rubro['codigo'] ); ?>" data-codigobpin="<?php echo esc_attr( $rubro['codigobpin'] ?? '' ); ?>" aria-expanded="false">
                <button type="button" class="gn-ejec__rubro-toggle">
                    <span class="gn-ejec__rubro-arrow">&#9654;</span>
                    <span class="codigo"><?php echo esc_html( $rubro['codigo'] ); ?></span>
                    <span class="nombre"><?php echo esc_html( $rubro['nombre'] ); ?></span>
                    <span class="meta">
                        <?php echo esc_html( $rubro['destino'] ); ?>
                        &middot; <?php echo esc_html( $rubro['naturaleza'] ); ?>
                        <?php if ( ! empty( $rubro['codigobpin'] ) ) : ?>
                            &middot; BPIN <?php echo esc_html( $rubro['codigobpin'] ); ?>
                        <?php endif; ?>
                    </span>
                </button>
                <div class="gn-ejec__rubro-body" hidden></div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php
        return ob_get_clean();
    }
}
