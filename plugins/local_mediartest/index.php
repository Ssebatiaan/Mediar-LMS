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
 * Presentación del test: qué es, instrucciones, aviso y botón para comenzar, continuar o ver el resultado.
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
require_capability('local/mediartest:presentar', $contexto);

$url = new moodle_url('/local/mediartest/index.php');
$PAGE->set_url($url);
$PAGE->set_context($contexto);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_mediartest'));
$PAGE->set_heading(get_string('pluginname', 'local_mediartest'));

$instrumento = instrumento::activo();
if (!$instrumento) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('sininstrumento', 'local_mediartest'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// Comenzar o repetir: crea un intento nuevo y va a la primera página.
$accion = optional_param('accion', '', PARAM_ALPHA);
if ($accion !== '' && confirm_sesskey()) {
    if (!intentos::en_progreso($USER->id, $instrumento->registro->id)) {
        intentos::iniciar($USER->id, $instrumento->registro->id);
    }
    redirect(new moodle_url('/local/mediartest/responder.php', ['pagina' => 1]));
}

$final = intentos::ultimo_finalizado($USER->id);
$progreso = intentos::en_progreso($USER->id, $instrumento->registro->id);
$items = count($instrumento->items());
$respondidas = $progreso ? count(intentos::respuestas($progreso->id)) : 0;

$datos = [
    'nombre' => $instrumento->def['nombre'],
    'subtitulo' => $instrumento->def['subtitulo'] ?? '',
    'descripcion' => $instrumento->def['descripcion'] ?? '',
    'instrucciones' => $instrumento->def['instrucciones'] ?? '',
    'aviso' => $instrumento->def['aviso'] ?? '',
    'items' => $items,
    'minutos' => max(5, (int) ceil($items * 20 / 60)),
    'paginas' => $instrumento->paginas(),
    'dimensiones' => array_map(fn($d) => [
        'nombre' => $d['nombre'], 'pregunta' => $d['pregunta'] ?? '',
        'polos' => $d['polos'][0]['nombre'] . ' · ' . $d['polos'][1]['nombre'],
    ], $instrumento->def['dimensiones']),
    'enprogreso' => (bool) $progreso,
    'avance' => $progreso ? (int) round($respondidas / $items * 100) : 0,
    'finalizado' => (bool) $final,
    'fechafinal' => $final ? userdate($final->timefinished, get_string('strftimedate', 'langconfig')) : '',
    'urlcontinuar' => (new moodle_url('/local/mediartest/responder.php', ['pagina' => 1]))->out(false),
    'urlresultado' => (new moodle_url('/local/mediartest/resultado.php'))->out(false),
    'urlcomenzar' => (new moodle_url($url, ['accion' => 'comenzar', 'sesskey' => sesskey()]))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_mediartest/inicio', $datos);
echo $OUTPUT->footer();
