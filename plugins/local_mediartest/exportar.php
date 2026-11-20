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
 * Exporta los resultados del test (solo administración).
 *
 * @package    local_mediartest
 * @copyright  2026 Mediar
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_mediartest\instrumento;

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/lib.php');

require_login();
require_capability('local/mediartest:verresultados', context_system::instance());
$formato = optional_param('formato', 'excel', PARAM_ALPHA);
if (!in_array($formato, ['excel', 'csv', 'ods'])) {
    throw new moodle_exception('invalidparameter', 'debug');
}

$activo = instrumento::activo();
$dims = $activo ? array_column($activo->def['dimensiones'], 'nombre', 'codigo') : [];
$columnas = array_merge([get_string('estudiante', 'local_mediartest'), get_string('username'),
    get_string('perfil', 'local_mediartest'), get_string('fecha', 'local_mediartest')],
    array_map(fn($n) => $n . ' (-100 a 100)', array_values($dims)));

$filas = [];
$intentos = $DB->get_records_sql("SELECT i.*, u.username FROM {local_mediartest_intento} i
    JOIN {user} u ON u.id = i.userid
    WHERE i.estado = 1 AND i.instrumentoid = ? ORDER BY u.lastname, u.firstname, i.timefinished",
    [$activo->registro->id ?? 0]);
$usuarios = $intentos ? user_get_users_by_id(array_unique(array_column($intentos, 'userid'))) : [];
$ultimo = [];
foreach ($intentos as $i) {
    $ultimo[$i->userid] = $i; // el más reciente de cada estudiante
}
foreach ($ultimo as $i) {
    $r = json_decode($i->resultado, true);
    $pordim = array_column($r['dimensiones'], 'puntaje', 'codigo');
    $filas[] = array_merge([fullname($usuarios[$i->userid]), $i->username, $r['perfil'],
        userdate($i->timefinished, get_string('strftimedatetimeshort', 'langconfig'))],
        array_map(fn($c) => $pordim[$c] ?? '', array_keys($dims)));
}

\core\dataformat::download_data(clean_filename('test_personalidad_' . date('Ymd')), $formato, $columnas, $filas);
