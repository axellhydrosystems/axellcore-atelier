// Submit path of /atelier/adesao: Interactivity store + REST, with the REST call mocked.
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
const SK = process.env.HOME + '/.claude/skills/figma-auto-html-merge/scripts';
const req = createRequire(SK + '/x.js');
const { chromium } = req('playwright-core');
const { launchOptions } = await import(pathToFileURL(SK + '/browser.mjs').href);
const browser = await chromium.launch({ ...launchOptions(), args: [ '--disable-gpu' ] });

async function fillRequired(page) {
	await page.evaluate(() => {
		document.querySelectorAll('form input[required], form select[required], form textarea[required]').forEach((el) => {
			if (el.tagName === 'SELECT') { el.selectedIndex = Math.min(1, el.options.length - 1); }
			else if (el.type === 'checkbox') { el.checked = true; }
			else if (el.type === 'email') { el.value = 'ana@exemplo.com.br'; }
			else if (el.type === 'tel') { el.value = '11900000000'; }
			else if (el.name === 'documento') { el.value = '52998224725'; }
			else { el.value = el.name === 'cep' ? '01001000' : 'Teste'; }
		});
	});
}

async function run(label, mock) {
	const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
	const page = await context.newPage();
	const errors = [];
	page.on('pageerror', (e) => errors.push(String(e)));
	const bodies = [];
	await page.route('**/wp-json/axellcore-atelier/v1/members', (route) => {
		bodies.push(route.request().postDataJSON());
		return route.fulfill(mock);
	});
	await page.goto('http://localhost:8906/atelier/adesao/', { waitUntil: 'load' });
	const form = page.locator('form[data-wp-interactive="axell/form"]');
	const notices = () => page.evaluate(() => [...document.querySelectorAll('[data-axell-notice-type]')].map((n) => `${n.getAttribute('data-axell-notice-type')}:${n.hidden ? 'hidden' : 'shown'}`));
	const beforeNotices = await notices();
	const attrs = await form.evaluate((f) => ({ action: f.getAttribute('action'), method: f.getAttribute('method'), ctx: f.getAttribute('data-wp-context') }));
	await fillRequired(page);
	await page.locator('form[data-wp-interactive="axell/form"] button[type="submit"]').click();
	await page.waitForTimeout(800);
	const after = await form.evaluate((f) => ({ ctx: f.getAttribute('data-wp-context'), emailValue: f.querySelector('input[name="email"]')?.value ?? null }));
	console.log(label, JSON.stringify({ beforeNotices, afterNotices: await notices(), attrs, after, requestWebsite: bodies[0]?.website, requestKeys: bodies[0] ? Object.keys(bodies[0]).length : 0, pageErrors: errors }));
	await context.close();
}

await run('success', { status: 200, contentType: 'application/json', body: JSON.stringify({ success: true, id: 1 }) });
await run('error', { status: 400, contentType: 'application/json', body: JSON.stringify({ code: 'aa_invalid_email', message: 'x' }) });
await browser.close();
