// Builds the unstyled /atelier/adesao content inside the block editor (wp.blocks),
// so the saved markup is produced by the blocks' own save() functions.
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import { readFileSync, writeFileSync } from 'node:fs';
const SK = process.env.HOME + '/.claude/skills/figma-auto-html-merge/scripts';
const req = createRequire(SK + '/x.js');
const { chromium } = req('playwright-core');
const { launchOptions } = await import(pathToFileURL(SK + '/browser.mjs').href);
const [ treePath, outPath ] = process.argv.slice(2);
const tree = JSON.parse(readFileSync(treePath, 'utf8'));

const browser = await chromium.launch({ ...launchOptions(), args: [ '--disable-gpu' ] });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
const errors = [];
page.on('pageerror', e => errors.push(String(e)));
await page.goto('http://localhost:8906/studio-auto-login?redirect_to=%2Fwp-admin%2Fpost.php%3Fpost%3D6%26action%3Dedit', { waitUntil: 'load' });
await page.waitForFunction(() => window.wp && wp.blocks && wp.data && wp.data.select('core/block-editor') && wp.data.select('core/block-editor').getBlocks().length > 0, null, { timeout: 120000 });

const result = await page.evaluate((tree) => {
	const { createBlock, serialize, parse } = wp.blocks;
	const bare = (n) => n.replace(/^core\//, '');
	const build = (n) => {
		const kids = (n.children || []).map(build);
		switch (n.name) {
			case 'core/paragraph': return createBlock('core/paragraph', { content: n.content });
			case 'core/heading': return createBlock('core/heading', { content: n.content, level: n.attrs.level || 2 });
			case 'core/list': return createBlock('core/list', { ordered: !!n.attrs.ordered }, n.items.map(i => createBlock('core/list-item', { content: i })));
			case 'core/button': return createBlock('core/button', { text: n.text, tagName: n.attrs.tagName, type: n.attrs.type });
			case 'core/html': return createBlock('core/html', { content: n.content });
			default: return createBlock(n.name, n.attrs, kids);
		}
	};
	const blocks = [ build(tree) ];
	const content = serialize(blocks);
	// Re-parse and ask the editor's own validator, block by block.
	const walk = (bs) => bs.flatMap(b => [ b, ...walk(b.innerBlocks) ]);
	const parsed = walk(parse(content));
	return { content, blocks: parsed.length, invalid: parsed.filter(b => !b.isValid).map(b => b.name) };
}, tree);

await browser.close();
writeFileSync(outPath, result.content);
console.log(JSON.stringify({ blocks: result.blocks, invalid: result.invalid, bytes: result.content.length, pageErrors: errors.slice(0, 3) }, null, 2));
