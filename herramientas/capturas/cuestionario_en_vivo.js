// Presenta un cuestionario real como estudiante (flujo de la demo en vivo):
// ver -> iniciar (con límite de tiempo) -> responder todas las páginas -> resumen -> enviar -> revisión.
// Uso: node cuestionario_en_vivo.js <usuario> <cmid> <carpeta_salida>
const { chromium } = require('playwright-core');
(async () => {
  const [usuario, cmid, salida] = process.argv.slice(2);
  const b = await chromium.launch({ channel: 'msedge', headless: true });
  const p = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
  const errores = []; p.on('pageerror', (e) => errores.push(e.message));
  await p.goto('http://localhost:8080/login/index.php');
  await p.fill('#username', usuario); await p.fill('#password', 'Mediar2026!');
  await Promise.all([p.waitForNavigation(), p.click('#loginbtn')]);

  await p.goto(`http://localhost:8080/mod/quiz/view.php?id=${cmid}`, { waitUntil: 'networkidle' });
  await p.getByRole('button', { name: /Intento de cuestionario|Continuar/ }).first().click();
  // Con límite de tiempo, Moodle pide confirmar en un modal.
  const confirmar = p.getByRole('button', { name: /Comenzar intento/ });
  if (await confirmar.isVisible({ timeout: 4000 }).catch(() => false)) {
    await Promise.all([p.waitForNavigation(), confirmar.click()]);
  }
  await p.waitForLoadState('networkidle');

  let paginas = 0;
  for (;;) {
    paginas++;
    // Por pregunta: primera opción de radio, primera casilla, o texto en respuesta corta.
    for (const q of await p.locator('.que').all()) {
      const radio = q.locator('input[type=radio]:not([value="-1"])').first();
      const casilla = q.locator('input[type=checkbox]').first();
      const texto = q.locator('input[type=text]').first();
      if (await radio.count()) await radio.check({ force: true });
      else if (await casilla.count()) await casilla.check({ force: true });
      else if (await texto.count()) await texto.fill('5');
    }
    if (paginas === 1) await p.screenshot({ path: `${salida}/vivo_1_pregunta.png` });
    const siguiente = p.locator('#mod_quiz-next-nav');
    const texto = (await siguiente.getAttribute('value')) || (await siguiente.innerText());
    await Promise.all([p.waitForNavigation(), siguiente.click()]);
    if (/Terminar/i.test(texto) || p.url().includes('summary.php')) break;
    if (paginas > 20) throw new Error('demasiadas páginas');
  }
  console.log('páginas respondidas:', paginas, '| resumen:', p.url().includes('summary.php'));
  await p.getByRole('button', { name: /Enviar todo y terminar/ }).first().click();
  const modal = p.locator('.modal-dialog').getByRole('button', { name: /Enviar todo y terminar/ });
  await Promise.all([p.waitForNavigation(), modal.click()]);
  await p.waitForLoadState('networkidle');
  const resumen = await p.locator('.quizreviewsummary').innerText().catch(() => '');
  console.log('revisión:', p.url().includes('review.php'), '|', resumen.replace(/\s+/g, ' ').match(/Calificación[^%]*%\)?/)?.[0] || '');
  await p.screenshot({ path: `${salida}/vivo_2_revision.png` });
  if (errores.length) console.log('Errores JS:', errores);
  await b.close();
})().catch((e) => { console.error(e); process.exit(1); });
