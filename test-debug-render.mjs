import { chromium } from 'playwright';

const browser = await chromium.launch({
  headless: true,
  args: ['--no-sandbox','--disable-gpu','--disable-software-rasterizer','--force-color-profile=srgb']
});
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();

// Capture ALL console messages
const logs = [];
page.on('console', msg => logs.push(`[${msg.type()}] ${msg.text()}`));
page.on('pageerror', err => logs.push(`[PAGEERROR] ${err.message}`));
page.on('requestfailed', req => logs.push(`[REQFAIL] ${req.url()}`));

// Login
await page.goto('http://127.0.0.1:8000/login', { waitUntil: 'load' });
await page.fill('input[type=email]', 'mahmoud.hamed.shenawy@gmail.com');
await page.fill('input[type=password]', 'Test1234!');
await page.click('button[type=submit]');
await new Promise(r => setTimeout(r, 4000));
console.log('URL after login:', page.url());

// Navigate to chats/{uuid}?page=1
await page.goto('http://127.0.0.1:8000/chats/9269d893-76ec-4d00-bb49-e6dc6e6614c9?page=1', { waitUntil: 'load' });

// Wait up to 10 seconds for any change
let rendered = false;
for (let i = 0; i < 20; i++) {
  await new Promise(r => setTimeout(r, 500));
  const bodyLen = await page.evaluate(() => document.body.innerHTML.length);
  const visibleText = await page.evaluate(() => document.body.innerText.trim().length);
  if (i % 4 === 0) console.log(`[${i/2}s] bodyLen=${bodyLen} visibleText=${visibleText}`);
  if (visibleText > 100) { rendered = true; break; }
}

console.log('Rendered:', rendered);
console.log('Final URL:', page.url());
await page.screenshot({ path: './test-artifacts/render-01.png' });

// Check what the body has
const info = await page.evaluate(() => {
  return {
    innerTextLen: document.body.innerText.length,
    appInnerHTML: document.getElementById('app')?.innerHTML.substring(0, 300),
    allDivs: Array.from(document.querySelectorAll('div')).length,
    visibleEls: Array.from(document.querySelectorAll('*')).filter(e => {
      const r = e.getBoundingClientRect();
      return r.width > 0 && r.height > 0 && r.top >= 0 && r.top < 900;
    }).length,
  };
});
console.log('Page info:', JSON.stringify(info, null, 2));
console.log('Console errors:', logs.filter(l => l.includes('error') || l.includes('FAIL') || l.includes('Error')).slice(0, 10));

await browser.close();
