import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
const SK = process.env.HOME + '/.claude/skills/figma-auto-html-merge/scripts';
const req = createRequire(SK + '/x.js');
const { chromium } = req('playwright-core');
const { launchOptions } = await import(pathToFileURL(SK + '/browser.mjs').href);
const browser = await chromium.launch({ ...launchOptions(), args: [ '--disable-gpu' ] });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1 });
await page.route('**/wp-json/axellcore-atelierclub/v1/members', (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ success: true, id: 1 }) }));
await page.goto('http://localhost:8906/atelier/', { waitUntil: 'load' });
await page.evaluate(() => document.fonts.ready);
const form = page.locator('form[data-wp-interactive="axell/form"]').first();
await form.scrollIntoViewIfNeeded();
await page.evaluate(() => {
	document.querySelectorAll('form[data-wp-interactive="axell/form"] input[required], form[data-wp-interactive="axell/form"] select[required]').forEach((el) => {
		if (el.tagName === 'SELECT') el.selectedIndex = 1; else if (el.type === 'checkbox') el.checked = true;
		else if (el.type === 'email') el.value = 'ana@exemplo.com.br'; else if (el.type === 'tel') el.value = '11900000000';
		else if (el.name === 'documento') el.value = '52998224725'; else el.value = el.name === 'cep' ? '01001000' : 'Teste';
	});
});
await page.locator('form[data-wp-interactive="axell/form"] button[type="submit"]').first().click();
await page.waitForTimeout(1200);
const notice = page.locator('[data-axell-notice-type="success"]').first();
await notice.scrollIntoViewIfNeeded();
const box = await notice.boundingBox();
console.log('success notice box:', JSON.stringify(box), 'anchor #adesao exists:', await page.locator('#adesao').count());
await page.screenshot({ path: process.env.TMPDIR + '/landing-notice.png', clip: { x: Math.max(0, box.x - 40), y: Math.max(0, box.y - 120), width: Math.min(900, box.width + 80), height: box.height + 160 } });
console.log('shot ok');
await browser.close();
