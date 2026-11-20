<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace block_mediar_dashboard;

use context_course;
use context_system;
use local_mediar\areas;
use local_mediar\datos;
use local_mediar\estadisticas;
use local_mediar\graficos;
use local_mediar\vista;
use moodle_url;
use stdClass;

/**
 * Contenido del panel de inicio según el rol: estudiante, profesor o administración.
 *
 * @package    block_mediar_dashboard
 * @copyright  2026 Mediar
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class panel {
    /**
     * Rol que define la vista del panel.
     *
     * @param stdClass $user
     * @return string admin | profesor | estudiante
     */
    public static function rol(stdClass $user): string {
        if (has_capability('local/mediar:verestadisticasinstitucion', context_system::instance(), $user)) {
            return 'admin';
        }
        foreach (enrol_get_all_users_courses($user->id, true) as $c) {
            if (has_capability('local/mediar:verestadisticascurso', context_course::instance($c->id), $user)) {
                return 'profesor';
            }
        }
        return 'estudiante';
    }

    /**
     * Contexto completo de la plantilla block_mediar_dashboard/panel.
     *
     * @param stdClass $user
     * @return array
     */
    public static function contexto(stdClass $user): array {
        $rol = self::rol($user);
        $base = [
            'saludo' => get_string('hola', 'block_mediar_dashboard', format_string($user->firstname)),
            'cuenta' => datos::cuenta_regresiva(),
            'es' . $rol => true,
        ];
        return array_merge($base, match ($rol) {
            'admin' => self::admin($user),
            'profesor' => self::profesor($user),
            default => self::estudiante($user),
        });
    }

    /**
     * Acceso rápido.
     *
     * @param string $clave cadena del bloque
     * @param string $pix icono de Moodle
     * @param moodle_url|null $url null = deshabilitado (próximamente)
     * @return array
     */
    private static function acceso(string $clave, string $pix, ?moodle_url $url): array {
        global $OUTPUT;
        return ['nombre' => get_string('acceso_' . $clave, 'block_mediar_dashboard'),
            'icono' => $OUTPUT->pix_icon($pix, '', 'core', ['aria-hidden' => 'true']),
            'url' => $url?->out(false), 'deshabilitado' => $url === null];
    }

    // -------------------------------------------------------------------------------------------

    /**
     * Estudiante: progreso, cursos, fortalezas y pendientes.
     *
     * @param stdClass $user
     * @return array
     */
    private static function estudiante(stdClass $user): array {
        $d = estadisticas::estudiante($user->id);
        // Test de personalidad (local_mediartest, opcional): estado y enlace.
        $test = class_exists('\local_mediartest\intentos') ? \local_mediartest\intentos::estado($user->id) : null;
        $tarjetas = [];
        foreach ($d['cursos'] as $c) {
            if ($c['area'] === null) {
                continue;
            }
            $u = $c['ultima'];
            $tarjetas[] = vista::tarjeta($c, $u ? [
                'linea' => get_string('ultimoresultado', 'block_mediar_dashboard', (object) [
                    'evaluacion' => trim(strtok($u['nombre'], '·')),
                    'nota' => vista::num($u['nota'], $u['maximo'] >= 100 ? 0 : 1) . '/' . vista::num($u['maximo'], 0),
                ]),
                'pct' => (int) round($u['pct']), 'tienepct' => true,
            ] : ['linea' => get_string('sinresultados', 'block_mediar_dashboard')]);
        }
        return [
            'subtitulo' => datos::cohorte($user->id) ?: get_string('subtitulo_estudiante', 'block_mediar_dashboard'),
            'kpis' => vista::kpis_estudiante($d),
            'tarjetas' => $tarjetas,
            'graficoareas' => graficos::evolucion_areas($d['cursos']),
            'fortalezas' => vista::barras($d['fortalezas']),
            'debilidades' => vista::barras($d['debilidades']),
            'hayfortalezas' => !empty($d['fortalezas']),
            'urlprogreso' => (new moodle_url('/local/mediar/estudiante.php'))->out(false),
            'proximas' => vista::proximas($user->id),
            'test' => $test,
            'accesos' => [
                self::acceso('miprogreso', 'i/report', new moodle_url('/local/mediar/estudiante.php')),
                self::acceso('calificaciones', 'i/grades', new moodle_url('/grade/report/overview/index.php')),
                self::acceso('calendario', 'i/calendar', new moodle_url('/calendar/view.php', ['view' => 'month'])),
                self::acceso('mensajes', 't/message', new moodle_url('/message/index.php')),
                self::acceso('testpersonalidad', 'i/user', $test ? new moodle_url('/local/mediartest/index.php') : null),
                self::acceso('miscursos', 'i/course', new moodle_url('/my/courses.php')),
            ],
        ];
    }

    /**
     * Profesor: sus cursos (NRC y colegio) con el resultado del grupo, comparación y estudiantes en alerta.
     *
     * @param stdClass $user
     * @return array
     */
    private static function profesor(stdClass $user): array {
        $tarjetas = $asignatura = $alertas = $estudiantes = $promedios = [];
        $primero = null;
        $banco = null;
        foreach (enrol_get_all_users_courses($user->id, true, 'id, fullname, shortname, idnumber, category') as $course) {
            $ctx = context_course::instance($course->id);
            if (!has_capability('local/mediar:verestadisticascurso', $ctx, $user)) {
                continue;
            }
            if ($course->idnumber === 'BANCO') {
                $banco = $course;
                continue;
            }
            $c = estadisticas::curso($course);
            if ($c['area'] === null) {
                continue;
            }
            $u = $c['ultima'];
            $tarjetas[] = vista::tarjeta($c, [
                'linea' => $u ? get_string('resumengrupo', 'block_mediar_dashboard', (object) [
                    'evaluacion' => trim(strtok($u['quiz']->nombre, '·')),
                    'promedio' => vista::num($u['resumen']['promediopct'], 0),
                    'evaluados' => $u['resumen']['evaluados'], 'total' => $c['estudiantes'],
                ]) : get_string('nestudiantes', 'local_mediar', $c['estudiantes']),
                'pct' => $u ? (int) round($u['resumen']['promediopct']) : 0, 'tienepct' => (bool) $u,
                'urlestadisticas' => (new moodle_url('/local/mediar/curso.php', ['id' => $course->id]))->out(false),
            ]);
            $estudiantes += $c['usuarios'];
            if ($c['area'] !== areas::SIMULACRO) {
                $asignatura[] = $c;
                $primero ??= $course;
                if ($u) {
                    $promedios[] = $u['resumen']['promediopct'];
                }
                foreach ($c['alertas'] as $uid => $pct) {
                    $alertas[] = [
                        'nombre' => fullname($c['usuarios'][$uid]), 'curso' => $c['corto'],
                        'pct' => vista::num($pct, 0), 'pctnum' => $pct,
                        'url' => (new moodle_url('/local/mediar/estudiante.php', ['id' => $uid]))->out(false),
                    ];
                }
            }
        }
        usort($alertas, fn($a, $b) => $a['pctnum'] <=> $b['pctnum']);

        $accesos = [];
        if ($banco) {
            $accesos[] = self::acceso('banco', 'e/question', new moodle_url('/course/view.php', ['id' => $banco->id]));
        }
        if ($primero) {
            $accesos[] = self::acceso('crearcuadernillo', 't/add',
                new moodle_url('/course/modedit.php', ['add' => 'quiz', 'course' => $primero->id, 'section' => 0, 'return' => 0]));
            $accesos[] = self::acceso('calificaciones', 'i/grades', new moodle_url('/grade/report/grader/index.php', ['id' => $primero->id]));
        }
        $accesos[] = self::acceso('calendario', 'i/calendar', new moodle_url('/calendar/view.php', ['view' => 'month']));
        $accesos[] = self::acceso('mensajes', 't/message', new moodle_url('/message/index.php'));
        $accesos[] = self::acceso('miscursos', 'i/course', new moodle_url('/my/courses.php'));

        $area = $asignatura ? $asignatura[0]['areanombre'] : '';
        return [
            'subtitulo' => get_string('subtitulo_profesor', 'block_mediar_dashboard', $area),
            'kpis' => [
                ['etiqueta' => get_string('kpi_cursos', 'block_mediar_dashboard'), 'valor' => count($tarjetas)],
                ['etiqueta' => get_string('kpi_estudiantes', 'block_mediar_dashboard'), 'valor' => count($estudiantes)],
                ['etiqueta' => get_string('kpi_promedio', 'block_mediar_dashboard'), 'destacado' => true,
                    'valor' => $promedios ? vista::num(array_sum($promedios) / count($promedios), 0) : '—', 'unidad' => ' %'],
                ['etiqueta' => get_string('kpi_alertas', 'block_mediar_dashboard'), 'valor' => count($alertas),
                    'detalle' => get_string('kpi_alertas_detalle', 'block_mediar_dashboard',
                        (int) (get_config('local_mediar', 'umbralalerta') ?: 50))],
            ],
            'tarjetas' => $tarjetas,
            'graficocomparacion' => graficos::comparacion_cursos($asignatura),
            'alertas' => array_slice($alertas, 0, 6),
            'hayalertas' => !empty($alertas),
            'masalertas' => count($alertas) > 6 ? count($alertas) - 6 : 0,
            'proximas' => vista::proximas($user->id),
            'accesos' => $accesos,
        ];
    }

    /**
     * Administración: indicadores de la institución, resultados por colegio y por área.
     *
     * @param stdClass $user
     * @return array
     */
    private static function admin(stdClass $user): array {
        global $DB;
        $rolestudiante = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $rolprofesor = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        $cuenta = fn($rol) => $DB->count_records_sql("SELECT COUNT(DISTINCT ra.userid) FROM {role_assignments} ra
            JOIN {context} c ON c.id = ra.contextid AND c.contextlevel = ? WHERE ra.roleid = ?", [CONTEXT_COURSE, $rol]);

        $colegios = [];
        $porarea = [];
        $cursosactivos = 0;
        foreach ($DB->get_records_select('course_categories', "idnumber LIKE 'COL-%'", null, 'sortorder') as $cat) {
            $categoria = \core_course_category::get($cat->id, IGNORE_MISSING, true);
            $usuarios = [];
            $pcts = [];
            $simulacro = null;
            $esperados = $presentados = 0;
            foreach ($categoria->get_courses(['recursive' => true]) as $cl) {
                $c = estadisticas::curso(get_course($cl->id));
                if ($c['area'] === null) {
                    continue;
                }
                $cursosactivos++;
                $usuarios += $c['usuarios'];
                foreach ($c['evaluaciones'] as $e) {
                    if ($e['resumen']['evaluados'] === 0) {
                        continue;
                    }
                    if ($c['area'] === areas::SIMULACRO) {
                        $simulacro = $e;
                    } else {
                        $pcts[] = $e['resumen']['promediopct'];
                        $porarea[$c['area']][] = $e['resumen']['promediopct'];
                    }
                    if ($e['quiz']->timeclose && $e['quiz']->timeclose < time()) {
                        $esperados += $e['resumen']['matriculados'];
                        $presentados += $e['resumen']['evaluados'];
                    }
                }
            }
            $colegios[] = [
                'nombre' => $categoria->get_formatted_name(),
                'estudiantes' => count($usuarios),
                'promedio' => $pcts ? vista::num(array_sum($pcts) / count($pcts), 0) . ' %' : '—',
                'simulacro' => $simulacro ? vista::num($simulacro['resumen']['promedio'], 0) : '—',
                'simulacronombre' => $simulacro ? trim(strtok($simulacro['quiz']->nombre, '·')) : '',
                'participacion' => $esperados ? round($presentados / $esperados * 100) . ' %' : '—',
            ];
        }

        $evaluaciones = $DB->count_records_sql("SELECT COUNT(1) FROM {course_modules} cm
            JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz' WHERE cm.visible = 1 AND cm.deletioninprogress = 0");
        $intentos = $DB->count_records('quiz_attempts', ['state' => 'finished', 'preview' => 0]);

        return [
            'subtitulo' => get_string('subtitulo_admin', 'block_mediar_dashboard'),
            'kpis' => [
                ['etiqueta' => get_string('kpi_estudiantes', 'block_mediar_dashboard'), 'valor' => $cuenta($rolestudiante)],
                ['etiqueta' => get_string('kpi_profesores', 'block_mediar_dashboard'), 'valor' => $cuenta($rolprofesor)],
                ['etiqueta' => get_string('kpi_cursosactivos', 'block_mediar_dashboard'), 'valor' => $cursosactivos],
                ['etiqueta' => get_string('kpi_evaluaciones', 'block_mediar_dashboard'), 'valor' => $evaluaciones],
                ['etiqueta' => get_string('kpi_intentos', 'block_mediar_dashboard'), 'valor' => $intentos, 'destacado' => true],
            ],
            'colegios' => $colegios,
            'areas' => vista::barras(array_map(fn($codigo) => [
                'nombre' => areas::nombre($codigo), 'area' => $codigo, 'areanombre' => '',
                'pct' => array_sum($porarea[$codigo]) / count($porarea[$codigo]), 'n' => count($porarea[$codigo]),
            ], array_values(array_filter(array_keys(areas::AREAS), fn($c) => !empty($porarea[$c]))))),
            'proximas' => vista::proximas($user->id, true),
            'accesos' => [
                self::acceso('usuarios', 'i/users', new moodle_url('/admin/user.php')),
                self::acceso('subirusuarios', 'i/import', new moodle_url('/admin/tool/uploaduser/index.php')),
                self::acceso('cursos', 'i/course', new moodle_url('/course/management.php')),
                self::acceso('cohortes', 'i/cohort', new moodle_url('/cohort/index.php')),
                self::acceso('ajustes', 'i/settings',
                    has_capability('local/mediar:configurar', context_system::instance(), $user)
                    ? new moodle_url('/admin/settings.php', ['section' => 'local_mediar']) : null),
                self::acceso('testpersonalidad', 'i/user', class_exists('\local_mediartest\intentos')
                    && has_capability('local/mediartest:verresultados', context_system::instance(), $user)
                    ? new moodle_url('/local/mediartest/admin.php') : null),
                self::acceso('calendario', 'i/calendar', new moodle_url('/calendar/view.php', ['view' => 'month'])),
            ],
        ];
    }
}
