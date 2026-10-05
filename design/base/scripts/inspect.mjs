import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
const S = process.env.HOME + '/.claude/skills/figma-auto-html-merge/scripts';
const { chromium } = createRequire(S + '/x.js')('playwright-core');
const { launchOptions } = await import(pathToFileURL(S + '/browser.mjs').href);
const browser = await chromium.launch({ ...launchOptions(), args: [ '--disable-gpu' ] });
const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
await page.goto(process.argv[2], { waitUntil: 'load' });
const res = await page.evaluate((sel) => {
  const g = document.querySelector(sel);
  if (!g) return 'not found';
  const cs = getComputedStyle(g);
  return {
    display: cs.display, width: g.getBoundingClientRect().width, parentClass: g.parentElement.className,
    parentWidth: g.parentElement.getBoundingClientRect().width,
    kids: [...g.children].map(c => ({ tag: c.tagName, cls: String(c.className).slice(0,60), disp: getComputedStyle(c).display, w: Math.round(c.getBoundingClientRect().width), x: Math.round(c.getBoundingClientRect().left) })),
  };
}, process.argv[3]);
console.log(JSON.stringify(res, null, 1));
await browser.close();
