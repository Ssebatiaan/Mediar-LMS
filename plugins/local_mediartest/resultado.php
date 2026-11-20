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
 * Resultado del test. Lo ve el propio estudiante; el de otra persona solo con local/mediartest:verresultados
 * (administración). Los profesores no tienen acceso.
 *
 * @package    local_mediartest
 * @copyright  2026 Mediar
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_mediartest\instrumento;
use local_mediartest\intentos;
use local_mediartest\presentacion;

require(__DIR__ . '/../../config.php');

require_login();
$contexto = context_system::instance();
$userid = optional_param('id', $USER->id, PARAM_INT);
$propio = (int) $userid === (int) $USER->id;
if (!$propio) {
    require_capability('local/mediartest:verresultados', $contexto);
}
$usuario = core_user::get_user($userid, '*', MUST_EXIST);

$url = new moodle_url('/local/mediartest/resultado.php', $propio ? [] : ['id' => $userid]);
$PAGE->set_url($url);
$PAGE->set_context($contexto);
$PAGE->set_pagelayout('standard');
$titulo = $propio ? get_string('miresultado', 'local_mediartest') : get_string('resultadode', 'local_mediartest', fullname($usuario));
$PAGE->set_title($titulo);
$PAGE->set_heading($titulo);

$intento = intentos::ultimo_finalizado($userid);
if (!$intento) {
    if ($propio) {
        redirect(new moodle_url('/local/mediartest/index.php'));
    }
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('sinresultado', 'local_mediartest'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$instrumento = instrumento::por_id($intento->instrumentoid);
$datos = presentacion::resultado($instrumento, json_decode($intento->resultado, true));
$datos += [
    'propio' => $propio,
    'fecha' => userdate($intento->timefinished, get_string('strftimedate', 'langconfig')),
    'urlrepetir' => (new moodle_url('/local/mediartest/index.php', ['accion' => 'repetir', 'sesskey' => sesskey()]))->out(false),
    'urlinicio' => (new moodle_url('/my/'))->out(false),
    'urladmin' => (new moodle_url('/local/mediartest/admin.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_mediartest/resultado', $datos);
echo $OUTPUT->footer();
