<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Historial académico de un estudiante: simulacros (0-500 y por área), cuadernillos, fortalezas y debilidades.
 *
 * Acceso: el propio estudiante; un profesor con local/mediar:verestadisticascurso en algún curso del
 * estudiante; o local/mediar:verestadisticasinstitucion.
 *
 * @package    local_mediar
 * @copyright  2026 Mediar
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_mediar\areas;
use local_mediar\datos;
use local_mediar\estadisticas;
use local_mediar\graficos;
use local_mediar\vista;

require(__DIR__ . '/../../config.php');

require_login();
$userid = optional_param('id', $USER->id, PARAM_INT);
$usuario = core_user::get_user($userid, '*', MUST_EXIST);

// Permisos: nadie ve los resultados de otro estudiante sin una capacidad que lo autorice.
$propio = $userid == $USER->id;
$permitido = $propio || has_capability('local/mediar:verestadisticasinstitucion', context_system::instance());
if (!$permitido) {
    foreach (enrol_get_all_users_courses($userid, true) as $c) {
        if (has_capability('local/mediar:verestadisticascurso', context_course::instance($c->id))) {
            $permitido = true;
            break;
        }
    }
}
if (!$permitido) {
    throw new required_capability_exception(context_system::instance(), 'local/mediar:verestadisticascurso',
        'nopermissions', '');
}

$url = new moodle_url('/local/mediar/estudiante.php', ['id' => $userid]);
$PAGE->set_url($url);
$PAGE->set_context(context_user::instance($userid));
$PAGE->set_pagelayout('standard');
$titulo = $propio ? get_string('miprogreso', 'local_mediar') : get_string('progresode', 'local_mediar', fullname($usuario));
$PAGE->set_title($titulo);
$PAGE->set_heading($titulo);

$d = estadisticas::estudiante($userid);

$kpis = vista::kpis_estudiante($d);

// Simulacros presentados con su puntaje por área.
$simulacros = [];
foreach ($d['simulacros'] as $s) {
    if ($s['nota'] === null) {
        continue;
    }
    $simulacros[] = [
        'nombre' => $s['nombre'], 'url' => $s['url'], 'global' => vista::num($s['nota'], 0),
        'fecha' => userdate($s['fecha'], get_string('strftimedate', 'langconfig')),
        'areas' => array_values(array_map(fn($codigo) => [
            'nombre' => areas::nombre($codigo), 'color' => areas::color($codigo),
            'valor' => $s['areas'][$codigo] ?? '—',
        ], array_keys(areas::AREAS))),
    ];
}

// Historial por curso.
$cursos = [];
foreach ($d['cursos'] as $c) {
    if ($c['area'] === null) {
        continue;
    }
    $cursos[] = [
        'nombre' => $c['nombre'], 'corto' => $c['corto'], 'url' => $c['url'],
        'evaluaciones' => array_map(fn($e) => [
            'nombre' => $e['nombre'], 'url' => $e['url'],
            'nota' => $e['nota'] !== null
                ? vista::num($e['nota'], $e['maximo'] >= 100 ? 0 : 1) . ' / ' . vista::num($e['maximo'], 0) : null,
            'pct' => $e['pct'] !== null ? vista::num($e['pct'], 0) . ' %' : '',
            'fecha' => $e['fecha'] ? userdate($e['fecha'], get_string('strftimedatefullshort', 'langconfig')) : '',
            'pendiente' => $e['pendiente'], 'futura' => $e['futura'],
        ], $c['evaluaciones']),
    ];
}

$contexto = [
    'nombre' => fullname($usuario),
    'cohorte' => datos::cohorte($userid),
    'kpis' => $kpis,
    'graficoareas' => graficos::evolucion_areas($d['cursos']),
    'graficosimulacros' => graficos::simulacros_por_area(array_filter($d['simulacros'], fn($s) => $s['nota'] !== null)),
    'simulacros' => $simulacros,
    'haysimulacros' => !empty($simulacros),
    'fortalezas' => vista::barras($d['fortalezas']),
    'debilidades' => vista::barras($d['debilidades']),
    'cursos' => $cursos,
    'propio' => $propio,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_mediar/estudiante', $contexto);
echo $OUTPUT->footer();
