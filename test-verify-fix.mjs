import { chromium } from 'playwright';

const browser = await chromium.launch({ headless: true, args: ['--no-sandbox', '--disable-gpu'] });
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();

const errors = [];
page.on('pageerror', e => errors.push(e.message));
page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });

// Login
await page.goto('http://127.0.0.1:8000/login', { waitUntil: 'networkidle' });
await page.fill('input[type=email]', 'mahmoud.hamed.shenawy@gmail.com');
await page.fill('input[type=password]', 'Test1234!');
await Promise.all([
  page.waitForNavigation({ waitUntil: 'networkidle', timeout: 20000 }).catch(() => {}),
  page.click('button[type=submit]'),
]);
await new Promise(r => setTimeout(r, 2000));
console.log('Logged in:', page.url());

// Navigate directly to the chat page (the bug URL)
await page.goto('http://127.0.0.1:8000/chats/9269d893-76ec-4d00-bb49-e6dc6e6614c9?page=1', {
  waitUntil: 'networkidle', timeout: 20000
});

// Wait for Vue to render
await new Promise(r => setTimeout(r, 5000));
console.log('Chat page URL:', page.url());

// Check if page rendered
const info = await page.evaluate(() => ({
  innerText: document.body.innerText.length,
  appLen: document.getElementById('app')?.innerHTML.length,
  hasContent: document.body.innerText.trim().length > 50,
}));
console.log('Page info:', info);
console.log('Errors:', errors.slice(0, 5).join('\n'));

await page.screenshot({ path: './test-artifacts/verify-fix-chat.png' });
console.log('Screenshot saved');

await browser.close();
