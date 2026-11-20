<?php
// Cuadernillos y simulacros de la DEMO de Mediar (actividad Cuestionario nativa). Idempotente.
//
// Uso (después de seed_estructura.php y seed_banco.php):
//   docker compose exec moodle php /opt/mediar/scripts/seed_simulacros.php
//
// En cada curso de asignatura (formato semanal):
//   Cuadernillo 1 · Diagnóstico  (10 preguntas, semana del 12 oct)  -> calificación sobre 10
//   Cuadernillo 2 · Seguimiento  (10 preguntas, semana del 9 nov)   -> sobre 10
//   Cuadernillo 3 · Repaso       (20 preguntas, del 16 al 29 nov)   -> sobre 20 (abierto ahora)
// En cada curso "Simulacros ICFES":
//   Simulacro 1 · Diagnóstico (25 oct), 2 · Intermedio (15 nov), 3 · Final (21 feb 2027)
//   Puntaje sobre 500 con la ponderación ICFES: cada pregunta de Lectura, Matemáticas, Sociales y
//   Naturales vale 3 puntos y cada una de Inglés vale 1. Así la nota de Moodle escalada a 500 es
//   ((LC×3 + MAT×3 + SOC×3 + NAT×3 + ING×1) / 13) × 5, con cada área en escala 0–100.
//
// Las preguntas salen del Banco de preguntas Mediar por su idnumber (campo "cuadernillo" del JSON).

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->libdir . '/testing/generator/lib.php');

\core\session\manager::set_user(get_admin());
force_current_language('es');

$tz = 'America/Bogota';
$dircontenido = '/opt/mediar/contenido/preguntas';
$gen = new testing_data_generator();

// Orden de las áreas (como en la prueba) y peso ICFES de sus preguntas.
$areas = [
    'lectura'     => ['prefijo' => 'LEC', 'peso' => 3, 'campo' => 2],
    'matematicas' => ['prefijo' => 'MAT', 'peso' => 3, 'campo' => 1],
    'sociales'    => ['prefijo' => 'SOC', 'peso' => 3, 'campo' => 3],
    'naturales'   => ['prefijo' => 'NAT', 'peso' => 3, 'campo' => 4],
    'ingles'      => ['prefijo' => 'ING', 'peso' => 1, 'campo' => 5],
];
// Preguntas por área y cuadernillo, en el orden del JSON.
$porcuadernillo = [];
foreach ($areas as $clave => $a) {
    $json = json_decode(file_get_contents("$dircontenido/$clave.json"), true, 512, JSON_THROW_ON_ERROR);
    foreach ($json['preguntas'] as $p) {
        $porcuadernillo[$clave][$p['cuadernillo']][] = $p['id'];
    }
}

/** Fecha en hora de Bogotá. */
function ts(string $fecha): int {
    global $tz;
    return (new DateTimeImmutable($fecha, new DateTimeZone($tz)))->getTimestamp();
}

/** Id de la versión más reciente de la pregunta con ese idnumber en el banco. */
function pregunta_por_idnumber(string $idnumber): int {
    global $DB;
    return (int) $DB->get_field_sql("SELECT qv.questionid
        FROM {question_bank_entries} qbe
        JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
        WHERE qbe.idnumber = ?
        ORDER BY qv.version DESC", [$idnumber], IGNORE_MULTIPLE) ?: throw new moodle_exception("No existe $idnumber");
}

/** Opciones de revisión: los cuadernillos muestran todo al terminar; los simulacros, solo el puntaje hasta el cierre. */
function opciones_revision(bool $simulacro): array {
    $campos = ['attempt', 'correctness', 'maxmarks', 'marks', 'specificfeedback', 'generalfeedback', 'rightanswer', 'overallfeedback'];
    $r = [];
    foreach ($campos as $c) {
        $r[$c . 'during'] = 0;
        $alterminar = !$simulacro || in_array($c, ['maxmarks', 'marks', 'overallfeedback']);
        $r[$c . 'immediately'] = (int) $alterminar;
        $r[$c . 'open'] = (int) $alterminar;
        $r[$c . 'closed'] = 1;
    }
    return $r;
}

/** Crea el cuestionario (si no existe) con sus preguntas. */
function asegurar_cuestionario(stdClass $course, string $idnumber, int $seccion, array $config, array $preguntas): void {
    global $DB, $gen;
    if ($DB->record_exists('course_modules', ['course' => $course->id, 'idnumber' => $idnumber])) {
        cli_writeln("  [existe] {$config['name']}");
        return;
    }
    $simulacro = !empty($config['simulacro']);
    unset($config['simulacro']);
    $quiz = $gen->create_module('quiz', array_merge([
        'course' => $course->id,
        'idnumber' => $idnumber,
        'attempts' => 1,
        'grademethod' => QUIZ_GRADEHIGHEST,
        'decimalpoints' => $simulacro ? 0 : 1,
        'shuffleanswers' => 1,
        'preferredbehaviour' => 'deferredfeedback',
        'questionsperpage' => 5,
        'navmethod' => 'free',
        'overduehandling' => 'autosubmit',
    ], opciones_revision($simulacro), $config), ['section' => $seccion]);

    // Preguntas: 5 por página. $preguntas = [[idnumber, puntos], ...].
    foreach (array_values($preguntas) as $i => [$qidnumber, $puntos]) {
        quiz_add_quiz_question(pregunta_por_idnumber($qidnumber), $quiz, intdiv($i, 5) + 1, $puntos);
    }
    \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();

    // Retroalimentación global según el porcentaje obtenido.
    $bandas = $simulacro
        ? [[0.75, '<p>Muy buen resultado. Mantén el ritmo y revisa las áreas con menor puntaje.</p>'],
           [0.5, '<p>Vas por buen camino. Revisa por área en qué competencias perdiste más puntos.</p>'],
           [0, '<p>Este resultado es un punto de partida. Con tu profesor define un plan de refuerzo por área.</p>']]
        : [[0.8, '<p>¡Excelente! Dominas los temas de este cuadernillo.</p>'],
           [0.6, '<p>Buen trabajo. Revisa la retroalimentación de las preguntas que fallaste.</p>'],
           [0, '<p>Necesitas reforzar estos temas. Repasa el material de la semana antes del próximo cuadernillo.</p>']];
    $max = $DB->get_field('quiz', 'grade', ['id' => $quiz->id]);
    $techo = $max + 1;
    foreach ($bandas as [$minimo, $texto]) {
        $DB->insert_record('quiz_feedback', (object) [
            'quizid' => $quiz->id, 'feedbacktext' => $texto, 'feedbacktextformat' => FORMAT_HTML,
            'mingrade' => $minimo * $max, 'maxgrade' => $techo,
        ]);
        $techo = $minimo * $max;
    }
    cli_writeln("  [creado] {$config['name']} (" . count($preguntas) . ' preguntas)');
}

// ---------------------------------------------------------------------------
// Cursos de asignatura: 3 cuadernillos
// ---------------------------------------------------------------------------
$handler = \core_course\customfield\course_handler::create();
$cuadernillos = [
    1 => ['nombre' => 'Cuadernillo 1 · Diagnóstico', 'seccion' => 2, 'abre' => '2026-10-12 07:00', 'cierra' => '2026-10-18 23:59',
          'cuadernillos' => [1], 'minutos' => 30],
    2 => ['nombre' => 'Cuadernillo 2 · Seguimiento', 'seccion' => 6, 'abre' => '2026-11-09 07:00', 'cierra' => '2026-11-15 23:59',
          'cuadernillos' => [2], 'minutos' => 30],
    3 => ['nombre' => 'Cuadernillo 3 · Repaso general', 'seccion' => 7, 'abre' => '2026-11-16 07:00', 'cierra' => '2026-11-29 23:59',
          'cuadernillos' => [1, 2], 'minutos' => 60],
];
$simulacros = [
    1 => ['nombre' => 'Simulacro 1 · Diagnóstico', 'seccion' => 1, 'abre' => '2026-10-25 07:00', 'cierra' => '2026-10-25 18:00',
          'cuadernillos' => [1], 'minutos' => 150],
    2 => ['nombre' => 'Simulacro 2 · Intermedio', 'seccion' => 2, 'abre' => '2026-11-15 07:00', 'cierra' => '2026-11-15 18:00',
          'cuadernillos' => [2], 'minutos' => 150],
    3 => ['nombre' => 'Simulacro 3 · Final', 'seccion' => 3, 'abre' => '2027-02-21 07:00', 'cierra' => '2027-02-21 18:00',
          'cuadernillos' => [1, 2], 'minutos' => 270],
];

$campoarea = array_column(array_map(fn($k, $a) => ['k' => $k, 'c' => $a['campo']], array_keys($areas), $areas), 'k', 'c');
$cursos = $DB->get_records_select('course', "idnumber ~ '^[0-9]+$'", null, 'idnumber');
foreach ($cursos as $course) {
    $area = null;
    foreach ($handler->get_instance_data($course->id) as $d) {
        if ($d->get_field()->get('shortname') === 'area') {
            $area = (int) $d->get_value();
        }
    }
    cli_heading("{$course->shortname}");

    if ($area === 6) {
        // Curso de simulacros completos: las 5 áreas, puntaje sobre 500.
        foreach ($simulacros as $n => $s) {
            $preguntas = [];
            foreach ($areas as $clave => $a) {
                foreach ($s['cuadernillos'] as $c) {
                    foreach ($porcuadernillo[$clave][$c] as $qid) {
                        $preguntas[] = [$qid, $a['peso']];
                    }
                }
            }
            asegurar_cuestionario($course, "Q-{$course->idnumber}-S$n", $s['seccion'], [
                'name' => $s['nombre'],
                'intro' => '<p>Simulacro completo tipo Saber 11 con las cinco áreas: Lectura Crítica, Matemáticas, '
                    . 'Sociales y Ciudadanas, Ciencias Naturales e Inglés.</p><p>El puntaje global va de 0 a 500 '
                    . 'y sigue la ponderación del ICFES (Inglés pesa menos que las otras cuatro áreas).</p>',
                'timeopen' => ts($s['abre']), 'timeclose' => ts($s['cierra']),
                'timelimit' => $s['minutos'] * MINSECS,
                'grade' => 500,
                'simulacro' => true,
            ], $preguntas);
        }
        continue;
    }

    $clave = $campoarea[$area] ?? null;
    if (!$clave) {
        continue;
    }
    foreach ($cuadernillos as $n => $c) {
        $preguntas = [];
        foreach ($c['cuadernillos'] as $cc) {
            foreach ($porcuadernillo[$clave][$cc] as $qid) {
                $preguntas[] = [$qid, 1];
            }
        }
        asegurar_cuestionario($course, "Q-{$course->idnumber}-C$n", $c['seccion'], [
            'name' => $c['nombre'],
            'intro' => '<p>Cuadernillo de práctica de ' . count($preguntas) . ' preguntas tipo Saber 11. '
                . 'Tienes ' . $c['minutos'] . ' minutos y un solo intento. Al terminar verás tu puntaje y la '
                . 'explicación de cada respuesta.</p>',
            'timeopen' => ts($c['abre']), 'timeclose' => ts($c['cierra']),
            'timelimit' => $c['minutos'] * MINSECS,
            'grade' => count($preguntas),
        ], $preguntas);
    }
}

rebuild_course_cache(0, true);
cli_heading('Resumen');
$filas = $DB->get_records_sql("SELECT q.id, c.idnumber AS nrc, q.name, q.grade, q.sumgrades,
        (SELECT COUNT(1) FROM {quiz_slots} s WHERE s.quizid = q.id) AS preguntas
    FROM {quiz} q JOIN {course} c ON c.id = q.course ORDER BY c.idnumber, q.id");
foreach ($filas as $f) {
    printf("  %-5s %-32s %3d preguntas  nota máx %3s  (suma de puntos %s)\n", $f->nrc, $f->name, $f->preguntas,
        (float) $f->grade, (float) $f->sumgrades);
}
