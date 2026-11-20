#!/bin/sh
# Devuelve la demo a su estado original después de una presentación:
#   - regenera los intentos de cuadernillos/simulacros de los estudiantes (los mismos de siempre:
#     la simulación usa semilla fija) y deja est.cps01 y est.alm01 sin el Cuadernillo 3;
#   - regenera los resultados del test de personalidad y deja est.cps01 y est.alm01 sin presentarlo.
# No toca cursos, preguntas, usuarios ni configuración.
#
# Uso: docker compose exec moodle sh /opt/mediar/scripts/restaurar_demo.sh
set -eu

S=/opt/mediar/scripts
php $S/simular_intentos.php --reiniciar | tail -7
php $S/setup_test.php --reiniciar | tail -2
php /var/www/html/admin/cli/purge_caches.php
printf '\nDemo restaurada.\n'
