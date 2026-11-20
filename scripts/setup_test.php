<?php
// Test de personalidad de la DEMO: carga los instrumentos y simula resultados. Idempotente.
//
// Uso (después de desplegar local_mediartest con deploy.sh):
//   docker compose exec moodle php /opt/mediar/scripts/setup_test.php
//   docker compose exec moodle php /opt/mediar/scripts/setup_test.php --reiniciar   (borra y regenera los intentos demo)
//
// - Carga cada contenido/tests/*.json que no exista (mismo código y versión) y activa
//   "preferencias-estudio" si no hay ninguno activo.
// - Simula que ~70 % de los estudiantes ya presentaron el test, con perfiles coherentes (cada
//   estudiante tiene una inclinación propia por dimensión). est.cps01 y est.alm01 quedan SIN
//   presentarlo, para hacerlo en vivo en la demo.

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');

use local_mediartest\instrumento;
use local_mediartest\intentos;

[$opciones] = cli_get_params(['reiniciar' => false]);
mt_srand(1119);

// 1. Instrumentos.
foreach (glob('/opt/mediar/contenido/tests/*.json') as $archivo) {
    $json = file_get_contents($archivo);
    $def = json_decode($json, true);
    if ($DB->record_exists('local_mediartest_instr', ['codigo' => $def['codigo'], 'version' => $def['version']])) {
        cli_writeln("  [existe] instrumento {$def['codigo']} {$def['version']}");
        continue;
    }
    instrumento::importar($json);
    cli_writeln("  [creado] instrumento {$def['codigo']} {$def['version']}");
}
if (!instrumento::activo()) {
    instrumento::activar($DB->get_field('local_mediartest_instr', 'id', ['codigo' => 'preferencias-estudio'], MUST_EXIST));
}
$activo = instrumento::activo();
cli_writeln("Instrumento activo: {$activo->def['nombre']} ({$activo->registro->version})");

// 2. Intentos simulados.
$estudiantes = $DB->get_records_select('user', "username LIKE 'est.%' AND deleted = 0", null, 'username');
if ($opciones['reiniciar']) {
    $ids = array_keys($estudiantes);
    [$sql, $params] = $DB->get_in_or_equal($ids);
    $intentosdemo = $DB->get_fieldset_select('local_mediartest_intento', 'id', "userid $sql", $params);
    if ($intentosdemo) {
        [$isql, $iparams] = $DB->get_in_or_equal($intentosdemo);
        $DB->delete_records_select('local_mediartest_resp', "intentoid $isql", $iparams);
        $DB->delete_records_select('local_mediartest_intento', "id $isql", $iparams);
    }
    cli_writeln('Intentos demo borrados: ' . count($intentosdemo));
}

$normal = function(): float {
    $u = max(mt_rand() / mt_getrandmax(), 1e-9);
    return sqrt(-2 * log($u)) * cos(2 * M_PI * mt_rand() / mt_getrandmax());
};
$valores = array_column($activo->escala(), 'valor');
[$min, $max] = [min($valores), max($valores)];
$centro = ($min + $max) / 2;
$inicio = (new DateTimeImmutable('2026-10-06 08:00', new DateTimeZone('America/Bogota')))->getTimestamp();
$fin = (new DateTimeImmutable('2026-11-18 20:00', new DateTimeZone('America/Bogota')))->getTimestamp();

$creados = 0;
foreach ($estudiantes as $u) {
    // El sorteo se hace siempre, para que la simulación sea igual en cada ejecución.
    $presenta = mt_rand() / mt_getrandmax() < 0.7;
    $inclinacion = array_combine(array_column($activo->def['dimensiones'], 'codigo'),
        array_map(fn() => $normal(), $activo->def['dimensiones']));
    $cuando = mt_rand($inicio, $fin);
    if (in_array($u->username, ['est.cps01', 'est.alm01']) || !$presenta
            || $DB->record_exists('local_mediartest_intento', ['userid' => $u->id])) {
        continue;
    }
    $intento = intentos::iniciar($u->id, $activo->registro->id);
    $respuestas = [];
    foreach ($activo->items() as $it) {
        $dim = array_values(array_filter($activo->def['dimensiones'], fn($d) => $d['codigo'] === $it['dimension']))[0];
        $signo = $it['polo'] === $dim['polos'][0]['codigo'] ? 1 : -1;
        $v = (int) round($centro + $signo * $inclinacion[$it['dimension']] * 1.1 + $normal() * 0.8);
        $respuestas[$it['codigo']] = max($min, min($max, $v));
    }
    intentos::guardar($intento, $respuestas);
    $DB->set_field('local_mediartest_intento', 'timecreated', $cuando - 15 * MINSECS, ['id' => $intento->id]);
    intentos::finalizar($intento, $activo, $cuando);
    $creados++;
}
$total = $DB->count_records('local_mediartest_intento', ['estado' => intentos::FINALIZADO]);
cli_writeln("Intentos simulados creados: $creados · finalizados en total: $total de " . count($estudiantes) . ' estudiantes');
