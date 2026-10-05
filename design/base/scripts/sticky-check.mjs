import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
const S = process.env.HOME + '/.claude/skills/figma-auto-html-merge/scripts';
const req = createRequire(S + '/x.js');
const { chromium } = req('playwright-core');
const { launchOptions } = await import(pathToFileURL(S + '/browser.mjs').href);

const url = process.argv[2] ?? 'http://localhost:8906/atelier/';
const browser = await chromium.launch({ ...launchOptions(), args: [ '--disable-gpu' ] });

for ( const width of [ 1440, 390 ] ) {
	const page = await browser.newPage({ viewport: { width, height: 900 }, deviceScaleFactor: 1 });
	const errors = [];
	page.on('pageerror', e => errors.push(String(e)));
	await page.goto(url, { waitUntil: 'load' });
	await page.waitForTimeout(500);
	const read = () => page.evaluate(() => {
		const h = document.querySelector('.wp-block-axell-sticky-header');
		if ( ! h ) return null;
		const cs = getComputedStyle(h);
		return {
			tag: h.tagName,
			interactive: h.getAttribute('data-wp-interactive'),
			classes: h.className,
			position: cs.position,
			top: h.getBoundingClientRect().top,
			paddingTop: cs.paddingTop,
			background: cs.backgroundColor,
			scrollY: window.scrollY,
		};
	});
	const top = await read();
	await page.evaluate(() => window.scrollTo(0, 200));
	await page.waitForTimeout(700);
	const scrolled = await read();
	await page.evaluate(() => window.scrollTo(0, 0));
	await page.waitForTimeout(700);
	const back = await read();
	console.log( JSON.stringify( { width, top, scrolled, back, pageErrors: errors }, null, 2 ) );
	await page.close();
}
await browser.close();
