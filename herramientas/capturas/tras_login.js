// Captura la PRIMERA página después de iniciar sesión (donde Moodle pone su saludo de bienvenida).
const { chromium } = require('playwright-core');
(async () => {
  const [usuario, salida] = process.argv.slice(2);
  const b = await chromium.launch({ channel: 'msedge', headless: true });
  const p = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
  await p.goto('http://localhost:8080/login/index.php');
  await p.fill('#username', usuario); await p.fill('#password', process.env.MEDIAR_CLAVE || 'Mediar2026!');
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle' }), p.click('#loginbtn')]);
  await p.waitForTimeout(1200);
  const texto = await p.evaluate(() => document.body.innerText);
  console.log(p.url(), '| saludo de Moodle visible:', /👋/.test(texto), '| saludos "Hola":', (texto.match(/Hola, /g) || []).length);
  await p.screenshot({ path: salida });
  await b.close();
})();
