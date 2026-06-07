import { chromium } from 'playwright';

const browser = await chromium.launch({ headless: true, args: ['--no-sandbox','--disable-gpu'] });
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

// Navigate to chats list
await page.goto('http://127.0.0.1:8000/chats', { waitUntil: 'networkidle' });
await new Promise(r => setTimeout(r, 3000));
console.log('Chats list:', page.url());
await page.screenshot({ path: './test-artifacts/click-01-list.png' });

// Find all clickable items in the list
const items = await page.evaluate(() => {
  const all = Array.from(document.querySelectorAll('a, [onclick], [class*="cursor-pointer"]'));
  return all.slice(0,20).map(e => ({
    tag: e.tagName,
    href: e.href || '',
    cls: e.className.substring(0,50),
    text: e.textContent.trim().substring(0,30),
  }));
});
console.log('Clickable items:', JSON.stringify(items, null, 2));

// Find the contact link
const contactLink = items.find(i => i.href && i.href.includes('/chats/'));
if (contactLink) {
  console.log('Found contact link:', contactLink.href);
  await page.goto(contactLink.href, { waitUntil: 'networkidle', timeout: 20000 });
  await new Promise(r => setTimeout(r, 5000));
  console.log('Contact URL:', page.url());
  await page.screenshot({ path: './test-artifacts/click-02-contact.png' });
} else {
  // Click on أحمد
  const ahmed = page.locator('text=أحمد محمود').first();
  if (await ahmed.isVisible({ timeout: 3000 }).catch(()=>false)) {
    // Listen for navigation
    const navPromise = page.waitForNavigation({ waitUntil: 'networkidle', timeout: 15000 }).catch(()=>{});
    await ahmed.click({ force: true });
    await navPromise;
    await new Promise(r => setTimeout(r, 4000));
    console.log('After click URL:', page.url());
    await page.screenshot({ path: './test-artifacts/click-02-contact.png' });
  }
}

await browser.close();
