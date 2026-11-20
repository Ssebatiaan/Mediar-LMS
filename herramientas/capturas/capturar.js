// Capturas de pantalla de Moodle con Edge instalado (playwright-core, sin descargar navegadores).
// Instalar una vez: npm install (en esta carpeta). En Git Bash: export MSYS_NO_PATHCONV=1
// Uso: node capturar.js <usuario|-> <ancho> <salida_dir> <ruta1> [ruta2 ...]
//   usuario "-" = sin iniciar sesión. Las rutas son relativas a http://localhost:8080
const { chromium } = require('playwright-core');
const path = require('path');

const BASE = 'http://localhost:8080';
const CLAVE = process.env.MEDIAR_CLAVE || 'Mediar2026!';

(async () => {
  const [usuario, ancho, salida, ...rutas] = process.argv.slice(2);
  const width = parseInt(ancho, 10);
  const movil = width < 600;
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  const context = await browser.newContext({
    viewport: { width, height: movil ? 844 : 900 },
    deviceScaleFactor: 1,
    isMobile: movil,
    hasTouch: movil,
    locale: 'es-CO',
  });
  const page = await context.newPage();
  const errores = [];
  page.on('pageerror', (e) => errores.push(e.message));

  if (usuario !== '-') {
    await page.goto(`${BASE}/login/index.php`);
    await page.fill('#username', usuario);
    await page.fill('#password', CLAVE);
    await Promise.all([page.waitForNavigation(), page.click('#loginbtn')]);
  }

  for (const ruta of rutas) {
    await page.goto(BASE + ruta, { waitUntil: 'networkidle' });
    await page.waitForTimeout(600);
    const nombre = `${usuario === '-' ? 'anonimo' : usuario}_${width}_${ruta.replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '') || 'inicio'}.png`;
    await page.screenshot({ path: path.join(salida, nombre), fullPage: false });
    console.log(nombre, '|', await page.title());
  }
  if (errores.length) console.log('Errores JS:', errores.slice(0, 5));
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
