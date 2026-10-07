// Capture a local HTML file with every .reveal forced to its final state (.in), no scrolling.
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
const SK = process.env.HOME + '/.claude/skills/figma-auto-html-merge/scripts';
const req = createRequire(SK + '/x.js');
const { chromium } = req('playwright-core');
const { launchOptions } = await import(pathToFileURL(SK + '/browser.mjs').href);
const [ file, width, out ] = process.argv.slice(2);
const browser = await chromium.launch({ ...launchOptions(), args: [ '--disable-gpu' ] });
const page = await browser.newPage({ viewport: { width: Number(width), height: 900 }, deviceScaleFactor: 1, reducedMotion: 'reduce' });
await page.goto(/^https?:/.test(file) ? file : pathToFileURL(file).href, { waitUntil: 'load' });
await page.evaluate(() => document.fonts.ready);
// Same procedure as the approved bases: reveals visible, no transitions (the approved bases were made this way).
await page.addStyleTag({ content: '.reveal{opacity:1!important;transform:none!important;transition:none!important}' });
await page.evaluate(() => document.querySelectorAll('.reveal').forEach((e) => e.classList.add('in')));
await page.waitForTimeout(1500);
await page.screenshot({ path: out, fullPage: true, animations: 'disabled' });
await browser.close();
console.log(out);
