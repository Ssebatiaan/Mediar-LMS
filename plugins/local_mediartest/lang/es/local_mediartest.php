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
 * Cadenas en español del test de personalidad.
 *
 * @package    local_mediartest
 * @copyright  2026 Mediar
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Test de personalidad';
$string['mediartest:presentar'] = 'Presentar el test de personalidad';
$string['mediartest:verresultados'] = 'Ver los resultados del test de personalidad de todos los estudiantes';
$string['mediartest:gestionar'] = 'Cargar y activar instrumentos de personalidad';

$string['privacy:metadata:intento'] = 'Intentos del test de personalidad y su resultado.';
$string['privacy:metadata:intento:userid'] = 'Estudiante que presentó el test.';
$string['privacy:metadata:intento:estado'] = 'Si el intento está en progreso o finalizado.';
$string['privacy:metadata:intento:resultado'] = 'Puntaje por dimensión y perfil de preferencias obtenido.';
$string['privacy:metadata:intento:timecreated'] = 'Fecha de inicio.';
$string['privacy:metadata:intento:timefinished'] = 'Fecha de finalización.';
$string['privacy:metadata:resp'] = 'Respuestas a cada afirmación del test.';
$string['privacy:metadata:resp:item'] = 'Afirmación respondida.';
$string['privacy:metadata:resp:valor'] = 'Opción elegida en la escala.';
$string['privacy:metadata:resp:timemodified'] = 'Fecha de la respuesta.';

// Presentación.
$string['sininstrumento'] = 'El test de personalidad todavía no está disponible.';
$string['afirmaciones'] = 'afirmaciones';
$string['minutos'] = 'minutos';
$string['paginas'] = 'páginas';
$string['comenzar'] = 'Comenzar el test';
$string['continuar'] = 'Continuar ({$a} % respondido)';
$string['verresultado'] = 'Ver mi resultado';
$string['repetir'] = 'Volver a presentarlo';
$string['presentadoel'] = 'Lo presentaste el {$a}. Si lo repites, se guarda el resultado más reciente.';
$string['comofunciona'] = 'Cómo funciona';
$string['queexplora'] = 'Qué explora';
$string['importante'] = 'Importante';

// Responder.
$string['progreso'] = 'Progreso: {$a} %';
$string['paginadek'] = 'Página {$a->pagina} de {$a->total}';
$string['respondetodas'] = 'Responde todas las afirmaciones de esta página para continuar.';
$string['faltanrespuestas'] = 'Te faltan algunas afirmaciones por responder.';
$string['anterior'] = 'Anterior';
$string['siguiente'] = 'Guardar y continuar';
$string['finalizar'] = 'Finalizar y ver mi resultado';
$string['seguarda'] = 'Tus respuestas se guardan en cada página: puedes salir y continuar después.';

// Resultado.
$string['miresultado'] = 'Mi resultado del test';
$string['resultadode'] = 'Resultado de {$a}';
$string['sinresultado'] = 'Este estudiante todavía no ha finalizado el test.';
$string['tuperfil'] = 'Tu perfil de preferencias';
$string['perfilpreferencias'] = 'Perfil de preferencias';
$string['perfil'] = 'Perfil';
$string['perfilexplicacion'] = 'Las letras resumen hacia dónde se inclinan tus preferencias en cada dimensión. No te definen ni miden lo que eres capaz de hacer: son una pista para estudiar de la forma que mejor te funciona.';
$string['equilibrada'] = 'Equilibrado';
$string['preferencia_clara'] = 'Preferencia clara por {$a}';
$string['preferencia_moderada'] = 'Preferencia moderada por {$a}';
$string['consejo'] = 'Para tu preparación:';
$string['volverinicio'] = 'Volver al inicio';
$string['volveradmin'] = 'Volver a los resultados';
$string['finalizado'] = 'Finalizado';
$string['enprogreso'] = 'En progreso';
$string['intento'] = 'Intento';

// Administración.
$string['administracion'] = 'Test de personalidad: resultados';
$string['instrumentoactivo'] = 'Instrumento activo';
$string['privacidadadmin'] = 'Solo la administración ve estos resultados; los profesores no tienen acceso.';
$string['estudiantes'] = 'Estudiantes';
$string['estudiante'] = 'Estudiante';
$string['finalizados'] = 'Finalizados';
$string['pctparticipacion'] = '{$a} % de los estudiantes';
$string['sinpresentar'] = 'Sin presentar';
$string['distribucion'] = 'Distribución por dimensión';
$string['distribucion_ayuda'] = 'Cuántos estudiantes se inclinan hacia cada polo y cuántos quedan equilibrados.';
$string['leyenda_a'] = 'Primer polo';
$string['leyenda_b'] = 'Segundo polo';
$string['resultadosestudiantes'] = 'Resultados de los estudiantes';
$string['exportarexcel'] = 'Exportar a Excel';
$string['colegio'] = 'Colegio';
$string['estado'] = 'Estado';
$string['fecha'] = 'Fecha';
$string['instrumentos'] = 'Instrumentos';
$string['instrumentos_ayuda'] = 'El motor es independiente del MBTI: cualquier instrumento con dimensiones bipolares, afirmaciones y escala se carga como un archivo JSON (ver contenido/tests/ en el proyecto). Las versiones no se modifican: cada intento queda ligado a la versión que se respondió.';
$string['instrumento'] = 'Instrumento';
$string['version'] = 'Versión';
$string['dimensiones'] = 'Dimensiones';
$string['activo'] = 'Activo';
$string['activar'] = 'Activar';
$string['activado'] = 'Instrumento activado. Los nuevos intentos usarán este instrumento.';
$string['cargarnuevo'] = 'Cargar un instrumento nuevo';
$string['archivojson'] = 'Archivo del instrumento (JSON)';
$string['archivojson_help'] = 'Archivo JSON con: codigo, version, nombre, escala (valor y texto), dimensiones (codigo, nombre y dos polos) e items (codigo, dimension, polo y texto). Opcionales: instrucciones, aviso, porpagina y umbralequilibrio.';
$string['activaralsubir'] = 'Activarlo al cargarlo';
$string['subirinstrumento'] = 'Cargar instrumento';
$string['instrumentocargado'] = 'Se cargó el instrumento «{$a}».';
$string['instrumentoinvalido'] = 'El instrumento no es válido: {$a}';
$string['versionexiste'] = 'Ya existe la versión {$a}. Cambia el campo «version» del archivo.';
