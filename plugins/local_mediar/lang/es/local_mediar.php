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
 * Cadenas en español de local_mediar.
 *
 * @package    local_mediar
 * @copyright  2026 Mediar
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Mediar';
$string['privacy:metadata'] = 'Mediar no guarda datos personales: resume los intentos de los cuestionarios, que gestiona la actividad Cuestionario.';
$string['mediar:verestadisticascurso'] = 'Ver las estadísticas del curso y el progreso de sus estudiantes';
$string['mediar:verestadisticasinstitucion'] = 'Ver las estadísticas de toda la institución';

// Ajustes.
$string['fechaicfes'] = 'Fecha del examen Saber 11';
$string['fechaicfes_desc'] = 'Fecha de referencia del examen (AAAA-MM-DD). Se usa en la cuenta regresiva del panel de inicio.';
$string['umbralalerta'] = 'Umbral de alerta (%)';
$string['umbralalerta_desc'] = 'Por debajo de este porcentaje de aciertos un estudiante o un tema se marca como «necesita apoyo» o «reforzar».';

// Próximas actividades.
$string['proximas'] = 'Próximas actividades';
$string['totalproximas'] = '{$a} actividades próximas';
$string['vertodas'] = 'Ver todas';
$string['sinproximas'] = 'No tienes actividades próximas. ¡Vas al día!';
$string['vercalendario'] = 'Ver todo el calendario';
$string['eventocierre'] = 'Cierre de {$a}';
$string['eventoapertura'] = 'Apertura de {$a}';
$string['eventositio'] = 'Mediar';

// Comunes.
$string['estadisticas'] = 'Estadísticas Mediar';
$string['estadisticascurso'] = 'Estadísticas de {$a}';
$string['verestadisticas'] = 'Ver estadísticas';
$string['nrc'] = 'NRC {$a}';
$string['nestudiantes'] = '{$a} estudiantes';
$string['estudiantes'] = 'Estudiantes';
$string['estudiante'] = 'Estudiante';
$string['pctaciertos'] = '% de aciertos';
$string['puntajearea'] = 'Puntaje del área (0-100)';
$string['promediogrupo'] = 'Promedio del grupo';
$string['promediopct'] = 'Promedio (%)';
$string['reforzar'] = 'Reforzar';
$string['necesitaapoyo'] = 'Necesita apoyo';
$string['pendiente'] = 'Pendiente';
$string['proximamente'] = 'Próximamente';
$string['nopresentado'] = 'No presentado';

// Panel del curso.
$string['evaluaciones'] = 'Evaluaciones';
$string['evaluacion'] = 'Evaluación';
$string['sinresultados'] = 'Este curso todavía no tiene evaluaciones con resultados.';
$string['abrirevaluacion'] = 'Abrir evaluación';
$string['evaluados'] = 'Estudiantes evaluados';
$string['participacion'] = '{$a} % de participación';
$string['notamasalta'] = 'Nota más alta';
$string['notamasbaja'] = 'Nota más baja';
$string['distribucion'] = 'Distribución de resultados';
$string['evoluciongrupo'] = 'Evolución del grupo';
$string['evolucionfaltan'] = 'La evolución aparece cuando hay al menos dos evaluaciones con resultados.';
$string['puntajeareas'] = 'Promedio del grupo por área';
$string['puntajeareas_ayuda'] = 'Puntaje de 0 a 100 en cada área. El global sobre 500 pondera Lectura, Matemáticas, Sociales y Naturales ×3 e Inglés ×1.';
$string['porcomponente'] = 'Aciertos por componente';
$string['porcomponente_ayuda'] = 'Temas evaluados, de menor a mayor porcentaje de aciertos.';
$string['porcompetencia'] = 'Aciertos por competencia';
$string['porcompetencia_ayuda'] = 'Competencias del ICFES, de menor a mayor porcentaje de aciertos.';
$string['preguntasdificiles'] = 'Preguntas con mayor dificultad';
$string['pregunta'] = 'Pregunta';
$string['componente'] = 'Componente';
$string['competencia'] = 'Competencia';
$string['aciertos'] = 'Aciertos';
$string['seguimientoestudiantes'] = 'Seguimiento de estudiantes';
$string['alertas_ayuda'] = 'Se marca «Necesita apoyo» a quien obtuvo menos del {$a} % en su última evaluación presentada. Haz clic en un nombre para ver su historial.';
$string['ultimo'] = 'Última';
$string['tendencia'] = 'Tendencia';
$string['estado'] = 'Estado';
$string['sube'] = 'Mejora';
$string['baja'] = 'Baja';
$string['exportar'] = 'Exportar:';
$string['exportar_excel'] = 'Excel';
$string['exportar_pdf'] = 'PDF';
$string['exportar_csv'] = 'CSV';

// Historial del estudiante.
$string['miprogreso'] = 'Mi progreso';
$string['progresode'] = 'Progreso de {$a}';
$string['ultimosimulacro'] = 'Último simulacro';
$string['frentealanterior'] = '{$a} frente al anterior';
$string['promediocuadernillos'] = 'Promedio en cuadernillos';
$string['presentados'] = 'Cuadernillos presentados';
$string['pendientes'] = 'Actividades pendientes';
$string['progresoarea'] = 'Mi progreso por área';
$string['progresoarea_ayuda'] = 'Porcentaje de aciertos en cada cuadernillo, por asignatura.';
$string['simulacrosarea'] = 'Simulacros por área';
$string['simulacrosarea_ayuda'] = 'Puntaje de 0 a 100 en cada área: el último simulacro en naranja y el anterior en gris.';
$string['sinsimulacros'] = 'Aún no has presentado simulacros.';
$string['fortalezas'] = 'Fortalezas';
$string['debilidades'] = 'Temas por reforzar';
$string['simulacros'] = 'Simulacros';
$string['global'] = 'Global';
$string['historial'] = 'Historial de evaluaciones';
