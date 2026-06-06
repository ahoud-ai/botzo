import { chromium } from 'playwright';

const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();

await page.goto('http://127.0.0.1:8000/login', { waitUntil: 'networkidle' });
await page.fill('input[type=email]', 'mahmoud.hamed.shenawy@gmail.com');
await page.fill('input[type=password]', 'Test1234!');
await Promise.all([
  page.waitForNavigation({ waitUntil: 'networkidle', timeout: 20000 }).catch(() => {}),
  page.click('button[type=submit]'),
]);
await new Promise(r => setTimeout(r, 2000));

await page.goto('http://127.0.0.1:8000/chats/9269d893-76ec-4d00-bb49-e6dc6e6614c9?page=1', { waitUntil: 'load' });
await new Promise(r => setTimeout(r, 1000));

const inertiaData = await page.evaluate(() => {
  const app = document.getElementById('app');
  if (!app?.dataset?.page) return 'NO DATA-PAGE';
  const parsed = JSON.parse(app.dataset.page);
  const props = parsed.props;
  return {
    component: parsed.component,
    // Show raw contact (first 500 chars of JSON)
    contact_raw: JSON.stringify(props?.contact).substring(0, 500),
    contact_keys: props?.contact ? Object.keys(props.contact) : 'NO CONTACT PROP',
    rows_type: Array.isArray(props?.rows) ? 'Array' : typeof props?.rows,
    rows_keys: props?.rows && !Array.isArray(props.rows) ? Object.keys(props.rows) : 'is array',
    chatThread_len: props?.chatThread?.length,
    has_contact_prop: 'contact' in (props || {}),
  };
});
console.log(JSON.stringify(inertiaData, null, 2));

await browser.close();
