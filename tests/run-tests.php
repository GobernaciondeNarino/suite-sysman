<?php
/**
 * Standalone unit tests (no WordPress install required).
 *
 * Usage: php tests/run-tests.php
 * Exits 0 when every assertion passes, 1 otherwise.
 */

require __DIR__ . '/bootstrap.php';

$failures = 0;
$passed   = 0;

function check( string $name, bool $condition ): void {
    global $failures, $passed;
    if ( $condition ) {
        $passed++;
        return;
    }
    $failures++;
    echo "FAIL: {$name}\n";
}

// ─── Visualizer::validate_custom_query ───────────────────────────
$visualizer = new \SysmanSuite\Visualizer( new \SysmanSuite\Database() );

$allowed = [
    'SELECT codigocuenta AS label, SUM(pagos) AS value FROM wp_sysman_ejecucion_gastos GROUP BY codigocuenta',
    'select nombre, apropiado from wp_sysman_ejecucion_ingresos where anio = 2025 LIMIT 50',
    'SELECT pp.nombre FROM wp_sysman_plan_presupuestal pp JOIN wp_sysman_ejecucion_gastos eg ON pp.codigo = eg.codigocuenta',
];
foreach ( $allowed as $query ) {
    check( "permite: {$query}", null !== $visualizer->validate_custom_query( $query ) );
}

$blocked = [
    'DELETE FROM wp_sysman_ejecucion_gastos',
    'UPDATE wp_sysman_ejecucion_gastos SET pagos = 0',
    'SELECT * FROM wp_users',
    'SELECT * FROM wp_sysman_ejecucion_gastos; DROP TABLE wp_users',
    'SELECT * FROM wp_sysman_ejecucion_gastos -- comentario',
    'SELECT * FROM wp_sysman_ejecucion_gastos /* x */',
    'SELECT user_pass FROM wp_users UNION SELECT 1',
    'SELECT SLEEP(10) FROM wp_sysman_ejecucion_gastos',
    'SELECT nombre FROM wp_sysman_plan_presupuestal INTO OUTFILE "/tmp/x"',
    'SELECT LOAD_FILE("/etc/passwd") FROM wp_sysman_plan_presupuestal',
    'SELECT * FROM information_schema.tables',
    'INSERT INTO wp_sysman_ejecucion_gastos VALUES (1)',
    '',
    'no es una query',
];
foreach ( $blocked as $query ) {
    check( "bloquea: {$query}", null === $visualizer->validate_custom_query( $query ) );
}

// LIMIT appended when missing, preserved when present
check(
    'agrega LIMIT 1000 cuando falta',
    str_ends_with( (string) $visualizer->validate_custom_query( 'SELECT nombre FROM wp_sysman_plan_presupuestal' ), 'LIMIT 1000' )
);
check(
    'respeta LIMIT existente',
    str_ends_with( (string) $visualizer->validate_custom_query( 'SELECT nombre FROM wp_sysman_plan_presupuestal LIMIT 25' ), 'LIMIT 25' )
);

// ─── Helpers::month_name ─────────────────────────────────────────
check( 'month_name(1) = Enero', 'Enero' === \SysmanSuite\Helpers::month_name( 1 ) );
check( 'month_name(12) = Diciembre', 'Diciembre' === \SysmanSuite\Helpers::month_name( 12 ) );
check( 'month_name(13) devuelve el número', '13' === \SysmanSuite\Helpers::month_name( 13 ) );

// ─── Helpers::rate_limit_check ───────────────────────────────────
$GLOBALS['__test_user_can']   = false;
$GLOBALS['__test_transients'] = [];
$_SERVER['REMOTE_ADDR']       = '203.0.113.7';

$results = [];
for ( $i = 0; $i < 6; $i++ ) {
    $results[] = \SysmanSuite\Helpers::rate_limit_check( 'test_bucket', 5, 60 );
}
check( 'rate limit: primeras 5 pasan', [ true, true, true, true, true ] === array_slice( $results, 0, 5 ) );
check( 'rate limit: la sexta se bloquea', false === $results[5] );
check( 'rate limit: otro bucket no comparte contador', true === \SysmanSuite\Helpers::rate_limit_check( 'other_bucket', 5, 60 ) );

$GLOBALS['__test_user_can'] = true;
check( 'rate limit: admin nunca se bloquea', true === \SysmanSuite\Helpers::rate_limit_check( 'test_bucket', 5, 60 ) );
$GLOBALS['__test_user_can'] = false;

// ─── CSV formula-injection guard (DatosAbiertos) ─────────────────
$module = \SysmanSuite\DatosAbiertos\DatosAbiertosModule::instance();
$method = new ReflectionMethod( $module, 'sanitize_csv_cell' );
$method->setAccessible( true );

check( 'csv: = se neutraliza', "'=SUM(A1)" === $method->invoke( $module, '=SUM(A1)' ) );
check( 'csv: + se neutraliza', "'+1" === $method->invoke( $module, '+1' ) );
check( 'csv: @ se neutraliza', "'@cmd" === $method->invoke( $module, '@cmd' ) );
check( 'csv: texto normal intacto', 'Secretaría TIC' === $method->invoke( $module, 'Secretaría TIC' ) );
check( 'csv: vacío intacto', '' === $method->invoke( $module, '' ) );

// ─── save_meta: los paneles Tablas / Vistas no se pisan ──────────
// Regresión de v5.10.0: en modo Vista el panel de Tablas (oculto) enviaba
// sysman_value_columns y contaminaba —o vaciaba— la configuración guardada.
function guardar( array $post, \SysmanSuite\Visualizer $viz, int $post_id ): array {
    $GLOBALS['__test_meta'][ $post_id ] = [];
    $GLOBALS['__test_user_can']         = true;
    $_POST                              = array_merge( [ 'sysman_chart_nonce' => 'valid-nonce' ], $post );
    $viz->save_meta( $post_id );
    return $GLOBALS['__test_meta'][ $post_id ];
}

$meta = guardar( [
    'sysman_data_source_mode'      => 'vista',
    'sysman_vista_value_columns'   => [ 'apropiacionvigente', 'pagos' ],
    'sysman_vista_aggregate'       => 'AVG',
    'sysman_value_columns'         => [ '' ],          // panel Tablas oculto
    'sysman_aggregate'             => 'SUM',
    'sysman_color_column'          => 'destino',       // resto del panel Tablas
    'sysman_vista_dependencia'     => 'SECRETARIA TIC',
], $visualizer, 101 );

check( 'vista: guarda las columnas del panel Vista',
    [ 'apropiacionvigente', 'pagos' ] === ( $meta['_sysman_value_columns'] ?? null ) );
check( 'vista: usa la agregación del panel Vista',
    'AVG' === ( $meta['_sysman_aggregate'] ?? null ) );
check( 'vista: descarta la columna de color del panel Tablas',
    ! isset( $meta['_sysman_color_column'] ) );
check( 'vista: conserva la dependencia',
    'SECRETARIA TIC' === ( $meta['_sysman_vista_dependencia'] ?? null ) );

$meta = guardar( [
    'sysman_data_source_mode'    => 'table',
    'sysman_data_table'          => 'wp_sysman_ejecucion_gastos',
    'sysman_group_column'        => 'nombrerubro',
    'sysman_value_columns'       => [ 'compromisos' ],
    'sysman_aggregate'           => 'SUM',
    'sysman_vista_value_columns' => [ 'apropiacionvigente', 'pagos' ], // panel Vista oculto
    'sysman_vista_aggregate'     => 'AVG',
], $visualizer, 102 );

check( 'tabla: guarda las columnas del panel Tablas',
    [ 'compromisos' ] === ( $meta['_sysman_value_columns'] ?? null ) );
check( 'tabla: usa la agregación del panel Tablas',
    'SUM' === ( $meta['_sysman_aggregate'] ?? null ) );

// Un nonce inválido no debe escribir nada.
$GLOBALS['__test_meta'][103] = [];
$_POST = [ 'sysman_chart_nonce' => 'malo', 'sysman_data_source_mode' => 'vista' ];
$visualizer->save_meta( 103 );
check( 'nonce inválido: no guarda nada', [] === $GLOBALS['__test_meta'][103] );

$_POST = [];

// ─── Presupuesto: cadena documental DIS → RES → OBL → EGR ────────
$repoPre = \SysmanSuite\Presupuesto\Repository::instance();

function doc( $numero, $tipo, $afectaTipo = '', $afectaNum = '', $valor = 0 ): array {
    return [
        'numero' => $numero, 'tipocpte' => $tipo,
        'tipocpteafect' => $afectaTipo, 'cmpteafectado' => $afectaNum,
        'fecha' => '2026-05-10', 'tercero' => '900123', 'nombretercero' => 'CONSORCIO X',
        'descripcion' => 'desc', 'nrodocumento' => 'CTO-1',
        'valordebito' => $valor, 'valorcredito' => 0, 'saldoporejecutaresp' => 0,
    ];
}

$filas = [
    doc( 'D1', 'DIS', '', '', 1000 ),
    doc( 'R1', 'RES', 'DIS', 'D1', 800 ),
    doc( 'O1', 'OBL', 'RES', 'R1', 600 ),
    doc( 'E1', 'EGR', 'OBL', 'O1', 500 ),
    doc( 'R2', 'RES', 'DIS', 'D1', 150 ),
    doc( 'D2', 'DIS', '', '', 2000 ),
    doc( 'X9', 'OBL', 'RES', 'NO-EXISTE', 90 ),  // padre fuera del periodo
];

$cad = $repoPre->armar_cadena( $filas );

check( 'cadena: dos disponibilidades raíz', 2 === count( $cad['documentos'] ) );
check( 'cadena: la primera DIS tiene 2 compromisos', 2 === count( $cad['documentos'][0]['hijos'] ) );
check( 'cadena: el compromiso R1 tiene su obligación',
    1 === count( $cad['documentos'][0]['hijos'][0]['hijos'] ) );
check( 'cadena: la obligación O1 tiene su egreso',
    1 === count( $cad['documentos'][0]['hijos'][0]['hijos'][0]['hijos'] ) );
check( 'cadena: el egreso es de tipo EGR',
    'EGR' === $cad['documentos'][0]['hijos'][0]['hijos'][0]['hijos'][0]['tipo'] );
check( 'cadena: la segunda DIS no tiene hijos', 0 === count( $cad['documentos'][1]['hijos'] ) );
check( 'cadena: el documento sin padre queda como huérfano',
    1 === count( $cad['huerfanos'] ) && 'X9' === $cad['huerfanos'][0]['numero'] );
check( 'cadena: conteo por tipo correcto',
    2 === $cad['conteo']['DIS'] && 2 === $cad['conteo']['RES']
    && 2 === $cad['conteo']['OBL'] && 1 === $cad['conteo']['EGR'] );
check( 'cadena: sin filas devuelve estructura vacía',
    [] === $repoPre->armar_cadena( [] )['documentos'] );

// Datos malformados (ciclo) no deben colgar la construcción.
$ciclo = [ doc( 'A', 'DIS', 'DIS', 'A', 10 ) ];
$res_ciclo = $repoPre->armar_cadena( $ciclo );
check( 'cadena: un ciclo no provoca recursión infinita', 1 === count( $res_ciclo['documentos'] ) );

// ─── Presupuesto: validación del campo métrico ───────────────────
check( 'campo válido se acepta',
    'compromisos' === \SysmanSuite\Presupuesto\Repository::validar_campo( 'compromisos' ) );
check( 'campo inválido cae al valor por defecto',
    'apropiacionvigente' === \SysmanSuite\Presupuesto\Repository::validar_campo( 'pagos; DROP TABLE x' ) );
check( 'campo inexistente cae al valor por defecto',
    'apropiacionvigente' === \SysmanSuite\Presupuesto\Repository::validar_campo( 'inventado' ) );

// ─── Presupuesto: motor de análisis ──────────────────────────────
$ctxPre = [ 'compania' => '001', 'anio' => 2026, 'mes' => 5 ];
$datosPre = [
    'filas' => [
        [ 'label' => 'EDUCACION', 'value' => 5000.0 ],
        [ 'label' => 'SALUD', 'value' => 3000.0 ],
        [ 'label' => 'VIAS', 'value' => 1500.0 ],
        [ 'label' => 'CULTURA', 'value' => 500.0 ],
    ],
    'totales' => [ 'apropiacionvigente' => 10000.0, 'compromisos' => 7000.0, 'obligacion' => 5000.0, 'pagos' => 4000.0 ],
];

$optsGas = [ 'campo' => 'apropiacionvigente', 'campo_label' => 'Apropiación Vigente', 'modulo' => 'gastos' ];
foreach ( [ 'descripcion', 'cualitativo', 'cuantitativo' ] as $t ) {
    $a = \SysmanSuite\Presupuesto\Analysis::generar( $t, 'dimensiones', $ctxPre, $datosPre, $optsGas );
    check( "análisis {$t}: devuelve título", ! empty( $a['titulo'] ) );
    check( "análisis {$t}: devuelve al menos un párrafo", ! empty( $a['parrafos'] ) );
}

$cuant = \SysmanSuite\Presupuesto\Analysis::generar( 'cuantitativo', 'dimensiones', $ctxPre, $datosPre, $optsGas );
$labels = array_column( $cuant['metricas'], 'label' );
check( 'cuantitativo: incluye el total', in_array( 'Total', $labels, true ) );
check( 'cuantitativo: incluye el % comprometido', in_array( '% Comprometido', $labels, true ) );
$total = null;
foreach ( $cuant['metricas'] as $m ) { if ( 'Total' === $m['label'] ) { $total = $m['crudo']; } }
check( 'cuantitativo: el total suma las filas', abs( $total - 10000.0 ) < 0.01 );

$vacio = \SysmanSuite\Presupuesto\Analysis::generar( 'cualitativo', 'dimensiones', $ctxPre, [ 'filas' => [] ], [] );
check( 'análisis sin datos no revienta', ! empty( $vacio['parrafos'] ) );

// Un tipo desconocido cae a descripción en lugar de fallar.
$fallback = \SysmanSuite\Presupuesto\Analysis::generar( 'inventado', 'dimensiones', $ctxPre, $datosPre, [] );
check( 'tipo de análisis desconocido cae a descripción', 'Descripción' === $fallback['titulo'] );

// ─── Presupuesto: campos extra del atributo tooltip ──────────────
$extra = \SysmanSuite\Presupuesto\Repository::validar_extra( [ 'compromisos', 'pagos', 'inventado', 'pagos', 'x; DROP TABLE y' ] );
check( 'tooltip: conserva los campos válidos', [ 'compromisos', 'pagos' ] === $extra );
check( 'tooltip: descarta lo no permitido y los duplicados', 2 === count( $extra ) );
check( 'tooltip: lista vacía devuelve vacío', [] === \SysmanSuite\Presupuesto\Repository::validar_extra( [] ) );

// ─── Ingresos: whitelist de campos y dimensiones ─────────────────
$ing = '\SysmanSuite\Presupuesto\IngresosRepository';
check( 'ingresos: campo válido se acepta', 'recaudosacumulados' === $ing::validar_campo( 'recaudosacumulados' ) );
check( 'ingresos: campo inválido cae al valor por defecto', 'totalpresupuesto' === $ing::validar_campo( 'inventado' ) );
check( 'ingresos: porcrecaudado NO es sumable', ! array_key_exists( 'porcrecaudado', $ing::CAMPOS ) );
check( 'ingresos: dimensión válida se acepta', 'fuenterecurso' === $ing::validar_dimension( 'fuenterecurso' ) );
check( 'ingresos: dimensión inválida cae a tiporecurso', 'tiporecurso' === $ing::validar_dimension( 'nombredependencia' ) );

// ─── Ingresos: el análisis usa el vocabulario de recaudo ─────────
$datosIng = [
    'filas' => [
        [ 'label' => 'Recursos propios', 'value' => 6000.0 ],
        [ 'label' => 'SGP', 'value' => 3000.0 ],
        [ 'label' => 'Regalías', 'value' => 1000.0 ],
    ],
    'totales' => [ 'totalpresupuesto' => 10000.0, 'recaudosacumulados' => 6500.0, 'recaudosmes' => 800.0, 'porrecaudar' => 3500.0 ],
];
$optsIng = [ 'campo' => 'totalpresupuesto', 'campo_label' => 'Total Presupuesto', 'modulo' => 'ingresos', 'dimension_label' => 'Tipo de recurso' ];

$cualIng = \SysmanSuite\Presupuesto\Analysis::generar( 'cualitativo', 'dimensiones', $ctxPre, $datosIng, $optsIng );
$textoIng = implode( ' ', $cualIng['parrafos'] );
check( 'ingresos: el cualitativo habla de recaudo', str_contains( $textoIng, 'recaudado' ) );
check( 'ingresos: el cualitativo NO habla de compromisos', ! str_contains( $textoIng, 'comprometido' ) );

$cuantIng = \SysmanSuite\Presupuesto\Analysis::generar( 'cuantitativo', 'dimensiones', $ctxPre, $datosIng, $optsIng );
$labelsIng = array_column( $cuantIng['metricas'], 'label' );
check( 'ingresos: el cuantitativo incluye % Recaudado', in_array( '% Recaudado', $labelsIng, true ) );
check( 'ingresos: el cuantitativo NO incluye % Comprometido', ! in_array( '% Comprometido', $labelsIng, true ) );

// El de gastos sigue hablando de ejecución.
$cualGas = \SysmanSuite\Presupuesto\Analysis::generar( 'cualitativo', 'dimensiones', $ctxPre, $datosPre, $optsGas );
check( 'gastos: el cualitativo habla de compromisos',
    str_contains( implode( ' ', $cualGas['parrafos'] ), 'comprometido' ) );

// Concordancia de género en el detalle (rubros es masculino).
$detGas = \SysmanSuite\Presupuesto\Analysis::generar( 'cualitativo', 'detalle', $ctxPre, $datosPre, $optsGas );
$textoDet = implode( ' ', $detGas['parrafos'] );
check( 'detalle de gastos: concordancia "los tres primeros rubros"',
    ! str_contains( $textoDet, 'las tres primeras rubros' ) );

// ─── Ingresos: concordancia de la dimensión en el análisis ───────
check( 'ingresos: "tipo de recurso" es masculino', ! $ing::es_femenino( 'tiporecurso' ) );
check( 'ingresos: "fuente de recurso" es femenino', $ing::es_femenino( 'fuenterecurso' ) );

$optsFuente = [
    'campo'              => 'totalpresupuesto',
    'campo_label'        => 'Total Presupuesto',
    'modulo'             => 'ingresos',
    'dimension_label'    => $ing::etiqueta_dimension( 'fuenterecurso' ),
    'dimension_plural'   => $ing::etiqueta_plural( 'fuenterecurso' ),
    'dimension_femenino' => $ing::es_femenino( 'fuenterecurso' ),
];
$cualFuente = \SysmanSuite\Presupuesto\Analysis::generar( 'cualitativo', 'dimensiones', $ctxPre, $datosIng, $optsFuente );
$textoFuente = implode( ' ', $cualFuente['parrafos'] );
check( 'ingresos: plural correcto "fuentes de recurso"', str_contains( $textoFuente, 'fuentes de recurso' ) );
check( 'ingresos: no pluraliza añadiendo "s" al final', ! str_contains( $textoFuente, 'fuente de recursos' ) );
check( 'ingresos: concordancia femenina "las tres primeras"', str_contains( $textoFuente, 'las tres primeras' ) );

$optsTipo = array_merge( $optsFuente, [
    'dimension_label'    => $ing::etiqueta_dimension( 'tiporecurso' ),
    'dimension_plural'   => $ing::etiqueta_plural( 'tiporecurso' ),
    'dimension_femenino' => $ing::es_femenino( 'tiporecurso' ),
] );
$cualTipo = \SysmanSuite\Presupuesto\Analysis::generar( 'cualitativo', 'dimensiones', $ctxPre, $datosIng, $optsTipo );
$textoTipo = implode( ' ', $cualTipo['parrafos'] );
check( 'ingresos: plural correcto "tipos de recurso"', str_contains( $textoTipo, 'tipos de recurso' ) );
check( 'ingresos: concordancia masculina "los tres primeros"', str_contains( $textoTipo, 'los tres primeros' ) );

// ─── El cuantitativo se lee como prosa, no como tabla ────────────
// La vista de análisis ya no pinta el cuadro de métricas: todas las cifras
// tienen que estar dentro de los párrafos.
$textoCuant = implode( ' ', $cuant['parrafos'] );
check( 'cuantitativo: la prosa incluye el total', str_contains( $textoCuant, \SysmanSuite\Presupuesto\Analysis::moneda( 10000.0 ) ) );
check( 'cuantitativo: la prosa incluye los porcentajes de ejecución',
    str_contains( $textoCuant, 'comprometido' ) && str_contains( $textoCuant, 'pagado' ) );
check( 'cuantitativo: la prosa incluye la dispersión', str_contains( $textoCuant, 'desviación estándar' ) );

$cuantIngProsa = implode( ' ', $cuantIng['parrafos'] );
check( 'cuantitativo de ingresos: la prosa habla de recaudo', str_contains( $cuantIngProsa, 'recaudado' ) );
check( 'cuantitativo de ingresos: NO habla de compromisos', ! str_contains( $cuantIngProsa, 'comprometido' ) );

// ─── Resumen ─────────────────────────────────────────────────────
echo "\n{$passed} aserciones OK, {$failures} fallos\n";
exit( $failures > 0 ? 1 : 0 );
