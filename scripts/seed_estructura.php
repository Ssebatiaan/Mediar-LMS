<?php
// Estructura académica de la DEMO de Mediar. Idempotente: se puede ejecutar varias veces
// sin duplicar nada (busca cada elemento por su idnumber / shortname / username).
//
// Uso (desde la raíz del proyecto, en el host):
//   docker compose exec moodle php /opt/mediar/scripts/seed_estructura.php
//
// Crea:
//   - Campos personalizados de curso: "NRC" y "Área Saber 11".
//   - Categoría por colegio > subcategoría por promoción.
//   - Por colegio: 5 cursos de asignatura (formato semanal hasta el ICFES) + 1 curso de Simulacros ICFES.
//   - Cuentas demo: 1 administrador Mediar, 5 profesores (uno por área, en todos los colegios), estudiantes.
//   - Cohorte por colegio/promoción con matrícula automática a sus 6 cursos.
//   - Evento de sitio con la fecha de referencia del examen Saber 11.
//
// Todas las cuentas demo usan la misma clave que el admin (variable MOODLE_PASSWORD del .env).

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->dirroot . '/enrol/cohort/locallib.php');
require_once($CFG->dirroot . '/calendar/lib.php');
require_once($CFG->libdir . '/testing/generator/lib.php');

\core\session\manager::set_user(get_admin());
force_current_language('es'); // Fechas y textos generados en español (el CLI arranca en inglés).

// ---------------------------------------------------------------------------
// Datos de la demo
// ---------------------------------------------------------------------------

$tz = 'America/Bogota';
// Inicio del proceso de preparación (lunes) y fecha de REFERENCIA del examen.
// Ajustar a la fecha oficial publicada por el ICFES.
$inicio = make_timestamp(2026, 10, 5, 0, 0, 0, $tz);
$fechaicfes = make_timestamp(2027, 3, 7, 7, 0, 0, $tz);
$semanas = (int) ceil(($fechaicfes - $inicio) / WEEKSECS);

$password = getenv('MOODLE_PASSWORD') ?: 'Mediar2026!';
$dominio = 'demo.mediar.local';

// Orden = valor del campo select "Área Saber 11" (1..6).
$areas = [
    'matematicas' => ['nombre' => 'Matemáticas', 'corto' => 'MATEMÁTICAS', 'temas' => [
        'Pensamiento numérico: números reales, proporciones y porcentajes',
        'Álgebra y funciones: expresiones, ecuaciones y gráficas',
        'Geometría: áreas, volúmenes y semejanza',
        'Estadística y probabilidad: tablas, gráficos y medidas de tendencia',
    ]],
    'lectura' => ['nombre' => 'Lectura Crítica', 'corto' => 'LECTURA CRÍTICA', 'temas' => [
        'Identificar y entender los contenidos locales de un texto',
        'Comprender cómo se articulan las partes de un texto',
        'Reflexionar a partir de un texto y evaluar su contenido',
        'Textos discontinuos: tablas, infografías y caricaturas',
    ]],
    'sociales' => ['nombre' => 'Sociales y Ciudadanas', 'corto' => 'SOCIALES Y CIUDADANAS', 'temas' => [
        'Constitución Política y ciudadanía',
        'Pensamiento social: historia y geografía de Colombia',
        'Interpretación y análisis de perspectivas',
        'Pensamiento reflexivo y sistémico',
    ]],
    'naturales' => ['nombre' => 'Ciencias Naturales', 'corto' => 'CIENCIAS NATURALES', 'temas' => [
        'Entorno vivo: célula, genética y ecosistemas',
        'Entorno físico (química): materia, mezclas y reacciones',
        'Entorno físico (física): movimiento, fuerzas y energía',
        'Ciencia, tecnología y sociedad',
    ]],
    'ingles' => ['nombre' => 'Inglés', 'corto' => 'INGLÉS', 'temas' => [
        'Avisos y vocabulario cotidiano (A1)',
        'Gramática en contexto: completar conversaciones',
        'Comprensión de textos cortos (A2)',
        'Comprensión de textos largos (B1)',
    ]],
    'simulacro' => ['nombre' => 'Simulacros ICFES', 'corto' => 'SIMULACROS ICFES', 'temas' => []],
];
$areaindex = array_flip(array_keys($areas)); // matematicas => 0 ...

$colegios = [
    'CPS' => [
        'nombre' => 'Colegio Promoción Social',
        'promocion' => '2027',
        'nrcinicial' => 1119,
        'estudiantes' => 20,
    ],
    'ALM' => [
        'nombre' => 'Colegio Los Almendros',
        'promocion' => '2027',
        'nrcinicial' => 1125,
        'estudiantes' => 12,
    ],
];

$admin = ['username' => 'coordinacion', 'firstname' => 'Laura', 'lastname' => 'Mendoza Ríos'];

$profesores = [
    'matematicas' => ['username' => 'prof.matematicas', 'firstname' => 'Carlos Andrés', 'lastname' => 'Herrera Díaz'],
    'lectura'     => ['username' => 'prof.lectura',     'firstname' => 'Diana Marcela', 'lastname' => 'Rojas Pineda'],
    'sociales'    => ['username' => 'prof.sociales',    'firstname' => 'Javier Eduardo', 'lastname' => 'Castillo Vega'],
    'naturales'   => ['username' => 'prof.naturales',   'firstname' => 'Natalia', 'lastname' => 'Gómez Pardo'],
    'ingles'      => ['username' => 'prof.ingles',      'firstname' => 'Andrés Felipe', 'lastname' => 'Morales Suárez'],
];

// Nombres ficticios para estudiantes (se combinan de forma determinista).
$nombres = ['Valentina', 'Santiago', 'Mariana', 'Juan David', 'Isabella', 'Samuel', 'Sofía', 'Alejandro',
    'Daniela', 'Mateo', 'Gabriela', 'Sebastián', 'Camila', 'Nicolás', 'Luciana', 'Tomás', 'Sara', 'Emmanuel',
    'Antonella', 'Miguel Ángel', 'Paula Andrea', 'Julián', 'Manuela', 'Esteban', 'Salomé', 'Jerónimo',
    'Valeria', 'Juan José', 'María José', 'Felipe', 'Laura Sofía', 'David'];
$apellidos = ['García Torres', 'Martínez Ruiz', 'Rodríguez Peña', 'López Arias', 'Hernández Cano',
    'Pérez Mejía', 'Sánchez Ortiz', 'Ramírez Salas', 'Torres Vargas', 'Flórez Rincón', 'Jiménez Mora',
    'Castro Ibáñez', 'Romero Lozano', 'Moreno Acosta', 'Ortega Barrios', 'Silva Navarro', 'Rueda Cárdenas',
    'Polo Guerrero', 'Medina Ospina', 'Cabrera Quintero', 'Pacheco Solano', 'Rincón Duarte', 'Vargas Montes',
    'Herazo Pupo', 'Julio Marrugo', 'Cassiani Díaz', 'Padilla Arrieta', 'Villalba Ríos', 'Ospino Cuello',
    'Barrios Tous', 'Caraballo Julio', 'Mendoza Beltrán'];

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

$gen = new testing_data_generator();
$roles = [
    'manager' => $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST),
    'editingteacher' => $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST),
    'student' => $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST),
];
$stats = ['creado' => 0, 'existente' => 0];

function informar(string $accion, string $que): void {
    global $stats;
    $stats[$accion === 'creado' ? 'creado' : 'existente']++;
    cli_writeln(sprintf('  [%s] %s', $accion, $que));
}

function obtener_usuario(array $datos, string $idnumber): stdClass {
    global $DB, $CFG, $password, $dominio;
    if ($u = $DB->get_record('user', ['username' => $datos['username'], 'deleted' => 0])) {
        informar('existe', "usuario {$datos['username']}");
        return $u;
    }
    $u = (object) [
        'username' => $datos['username'],
        'password' => $password,
        'firstname' => $datos['firstname'],
        'lastname' => $datos['lastname'],
        'email' => $datos['username'] . '@' . $dominio,
        'idnumber' => $idnumber,
        'auth' => 'manual',
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
        'lang' => 'es',
        'country' => 'CO',
        'timezone' => '99',
    ];
    $u->id = \core\user::create_user($u, true, false);
    informar('creado', "usuario {$datos['username']} ({$datos['firstname']} {$datos['lastname']})");
    return $DB->get_record('user', ['id' => $u->id]);
}

function obtener_categoria(string $nombre, string $idnumber, int $parent, string $descripcion): core_course_category {
    global $DB;
    if ($id = $DB->get_field('course_categories', 'id', ['idnumber' => $idnumber])) {
        informar('existe', "categoría $nombre");
        return core_course_category::get($id);
    }
    $cat = core_course_category::create([
        'name' => $nombre,
        'idnumber' => $idnumber,
        'parent' => $parent,
        'description' => $descripcion,
        'descriptionformat' => FORMAT_HTML,
    ]);
    informar('creado', "categoría $nombre");
    return $cat;
}

function asegurar_campo(\core_course\customfield\course_handler $handler, \core_customfield\category_controller $cat,
        string $shortname, string $tipo, string $nombre, array $configdata): void {
    foreach ($cat->get_fields() as $f) {
        if ($f->get('shortname') === $shortname) {
            informar('existe', "campo de curso $shortname");
            return;
        }
    }
    $field = \core_customfield\field_controller::create(0, (object) ['type' => $tipo, 'categoryid' => $cat->get('id')], $cat);
    $handler->save_field_configuration($field, (object) [
        'name' => $nombre,
        'shortname' => $shortname,
        'description' => '',
        'descriptionformat' => FORMAT_HTML,
        'configdata' => $configdata + ['required' => 0, 'uniquevalues' => 0, 'locked' => 1,
            'visibility' => \core_course\customfield\course_handler::VISIBLETOALL],
    ]);
    informar('creado', "campo de curso $shortname");
}

// ---------------------------------------------------------------------------
// 1. Campos personalizados de curso
// ---------------------------------------------------------------------------
cli_heading('Campos personalizados de curso');
$handler = \core_course\customfield\course_handler::create();
$cfcat = null;
foreach ($handler->get_categories_with_fields() as $c) {
    if ($c->get('name') === 'Mediar') {
        $cfcat = $c;
    }
}
if (!$cfcat) {
    $cfcat = \core_customfield\category_controller::create($handler->create_category('Mediar'), null, $handler);
}
asegurar_campo($handler, $cfcat, 'nrc', 'text', 'NRC', [
    'defaultvalue' => '', 'displaysize' => 10, 'maxlength' => 10, 'ispassword' => 0, 'link' => '', 'linktarget' => '',
    'uniquevalues' => 1,
]);
asegurar_campo($handler, $cfcat, 'area', 'select', 'Área Saber 11', [
    'options' => implode("\n", array_column($areas, 'nombre')), 'defaultvalue' => '',
]);
$handler = \core_course\customfield\course_handler::create(); // recargar configuración

// ---------------------------------------------------------------------------
// 2. Usuarios: administrador Mediar y profesores
// ---------------------------------------------------------------------------
cli_heading('Administrador y profesores');
$coord = obtener_usuario($admin, 'ADM-001');
role_assign($roles['manager'], $coord->id, context_system::instance()->id);

$profs = [];
$i = 1;
foreach ($profesores as $area => $datos) {
    $profs[$area] = obtener_usuario($datos, sprintf('PROF-%03d', $i++));
}

// ---------------------------------------------------------------------------
// 3. Colegios, cursos, estudiantes y cohortes
// ---------------------------------------------------------------------------
$raiz = obtener_categoria('Colegios', 'COLEGIOS', 0,
    '<p>Instituciones educativas atendidas por Mediar.</p>');

$cohortplugin = enrol_get_plugin('cohort');
$manualplugin = enrol_get_plugin('manual');
$idx = 0; // índice global para nombres de estudiantes

foreach ($colegios as $codigo => $col) {
    cli_heading($col['nombre']);
    $catcol = obtener_categoria($col['nombre'], "COL-$codigo", $raiz->id,
        "<p>Cursos de preparación Saber 11 de {$col['nombre']}.</p>");
    $catprom = obtener_categoria("Promoción {$col['promocion']}", "$codigo-{$col['promocion']}", $catcol->id,
        "<p>Estudiantes que presentan la prueba Saber 11 en {$col['promocion']}.</p>");

    // Cohorte del colegio/promoción (contexto de la categoría de la promoción).
    $cohortidn = "$codigo-{$col['promocion']}";
    if (!$cohort = $DB->get_record('cohort', ['idnumber' => $cohortidn])) {
        $cohort = (object) [
            'contextid' => context_coursecat::instance($catprom->id)->id,
            'name' => "{$col['nombre']} - Promoción {$col['promocion']}",
            'idnumber' => $cohortidn,
            'description' => 'Estudiantes del colegio. Al agregar un estudiante aquí queda matriculado en todos los cursos de su promoción.',
            'descriptionformat' => FORMAT_HTML,
        ];
        $cohort->id = cohort_add_cohort($cohort);
        informar('creado', "cohorte {$cohort->name}");
    } else {
        informar('existe', "cohorte {$cohort->name}");
    }

    // Cursos.
    $nrc = $col['nrcinicial'];
    foreach ($areas as $clave => $area) {
        $esimulacro = ($clave === 'simulacro');
        $shortname = "{$area['corto']}-$nrc-{$col['promocion']}";
        if ($course = $DB->get_record('course', ['idnumber' => (string) $nrc])) {
            informar('existe', "curso $shortname");
        } else {
            $data = (object) [
                'category' => $catprom->id,
                'fullname' => "{$area['nombre']} - {$col['nombre']}",
                'shortname' => $shortname,
                'idnumber' => (string) $nrc,
                'summary' => $esimulacro
                    ? "<p>Simulacros completos tipo Saber 11 (las 5 áreas). Puntaje global en escala ICFES de 0 a 500.</p>"
                    : "<p>Preparación Saber 11 en {$area['nombre']}. Cuadernillos de práctica por competencias, "
                        . "recursos semanales y seguimiento de resultados.</p>",
                'summaryformat' => FORMAT_HTML,
                'format' => $esimulacro ? 'topics' : 'weeks',
                'numsections' => $esimulacro ? 3 : $semanas,
                'startdate' => $inicio,
                'enddate' => $fechaicfes,
                'visible' => 1,
                'lang' => 'es',
                'enablecompletion' => 1,
                'showgrades' => 1,
                'newsitems' => 3,
                'customfield_nrc' => (string) $nrc,
                'customfield_area' => $areaindex[$clave] + 1,
            ];
            if (!$esimulacro) {
                $data->automaticenddate = 0;
            }
            $course = create_course($data);
            informar('creado', "curso $shortname ({$data->fullname})");

            if ($esimulacro) {
                $nombressim = ['Simulacro 1 - Diagnóstico', 'Simulacro 2 - Intermedio', 'Simulacro 3 - Final'];
                foreach ($nombressim as $n => $nombre) {
                    $sec = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $n + 1]);
                    course_update_section($course, $sec, ['name' => $nombre,
                        'summary' => '<p>Prueba completa: Lectura Crítica, Matemáticas, Sociales y Ciudadanas, '
                            . 'Ciencias Naturales e Inglés.</p>', 'summaryformat' => FORMAT_HTML]);
                }
            } else {
                // Temas de las primeras semanas.
                foreach ($area['temas'] as $n => $tema) {
                    $sec = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $n + 1]);
                    course_update_section($course, $sec,
                        ['summary' => "<p><strong>Tema:</strong> $tema</p>", 'summaryformat' => FORMAT_HTML]);
                }
                // Recurso de ejemplo en la semana 1.
                $gen->create_module('page', [
                    'course' => $course->id,
                    'section' => 1,
                    'name' => 'Cómo funciona este curso',
                    'intro' => '',
                    'content' => '<h3>Tu ruta de preparación</h3><ol>'
                        . '<li>Cada semana revisa el tema y los recursos (videos, guías, lecturas).</li>'
                        . '<li>Resuelve el cuadernillo de práctica de la semana.</li>'
                        . '<li>Consulta tus resultados para ver en qué competencias debes reforzar.</li></ol>',
                    'contentformat' => FORMAT_HTML,
                ]);
            }
        }

        // Bienvenida (sección general). Se reescribe siempre, así refleja cambios de fecha o profesor.
        $intro = $esimulacro
            ? '<p>Bienvenido al espacio de <strong>simulacros completos</strong>. Aquí se presentan las pruebas '
                . 'que combinan las 5 áreas evaluadas por el ICFES.</p>'
            : "<p>Bienvenido al curso de <strong>{$area['nombre']}</strong>. Tu profesor es "
                . fullname($profs[$clave]) . '. Cada semana encontrarás los temas, recursos y cuadernillos de práctica.</p>';
        $intro .= '<p><strong>Fecha de referencia del examen Saber 11:</strong> '
            . userdate($fechaicfes, '%d de %B de %Y', $tz) . '.</p>';
        course_update_section($course, $DB->get_record('course_sections', ['course' => $course->id, 'section' => 0]),
            ['summary' => $intro, 'summaryformat' => FORMAT_HTML]);

        // Profesores: el del área; en Simulacros, todos (cada uno aporta las preguntas de su área).
        $instmanual = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        $docentes = $esimulacro ? $profs : [$profs[$clave]];
        foreach ($docentes as $p) {
            $manualplugin->enrol_user($instmanual, $p->id, $roles['editingteacher']);
        }

        // Estudiantes: matrícula automática por cohorte.
        if (!$DB->record_exists('enrol', ['courseid' => $course->id, 'enrol' => 'cohort', 'customint1' => $cohort->id])) {
            $cohortplugin->add_instance($course, [
                'name' => "Cohorte {$cohort->name}",
                'customint1' => $cohort->id,
                'roleid' => $roles['student'],
                'customint2' => 0,
            ]);
        }
        $nrc++;
    }

    // Estudiantes del colegio -> cohorte (la cohorte los matricula en los 6 cursos).
    for ($n = 1; $n <= $col['estudiantes']; $n++) {
        $datos = [
            'username' => sprintf('est.%s%02d', strtolower($codigo), $n),
            'firstname' => $nombres[$idx % count($nombres)],
            'lastname' => $apellidos[($idx * 7) % count($apellidos)],
        ];
        $idx++;
        $est = obtener_usuario($datos, sprintf('%s-%s-%02d', $codigo, $col['promocion'], $n));
        cohort_add_member($cohort->id, $est->id);
    }
}

// Sincronizar matrículas por cohorte (por si los cursos se crearon antes que los miembros).
enrol_cohort_sync(new null_progress_trace());

// ---------------------------------------------------------------------------
// 4. Evento de sitio: fecha del examen
// ---------------------------------------------------------------------------
cli_heading('Calendario');
$nombreevento = 'Examen Saber 11 (fecha de referencia)';
if (!$DB->record_exists_select('event', $DB->sql_compare_text('name') . ' = ' . $DB->sql_compare_text(':name')
        . " AND eventtype = 'site'", ['name' => $nombreevento])) {
    calendar_event::create((object) [
        'name' => $nombreevento,
        'description' => '<p>Fecha de referencia para la demo. Ajustar a la fecha oficial publicada por el ICFES.</p>',
        'format' => FORMAT_HTML,
        'eventtype' => 'site',
        'courseid' => SITEID,
        'userid' => get_admin()->id,
        'timestart' => $fechaicfes,
        'timeduration' => 9 * HOURSECS,
    ], false);
    informar('creado', "evento $nombreevento");
} else {
    informar('existe', "evento $nombreevento");
}

purge_all_caches();
cli_heading('Resumen');
cli_writeln("Creados: {$stats['creado']} · Ya existían: {$stats['existente']}");
cli_writeln("Semanas por curso: $semanas · Clave de todas las cuentas demo: la del admin (.env)");
