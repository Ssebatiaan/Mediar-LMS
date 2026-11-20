// Verificación completa de la demo (paso 7): recorre las páginas de cada rol y revisa
//   - que cargue sin errores (título "Error", mensajes de depuración de Moodle, errores de JavaScript);
//   - que los accesos prohibidos SE NIEGUEN (privacidad);
//   - que en móvil (390 px) no haya desbordamiento horizontal.
//
// Uso (Git Bash: export MSYS_NO_PATHCONV=1):  node verificar_demo.js
// Sale con código 1 si algo falla. Los IDs corresponden a la demo creada por los scripts de scripts/.
const { chromium } = require('playwright-core');

const BASE = 'http://localhost:8080';
const CLAVE = process.env.MEDIAR_CLAVE || 'Mediar2026!';
// IDs de la demo: se pueden pasar en MEDIAR_IDS (JSON, ver ids_demo.sh) porque cambian si la demo se monta
// en otra instalación. Por defecto, los de la instalación original.
const ID = Object.assign({
  mat: 2, lec: 3, simCps: 7, matAlm: 8, banco: 14,
  cmC1: 29, cmC3: 31, cmS2: 45, cmBanco: 24,
  cps01: 10, cps02: 11, alm01: 30,
}, JSON.parse(process.env.MEDIAR_IDS || '{}'));

// [ruta, esperado]  esperado: 'ok' = debe verse; 'no' = debe negarse.
const PLAN = {
  '-': [
    ['/login/index.php', 'ok'],
    ['/local/mediar/curso.php?id=' + ID.mat, 'no'],
    ['/local/mediartest/admin.php', 'no'],
  ],
  'est.cps01': [
    ['/my/', 'ok'],
    ['/my/courses.php', 'ok'],
    ['/course/view.php?id=' + ID.mat, 'ok'],
    ['/course/view.php?id=' + ID.simCps, 'ok'],
    ['/mod/quiz/view.php?id=' + ID.cmC3, 'ok'],
    ['/local/mediar/estudiante.php', 'ok'],
    ['/local/mediartest/index.php', 'ok'],
    ['/calendar/view.php?view=upcoming', 'ok'],
    ['/grade/report/overview/index.php', 'ok'],
    // Privacidad: nada de otros estudiantes ni de estadísticas del grupo.
    ['/local/mediar/estudiante.php?id=' + ID.cps02, 'no'],
    ['/local/mediar/curso.php?id=' + ID.mat, 'no'],
    ['/local/mediar/exportar.php?id=' + ID.mat + '&formato=csv', 'no'],
    ['/local/mediartest/resultado.php?id=' + ID.cps02, 'no'],
    ['/local/mediartest/admin.php', 'no'],
    ['/course/view.php?id=' + ID.matAlm, 'no'], // curso de otro colegio
  ],
  'est.cps02': [ // modo oscuro
    ['/my/', 'ok'],
    ['/local/mediar/estudiante.php', 'ok'],
    ['/local/mediartest/resultado.php', 'ok'],
    ['/mod/quiz/view.php?id=' + ID.cmS2, 'ok'],
  ],
  'prof.matematicas': [
    ['/my/', 'ok'],
    ['/local/mediar/curso.php?id=' + ID.mat, 'ok'],
    ['/local/mediar/curso.php?id=' + ID.matAlm, 'ok'],
    ['/local/mediar/curso.php?id=' + ID.simCps, 'ok'],
    ['/local/mediar/estudiante.php?id=' + ID.cps02, 'ok'],
    ['/mod/quiz/report.php?id=' + ID.cmC1 + '&mode=overview', 'ok'],
    ['/question/edit.php?cmid=' + ID.cmBanco, 'ok'],
    // El profesor NO ve el test de personalidad ni cursos ajenos.
    ['/local/mediartest/admin.php', 'no'],
    ['/local/mediartest/resultado.php?id=' + ID.cps02, 'no'],
    ['/local/mediar/curso.php?id=' + ID.lec, 'no'],
  ],
  'coordinacion': [
    ['/my/', 'ok'],
    ['/local/mediar/curso.php?id=' + ID.lec, 'ok'],
    ['/local/mediar/estudiante.php?id=' + ID.cps02, 'ok'],
    ['/local/mediartest/admin.php', 'ok'],
    ['/local/mediartest/resultado.php?id=' + ID.cps02, 'ok'],
    ['/admin/settings.php?section=local_mediar', 'ok'],
    ['/cohort/index.php', 'ok'],
  ],
};
// Páginas que también se revisan en móvil (desbordamiento horizontal).
const MOVIL = new Set(['/my/', '/my/courses.php', '/local/mediar/estudiante.php', '/local/mediartest/index.php',
  '/local/mediartest/resultado.php', '/mod/quiz/view.php?id=' + ID.cmC3, '/local/mediar/curso.php?id=' + ID.mat,
  '/login/index.php']);

async function revisar(page, ruta, esperado, ancho) {
  const errores = [];
  const alerror = (e) => errores.push(e.message || String(e));
  page.on('pageerror', alerror);
  let estado = 0;
  try {
    const r = await page.goto(BASE + ruta, { waitUntil: 'networkidle', timeout: 45000 });
    estado = r ? r.status() : 0;
  } catch (e) {
    errores.push('navegación: ' + e.message.split('\n')[0]);
  }
  await page.waitForTimeout(300);
  const info = await page.evaluate(() => ({
    titulo: document.title,
    url: location.pathname + location.search,
    depuracion: document.querySelectorAll('.debuggingmessage, .notifytiny, .debuginfo').length,
    textoerror: /(Notice|Warning|Deprecated|Fatal error|Error de codificación|Coding error):/i.test(document.body.innerText),
    desborde: document.documentElement.scrollWidth - window.innerWidth,
    login: !!document.querySelector('#loginbtn'),
  }));
  page.off('pageerror', alerror);

  const objetivo = ruta.split('?')[0];
  const negado = info.titulo.startsWith('Error') || estado >= 400 || info.login && objetivo !== '/login/index.php'
    || !info.url.startsWith(objetivo);
  const fallas = [];
  if (esperado === 'ok' && negado) fallas.push(`se esperaba acceso y se negó (${info.titulo} → ${info.url})`);
  if (esperado === 'no' && !negado) fallas.push(`ACCESO INDEBIDO: se ve «${info.titulo}»`);
  if (esperado === 'ok' && (info.depuracion || info.textoerror)) fallas.push('mensajes de error/depuración en la página');
  if (esperado === 'ok' && errores.length) fallas.push('JS: ' + errores.slice(0, 2).join(' | '));
  if (esperado === 'ok' && ancho < 600 && info.desborde > 2) fallas.push(`desborde horizontal de ${info.desborde}px`);
  return { fallas, titulo: info.titulo };
}

(async () => {
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  let total = 0;
  const fallidas = [];
  for (const [usuario, rutas] of Object.entries(PLAN)) {
    for (const ancho of [1440, 390]) {
      const casos = rutas.filter(([r, e]) => ancho === 1440 || (e === 'ok' && MOVIL.has(r)));
      if (!casos.length) continue;
      const ctx = await browser.newContext({ viewport: { width: ancho, height: 900 }, isMobile: ancho < 600, locale: 'es-CO' });
      const page = await ctx.newPage();
      if (usuario !== '-') {
        await page.goto(BASE + '/login/index.php');
        await page.fill('#username', usuario);
        await page.fill('#password', CLAVE);
        await Promise.all([page.waitForNavigation(), page.click('#loginbtn')]);
      }
      for (const [ruta, esperado] of casos) {
        total++;
        const { fallas, titulo } = await revisar(page, ruta, esperado, ancho);
        const marca = fallas.length ? '✗' : '✓';
        console.log(`${marca} ${usuario.padEnd(16)} ${String(ancho).padEnd(4)} ${esperado === 'ok' ? 'ver  ' : 'negar'} ${ruta.padEnd(52)} ${fallas.length ? fallas.join('; ') : titulo}`);
        if (fallas.length) fallidas.push({ usuario, ancho, ruta, fallas });
      }
      await ctx.close();
    }
  }
  await browser.close();
  console.log(`\n${total - fallidas.length}/${total} verificaciones correctas`);
  process.exit(fallidas.length ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
