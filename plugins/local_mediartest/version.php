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

/**
 * Mediar: motor de tests de personalidad configurables (independiente de las evaluaciones académicas).
 *
 * @package    local_mediartest
 * @copyright  2026 Mediar
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_mediartest';
$plugin->version   = 2026112000;
$plugin->requires  = 2026100500; // Moodle 5.3.
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.0';
// Usa local_mediar para mostrar el colegio (cohorte) de cada estudiante en la administración.
$plugin->dependencies = ['local_mediar' => 2026112000];
