// Recorre el test de personalidad como un estudiante: inicio, validación, 4 páginas y resultado.
const { chromium } = require('playwright-core');
(async () => {
  const [usuario, ancho] = [process.argv[2], parseInt(process.argv[3] || '1440', 10)];
  const b = await chromium.launch({ channel: 'msedge', headless: true });
  const p = await (await b.newContext({ viewport: { width: ancho, height: 900 } })).newPage();
  const errores = []; p.on('pageerror', (e) => errores.push(e.message));
  await p.goto('http://localhost:8080/login/index.php');
  await p.fill('#username', usuario); await p.fill('#password', 'Mediar2026!');
  await Promise.all([p.waitForNavigation(), p.click('#loginbtn')]);
  await p.goto('http://localhost:8080/local/mediartest/index.php', { waitUntil: 'networkidle' });
  await p.screenshot({ path: `salida/p6/${usuario}_${ancho}_1_inicio.png`, fullPage: true });
  await Promise.all([p.waitForNavigation(), p.click('.mediartest-acciones .btn-primary')]);
  // Validación: avanzar sin responder.
  await Promise.all([p.waitForNavigation(), p.click('button[name=siguiente]')]);
  console.log('validación sin responder:', await p.locator('.alert-warning').count() > 0 ? 'OK (aviso mostrado)' : 'FALLA');
  for (let pagina = 1; pagina <= 10; pagina++) {
    const grupos = await p.locator('fieldset.mediartest-item').count();
    for (let i = 0; i < grupos; i++) {
      const opciones = p.locator('fieldset.mediartest-item').nth(i).locator('label.mediartest-opcion');
      await opciones.nth((i * 3 + pagina) % 5).click();
    }
    if (pagina === 2) await p.screenshot({ path: `salida/p6/${usuario}_${ancho}_2_pagina.png`, fullPage: false });
    await Promise.all([p.waitForNavigation(), p.click('button[name=siguiente]')]);
    if (p.url().includes('resultado.php')) { console.log('páginas respondidas:', pagina); break; }
  }
  await p.waitForTimeout(500);
  console.log('final:', p.url(), '|', await p.title());
  await p.screenshot({ path: `salida/p6/${usuario}_${ancho}_3_resultado.png`, fullPage: true });
  if (errores.length) console.log('Errores JS:', errores);
  await b.close();
})().catch((e) => { console.error(e); process.exit(1); });
