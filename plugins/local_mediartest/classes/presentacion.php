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

namespace local_mediartest;

/**
 * Convierte un resultado en el contexto de la plantilla de resultado, con lenguaje de PREFERENCIAS
 * («sueles preferir…»), nunca de identidad ni de capacidad.
 *
 * @package    local_mediartest
 * @copyright  2026 Mediar
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class presentacion {
    /**
     * Contexto de local_mediartest/resultado.
     *
     * @param instrumento $instrumento
     * @param array $resultado de motor::calcular()
     * @return array
     */
    public static function resultado(instrumento $instrumento, array $resultado): array {
        $porcodigo = array_column($resultado['dimensiones'], null, 'codigo');
        $dimensiones = [];
        $letras = [];
        foreach ($instrumento->def['dimensiones'] as $dim) {
            $r = $porcodigo[$dim['codigo']];
            [$a, $b] = $dim['polos'];
            $polo = $r['polo'] === $a['codigo'] ? $a : ($r['polo'] === $b['codigo'] ? $b : null);
            $dimensiones[] = [
                'nombre' => $dim['nombre'],
                'pregunta' => $dim['pregunta'] ?? '',
                'poloa' => $a['nombre'] . ' (' . $a['codigo'] . ')',
                'polob' => $b['nombre'] . ' (' . $b['codigo'] . ')',
                // Marcador sobre la barra: 0 % = todo hacia el polo A (izquierda), 100 % = polo B (derecha).
                'posicion' => (int) round(50 - $r['puntaje'] / 2),
                'haciaa' => $r['puntaje'] > 0,
                'equilibrada' => $r['equilibrada'],
                'titulo' => $r['equilibrada']
                    ? get_string('equilibrada', 'local_mediartest')
                    : get_string('preferencia_' . $r['intensidad'], 'local_mediartest', $polo['nombre']),
                'fuerza' => $r['fuerza'],
                'descripcion' => $r['equilibrada'] ? ($dim['equilibrio'] ?? '') : ($polo['descripcion'] ?? ''),
                'consejo' => $r['equilibrada'] ? '' : ($polo['consejo'] ?? ''),
            ];
            $letras[] = ['letra' => $polo['codigo'] ?? ($a['codigo'] . '/' . $b['codigo']), 'equilibrada' => $r['equilibrada'],
                'nombre' => $polo['nombre'] ?? get_string('equilibrada', 'local_mediartest')];
        }
        $umbral = $instrumento->umbral();
        return [
            // Banda de equilibrio sobre la barra (umbral/2 a cada lado del centro).
            'zonaizq' => 50 - $umbral / 2,
            'zonaancho' => $umbral,
            'instrumento' => $instrumento->def['nombre'],
            'subtitulo' => $instrumento->def['subtitulo'] ?? '',
            'version' => $instrumento->registro->version,
            'aviso' => $instrumento->def['aviso'] ?? '',
            'letras' => $letras,
            'dimensiones' => $dimensiones,
        ];
    }
}
