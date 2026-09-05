<?php
/**
 * Catálogo de shortcodes del módulo Presupuesto.
 * Cada tarjeta muestra qué hace la vista y los shortcodes listos para copiar.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use SysmanSuite\Presupuesto\Repository;

$repo    = Repository::instance();
$ctx     = $repo->contexto();
$periodo = $ctx['anio'] > 0
    ? \SysmanSuite\Helpers::month_name( (int) $ctx['mes'] ) . ' ' . (int) $ctx['anio']
    : __( 'sin datos importados', 'sysman-suite' );

/**
 * Tarjetas del catálogo: título, descripción y lista de shortcodes.
 */
$tarjetas = [
    [
        'titulo' => __( 'Treemap de dependencias', 'sysman-suite' ),
        'desc'   => __( 'Distribución del valor entre todas las dependencias. Al hacer clic en una, baja a sus rubros; si está enlazado, actualiza el resto de la página.', 'sysman-suite' ),
        'codigos' => [
            '[sysman_pre_treemap]',
            '[sysman_pre_treemap campo="compromisos"]',
            '[sysman_pre_treemap campo="pagos" altura="620"]',
            '[sysman_pre_treemap enlazar="no"]',
        ],
    ],
    [
        'titulo' => __( 'Lista de dependencias', 'sysman-suite' ),
        'desc'   => __( 'Listado con número de rubros y valor por dependencia, con buscador. Al hacer clic fija la dependencia del filtro compartido.', 'sysman-suite' ),
        'codigos' => [
            '[sysman_pre_lista]',
            '[sysman_pre_lista campo="apropiacionvigente" limite="15"]',
            '[sysman_pre_lista buscador="no"]',
        ],
    ],
    [
        'titulo' => __( 'Ejecución por rubro', 'sysman-suite' ),
        'desc'   => __( 'Ejecución de una dependencia organizada por rubro. Cada rubro despliega su consolidado, sus modificaciones presupuestales y la cadena Disponibilidad → Compromiso → Obligación → Pago.', 'sysman-suite' ),
        'codigos' => [
            '[sysman_pre_ejecucion]',
            '[sysman_pre_ejecucion dependencia="SECRETARIA DE HACIENDA"]',
            '[sysman_pre_ejecucion dependencia="SECRETARIA DE EDUCACION" enlazar="no"]',
        ],
    ],
    [
        'titulo' => __( 'Explorador maestro-detalle', 'sysman-suite' ),
        'desc'   => __( 'Las dos vistas anteriores en un solo shortcode: dependencias a la izquierda y, al hacer clic, su ejecución completa a la derecha. Es la forma más rápida de publicar el módulo en una página.', 'sysman-suite' ),
        'codigos' => [
            '[sysman_pre_explora]',
            '[sysman_pre_explora altura="720"]',
            '[sysman_pre_explora campo="compromisos" enlazar="no"]',
        ],
    ],
    [
        'titulo' => __( 'Análisis automático', 'sysman-suite' ),
        'desc'   => __( 'Texto generado a partir de los datos de la vista. La descripción resume qué se muestra, el cualitativo interpreta concentración y niveles de ejecución, y el cuantitativo entrega los estadísticos. Si está enlazado y hay una dependencia elegida, el análisis pasa a sus rubros.', 'sysman-suite' ),
        'codigos' => [
            '[sysman_pre_analisis tipo="descripcion"]',
            '[sysman_pre_analisis tipo="cualitativo"]',
            '[sysman_pre_analisis tipo="cuantitativo"]',
            '[sysman_pre_analisis tipo="cualitativo" vista="rubros" dependencia="SECRETARIA DE SALUD"]',
        ],
    ],
    [
        'titulo' => __( 'Selector de filtro compartido', 'sysman-suite' ),
        'desc'   => __( 'Desplegable de dependencias que fija el filtro de la página. Colóquelo junto a los elementos que quiera controlar.', 'sysman-suite' ),
        'codigos' => [
            '[sysman_pre_selector]',
            '[sysman_pre_selector etiqueta="Secretaría:" todas="— Toda la entidad —"]',
        ],
    ],
];
?>
<div class="wrap sysman-admin-wrap">

    <div class="sysman-page-header">
        <div class="sysman-page-header-content">
            <div class="sysman-header-logo">
                <span class="dashicons dashicons-chart-pie" aria-hidden="true" style="font-size:32px;width:32px;height:32px;color:#1a5632;"></span>
            </div>
            <div>
                <h1 class="sysman-page-title"><?php esc_html_e( 'Presupuesto — Vistas prediseñadas', 'sysman-suite' ); ?></h1>
                <p class="sysman-page-subtitle">
                    <?php
                    printf(
                        /* translators: %s: periodo con datos */
                        esc_html__( 'Catálogo de vistas listas para usar. Periodo activo: %s', 'sysman-suite' ),
                        '<strong>' . esc_html( $periodo ) . '</strong>'
                    );
                    ?>
                </p>
            </div>
        </div>
    </div>

    <?php if ( $ctx['anio'] <= 0 ) : ?>
    <div class="notice notice-warning">
        <p>
            <?php esc_html_e( 'Todavía no hay datos de ejecución importados. Vaya a «Importar Datos» y ejecute una importación completa para que estas vistas muestren información.', 'sysman-suite' ); ?>
        </p>
    </div>
    <?php endif; ?>

    <div class="sysman-card">
        <div class="sysman-card-header">
            <h2>
                <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                <?php esc_html_e( 'Cómo funcionan', 'sysman-suite' ); ?>
            </h2>
        </div>
        <div class="sysman-card-body">
            <p>
                <?php esc_html_e( 'Copie un shortcode y péguelo en cualquier página o entrada. Las vistas no se crean a mano: se alimentan del último periodo importado y se actualizan solas con cada importación.', 'sysman-suite' ); ?>
            </p>
            <h4><?php esc_html_e( 'Atributos comunes', 'sysman-suite' ); ?></h4>
            <table class="widefat striped sysman-pre-atributos">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Atributo', 'sysman-suite' ); ?></th>
                        <th><?php esc_html_e( 'Por defecto', 'sysman-suite' ); ?></th>
                        <th><?php esc_html_e( 'Descripción', 'sysman-suite' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>campo</code></td>
                        <td><code>apropiacionvigente</code></td>
                        <td><?php esc_html_e( 'Métrica a visualizar. Ver la tabla de campos más abajo.', 'sysman-suite' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>enlazar</code></td>
                        <td><code>si</code></td>
                        <td><?php esc_html_e( 'Con "si" el elemento comparte el filtro con los demás de la página: al hacer clic en uno se actualizan los otros. Con "no" queda aislado y funciona por su cuenta.', 'sysman-suite' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>grupo</code></td>
                        <td><code>principal</code></td>
                        <td><?php esc_html_e( 'Permite tener dos conjuntos enlazados independientes en la misma página: use un grupo distinto en cada conjunto.', 'sysman-suite' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>anio</code> / <code>mes</code></td>
                        <td><?php esc_html_e( 'último importado', 'sysman-suite' ); ?></td>
                        <td><?php esc_html_e( 'Fijan el periodo. Si se omiten, la vista usa siempre el periodo más reciente con datos.', 'sysman-suite' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>compania</code></td>
                        <td><code>001</code></td>
                        <td><?php esc_html_e( 'Código de compañía en SYSMAN.', 'sysman-suite' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>titulo</code></td>
                        <td><?php esc_html_e( '(vacío)', 'sysman-suite' ); ?></td>
                        <td><?php esc_html_e( 'Encabezado opcional sobre el componente.', 'sysman-suite' ); ?></td>
                    </tr>
                </tbody>
            </table>

            <h4 style="margin-top:1.2rem;"><?php esc_html_e( 'Campos disponibles para el atributo campo', 'sysman-suite' ); ?></h4>
            <p class="sysman-pre-campos">
                <?php foreach ( Repository::CAMPOS as $clave => $etiqueta ) : ?>
                    <code title="<?php echo esc_attr( $etiqueta ); ?>"><?php echo esc_html( $clave ); ?></code>
                <?php endforeach; ?>
            </p>
        </div>
    </div>

    <div class="sysman-pre-catalogo">
        <?php foreach ( $tarjetas as $t ) : ?>
        <div class="sysman-card sysman-pre-tarjeta">
            <div class="sysman-card-header">
                <h2><?php echo esc_html( $t['titulo'] ); ?></h2>
            </div>
            <div class="sysman-card-body">
                <p class="sysman-pre-tarjeta__desc"><?php echo esc_html( $t['desc'] ); ?></p>
                <h4><?php esc_html_e( 'Shortcodes', 'sysman-suite' ); ?></h4>
                <?php foreach ( $t['codigos'] as $codigo ) : ?>
                <div class="sysman-pre-shortcode">
                    <input type="text" readonly value="<?php echo esc_attr( $codigo ); ?>" aria-label="<?php esc_attr_e( 'Shortcode', 'sysman-suite' ); ?>">
                    <button type="button" class="button sysman-pre-copiar" data-copiar="<?php echo esc_attr( $codigo ); ?>">
                        <?php esc_html_e( 'Copiar', 'sysman-suite' ); ?>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="sysman-card">
        <div class="sysman-card-header">
            <h2>
                <span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
                <?php esc_html_e( 'Ejemplo de página completa', 'sysman-suite' ); ?>
            </h2>
        </div>
        <div class="sysman-card-body">
            <p><?php esc_html_e( 'Pegue este bloque en una página para obtener un tablero enlazado: al hacer clic en el treemap o en la lista, el detalle y los análisis siguen la dependencia elegida.', 'sysman-suite' ); ?></p>
            <div class="sysman-pre-shortcode sysman-pre-shortcode--bloque">
                <textarea readonly rows="7" aria-label="<?php esc_attr_e( 'Bloque de ejemplo', 'sysman-suite' ); ?>">[sysman_pre_selector]

[sysman_pre_treemap titulo="Apropiación vigente por dependencia"]

[sysman_pre_analisis tipo="descripcion"]
[sysman_pre_analisis tipo="cualitativo"]

[sysman_pre_explora titulo="Ejecución detallada"]

[sysman_pre_analisis tipo="cuantitativo"]</textarea>
                <button type="button" class="button button-primary sysman-pre-copiar" data-copiar="[sysman_pre_selector]

[sysman_pre_treemap titulo=&quot;Apropiación vigente por dependencia&quot;]

[sysman_pre_analisis tipo=&quot;descripcion&quot;]
[sysman_pre_analisis tipo=&quot;cualitativo&quot;]

[sysman_pre_explora titulo=&quot;Ejecución detallada&quot;]

[sysman_pre_analisis tipo=&quot;cuantitativo&quot;]">
                    <?php esc_html_e( 'Copiar bloque', 'sysman-suite' ); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('.sysman-pre-copiar');
    if (!btn) { return; }
    var texto = btn.getAttribute('data-copiar');
    var original = btn.textContent;
    var ok = function () {
        btn.textContent = '<?php echo esc_js( __( '¡Copiado!', 'sysman-suite' ) ); ?>';
        setTimeout(function () { btn.textContent = original; }, 1500);
    };
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(texto).then(ok);
    } else {
        var ta = document.createElement('textarea');
        ta.value = texto;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); ok(); } catch (e) { /* sin portapapeles */ }
        document.body.removeChild(ta);
    }
});
</script>
