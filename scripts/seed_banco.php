<?php
// Banco de preguntas de la DEMO de Mediar. Idempotente.
//
// Uso (desde la raíz del proyecto, en el host):
//   docker compose exec moodle php /opt/mediar/scripts/seed_banco.php
//
// Crea el curso "Banco de preguntas Mediar" (categoría "Recursos Mediar") con un banco de
// preguntas compartido (mod_qbank) por área, y carga las preguntas de contenido/preguntas/*.json:
//   - Categoría del banco = COMPONENTE (Álgebra y funciones, Geometría...).
//   - Etiqueta de la pregunta = COMPETENCIA (Interpretación y representación...).
//   - idnumber de la pregunta = id del JSON (MAT-001...), para ubicarla desde los simulacros.
// El JSON se convierte a GIFT (formato nativo de Moodle) y se importa con el importador de Moodle.
// Si un banco ya tiene preguntas, no se vuelve a importar (no duplica).

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/question/format/gift/format.php');
require_once($CFG->libdir . '/testing/generator/lib.php');

\core\session\manager::set_user(get_admin());
force_current_language('es');

$dircontenido = '/opt/mediar/contenido/preguntas';
$gen = new testing_data_generator();

// ---------------------------------------------------------------------------
// GIFT
// ---------------------------------------------------------------------------

/** Escapa los caracteres especiales de GIFT. */
function gift_esc(string $s): string {
    return addcslashes(trim(preg_replace('/\s+/u', ' ', $s)), '~=#{}:');
}

/**
 * Convierte una pregunta del JSON a texto GIFT.
 *
 * @param array $p pregunta
 * @param array $textos textos compartidos del área (clave => HTML), referenciados con "texto"
 */
function pregunta_a_gift(array $p, array $textos = []): string {
    $tags = '[tag:' . str_replace(']', '\]', $p['competencia']) . ']';
    $out = "// [id:{$p['id']}] $tags\n";

    $titulo = $p['id'] . ' · ' . core_text::substr($p['enunciado'], 0, 60);
    $html = '';
    if (!empty($p['texto'])) {
        // Texto compartido por varias preguntas (lectura, aviso, tabla), como en el ICFES.
        $html .= '<div class="mediar-contexto">' . $textos[$p['texto']] . '</div>';
    }
    if (!empty($p['contexto'])) {
        $html .= '<div class="mediar-contexto">' . $p['contexto'] . '</div>';
    }
    $html .= '<p>' . $p['enunciado'] . '</p>';
    $out .= '::' . gift_esc($titulo) . '::[html]' . gift_esc($html) . " {\n";

    switch ($p['tipo']) {
        case 'unica':
            foreach ($p['opciones'] as $o) {
                $out .= (!empty($o['correcta']) ? '=' : '~') . gift_esc($o['texto'])
                    . (!empty($o['retro']) ? '#' . gift_esc($o['retro']) : '') . "\n";
            }
            break;
        case 'multiple':
            $k = count(array_filter($p['opciones'], fn($o) => !empty($o['correcta'])));
            $peso = rtrim(rtrim(number_format(100 / $k, 5, '.', ''), '0'), '.');
            foreach ($p['opciones'] as $o) {
                $out .= '~%' . (!empty($o['correcta']) ? $peso : "-$peso") . '%' . gift_esc($o['texto'])
                    . (!empty($o['retro']) ? '#' . gift_esc($o['retro']) : '') . "\n";
            }
            break;
        case 'vf':
            $out .= ($p['respuesta'] ? 'TRUE' : 'FALSE') . "\n";
            break;
        case 'corta':
            foreach ($p['respuestas'] as $r) {
                $out .= '=' . gift_esc($r) . "\n";
            }
            break;
        default:
            throw new coding_exception("Tipo de pregunta desconocido en {$p['id']}: {$p['tipo']}");
    }
    if (!empty($p['retro'])) {
        $out .= '####' . gift_esc($p['retro']) . "\n";
    }
    return $out . "}\n";
}

/** Archivo GIFT completo de un área: una sección $CATEGORY por componente. */
function area_a_gift(array $area): string {
    $out = '';
    foreach ($area['componentes'] as $componente) {
        $out .= '$CATEGORY: ' . gift_esc($componente) . "\n\n";
        foreach ($area['preguntas'] as $p) {
            if ($p['componente'] === $componente) {
                $out .= pregunta_a_gift($p, $area['textos'] ?? []) . "\n";
            }
        }
    }
    return $out;
}

// ---------------------------------------------------------------------------
// 1. Curso del banco
// ---------------------------------------------------------------------------
cli_heading('Curso del banco');
if (!$catid = $DB->get_field('course_categories', 'id', ['idnumber' => 'RECURSOS'])) {
    $catid = core_course_category::create([
        'name' => 'Recursos Mediar', 'idnumber' => 'RECURSOS', 'parent' => 0,
        'description' => '<p>Recursos compartidos por todos los colegios.</p>', 'descriptionformat' => FORMAT_HTML,
    ])->id;
    cli_writeln('  [creado] categoría Recursos Mediar');
}
if (!$banco = $DB->get_record('course', ['idnumber' => 'BANCO'])) {
    $banco = create_course((object) [
        'category' => $catid,
        'fullname' => 'Banco de preguntas Mediar',
        'shortname' => 'BANCO DE PREGUNTAS',
        'idnumber' => 'BANCO',
        'summary' => '<p>Bancos de preguntas compartidos por área Saber 11. Las preguntas se organizan por '
            . 'componente (categorías) y competencia (etiquetas), y se reutilizan en los cuadernillos y '
            . 'simulacros de todos los colegios.</p>',
        'summaryformat' => FORMAT_HTML,
        'format' => 'topics',
        'numsections' => 0,
        'visible' => 1,
        'lang' => 'es',
    ]);
    cli_writeln('  [creado] curso Banco de preguntas Mediar');
} else {
    cli_writeln('  [existe] curso Banco de preguntas Mediar');
}

// Todos los profesores editan el banco (así usan sus preguntas en los cursos de cada colegio).
$rolprofesor = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
$manual = enrol_get_plugin('manual');
$instancia = $DB->get_record('enrol', ['courseid' => $banco->id, 'enrol' => 'manual'], '*', MUST_EXIST);
foreach ($DB->get_records_select('user', "username LIKE 'prof.%' AND deleted = 0") as $prof) {
    $manual->enrol_user($instancia, $prof->id, $rolprofesor);
}

// ---------------------------------------------------------------------------
// 2. Un banco (mod_qbank) por área + importación de preguntas
// ---------------------------------------------------------------------------
$archivos = glob("$dircontenido/*.json");
sort($archivos);
foreach ($archivos as $archivo) {
    $area = json_decode(file_get_contents($archivo), true, 512, JSON_THROW_ON_ERROR);
    cli_heading("Banco · {$area['banco']}");

    $idnumber = "BANCO-{$area['prefijo']}";
    if ($cmid = $DB->get_field('course_modules', 'id', ['course' => $banco->id, 'idnumber' => $idnumber])) {
        cli_writeln("  [existe] banco $idnumber");
    } else {
        $modulo = $gen->create_module('qbank', [
            'course' => $banco->id,
            'name' => "Banco · {$area['banco']}",
            'intro' => "<p>Preguntas de {$area['banco']} por componente y competencia.</p>",
            'idnumber' => $idnumber,
        ], ['section' => 0]);
        $cmid = $modulo->cmid;
        cli_writeln("  [creado] banco $idnumber");
    }
    $contexto = context_module::instance($cmid);

    // La categoría "Por defecto en Banco · …" queda vacía (las preguntas van en sus componentes) y es la
    // que el banco abre primero. Se borra: así la vista por defecto es el primer componente, con preguntas.
    $limpiar = function() use ($DB, $contexto) {
        $vacias = $DB->get_records_sql("SELECT qc.id FROM {question_categories} qc
            WHERE qc.contextid = ? AND qc.parent <> 0 AND qc.name LIKE ?
              AND NOT EXISTS (SELECT 1 FROM {question_bank_entries} qbe WHERE qbe.questioncategoryid = qc.id)
              AND NOT EXISTS (SELECT 1 FROM {question_categories} h WHERE h.parent = qc.id)",
            [$contexto->id, 'Por defecto en%']);
        $otras = $DB->count_records_select('question_categories', 'contextid = ? AND parent <> 0', [$contexto->id]);
        foreach ($vacias as $c) {
            if ($otras > 1) {
                (new \core_question\category_manager())->delete_category($c->id);
                $otras--;
                cli_writeln('  [borrado] categoría por defecto vacía');
            }
        }
    };

    $existentes = $DB->count_records_sql("SELECT COUNT(1)
        FROM {question_bank_entries} qbe
        JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
        WHERE qc.contextid = ? AND qbe.idnumber LIKE ?", [$contexto->id, $area['prefijo'] . '-%']);
    if ($existentes > 0) {
        cli_writeln("  [existe] $existentes preguntas: no se reimporta");
        $limpiar();
        continue;
    }

    $gift = area_a_gift($area);
    $tmp = make_request_directory() . "/{$area['area']}.gift";
    file_put_contents($tmp, $gift);

    $qformat = new qformat_gift();
    $qformat->setCategory(question_get_default_category($contexto->id, true));
    $qformat->setContexts([$contexto]);
    $qformat->setCourse($banco);
    $qformat->setFilename($tmp);
    $qformat->setRealfilename("{$area['area']}.gift");
    $qformat->setMatchgrades('nearest');
    $qformat->setCatfromfile(true);
    $qformat->setContextfromfile(false);
    $qformat->setStoponerror(true);

    ob_start(); // El importador imprime HTML de progreso.
    $ok = $qformat->importpreprocess() && $qformat->importprocess() && $qformat->importpostprocess();
    $salida = ob_get_clean();
    if (!$ok) {
        cli_writeln(strip_tags($salida));
        cli_error("Falló la importación de {$area['area']}");
    }
    cli_writeln('  [creado] ' . count($area['preguntas']) . ' preguntas importadas');
    $limpiar();
}

// ---------------------------------------------------------------------------
// 3. Resumen por banco y componente
// ---------------------------------------------------------------------------
cli_heading('Resumen');
$filas = $DB->get_records_sql("SELECT qc.id, cm.idnumber AS banco, qc.name AS componente, COUNT(qbe.id) AS n
    FROM {course_modules} cm
    JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :nivel
    JOIN {question_categories} qc ON qc.contextid = ctx.id
    LEFT JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
    WHERE cm.course = :curso
    GROUP BY qc.id, cm.idnumber, qc.name
    HAVING COUNT(qbe.id) > 0
    ORDER BY cm.idnumber, qc.id", ['nivel' => CONTEXT_MODULE, 'curso' => $banco->id]);
foreach ($filas as $f) {
    printf("  %-10s %-32s %2d\n", $f->banco, $f->componente, $f->n);
}
