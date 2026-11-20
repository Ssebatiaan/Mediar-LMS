#!/bin/sh
# Imprime en JSON los IDs de la demo (cursos, actividades, usuarios) para verificar_demo.js.
# Uso (raíz del proyecto):  MEDIAR_IDS=$(sh herramientas/capturas/ids_demo.sh [proyecto]) node herramientas/capturas/verificar_demo.js
P=${1:-mediar}
MSYS_NO_PATHCONV=1 docker compose -p "$P" exec -T db psql -U moodle -d moodle -tA -c "
SELECT json_build_object(
  'mat', (SELECT id FROM mdl_course WHERE idnumber='1119'), 'lec', (SELECT id FROM mdl_course WHERE idnumber='1120'),
  'simCps', (SELECT id FROM mdl_course WHERE idnumber='1124'), 'matAlm', (SELECT id FROM mdl_course WHERE idnumber='1125'),
  'banco', (SELECT id FROM mdl_course WHERE idnumber='BANCO'),
  'cmC1', (SELECT id FROM mdl_course_modules WHERE idnumber='Q-1119-C1'), 'cmC3', (SELECT id FROM mdl_course_modules WHERE idnumber='Q-1119-C3'),
  'cmS2', (SELECT id FROM mdl_course_modules WHERE idnumber='Q-1124-S2'), 'cmBanco', (SELECT id FROM mdl_course_modules WHERE idnumber='BANCO-MAT'),
  'cps01', (SELECT id FROM mdl_user WHERE username='est.cps01'), 'cps02', (SELECT id FROM mdl_user WHERE username='est.cps02'),
  'alm01', (SELECT id FROM mdl_user WHERE username='est.alm01'));"
