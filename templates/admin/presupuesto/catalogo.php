<?php
/**
 * Catálogo de shortcodes del módulo Presupuesto.
 * Sirve a Gastos e Ingresos según la variable $modulo que fija el llamador.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use SysmanSuite\Presupuesto\IngresosRepository;
use SysmanSuite\Presupuesto\Repository;

$modulo   = isset( $modulo ) && 'ingresos' === $modulo ? 'ingresos' : 'gastos';
$ingresos = 'ingresos' === $modulo;
$pre      = 'sysman_' . $modulo;

$repo   = $ingresos ? IngresosRepository::instance() : Repository::instance();
$campos = $ingresos ? IngresosRepository::CAMPOS : Repository::CAMPOS;
$ctx    = $repo->contexto();

$periodo = $ctx['anio'] > 0
    ? \SysmanSuite\Helpers::month_name( (int) $ctx['mes'] ) . ' ' . (int) $ctx['anio']
    : __( 'sin datos importados', 'sysman-suite' );

$grupo_label  = $ingresos ? __( 'tipo de recurso', 'sysman-suite' ) : __( 'dependencia', 'sysman-suite' );
$grupo_plural = $ingresos ? __( 'tipos de recurso', 'sysman-suite' ) : __( 'dependencias', 'sysman-suite' );
$hoja_plural  = $ingresos ? __( 'cuentas de ingreso', 'sysman-suite' ) : __( 'rubros', 'sysman-suite' );
$campo_def    = $ingresos ? 'totalpresupuesto' : 'apropiacionvigente';
$ejemplo_val  = $ingresos ? 'Recursos propios' : 'SECRETARIA DE HACIENDA';

$tarjetas = [
    [
        'titulo'  => sprintf( __( 'Treemap de %s', 'sysman-suite' ), $grupo_plural ),
        'desc'    => sprintf(
            __( 'Distribución del valor entre %s. Al hacer clic en uno baja a sus %s; si está enlazado, actualiza el resto de la página.', 'sysman-suite' ),
            $grupo_plural,
            $hoja_plural
        ),
        'codigos' => array_values( array_filter( [
            "[{$pre}_treemap]",
            $ingresos ? "[{$pre}_treemap campo=\"recaudosacumulados\"]" : "[{$pre}_treemap campo=\"compromisos\"]",
            "[{$pre}_treemap tooltip=\"" . ( $ingresos ? 'apropiado,recaudosacumulados,porrecaudar' : 'apropiacionvigente,compromisos,pagos' ) . "\"]",
            $ingresos ? "[{$pre}_treemap dimension=\"fuenterecurso\"]" : null,
            "[{$pre}_treemap enlazar=\"no\"]",
        ] ) ),
    ],
    [
        'titulo'  => sprintf( __( 'Lista de %s', 'sysman-suite' ), $grupo_plural ),
        'desc'    => sprintf(
            __( 'Listado con el número de %s y el valor al frente del nombre, con buscador. Al hacer clic fija el filtro compartido.', 'sysman-suite' ),
            $hoja_plural
        ),
        'codigos' => array_values( array_filter( [
            "[{$pre}_lista]",
            "[{$pre}_lista campo=\"{$campo_def}\" limite=\"15\"]",
            $ingresos ? "[{$pre}_lista dimension=\"fuenterecurso\"]" : null,
            "[{$pre}_lista buscador=\"no\"]",
        ] ) ),
    ],
    [
        'titulo'  => $ingresos
            ? __( 'Detalle por cuenta', 'sysman-suite' )
            : __( 'Ejecución por rubro', 'sysman-suite' ),
        'desc'    => $ingresos
            ? __( 'Cuentas del tipo o fuente elegida. Cada cuenta despliega su apropiado, modificaciones, presupuesto definitivo, la composición del recaudo y el porcentaje recaudado.', 'sysman-suite' )
            : __( 'Ejecución de una dependencia organizada por rubro. Cada rubro despliega su consolidado, sus modificaciones presupuestales y la cadena Disponibilidad → Compromiso → Obligación → Pago.', 'sysman-suite' ),
        'codigos' => [
            "[{$pre}_ejecucion]",
            "[{$pre}_ejecucion valor=\"{$ejemplo_val}\"]",
            "[{$pre}_ejecucion valor=\"{$ejemplo_val}\" enlazar=\"no\"]",
        ],
    ],
    [
        'titulo'  => __( 'Explorador maestro-detalle', 'sysman-suite' ),
        'desc'    => sprintf(
            __( 'Las dos vistas anteriores en un solo shortcode: %s a la izquierda y, al hacer clic, su detalle completo a la derecha. Es la forma más rápida de publicar el módulo en una página.', 'sysman-suite' ),
            $grupo_plural
        ),
        'codigos' => [
            "[{$pre}_explora]",
            "[{$pre}_explora altura=\"720\"]",
            "[{$pre}_explora enlazar=\"no\"]",
        ],
    ],
    [
        'titulo'  => __( 'Análisis automático', 'sysman-suite' ),
        'desc'    => __( 'Texto generado a partir de los datos de la vista. La descripción resume qué se muestra, el cualitativo interpreta concentración y niveles de ejecución, y el cuantitativo entrega los estadísticos. Si está enlazado y hay un valor elegido, el análisis pasa a su detalle.', 'sysman-suite' ),
        'codigos' => [
            "[{$pre}_analisis tipo=\"descripcion\"]",
            "[{$pre}_analisis tipo=\"cualitativo\"]",
            "[{$pre}_analisis tipo=\"cuantitativo\"]",
            "[{$pre}_analisis tipo=\"cualitativo\" valor=\"{$ejemplo_val}\"]",
        ],
    ],
    [
        'titulo'  => __( 'Selector de filtro compartido', 'sysman-suite' ),
        'desc'    => sprintf(
            __( 'Desplegable de %s que fija el filtro de la página. Colóquelo junto a los elementos que quiera controlar.', 'sysman-suite' ),
            $grupo_plural
        ),
        'codigos' => array_values( array_filter( [
            "[{$pre}_selector]",
            "[{$pre}_selector etiqueta=\"" . ( $ingresos ? 'Recurso:' : 'Secretaría:' ) . "\" todas=\"— Todo —\"]",
            $ingresos ? "[{$pre}_selector dimension=\"fuenterecurso\"]" : null,
        ] ) ),
    ],
];

$bloque_ejemplo = "[{$pre}_selector]\n\n[{$pre}_treemap titulo=\"Distribución por " . $grupo_label . "\"]\n\n"
    . "[{$pre}_analisis tipo=\"descripcion\"]\n[{$pre}_analisis tipo=\"cualitativo\"]\n\n"
    . "[{$pre}_explora titulo=\"Detalle\"]\n\n[{$pre}_analisis tipo=\"cuantitativo\"]";
?>
<div class="wrap sysman-admin-wrap">

    <div class="sysman-page-header">
        <div class="sysman-page-header-content">
            <div class="sysman-header-logo">
                <span class="dashicons dashicons-<?php echo $ingresos ? 'money-alt' : 'chart-pie'; ?>" aria-hidden="true" style="font-size:32px;width:32px;height:32px;color:#1a5632;"></span>
            </div>
            <div>
                <h1 class="sysman-page-title">
                    <?php
                    echo esc_html( $ingresos
                        ? __( 'Ingresos — Vistas prediseñadas', 'sysman-suite' )
                        : __( 'Gastos — Vistas prediseñadas', 'sysman-suite' ) );
                    ?>
                </h1>
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
            <?php
            echo esc_html( $ingresos
                ? __( 'Todavía no hay datos de ingresos importados. Vaya a «Importar Datos» e importe el informe «Ejecución de Ingresos».', 'sysman-suite' )
                : __( 'Todavía no hay datos de ejecución importados. Vaya a «Importar Datos» y ejecute una importación completa.', 'sysman-suite' ) );
            ?>
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
                <?php
                echo esc_html( sprintf(
                    /* translators: %s: agrupación del módulo */
                    __( 'Copie un shortcode y péguelo en cualquier página o entrada. Las vistas se alimentan del último periodo importado y se agrupan por %s.', 'sysman-suite' ),
                    $grupo_label
                ) );
                ?>
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
                        <td><code><?php echo esc_html( $campo_def ); ?></code></td>
                        <td><?php esc_html_e( 'Métrica a visualizar. Ver la lista de campos más abajo.', 'sysman-suite' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>tooltip</code></td>
                        <td><?php esc_html_e( '(vacío)', 'sysman-suite' ); ?></td>
                        <td><?php esc_html_e( 'Campos adicionales a mostrar al pasar el cursor sobre el gráfico, separados por comas. Ejemplo: tooltip="apropiacionvigente,compromisos,pagos".', 'sysman-suite' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>enlazar</code></td>
                        <td><code>si</code></td>
                        <td><?php esc_html_e( 'Con "si" el elemento comparte el filtro con los demás de la página: al hacer clic en uno se actualizan los otros. Con "no" queda aislado.', 'sysman-suite' ); ?></td>
                    </tr>
                    <?php if ( $ingresos ) : ?>
                    <tr>
                        <td><code>dimension</code></td>
                        <td><code>tiporecurso</code></td>
                        <td><?php esc_html_e( 'Cómo se agrupan los ingresos: tiporecurso o fuenterecurso.', 'sysman-suite' ); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td><code>valor</code></td>
                        <td><?php esc_html_e( '(vacío)', 'sysman-suite' ); ?></td>
                        <td>
                            <?php
                            echo esc_html( sprintf(
                                /* translators: %s: agrupación del módulo */
                                __( 'Fija de entrada un %s concreto en lugar de esperar a que el usuario elija.', 'sysman-suite' ),
                                $grupo_label
                            ) );
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td><code>grupo</code></td>
                        <td><code>principal</code></td>
                        <td><?php esc_html_e( 'Permite tener dos conjuntos enlazados independientes en la misma página. Gastos e Ingresos nunca se enlazan entre sí.', 'sysman-suite' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>anio</code> / <code>mes</code></td>
                        <td><?php esc_html_e( 'último importado', 'sysman-suite' ); ?></td>
                        <td><?php esc_html_e( 'Fijan el periodo. Si se omiten, la vista usa siempre el periodo más reciente con datos.', 'sysman-suite' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>titulo</code> / <code>altura</code> / <code>limite</code></td>
                        <td>—</td>
                        <td><?php esc_html_e( 'Encabezado opcional, alto en píxeles y número máximo de elementos.', 'sysman-suite' ); ?></td>
                    </tr>
                </tbody>
            </table>

            <h4 style="margin-top:1.2rem;"><?php esc_html_e( 'Campos disponibles para campo y tooltip', 'sysman-suite' ); ?></h4>
            <p class="sysman-pre-campos">
                <?php foreach ( $campos as $clave => $etiqueta ) : ?>
                    <code title="<?php echo esc_attr( $etiqueta ); ?>"><?php echo esc_html( $clave ); ?></code>
                <?php endforeach; ?>
            </p>
            <?php if ( $ingresos ) : ?>
            <p class="description">
                <?php esc_html_e( 'El porcentaje recaudado no se lista como campo porque no se puede sumar entre registros: el módulo lo calcula como recaudos acumulados sobre presupuesto definitivo.', 'sysman-suite' ); ?>
            </p>
            <?php endif; ?>
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
            <p><?php esc_html_e( 'Pegue este bloque en una página para obtener un tablero enlazado: al hacer clic en el treemap o en la lista, el detalle y los análisis siguen el elemento elegido.', 'sysman-suite' ); ?></p>
            <div class="sysman-pre-shortcode sysman-pre-shortcode--bloque">
                <textarea readonly rows="9" aria-label="<?php esc_attr_e( 'Bloque de ejemplo', 'sysman-suite' ); ?>"><?php echo esc_textarea( $bloque_ejemplo ); ?></textarea>
                <button type="button" class="button button-primary sysman-pre-copiar" data-copiar="<?php echo esc_attr( $bloque_ejemplo ); ?>">
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
