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
 * Panel de inicio Mediar: un solo bloque en el Área personal que cambia según el rol
 * (estudiante, profesor o administración). Los datos los calcula local_mediar.
 *
 * @package    block_mediar_dashboard
 * @copyright  2026 Mediar
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_mediar_dashboard extends block_base {
    /**
     * Inicialización.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_mediar_dashboard');
    }

    /**
     * Solo en el Área personal.
     *
     * @return array
     */
    public function applicable_formats() {
        return ['my' => true, 'all' => false];
    }

    /**
     * Una sola instancia.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Sin encabezado: el panel tiene su propio saludo.
     *
     * @return bool
     */
    public function hide_header() {
        return true;
    }

    /**
     * Contenido del panel.
     *
     * @return stdClass
     */
    public function get_content() {
        global $OUTPUT, $USER;
        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = (object) ['text' => '', 'footer' => ''];
        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }
        $contexto = \block_mediar_dashboard\panel::contexto($USER);
        $this->content->text = $OUTPUT->render_from_template('block_mediar_dashboard/panel', $contexto);
        return $this->content;
    }
}
