import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
const S = process.env.HOME + '/.claude/skills/figma-auto-html-merge/scripts';
const { chromium } = createRequire(S + '/x.js')('playwright-core');
const { launchOptions } = await import(pathToFileURL(S + '/browser.mjs').href);
const browser = await chromium.launch({ ...launchOptions(), args: [ '--disable-gpu' ] });
const page = await browser.newPage({ viewport: { width: Number(process.argv[3]), height: 844 } });
await page.goto(process.argv[2], { waitUntil: 'load' });
const res = await page.evaluate(() => {
  const h = [...document.querySelectorAll('h2')].find(el => el.getBoundingClientRect().right > window.innerWidth + 0.5);
  if (!h) return 'none';
  const chain = [];
  let el = h;
  while (el && el !== document.body) {
    const r = el.getBoundingClientRect(); const cs = getComputedStyle(el);
    chain.push({ tag: el.tagName, cls: String(el.className).slice(0, 60), w: Math.round(r.width), left: Math.round(r.left), padL: cs.paddingLeft, padR: cs.paddingRight, minW: cs.minWidth, display: cs.display, gridCols: cs.gridTemplateColumns.slice(0,60) });
    el = el.parentElement;
  }
  return chain;
});
console.log(JSON.stringify(res, null, 1));
await browser.close();
