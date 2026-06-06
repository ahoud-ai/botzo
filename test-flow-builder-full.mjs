/**
 * test-flow-builder-full.mjs
 * - تسجيل الدخول
 * - حذف كل الفلوز
 * - إنشاء فلو جديد
 * - استعراض Builder
 * - Validate → Preview → Publish
 * - تسجيل فيديو .webm
 */

import { chromium } from 'playwright';
import path from 'path';
import fs from 'fs';

const BASE   = 'http://localhost:8000';
const EMAIL  = 'mahmoud.hamed.shenawy@gmail.com';
const PASS   = 'Password@2026';
const OUTDIR = path.join(process.cwd(), 'recordings');

if (!fs.existsSync(OUTDIR)) fs.mkdirSync(OUTDIR, { recursive: true });

const sleep = ms => new Promise(r => setTimeout(r, ms));
const log   = msg => console.log(`[${new Date().toISOString().slice(11,19)}] ${msg}`);

(async () => {
  log('🚀 بدء التسجيل');

  const browser = await chromium.launch({
    headless: false,
    args: ['--start-maximized', '--no-sandbox'],
    slowMo: 60,
  });

  const ctx = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    recordVideo: { dir: OUTDIR, size: { width: 1440, height: 900 } },
  });

  const page = await ctx.newPage();

  /* ═══════════════════════════════
   * 1. تسجيل الدخول
   * ═══════════════════════════════ */
  log('1️⃣  تسجيل الدخول...');
  await page.goto(`${BASE}/login`);
  await page.waitForLoadState('networkidle');
  await sleep(1500);

  await page.fill('input[type=email]', EMAIL);
  await page.fill('input[type=password]', PASS);
  await page.press('input[type=password]', 'Enter');

  // انتظر كل الـ redirects (dashboard?refresh_lang=1 → dashboard)
  await sleep(7000);

  if (page.url().includes('/login')) {
    log('❌ فشل تسجيل الدخول'); await ctx.close(); await browser.close(); process.exit(1);
  }
  log(`  ✅ URL: ${page.url()}`);

  /* ═══════════════════════════════
   * 2. صفحة الفلوز
   * ═══════════════════════════════ */
  log('2️⃣  صفحة Flow Builder...');
  await page.goto(`${BASE}/automation/flows`, { waitUntil: 'domcontentloaded' });
  await sleep(4000);
  log(`  URL: ${page.url()}`);

  /* ═══════════════════════════════
   * 3. حذف كل الفلوز
   * ═══════════════════════════════ */
  log('3️⃣  حذف الفلوز...');

  for (let round = 0; round < 10; round++) {
    const count = await page.locator('article').count();
    if (count === 0) { log('  ✅ لا فلوز'); break; }
    log(`  ${count} صف — نحذف الأول`);

    // زر الـ Row Menu (p-2 icon button)
    const menuBtn = page.locator('article').first().locator('button[class*="p-2"]').first();
    await menuBtn.click();
    await sleep(600);

    // الدروب داون
    const dropdown = page.locator('.ui-layer-dropdown');
    if (!(await dropdown.isVisible({ timeout: 2000 }).catch(() => false))) {
      log('  ⚠️ لا dropdown'); await page.keyboard.press('Escape'); await sleep(400); continue;
    }

    // "حذف" في الدروب داون
    await page.locator('.ui-layer-dropdown button').filter({ hasText: 'حذف' }).first().click();
    await sleep(600);

    // انتظر زر "حذف الأتمتة" — البحث مباشرة في الصفحة (بدون [role="dialog"])
    const confirmBtn = page.locator('button:visible').filter({ hasText: 'حذف الأتمتة' });
    try {
      await confirmBtn.waitFor({ state: 'visible', timeout: 4000 });
      await confirmBtn.click();
      await page.waitForLoadState('networkidle').catch(() => {});
      await sleep(1500);
      log(`  ✅ حُذف (جولة ${round+1})`);
    } catch {
      log('  ⚠️ لم يظهر زر التأكيد');
      await page.keyboard.press('Escape');
      await sleep(500);
    }
    await sleep(500);
  }

  const remaining = await page.locator('article').count();
  log(`  صفوف متبقية: ${remaining}`);
  await sleep(2000);

  /* ═══════════════════════════════
   * 4. إنشاء فلو جديد
   * ═══════════════════════════════ */
  log('4️⃣  إنشاء فلو جديد...');

  // زر "إنشاء مسار"
  const createJourneyBtn = page.locator('button').filter({ hasText: /إنشاء مسار|Create journey/ }).first();
  await createJourneyBtn.waitFor({ state: 'visible', timeout: 8000 });
  await createJourneyBtn.click();
  await sleep(1000);

  // انتظر الـ input في modal (بدون type attribute — FormInput يرندر <input> بدون type)
  // البحث المباشر: input ليس input[type=text] (search bar) وليس email/password
  const nameInput = page.locator('input:not([type="text"]):not([type="email"]):not([type="password"])').first();
  await nameInput.waitFor({ state: 'visible', timeout: 8000 });
  log('  ✅ Modal مفتوح');
  await nameInput.fill('ترحيب وتوجيه العملاء الجدد');
  await sleep(500);

  // اختر الـ preset الأول
  const presetBtns = page.locator('.ui-layer-modal .grid button, [data-headlessui-state] .grid button');
  const presetCount = await presetBtns.count();
  log(`  Presets: ${presetCount}`);
  if (presetCount > 0) {
    await presetBtns.first().click();
    await sleep(400);
  }

  // زر "إنشاء" — البحث عن button[type="submit"] أو نص "إنشاء"
  const submitBtn = page.locator('.ui-layer-modal button[type="submit"], [data-headlessui-state] button[type="submit"]').first();
  if (await submitBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
    await submitBtn.click();
  } else {
    // fallback: زر نصه "إنشاء"
    await page.locator('button:visible').filter({ hasText: /^إنشاء$|^Create$/ }).last().click();
  }

  log('  ⏳ انتظار Builder...');
  await page.waitForURL(/\/automation\/flows\/[a-f0-9-]{36}/, { timeout: 20000 });
  await sleep(5000);
  log(`  ✅ Builder: ${page.url()}`);

  /* ═══════════════════════════════
   * 5. استعراض Builder
   * ═══════════════════════════════ */
  log('5️⃣  استعراض Builder...');
  await sleep(2000);

  const fitBtn = page.locator('.vue-flow__controls button').last();
  if (await fitBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
    await fitBtn.click(); await sleep(700); log('  Fit view');
  }

  const nodes = page.locator('.vue-flow__node');
  const nc = await nodes.count();
  log(`  Nodes: ${nc}`);

  for (let i = 0; i < Math.min(nc, 6); i++) {
    const n = nodes.nth(i);
    const txt = (await n.textContent().catch(() => '')).replace(/\s+/g,' ').slice(0, 50).trim();
    log(`  Node ${i+1}: "${txt}"`);
    await n.click({ force: true }); await sleep(600);
    await n.click({ force: true }); await sleep(1000);
    await page.locator('.vue-flow__pane').click({ position:{x:80,y:80}, force:true }).catch(()=>{});
    await sleep(400);
  }

  if (await fitBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
    await fitBtn.click(); await sleep(600);
  }
  await sleep(2000);

  /* ═══════════════════════════════
   * 6. حفظ
   * ═══════════════════════════════ */
  log('6️⃣  حفظ...');
  const saveBtn = page.locator('button').filter({ hasText: /^حفظ$|^Save$/ }).first();
  if (await saveBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
    await saveBtn.click(); await sleep(1500); log('  ✅ حفظ');
  }

  /* ═══════════════════════════════
   * 7. Validate
   * ═══════════════════════════════ */
  log('7️⃣  Validate...');
  // زر More في FlowBuilderHeaderCard — يحمل title="المزيد" أو title="More"
  const moreMenuBtn = page.locator('button[title="المزيد"], button[title="More"]').first();
  if (await moreMenuBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
    await moreMenuBtn.click({ force: true });
    await sleep(800);

    // الدروب داون في body (fixed z-[2400])
    const validateBtn = page.locator('button:visible').filter({ hasText: /شغّل التحقق|Run validation/ }).first();
    if (await validateBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
      await validateBtn.click();
      await sleep(2000);
      log('  ✅ Validate شغّل');
    } else {
      await page.keyboard.press('Escape');
      log('  ⚠️ لا validate button');
    }
  } else {
    log('  ⚠️ لا More button');
  }
  await sleep(2000); // انتظار استقرار الـ DOM بعد Validate

  /* ═══════════════════════════════
   * 8. معاينة
   * ═══════════════════════════════ */
  log('8️⃣  معاينة...');

  // بدون anchors لأن النص قد يحتوي على whitespace
  const previewBtn = page.locator('button').filter({ hasText: /معاينة|Preview/ }).first();
  if (await previewBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
    await previewBtn.click();
    await sleep(2000);

    // انتظر محتوى الـ preview modal — الترجمة: "معاينة الجوال"
    const previewTitle = page.locator('text=/معاينة الجوال|Mobile preview/i').first();
    const previewPortal = page.locator('#headlessui-portal-root').first();

    const previewOpened = await previewTitle.isVisible({ timeout: 5000 }).catch(() => false)
      || await previewPortal.isVisible({ timeout: 1000 }).catch(() => false);

    if (previewOpened) {
      log('  ✅ Preview مفتوح');
      await sleep(4000);

      // تفاعل مع المحتوى
      const pBtns = page.locator('#headlessui-portal-root button:visible');
      const pCount = await pBtns.count();
      log(`  أزرار Preview: ${pCount}`);

      for (let i = 0; i < Math.min(pCount, 4); i++) {
        const txt = (await pBtns.nth(i).textContent().catch(() => '')).trim();
        if (txt && txt.length > 1 && !txt.includes('×') && !txt.includes('إغلاق') && !txt.includes('Close')) {
          log(`  - "${txt.slice(0,30)}"`);
          await pBtns.nth(i).click({ force: true }).catch(() => {});
          await sleep(1500);
        }
      }

      await sleep(2000);
    } else {
      log('  ⚠️ Preview content لم يظهر');
    }

    // إغلاق أي modal مفتوح قبل المتابعة
    await page.keyboard.press('Escape');
    await sleep(800);
    // إذا لا يزال مفتوحاً، اضغط مرة أخرى
    if (await page.locator('#headlessui-portal-root').isVisible({ timeout: 500 }).catch(() => false)) {
      await page.keyboard.press('Escape');
      await sleep(500);
    }
    log('  Preview مغلق');
  }
  await sleep(1000);

  /* ═══════════════════════════════
   * 9. نشر
   * ═══════════════════════════════ */
  log('9️⃣  نشر...');

  const publishBtn = page.locator('button').filter({ hasText: /^نشر$|^Publish$/ }).first();
  if (await publishBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
    const disabled = await publishBtn.isDisabled();
    log(`  Publish: ${disabled ? '🔒 معطّل' : '✅ نشط'}`);
    if (!disabled) {
      await publishBtn.click({ force: true });
      await page.waitForLoadState('networkidle').catch(() => {});
      await sleep(2500);
      log('  ✅ نُشر');
    }
  }
  await sleep(2000);

  /* ═══════════════════════════════
   * 10. النتيجة النهائية
   * ═══════════════════════════════ */
  log('🔟 النتيجة...');

  if (await fitBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
    await fitBtn.click(); await sleep(600);
  }

  const statusTxt = await page.locator('span').filter({ hasText: /منشور|Published|مسودة|Draft/ }).first().textContent().catch(() => '');
  log(`  الحالة: "${statusTxt?.trim()}"`);

  await sleep(4000);
  log('✅ اكتمل');

  /* ═══════════════════════════════
   * إنهاء
   * ═══════════════════════════════ */
  await ctx.close();
  await browser.close();

  const files = fs.readdirSync(OUTDIR)
    .filter(f => f.endsWith('.webm'))
    .map(f => ({ f, t: fs.statSync(path.join(OUTDIR, f)).mtimeMs }))
    .sort((a, b) => b.t - a.t);

  if (!files.length) { log('❌ لا فيديو'); process.exit(1); }

  const dest = path.join(OUTDIR, 'flow-builder-test-result.webm');
  if (fs.existsSync(dest)) fs.unlinkSync(dest);
  fs.renameSync(path.join(OUTDIR, files[0].f), dest);

  const mb = (fs.statSync(dest).size / 1024 / 1024).toFixed(1);
  log(`\n🎬 الفيديو: ${dest}`);
  log(`📦 الحجم: ${mb} MB`);

})().catch(e => {
  console.error('❌ خطأ:', e.message);
  process.exit(1);
});
