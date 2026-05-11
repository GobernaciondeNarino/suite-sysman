<?php
namespace SysmanSuite\Ejecucion;

if ( ! defined( 'ABSPATH' ) ) exit;

class AccordionRenderer {

    public static function render( int $post_id ): string {
        $post = get_post( $post_id );
        if ( ! $post || 'gn_ejecucion' !== $post->post_type ) {
            return '<p>' . esc_html__( 'Seguimiento no encontrado.', 'sysman-suite' ) . '</p>';
        }

        $dependencia = get_post_meta( $post_id, '_gn_dependencia', true );
        $vigencia    = get_post_meta( $post_id, '_gn_vigencia', true );
        $anio        = (int) get_post_meta( $post_id, '_gn_anio', true );
        $mes         = (int) get_post_meta( $post_id, '_gn_mes', true );
        $compania    = get_post_meta( $post_id, '_gn_compania', true ) ?: '001';

        $repo   = Repository::instance();
        $rubros = $repo->get_rubros( $post_id );

        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
        $mes_nombre = $meses[ $mes ] ?? $mes;

        ob_start();
        ?>
        <div class="gn-ejec" data-post-id="<?php echo esc_attr( $post_id ); ?>" data-anio="<?php echo esc_attr( $anio ); ?>" data-mes="<?php echo esc_attr( $mes ); ?>">
            <header class="gn-ejec__header">
                <h2><?php echo esc_html( $post->post_title ); ?></h2>
                <span class="gn-ejec__periodo"><?php echo esc_html( $dependencia ); ?> &mdash; <?php echo esc_html( $mes_nombre ); ?> <?php echo esc_html( $anio ); ?><?php if ( $vigencia ) : ?> &mdash; <?php echo esc_html( $vigencia ); ?><?php endif; ?></span>
            </header>

            <?php if ( empty( $rubros ) ) : ?>
                <div class="gn-ejec__empty">
                    <span class="dashicons dashicons-info-outline"></span>
                    <?php esc_html_e( 'No se encontraron rubros para esta dependencia y periodo. Verifique que los datos estén sincronizados.', 'sysman-suite' ); ?>
                </div>
            <?php else : ?>
                <div class="gn-ejec__count">
                    <?php printf( esc_html__( '%d rubros encontrados', 'sysman-suite' ), count( $rubros ) ); ?>
                </div>
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
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
