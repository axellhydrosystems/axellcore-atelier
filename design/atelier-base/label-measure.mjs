import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
const SK = process.env.HOME + '/.claude/skills/figma-auto-html-merge/scripts';
const req = createRequire(SK + '/x.js');
const { chromium } = req('playwright-core');
const { launchOptions } = await import(pathToFileURL(SK + '/browser.mjs').href);
const browser = await chromium.launch({ ...launchOptions(), args: [ '--disable-gpu' ] });
for (const f of process.argv.slice(2)) {
	const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
	await page.goto(pathToFileURL(f).href, { waitUntil: 'load' });
	await page.evaluate(() => document.fonts.ready);
	console.log(f.split('/').slice(-2).join('/'), JSON.stringify(await page.evaluate(() => {
		const e = [...document.querySelectorAll('.editorial .chapter-tag')][0];
		const c = getComputedStyle(e); const b = e.getBoundingClientRect();
		return { tag: e.tagName, h: Math.round(b.height * 100) / 100, lh: c.lineHeight, fs: c.fontSize, mt: c.marginTop, mb: c.marginBottom };
	})));
	await page.close();
}
await browser.close();
