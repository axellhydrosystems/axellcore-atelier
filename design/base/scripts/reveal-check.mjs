import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
const S = process.env.HOME + '/.claude/skills/figma-auto-html-merge/scripts';
const req = createRequire(S + '/x.js');
const { chromium } = req('playwright-core');
const { launchOptions } = await import(pathToFileURL(S + '/browser.mjs').href);
const url = process.argv[2] ?? 'http://localhost:8906/atelier/';
const browser = await chromium.launch({ ...launchOptions(), args: [ '--disable-gpu' ] });

const count = (page) => page.evaluate(() => {
	const roots = [...document.querySelectorAll('[data-axell-reveal]')];
	const targets = roots.flatMap(r => r.getAttribute('data-axell-reveal') === 'items' ? [...r.children] : [r]);
	return {
		roots: roots.length,
		ready: roots.filter(r => r.classList.contains('is-ready')).length,
		targets: targets.length,
		inView: targets.filter(t => t.classList.contains('is-in')).length,
		hiddenNow: targets.filter(t => getComputedStyle(t).opacity === '0').length,
	};
});

async function run(label, opts) {
	const page = await browser.newPage({ viewport: { width: 1440, height: 900 }, ...opts });
	const errors = [];
	page.on('pageerror', e => errors.push(String(e)));
	await page.goto(url, { waitUntil: 'load' });
	await page.waitForTimeout(700);
	const top = await count(page);
	// scroll the whole page in steps so every target intersects
	const h = await page.evaluate(() => document.documentElement.scrollHeight);
	for (let y = 0; y < h; y += 500) { await page.evaluate(v => window.scrollTo(0, v), y); await page.waitForTimeout(120); }
	await page.waitForTimeout(1200);
	const end = await count(page);
	console.log(label, JSON.stringify({ atTop: top, afterScroll: end, pageErrors: errors }));
	await page.close();
}

await run('js-on', {});
await run('reduced-motion', { reducedMotion: 'reduce' });
await run('js-off', { javaScriptEnabled: false });
await browser.close();
