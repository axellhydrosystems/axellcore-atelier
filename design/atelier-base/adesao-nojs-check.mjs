// No-JavaScript path of /atelier/adesao: admin-post submission and the server-rendered result.
// Nothing is created: the error case fails validation first, the honeypot case returns success before storing.
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
const SK = process.env.HOME + '/.claude/skills/figma-auto-html-merge/scripts';
const req = createRequire(SK + '/x.js');
const { chromium } = req('playwright-core');
const { launchOptions } = await import(pathToFileURL(SK + '/browser.mjs').href);
const browser = await chromium.launch({ ...launchOptions(), args: [ '--disable-gpu' ] });
const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, javaScriptEnabled: false });
const page = await context.newPage();
const base = 'http://localhost:8906/atelier/adesao/';
const shown = () => page.evaluate(() => [...document.querySelectorAll('[data-axell-notice-type]')].map((n) => `${n.getAttribute('data-axell-notice-type')}:${n.hidden ? 'hidden' : 'shown'}`));

await page.goto(base + '?axell-form=success', { waitUntil: 'load' });
console.log('result=success (query):', JSON.stringify(await shown()));
await page.goto(base + '?axell-form=error', { waitUntil: 'load' });
console.log('result=error (query):  ', JSON.stringify(await shown()));
await page.goto(base, { waitUntil: 'load' });
console.log('no result:             ', JSON.stringify(await shown()));

// Empty submission: the browser sends it (noValidate), the server answers with an error.
await page.locator('form[data-wp-interactive="axell/form"] button[type="submit"]').click();
await page.waitForLoadState('load');
console.log('empty submit ->', page.url().replace(base, '/adesao/'), JSON.stringify(await shown()));

// Honeypot only: bots fill it, the server answers success without storing anything.
await page.goto(base, { waitUntil: 'load' });
await page.evaluate(() => { document.querySelector('input[name="website"]').value = 'spam'; });
await page.locator('form[data-wp-interactive="axell/form"] button[type="submit"]').click();
await page.waitForLoadState('load');
console.log('honeypot ->', page.url().replace(base, '/adesao/'), JSON.stringify(await shown()));
await browser.close();
