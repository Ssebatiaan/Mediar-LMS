<?php
// Verificación rápida de la estructura demo (solo lectura).
//   docker compose exec moodle php /opt/mediar/scripts/verificar_estructura.php
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');

$cursos = $DB->get_records_sql("SELECT c.id, c.idnumber, c.shortname, c.format, cc.name AS cat
    FROM {course} c JOIN {course_categories} cc ON cc.id = c.category
    WHERE c.id <> :site ORDER BY c.idnumber", ['site' => SITEID]);
$handler = \core_course\customfield\course_handler::create();

cli_heading('Cursos');
printf("%-5s %-32s %-6s %4s %4s %4s  %s\n", 'NRC', 'Shortname', 'Format', 'Est', 'Prof', 'Secc', 'Campos (NRC | Área)');
foreach ($cursos as $c) {
    $ctx = context_course::instance($c->id);
    $est = count(get_role_users($DB->get_field('role', 'id', ['shortname' => 'student']), $ctx));
    $prof = count(get_role_users($DB->get_field('role', 'id', ['shortname' => 'editingteacher']), $ctx));
    $secc = $DB->count_records('course_sections', ['course' => $c->id]) - 1;
    $campos = [];
    foreach ($handler->get_instance_data($c->id, true) as $d) {
        $campos[] = $d->export_value();
    }
    printf("%-5s %-32s %-6s %4d %4d %4d  %s\n", $c->idnumber, $c->shortname, $c->format, $est, $prof, $secc,
        implode(' | ', $campos));
}

cli_heading('Cursos por cuenta');
foreach (['coordinacion', 'prof.matematicas', 'prof.ingles', 'est.cps01', 'est.alm01'] as $username) {
    $u = $DB->get_record('user', ['username' => $username]);
    $mis = enrol_get_all_users_courses($u->id, true);
    $esmanager = user_has_role_assignment($u->id, $DB->get_field('role', 'id', ['shortname' => 'manager']),
        context_system::instance()->id);
    printf("%-18s %2d cursos%s  %s\n", $username, count($mis), $esmanager ? ' (Gestor del sitio)' : '',
        implode(', ', array_map(fn($c) => $c->idnumber, $mis)));
}
