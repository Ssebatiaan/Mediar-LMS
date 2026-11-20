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

use moodle_exception;
use stdClass;

/**
 * Un instrumento de personalidad: definición JSON versionada (dimensiones bipolares, ítems,
 * escala de respuesta y reglas de interpretación). El motor no sabe nada del MBTI: cualquier
 * instrumento con esta estructura funciona igual.
 *
 * Las versiones son inmutables: un intento queda ligado a la versión que respondió.
 *
 * @package    local_mediartest
 * @copyright  2026 Mediar
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class instrumento {
    /** @var stdClass Registro de local_mediartest_instr. */
    public stdClass $registro;
    /** @var array Definición decodificada. */
    public array $def;

    /**
     * Constructor.
     *
     * @param stdClass $registro
     */
    public function __construct(stdClass $registro) {
        $this->registro = $registro;
        $this->def = json_decode($registro->definicion, true, 64, JSON_THROW_ON_ERROR);
    }

    /**
     * Instrumento activo (el que presentan los estudiantes).
     *
     * @return self|null
     */
    public static function activo(): ?self {
        global $DB;
        $r = $DB->get_record('local_mediartest_instr', ['activo' => 1], '*', IGNORE_MULTIPLE);
        return $r ? new self($r) : null;
    }

    /**
     * Instrumento por id.
     *
     * @param int $id
     * @return self
     */
    public static function por_id(int $id): self {
        global $DB;
        return new self($DB->get_record('local_mediartest_instr', ['id' => $id], '*', MUST_EXIST));
    }

    /**
     * Revisa la estructura de una definición.
     *
     * @param mixed $def
     * @return string[] errores (vacío = válida)
     */
    public static function validar($def): array {
        $e = [];
        if (!is_array($def)) {
            return ['El archivo no es un JSON válido.'];
        }
        foreach (['codigo', 'version', 'nombre', 'escala', 'dimensiones', 'items'] as $campo) {
            if (empty($def[$campo])) {
                $e[] = "Falta el campo «{$campo}».";
            }
        }
        if ($e) {
            return $e;
        }
        if (!preg_match('/^[a-z0-9-]{2,100}$/', $def['codigo'])) {
            $e[] = 'El código solo puede tener minúsculas, números y guiones.';
        }
        $valores = array_column($def['escala'], 'valor');
        if (count($valores) < 2 || count($valores) !== count(array_filter($valores, 'is_int'))) {
            $e[] = 'La escala necesita al menos dos opciones con «valor» entero y «texto».';
        }
        $polos = [];
        foreach ($def['dimensiones'] as $i => $d) {
            if (empty($d['codigo']) || empty($d['nombre']) || count($d['polos'] ?? []) !== 2) {
                $e[] = 'La dimensión ' . ($i + 1) . ' necesita «codigo», «nombre» y exactamente dos «polos».';
                continue;
            }
            foreach ($d['polos'] as $p) {
                if (empty($p['codigo']) || empty($p['nombre'])) {
                    $e[] = "Los polos de la dimensión {$d['codigo']} necesitan «codigo» y «nombre».";
                }
            }
            $polos[$d['codigo']] = array_column($d['polos'], 'codigo');
        }
        $codigos = [];
        $porDimension = array_fill_keys(array_keys($polos), 0);
        foreach ($def['items'] as $i => $it) {
            $n = $it['codigo'] ?? ('#' . ($i + 1));
            if (empty($it['codigo']) || empty($it['texto'])) {
                $e[] = "El ítem $n necesita «codigo» y «texto».";
            }
            if (isset($codigos[$it['codigo'] ?? ''])) {
                $e[] = "El código de ítem $n está repetido.";
            }
            $codigos[$it['codigo'] ?? ''] = true;
            if (!isset($polos[$it['dimension'] ?? ''])) {
                $e[] = "El ítem $n apunta a una dimensión que no existe.";
            } else if (!in_array($it['polo'] ?? '', $polos[$it['dimension']], true)) {
                $e[] = "El ítem $n apunta a un polo que no es de su dimensión.";
            } else {
                $porDimension[$it['dimension']]++;
            }
        }
        foreach ($porDimension as $d => $n) {
            if ($n === 0) {
                $e[] = "La dimensión $d no tiene ítems.";
            }
        }
        $umbral = $def['umbralequilibrio'] ?? 20;
        if (!is_numeric($umbral) || $umbral < 0 || $umbral >= 100) {
            $e[] = '«umbralequilibrio» debe ser un número entre 0 y 99.';
        }
        return $e;
    }

    /**
     * Carga una definición nueva (código + versión que no exista).
     *
     * @param string $json
     * @param bool $activar dejarla como instrumento activo
     * @return self
     */
    public static function importar(string $json, bool $activar = false): self {
        global $DB;
        $def = json_decode($json, true);
        if ($errores = self::validar($def)) {
            throw new moodle_exception('instrumentoinvalido', 'local_mediartest', '', implode(' ', $errores));
        }
        if ($DB->record_exists('local_mediartest_instr', ['codigo' => $def['codigo'], 'version' => $def['version']])) {
            throw new moodle_exception('versionexiste', 'local_mediartest', '', "{$def['codigo']} {$def['version']}");
        }
        $ahora = time();
        $id = $DB->insert_record('local_mediartest_instr', (object) [
            'codigo' => $def['codigo'], 'version' => $def['version'], 'nombre' => $def['nombre'],
            'definicion' => json_encode($def, JSON_UNESCAPED_UNICODE), 'activo' => 0,
            'timecreated' => $ahora, 'timemodified' => $ahora,
        ]);
        if ($activar) {
            self::activar($id);
        }
        return self::por_id($id);
    }

    /**
     * Deja un instrumento como el único activo.
     *
     * @param int $id
     */
    public static function activar(int $id): void {
        global $DB;
        $transaccion = $DB->start_delegated_transaction();
        $DB->set_field('local_mediartest_instr', 'activo', 0, []);
        $DB->set_field('local_mediartest_instr', 'activo', 1, ['id' => $id]);
        $DB->set_field('local_mediartest_instr', 'timemodified', time(), ['id' => $id]);
        $transaccion->allow_commit();
    }

    /**
     * Ítems en orden de presentación.
     *
     * @return array
     */
    public function items(): array {
        return $this->def['items'];
    }

    /**
     * Ítems por página.
     *
     * @return int
     */
    public function porpagina(): int {
        return max(1, min(50, (int) ($this->def['porpagina'] ?? 8)));
    }

    /**
     * Número de páginas.
     *
     * @return int
     */
    public function paginas(): int {
        return (int) ceil(count($this->items()) / $this->porpagina());
    }

    /**
     * Ítems de una página (1..paginas).
     *
     * @param int $pagina
     * @return array
     */
    public function items_pagina(int $pagina): array {
        return array_slice($this->items(), ($pagina - 1) * $this->porpagina(), $this->porpagina());
    }

    /**
     * Escala de respuesta.
     *
     * @return array de ['valor' => int, 'texto' => string]
     */
    public function escala(): array {
        return $this->def['escala'];
    }

    /**
     * Umbral (0-100) por debajo del cual una dimensión se considera equilibrada.
     *
     * @return float
     */
    public function umbral(): float {
        return (float) ($this->def['umbralequilibrio'] ?? 20);
    }
}
