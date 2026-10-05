import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
const S = process.env.HOME + '/.claude/skills/figma-auto-html-merge/scripts';
const { chromium } = createRequire(S + '/x.js')('playwright-core');
const { launchOptions } = await import(pathToFileURL(S + '/browser.mjs').href);
const browser = await chromium.launch({ ...launchOptions(), args: [ '--disable-gpu' ] });
const page = await browser.newPage({ viewport: { width: Number(process.argv[3]||390), height: 844 } });
await page.goto(process.argv[2], { waitUntil: 'load' });
const res = await page.evaluate(() => {
  const out = [];
  document.querySelectorAll('body *').forEach(el => {
    const r = el.getBoundingClientRect();
    if (r.right > window.innerWidth + 0.5 && r.width > 0) out.push({ tag: el.tagName, cls: (el.className && el.className.baseVal === undefined ? el.className : '').toString().slice(0, 80), right: Math.round(r.right), width: Math.round(r.width), top: Math.round(r.top + scrollY) });
  });
  return { scrollWidth: document.documentElement.scrollWidth, count: out.length, first: out.slice(0, 25) };
});
console.log(JSON.stringify(res, null, 1));
await browser.close();
