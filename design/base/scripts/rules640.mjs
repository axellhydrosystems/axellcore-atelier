import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
const S = process.env.HOME + '/.claude/skills/figma-auto-html-merge/scripts';
const { chromium } = createRequire(S + '/x.js')('playwright-core');
const { launchOptions } = await import(pathToFileURL(S + '/browser.mjs').href);
const browser = await chromium.launch({ ...launchOptions(), args: [ '--disable-gpu' ] });
const page = await browser.newPage({ viewport: { width: 640, height: 900 } });
await page.goto(process.argv[2], { waitUntil: 'load' });
const out = await page.evaluate(() => {
  const hits = [];
  for (const sh of document.styleSheets) { let rules; try { rules = sh.cssRules; } catch (e) { continue; }
    const walk = (list, media) => { for (const r of list) { if (r.cssRules && r.media) walk(r.cssRules, r.media.mediaText); else if (r.selectorText && r.selectorText.includes('aa-pillar-row') && r.selectorText.includes('> .wp-block-column')) hits.push([media || '', r.selectorText, r.style.cssText]); } };
    walk(rules, ''); }
  const el = document.querySelector('.aa-pillar-row > .wp-block-column');
  return { hits, matchesSelector: el ? el.matches('.aa-pillar-row.wp-block-columns.aa-pillar-row > .wp-block-column') : null, colClass: el ? el.className : null, parentClass: el ? el.parentElement.className : null };
});
console.log(JSON.stringify(out, null, 1));
await browser.close();
