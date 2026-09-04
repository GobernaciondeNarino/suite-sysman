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

// ─── Resumen ─────────────────────────────────────────────────────
echo "\n{$passed} aserciones OK, {$failures} fallos\n";
exit( $failures > 0 ? 1 : 0 );
