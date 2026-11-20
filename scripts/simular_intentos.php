<?php
// Intentos SIMULADOS de los estudiantes demo en cuadernillos y simulacros. Idempotente.
//
// Uso (después de seed_simulacros.php):
//   docker compose exec moodle php /opt/mediar/scripts/simular_intentos.php
//   docker compose exec moodle php /opt/mediar/scripts/simular_intentos.php --reiniciar   (borra y regenera)
//
// Genera intentos reales del Cuestionario de Moodle (calificados por Moodle, con fecha en el pasado)
// para que las estadísticas de la demo tengan datos. Modelo de respuesta (reproducible):
//   P(acierto) = logística(base + habilidad + área + colegio + mejora en el tiempo
//                          − dificultad − debilidad del grupo en el componente/competencia)
// Así aparecen patrones que la analítica debe detectar: Geometría, textos discontinuos, Química e
// Historia cuestan más, y las competencias de argumentación/indagación salen más bajas.
//
// Para la demo en vivo quedan SIN intento en las actividades abiertas: est.cps01 y est.alm01.

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

\core\session\manager::set_user(get_admin());
mt_srand(2027);

// --reiniciar: borra los intentos de los estudiantes demo (con la API del cuestionario) y los regenera.
[$opciones] = cli_get_params(['reiniciar' => false]);
if ($opciones['reiniciar']) {
    $borrados = 0;
    $intentos = $DB->get_records_sql("SELECT qa.* FROM {quiz_attempts} qa
        JOIN {user} u ON u.id = qa.userid WHERE u.username LIKE 'est.%'");
    foreach ($intentos as $intento) {
        quiz_delete_attempt($intento, $DB->get_record('quiz', ['id' => $intento->quiz]));
        $borrados++;
    }
    cli_writeln("Intentos anteriores borrados: $borrados");
}

$tz = new DateTimeZone('America/Bogota');
$dircontenido = '/opt/mediar/contenido/preguntas';
$ahora = time();
$reservados = ['est.cps01', 'est.alm01']; // presentan en vivo lo que está abierto

// Metadatos de cada pregunta (componente, competencia, dificultad) desde el JSON.
$meta = [];
foreach (glob("$dircontenido/*.json") as $archivo) {
    $json = json_decode(file_get_contents($archivo), true, 512, JSON_THROW_ON_ERROR);
    foreach ($json['preguntas'] as $p) {
        $meta[$p['id']] = $p + ['area' => $json['area']];
    }
}

$dificultad = ['baja' => -0.9, 'media' => 0.0, 'alta' => 0.9];
// Debilidades del grupo (lo que la analítica debería señalar como "a reforzar").
$debilidad = [
    'Geometría' => 0.6, 'Estadística y probabilidad' => 0.3, 'Textos discontinuos' => 0.5,
    'Química' => 0.5, 'Historia' => 0.4, 'Gramática en contexto' => 0.3,
    'Argumentación' => 0.5, 'Reflexionar y evaluar un texto' => 0.4, 'Indagación' => 0.4,
    'Pensamiento reflexivo y sistémico' => 0.4,
];
// Momento de cada actividad en el proceso (la mejora crece con el tiempo).
$momento = ['C1' => 0.0, 'S1' => 0.4, 'C2' => 1.0, 'S2' => 1.1, 'C3' => 1.4];
$colegio = ['CPS' => 0.15, 'ALM' => -0.15];

function normal(float $media = 0, float $desv = 1): float {
    $u = max(mt_rand() / mt_getrandmax(), 1e-9);
    $v = mt_rand() / mt_getrandmax();
    return $media + $desv * sqrt(-2 * log($u)) * cos(2 * M_PI * $v);
}
function azar(): float {
    return mt_rand() / mt_getrandmax();
}

// Perfil fijo de cada estudiante: habilidad general y ajuste por área.
$estudiantes = $DB->get_records_select('user', "username LIKE 'est.%' AND deleted = 0", null, 'username');
$perfil = [];
foreach ($estudiantes as $u) {
    $perfil[$u->id] = [
        'habilidad' => normal(0, 0.8),
        'area' => array_combine(['matematicas', 'lectura', 'sociales', 'naturales', 'ingles'],
            array_map(fn() => normal(0, 0.45), range(1, 5))),
        'colegio' => $colegio[strtoupper(substr($u->username, 4, 3))] ?? 0,
    ];
}

/** Respuesta incorrecta verosímil para una pregunta. */
function respuesta_incorrecta(question_definition $q, question_attempt $qa, array $correcta): array {
    if ($q instanceof qtype_multichoice_single_question) {
        $opciones = array_keys($q->get_order($qa));
        $otras = array_values(array_diff($opciones, [(int) $correcta['answer']]));
        return ['answer' => $otras[array_rand($otras)]];
    }
    if ($q instanceof qtype_multichoice_multi_question) {
        // Marca una correcta y una incorrecta (en el banco vale 0 puntos).
        $total = count($q->get_order($qa));
        $buenas = array_keys(array_filter($correcta));
        $malas = array_values(array_diff(array_map(fn($i) => "choice$i", range(0, $total - 1)), $buenas));
        $r = array_fill_keys(array_map(fn($i) => "choice$i", range(0, $total - 1)), 0);
        $r[$buenas[array_rand($buenas)]] = 1;
        $r[$malas[array_rand($malas)]] = 1;
        return $r;
    }
    if ($q instanceof qtype_truefalse_question) {
        return ['answer' => $correcta['answer'] ? 0 : 1];
    }
    return ['answer' => 'no sé']; // respuesta corta
}

// Actividades: código del idnumber del módulo (Q-<NRC>-C1...) -> momento y ventana de fechas.
$cms = $DB->get_records_sql("SELECT cm.id, cm.idnumber, cm.course, cm.instance, q.timeopen, q.timeclose, q.timelimit
    FROM {course_modules} cm
    JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
    JOIN {quiz} q ON q.id = cm.instance
    WHERE cm.idnumber LIKE 'Q-%' ORDER BY cm.idnumber");

$creados = 0;
$omitidos = 0;
foreach ($cms as $cm) {
    $codigo = substr($cm->idnumber, strrpos($cm->idnumber, '-') + 1); // C1, C2, C3, S1, S2, S3
    if (!isset($momento[$codigo]) || $cm->timeopen > $ahora) {
        continue; // actividad futura (Simulacro 3)
    }
    $abierta = $cm->timeclose > $ahora;
    $participacion = $abierta ? 0.55 : 0.92;
    $finventana = min($cm->timeclose, $ahora - HOURSECS);
    $quizobj = \mod_quiz\quiz_settings::create($cm->instance);
    $contexto = context_course::instance($cm->course);
    $matriculados = get_enrolled_users($contexto, 'mod/quiz:attempt', 0, 'u.id, u.username');

    $n = 0;
    foreach ($matriculados as $u) {
        if (!isset($perfil[$u->id])
                || ($abierta && in_array($u->username, $reservados))
                || $DB->record_exists('quiz_attempts', ['quiz' => $cm->instance, 'userid' => $u->id])) {
            continue;
        }
        if (azar() > $participacion && !in_array($u->username, $reservados)) {
            continue; // no presentó
        }

        // Fechas: simulacros a primera hora del día; cuadernillos repartidos en la ventana.
        $essimulacro = $codigo[0] === 'S';
        $inicio = $essimulacro
            ? $cm->timeopen + mt_rand(0, 25 * MINSECS)
            : mt_rand($cm->timeopen, max($cm->timeopen, $finventana - $cm->timelimit));
        $duracion = (int) ($cm->timelimit * (0.45 + 0.5 * azar()));
        $fin = $inicio + $duracion;

        $quizuser = \mod_quiz\quiz_settings::create($cm->instance, $u->id);
        $attempt = quiz_prepare_and_start_new_attempt($quizuser, 1, null, false, [], [], $u->id);
        $attemptobj = \mod_quiz\quiz_attempt::create($attempt->id);
        $quba = question_engine::load_questions_usage_by_activity($attempt->uniqueid);

        $respuestas = [];
        foreach ($attemptobj->get_slots() as $slot) {
            $qa = $quba->get_question_attempt($slot);
            $q = $qa->get_question();
            $info = $meta[$q->idnumber] ?? null;
            if (!$info) {
                continue;
            }
            if (azar() < 0.03) {
                continue; // la deja en blanco
            }
            $p = $perfil[$u->id];
            $logit = 0.45 + $p['habilidad'] + $p['area'][$info['area']] + $p['colegio'] + 0.75 * $momento[$codigo]
                - $dificultad[$info['dificultad']]
                - ($debilidad[$info['componente']] ?? 0) - ($debilidad[$info['competencia']] ?? 0);
            $acierta = azar() < 1 / (1 + exp(-$logit));
            $correcta = $q->get_correct_response();
            $postdata = $acierta ? $correcta : respuesta_incorrecta($q, $qa, $correcta);
            // La simulación de Moodle identifica las opciones por su texto (no por su posición):
            // la propia pregunta hace la conversión.
            $respuestas[$slot] = $q->get_student_response_values_for_simulation($postdata);
        }

        $attemptobj->process_submitted_actions($fin - 60, false, $respuestas);
        $attemptobj->process_submit($fin, false);
        $attemptobj->process_grade_submission($fin);

        // Fechas en el pasado: el intento y sus pasos se crearon "ahora".
        $DB->set_field('quiz_attempts', 'timestart', $inicio, ['id' => $attempt->id]);
        $DB->execute("UPDATE {question_attempt_steps} SET timecreated = ?
            WHERE timecreated > ? AND questionattemptid IN (
                SELECT id FROM {question_attempts} WHERE questionusageid = ?)", [$inicio, $fin, $attempt->uniqueid]);
        $DB->set_field('quiz_grades', 'timemodified', $fin, ['quiz' => $cm->instance, 'userid' => $u->id]);
        $n++;
    }
    $creados += $n;
    cli_writeln(sprintf('  %-12s %3d intentos%s', $cm->idnumber, $n, $abierta ? ' (abierto: participación parcial)' : ''));
}

cli_heading('Resumen');
cli_writeln("Intentos creados: $creados");
$filas = $DB->get_records_sql("SELECT q.id, q.name, ROUND(AVG(g.grade / q.grade * 100)::numeric, 1) AS prom, COUNT(g.id) AS n
    FROM {quiz} q JOIN {quiz_grades} g ON g.quiz = q.id
    GROUP BY q.id, q.name ORDER BY q.id");
$porNombre = [];
foreach ($filas as $f) {
    $porNombre[$f->name][] = $f;
}
foreach ($porNombre as $nombre => $grupo) {
    $prom = array_sum(array_column($grupo, 'prom')) / count($grupo);
    printf("  %-32s promedio %5.1f %%  (%d intentos)\n", $nombre, $prom, array_sum(array_column($grupo, 'n')));
}
