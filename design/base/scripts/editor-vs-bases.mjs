// The block editor canvas of /atelier, section by section, against the bases:
// each section's rows of design/base/<bp>.png (same fractional offsets as on
// the page), the hero against design/base/hero/<bp>.png (the page's fixed
// header covers its top). Editor in full screen, no sidebar, header, notices
// or meta boxes, the viewport as tall as the canvas and the cover's 100vh
// fixed at 900px (the bases' viewport height). SECTION_BASES=1 compares with
// the per-section bases instead.
// Usage: node editor-vs-bases.mjs <site> <post-id> <1440|768|390> <outdir> [section]
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import { mkdirSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
const SK = process.env.HOME + '/.claude/skills/figma-auto-html-merge/scripts';
const { chromium } = createRequire(SK + '/x.js')('playwright-core');
const { launchOptions } = await import(pathToFileURL(SK + '/browser.mjs').href);
const [site, post, width, out, only] = process.argv.slice(2);
const BASE = dirname(dirname(fileURLToPath(import.meta.url)));
const bp = { 1440: 'desktop', 768: 'tablet', 390: 'mobile' }[width];
const NAMES = ['hero','convite','manifesto','placa','protagonistas','promessas','conceito','jornada','niveis','beneficios','editorial','cta-strip','adesao'];
mkdirSync(out, { recursive: true });
const browser = await chromium.launch({ ...launchOptions(), args: ['--disable-gpu'] });
const page = await browser.newPage({ viewport: { width: +width, height: 900 }, deviceScaleFactor: 1, reducedMotion: 'reduce' });
const editor = `${site}/wp-admin/post.php?post=${post}&action=edit`;
await page.goto(`${site}/studio-auto-login?redirect_to=${encodeURIComponent(editor)}`, { waitUntil: 'load' });
await page.waitForFunction(() => window.wp?.data?.select('core/editor')?.getCurrentPostId(), null, { timeout: 90000 });
await page.evaluate(() => {
  const { dispatch, select } = window.wp.data;
  dispatch('core/edit-post')?.closeGeneralSidebar?.();
  dispatch('core/interface')?.disableComplementaryArea?.('core');
  dispatch('core/editor')?.setIsListViewOpened?.(false);
  dispatch('core/preferences')?.set('core', 'welcomeGuide', false);
  dispatch('core/preferences')?.set('core', 'fullscreenMode', true);
  select('core/notices').getNotices().forEach((n) => dispatch('core/notices').removeNotice(n.id));
  dispatch('core/block-editor').clearSelectedBlock();
  document.querySelectorAll('.editor-header, .edit-post-header').forEach((h) => { h.style.visibility = 'hidden'; });
  const st = document.createElement('style');
  st.textContent = '#wpadminbar,.edit-post-meta-boxes-main,.edit-post-layout__metaboxes,.interface-interface-skeleton__footer{display:none!important}html.wp-toolbar{padding-top:0!important}.interface-interface-skeleton{top:0!important}';
  document.head.appendChild(st);
});
const frame = page.frameLocator('iframe[name="editor-canvas"]');
await frame.locator('.is-root-container').first().waitFor({ timeout: 90000 });
await page.waitForTimeout(2500);
const canvas = page.frame({ name: 'editor-canvas' });
await canvas.addStyleTag({ content: '.reveal,.axell-reveal,.axell-reveal>*{opacity:1!important;transform:none!important;transition:none!important}*{caret-color:transparent!important}html{scrollbar-width:none}::-webkit-scrollbar{display:none}.is-root-container>.wp-block-cover[style*="100vh"]{min-height:900px!important}' });
const info = await canvas.evaluate(async () => {
  document.querySelector('.editor-visual-editor__post-title-wrapper')?.remove();
  document.querySelectorAll('img').forEach((img) => { img.loading = 'eager'; img.decoding = 'sync'; });
  await document.fonts.ready;
  await Promise.all([...document.images].map((img) => (img.complete ? Promise.resolve() : new Promise((ok) => img.addEventListener('load', ok, { once: true }))).then(() => img.decode()).catch(() => 0)));
  return { canvasW: document.documentElement.clientWidth };
});
await page.waitForTimeout(800);
const tall = await canvas.evaluate(() => document.documentElement.scrollHeight);
await page.setViewportSize({ width: +width, height: tall + 400 });
await page.waitForTimeout(1000);
const blocks = frame.locator('.is-root-container').first().locator(':scope > .wp-block');
const n = await blocks.count();
console.log(`canvas ${info.canvasW}px, ${n} blocks`);
const meta = await canvas.evaluate(() => [...document.querySelector('.is-root-container').children].filter((e) => e.classList.contains('wp-block')).map((e) => ({ type: e.getAttribute('data-type'), id: e.id, label: e.getAttribute('aria-label'), h: Math.round(e.getBoundingClientRect().height) })));
console.log(JSON.stringify(meta.map((m) => `${m.type} ${m.label} ${m.h}`)));
for (let i = 0; i < n; i++) {
  const name = NAMES[i]; if (only && only !== name) continue;
  const file = `${out}/${name}-${bp}.png`;
  const rootBox = await frame.locator('.is-root-container').first().boundingBox();
  const baseH = +execFileSync('python3', ['-c', `from PIL import Image;print(Image.open('${BASE}/${name}/${bp}.png').size[1])`], { encoding: 'utf8' }).trim();
  const box = await blocks.nth(i).boundingBox();
  await page.screenshot({ path: file, clip: { x: Math.round(box.x), y: Math.round(box.y), width: Math.round(box.width), height: (name === 'hero' || process.env.SECTION_BASES) && Math.abs(Math.round(box.height) - baseH) <= 1 ? baseH : Math.round(box.height) }, animations: 'disabled' });
  let r = '';
  // Reference: the section's own base for the hero (the page's fixed header covers its top),
  // else the same rows of the whole page's base, at the same fractional offsets as in the editor.
  let ref = `${BASE}/${name}/${bp}.png`;
  if (name !== 'hero' && !process.env.SECTION_BASES) {
    ref = `${out}/ref-${name}-${bp}.png`;
    const top = Math.round(box.y) - Math.round(rootBox.y);
    const h = Math.round(box.height);
    execFileSync('python3', ['-c', `from PIL import Image;Image.MAX_IMAGE_PIXELS=None;im=Image.open('${BASE}/${bp}.png');im.crop((0,${top},im.size[0],${top}+${h})).save('${ref}')`]);
  }
  try { r = execFileSync('python3', [SK + '/compare.py', ref, file, `${out}/cmp-${name}-${bp}.png`], { encoding: 'utf8' }); } catch (e) { r = e.stdout || String(e); }
  const lines = r.split('\n');
  console.log(name.padEnd(14), (lines[0] || '').replace('sizes reference ', 'ref ').replace('candidate ', 'ed ').padEnd(34), (lines[1] || '').trim().padEnd(30), (lines[2] || '').trim());
}
await browser.close();
