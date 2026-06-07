import { chromium } from 'playwright';
import path from 'path';
import fs from 'fs';

const BASE_URL = 'http://localhost:8000';
const EMAIL = 'mahmoud.hamed.shenawy@gmail.com';
const PASSWORD = 'Test@1234';
const SCREENSHOTS_DIR = path.join(process.cwd(), 'screenshots-flow-builder');

if (!fs.existsSync(SCREENSHOTS_DIR)) {
  fs.mkdirSync(SCREENSHOTS_DIR, { recursive: true });
}

let stepNum = 0;
async function shot(page, slug, label) {
  stepNum++;
  const num = String(stepNum).padStart(2, '0');
  const file = path.join(SCREENSHOTS_DIR, `${num}-${slug}.png`);
  await page.screenshot({ path: file, fullPage: false });
  console.log(`  📸 [${num}] ${label}`);
  return file;
}

async function pause(ms = 1200) {
  await new Promise(r => setTimeout(r, ms));
}

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox', '--disable-gpu'] });
  const ctx = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    locale: 'ar',
  });
  const page = await ctx.newPage();

  // ═══════════════════════════════════════════════
  // STEP 1 — صفحة تسجيل الدخول
  // ═══════════════════════════════════════════════
  console.log('\n━━━━ الخطوة 1: صفحة تسجيل الدخول ━━━━');
  await page.goto(`${BASE_URL}/login`);
  await page.waitForLoadState('networkidle');
  await pause();
  await shot(page, 'login-page', 'صفحة تسجيل الدخول الفارغة');

  // ═══════════════════════════════════════════════
  // STEP 2 — ملء بيانات الدخول
  // ═══════════════════════════════════════════════
  console.log('\n━━━━ الخطوة 2: ملء بيانات الدخول ━━━━');
  const emailSel = 'input[type="email"], input[name="email"]';
  const passSel  = 'input[type="password"], input[name="password"]';
  await page.locator(emailSel).first().fill(EMAIL);
  await page.locator(passSel).first().fill(PASSWORD);
  await pause(600);
  await shot(page, 'login-filled', 'البيانات مكتملة قبل الضغط على الدخول');

  // ═══════════════════════════════════════════════
  // STEP 3 — تسجيل الدخول
  // ═══════════════════════════════════════════════
  console.log('\n━━━━ الخطوة 3: الضغط على تسجيل الدخول ━━━━');
  await page.locator('button[type="submit"], input[type="submit"]').first().click();
  await page.waitForLoadState('networkidle');
  await pause(2500);
  console.log('  → URL بعد الدخول:', page.url());
  await shot(page, 'after-login', 'بعد تسجيل الدخول الناجح');

  // If we need to select organization
  if (page.url().includes('select-organization') || page.url().includes('organization')) {
    console.log('\n  → اختيار المؤسسة مطلوب');
    await shot(page, 'select-org', 'صفحة اختيار المؤسسة');
    const orgBtn = page.locator('button, a, [role="button"]').filter({ hasText: /مؤسسة|organization|select/i }).first();
    if (await orgBtn.isVisible().catch(() => false)) {
      await orgBtn.click();
      await page.waitForLoadState('networkidle');
      await pause(1500);
      console.log('  → URL بعد اختيار المؤسسة:', page.url());
    }
  }

  // ═══════════════════════════════════════════════
  // STEP 4 — الداشبورد
  // ═══════════════════════════════════════════════
  console.log('\n━━━━ الخطوة 4: الداشبورد ━━━━');
  await shot(page, 'dashboard', 'الداشبورد الرئيسي بعد الدخول');

  // ═══════════════════════════════════════════════
  // STEP 5 — التنقل لـ Automation
  // ═══════════════════════════════════════════════
  console.log('\n━━━━ الخطوة 5: صفحة الـ Flows ━━━━');
  await page.goto(`${BASE_URL}/automation/flows`);
  await page.waitForLoadState('networkidle');
  await pause(2500);
  console.log('  → URL:', page.url());
  await shot(page, 'flows-list-empty', 'صفحة قائمة الـ Automation Flows');

  if (!page.url().includes('/automation/flows')) {
    console.log('  ⚠️  تحويل! URL الحالي:', page.url());
    // Try to debug — take screenshot anyway
    await shot(page, 'unexpected-redirect', 'صفحة غير متوقعة بعد التحويل');
    await browser.close();
    process.exit(1);
  }

  // ═══════════════════════════════════════════════
  // STEP 6 — فتح نافذة إنشاء Flow
  // ═══════════════════════════════════════════════
  console.log('\n━━━━ الخطوة 6: إنشاء Flow جديد ━━━━');
  // Try multiple possible create button selectors
  const createCandidates = [
    'button:has-text("Create")',
    'button:has-text("إنشاء")',
    'button:has-text("New")',
    'button:has-text("جديد")',
    'a:has-text("Create")',
    '[class*="create"]',
    'button.primary',
    'button[class*="btn"]',
  ];
  let clicked = false;
  for (const sel of createCandidates) {
    const el = page.locator(sel).first();
    if (await el.isVisible({ timeout: 1000 }).catch(() => false)) {
      const txt = await el.textContent().catch(() => '');
      console.log(`  → زر الإنشاء: "${txt.trim()}" [${sel}]`);
      await el.click();
      clicked = true;
      break;
    }
  }
  if (!clicked) {
    console.log('  ⚠️  ما لقيتش زر إنشاء — هخد screenshot للصفحة');
    const btns = await page.locator('button').all();
    for (const b of btns) {
      const t = await b.textContent().catch(() => '');
      console.log('    Button:', t.trim().substring(0, 50));
    }
  }
  await pause(1500);
  await shot(page, 'create-flow-modal', 'نافذة إنشاء Flow جديد');

  // ═══════════════════════════════════════════════
  // STEP 7 — ملء اسم الـ Flow
  // ═══════════════════════════════════════════════
  console.log('\n━━━━ الخطوة 7: ملء اسم الـ Flow ━━━━');
  const nameSelectors = [
    'input[name="name"]',
    'input[placeholder*="name"]',
    'input[placeholder*="اسم"]',
    'input[placeholder*="Name"]',
    'dialog input[type="text"]',
    '[role="dialog"] input',
    'input[type="text"]:visible',
  ];
  let nameInput = null;
  for (const sel of nameSelectors) {
    const el = page.locator(sel).first();
    if (await el.isVisible({ timeout: 800 }).catch(() => false)) {
      nameInput = el;
      console.log('  → خانة الاسم:', sel);
      break;
    }
  }
  if (nameInput) {
    await nameInput.fill('رحلة ترحيب بالعملاء الجدد');
    await pause(600);
    await shot(page, 'create-flow-filled', 'النافذة مع اسم الـ Flow');

    // Submit
    const submitCandidates = [
      'button[type="submit"]:visible',
      'dialog button:has-text("Create")',
      'dialog button:has-text("إنشاء")',
      '[role="dialog"] button[type="submit"]',
      '[role="dialog"] button:last-of-type',
    ];
    for (const sel of submitCandidates) {
      const el = page.locator(sel).last();
      if (await el.isVisible({ timeout: 800 }).catch(() => false)) {
        const txt = await el.textContent().catch(() => '');
        console.log(`  → زر الإنشاء النهائي: "${txt.trim()}" [${sel}]`);
        await el.click();
        break;
      }
    }
    await page.waitForLoadState('networkidle');
    await pause(3500);
    console.log('  → URL بعد الإنشاء:', page.url());
  }

  // ═══════════════════════════════════════════════
  // STEP 8 — الـ Flow Builder Canvas
  // ═══════════════════════════════════════════════
  const isInBuilder = page.url().includes('/automation/flows/') && !page.url().endsWith('/flows');
  console.log('\n━━━━ الخطوة 8: الـ Flow Builder Canvas ━━━━');
  if (isInBuilder) {
    await page.waitForLoadState('networkidle');
    await pause(3000);
    await shot(page, 'builder-canvas-full', 'الـ Flow Builder Canvas بالكامل');

    // ═══════════════════════════════════════════════
    // STEP 9 — لوحة المكتبة
    // ═══════════════════════════════════════════════
    console.log('\n━━━━ الخطوة 9: مكتبة الخطوات ━━━━');
    await shot(page, 'builder-library-panel', 'مكتبة الخطوات (يسار)');

    // ═══════════════════════════════════════════════
    // STEP 10 — تحديد الـ Trigger Node
    // ═══════════════════════════════════════════════
    console.log('\n━━━━ الخطوة 10: تحديد الـ Trigger Node ━━━━');
    const triggerNode = page.locator('.vue-flow__node, [data-id="trigger-1"], [class*="canvas-node"]').first();
    if (await triggerNode.isVisible({ timeout: 2000 }).catch(() => false)) {
      await triggerNode.click();
      await pause(800);
      await shot(page, 'trigger-node-selected', 'الـ Trigger Node محددة');

      // Double click to open inline editor
      await triggerNode.dblclick();
      await pause(1200);
      await shot(page, 'trigger-inspector-open', 'Inspector الـ Trigger Node مفتوح');
    }

    // ═══════════════════════════════════════════════
    // STEP 11 — تاب Actions في المكتبة
    // ═══════════════════════════════════════════════
    console.log('\n━━━━ الخطوة 11: تاب Actions ━━━━');
    const actionsTab = page.locator('button:has-text("Actions"), button:has-text("أعمال")').first();
    if (await actionsTab.isVisible({ timeout: 1000 }).catch(() => false)) {
      await actionsTab.click();
      await pause(800);
      await shot(page, 'library-actions-tab', 'تاب Actions في المكتبة');
    }

    // ═══════════════════════════════════════════════
    // STEP 12 — المعاينة
    // ═══════════════════════════════════════════════
    console.log('\n━━━━ الخطوة 12: زر المعاينة ━━━━');
    const previewBtn = page.locator('button:has-text("Preview"), button:has-text("معاينة")').first();
    if (await previewBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
      await previewBtn.click();
      await pause(1500);
      await shot(page, 'preview-modal', 'نافذة معاينة الـ Flow');
      // Close it
      await page.keyboard.press('Escape');
      await pause(500);
    }

    // ═══════════════════════════════════════════════
    // STEP 13 — Publish button
    // ═══════════════════════════════════════════════
    console.log('\n━━━━ الخطوة 13: زر النشر ━━━━');
    await shot(page, 'publish-button-visible', 'زر النشر في الـ Header');
  } else {
    // Try navigating to an existing flow if we got redirected back to list
    await page.goto(`${BASE_URL}/automation/flows`);
    await page.waitForLoadState('networkidle');
    await pause(2000);
    await shot(page, 'flows-list-after-create', 'قائمة الـ Flows بعد الإنشاء');

    // Click first flow
    const firstFlow = page.locator('a[href*="/automation/flows/"], tr a').first();
    if (await firstFlow.isVisible({ timeout: 1000 }).catch(() => false)) {
      await firstFlow.click();
      await page.waitForLoadState('networkidle');
      await pause(3000);
      await shot(page, 'builder-canvas-via-list', 'الـ Builder عبر القائمة');
    }
  }

  // ═══════════════════════════════════════════════
  // STEP FINAL — القائمة النهائية للـ Flows
  // ═══════════════════════════════════════════════
  console.log('\n━━━━ الخطوة الأخيرة: قائمة الـ Flows مع البيانات ━━━━');
  await page.goto(`${BASE_URL}/automation/flows`);
  await page.waitForLoadState('networkidle');
  await pause(2000);
  await shot(page, 'flows-list-final', 'قائمة الـ Flows النهائية');

  await browser.close();

  console.log('\n\n📁 Screenshots في:', SCREENSHOTS_DIR);
  const files = fs.readdirSync(SCREENSHOTS_DIR).sort().filter(f => f.endsWith('.png'));
  console.log(`  عدد الـ Screenshots: ${files.length}`);
  files.forEach(f => console.log('  ✅', f));
})().catch(err => {
  console.error('\n❌ Error:', err.message);
  console.error(err.stack?.split('\n').slice(0,5).join('\n'));
  process.exit(1);
});
