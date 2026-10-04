import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
const SK = process.env.HOME + '/.claude/skills/figma-auto-html-merge/scripts';
const req = createRequire(SK + '/x.js');
const { chromium } = req('playwright-core');
const { launchOptions } = await import(pathToFileURL(SK + '/browser.mjs').href);
const browser = await chromium.launch({ ...launchOptions(), args: [ '--disable-gpu' ] });
const grab = async (file) => {
	const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
	await page.goto(pathToFileURL(file).href, { waitUntil: 'load' });
	await page.evaluate(() => document.fonts.ready);
	const r = await page.evaluate(() => {
		const out = {};
		for (const sel of ['.nav-cta', '.nav-cta .arrow', '.nav']) {
			const e = document.querySelector(sel); const c = getComputedStyle(e);
			const b = e.getBoundingClientRect();
			out[sel] = { rect: [b.x, b.y, b.width, b.height].map((v) => +v.toFixed(3)), font: c.fontFamily, size: c.fontSize, ls: c.letterSpacing, bg: c.backgroundColor, color: c.color, tr: c.transition, tf: c.transform, pad: c.padding, shadow: c.boxShadow, fam: c.fontWeight };
		}
		out.fontsOk = document.fonts.check('12px Inter');
		return out;
	});
	await page.close();
	return r;
};
const a = await grab(process.cwd() + '/atelier-axell-club.html');
const b = await grab(process.cwd() + '/bootstrap/index.html');
for (const k of Object.keys(a)) {
	if (typeof a[k] !== 'object') { console.log(k, a[k], b[k]); continue; }
	for (const p of Object.keys(a[k])) if (JSON.stringify(a[k][p]) !== JSON.stringify(b[k]?.[p])) console.log(k, p, 'mock=', JSON.stringify(a[k][p]), 'proto=', JSON.stringify(b[k]?.[p]));
}
await browser.close();
