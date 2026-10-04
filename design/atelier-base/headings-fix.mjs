// Post 6 (/atelier): headings of level 4 become level 3 (heading-order audit). Saves through the editor.
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import { writeFileSync } from 'node:fs';
const SK = process.env.HOME + '/.claude/skills/figma-auto-html-merge/scripts';
const req = createRequire(SK + '/x.js');
const { chromium } = req('playwright-core');
const { launchOptions } = await import(pathToFileURL(SK + '/browser.mjs').href);
const browser = await chromium.launch({ ...launchOptions(), args: [ '--disable-gpu' ] });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await page.goto('http://localhost:8906/studio-auto-login?redirect_to=%2Fwp-admin%2Fpost.php%3Fpost%3D6%26action%3Dedit', { waitUntil: 'load' });
await page.waitForFunction(() => window.wp && wp.data && wp.data.select('core/block-editor') && wp.data.select('core/block-editor').getBlocks().length > 0, null, { timeout: 120000 });
const levels = () => page.evaluate(() => {
	const walk = (bs) => bs.flatMap((b) => [ b, ...walk(b.innerBlocks) ]);
	const count = {};
	walk(wp.data.select('core/block-editor').getBlocks()).filter((b) => b.name === 'core/heading').forEach((b) => { count[b.attributes.level] = (count[b.attributes.level] || 0) + 1; });
	return count;
});
console.log('before', JSON.stringify(await levels()));
const changed = await page.evaluate(async () => {
	const walk = (bs) => bs.flatMap((b) => [ b, ...walk(b.innerBlocks) ]);
	const be = wp.data.dispatch('core/block-editor');
	let n = 0;
	walk(wp.data.select('core/block-editor').getBlocks()).forEach((b) => {
		if (b.name === 'core/heading' && b.attributes.level === 4) { be.updateBlockAttributes(b.clientId, { level: 3 }); n++; }
	});
	await wp.data.dispatch('core/editor').savePost();
	return n;
});
console.log('changed', changed);
await page.waitForTimeout(2500);
const saved = await page.evaluate(async () => (await wp.apiFetch({ path: '/wp/v2/pages/6?context=edit' })).content.raw);
writeFileSync(process.env.TMPDIR + '/post6-headings.html', saved);
console.log('after  (db)', JSON.stringify((saved.match(/<!-- wp:heading {"level":\d}/g) || []).reduce((a, m) => { const l = m.match(/level":(\d)/)[1]; a[l] = (a[l] || 0) + 1; return a; }, {})));
await browser.close();
