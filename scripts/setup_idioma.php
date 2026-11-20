<?php
// Instala las traducciones locales de Mediar (contenido/idioma/es_local/*.php) en el paquete es_local
// de Moodle: completan cadenas que el paquete oficial «es» aún no tiene, sin modificarlo. Idempotente.
//
// Uso: docker compose exec moodle php /opt/mediar/scripts/setup_idioma.php

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');

$destino = $CFG->dataroot . '/lang/es_local';
check_dir_exists($destino, true, true);
foreach (glob('/opt/mediar/contenido/idioma/es_local/*.php') as $archivo) {
    copy($archivo, $destino . '/' . basename($archivo));
    cli_writeln('  instalado es_local/' . basename($archivo));
}
get_string_manager()->reset_caches();
cli_writeln('Listo: ' . get_string('attemptsubmitted', 'quiz') . ' · ' . get_string('colourmode:dark', 'theme_boost'));
