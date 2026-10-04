import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
const S = process.env.HOME + '/.claude/skills/figma-auto-html-merge/scripts';
const req = createRequire(S + '/x.js');
const { chromium } = req('playwright-core');
const { launchOptions } = await import(pathToFileURL(S + '/browser.mjs').href);
const [ width, out ] = process.argv.slice(2);
const browser = await chromium.launch({ ...launchOptions(), args: [ '--disable-gpu' ] });
const page = await browser.newPage({ viewport: { width: Number(width), height: 900 }, deviceScaleFactor: 1 });
await page.goto('http://localhost:8906/atelier/', { waitUntil: 'load' });
await page.evaluate(() => document.fonts.ready);
const h = await page.evaluate(() => document.documentElement.scrollHeight);
for (let y = 0; y < h; y += 400) { await page.evaluate(v => window.scrollTo(0, v), y); await page.waitForTimeout(90); }
await page.waitForTimeout(1300);
await page.evaluate(() => window.scrollTo(0, 0));
await page.waitForTimeout(400);
await page.evaluate(async () => {
	document.querySelectorAll('img').forEach(i => { i.loading = 'eager'; });
	await Promise.all([...document.images].map(i => i.decode().catch(() => 0)));
});
await page.screenshot({ path: out, fullPage: true, animations: 'disabled' });
await browser.close();
console.log(out);
