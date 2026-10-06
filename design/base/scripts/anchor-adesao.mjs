// Gives the apply section of /atelier (post 6) the anchor "adesao", so #adesao links land on it.
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
const SK = process.env.HOME + '/.claude/skills/figma-auto-html-merge/scripts';
const req = createRequire(SK + '/x.js');
const { chromium } = req('playwright-core');
const { launchOptions } = await import(pathToFileURL(SK + '/browser.mjs').href);
const browser = await chromium.launch({ ...launchOptions(), args: [ '--disable-gpu' ] });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await page.goto('http://localhost:8906/studio-auto-login?redirect_to=%2Fwp-admin%2Fpost.php%3Fpost%3D6%26action%3Dedit', { waitUntil: 'load' });
await page.waitForFunction(() => window.wp && wp.data && wp.data.select('core/block-editor') && wp.data.select('core/block-editor').getBlocks().length > 0, null, { timeout: 120000 });
const changed = await page.evaluate(async () => {
	const be = wp.data.select('core/block-editor');
	const target = be.getBlocks().find((b) => b.name === 'core/columns' && (b.attributes.className || '').includes('aa-apply'));
	if (!target) return 'not found';
	wp.data.dispatch('core/block-editor').updateBlockAttributes(target.clientId, { anchor: 'adesao' });
	await wp.data.dispatch('core/editor').savePost();
	return 'anchored';
});
console.log(changed);
await page.waitForTimeout(2500);
const content = await page.evaluate(async () => (await wp.apiFetch({ path: '/wp/v2/pages/6?context=edit' })).content.raw);
const fs = await import('node:fs');
fs.writeFileSync(process.env.TMPDIR + '/post6-anchor.html', content);
console.log('id="adesao" in content:', (content.match(/id="adesao"/g) || []).length);
await browser.close();
