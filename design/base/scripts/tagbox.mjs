import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
const S = process.env.HOME + '/.claude/skills/figma-auto-html-merge/scripts';
const { chromium } = createRequire(S + '/x.js')('playwright-core');
const { launchOptions } = await import(pathToFileURL(S + '/browser.mjs').href);
const browser = await chromium.launch({ ...launchOptions(), args: [ '--disable-gpu' ] });
const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
await page.goto('http://localhost:8906/atelier/', { waitUntil: 'load' });
const out = await page.evaluate(() => {
  const tag = [...document.querySelectorAll('p')].find(p => p.textContent === 'Por que Atelier');
  const row = [...document.querySelectorAll('p')].find(p => p.textContent.startsWith('Espaço de criação')).closest('.wp-block-columns');
  const grp = row.parentElement; const col = tag.parentElement;
  const r = e => { const b = e.getBoundingClientRect(); const cs = getComputedStyle(e); return { y: Math.round(b.top + scrollY), h: Math.round(b.height), mt: cs.marginTop, mb: cs.marginBottom, pt: cs.paddingTop, disp: cs.display }; };
  return { tag: r(tag), colParent: { cls: col.className.slice(0, 50), ...r(col) }, grp: { cls: grp.className.slice(0, 60), ...r(grp) }, row: r(row), kidsOfCol: [...col.children].map(c => ({ n: c.tagName, ...r(c) })) };
});
console.log(JSON.stringify(out, null, 1));
await browser.close();
