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
 * Áreas Saber 11: nombre, peso ICFES, valor del campo de curso "area" y color de gráfico.
 *
 * Los colores son una paleta categórica validada (daltonismo, contraste) para modo claro y oscuro;
 * el orden es el de la prueba y debe mantenerse (el color sigue al área, nunca a su posición).
 *
 * @package    local_mediar
 * @copyright  2026 Mediar
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class areas {
    /** @var string Código de los cursos de simulacros completos. */
    public const SIMULACRO = 'SIM';

    /** @var array Áreas en el orden de la prueba. */
    public const AREAS = [
        'LEC' => ['nombre' => 'Lectura Crítica', 'peso' => 3, 'campo' => 2, 'claro' => '#2a78d6', 'oscuro' => '#3987e5'],
        'MAT' => ['nombre' => 'Matemáticas', 'peso' => 3, 'campo' => 1, 'claro' => '#FF5D1C', 'oscuro' => '#E04A0C'],
        'SOC' => ['nombre' => 'Sociales y Ciudadanas', 'peso' => 3, 'campo' => 3, 'claro' => '#1baf7a', 'oscuro' => '#199e70'],
        'NAT' => ['nombre' => 'Ciencias Naturales', 'peso' => 3, 'campo' => 4, 'claro' => '#6250d6', 'oscuro' => '#9085e9'],
        'ING' => ['nombre' => 'Inglés', 'peso' => 1, 'campo' => 5, 'claro' => '#e87ba4', 'oscuro' => '#d55181'],
    ];

    /**
     * Código de área a partir del valor del campo de curso "area" (1..6).
     *
     * @param int|null $valor
     * @return string|null MAT, LEC... o SIM; null si el curso no tiene área.
     */
    public static function por_campo(?int $valor): ?string {
        if ($valor === 6) {
            return self::SIMULACRO;
        }
        foreach (self::AREAS as $codigo => $a) {
            if ($a['campo'] === $valor) {
                return $codigo;
            }
        }
        return null;
    }

    /**
     * Nombre visible del área.
     *
     * @param string|null $codigo
     * @return string
     */
    public static function nombre(?string $codigo): string {
        if ($codigo === self::SIMULACRO) {
            return 'Simulacros ICFES';
        }
        return self::AREAS[$codigo]['nombre'] ?? '';
    }

    /**
     * Color del área según el modo de color del usuario (claro u oscuro).
     *
     * @param string $codigo
     * @return string hexadecimal
     */
    public static function color(string $codigo): string {
        $modo = get_user_preferences('theme_boost_colourmode', get_config('theme_boost', 'defaultcolourmode') ?: 'light');
        return self::AREAS[$codigo][$modo === 'dark' ? 'oscuro' : 'claro'] ?? '#777777';
    }

    /**
     * Puntaje global ICFES (0-500) a partir del puntaje de cada área (0-100).
     *
     * ((LC×3 + MAT×3 + SOC×3 + NAT×3 + ING×1) / 13) × 5. Si falta un área, se pondera con las presentes.
     *
     * @param array $puntajes código => 0..100
     * @return int|null
     */
    public static function puntaje_global(array $puntajes): ?int {
        $suma = 0;
        $pesos = 0;
        foreach (self::AREAS as $codigo => $a) {
            if (isset($puntajes[$codigo])) {
                $suma += $puntajes[$codigo] * $a['peso'];
                $pesos += $a['peso'];
            }
        }
        return $pesos ? (int) round($suma / $pesos * 5) : null;
    }
}
