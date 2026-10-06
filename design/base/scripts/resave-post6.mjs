// Re-saves /atelier (post 6) in the block editor so deprecated blocks (old form and notice markup)
// are migrated to the current save output. Reports invalid blocks before and after.
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
const SK = process.env.HOME + '/.claude/skills/figma-auto-html-merge/scripts';
const req = createRequire(SK + '/x.js');
const { chromium } = req('playwright-core');
const { launchOptions } = await import(pathToFileURL(SK + '/browser.mjs').href);
const browser = await chromium.launch({ ...launchOptions(), args: [ '--disable-gpu' ] });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
const errors = [];
page.on('pageerror', (e) => errors.push(String(e)));
await page.goto('http://localhost:8906/studio-auto-login?redirect_to=%2Fwp-admin%2Fpost.php%3Fpost%3D6%26action%3Dedit', { waitUntil: 'load' });
await page.waitForFunction(() => window.wp && wp.data && wp.data.select('core/block-editor') && wp.data.select('core/block-editor').getBlocks().length > 0, null, { timeout: 120000 });
await page.waitForTimeout(1500);
const check = () => page.evaluate(() => {
	const walk = (bs) => bs.flatMap((b) => [ b, ...walk(b.innerBlocks) ]);
	const all = walk(wp.data.select('core/block-editor').getBlocks());
	return { blocks: all.length, invalid: all.filter((b) => !b.isValid).map((b) => b.name) };
});
console.log('before:', JSON.stringify(await check()));
await page.evaluate(() => {
	const be = wp.data.dispatch('core/block-editor');
	be.resetBlocks(wp.data.select('core/block-editor').getBlocks());
});
await page.evaluate(async () => { await wp.data.dispatch('core/editor').savePost(); });
await page.waitForTimeout(2500);
console.log('after:', JSON.stringify(await check()));
const saved = await page.evaluate(async () => {
	const r = await wp.apiFetch({ path: '/wp/v2/pages/6?context=edit' });
	return { content: r.content.raw, errors: 0 };
});
const fs = await import('node:fs');
fs.writeFileSync(process.env.TMPDIR + '/post6-resaved.html', saved.content);
console.log('aa-notice left:', (saved.content.match(/aa-notice/g) || []).length, 'data-aa-club-form left:', (saved.content.match(/data-aa-club-form/g) || []).length, 'bytes:', saved.content.length);
console.log('pageErrors:', errors.slice(0, 3));
await browser.close();
