// Imprime el HTML (resumido) de selectores de una página de Moodle.
// Uso: node dom.js <usuario|-> <ruta> <selector1> [selector2 ...]
const { chromium } = require('playwright-core');
const BASE = 'http://localhost:8080';
(async () => {
  const [usuario, ruta, ...selectores] = process.argv.slice(2);
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
  if (usuario !== '-') {
    await page.goto(`${BASE}/login/index.php`);
    await page.fill('#username', usuario);
    await page.fill('#password', process.env.MEDIAR_CLAVE || 'Mediar2026!');
    await Promise.all([page.waitForNavigation(), page.click('#loginbtn')]);
  }
  await page.goto(BASE + ruta, { waitUntil: 'networkidle' });
  await page.waitForTimeout(800);
  for (const sel of selectores) {
    const html = await page.evaluate((s) => {
      const el = document.querySelector(s);
      if (!el) return '(no encontrado)';
      // Resumen: etiquetas y clases, sin textos largos ni SVG.
      const resumir = (n, nivel) => {
        if (nivel > 7) return '';
        const pad = '  '.repeat(nivel);
        if (n.nodeType === 3) { const t = n.textContent.trim(); return t ? pad + '"' + t.slice(0, 40) + '"\n' : ''; }
        if (n.nodeType !== 1 || ['SVG', 'svg', 'SCRIPT', 'STYLE'].includes(n.tagName)) return '';
        const attrs = ['id', 'class', 'role', 'data-region', 'data-bs-theme'].map((a) => n.getAttribute(a) ? `${a}="${n.getAttribute(a)}"` : '').filter(Boolean).join(' ');
        let out = `${pad}<${n.tagName.toLowerCase()} ${attrs}>\n`;
        for (const c of n.childNodes) out += resumir(c, nivel + 1);
        return out;
      };
      return resumir(el, 0);
    }, sel);
    console.log(`===== ${sel}\n${html.split('\n').slice(0, 70).join('\n')}`);
  }
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
