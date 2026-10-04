import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
const S = process.env.HOME + '/.claude/skills/figma-auto-html-merge/scripts';
const { chromium } = createRequire(S + '/x.js')('playwright-core');
const { launchOptions } = await import(pathToFileURL(S + '/browser.mjs').href);
const browser = await chromium.launch({ ...launchOptions(), args: [ '--disable-gpu' ] });
const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
await page.goto('http://localhost:8906/atelier/', { waitUntil: 'load' });
const out = await page.evaluate(() => {
  const h2 = [...document.querySelectorAll('h2')].find(h => h.textContent.includes('Quatro passos'));
  const cols = h2.closest('.wp-block-columns');
  const kids = [...cols.children].map(c => { const cs = getComputedStyle(c); return { cls: c.className.slice(0,40), basis: cs.flexBasis, width: Math.round(c.getBoundingClientRect().width), y: Math.round(c.getBoundingClientRect().top+scrollY), flexDir: getComputedStyle(cols).flexDirection, wrap: getComputedStyle(cols).flexWrap, style: c.getAttribute('style') }; });
  return { colsDir: getComputedStyle(cols).flexDirection, kids };
});
console.log(JSON.stringify(out, null, 1));
await browser.close();
