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

namespace local_mediartest;

use stdClass;

/**
 * Intentos de los estudiantes: iniciar, guardar respuestas página a página (se puede reanudar) y finalizar.
 *
 * @package    local_mediartest
 * @copyright  2026 Mediar
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class intentos {
    /** @var int En progreso. */
    public const EN_PROGRESO = 0;
    /** @var int Finalizado. */
    public const FINALIZADO = 1;

    /**
     * Intento en progreso del usuario en un instrumento.
     *
     * @param int $userid
     * @param int $instrumentoid
     * @return stdClass|null
     */
    public static function en_progreso(int $userid, int $instrumentoid): ?stdClass {
        global $DB;
        return $DB->get_record('local_mediartest_intento',
            ['userid' => $userid, 'instrumentoid' => $instrumentoid, 'estado' => self::EN_PROGRESO], '*', IGNORE_MULTIPLE) ?: null;
    }

    /**
     * Último intento finalizado del usuario (de cualquier instrumento, el más reciente).
     *
     * @param int $userid
     * @return stdClass|null
     */
    public static function ultimo_finalizado(int $userid): ?stdClass {
        global $DB;
        $r = $DB->get_records('local_mediartest_intento', ['userid' => $userid, 'estado' => self::FINALIZADO],
            'timefinished DESC, id DESC', '*', 0, 1);
        return $r ? reset($r) : null;
    }

    /**
     * Crea un intento nuevo.
     *
     * @param int $userid
     * @param int $instrumentoid
     * @return stdClass
     */
    public static function iniciar(int $userid, int $instrumentoid): stdClass {
        global $DB;
        $ahora = time();
        $r = (object) ['instrumentoid' => $instrumentoid, 'userid' => $userid, 'estado' => self::EN_PROGRESO,
            'resultado' => null, 'timecreated' => $ahora, 'timemodified' => $ahora, 'timefinished' => 0];
        $r->id = $DB->insert_record('local_mediartest_intento', $r);
        return $r;
    }

    /**
     * Respuestas de un intento.
     *
     * @param int $intentoid
     * @return array código de ítem => valor
     */
    public static function respuestas(int $intentoid): array {
        global $DB;
        return array_map('intval', $DB->get_records_menu('local_mediartest_resp', ['intentoid' => $intentoid], '', 'item, valor'));
    }

    /**
     * Guarda (o actualiza) respuestas.
     *
     * @param stdClass $intento
     * @param array $respuestas código de ítem => valor
     */
    public static function guardar(stdClass $intento, array $respuestas): void {
        global $DB;
        $ahora = time();
        $existentes = $DB->get_records_menu('local_mediartest_resp', ['intentoid' => $intento->id], '', 'item, id');
        foreach ($respuestas as $item => $valor) {
            if (isset($existentes[$item])) {
                $DB->update_record('local_mediartest_resp', (object) ['id' => $existentes[$item], 'valor' => $valor, 'timemodified' => $ahora]);
            } else {
                $DB->insert_record('local_mediartest_resp', (object) ['intentoid' => $intento->id, 'item' => $item,
                    'valor' => $valor, 'timemodified' => $ahora]);
            }
        }
        $DB->set_field('local_mediartest_intento', 'timemodified', $ahora, ['id' => $intento->id]);
    }

    /**
     * Calcula el resultado y cierra el intento.
     *
     * @param stdClass $intento
     * @param instrumento $instrumento
     * @param int|null $cuando marca de tiempo de finalización (por defecto, ahora)
     * @return array resultado
     */
    public static function finalizar(stdClass $intento, instrumento $instrumento, ?int $cuando = null): array {
        global $DB;
        $resultado = motor::calcular($instrumento, self::respuestas($intento->id));
        $cuando ??= time();
        $DB->update_record('local_mediartest_intento', (object) [
            'id' => $intento->id, 'estado' => self::FINALIZADO,
            'resultado' => json_encode($resultado, JSON_UNESCAPED_UNICODE),
            'timemodified' => $cuando, 'timefinished' => $cuando,
        ]);
        return $resultado;
    }

    /**
     * Resumen para el panel de inicio: estado del test del usuario.
     *
     * @param int $userid
     * @return array|null null si no hay instrumento activo
     */
    public static function estado(int $userid): ?array {
        $instrumento = instrumento::activo();
        if (!$instrumento) {
            return null;
        }
        $final = self::ultimo_finalizado($userid);
        $progreso = self::en_progreso($userid, $instrumento->registro->id);
        $respondidas = $progreso ? count(self::respuestas($progreso->id)) : 0;
        return [
            'nombre' => $instrumento->def['nombre'],
            'finalizado' => (bool) $final,
            'perfil' => $final ? json_decode($final->resultado, true)['perfil'] : '',
            'enprogreso' => (bool) $progreso,
            'avance' => $progreso ? (int) round($respondidas / count($instrumento->items()) * 100) : 0,
            'items' => count($instrumento->items()),
            'url' => (new \moodle_url('/local/mediartest/index.php'))->out(false),
            'urlresultado' => (new \moodle_url('/local/mediartest/resultado.php'))->out(false),
        ];
    }
}
