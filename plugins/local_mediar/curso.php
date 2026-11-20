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
 * Panel de estadísticas de un curso (profesor): resultados del grupo por evaluación, componente y competencia.
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

$id = required_param('id', PARAM_INT);
$quizid = optional_param('quiz', 0, PARAM_INT);

$course = get_course($id);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/mediar:verestadisticascurso', $context);

$url = new moodle_url('/local/mediar/curso.php', ['id' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('estadisticascurso', 'local_mediar', format_string($course->shortname)));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('estadisticas', 'local_mediar'), $url);

$datos = estadisticas::curso($course);
$essimulacro = $datos['area'] === areas::SIMULACRO;

// Evaluación seleccionada: la pedida, o la última con resultados.
$seleccionada = $datos['ultima'];
foreach ($datos['evaluaciones'] as $e) {
    if ($e['quiz']->id === $quizid) {
        $seleccionada = $e;
    }
}

$contexto = [
    'curso' => vista::tarjeta($datos),
    'nrc' => $datos['nrc'],
    'colegio' => $datos['colegio'],
    'promocion' => $datos['promocion'],
    'estudiantes' => $datos['estudiantes'],
    'essimulacro' => $essimulacro,
    'evaluaciones' => array_map(fn($e) => [
        'nombre' => $e['quiz']->nombre,
        'url' => (new moodle_url($url, ['quiz' => $e['quiz']->id]))->out(false),
        'activa' => $seleccionada && $e['quiz']->id === $seleccionada['quiz']->id,
        'sinresultados' => $e['resumen']['evaluados'] === 0,
    ], $datos['evaluaciones']),
    'haydatos' => (bool) $seleccionada,
];

if ($seleccionada) {
    $quiz = $seleccionada['quiz'];
    $r = $seleccionada['resumen'];
    $notas = $datos['notas'][$quiz->id] ?? [];
    $respuestas = datos::respuestas([$quiz->id], array_keys($datos['usuarios']));
    $max = vista::num($quiz->grade, 0);
    $dec = $quiz->grade >= 100 ? 0 : 1; // sobre 500: enteros, como el ICFES

    $contexto['evaluacion'] = $quiz->nombre;
    $contexto['urlevaluacion'] = $quiz->url;
    $contexto['kpis'] = [
        ['etiqueta' => get_string('evaluados', 'local_mediar'), 'valor' => $r['evaluados'], 'unidad' => '/' . $r['matriculados'],
            'detalle' => get_string('participacion', 'local_mediar', $r['participacion'])],
        ['etiqueta' => get_string('promediogrupo', 'local_mediar'), 'valor' => vista::num($r['promedio'], $dec), 'unidad' => "/$max",
            'detalle' => vista::num($r['promediopct'], 0) . ' %', 'destacado' => true],
        ['etiqueta' => get_string('notamasalta', 'local_mediar'), 'valor' => vista::num($r['maximo'], $dec), 'unidad' => "/$max"],
        ['etiqueta' => get_string('notamasbaja', 'local_mediar'), 'valor' => vista::num($r['minimo'], $dec), 'unidad' => "/$max"],
    ];
    $contexto['graficodistribucion'] = graficos::distribucion(estadisticas::distribucion($notas, $quiz->grade));
    $contexto['graficoevolucion'] = graficos::evolucion_curso($datos['evaluaciones']);
    $contexto['componentes'] = vista::barras(estadisticas::por_dimension($respuestas, 'componente'), $essimulacro);
    $contexto['competencias'] = vista::barras(estadisticas::por_dimension($respuestas, 'competencia'), $essimulacro);
    $contexto['dificiles'] = array_map(fn($p) => $p + ['pcttexto' => vista::num($p['pct'], 0)],
        estadisticas::preguntas_dificiles($respuestas, 5));

    // Simulacro: promedio del grupo por área (0-100) y global (0-500).
    if ($essimulacro) {
        $porusuario = [];
        foreach ($respuestas as $resp) {
            $porusuario[$resp->userid][] = $resp;
        }
        $sumas = [];
        foreach ($porusuario as $resps) {
            foreach (estadisticas::puntaje_areas($resps) as $codigo => $p) {
                $sumas[$codigo][] = $p;
            }
        }
        $contexto['areas'] = vista::barras_areas($sumas);
    }
}

// Tabla de estudiantes: nota en cada evaluación, tendencia y alerta.
$conresultados = array_values(array_filter($datos['evaluaciones'], fn($e) => $e['resumen']['evaluados'] > 0));
$contexto['columnas'] = array_map(fn($e) => ['nombre' => trim(strtok($e['quiz']->nombre, '·'))], $conresultados);
$contexto['filas'] = [];
foreach ($datos['usuarios'] as $u) {
    $celdas = [];
    $pcts = [];
    foreach ($conresultados as $e) {
        $n = $datos['notas'][$e['quiz']->id][$u->id] ?? null;
        $celdas[] = ['texto' => $n ? vista::num($n->grade, $e['quiz']->grade >= 100 ? 0 : 1) : '—', 'vacia' => !$n];
        if ($n) {
            $pcts[] = estadisticas::pct($n->grade, $e['quiz']->grade);
        }
    }
    $ultimo = $pcts ? end($pcts) : null;
    $tendencia = count($pcts) > 1 ? $pcts[count($pcts) - 1] - $pcts[count($pcts) - 2] : null;
    $contexto['filas'][] = [
        'nombre' => fullname($u),
        'url' => (new moodle_url('/local/mediar/estudiante.php', ['id' => $u->id]))->out(false),
        'celdas' => $celdas,
        'ultimo' => $ultimo !== null ? vista::num($ultimo, 0) . ' %' : '—',
        'sube' => $tendencia !== null && $tendencia >= 5,
        'baja' => $tendencia !== null && $tendencia <= -5,
        'tendencia' => $tendencia !== null ? ($tendencia > 0 ? '+' : '') . vista::num($tendencia, 0) : '',
        'alerta' => isset($datos['alertas'][$u->id]),
    ];
}
$contexto['umbral'] = $datos['umbral'];
$contexto['numalertas'] = count($datos['alertas']);
$contexto['exportar'] = array_map(fn($f) => [
    'nombre' => get_string('exportar_' . $f, 'local_mediar'),
    'url' => (new moodle_url('/local/mediar/exportar.php', ['id' => $course->id, 'formato' => $f]))->out(false),
], ['excel', 'pdf', 'csv']);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_mediar/curso', $contexto);
echo $OUTPUT->footer();
