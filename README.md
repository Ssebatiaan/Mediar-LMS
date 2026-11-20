# Mediar — Plataforma académica (Moodle)

Plataforma de preparación para las pruebas Saber 11 construida sobre **Moodle 5.3 LTS**, sin modificar el núcleo: toda la personalización va en plugins propios.

Requisitos y alcance: [documento inicial.txt](documento%20inicial.txt).

## Entorno local (demo)

Requisito: Docker Desktop abierto.

```bash
docker compose up -d            # levantar (la primera vez instala Moodle: ~3 min)
docker compose logs -f moodle   # ver el avance
docker compose down             # detener, conserva los datos
docker compose down -v          # BORRAR todo y empezar de cero
```

| Servicio | URL | Acceso |
|---|---|---|
| Moodle | http://localhost:8080 | usuario `admin`, clave en `.env` |
| Correo de prueba (Mailpit) | http://localhost:8025 | captura todos los correos que envía Moodle |

Stack: `erseco/alpine-moodle:5.3.1` (PHP 8.4 + nginx) extendida en [docker/moodle/Dockerfile](docker/moodle/Dockerfile) con los datos ICU completos (sin ellos las fechas salen en inglés) · PostgreSQL 17 · Mailpit.

### Cuentas demo

Todas usan la misma clave que el admin (`MOODLE_ADMIN_PASSWORD` en `.env`).

| Usuario | Rol | Qué ve |
|---|---|---|
| `admin` | Administrador del sitio (técnico) | Todo |
| `coordinacion` | Administrador Mediar (Gestor) | Todos los colegios, cursos y usuarios |
| `prof.matematicas`, `prof.lectura`, `prof.sociales`, `prof.naturales`, `prof.ingles` | Profesor | Su asignatura en los 2 colegios + los 2 cursos de Simulacros |
| `est.cps01` … `est.cps20` | Estudiante, Colegio Promoción Social | Sus 6 cursos |
| `est.alm01` … `est.alm12` | Estudiante, Colegio Los Almendros (ficticio) | Sus 6 cursos |

### Estructura académica

```
Colegios
├── Colegio Promoción Social / Promoción 2027      NRC 1119–1124
│   ├── Matemáticas · Lectura Crítica · Sociales y Ciudadanas · Ciencias Naturales · Inglés  (formato semanal)
│   └── Simulacros ICFES                                                                     (3 simulacros completos)
└── Colegio Los Almendros / Promoción 2027         NRC 1125–1130
```

- Cada curso tiene los campos **NRC** y **Área Saber 11** (los usarán dashboards y estadísticas).
- Los estudiantes se matriculan por **cohorte**: agregar un estudiante a la cohorte de su colegio lo matricula en los 6 cursos.
- Los cursos van en semanas desde el 5 de octubre de 2026 hasta la **fecha de referencia del examen (7 de marzo de 2027)**, que también es un evento del calendario. Ajustar en `scripts/seed_estructura.php` a la fecha oficial del ICFES.

> El puerto de Moodle debe ser **8080** afuera y adentro: si difieren, Moodle entra en un bucle de redirecciones.

## Desarrollo de plugins

Los plugins viven en `plugins/`, una carpeta por plugin con su nombre *frankenstyle* (`theme_mediar`, `local_mediar`, `block_...`). El contenedor los ve en solo lectura y se despliegan con:

```bash
docker compose exec moodle sh /opt/mediar/scripts/deploy.sh
```

El script copia cada plugin a su ruta dentro de `public/`, ejecuta `upgrade.php` y purga cachés.

**No montar carpetas del host dentro de `/var/www/html`:** al arrancar, la imagen sincroniza el código de Moodle con `rsync --delete` y podría borrar archivos del host.

En **Git Bash**, anteponer `MSYS_NO_PATHCONV=1` a los comandos `docker compose exec` que llevan rutas del contenedor (en PowerShell no hace falta).

## Scripts

| Script | Qué hace |
|---|---|
| `scripts/setup_site.php` | Nombre del sitio, zona horaria de Bogotá, país, idioma. Idempotente. |
| `scripts/deploy.sh` | Despliega los plugins de `plugins/`. |
| `scripts/seed_estructura.php` | Colegios, cursos con NRC, profesores, estudiantes, cohortes, evento del examen. Idempotente. |
| `scripts/verificar_estructura.php` | Resumen de solo lectura: cursos, matrículas y campos por curso. |

| `scripts/setup_tema.php` | Activa el tema Mediar, carga logos, imágenes de curso por área y modos claro/oscuro. Idempotente. |
| `scripts/marca/generar_logos.py` | (host) Logos PNG transparentes a partir del JPEG original. |
| `scripts/marca/generar_imagenes_cursos.py` | (host) Imágenes de tarjeta de curso por área. |

Para montar todo desde cero:

```bash
docker compose up -d
docker compose exec moodle php /opt/mediar/scripts/setup_site.php
docker compose exec moodle php /opt/mediar/scripts/seed_estructura.php
docker compose exec moodle sh /opt/mediar/scripts/deploy.sh
docker compose exec moodle php /opt/mediar/scripts/setup_tema.php
```

## Tema Mediar (`plugins/theme_mediar`)

Tema hijo de Boost. No reescribe layouts: cambia la paleta y sobrescribe solo dos plantillas.

| Archivo | Qué hace |
|---|---|
| `scss/pre.scss` | Paleta Mediar como variables de Bootstrap (`$primary` naranja, fondo `#F5F5F5`…) |
| `scss/post.scss` | Tokens del sistema de diseño de Moodle (azul → naranja, claro y oscuro), barra, tarjetas, login |
| `templates/theme_boost/navbar.mustache` | Barra superior siempre oscura (`data-bs-theme="dark"`) |
| `templates/core/login_panel.mustache` | Panel izquierdo del acceso con el mensaje de Mediar |
| `fonts/` | Poppins (SIL OFL) para títulos y navegación |

Los textos del panel de acceso están en `lang/es/theme_mediar.php`.

## Capturas de pantalla (verificación visual)

`herramientas/capturas/` usa el Edge instalado en Windows (sin descargar navegadores):

```bash
cd herramientas/capturas && npm install          # una vez
export MSYS_NO_PATHCONV=1                         # en Git Bash
node capturar.js est.cps01 1440 salida /my/courses.php "/course/view.php?id=2"
node capturar.js - 390 salida /login/index.php    # "-" = sin sesión; 390 = móvil
```

`dom.js` imprime la estructura HTML de un selector y `reglas.js` lista qué reglas CSS aplican a un elemento.

## Estructura

```
docker-compose.yml   entorno local
.env                 credenciales locales (no se versiona; ver .env.example)
plugins/             plugins propios de Mediar
scripts/             configuración, despliegue y datos demo
assets/              logo y recursos de marca
docs/                documentación y referencias
```

## Hoja de ruta

1. ✅ Moodle 5.3 en Docker
2. ✅ Estructura académica y datos demo (colegios, cursos con código NRC, profesores, estudiantes)
3. ✅ Tema Mediar
4. Simulacros (cuadernillos por asignatura y simulacro completo con escala ICFES 0–500)
5. Dashboards por rol y estadísticas
6. Test de personalidad configurable
