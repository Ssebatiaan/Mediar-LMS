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
 * Integración con la navegación de Moodle.
 *
 * @package    local_mediar
 * @copyright  2026 Mediar
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Agrega "Estadísticas Mediar" al menú del curso para quien puede verlas.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function local_mediar_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context) {
    if ($course->id == SITEID || !has_capability('local/mediar:verestadisticascurso', $context)) {
        return;
    }
    $navigation->add(
        get_string('estadisticas', 'local_mediar'),
        new moodle_url('/local/mediar/curso.php', ['id' => $course->id]),
        navigation_node::TYPE_CUSTOM,
        null,
        'mediarestadisticas',
        new pix_icon('i/report', '')
    );
}
