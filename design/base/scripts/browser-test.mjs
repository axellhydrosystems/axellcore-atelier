// Visual pass over the adesao form: success and error notices at desktop and mobile widths,
// plus one real (unmocked) submission to see the live server answer.
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
const SK = process.env.HOME + '/.claude/skills/figma-auto-html-merge/scripts';
const req = createRequire(SK + '/x.js');
const { chromium } = req('playwright-core');
const { launchOptions } = await import(pathToFileURL(SK + '/browser.mjs').href);
const out = process.env.TMPDIR + '/';
const browser = await chromium.launch({ ...launchOptions(), args: [ '--disable-gpu' ] });
const base = 'http://localhost:8906/atelier/adesao/';

async function fillRequired(page) {
	await page.evaluate(() => {
		document.querySelectorAll('form input[required], form select[required]').forEach((el) => {
			if (el.tagName === 'SELECT') el.selectedIndex = Math.min(1, el.options.length - 1);
			else if (el.type === 'checkbox') el.checked = true;
			else if (el.type === 'email') el.value = 'ana@exemplo.com.br';
			else if (el.type === 'tel') el.value = '11900000000';
			else if (el.name === 'documento') el.value = '52998224725';
			else el.value = el.name === 'cep' ? '01001000' : 'Teste';
		});
	});
}

async function scenario(name, width, mock) {
	const context = await browser.newContext({ viewport: { width, height: 900 }, deviceScaleFactor: 1 });
	const page = await context.newPage();
	const errors = [];
	page.on('pageerror', (e) => errors.push(String(e)));
	if (mock) {
		await page.route('**/wp-json/axellcore-atelierclub/v1/members', (route) => route.fulfill(mock));
	}
	await page.goto(base, { waitUntil: 'load' });
	await fillRequired(page);
	await page.locator('form[data-wp-interactive="axell/form"] button[type="submit"]').click();
	await page.waitForTimeout(1200);
	const form = page.locator('form[data-wp-interactive="axell/form"]');
	const notices = await page.evaluate(() => [...document.querySelectorAll('[data-axell-notice-type]')].map((n) => `${n.getAttribute('data-axell-notice-type')}:${n.hidden ? 'hidden' : 'shown'}`));
	const file = `${out}browser-${name}-${width}.png`;
	await form.screenshot({ path: file, animations: 'disabled' });
	console.log(name, width, JSON.stringify({ notices, emailAfter: await page.locator('input[name="email"]').inputValue(), pageErrors: errors }), file);
	await context.close();
}

for (const width of [ 1440, 390 ]) {
	await scenario('success', width, { status: 200, contentType: 'application/json', body: JSON.stringify({ success: true, id: 1 }) });
	await scenario('error', width, { status: 400, contentType: 'application/json', body: JSON.stringify({ code: 'aa_invalid_email', message: 'x' }) });
}
await scenario('live', 1440, null);
await browser.close();
