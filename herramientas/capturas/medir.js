// Mide el ancho de la cadena de contenedores de cada gráfico.
const { chromium } = require('playwright-core');
(async () => {
  const [usuario, ruta] = process.argv.slice(2);
  const b = await chromium.launch({ channel: 'msedge', headless: true });
  const p = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
  await p.goto('http://localhost:8080/login/index.php');
  await p.fill('#username', usuario); await p.fill('#password', 'Mediar2026!');
  await Promise.all([p.waitForNavigation(), p.click('#loginbtn')]);
  await p.goto('http://localhost:8080' + ruta, { waitUntil: 'networkidle' });
  await p.waitForTimeout(1500);
  console.log(JSON.stringify(await p.evaluate(() => [...document.querySelectorAll('canvas')].map((c) => {
    const out = []; let n = c;
    for (let i = 0; i < 6 && n; i++, n = n.parentElement) out.push(`${n.tagName}.${[...n.classList].slice(0, 2).join('.')} w=${Math.round(n.getBoundingClientRect().width)} disp=${getComputedStyle(n).display}`);
    return out;
  })), null, 1));
  await b.close();
})();
