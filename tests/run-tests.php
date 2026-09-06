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

// ─── Redacción: un solo párrafo por análisis ─────────────────────
$An = '\SysmanSuite\Presupuesto\Analysis';
foreach ( [ 'descripcion', 'cualitativo', 'cuantitativo' ] as $t ) {
    $unop = $An::generar( $t, 'dimensiones', $ctxPre, $datosPre, $optsGas );
    check( "análisis {$t}: un solo párrafo", 1 === count( $unop['parrafos'] ) );
    check( "análisis {$t}: termina en punto", str_ends_with( trim( $unop['parrafos'][0] ), '.' ) );
    check( "análisis {$t}: no encadena punto y coma con punto",
        ! str_contains( $unop['parrafos'][0], ';.' ) && ! str_contains( $unop['parrafos'][0], ',.' ) );
}

$desc = $An::generar( 'descripcion', 'dimensiones', $ctxPre, $datosPre, $optsGas );
check( 'descripción: encabeza con la entidad', str_starts_with( $desc['parrafos'][0], 'La Gobernación de Nariño' ) );
check( 'descripción: el mes va en minúscula dentro de la frase', str_contains( $desc['parrafos'][0], 'a mayo de 2026' ) );
check( 'descripción: nombra la mayor asignación', str_contains( $desc['parrafos'][0], 'Educacion' ) );

// ─── Nombres en mayúscula sostenida → forma legible ──────────────
check( 'nombre: capitaliza y respeta conectores',
    'Secretaria de Educacion' === $An::nombre_legible( 'SECRETARIA DE EDUCACION' ) );
check( 'nombre: la "y" también es conector',
    'Secretaria de Infraestructura y Minas' === $An::nombre_legible( 'SECRETARIA DE INFRAESTRUCTURA Y MINAS' ) );
check( 'nombre: conserva las siglas conocidas',
    'SGP-Educacion' === $An::nombre_legible( 'SGP-EDUCACION' ) );
check( 'nombre: conserva siglas cortas', 'Fondo de TIC' === $An::nombre_legible( 'FONDO DE TIC' ) );
check( 'nombre: no toca lo que ya viene en minúsculas',
    'Recursos propios' === $An::nombre_legible( 'Recursos propios' ) );
check( 'nombre: cadena vacía no revienta', '' === $An::nombre_legible( '' ) );

// ─── Ámbito de importación: sin duplicados entre borrado e inserción ─
$IS = '\\SysmanSuite\\Import_Scope';

check( 'importación: cubre las cinco tablas', 5 === count( $IS::tablas() ) );
check( 'importación: gastos se identifica por código de cuenta',
    [ 'compania', 'anio', 'mes', 'codigocuenta' ] === $IS::clave_natural( 'sysman_ejecucion_gastos' ) );
check( 'importación: tabla desconocida no tiene clave', [] === $IS::clave_natural( 'wp_posts' ) );

// El borrado previo tiene que cubrir exactamente lo que se va a insertar: si
// se queda corto, las filas viejas sobreviven y las cifras salen infladas.
[ $where, $params ] = $IS::scope_borrado( 'sysman_ejecucion_gastos', '001', 2026, 9 );
check( 'borrado: gastos se limpia por compañía, año y mes',
    'compania = %s AND anio = %d AND mes = %d' === $where && [ '001', 2026, 9 ] === $params );

[ $whereAnual ] = $IS::scope_borrado( 'sysman_ejecucion_gastos', '001', 2026, 0 );
check( 'borrado: mes 0 limpia el año completo', 'compania = %s AND anio = %d' === $whereAnual );

[ $whereNomina, $paramsNomina ] = $IS::scope_borrado( 'sysman_personal_nomina', '001', 2026, 9 );
check( 'borrado: nómina no tiene mes, se limpia por año',
    'compania = %s AND anio = %d' === $whereNomina && [ '001', 2026 ] === $paramsNomina );
check( 'nómina: la clave natural no incluye mes', ! $IS::tiene_mes( 'sysman_personal_nomina' ) );
check( 'gastos: la clave natural incluye mes', $IS::tiene_mes( 'sysman_ejecucion_gastos' ) );

// Toda clave natural empieza por el ámbito que se borra: si no, el borrado y
// la detección de duplicados hablarían de cosas distintas.
$coherentes = true;
foreach ( $IS::CLAVES_NATURALES as $tabla => $clave ) {
    $esperado = $IS::tiene_mes( $tabla )
        ? [ 'compania', 'anio', 'mes' ]
        : [ 'compania', 'anio' ];
    if ( array_slice( $clave, 0, count( $esperado ) ) !== $esperado ) {
        $coherentes = false;
    }
}
check( 'toda clave natural empieza por el ámbito del borrado', $coherentes );

// ─── Vista de avance: porcentaje de ejecución ────────────────────
$fila = static fn( $l, $b, $e ) => [
    'label' => $l, 'codigo' => '', 'base' => (float) $b, 'ejecutado' => (float) $e,
    'porcentaje' => $b > 0 ? $e / $b : null, 'value' => $b > 0 ? $e / $b : 0.0,
];
$datosAv = [ 'filas' => [
    $fila( 'EDUCACION', 8000.0, 7200.0 ),   // 90%
    $fila( 'SALUD', 1000.0, 200.0 ),        // 20%
    $fila( 'VIAS', 1000.0, 0.0 ),           // 0%
] ];
$optsAv = [
    'modulo' => 'gastos', 'campo_label' => 'Apropiación Vigente', 'valor' => '',
    'base_label' => 'apropiación vigente', 'ejecutado_label' => 'comprometido',
];

foreach ( [ 'descripcion', 'cualitativo', 'cuantitativo' ] as $t ) {
    $av = $An::generar( $t, 'avance', $ctxPre, $datosAv, $optsAv );
    check( "avance {$t}: un solo párrafo", 1 === count( $av['parrafos'] ) );
    check( "avance {$t}: termina en punto", str_ends_with( trim( $av['parrafos'][0] ), '.' ) );
}

// El ponderado (7.400 / 10.000 = 74 %) manda sobre el promedio simple de los
// porcentajes (36,7 %): si no, una dependencia diminuta pesaría igual que una
// de ocho mil millones.
$avDesc = $An::generar( 'descripcion', 'avance', $ctxPre, $datosAv, $optsAv );
check( 'avance: la descripción usa el porcentaje ponderado',
    str_contains( $avDesc['parrafos'][0], '74,0%' ) );
check( 'avance: la descripción no usa el promedio simple',
    ! str_contains( $avDesc['parrafos'][0], '36,7%' ) );
check( 'avance: nombra la de mayor y la de menor', str_contains( $avDesc['parrafos'][0], 'Educacion' ) );

$avCuant = $An::generar( 'cuantitativo', 'avance', $ctxPre, $datosAv, $optsAv );
$etq     = array_column( $avCuant['metricas'], 'label' );
check( 'avance: separa ponderado de promedio simple',
    in_array( '% Ponderado', $etq, true ) && in_array( '% Promedio simple', $etq, true ) );
check( 'avance: reporta los tramos', in_array( 'Por encima del 75%', $etq, true ) );
$crudos = array_column( $avCuant['metricas'], 'crudo', 'label' );
check( 'avance: el ponderado se calcula sobre las sumas', abs( $crudos['% Ponderado'] - 0.74 ) < 0.0001 );
check( 'avance: el pendiente es la base menos lo ejecutado', abs( $crudos['Pendiente'] - 2600.0 ) < 0.01 );

$avCual = $An::generar( 'cualitativo', 'avance', $ctxPre, $datosAv, $optsAv );
check( 'avance: el cualitativo cuenta las rezagadas',
    str_contains( $avCual['parrafos'][0], 'no pasan del 30%' ) );
check( 'avance: menciona las que no han empezado',
    str_contains( $avCual['parrafos'][0], 'sin compromiso alguno' ) );

// Sin apropiación no se inventa un 0 % de avance.
$avVacio = $An::generar( 'descripcion', 'avance', $ctxPre, [ 'filas' => [ $fila( 'X', 0.0, 0.0 ) ] ], $optsAv );
check( 'avance: sin base no se calcula porcentaje',
    str_contains( $avVacio['parrafos'][0], 'No hay apropiación registrada' ) );

// Ingresos habla de recaudo, no de compromisos.
$optsAvIng = [
    'modulo' => 'ingresos', 'campo_label' => 'Total Presupuesto', 'valor' => '',
    'dimension_label' => 'Tipo de recurso', 'dimension_plural' => 'tipos de recurso',
    'dimension_femenino' => false,
    'base_label' => 'presupuesto definitivo', 'ejecutado_label' => 'recaudado',
];
$avIng = $An::generar( 'cualitativo', 'avance', $ctxPre, $datosAv, $optsAvIng );
check( 'avance de ingresos: habla de recaudo', str_contains( $avIng['parrafos'][0], 'recaudar' ) );
check( 'avance de ingresos: no habla de comprometer', ! str_contains( $avIng['parrafos'][0], 'comprometer' ) );
check( 'avance de ingresos: concordancia masculina',
    ! str_contains( $avIng['parrafos'][0], 'entre unas y otras' ) );

// ─── Consultas reales contra SQLite ──────────────────────────────
// El resto de la batería no toca la base de datos. Estas comprueban lo que
// solo se ve ejecutando el SQL: que ninguna fila se quede fuera de los
// agregados cuando la dependencia o el tipo de recurso vienen vacíos, que fue
// el motivo de que el módulo de Ingresos apareciera "sin datos" con la tabla
// llena.
if ( ! extension_loaded( 'pdo_sqlite' ) ) {
    echo "  (omitidas las pruebas de SQL: falta pdo_sqlite)\n";
} else {
    require __DIR__ . '/sqlite-wpdb.php';

    $sqlite = new Sysman_Sqlite_Wpdb();
    $GLOBALS['wpdb'] = $sqlite;
    $GLOBALS['__test_transients'] = [];

    $sqlite->pdo->exec(
        'CREATE TABLE wp_sysman_plan_presupuestal (id INTEGER PRIMARY KEY, compania TEXT, anio INT, mes INT,
         codigo TEXT, nombre TEXT, destino TEXT, naturaleza TEXT, movimiento TEXT, codigobpin TEXT,
         nombredependencia TEXT)'
    );
    $sqlite->pdo->exec(
        'CREATE TABLE wp_sysman_ejecucion_gastos (id INTEGER PRIMARY KEY, compania TEXT, anio INT, mes INT,
         codigocuenta TEXT, movimiento TEXT, apropiacionvigente REAL, compromisos REAL, obligacion REAL,
         pagos REAL, disponibilidades REAL, saldodisponible REAL, adicion REAL, reduccion REAL, credito REAL,
         contracredito REAL, aplazamiento REAL, desplazamiento REAL, apropiacioninicial REAL,
         disponibilidadesabiertas REAL, obligacionesporpagar REAL)'
    );
    $sqlite->pdo->exec(
        'CREATE TABLE wp_sysman_ejecucion_ingresos (id INTEGER PRIMARY KEY, anio INT, mes INT, compania TEXT,
         cuenta TEXT, codigo TEXT, nombre TEXT, movimiento TEXT, tiporecurso TEXT, fuenterecurso TEXT,
         apropiado REAL, modificaciones REAL, totalpresupuesto REAL, recaudosanteriores REAL, recaudosmes REAL,
         recaudosacumulados REAL, porrecaudar REAL, porcrecaudado REAL)'
    );

    $plan = $sqlite->pdo->prepare(
        "INSERT INTO wp_sysman_plan_presupuestal (compania,anio,mes,codigo,nombre,destino,naturaleza,movimiento,codigobpin,nombredependencia)
         VALUES ('001',2026,9,?,?,'','','SI','',?)"
    );
    $gasto = $sqlite->pdo->prepare(
        "INSERT INTO wp_sysman_ejecucion_gastos (compania,anio,mes,codigocuenta,movimiento,apropiacionvigente,compromisos,
         obligacion,pagos,disponibilidades,saldodisponible,adicion,reduccion,credito,contracredito,aplazamiento,
         desplazamiento,apropiacioninicial,disponibilidadesabiertas,obligacionesporpagar)
         VALUES ('001',2026,9,?,'SI',?,?,0,0,0,0,0,0,0,0,0,0,0,0,0)"
    );

    foreach ( [
        [ '2.1.1', 'Sueldos', 'SECRETARIA DE EDUCACION', 1000.0, 900.0 ],
        [ '2.1.2', 'Dotación', 'SECRETARIA DE EDUCACION', 500.0, 100.0 ],
        [ '2.2.1', 'Vías', 'INFRAESTRUCTURA', 800.0, 400.0 ],
        [ '2.9.9', 'Sin asignar', '   ', 300.0, 150.0 ],   // dependencia en blanco
    ] as $f ) {
        $plan->execute( [ $f[0], $f[1], $f[2] ] );
        $gasto->execute( [ $f[0], $f[3], $f[4] ] );
    }

    $repoG = \SysmanSuite\Presupuesto\Repository::instance();
    $ctxSql = $repoG->contexto( [ 'compania' => '001' ] );
    check( 'SQL: el contexto toma el último periodo con datos',
        2026 === $ctxSql['anio'] && 9 === $ctxSql['mes'] );

    $deps = $repoG->dependencias( $ctxSql );
    check( 'SQL: las filas sin dependencia no se descartan', 3 === count( $deps ) );
    check( 'SQL: se agrupan bajo "Sin dependencia"',
        in_array( \SysmanSuite\Presupuesto\Repository::SIN_DEPENDENCIA, array_column( $deps, 'label' ), true ) );
    check( 'SQL: el total agregado es el de la tabla completa',
        abs( array_sum( array_column( $deps, 'value' ) ) - 2600.0 ) < 0.01 );

    $rubSin = $repoG->rubros( $ctxSql, \SysmanSuite\Presupuesto\Repository::SIN_DEPENDENCIA );
    check( 'SQL: se puede abrir el grupo sin dependencia', 1 === count( $rubSin ) );
    check( 'SQL: los rubros de una dependencia normal siguen bien',
        2 === count( $repoG->rubros( $ctxSql, 'SECRETARIA DE EDUCACION' ) ) );

    $avG = $repoG->avance( $ctxSql );
    $porNombre = array_column( $avG, 'porcentaje', 'label' );
    check( 'SQL: el avance se calcula por dependencia',
        abs( $porNombre['SECRETARIA DE EDUCACION'] - ( 1000.0 / 1500.0 ) ) < 0.001 );

    // ── Ingresos con el tipo de recurso vacío (el caso reportado) ──
    $ingIns = $sqlite->pdo->prepare(
        "INSERT INTO wp_sysman_ejecucion_ingresos (anio,mes,compania,cuenta,codigo,nombre,movimiento,tiporecurso,
         fuenterecurso,apropiado,modificaciones,totalpresupuesto,recaudosanteriores,recaudosmes,recaudosacumulados,
         porrecaudar,porcrecaudado) VALUES (?,?,'001',?,?,?,'SI',?,?,0,0,?,0,0,?,?,0)"
    );
    foreach ( [
        // Septiembre con la forma del sitio: tipo de recurso vacío y una única
        // fuente repetida (un código comodín que no agrupa nada).
        [ 2026, 9, '1.1.01.02.105.01-16-1.2.3.4.01', '', '99999999999999999999', 9000.0, 6000.0 ],
        [ 2026, 9, '1.1.01.02.105.02-16-1.2.3.4.02', '', '99999999999999999999', 1000.0, 500.0 ],
        [ 2026, 9, '1.2.10.02-16-1.3.3.2.00', '', '', 500.0, 400.0 ],   // sin fuente tampoco
        // Mayo sí trae tipo y fuente diligenciados, con más de un valor.
        [ 2026, 5, '1.1.01', 'Recursos propios', 'Tributarios', 100.0, 50.0 ],
        [ 2026, 5, '1.1.02', 'SGP', 'Transferencias', 200.0, 80.0 ],
    ] as $f ) {
        $ingIns->execute( [ $f[0], $f[1], $f[2], $f[2], 'Cuenta ' . $f[2], $f[3], $f[4], $f[5], $f[6], $f[5] - $f[6] ] );
    }

    $repoI  = \SysmanSuite\Presupuesto\IngresosRepository::instance();
    $ctxIng = $repoI->contexto( [ 'compania' => '001' ] );
    check( 'SQL ingresos: el contexto toma septiembre, el último con datos',
        2026 === $ctxIng['anio'] && 9 === $ctxIng['mes'] );

    // Con el tipo vacío y una sola fuente repetida, agrupar por fuente dejaría
    // un único bloque del 100%: se pasa al rubro (prefijo del código).
    check( 'SQL ingresos: sin tipo ni fuente que agrupen, se usa el rubro',
        'rubro' === $repoI->dimension_util( $ctxIng, 'tiporecurso' ) );
    check( 'SQL ingresos: si el tipo sí agrupa, se respeta el pedido',
        'tiporecurso' === $repoI->dimension_util( [ 'compania' => '001', 'anio' => 2026, 'mes' => 5 ], 'tiporecurso' ) );

    $dimIng = $repoI->dimensiones( $ctxIng, 'totalpresupuesto', 0, 'fuenterecurso' );
    check( 'SQL ingresos: la vista ya no queda vacía', count( $dimIng ) > 0 );
    check( 'SQL ingresos: el total agregado es el de la tabla',
        abs( array_sum( array_column( $dimIng, 'value' ) ) - 10500.0 ) < 0.01 );
    check( 'SQL ingresos: las filas sin fuente van a "Sin clasificar"',
        in_array( \SysmanSuite\Presupuesto\IngresosRepository::SIN_CLASIFICAR, array_column( $dimIng, 'label' ), true ) );
    check( 'SQL ingresos: se puede abrir el grupo sin clasificar',
        1 === count( $repoI->detalle( $ctxIng, \SysmanSuite\Presupuesto\IngresosRepository::SIN_CLASIFICAR, 'totalpresupuesto', 'fuenterecurso' ) ) );

    $avI = $repoI->avance( $ctxIng, '', 0, 'fuenterecurso' );
    check( 'SQL ingresos: el avance de recaudo se calcula por fuente', count( $avI ) === count( $dimIng ) );

    // ── Rubro: los 13 primeros caracteres del código de cuenta ──
    $GLOBALS['__test_transients'] = [];
    $rub = $repoI->dimensiones( $ctxIng, 'totalpresupuesto', 0, 'rubro', [], 13 );
    $etiquetasRub = array_column( $rub, 'label' );
    check( 'SQL rubro: agrupa por los 13 primeros caracteres del código',
        in_array( '1.1.01.02.105', $etiquetasRub, true ) );
    check( 'SQL rubro: recorta el separador que queda suelto al final',
        in_array( '1.2.10.02-16', $etiquetasRub, true ) );
    check( 'SQL rubro: dos cuentas del mismo rubro se suman en una fila', 2 === count( $rub ) );
    check( 'SQL rubro: el total sigue siendo el de la tabla',
        abs( array_sum( array_column( $rub, 'value' ) ) - 10500.0 ) < 0.01 );
    check( 'SQL rubro: la fila lleva un nombre representativo, no solo el código',
        '' !== ( $rub[0]['nombre'] ?? '' ) );

    $hijas = $repoI->detalle( $ctxIng, '1.1.01.02.105', 'totalpresupuesto', 'rubro', 13 );
    check( 'SQL rubro: el detalle lista las cuentas hijas del rubro', 2 === count( $hijas ) );
    check( 'SQL rubro: las hijas son las del prefijo, no otras',
        '1.1.01.02.105.01-16-1.2.3.4.01' === ( $hijas[0]['codigo'] ?? '' ) );

    $GLOBALS['__test_transients'] = [];
    $rub9 = $repoI->dimensiones( $ctxIng, 'totalpresupuesto', 0, 'rubro', [], 9 );
    check( 'SQL rubro: la longitud es parametrizable', 2 === count( $rub9 )
        && in_array( '1.1.01.02', array_column( $rub9, 'label' ), true ) );

    check( 'SQL rubro: una longitud absurda cae al valor por defecto',
        13 === \SysmanSuite\Presupuesto\IngresosRepository::validar_longitud( 0 )
        && 13 === \SysmanSuite\Presupuesto\IngresosRepository::validar_longitud( 999 ) );

    $GLOBALS['__test_transients'] = [];
    $avRub = $repoI->avance( $ctxIng, '', 0, 'rubro', 'recaudosacumulados', 'totalpresupuesto', 13 );
    check( 'SQL rubro: el avance también agrupa por rubro', 2 === count( $avRub ) );
    check( 'SQL rubro: el avance dentro de un rubro baja a sus cuentas',
        2 === count( $repoI->avance( $ctxIng, '1.1.01.02.105', 0, 'rubro', 'recaudosacumulados', 'totalpresupuesto', 13 ) ) );
}

// ─── Resumen ─────────────────────────────────────────────────────
echo "\n{$passed} aserciones OK, {$failures} fallos\n";
exit( $failures > 0 ? 1 : 0 );
