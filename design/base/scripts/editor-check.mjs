import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
const S = process.env.HOME + '/.claude/skills/figma-auto-html-merge/scripts';
const req = createRequire(S + '/x.js');
const { chromium } = req('playwright-core');
const { launchOptions } = await import(pathToFileURL(S + '/browser.mjs').href);
const browser = await chromium.launch({ ...launchOptions(), args: [ '--disable-gpu' ] });
const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await context.newPage();
const errors = [];
page.on('pageerror', e => errors.push(String(e)));
await page.goto('http://localhost:8906/studio-auto-login?redirect_to=%2Fwp-admin%2Fpost.php%3Fpost%3D6%26action%3Dedit', { waitUntil: 'load' });
await page.waitForFunction(() => window.wp && wp.data && wp.data.select('core/block-editor') && wp.data.select('core/block-editor').getBlocks().length > 0, null, { timeout: 120000 });
await page.waitForTimeout(1500);
const report = await page.evaluate(() => {
	const be = wp.data.select('core/block-editor');
	const walk = (bs) => bs.flatMap(b => [b, ...walk(b.innerBlocks)]);
	const all = walk(be.getBlocks());
	const withReveal = all.filter(b => b.attributes.revealMode && b.attributes.revealMode !== 'none');
	const sample = withReveal.find(b => b.name === 'core/columns');
	return {
		blocks: all.length,
		invalid: all.filter(b => !b.isValid).map(b => b.name),
		revealAttributeSeen: withReveal.length,
		modes: withReveal.reduce((acc, b) => (acc[b.attributes.revealMode] = (acc[b.attributes.revealMode] || 0) + 1, acc), {}),
		sampleClientId: sample ? sample.clientId : null,
		hasControlFilter: !!wp.hooks && typeof wp.hooks.hasFilter === 'function' && wp.hooks.hasFilter('editor.BlockEdit', 'axellcore-atelierclub/reveal-control'),
	};
});
console.log(JSON.stringify({ ...report, errors: errors.slice(0, 3) }, null, 2));
if (report.sampleClientId) {
	await page.evaluate(id => wp.data.dispatch('core/block-editor').selectBlock(id), report.sampleClientId);
	await page.waitForTimeout(800);
	const panel = await page.getByText('Reveal on scroll', { exact: true }).count();
	console.log('panel visible for selected columns:', panel > 0);
}
await browser.close();
