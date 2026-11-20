<?php
// Panel de inicio Mediar en el Área personal de todos los usuarios. Idempotente.
//
// Uso (después de desplegar local_mediar y block_mediar_dashboard con deploy.sh):
//   docker compose exec moodle php /opt/mediar/scripts/setup_dashboard.php
//
// Hace:
//   - Ajustes de local_mediar: fecha del examen (cuenta regresiva) y umbral de alerta.
//   - Eventos de curso de la demo (talleres de refuerzo) para "Próximas actividades".
//   - Área personal por defecto: quita los bloques de Moodle y deja solo el panel Mediar,
//     y la aplica a todos los usuarios (reinicia sus áreas personales).
//   - El Área personal es la página de inicio.

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/blocklib.php');
require_once($CFG->dirroot . '/my/lib.php');
require_once($CFG->dirroot . '/calendar/lib.php');

\core\session\manager::set_user(get_admin());
$tz = new DateTimeZone('America/Bogota');

// 1. Ajustes.
if (!get_config('local_mediar', 'fechaicfes')) {
    set_config('fechaicfes', '2027-03-07', 'local_mediar'); // misma fecha de referencia que seed_estructura.php
}
if (!get_config('local_mediar', 'umbralalerta')) {
    set_config('umbralalerta', 50, 'local_mediar');
}
cli_writeln('Fecha del examen: ' . get_config('local_mediar', 'fechaicfes') . ' · umbral de alerta: '
    . get_config('local_mediar', 'umbralalerta') . ' %');

// 2. Eventos de curso (refuerzo de los temas que el grupo tiene más bajos).
$eventos = [
    'MAT' => ['Taller de refuerzo: Geometría', '2026-11-25 15:00'],
    'SOC' => ['Debate: mecanismos de participación', '2026-11-26 16:00'],
    'NAT' => ['Laboratorio virtual de Química', '2026-11-27 14:00'],
    'LEC' => ['Club de lectura: tablas e infografías', '2026-12-02 15:00'],
    'ING' => ['Speaking practice', '2026-12-03 15:00'],
];
$n = 0;
foreach ($DB->get_records_select('course', "idnumber ~ '^[0-9]+$'") as $course) {
    $area = \local_mediar\datos::curso($course)['area'];
    if (!isset($eventos[$area])) {
        continue;
    }
    [$nombre, $fecha] = $eventos[$area];
    $existe = $DB->record_exists_select('event', $DB->sql_compare_text('name') . ' = ' . $DB->sql_compare_text(':n')
        . ' AND courseid = :c', ['n' => $nombre, 'c' => $course->id]);
    if (!$existe) {
        calendar_event::create((object) [
            'name' => $nombre,
            'description' => '<p>Sesión de refuerzo con el profesor del curso.</p>',
            'format' => FORMAT_HTML,
            'eventtype' => 'course',
            'courseid' => $course->id,
            'userid' => get_admin()->id,
            'timestart' => (new DateTimeImmutable($fecha, $tz))->getTimestamp(),
            'timeduration' => HOURSECS,
        ], false);
        $n++;
    }
}
cli_writeln("Eventos de curso creados: $n");

// 3. Área personal por defecto: solo el panel Mediar.
$pagina = my_get_page(null, MY_PAGE_PRIVATE);
$syscontext = context_system::instance();
$bloques = $DB->get_records('block_instances', [
    'parentcontextid' => $syscontext->id, 'pagetypepattern' => 'my-index', 'subpagepattern' => $pagina->id,
]);
$tiene = false;
foreach ($bloques as $b) {
    if ($b->blockname === 'mediar_dashboard') {
        $tiene = true;
        continue;
    }
    blocks_delete_instance($b);
    cli_writeln("  quitado bloque {$b->blockname}");
}
if (!$tiene) {
    $id = $DB->insert_record('block_instances', (object) [
        'blockname' => 'mediar_dashboard',
        'parentcontextid' => $syscontext->id,
        'showinsubcontexts' => 0,
        'requiredbytheme' => 0,
        'pagetypepattern' => 'my-index',
        'subpagepattern' => $pagina->id,
        'defaultregion' => 'content',
        'defaultweight' => 0,
        'configdata' => '',
        'timecreated' => time(),
        'timemodified' => time(),
    ]);
    context_block::instance($id);
    cli_writeln('  agregado el panel Mediar');
}
my_reset_page_for_all_users(MY_PAGE_PRIVATE, 'my-index');
cli_writeln('Área personal reiniciada para todos los usuarios');

// 4. Página de inicio: el Área personal.
set_config('defaulthomepage', HOMEPAGE_MY);
purge_all_caches();
cli_writeln('Listo.');
