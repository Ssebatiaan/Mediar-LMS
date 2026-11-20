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
 * Administración del test de personalidad: participación, resultados de los estudiantes e instrumentos.
 *
 * @package    local_mediartest
 * @copyright  2026 Mediar
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_mediartest\instrumento;
use local_mediartest\intentos;

require(__DIR__ . '/../../config.php');

require_login();
$contexto = context_system::instance();
require_capability('local/mediartest:verresultados', $contexto);
$puedegestionar = has_capability('local/mediartest:gestionar', $contexto);

$url = new moodle_url('/local/mediartest/admin.php');
$PAGE->set_url($url);
$PAGE->set_context($contexto);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('administracion', 'local_mediartest'));
$PAGE->set_heading(get_string('administracion', 'local_mediartest'));

// Activar un instrumento.
if ($puedegestionar && ($activar = optional_param('activar', 0, PARAM_INT)) && confirm_sesskey()) {
    instrumento::activar($activar);
    redirect($url, get_string('activado', 'local_mediartest'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Cargar un instrumento nuevo.
$form = null;
if ($puedegestionar) {
    $form = new \local_mediartest\form\subir($url);
    if ($datos = $form->get_data()) {
        try {
            $nuevo = instrumento::importar($form->get_file_content('archivo'), !empty($datos->activar));
            redirect($url, get_string('instrumentocargado', 'local_mediartest', $nuevo->def['nombre']), null,
                \core\output\notification::NOTIFY_SUCCESS);
        } catch (moodle_exception $e) {
            redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
        }
    }
}

$activo = instrumento::activo();

// Estudiantes: usuarios con rol de estudiante en algún curso.
$rolestudiante = $DB->get_field('role', 'id', ['shortname' => 'student']);
$estudiantes = $DB->get_records_sql("SELECT DISTINCT u.*
    FROM {user} u
    JOIN {role_assignments} ra ON ra.userid = u.id AND ra.roleid = ?
    JOIN {context} c ON c.id = ra.contextid AND c.contextlevel = ?
    WHERE u.deleted = 0 ORDER BY u.lastname, u.firstname", [$rolestudiante, CONTEXT_COURSE]);

$filas = [];
$finalizados = $enprogreso = 0;
$distribucion = [];
foreach ($estudiantes as $u) {
    $final = intentos::ultimo_finalizado($u->id);
    $progreso = $activo ? intentos::en_progreso($u->id, $activo->registro->id) : null;
    $resultado = $final ? json_decode($final->resultado, true) : null;
    if ($final) {
        $finalizados++;
        if ($activo && (int) $final->instrumentoid === (int) $activo->registro->id) {
            foreach ($resultado['dimensiones'] as $d) {
                $distribucion[$d['codigo']][$d['polo'] ?? 'equilibrada'] = ($distribucion[$d['codigo']][$d['polo'] ?? 'equilibrada'] ?? 0) + 1;
            }
        }
    } else if ($progreso) {
        $enprogreso++;
    }
    $filas[] = [
        'nombre' => fullname($u),
        'cohorte' => \local_mediar\datos::cohorte($u->id),
        'finalizado' => (bool) $final,
        'enprogreso' => !$final && $progreso,
        'perfil' => $resultado['perfil'] ?? '',
        'fecha' => $final ? userdate($final->timefinished, get_string('strftimedatefullshort', 'langconfig')) : '',
        'url' => (new moodle_url('/local/mediartest/resultado.php', ['id' => $u->id]))->out(false),
    ];
}
$total = count($estudiantes);

// Distribución por dimensión del instrumento activo.
$dimensiones = [];
if ($activo) {
    foreach ($activo->def['dimensiones'] as $d) {
        [$a, $b] = $d['polos'];
        $na = $distribucion[$d['codigo']][$a['codigo']] ?? 0;
        $nb = $distribucion[$d['codigo']][$b['codigo']] ?? 0;
        $ne = $distribucion[$d['codigo']]['equilibrada'] ?? 0;
        $n = max(1, $na + $nb + $ne);
        $dimensiones[] = [
            'nombre' => $d['nombre'],
            'poloa' => $a['nombre'], 'polob' => $b['nombre'],
            'na' => $na, 'nb' => $nb, 'ne' => $ne,
            'pa' => round($na / $n * 100), 'pb' => round($nb / $n * 100), 'pe' => 100 - round($na / $n * 100) - round($nb / $n * 100),
        ];
    }
}

$instrumentos = array_map(function($r) use ($DB, $puedegestionar, $url) {
    $def = json_decode($r->definicion, true);
    return [
        'nombre' => $r->nombre, 'version' => $r->version, 'codigo' => $r->codigo,
        'items' => count($def['items']), 'dimensiones' => count($def['dimensiones']),
        'intentos' => $DB->count_records('local_mediartest_intento', ['instrumentoid' => $r->id, 'estado' => 1]),
        'activo' => (bool) $r->activo,
        'urlactivar' => $puedegestionar && !$r->activo
            ? (new moodle_url($url, ['activar' => $r->id, 'sesskey' => sesskey()]))->out(false) : '',
    ];
}, array_values($DB->get_records('local_mediartest_instr', null, 'timecreated DESC')));

$datos = [
    'activo' => $activo ? $activo->def['nombre'] . ' · ' . $activo->registro->version : '',
    'kpis' => [
        ['etiqueta' => get_string('estudiantes', 'local_mediartest'), 'valor' => $total],
        ['etiqueta' => get_string('finalizados', 'local_mediartest'), 'valor' => $finalizados, 'destacado' => true,
            'detalle' => $total ? get_string('pctparticipacion', 'local_mediartest', round($finalizados / $total * 100)) : ''],
        ['etiqueta' => get_string('enprogreso', 'local_mediartest'), 'valor' => $enprogreso],
        ['etiqueta' => get_string('sinpresentar', 'local_mediartest'), 'valor' => $total - $finalizados - $enprogreso],
    ],
    'dimensiones' => $dimensiones,
    'haydimensiones' => !empty($dimensiones) && $finalizados > 0,
    'filas' => $filas,
    'instrumentos' => $instrumentos,
    'urlexportar' => (new moodle_url('/local/mediartest/exportar.php'))->out(false),
    'puedegestionar' => $puedegestionar,
    'formulario' => $form ? $form->render() : '',
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_mediartest/admin', $datos);
echo $OUTPUT->footer();
