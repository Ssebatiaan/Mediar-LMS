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
 * Calcula el resultado de un intento a partir de sus respuestas y las reglas del instrumento.
 *
 * Cada dimensión es bipolar (polo A / polo B). Cada respuesta se centra en la escala (en una escala
 * 1-5, el 3 vale 0) y suma hacia el polo del ítem. El puntaje de la dimensión va de -100 (todo
 * hacia B) a +100 (todo hacia A). Si su valor absoluto no llega al umbral del instrumento, la
 * dimensión es EQUILIBRADA: no se asigna ningún polo (con pocos ítems, una diferencia pequeña no
 * es una preferencia confiable y no debe convertirse en etiqueta).
 *
 * @package    local_mediartest
 * @copyright  2026 Mediar
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class motor {
    /**
     * Resultado de un conjunto completo de respuestas.
     *
     * @param instrumento $instrumento
     * @param array $respuestas código de ítem => valor
     * @return array ['perfil' => string, 'dimensiones' => [...]]
     */
    public static function calcular(instrumento $instrumento, array $respuestas): array {
        $valores = array_column($instrumento->escala(), 'valor');
        $centro = (min($valores) + max($valores)) / 2;
        $medio = (max($valores) - min($valores)) / 2;

        $suma = $maximo = [];
        foreach ($instrumento->items() as $item) {
            if (!array_key_exists($item['codigo'], $respuestas)) {
                throw new \coding_exception("Falta la respuesta del ítem {$item['codigo']}");
            }
            $d = $item['dimension'];
            $polos = self::polos($instrumento, $d);
            $signo = $item['polo'] === $polos[0]['codigo'] ? 1 : -1;
            $suma[$d] = ($suma[$d] ?? 0) + $signo * ($respuestas[$item['codigo']] - $centro);
            $maximo[$d] = ($maximo[$d] ?? 0) + $medio;
        }

        $dimensiones = [];
        $letras = [];
        foreach ($instrumento->def['dimensiones'] as $dim) {
            $c = $dim['codigo'];
            $pct = $maximo[$c] ? round($suma[$c] / $maximo[$c] * 100) : 0;
            $equilibrada = abs($pct) < $instrumento->umbral();
            $polo = $equilibrada ? null : ($pct > 0 ? $dim['polos'][0]['codigo'] : $dim['polos'][1]['codigo']);
            $dimensiones[] = [
                'codigo' => $c,
                'puntaje' => (int) $pct,
                'fuerza' => (int) abs($pct),
                'polo' => $polo,
                'equilibrada' => $equilibrada,
                'intensidad' => $equilibrada ? 'equilibrada' : (abs($pct) >= 50 ? 'clara' : 'moderada'),
            ];
            $letras[] = $polo ?? ($dim['polos'][0]['codigo'] . '/' . $dim['polos'][1]['codigo']);
        }
        return ['perfil' => implode(' ', $letras), 'dimensiones' => $dimensiones];
    }

    /**
     * Polos de una dimensión.
     *
     * @param instrumento $instrumento
     * @param string $codigo
     * @return array
     */
    private static function polos(instrumento $instrumento, string $codigo): array {
        foreach ($instrumento->def['dimensiones'] as $d) {
            if ($d['codigo'] === $codigo) {
                return $d['polos'];
            }
        }
        throw new \coding_exception("Dimensión desconocida: $codigo");
    }
}
