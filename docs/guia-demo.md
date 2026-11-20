# Guía para presentar la demo de Mediar

## Antes de presentar (5 minutos)

1. Abrir **Docker Desktop** y, en la carpeta del proyecto:
   ```bash
   docker compose up -d
   ```
2. Dejar la demo en su estado original (por si se usó antes):
   ```bash
   docker compose exec moodle sh /opt/mediar/scripts/restaurar_demo.sh
   ```
3. *(Opcional)* Ocultar los mensajes técnicos de Moodle: en `.env` poner `MOODLE_DEBUG=false` y ejecutar `docker compose up -d`.
4. Abrir http://localhost:8080 en una ventana de incógnito por cada rol, así no hay que cerrar sesión entre uno y otro.

**Clave de todas las cuentas:** `Mediar2026!`

| Cuenta | Rol | Para mostrar |
|---|---|---|
| `est.cps01` | Estudiante (Valentina) | Panel, presentar **en vivo** el Cuadernillo 3 y el test de personalidad. No los tiene presentados. |
| `est.cps02` | Estudiante (Santiago) | Modo oscuro, historial completo y un resultado del test ya hecho. |
| `prof.matematicas` | Profesor | Sus 4 cursos en 2 colegios, estadísticas del grupo y banco de preguntas. |
| `coordinacion` | Coordinación (Laura) | Panel institucional, resultados del test y ajustes. |

## Recorrido sugerido (15–20 minutos)

**1. La entrada (1 min).** Abrir la página de acceso: identidad Mediar, mensaje Saber 11. Mostrarla también en el celular.

**2. El estudiante (5 min) — `est.cps01`.**
- **Panel:**
  - "Hola, Valentina", su colegio y la cuenta regresiva al ICFES.
  - El último simulacro sobre 500 y la diferencia con el anterior.
  - Sus cursos con NRC.
  - La columna derecha "Próximas actividades".
- **Mi progreso:** la evolución por área, y las fortalezas y temas por reforzar (Química, Geometría…).
- **En vivo:**
  - Entrar a Matemáticas → Cuadernillo 3 → presentarlo.
  - Al terminar se ve la nota, la explicación de cada respuesta y la retroalimentación.
  - Volver al panel: las pendientes bajan y la tarjeta muestra el resultado.
- **Test de personalidad:** presentarlo (unos 3 minutos si se responde rápido) y mostrar el perfil con los consejos de estudio.

**3. El profesor (5 min) — `prof.matematicas`.**
- **Panel:**
  - Sus cursos en **dos colegios** con el promedio del grupo.
  - La comparación entre grupos.
  - Los estudiantes que necesitan apoyo.
- **"Ver estadísticas" de un curso:**
  - Distribución, evolución, aciertos por **componente** y **competencia**, y las preguntas más difíciles.
  - La tabla de estudiantes con su tendencia.
  - Exportar a Excel.
- **Curso Simulacros ICFES:** el puntaje sobre 500 y el promedio por área.
- **Banco de preguntas:** preguntas por componente y etiquetadas por competencia, compartidas por todos los colegios.

**4. La coordinación (3 min) — `coordinacion`.**
- **Panel:** indicadores de la institución, resultados por colegio, promedio por área y la agenda institucional.
- **Test de personalidad:** participación, distribución, resultados y **carga de un instrumento nuevo** (el test se puede cambiar sin programar).
- **Ajustes Mediar:** fecha del examen y umbral de alerta.

**5. Cierre (1 min).** Modo oscuro (ícono del sol en la barra) y la vista en el celular.

## Qué aclarar al presentar

- **Datos ficticios:**
  - Los estudiantes, los resultados y el colegio "Los Almendros" son inventados para la demo.
  - Los resultados se generaron con un modelo que muestra mejora en el tiempo y temas débiles.
- **Puntajes:**
  - Cuadernillos: aciertos (7/10).
  - Simulacros: escala 0–500 con la ponderación del ICFES (Inglés pesa ×1; las otras cuatro áreas, ×3).
  - El ICFES real calcula el puntaje de cada área con un modelo estadístico, no como un porcentaje simple: la demo es una aproximación.
- **Fecha del examen:** el 7 de marzo de 2027 es una fecha de referencia. Se cambia en *Ajustes Mediar*.
- **Test de personalidad:**
  - Es **ilustrativo**: preguntas originales inspiradas en las cuatro dimensiones del MBTI, no el MBTI oficial.
  - No es un diagnóstico ni mide capacidades.
  - Lo ven solo el estudiante y la coordinación.
  - Mediar decide qué instrumento usar.
- **Privacidad:**
  - Cada rol ve solo lo que le corresponde (verificado).
  - Los datos del test se pueden exportar o borrar a solicitud (Ley 1581).

## Después de presentar

```bash
docker compose exec moodle sh /opt/mediar/scripts/restaurar_demo.sh   # deja todo como estaba
docker compose down                                                     # apagar (conserva los datos)
```

## Si algo falla

| Síntoma | Solución |
|---|---|
| La página no carga | ¿Docker Desktop está abierto? Ejecutar `docker compose up -d` y esperar 30 s. |
| Se ve como Moodle estándar (azul) | `docker compose exec moodle php /opt/mediar/scripts/setup_tema.php` |
| Faltan datos o algo quedó a medias | `docker compose exec moodle sh /opt/mediar/scripts/montar_demo.sh` (no duplica nada) |
| Comprobar que todo funciona | `MEDIAR_IDS=$(sh herramientas/capturas/ids_demo.sh) node herramientas/capturas/verificar_demo.js` (51 verificaciones) |
