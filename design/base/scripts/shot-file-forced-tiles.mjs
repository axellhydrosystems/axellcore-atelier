// Capture a local HTML file like shot-file-forced.mjs (every .reveal forced to
// its final state, no scrolling, no transitions), but in tiles of 8000 px
// stitched together: a single full-page capture goes blank past ~16384 px.
// Usage: node shot-file-forced-tiles.mjs <file.html> <width> <out.png>
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import { execFileSync } from 'node:child_process';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
const SK = process.env.HOME + '/.claude/skills/figma-auto-html-merge/scripts';
const req = createRequire(SK + '/x.js');
const { chromium } = req('playwright-core');
const { launchOptions } = await import(pathToFileURL(SK + '/browser.mjs').href);
const [ file, width, out ] = process.argv.slice(2);
const TILE = 8000;
const browser = await chromium.launch({ ...launchOptions(), args: [ '--disable-gpu' ] });
const page = await browser.newPage({ viewport: { width: Number(width), height: 900 }, deviceScaleFactor: 1 });
await page.goto(pathToFileURL(file).href, { waitUntil: 'load' });
await page.evaluate(() => document.fonts.ready);
await page.addStyleTag({ content: '.reveal{opacity:1!important;transform:none!important;transition:none!important}' });
await page.evaluate(() => document.querySelectorAll('.reveal').forEach((e) => e.classList.add('in')));
await page.waitForTimeout(1500);
const height = await page.evaluate(() => Math.ceil(document.documentElement.scrollHeight));
const dir = mkdtempSync(join(tmpdir(), 'tiles-'));
const tiles = [];
for (let y = 0; y < height; y += TILE) {
	const path = join(dir, `${tiles.length}.png`);
	await page.screenshot({ path, fullPage: true, animations: 'disabled', clip: { x: 0, y, width: Number(width), height: Math.min(TILE, height - y) } });
	tiles.push(path);
}
await browser.close();
execFileSync('python3', [ '-c', `
import sys
from PIL import Image
ims = [Image.open(p) for p in sys.argv[2:]]
out = Image.new('RGB', (ims[0].width, sum(i.height for i in ims)))
y = 0
for i in ims:
    out.paste(i.convert('RGB'), (0, y)); y += i.height
out.save(sys.argv[1])
`, out, ...tiles ]);
rmSync(dir, { recursive: true });
console.log(out, height);
