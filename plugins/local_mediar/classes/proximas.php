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

namespace local_mediar;

/**
 * "Próximas actividades": eventos del calendario de Moodle (aperturas y cierres de cuestionarios,
 * eventos de curso y del sitio), en orden de fecha.
 *
 * Para un estudiante se omiten los cierres de cuestionarios que ya presentó.
 *
 * @package    local_mediar
 * @copyright  2026 Mediar
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class proximas {
    /** @var int Días hacia adelante que se consultan. */
    public const DIAS = 120;

    /**
     * Próximas actividades de un usuario.
     *
     * @param int $userid
     * @param int $limite máximo de elementos a devolver
     * @param bool $todoslossitios true = todos los cursos (vista de administración)
     * @return array ['total' => int, 'items' => array]
     */
    public static function de_usuario(int $userid, int $limite = 5, bool $todoslossitios = false): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/calendar/lib.php');

        $desde = time();
        $hasta = $desde + self::DIAS * DAYSECS;
        $cursos = $todoslossitios
            ? $DB->get_fieldset_select('course', 'id', 'id <> ? AND visible = 1', [SITEID])
            : array_keys(enrol_get_all_users_courses($userid, true));
        $cursos[] = SITEID;
        // Los eventos de curso salen de la lista de cursos; $users solo afecta los eventos personales.
        // (Con $users = true la API del calendario falla cuando no hay grupos.)
        $eventos = calendar_get_legacy_events($desde, $hasta, [$userid], false, $cursos);

        $esestudiante = !$todoslossitios;
        $items = [];
        $vistos = [];
        foreach ($eventos as $ev) {
            $item = self::item($ev, $userid, $esestudiante);
            if (!$item) {
                continue;
            }
            // En la vista de administración el mismo cuestionario existe en varios colegios: uno basta.
            $clave = $item['titulo'] . '|' . $ev->timestart;
            if ($todoslossitios && isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $items[] = $item;
        }
        usort($items, fn($a, $b) => $a['timestart'] <=> $b['timestart']);
        return ['total' => count($items), 'items' => array_slice($items, 0, $limite)];
    }

    /**
     * Convierte un evento del calendario en un elemento de la lista (o null si no se muestra).
     *
     * @param \stdClass $ev
     * @param int $userid
     * @param bool $esestudiante
     * @return array|null
     */
    private static function item(\stdClass $ev, int $userid, bool $esestudiante): ?array {
        global $DB;
        $curso = '';
        if ($ev->courseid && $ev->courseid != SITEID && ($c = get_course($ev->courseid))) {
            $curso = format_string($c->shortname);
        }
        $url = new \moodle_url('/calendar/view.php', ['view' => 'day', 'time' => $ev->timestart]);
        $titulo = format_string($ev->name);
        $tipo = $ev->courseid == SITEID ? 'sitio' : 'evento';

        if ($ev->modulename === 'quiz') {
            $cm = get_coursemodule_from_instance('quiz', $ev->instance, $ev->courseid);
            if (!$cm || !$cm->visible) {
                return null;
            }
            if ($esestudiante && $ev->eventtype === 'close' && $DB->record_exists('quiz_attempts',
                    ['quiz' => $ev->instance, 'userid' => $userid, 'state' => 'finished'])) {
                return null; // ya lo presentó
            }
            $nombre = format_string($DB->get_field('quiz', 'name', ['id' => $ev->instance]));
            $tipo = $ev->eventtype === 'close' ? 'cierre' : 'apertura';
            $titulo = get_string('evento' . $tipo, 'local_mediar', $nombre);
            $url = new \moodle_url('/mod/quiz/view.php', ['id' => $cm->id]);
        } else if ($ev->modulename) {
            return null; // otros módulos: no se usan en la demo
        }

        return [
            'timestart' => (int) $ev->timestart,
            'dia' => (int) userdate($ev->timestart, '%d') . ' de ' . userdate($ev->timestart, '%B'),
            'hora' => userdate($ev->timestart, '%H:%M'),
            'titulo' => $titulo,
            'curso' => $curso ?: get_string('eventositio', 'local_mediar'),
            'url' => $url->out(false),
            'tipo' => $tipo,
            'escierre' => $tipo === 'cierre',
            'pronto' => $ev->timestart - time() < 3 * DAYSECS,
        ];
    }
}
