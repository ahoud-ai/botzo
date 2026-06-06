import { chromium } from 'playwright';

const browser = await chromium.launch({
  headless: true,
  args: ['--no-sandbox', '--disable-gpu', '--force-color-profile=srgb']
});
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();

// Login
await page.goto('http://127.0.0.1:8000/login', { waitUntil: 'networkidle' });
await page.fill('input[type=email]', 'mahmoud.hamed.shenawy@gmail.com');
await page.fill('input[type=password]', 'Test1234!');
await Promise.all([
  page.waitForNavigation({ waitUntil: 'networkidle', timeout: 20000 }).catch(()=>{}),
  page.click('button[type=submit]'),
]);
await new Promise(r => setTimeout(r, 2000));
console.log('Logged in:', page.url());
await page.screenshot({ path: './test-artifacts/d-01-dashboard.png' });

// Navigate to chats
await page.goto('http://127.0.0.1:8000/chats', { waitUntil: 'networkidle', timeout: 20000 });
await new Promise(r => setTimeout(r, 3000));
console.log('Chats URL:', page.url());
await page.screenshot({ path: './test-artifacts/d-02-chats.png' });

// Check rendered elements
const info = await page.evaluate(() => {
  const els = Array.from(document.querySelectorAll('[class*=chat],[class*=Chat],[class*=message],[class*=contact],[class*=Contact]'));
  return els.slice(0,10).map(e => {
    const r = e.getBoundingClientRect();
    return e.tagName + ' w=' + Math.round(r.width) + ' h=' + Math.round(r.height) + ' text=' + e.textContent.trim().substring(0,20);
  });
});
console.log('Chat elements:', info);

// Try the specific contact
await page.goto('http://127.0.0.1:8000/chats/9269d893-76ec-4d00-bb49-e6dc6e6614c9', { waitUntil: 'networkidle', timeout: 20000 });
await new Promise(r => setTimeout(r, 4000));
await page.screenshot({ path: './test-artifacts/d-03-contact.png' });
console.log('Contact page:', page.url());

const bodyHTML = await page.evaluate(() => document.body.innerHTML.substring(0, 500));
console.log('Body HTML preview:', bodyHTML.replace(/\n/g, ' '));

await browser.close();
