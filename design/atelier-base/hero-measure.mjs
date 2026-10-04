import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
const SK = process.env.HOME + '/.claude/skills/figma-auto-html-merge/scripts';
const req = createRequire(SK + '/x.js');
const { chromium } = req('playwright-core');
const { launchOptions } = await import(pathToFileURL(SK + '/browser.mjs').href);
const browser = await chromium.launch({ ...launchOptions(), args: [ '--disable-gpu' ] });
for (const h of [ 900, 1000 ]) {
	const page = await browser.newPage({ viewport: { width: 1440, height: h } });
	await page.goto('file://' + process.cwd() + '/atelier-base/reference.html', { waitUntil: 'load' });
	await page.evaluate(() => document.fonts.ready);
	const r = await page.evaluate(() => {
		const q = (s) => { const e = document.querySelector(s); if (!e) return null; const b = e.getBoundingClientRect(); const c = getComputedStyle(e); return { top: Math.round(b.top + scrollY), h: Math.round(b.height), opacity: c.opacity, display: c.display }; };
		return { hero: q('.hero'), h1: q('.hero h1'), cap: q('.hero-cap'), photo: q('.hero-photo'), footer: q('.hero-footer'), docH: document.documentElement.scrollHeight };
	});
	console.log(h, JSON.stringify(r));
	await page.close();
}
await browser.close();
