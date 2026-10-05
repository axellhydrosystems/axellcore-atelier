import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
const SK = process.env.HOME + '/.claude/skills/figma-auto-html-merge/scripts';
const req = createRequire(SK + '/x.js');
const { chromium } = req('playwright-core');
const { launchOptions } = await import(pathToFileURL(SK + '/browser.mjs').href);
const browser = await chromium.launch({ ...launchOptions(), args: [ '--disable-gpu' ] });
for (const url of ['https://localhost:8543/index.html', 'https://localhost:8544/index.html']) {
  for (const width of [1440, 390]) {
    const ctx = await browser.newContext({ viewport: { width, height: 900 }, ignoreHTTPSErrors: true });
    const page = await ctx.newPage();
    const problems = [];
    page.on('console', (m) => { if (m.type() === 'error') problems.push('console: ' + m.text().slice(0, 120)); });
    page.on('requestfailed', (r) => problems.push('failed: ' + r.url().slice(0, 100)));
    page.on('response', (r) => { if (r.status() >= 400) problems.push('HTTP ' + r.status() + ' ' + r.url().slice(0, 100)); });
    await page.goto(url, { waitUntil: 'load' });
    await page.evaluate(() => document.fonts.ready);
    await page.mouse.wheel(0, 400);
    await page.waitForTimeout(1200);
    const info = await page.evaluate(() => ({
      title: document.title,
      fontsLoaded: [...document.fonts].filter((f) => f.status === 'loaded').length,
      fontsTotal: document.fonts.size,
      h1: document.querySelector('h1') && document.querySelector('h1').textContent.trim().slice(0, 40),
      sections: document.querySelectorAll('section').length,
      formFields: document.querySelectorAll('form input, form select').length,
      labelsLinked: [...document.querySelectorAll('label[for]')].length,
    }));
    console.log(url.split('/')[2], width, JSON.stringify(info), problems.length ? problems.slice(0, 4).join(' | ') : 'no errors');
    await ctx.close();
  }
}
await browser.close();
