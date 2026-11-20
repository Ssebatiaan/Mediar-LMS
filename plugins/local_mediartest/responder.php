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
 * Responder el test página a página. Cada página se guarda al avanzar, así que se puede reanudar.
 *
 * @package    local_mediartest
 * @copyright  2026 Mediar
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_mediartest\instrumento;
use local_mediartest\intentos;

require(__DIR__ . '/../../config.php');

require_login();
$contexto = context_system::instance();
require_capability('local/mediartest:presentar', $contexto);

$instrumento = instrumento::activo();
$intento = $instrumento ? intentos::en_progreso($USER->id, $instrumento->registro->id) : null;
if (!$intento) {
    redirect(new moodle_url('/local/mediartest/index.php'));
}

$total = $instrumento->paginas();
$pagina = min($total, max(1, optional_param('pagina', 1, PARAM_INT)));
$url = new moodle_url('/local/mediartest/responder.php', ['pagina' => $pagina]);
$PAGE->set_url($url);
$PAGE->set_context($contexto);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_mediartest'));
$PAGE->set_heading($instrumento->def['nombre']);

$items = $instrumento->items_pagina($pagina);
$validos = array_column($instrumento->escala(), 'valor');
$faltantes = [];

if (data_submitted() && confirm_sesskey()) {
    // Solo se aceptan ítems de esta página y valores de la escala.
    $enviadas = optional_param_array('r', [], PARAM_INT);
    $guardar = [];
    foreach ($items as $it) {
        if (isset($enviadas[$it['codigo']]) && in_array($enviadas[$it['codigo']], $validos, true)) {
            $guardar[$it['codigo']] = $enviadas[$it['codigo']];
        }
    }
    intentos::guardar($intento, $guardar);

    if (optional_param('anterior', '', PARAM_RAW) !== '') {
        redirect(new moodle_url('/local/mediartest/responder.php', ['pagina' => max(1, $pagina - 1)]));
    }
    $respuestas = intentos::respuestas($intento->id);
    $faltantes = array_values(array_filter(array_column($items, 'codigo'), fn($c) => !isset($respuestas[$c])));
    if (!$faltantes) {
        if ($pagina < $total) {
            redirect(new moodle_url('/local/mediartest/responder.php', ['pagina' => $pagina + 1]));
        }
        // Última página: todo debe estar respondido antes de calcular.
        foreach (range(1, $total) as $p) {
            foreach ($instrumento->items_pagina($p) as $it) {
                if (!isset($respuestas[$it['codigo']])) {
                    redirect(new moodle_url('/local/mediartest/responder.php', ['pagina' => $p]),
                        get_string('faltanrespuestas', 'local_mediartest'), null, \core\output\notification::NOTIFY_WARNING);
                }
            }
        }
        intentos::finalizar($intento, $instrumento);
        redirect(new moodle_url('/local/mediartest/resultado.php'));
    }
}

$respuestas = intentos::respuestas($intento->id);
$escala = $instrumento->escala();
$numero = ($pagina - 1) * $instrumento->porpagina();
$datos = [
    'accion' => $url->out(false),
    'sesskey' => sesskey(),
    'pagina' => $pagina,
    'total' => $total,
    'avance' => (int) round(count($respuestas) / count($instrumento->items()) * 100),
    'hayfaltantes' => (bool) $faltantes,
    'primera' => $pagina === 1,
    'ultima' => $pagina === $total,
    'escala' => $escala,
    'items' => array_map(function($it) use (&$numero, $escala, $respuestas, $faltantes) {
        $numero++;
        return [
            'numero' => $numero,
            'codigo' => $it['codigo'],
            'texto' => $it['texto'],
            'falta' => in_array($it['codigo'], $faltantes, true),
            'opciones' => array_map(fn($o) => [
                'valor' => $o['valor'], 'texto' => $o['texto'], 'codigo' => $it['codigo'],
                'id' => 'r-' . $it['codigo'] . '-' . $o['valor'],
                'marcada' => ($respuestas[$it['codigo']] ?? null) === $o['valor'],
            ], $escala),
        ];
    }, $items),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_mediartest/responder', $datos);
echo $OUTPUT->footer();
