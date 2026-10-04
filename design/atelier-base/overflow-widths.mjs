import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
const S = process.env.HOME + '/.claude/skills/figma-auto-html-merge/scripts';
const { chromium } = createRequire(S + '/x.js')('playwright-core');
const { launchOptions } = await import(pathToFileURL(S + '/browser.mjs').href);
const [ url, ...widths ] = process.argv.slice(2);
const browser = await chromium.launch({ ...launchOptions(), args: [ '--disable-gpu' ] });
for (const w of widths) {
  const page = await browser.newPage({ viewport: { width: Number(w), height: 844 } });
  await page.goto(url, { waitUntil: 'load' });
  const sw = await page.evaluate(() => document.documentElement.scrollWidth);
  console.log(w, sw, sw > Number(w) ? 'OVERFLOW' : 'ok');
  await page.close();
}
await browser.close();
