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

namespace local_mediartest\privacy;

use context;
use context_user;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacidad (Ley 1581 de 2012 / herramientas de privacidad de Moodle): las respuestas y resultados
 * del test son datos personales del estudiante, guardados en su contexto de usuario. Se pueden
 * exportar y borrar con las solicitudes de datos de Moodle.
 *
 * @package    local_mediartest
 * @copyright  2026 Mediar
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\core_userlist_provider,
        \core_privacy\local\request\plugin\provider {

    /**
     * Datos que guarda el plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_mediartest_intento', [
            'userid' => 'privacy:metadata:intento:userid',
            'estado' => 'privacy:metadata:intento:estado',
            'resultado' => 'privacy:metadata:intento:resultado',
            'timecreated' => 'privacy:metadata:intento:timecreated',
            'timefinished' => 'privacy:metadata:intento:timefinished',
        ], 'privacy:metadata:intento');
        $collection->add_database_table('local_mediartest_resp', [
            'item' => 'privacy:metadata:resp:item',
            'valor' => 'privacy:metadata:resp:valor',
            'timemodified' => 'privacy:metadata:resp:timemodified',
        ], 'privacy:metadata:resp');
        return $collection;
    }

    /**
     * Contextos con datos del usuario: su contexto de usuario.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $lista = new contextlist();
        $lista->add_from_sql("SELECT ctx.id FROM {context} ctx
            JOIN {local_mediartest_intento} i ON i.userid = ctx.instanceid AND ctx.contextlevel = :nivel
            WHERE i.userid = :userid", ['nivel' => CONTEXT_USER, 'userid' => $userid]);
        return $lista;
    }

    /**
     * Usuarios con datos en un contexto.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context instanceof context_user) {
            $userlist->add_from_sql('userid', 'SELECT userid FROM {local_mediartest_intento} WHERE userid = :userid',
                ['userid' => $context->instanceid]);
        }
    }

    /**
     * Exporta intentos, respuestas y resultados.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_user || (int) $context->instanceid !== (int) $userid) {
                continue;
            }
            $intentos = $DB->get_records('local_mediartest_intento', ['userid' => $userid], 'id');
            foreach ($intentos as $i) {
                $instr = $DB->get_record('local_mediartest_instr', ['id' => $i->instrumentoid]);
                $datos = (object) [
                    'instrumento' => $instr ? "{$instr->nombre} ({$instr->version})" : '',
                    'estado' => $i->estado ? get_string('finalizado', 'local_mediartest') : get_string('enprogreso', 'local_mediartest'),
                    'iniciado' => transform::datetime($i->timecreated),
                    'finalizado' => $i->timefinished ? transform::datetime($i->timefinished) : '',
                    'resultado' => $i->resultado ? json_decode($i->resultado) : null,
                    'respuestas' => array_values(array_map(fn($r) => ['item' => $r->item, 'valor' => (int) $r->valor],
                        $DB->get_records('local_mediartest_resp', ['intentoid' => $i->id], 'id'))),
                ];
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_mediartest'), get_string('intento', 'local_mediartest') . " {$i->id}"], $datos);
            }
        }
    }

    /**
     * Borra todos los datos de un contexto (de usuario).
     *
     * @param context $context
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        if ($context instanceof context_user) {
            self::borrar_usuario((int) $context->instanceid);
        }
    }

    /**
     * Borra los datos del usuario aprobado.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_user && (int) $context->instanceid === (int) $contextlist->get_user()->id) {
                self::borrar_usuario((int) $context->instanceid);
            }
        }
    }

    /**
     * Borra los datos de varios usuarios de un contexto.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if ($context instanceof context_user && in_array($context->instanceid, $userlist->get_userids())) {
            self::borrar_usuario((int) $context->instanceid);
        }
    }

    /**
     * Borra intentos y respuestas de un usuario.
     *
     * @param int $userid
     */
    private static function borrar_usuario(int $userid): void {
        global $DB;
        $ids = $DB->get_fieldset_select('local_mediartest_intento', 'id', 'userid = ?', [$userid]);
        if ($ids) {
            [$sql, $params] = $DB->get_in_or_equal($ids);
            $DB->delete_records_select('local_mediartest_resp', "intentoid $sql", $params);
            $DB->delete_records('local_mediartest_intento', ['userid' => $userid]);
        }
    }
}
