// Capture one element of a local HTML file on its own (e.g. the footer of
// source/footer/), with the same procedure as shot-file-forced.mjs (reveals
// forced visible, no transitions): the shot is the element's box, independent
// of the page and body height, so it needs no crop.
// Usage: node shot-file-element.mjs <file.html | URL> <width> <selector> <out.png>
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
const SK = process.env.HOME + '/.claude/skills/figma-auto-html-merge/scripts';
const req = createRequire(SK + '/x.js');
const { chromium } = req('playwright-core');
const { launchOptions } = await import(pathToFileURL(SK + '/browser.mjs').href);
const [ file, width, selector, out ] = process.argv.slice(2);
const browser = await chromium.launch({ ...launchOptions(), args: [ '--disable-gpu' ] });
const page = await browser.newPage({ viewport: { width: Number(width), height: 900 }, deviceScaleFactor: 1, reducedMotion: 'reduce' });
await page.goto(/^https?:/.test(file) ? file : pathToFileURL(file).href, { waitUntil: 'load' });
await page.evaluate(() => document.fonts.ready);
await page.addStyleTag({ content: '.reveal,.axell-reveal,.axell-reveal>*{opacity:1!important;transform:none!important;transition:none!important}' });
await page.evaluate(() => document.querySelectorAll('.reveal').forEach((e) => e.classList.add('in')));
await page.waitForTimeout(1500);
const box = await page.locator(selector).first().boundingBox();
await page.locator(selector).first().screenshot({ path: out, animations: 'disabled' });
await browser.close();
console.log(out, Math.round(box.width) + 'x' + box.height);
