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
 * Exporta los resultados de un curso (nota por evaluación y promedio) con los formatos nativos de Moodle.
 *
 * @package    local_mediar
 * @copyright  2026 Mediar
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_mediar\estadisticas;

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$formato = required_param('formato', PARAM_ALPHA);
if (!in_array($formato, ['excel', 'pdf', 'csv', 'ods'])) {
    throw new moodle_exception('invalidparameter', 'debug');
}

$course = get_course($id);
require_login($course);
require_capability('local/mediar:verestadisticascurso', context_course::instance($course->id));

$datos = estadisticas::curso($course);
$evals = array_values(array_filter($datos['evaluaciones'], fn($e) => $e['resumen']['evaluados'] > 0));

$columnas = [get_string('estudiante', 'local_mediar'), get_string('username')];
foreach ($evals as $e) {
    $columnas[] = $e['quiz']->nombre . ' (/' . (float) $e['quiz']->grade . ')';
}
$columnas[] = get_string('promediopct', 'local_mediar');

$filas = [];
foreach ($datos['usuarios'] as $u) {
    $fila = [fullname($u), $u->username];
    $pcts = [];
    foreach ($evals as $e) {
        $n = $datos['notas'][$e['quiz']->id][$u->id] ?? null;
        $fila[] = $n ? round($n->grade, 1) : '';
        if ($n) {
            $pcts[] = estadisticas::pct($n->grade, $e['quiz']->grade);
        }
    }
    $fila[] = $pcts ? round(array_sum($pcts) / count($pcts), 1) : '';
    $filas[] = $fila;
}

$archivo = clean_filename('resultados_' . ($datos['nrc'] ?: $course->id) . '_' . date('Ymd'));
\core\dataformat::download_data($archivo, $formato, $columnas, $filas);
