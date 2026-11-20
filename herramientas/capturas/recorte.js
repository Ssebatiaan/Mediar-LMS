// Captura solo un elemento (selector) de una página, con sesión iniciada.
const { chromium } = require('playwright-core');
(async () => {
  const [usuario, ruta, selector, salida] = process.argv.slice(2);
  const b = await chromium.launch({ channel: 'msedge', headless: true });
  const p = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
  await p.goto('http://localhost:8080/login/index.php');
  await p.fill('#username', usuario); await p.fill('#password', 'Mediar2026!');
  await Promise.all([p.waitForNavigation(), p.click('#loginbtn')]);
  await p.goto('http://localhost:8080' + ruta, { waitUntil: 'networkidle' });
  await p.waitForTimeout(800);
  await p.locator(selector).first().screenshot({ path: salida });
  console.log('ok', salida);
  await b.close();
})();
