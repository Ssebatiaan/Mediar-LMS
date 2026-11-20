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

use core\chart_bar;
use core\chart_line;
use core\chart_series;

/**
 * Gráficos con la API nativa de Moodle (core\chart, Chart.js): incluyen tooltip al pasar el mouse y
 * la opción "Mostrar datos del gráfico" (tabla accesible).
 *
 * Reglas: un solo eje; el color sigue al área (areas::color); una sola serie no lleva leyenda.
 *
 * @package    local_mediar
 * @copyright  2026 Mediar
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class graficos {
    /** @var string Naranja de marca para gráficos de una sola serie. */
    private const MARCA = '#FF5D1C';
    /** @var string Gris neutro para la referencia anterior en comparaciones. */
    private const NEUTRO = '#9a9a9a';

    /**
     * HTML de un gráfico.
     *
     * @param \core\chart_base $chart
     * @return string
     */
    private static function html(\core\chart_base $chart): string {
        global $OUTPUT;
        return $OUTPUT->render_chart($chart, true);
    }

    /**
     * Eje Y de porcentaje (0-100).
     *
     * @param \core\chart_base $chart
     * @param string $etiqueta
     */
    private static function eje_pct(\core\chart_base $chart, string $etiqueta): void {
        $y = $chart->get_yaxis(0, true);
        $y->set_min(0);
        $y->set_max(100);
        $y->set_stepsize(20);
        $y->set_label($etiqueta);
    }

    /**
     * Evolución del estudiante por área en sus cuadernillos (una línea por área).
     *
     * @param array $cursos de estadisticas::estudiante()['cursos']
     * @return string|null HTML o null si no hay datos suficientes
     */
    public static function evolucion_areas(array $cursos): ?string {
        $porarea = [];
        $etiquetas = [];
        foreach ($cursos as $c) {
            if (!isset(areas::AREAS[$c['area']])) {
                continue;
            }
            foreach ($c['evaluaciones'] as $i => $e) {
                $etiquetas[$i] ??= $e['nombre'];
                $porarea[$c['area']][$i] = $e['pct'];
            }
        }
        // Se quitan las evaluaciones del final que nadie ha presentado aún (columna vacía).
        ksort($etiquetas);
        while ($etiquetas && !array_filter(array_column($porarea, array_key_last($etiquetas)), fn($v) => $v !== null)) {
            array_pop($etiquetas);
        }
        if (!$porarea || count($etiquetas) < 2) {
            return null;
        }
        $chart = new chart_line();
        $chart->set_labels(array_map(fn($e) => trim(strtok($e, '·')), array_values($etiquetas)));
        foreach (array_keys(areas::AREAS) as $codigo) {
            if (isset($porarea[$codigo])) {
                $valores = array_map(fn($i) => $porarea[$codigo][$i] ?? null, array_keys($etiquetas));
                $serie = new chart_series(areas::nombre($codigo), $valores);
                $serie->set_color(areas::color($codigo));
                $chart->add_series($serie);
            }
        }
        self::eje_pct($chart, get_string('pctaciertos', 'local_mediar'));
        return self::html($chart);
    }

    /**
     * Puntaje por área en los dos últimos simulacros (barras agrupadas: anterior en gris, último en naranja).
     *
     * @param array $simulacros presentados, cada uno con 'nombre' y 'areas'
     * @return string|null
     */
    public static function simulacros_por_area(array $simulacros): ?string {
        $simulacros = array_slice(array_values(array_filter($simulacros, fn($s) => !empty($s['areas']))), -2);
        if (!$simulacros) {
            return null;
        }
        $chart = new chart_bar();
        $chart->set_labels(array_values(array_map(fn($a) => $a['nombre'], areas::AREAS)));
        foreach ($simulacros as $i => $s) {
            $valores = array_map(fn($codigo) => $s['areas'][$codigo] ?? null, array_keys(areas::AREAS));
            $serie = new chart_series(trim(strtok($s['nombre'], '·')), array_values($valores));
            $serie->set_color($i === count($simulacros) - 1 ? self::MARCA : self::NEUTRO);
            $chart->add_series($serie);
        }
        self::eje_pct($chart, get_string('puntajearea', 'local_mediar'));
        return self::html($chart);
    }

    /**
     * Promedio del grupo en cada evaluación del curso (una sola serie).
     *
     * @param array $evaluaciones de estadisticas::curso()['evaluaciones']
     * @return string|null
     */
    public static function evolucion_curso(array $evaluaciones): ?string {
        $con = array_values(array_filter($evaluaciones, fn($e) => $e['resumen']['evaluados'] > 0));
        if (count($con) < 2) {
            return null;
        }
        $chart = new chart_line();
        $chart->set_labels(array_map(fn($e) => trim(strtok($e['quiz']->nombre, '·')), $con));
        $serie = new chart_series(get_string('promediogrupo', 'local_mediar'), array_map(fn($e) => $e['resumen']['promediopct'], $con));
        $serie->set_color(self::MARCA);
        $chart->add_series($serie);
        $chart->set_legend_options(['display' => false]);
        self::eje_pct($chart, get_string('pctaciertos', 'local_mediar'));
        return self::html($chart);
    }

    /**
     * Distribución de los resultados de una evaluación (cantidad de estudiantes por rango).
     *
     * @param array $distribucion etiqueta => cantidad
     * @return string
     */
    public static function distribucion(array $distribucion): string {
        $chart = new chart_bar();
        $chart->set_labels(array_keys($distribucion));
        $serie = new chart_series(get_string('estudiantes', 'local_mediar'), array_values($distribucion));
        $serie->set_color(self::MARCA);
        $chart->add_series($serie);
        $chart->set_legend_options(['display' => false]);
        $y = $chart->get_yaxis(0, true);
        $y->set_min(0);
        $y->set_stepsize(1);
        $y->set_label(get_string('estudiantes', 'local_mediar'));
        return self::html($chart);
    }

    /**
     * Comparación de los cursos de un profesor (un curso por serie), por evaluación.
     *
     * @param array $cursos de estadisticas::curso(), solo cursos de asignatura
     * @return string|null
     */
    public static function comparacion_cursos(array $cursos): ?string {
        $cursos = array_values(array_filter($cursos, fn($c) => count(array_filter($c['evaluaciones'],
            fn($e) => $e['resumen']['evaluados'] > 0)) >= 2));
        if (count($cursos) < 1) {
            return null;
        }
        $etiquetas = array_map(fn($e) => trim(strtok($e['quiz']->nombre, '·')), $cursos[0]['evaluaciones']);
        $chart = new chart_line();
        $chart->set_labels($etiquetas);
        $colores = array_keys(areas::AREAS); // orden categórico validado
        foreach ($cursos as $i => $c) {
            $valores = array_map(fn($e) => $e['resumen']['promediopct'], $c['evaluaciones']);
            $serie = new chart_series($c['colegio'] . ' (NRC ' . $c['nrc'] . ')', array_slice($valores, 0, count($etiquetas)));
            $serie->set_color(areas::color($colores[$i % count($colores)]));
            $chart->add_series($serie);
        }
        self::eje_pct($chart, get_string('pctaciertos', 'local_mediar'));
        return self::html($chart);
    }
}
