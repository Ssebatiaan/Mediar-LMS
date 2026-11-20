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
 * English strings for the personality test.
 *
 * @package    local_mediartest
 * @copyright  2026 Mediar
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Personality test';
$string['mediartest:presentar'] = 'Take the personality test';
$string['mediartest:verresultados'] = 'View all students\' personality test results';
$string['mediartest:gestionar'] = 'Upload and activate personality instruments';
$string['privacy:metadata:intento'] = 'Personality test attempts and their result.';
$string['privacy:metadata:intento:userid'] = 'Student who took the test.';
$string['privacy:metadata:intento:estado'] = 'Whether the attempt is in progress or finished.';
$string['privacy:metadata:intento:resultado'] = 'Score per dimension and preference profile obtained.';
$string['privacy:metadata:intento:timecreated'] = 'Start date.';
$string['privacy:metadata:intento:timefinished'] = 'Finish date.';
$string['privacy:metadata:resp'] = 'Answers to each statement.';
$string['privacy:metadata:resp:item'] = 'Statement answered.';
$string['privacy:metadata:resp:valor'] = 'Option chosen on the scale.';
$string['privacy:metadata:resp:timemodified'] = 'Answer date.';
$string['sininstrumento'] = 'The personality test is not available yet.';
$string['afirmaciones'] = 'statements';
$string['minutos'] = 'minutes';
$string['paginas'] = 'pages';
$string['comenzar'] = 'Start the test';
$string['continuar'] = 'Continue ({$a} % answered)';
$string['verresultado'] = 'See my result';
$string['repetir'] = 'Take it again';
$string['presentadoel'] = 'You took it on {$a}. If you repeat it, the latest result is kept.';
$string['comofunciona'] = 'How it works';
$string['queexplora'] = 'What it explores';
$string['importante'] = 'Important';
$string['progreso'] = 'Progress: {$a} %';
$string['paginadek'] = 'Page {$a->pagina} of {$a->total}';
$string['respondetodas'] = 'Answer every statement on this page to continue.';
$string['faltanrespuestas'] = 'Some statements are still unanswered.';
$string['anterior'] = 'Previous';
$string['siguiente'] = 'Save and continue';
$string['finalizar'] = 'Finish and see my result';
$string['seguarda'] = 'Your answers are saved on every page: you can leave and continue later.';
$string['miresultado'] = 'My test result';
$string['resultadode'] = 'Result of {$a}';
$string['sinresultado'] = 'This student has not finished the test yet.';
$string['tuperfil'] = 'Your preference profile';
$string['perfilpreferencias'] = 'Preference profile';
$string['perfil'] = 'Profile';
$string['perfilexplicacion'] = 'The letters summarise where your preferences lean in each dimension. They do not define you or measure what you can do: they are a hint to study in the way that works best for you.';
$string['equilibrada'] = 'Balanced';
$string['preferencia_clara'] = 'Clear preference for {$a}';
$string['preferencia_moderada'] = 'Moderate preference for {$a}';
$string['consejo'] = 'For your preparation:';
$string['volverinicio'] = 'Back to dashboard';
$string['volveradmin'] = 'Back to results';
$string['finalizado'] = 'Finished';
$string['enprogreso'] = 'In progress';
$string['intento'] = 'Attempt';
$string['administracion'] = 'Personality test: results';
$string['instrumentoactivo'] = 'Active instrument';
$string['privacidadadmin'] = 'Only administration can see these results; teachers have no access.';
$string['estudiantes'] = 'Students';
$string['estudiante'] = 'Student';
$string['finalizados'] = 'Finished';
$string['pctparticipacion'] = '{$a} % of students';
$string['sinpresentar'] = 'Not taken';
$string['distribucion'] = 'Distribution by dimension';
$string['distribucion_ayuda'] = 'How many students lean towards each pole and how many are balanced.';
$string['leyenda_a'] = 'First pole';
$string['leyenda_b'] = 'Second pole';
$string['resultadosestudiantes'] = 'Student results';
$string['exportarexcel'] = 'Export to Excel';
$string['colegio'] = 'School';
$string['estado'] = 'Status';
$string['fecha'] = 'Date';
$string['instrumentos'] = 'Instruments';
$string['instrumentos_ayuda'] = 'The engine does not depend on MBTI: any instrument with bipolar dimensions, statements and a scale can be uploaded as a JSON file. Versions are immutable: each attempt stays linked to the version answered.';
$string['instrumento'] = 'Instrument';
$string['version'] = 'Version';
$string['dimensiones'] = 'Dimensions';
$string['activo'] = 'Active';
$string['activar'] = 'Activate';
$string['activado'] = 'Instrument activated. New attempts will use it.';
$string['cargarnuevo'] = 'Upload a new instrument';
$string['archivojson'] = 'Instrument file (JSON)';
$string['archivojson_help'] = 'JSON file with: codigo, version, nombre, escala (valor and texto), dimensiones (codigo, nombre and two polos) and items (codigo, dimension, polo and texto). Optional: instrucciones, aviso, porpagina and umbralequilibrio.';
$string['activaralsubir'] = 'Activate on upload';
$string['subirinstrumento'] = 'Upload instrument';
$string['instrumentocargado'] = 'Instrument "{$a}" uploaded.';
$string['instrumentoinvalido'] = 'Invalid instrument: {$a}';
$string['versionexiste'] = 'Version {$a} already exists. Change the "version" field in the file.';
