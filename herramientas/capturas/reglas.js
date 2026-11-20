const { chromium } = require('playwright-core');
(async () => {
  const [ruta, sel, prop] = process.argv.slice(2);
  const b = await chromium.launch({ channel: 'msedge', headless: true });
  const p = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
  await p.goto('http://localhost:8080' + ruta, { waitUntil: 'networkidle' });
  const r = await p.evaluate(([sel, prop]) => {
    const el = document.querySelector(sel); const out = [];
    const walk = (rules, media) => { for (const rule of rules) {
      if (rule.cssRules && !rule.selectorText) { walk(rule.cssRules, (rule.conditionText || '') ); continue; }
      try { if (rule.selectorText && el.matches(rule.selectorText) && rule.style.getPropertyValue(prop)) out.push(`[${(rule.parentStyleSheet && rule.parentStyleSheet.href || "inline").slice(-70)}] ${media ? '@' + media + ' ' : ''}${rule.selectorText.slice(0, 160)} { ${prop}: ${rule.style.getPropertyValue(prop)} }`); } catch (e) {}
    } };
    for (const ss of document.styleSheets) { try { walk(ss.cssRules, ''); } catch (e) {} }
    return out;
  }, [sel, prop]);
  console.log(r.join('\n'));
  await b.close();
})();
