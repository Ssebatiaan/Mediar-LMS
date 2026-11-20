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

namespace local_mediar;

/**
 * Cálculos de la analítica: resúmenes por evaluación, por estudiante, por curso y por institución.
 *
 * Porcentajes en escala 0-100. En los simulacros (curso de área SIM) el puntaje global es la nota de
 * Moodle sobre 500 y el de cada área (0-100) sale de las preguntas de esa área.
 *
 * @package    local_mediar
 * @copyright  2026 Mediar
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class estadisticas {
    /**
     * Porcentaje de una nota.
     *
     * @param float|null $nota
     * @param float $maximo
     * @return float|null
     */
    public static function pct(?float $nota, float $maximo): ?float {
        return ($nota === null || $maximo <= 0) ? null : round($nota / $maximo * 100, 1);
    }

    /**
     * Resumen de una evaluación.
     *
     * @param array $notas userid => stdClass(grade)
     * @param float $maximo nota máxima del cuestionario
     * @param int $matriculados
     * @return array
     */
    public static function resumen(array $notas, float $maximo, int $matriculados): array {
        $valores = array_map(fn($n) => $n->grade, $notas);
        $n = count($valores);
        return [
            'evaluados' => $n,
            'matriculados' => $matriculados,
            'participacion' => $matriculados ? round($n / $matriculados * 100) : 0,
            'promedio' => $n ? round(array_sum($valores) / $n, 1) : null,
            'promediopct' => $n ? self::pct(array_sum($valores) / $n, $maximo) : null,
            'maximo' => $n ? max($valores) : null,
            'minimo' => $n ? min($valores) : null,
            'notamaxima' => $maximo,
        ];
    }

    /**
     * Distribución de resultados en 5 rangos de porcentaje.
     *
     * @param array $notas userid => stdClass(grade)
     * @param float $maximo
     * @return array [etiqueta => cantidad]
     */
    public static function distribucion(array $notas, float $maximo): array {
        $rangos = ['0–20 %' => 0, '21–40 %' => 0, '41–60 %' => 0, '61–80 %' => 0, '81–100 %' => 0];
        $etiquetas = array_keys($rangos);
        foreach ($notas as $n) {
            $p = self::pct($n->grade, $maximo);
            $rangos[$etiquetas[min(4, max(0, (int) ceil($p / 20) - 1))]]++;
        }
        return $rangos;
    }

    /**
     * Porcentaje de aciertos agrupado por componente o competencia (de menor a mayor).
     *
     * @param array $respuestas de datos::respuestas()
     * @param string $campo 'componente' o 'competencia'
     * @param int $minimo respuestas mínimas para mostrar un grupo
     * @return array de ['nombre', 'area', 'areanombre', 'pct', 'n']
     */
    public static function por_dimension(array $respuestas, string $campo, int $minimo = 1): array {
        $grupos = [];
        foreach ($respuestas as $r) {
            if ($r->$campo === '' || $r->area === null) {
                continue;
            }
            $clave = $r->area . '|' . $r->$campo;
            $grupos[$clave] ??= ['nombre' => $r->$campo, 'area' => $r->area, 'suma' => 0, 'n' => 0];
            $grupos[$clave]['suma'] += $r->fraccion;
            $grupos[$clave]['n']++;
        }
        $lista = [];
        foreach ($grupos as $g) {
            if ($g['n'] >= $minimo) {
                $lista[] = [
                    'nombre' => $g['nombre'],
                    'area' => $g['area'],
                    'areanombre' => areas::nombre($g['area']),
                    'pct' => round($g['suma'] / $g['n'] * 100, 1),
                    'n' => $g['n'],
                ];
            }
        }
        usort($lista, fn($a, $b) => $a['pct'] <=> $b['pct']);
        return $lista;
    }

    /**
     * Preguntas con menor porcentaje de aciertos.
     *
     * @param array $respuestas
     * @param int $limite
     * @return array de ['id', 'pregunta', 'componente', 'competencia', 'pct', 'n']
     */
    public static function preguntas_dificiles(array $respuestas, int $limite = 5): array {
        $grupos = [];
        foreach ($respuestas as $r) {
            $grupos[$r->questionid] ??= ['id' => $r->qidnumber ?: '', 'pregunta' => $r->pregunta,
                'componente' => $r->componente, 'competencia' => $r->competencia, 'suma' => 0, 'n' => 0];
            $grupos[$r->questionid]['suma'] += $r->fraccion;
            $grupos[$r->questionid]['n']++;
        }
        foreach ($grupos as &$g) {
            $g['pct'] = round($g['suma'] / $g['n'] * 100, 1);
            // El nombre importado es "MAT-001 · enunciado": se muestra solo el enunciado.
            $g['pregunta'] = trim(preg_replace('/^[A-Z]{3}-\d+\s*·\s*/u', '', $g['pregunta']));
            unset($g['suma']);
        }
        unset($g);
        usort($grupos, fn($a, $b) => $a['pct'] <=> $b['pct']);
        return array_slice(array_values($grupos), 0, $limite);
    }

    /**
     * Puntaje 0-100 por área en un conjunto de respuestas (ponderado por los puntos de cada pregunta).
     *
     * @param array $respuestas
     * @return array código => 0..100
     */
    public static function puntaje_areas(array $respuestas): array {
        $suma = $total = [];
        foreach ($respuestas as $r) {
            if ($r->area === null) {
                continue;
            }
            $suma[$r->area] = ($suma[$r->area] ?? 0) + $r->fraccion * $r->maxmark;
            $total[$r->area] = ($total[$r->area] ?? 0) + $r->maxmark;
        }
        $puntajes = [];
        foreach (array_keys(areas::AREAS) as $codigo) {
            if (!empty($total[$codigo])) {
                $puntajes[$codigo] = (int) round($suma[$codigo] / $total[$codigo] * 100);
            }
        }
        return $puntajes;
    }

    // -------------------------------------------------------------------------------------------
    // Resúmenes compuestos
    // -------------------------------------------------------------------------------------------

    /**
     * Todo lo de un estudiante: cursos, evaluaciones, simulacros, fortalezas y debilidades.
     *
     * @param int $userid
     * @return array
     */
    public static function estudiante(int $userid): array {
        $ahora = time();
        $cursos = [];
        $simulacros = [];
        $quizids = [];
        foreach (enrol_get_all_users_courses($userid, true, 'id, fullname, shortname, idnumber, category') as $course) {
            $info = datos::curso($course);
            $evals = datos::evaluaciones($course->id);
            if (!$evals) {
                continue;
            }
            $notas = datos::notas(array_column($evals, 'id'), [$userid]);
            $info['evaluaciones'] = [];
            foreach ($evals as $e) {
                $nota = $notas[$e->id][$userid] ?? null;
                $abierta = (!$e->timeopen || $e->timeopen <= $ahora) && (!$e->timeclose || $e->timeclose > $ahora);
                $info['evaluaciones'][] = [
                    'id' => $e->id, 'nombre' => $e->nombre, 'url' => $e->url,
                    'nota' => $nota ? round($nota->grade, 1) : null,
                    'maximo' => $e->grade,
                    'pct' => $nota ? self::pct($nota->grade, $e->grade) : null,
                    'fecha' => $nota ? $nota->timemodified : null,
                    'abierta' => $abierta,
                    'futura' => $e->timeopen > $ahora,
                    'pendiente' => $abierta && !$nota,
                ];
                $quizids[] = $e->id;
            }
            $presentadas = array_values(array_filter($info['evaluaciones'], fn($e) => $e['nota'] !== null));
            $info['ultima'] = $presentadas ? end($presentadas) : null;
            if ($info['area'] === areas::SIMULACRO) {
                $simulacros = array_merge($simulacros, $info['evaluaciones']);
            }
            $cursos[] = $info;
        }

        $respuestas = datos::respuestas($quizids, [$userid]);
        // Puntaje por área de cada simulacro presentado.
        foreach ($simulacros as &$s) {
            if ($s['nota'] !== null) {
                $s['areas'] = self::puntaje_areas(array_filter($respuestas, fn($r) => (int) $r->quiz === $s['id']));
            }
        }
        unset($s);
        $simulacrospresentados = array_values(array_filter($simulacros, fn($s) => $s['nota'] !== null));

        $componentes = self::por_dimension($respuestas, 'componente', 3);
        $cuadernillos = [];
        foreach ($cursos as $c) {
            if ($c['area'] !== areas::SIMULACRO && $c['area'] !== null) {
                foreach ($c['evaluaciones'] as $e) {
                    $cuadernillos[] = $e;
                }
            }
        }
        $hechos = array_filter($cuadernillos, fn($e) => $e['pct'] !== null);
        $disponibles = array_filter($cuadernillos, fn($e) => !$e['futura']);

        return [
            'cursos' => $cursos,
            'simulacros' => $simulacros,
            'ultimosimulacro' => $simulacrospresentados ? end($simulacrospresentados) : null,
            'simulacroanterior' => count($simulacrospresentados) > 1 ? $simulacrospresentados[count($simulacrospresentados) - 2] : null,
            'promediocuadernillos' => $hechos ? round(array_sum(array_column($hechos, 'pct')) / count($hechos)) : null,
            'presentados' => count($hechos),
            'disponibles' => count($disponibles),
            'pendientes' => count(array_filter(array_merge($cuadernillos, $simulacros), fn($e) => $e['pendiente'])),
            'debilidades' => array_slice($componentes, 0, 3),
            'fortalezas' => array_slice(array_reverse($componentes), 0, 3),
        ];
    }

    /**
     * Resumen de un curso para el profesor: evaluaciones, notas por estudiante y alertas.
     *
     * @param \stdClass $course
     * @return array
     */
    public static function curso(\stdClass $course): array {
        $info = datos::curso($course);
        $evals = datos::evaluaciones($course->id);
        $estudiantes = datos::estudiantes($course->id);
        $notas = datos::notas(array_column($evals, 'id'), array_keys($estudiantes));
        $umbral = (int) (get_config('local_mediar', 'umbralalerta') ?: 50);

        $info['estudiantes'] = count($estudiantes);
        $info['evaluaciones'] = [];
        $ultima = null;
        foreach ($evals as $e) {
            $resumen = self::resumen($notas[$e->id] ?? [], $e->grade, count($estudiantes));
            $fila = ['quiz' => $e, 'resumen' => $resumen];
            $info['evaluaciones'][] = $fila;
            if ($resumen['evaluados'] > 0) {
                $ultima = $fila;
            }
        }
        $info['ultima'] = $ultima;
        // Alerta: el ÚLTIMO resultado de cada estudiante (no solo la última evaluación del curso,
        // que puede seguir abierta) está por debajo del umbral. userid => porcentaje.
        $info['alertas'] = [];
        foreach (array_keys($estudiantes) as $userid) {
            $ultimopct = null;
            foreach ($evals as $e) {
                if (isset($notas[$e->id][$userid])) {
                    $ultimopct = self::pct($notas[$e->id][$userid]->grade, $e->grade);
                }
            }
            if ($ultimopct !== null && $ultimopct < $umbral) {
                $info['alertas'][$userid] = $ultimopct;
            }
        }
        asort($info['alertas']);
        $info['notas'] = $notas;
        $info['usuarios'] = $estudiantes;
        $info['umbral'] = $umbral;
        return $info;
    }
}
