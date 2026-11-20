<?php
// Aplica la identidad visual de Mediar al sitio. Idempotente.
//
// Uso (desde la raíz del proyecto, en el host; antes desplegar el tema con deploy.sh):
//   docker compose exec moodle php /opt/mediar/scripts/setup_tema.php
//
// Hace:
//   - Activa el tema "mediar".
//   - Carga los logos en Administración > Apariencia > Logos (logo completo y compacto).
//   - Activa los modos de color claro/oscuro de Boost (por defecto: claro).
//   - Asigna a cada curso la imagen de su área (campo de curso "Área Saber 11").
//
// Las imágenes salen de assets/marca/ (generadas con scripts/marca/*.py).

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');

$assets = '/opt/mediar/assets/marca';
$fs = get_file_storage();
$syscontext = context_system::instance();

/**
 * Reemplaza el contenido de un área de archivos por un único archivo.
 */
function reemplazar_archivo(int $contextid, string $component, string $filearea, string $origen, string $nombre): void {
    global $fs;
    if (!is_readable($origen)) {
        cli_error("No se encuentra $origen (¿falta montar ./assets en docker-compose?)");
    }
    $fs->delete_area_files($contextid, $component, $filearea, 0);
    $fs->create_file_from_pathname([
        'contextid' => $contextid,
        'component' => $component,
        'filearea' => $filearea,
        'itemid' => 0,
        'filepath' => '/',
        'filename' => $nombre,
    ], $origen);
}

// 1. Tema.
if (!file_exists($CFG->dirroot . '/theme/mediar/config.php')) {
    cli_error('El tema no está desplegado. Ejecutar antes: sh /opt/mediar/scripts/deploy.sh');
}
set_config('theme', 'mediar');
cli_writeln('Tema activo: mediar');

// 2. Logos (ajustes nativos core_admin/logo y core_admin/logocompact).
reemplazar_archivo($syscontext->id, 'core_admin', 'logo', "$assets/logo-mediar.png", 'logo-mediar.png');
set_config('logo', '/logo-mediar.png', 'core_admin');
reemplazar_archivo($syscontext->id, 'core_admin', 'logocompact', "$assets/logo-mediar-icono.png", 'logo-mediar-icono.png');
set_config('logocompact', '/logo-mediar-icono.png', 'core_admin');
cli_writeln('Logos cargados');

// 3. Modos de color (Boost 5.3): el usuario elige claro/oscuro en su menú.
set_config('enablecolourmodes', 1, 'theme_boost');
set_config('defaultcolourmode', 'light', 'theme_boost');
cli_writeln('Modos de color claro/oscuro activados');

// 4. Imagen de curso por área (valor del select "area": 1..6).
$imagenes = [1 => 'matematicas', 2 => 'lectura', 3 => 'sociales', 4 => 'naturales', 5 => 'ingles', 6 => 'simulacro'];
$handler = \core_course\customfield\course_handler::create();
$n = 0;
foreach ($DB->get_records_select('course', 'id <> ?', [SITEID], 'id', 'id, shortname') as $course) {
    $area = null;
    foreach ($handler->get_instance_data($course->id) as $data) {
        if ($data->get_field()->get('shortname') === 'area') {
            $area = (int) $data->get_value();
        }
    }
    // Cursos sin área: el Banco de preguntas tiene su propia imagen.
    $archivo = !empty($imagenes[$area]) ? $imagenes[$area] . '.png'
        : ($DB->get_field('course', 'idnumber', ['id' => $course->id]) === 'BANCO' ? 'banco.png' : null);
    if (!$archivo) {
        continue;
    }
    reemplazar_archivo(context_course::instance($course->id)->id, 'course', 'overviewfiles',
        "$assets/cursos/$archivo", $archivo);
    $n++;
}
// La imagen de curso se guarda en caché.
cache::make('core', 'course_image')->purge();
cli_writeln("Imágenes de curso asignadas: $n");

theme_reset_all_caches();
purge_all_caches();
cli_writeln('Listo.');
