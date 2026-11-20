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

use context_course;
use core_course_category;
use stdClass;

/**
 * Lectura de datos para la analítica. No guarda nada: todo sale de las tablas nativas
 * (cursos, campos de curso, cuestionarios, intentos, banco de preguntas y etiquetas).
 *
 *  - Área de una pregunta: el banco (mod_qbank) donde vive, por su idnumber "BANCO-MAT".
 *  - Componente: la categoría de la pregunta en el banco.
 *  - Competencia: la etiqueta de la pregunta.
 *
 * @package    local_mediar
 * @copyright  2026 Mediar
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class datos {
    /** @var array Caché por petición de los datos de curso. */
    private static array $cursos = [];

    /**
     * Datos de presentación de un curso: NRC, área, colegio, promoción e imagen.
     *
     * @param stdClass $course
     * @return array
     */
    public static function curso(stdClass $course): array {
        if (isset(self::$cursos[$course->id])) {
            return self::$cursos[$course->id];
        }
        $nrc = $course->idnumber ?? '';
        $area = null;
        $handler = \core_course\customfield\course_handler::create();
        foreach ($handler->get_instance_data($course->id) as $data) {
            $campo = $data->get_field()->get('shortname');
            if ($campo === 'nrc' && $data->get_value()) {
                $nrc = $data->get_value();
            } else if ($campo === 'area') {
                $area = areas::por_campo((int) $data->get_value() ?: null);
            }
        }
        $promocion = $colegio = '';
        if ($cat = core_course_category::get($course->category, IGNORE_MISSING, true)) {
            $promocion = $cat->get_formatted_name();
            if ($cat->parent && ($padre = core_course_category::get($cat->parent, IGNORE_MISSING, true))) {
                $colegio = $padre->get_formatted_name();
            }
        }
        $imagen = \core_course\external\course_summary_exporter::get_course_image(get_course($course->id));
        return self::$cursos[$course->id] = [
            'id' => (int) $course->id,
            'nombre' => format_string($course->fullname, true, ['context' => context_course::instance($course->id)]),
            'corto' => format_string($course->shortname),
            'nrc' => $nrc,
            'area' => $area,
            'areanombre' => areas::nombre($area),
            'colegio' => $colegio,
            'promocion' => $promocion,
            'imagen' => $imagen ?: '',
            'url' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
        ];
    }

    /**
     * Cuestionarios visibles de un curso, en orden de apertura.
     *
     * @param int $courseid
     * @return array de stdClass (id, cmid, nombre, grade, timeopen, timeclose, url)
     */
    public static function evaluaciones(int $courseid): array {
        global $DB;
        $modinfo = get_fast_modinfo($courseid);
        $cms = array_filter($modinfo->get_instances_of('quiz'), fn($cm) => $cm->visible && !$cm->deletioninprogress);
        if (!$cms) {
            return [];
        }
        $quizzes = $DB->get_records_list('quiz', 'id', array_keys($cms), '', 'id, name, grade, timeopen, timeclose');
        $lista = [];
        foreach ($quizzes as $q) {
            $cm = $cms[$q->id];
            $lista[] = (object) [
                'id' => (int) $q->id,
                'cmid' => (int) $cm->id,
                'nombre' => format_string($q->name),
                'grade' => (float) $q->grade,
                'timeopen' => (int) $q->timeopen,
                'timeclose' => (int) $q->timeclose,
                'url' => (new \moodle_url('/mod/quiz/view.php', ['id' => $cm->id]))->out(false),
            ];
        }
        usort($lista, fn($a, $b) => [$a->timeopen ?: PHP_INT_MAX, $a->id] <=> [$b->timeopen ?: PHP_INT_MAX, $b->id]);
        return $lista;
    }

    /**
     * Mejor nota de cada usuario en cada cuestionario.
     *
     * @param int[] $quizids
     * @param int[]|null $userids null = todos
     * @return array [quizid][userid] => stdClass(grade, timemodified)
     */
    public static function notas(array $quizids, ?array $userids = null): array {
        global $DB;
        if (!$quizids || ($userids !== null && !$userids)) {
            return [];
        }
        [$qsql, $params] = $DB->get_in_or_equal($quizids, SQL_PARAMS_NAMED, 'q');
        $where = "quiz $qsql";
        if ($userids !== null) {
            [$usql, $uparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
            $where .= " AND userid $usql";
            $params += $uparams;
        }
        $notas = [];
        foreach ($DB->get_recordset_select('quiz_grades', $where, $params, '', 'id, quiz, userid, grade, timemodified') as $g) {
            $notas[$g->quiz][$g->userid] = (object) ['grade' => (float) $g->grade, 'timemodified' => (int) $g->timemodified];
        }
        return $notas;
    }

    /**
     * Estudiantes (quienes pueden presentar cuestionarios) de un curso.
     *
     * @param int $courseid
     * @return stdClass[] userid => usuario
     */
    public static function estudiantes(int $courseid): array {
        return get_enrolled_users(context_course::instance($courseid), 'mod/quiz:attempt', 0, 'u.*',
            'u.lastname, u.firstname', 0, 0, true);
    }

    /**
     * Respuesta a cada pregunta en los intentos finalizados, con área, componente y competencia.
     *
     * @param int[] $quizids
     * @param int[]|null $userids null = todos
     * @return stdClass[] (quiz, userid, timefinish, slot, maxmark, fraccion, qidnumber, pregunta, componente,
     *                     competencia, area)
     */
    public static function respuestas(array $quizids, ?array $userids = null): array {
        global $DB;
        if (!$quizids || ($userids !== null && !$userids)) {
            return [];
        }
        [$qsql, $params] = $DB->get_in_or_equal($quizids, SQL_PARAMS_NAMED, 'q');
        $where = "qa.quiz $qsql";
        if ($userids !== null) {
            [$usql, $uparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
            $where .= " AND qa.userid $usql";
            $params += $uparams;
        }
        $params['finished'] = 'finished';
        $params['nivel'] = CONTEXT_MODULE;
        $sql = "SELECT qat.id, qa.quiz, qa.userid, qa.timefinish, qat.slot, qat.maxmark, qas.fraction,
                       q.id AS questionid, q.name AS pregunta, qbe.idnumber AS qidnumber,
                       qc.name AS componente, cmb.idnumber AS banco
                  FROM {quiz_attempts} qa
                  JOIN {question_attempts} qat ON qat.questionusageid = qa.uniqueid
                  JOIN {question_attempt_steps} qas ON qas.questionattemptid = qat.id
                       AND qas.sequencenumber = (SELECT MAX(s2.sequencenumber) FROM {question_attempt_steps} s2
                                                  WHERE s2.questionattemptid = qat.id)
                  JOIN {question} q ON q.id = qat.questionid
                  JOIN {question_versions} qv ON qv.questionid = q.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                  JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                  JOIN {context} ctx ON ctx.id = qc.contextid
             LEFT JOIN {course_modules} cmb ON cmb.id = ctx.instanceid AND ctx.contextlevel = :nivel
                 WHERE $where AND qa.state = :finished AND qa.preview = 0";
        $filas = $DB->get_records_sql($sql, $params);

        // Competencia = etiqueta de la pregunta.
        $competencias = [];
        $qids = array_unique(array_column($filas, 'questionid'));
        if ($qids) {
            [$tsql, $tparams] = $DB->get_in_or_equal($qids, SQL_PARAMS_NAMED, 't');
            $tags = $DB->get_records_sql("SELECT ti.id, ti.itemid, t.rawname
                FROM {tag_instance} ti JOIN {tag} t ON t.id = ti.tagid
                WHERE ti.itemtype = 'question' AND ti.component = 'core_question' AND ti.itemid $tsql", $tparams);
            foreach ($tags as $t) {
                $competencias[$t->itemid] = $t->rawname;
            }
        }
        foreach ($filas as $f) {
            $f->fraccion = (float) ($f->fraction ?? 0);
            $f->maxmark = (float) $f->maxmark;
            $f->competencia = $competencias[$f->questionid] ?? '';
            $f->area = self::area_de_banco($f->banco, $f->qidnumber);
        }
        return array_values($filas);
    }

    /**
     * Área de una pregunta: por su banco (BANCO-MAT) o, si no, por el prefijo de su idnumber (MAT-001).
     *
     * @param string|null $banco
     * @param string|null $qidnumber
     * @return string|null
     */
    private static function area_de_banco(?string $banco, ?string $qidnumber): ?string {
        foreach ([substr((string) $banco, 6), strtok((string) $qidnumber, '-')] as $codigo) {
            if (isset(areas::AREAS[$codigo])) {
                return $codigo;
            }
        }
        return null;
    }

    /**
     * Colegio y promoción del estudiante, por su cohorte.
     *
     * @param int $userid
     * @return string
     */
    public static function cohorte(int $userid): string {
        global $DB;
        $nombres = $DB->get_fieldset_sql("SELECT c.name FROM {cohort} c
            JOIN {cohort_members} cm ON cm.cohortid = c.id WHERE cm.userid = ? ORDER BY c.name", [$userid]);
        return implode(' · ', array_map('format_string', $nombres));
    }

    /**
     * Días que faltan para la fecha de referencia del examen (ajuste local_mediar/fechaicfes).
     *
     * @return array|null ['dias' => int, 'fecha' => string]
     */
    public static function cuenta_regresiva(): ?array {
        $fecha = get_config('local_mediar', 'fechaicfes');
        if (!$fecha || !($dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $fecha, \core_date::get_user_timezone_object()))) {
            return null;
        }
        $dias = (int) floor(($dt->getTimestamp() - time()) / DAYSECS) + 1;
        return $dias > 0 ? ['dias' => $dias, 'fecha' => userdate($dt->getTimestamp(), get_string('strftimedate', 'langconfig'))] : null;
    }
}
