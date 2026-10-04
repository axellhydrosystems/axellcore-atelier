// Builds every unstyled child page of /atelier inside the block editor (wp.blocks),
// so the saved markup comes from the blocks' own save() functions.
// Usage: node pages-build.mjs <pages.json> <outDir>
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
const SK = process.env.HOME + '/.claude/skills/figma-auto-html-merge/scripts';
const req = createRequire(SK + '/x.js');
const { chromium } = req('playwright-core');
const { launchOptions } = await import(pathToFileURL(SK + '/browser.mjs').href);
const [ pagesPath, outDir ] = process.argv.slice(2);
const pages = JSON.parse(readFileSync(pagesPath, 'utf8'));
mkdirSync(outDir, { recursive: true });

const browser = await chromium.launch({ ...launchOptions(), args: [ '--disable-gpu' ] });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
const errors = [];
page.on('pageerror', e => errors.push(String(e)));
await page.goto('http://localhost:8906/studio-auto-login?redirect_to=%2Fwp-admin%2Fpost.php%3Fpost%3D6%26action%3Dedit', { waitUntil: 'load' });
await page.waitForFunction(() => window.wp && wp.blocks && wp.data && wp.data.select('core/block-editor') && wp.data.select('core/block-editor').getBlocks().length > 0, null, { timeout: 120000 });

const results = await page.evaluate((pages) => {
	const { createBlock, serialize, parse } = wp.blocks;
	const build = (n) => {
		const kids = (n.children || []).map(build);
		switch (n.name) {
			case 'core/paragraph': return createBlock('core/paragraph', { content: n.content });
			case 'core/heading': return createBlock('core/heading', { content: n.content, level: n.attrs.level || 2 });
			case 'core/list': return createBlock('core/list', { ordered: !!n.attrs.ordered }, n.items.map(i => createBlock('core/list-item', { content: i })));
			case 'core/button': return createBlock('core/button', { text: n.text, url: n.attrs.url, tagName: n.attrs.tagName, type: n.attrs.type });
			case 'core/html': return createBlock('core/html', { content: n.content });
			case 'axellcore/chapter': return createBlock('core/group', {}, kids);
			default: return createBlock(n.name, n.attrs, kids);
		}
	};
	const walk = (bs) => bs.flatMap(b => [ b, ...walk(b.innerBlocks) ]);
	return pages.map(p => {
		const content = serialize([ build(p.tree) ]);
		const parsed = walk(parse(content));
		return { slug: p.slug, content, blocks: parsed.length, invalid: parsed.filter(b => !b.isValid).map(b => b.name) };
	});
}, pages);

await browser.close();
for (const r of results) writeFileSync(`${outDir}/${r.slug}.html`, r.content);
console.log(JSON.stringify(results.map(({ slug, blocks, invalid }) => ({ slug, blocks, invalid })), null, 1));
console.log('pageErrors:', errors.slice(0, 3));
