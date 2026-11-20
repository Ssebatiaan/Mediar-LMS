<?php
// Configuración base del sitio Mediar. Idempotente: se puede ejecutar varias veces.
//
// Uso (desde la raíz del proyecto, en el host):
//   docker compose exec moodle php /opt/mediar/scripts/setup_site.php

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');

// Nombre del sitio (vive en el "curso" del sitio, id = SITEID).
$site = get_site();
$site->fullname = 'Mediar - Plataforma Académica';
$site->shortname = 'Mediar';
$site->summary = 'Preparación para las pruebas Saber 11. Potencializa tu realidad.';
$DB->update_record('course', $site);

// Regional: Colombia.
set_config('timezone', 'America/Bogota');
set_config('forcetimezone', 'America/Bogota');
set_config('country', 'CO');
set_config('lang', 'es');
set_config('calendar_startwday', 1); // La semana empieza el lunes.

// Navegación: "Mis cursos" (tarjetas de curso) y "Área personal" activos.
set_config('enablemycourses', 1);
set_config('enabledashboard', 1);


purge_all_caches();
cli_writeln('Sitio configurado: ' . $site->fullname);
