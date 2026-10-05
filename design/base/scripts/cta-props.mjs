import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
const SK = process.env.HOME + '/.claude/skills/figma-auto-html-merge/scripts';
const req = createRequire(SK + '/x.js');
const { chromium } = req('playwright-core');
const { launchOptions } = await import(pathToFileURL(SK + '/browser.mjs').href);
const browser = await chromium.launch({ ...launchOptions(), args: [ '--disable-gpu' ] });
for (const url of process.argv.slice(2)) {
	const page = await browser.newPage({ viewport: { width: 390, height: 900 }, ignoreHTTPSErrors: true });
	await page.goto(url, { waitUntil: 'load' });
	await page.evaluate(() => document.fonts.ready);
	const r = await page.evaluate(() => { const e = document.querySelector('nav.nav-links a.btn'); const c = getComputedStyle(e); const b = e.getBoundingClientRect(); return { rect: [b.x, b.y, b.width, b.height].map((v) => +v.toFixed(2)), ff: c.fontFamily, fw: c.fontWeight, fs: c.fontSize, ls: c.letterSpacing, lh: c.lineHeight, pad: c.padding, tt: c.textTransform, fsm: c.fontSmooth || c.webkitFontSmoothing, tr: c.transform, op: c.opacity }; });
	console.log(url.split('/').slice(-2).join('/'), JSON.stringify(r));
	await page.close();
}
await browser.close();
