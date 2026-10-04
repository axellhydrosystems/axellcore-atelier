import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
const SK = process.env.HOME + '/.claude/skills/figma-auto-html-merge/scripts';
const req = createRequire(SK + '/x.js');
const { chromium } = req('playwright-core');
const { launchOptions } = await import(pathToFileURL(SK + '/browser.mjs').href);
const browser = await chromium.launch({ ...launchOptions(), args: [ '--disable-gpu' ] });
for (const name of ['eager','lazy','none']) {
  const page = await browser.newPage({ viewport: { width: 390, height: 900 } });
  await page.goto(pathToFileURL(`/tmp/_mode/${name}/index.html`).href, { waitUntil: 'load' });
  await page.waitForTimeout(800);
  const before = await page.evaluate(() => [!!document.querySelector('link[href*="fonts-rest"]'), [...document.fonts].filter(f => f.status === 'loaded').length]);
  await page.mouse.wheel(0, 300);
  await page.waitForTimeout(800);
  const after = await page.evaluate(() => [!!document.querySelector('link[href*="fonts-rest"]'), [...document.fonts].filter(f => f.status === 'loaded').length]);
  console.log(name.padEnd(6), 'before load+idle: rest-requested', before[0], 'faces loaded', before[1], '| after scroll: rest-requested', after[0], 'faces loaded', after[1]);
  await page.close();
}
await browser.close();
