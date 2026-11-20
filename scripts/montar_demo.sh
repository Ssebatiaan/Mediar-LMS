#!/bin/sh
# Monta TODA la demo de Mediar sobre un Moodle recién instalado, en el orden correcto. Idempotente.
#
# Uso (desde la raíz del proyecto, en el host, con el contenedor arriba):
#   docker compose up -d                      # la primera vez instala Moodle (~3 min)
#   docker compose exec moodle sh /opt/mediar/scripts/montar_demo.sh
set -eu

S=/opt/mediar/scripts
paso() { printf '\n==> %s\n' "$1"; }

paso "1/10 Sitio (nombre, idioma, zona horaria)";          php $S/setup_site.php
paso "2/10 Traducciones locales (es_local)";              php $S/setup_idioma.php
paso "3/10 Colegios, cursos, usuarios y cohortes";         php $S/seed_estructura.php
paso "4/10 Plugins de Mediar (tema, analítica, panel, test)"; sh $S/deploy.sh
paso "5/10 Banco de preguntas";                           php $S/seed_banco.php
paso "6/10 Cuadernillos y simulacros";                    php $S/seed_simulacros.php
paso "7/10 Intentos simulados de los estudiantes";        php $S/simular_intentos.php
paso "8/10 Tema, logos e imágenes de curso";              php $S/setup_tema.php
paso "9/10 Panel de inicio";                              php $S/setup_dashboard.php
paso "10/10 Test de personalidad";                        php $S/setup_test.php

printf '\nDemo lista: http://localhost:8080  (cuentas en docs/guia-demo.md)\n'
