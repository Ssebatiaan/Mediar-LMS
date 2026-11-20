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
 * Ayudas para armar el contexto de las plantillas (barras, tarjetas, panel de próximas actividades).
 *
 * @package    local_mediar
 * @copyright  2026 Mediar
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class vista {
    /**
     * Número con formato colombiano (coma decimal), sin decimales innecesarios.
     *
     * @param float|null $n
     * @param int $decimales
     * @return string
     */
    public static function num(?float $n, int $decimales = 1): string {
        if ($n === null) {
            return '—';
        }
        $texto = number_format($n, $decimales, ',', '.');
        return str_contains($texto, ',') ? rtrim(rtrim($texto, '0'), ',') : $texto;
    }

    /**
     * Lista de barras horizontales (porcentaje de aciertos por componente o competencia).
     *
     * @param array $dimension de estadisticas::por_dimension()
     * @param bool $conarea mostrar el área (punto de color + nombre) junto a cada barra
     * @param string $unidad texto después del valor
     * @return array contexto de local_mediar/barras: ['barras' => [...]]
     */
    public static function barras(array $dimension, bool $conarea = true, string $unidad = '%'): array {
        $umbral = (int) (get_config('local_mediar', 'umbralalerta') ?: 50);
        return ['barras' => array_map(fn($d) => [
            'nombre' => $d['nombre'],
            'areanombre' => $conarea ? $d['areanombre'] : '',
            'conpunto' => $conarea,
            'color' => areas::color($d['area']),
            'pct' => self::num($d['pct'], 0),
            'unidad' => $unidad,
            'ancho' => max(2, (int) round($d['pct'])),
            'reforzar' => $d['pct'] < $umbral,
            'n' => $d['n'],
        ], $dimension)];
    }

    /**
     * Barras del puntaje promedio por área (0-100), con el punto de color del área.
     *
     * @param array $puntajes código => lista de puntajes 0..100
     * @return array contexto de local_mediar/barras
     */
    public static function barras_areas(array $puntajes): array {
        $dim = [];
        foreach (array_keys(areas::AREAS) as $codigo) {
            if (!empty($puntajes[$codigo])) {
                $dim[] = ['nombre' => areas::nombre($codigo), 'area' => $codigo, 'areanombre' => '',
                    'pct' => array_sum($puntajes[$codigo]) / count($puntajes[$codigo]), 'n' => count($puntajes[$codigo])];
            }
        }
        $ctx = self::barras($dim, true, '/ 100');
        foreach ($ctx['barras'] as &$b) {
            $b['areanombre'] = '';
        }
        unset($b);
        return $ctx;
    }

    /**
     * Indicadores del estudiante: último simulacro, promedio en cuadernillos, presentados y pendientes.
     *
     * @param array $d de estadisticas::estudiante()
     * @return array
     */
    public static function kpis_estudiante(array $d): array {
        $ultimo = $d['ultimosimulacro'];
        $anterior = $d['simulacroanterior'];
        $delta = ($ultimo && $anterior) ? (int) round($ultimo['nota'] - $anterior['nota']) : null;
        return [
            ['etiqueta' => get_string('ultimosimulacro', 'local_mediar'), 'valor' => $ultimo ? self::num($ultimo['nota'], 0) : '—',
                'unidad' => '/500', 'destacado' => true,
                'detalle' => $delta !== null ? get_string('frentealanterior', 'local_mediar', ($delta >= 0 ? '+' : '') . $delta) : '',
                'detallebueno' => $delta > 0, 'detallemalo' => $delta < 0],
            ['etiqueta' => get_string('promediocuadernillos', 'local_mediar'),
                'valor' => $d['promediocuadernillos'] ?? '—', 'unidad' => $d['promediocuadernillos'] !== null ? ' %' : ''],
            ['etiqueta' => get_string('presentados', 'local_mediar'), 'valor' => $d['presentados'], 'unidad' => '/' . $d['disponibles']],
            ['etiqueta' => get_string('pendientes', 'local_mediar'), 'valor' => $d['pendientes']],
        ];
    }

    /**
     * Panel de próximas actividades (contexto de local_mediar/proximas).
     *
     * @param int $userid
     * @param bool $todos vista de administración (todos los cursos)
     * @return array
     */
    public static function proximas(int $userid, bool $todos = false): array {
        $datos = proximas::de_usuario($userid, 5, $todos);
        return [
            'total' => $datos['total'],
            'hayitems' => !empty($datos['items']),
            'items' => $datos['items'],
            'urltodas' => (new \moodle_url('/calendar/view.php', ['view' => 'upcoming']))->out(false),
            'urlcalendario' => (new \moodle_url('/calendar/view.php', ['view' => 'month']))->out(false),
        ];
    }

    /**
     * Tarjeta de curso (contexto de local_mediar/tarjeta_curso).
     *
     * @param array $info de datos::curso()
     * @param array $extra campos adicionales (linea, pct, urlestadisticas…)
     * @return array
     */
    public static function tarjeta(array $info, array $extra = []): array {
        $meta = array_filter([$info['nrc'] ? get_string('nrc', 'local_mediar', $info['nrc']) : '', $info['colegio']]);
        return array_merge([
            'nombre' => $info['nombre'],
            'corto' => $info['corto'],
            'url' => $info['url'],
            'imagen' => $info['imagen'],
            'meta' => implode(' · ', $meta),
        ], $extra);
    }
}
