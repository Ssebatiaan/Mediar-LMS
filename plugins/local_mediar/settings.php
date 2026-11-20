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
 * Ajustes de Mediar (Administración del sitio > Extensiones > Extensiones locales > Mediar).
 *
 * @package    local_mediar
 * @copyright  2026 Mediar
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// La coordinación (rol Gestor) también puede cambiar estos ajustes: capacidad propia en vez de moodle/site:config.
if ($hassiteconfig || has_capability('local/mediar:configurar', context_system::instance())) {
    $settings = new admin_settingpage('local_mediar', get_string('pluginname', 'local_mediar'), 'local/mediar:configurar');
    $settings->add(new admin_setting_configtext(
        'local_mediar/fechaicfes',
        get_string('fechaicfes', 'local_mediar'),
        get_string('fechaicfes_desc', 'local_mediar'),
        '2027-03-07',
        '/^\d{4}-\d{2}-\d{2}$/'
    ));
    $settings->add(new admin_setting_configtext(
        'local_mediar/umbralalerta',
        get_string('umbralalerta', 'local_mediar'),
        get_string('umbralalerta_desc', 'local_mediar'),
        50,
        PARAM_INT
    ));
    $ADMIN->add('localplugins', $settings);
}
