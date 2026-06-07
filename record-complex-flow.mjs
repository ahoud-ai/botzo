/**
 * record-complex-flow.mjs
 * جولة كاملة في الفلو المعقد "خدمة عملاء متجر النجاح الإلكتروني"
 * 40 نود × 3 فروع رئيسية × 3 فروع فرعية
 */

import { chromium } from 'playwright';
import path from 'path';
import fs from 'fs';

const BASE    = 'http://localhost:8000';
const EMAIL   = 'mahmoud.hamed.shenawy@gmail.com';
const PASS    = 'Password@2026';
const FLOW_ID = '66904b6e-ae97-4624-b757-60d3135c97ab';
const OUTDIR  = path.join(process.cwd(), 'recordings');

if (!fs.existsSync(OUTDIR)) fs.mkdirSync(OUTDIR, { recursive: true });

const sleep = ms => new Promise(r => setTimeout(r, ms));
const log   = msg => console.log(`[${new Date().toISOString().slice(11,19)}] ${msg}`);

// اضغط Fit View
async function fitView(page) {
  const btn = page.locator('.vue-flow__controls button').last();
  if (await btn.isVisible({ timeout: 1500 }).catch(() => false)) {
    await btn.click(); await sleep(900);
  }
}

// scroll canvas عبر mouse.wheel على منتصف اللوحة
async function wheelCanvas(page, deltaX, deltaY) {
  const box = await page.locator('.vue-flow__pane').boundingBox().catch(() => null);
  if (!box) return;
  const cx = box.x + box.width  / 2;
  const cy = box.y + box.height / 2;
  await page.mouse.wheel(deltaX, deltaY);
  await sleep(300);
}

// تحريك اللوحة بـ drag
async function panCanvas(page, dx, dy) {
  const box = await page.locator('.vue-flow__pane').boundingBox().catch(() => null);
  if (!box) return;
  const cx = box.x + box.width  / 2;
  const cy = box.y + box.height / 2;
  await page.mouse.move(cx, cy);
  await page.mouse.down();
  await page.mouse.move(cx + dx, cy + dy, { steps: 25 });
  await page.mouse.up();
  await sleep(500);
}

// انقر نود بـ evaluate (يتجاوز viewport check)
async function clickNodeSafe(page, nodeLocator) {
  try {
    await nodeLocator.evaluate(el => el.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true })));
    return true;
  } catch { return false; }
}

(async () => {
  log('🚀 بدء تسجيل الفلو المعقد');

  const browser = await chromium.launch({
    headless: false,
    args: ['--start-maximized', '--no-sandbox'],
    slowMo: 40,
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
  await sleep(7000);

  if (page.url().includes('/login')) {
    log('❌ فشل تسجيل الدخول'); await ctx.close(); await browser.close(); process.exit(1);
  }
  log(`  ✅ ${page.url()}`);

  /* ═══════════════════════════════
   * 2. فتح الفلو مباشرةً
   * ═══════════════════════════════ */
  log('2️⃣  فتح الفلو المعقد...');
  await page.goto(`${BASE}/automation/flows/${FLOW_ID}`, { waitUntil: 'domcontentloaded' });
  await sleep(7000);

  await page.waitForSelector('.vue-flow__node', { timeout: 15000 }).catch(() => {});
  const totalNodes = await page.locator('.vue-flow__node').count();
  log(`  Nodes: ${totalNodes}`);

  /* ═══════════════════════════════
   * 3. نظرة عامة — كل الفلو
   * ═══════════════════════════════ */
  log('3️⃣  نظرة عامة — Fit View...');
  await fitView(page);
  await sleep(3000);

  // zoom out لرؤية كل الفلو
  const box0 = await page.locator('.vue-flow__pane').boundingBox().catch(() => null);
  if (box0) {
    await page.mouse.move(box0.x + box0.width/2, box0.y + box0.height/2);
    for (let i = 0; i < 8; i++) { await page.mouse.wheel(0, 120); await sleep(60); }
  }
  await sleep(2000);

  await fitView(page);
  await sleep(3000); // نوقف هنا ليشوف المشاهد الفلو كاملاً

  /* ═══════════════════════════════
   * 4. تكبير على البداية — المحور الرئيسي
   * ═══════════════════════════════ */
  log('4️⃣  المحور الرئيسي — Trigger → Main Menu...');

  // تكبير
  const boxM = await page.locator('.vue-flow__pane').boundingBox().catch(() => null);
  if (boxM) {
    // تركيز في الجزء اليسار حيث Trigger
    await page.mouse.move(boxM.x + 200, boxM.y + boxM.height/2);
    for (let i = 0; i < 5; i++) { await page.mouse.wheel(0, -120); await sleep(60); }
  }
  await sleep(1000);

  // انقر على النودات المرئية (trigger, welcome, main-menu)
  const allNodes = page.locator('.vue-flow__node');
  for (let i = 0; i < 3; i++) {
    const ok = await clickNodeSafe(page, allNodes.nth(i));
    if (ok) {
      const txt = (await allNodes.nth(i).textContent().catch(() => '')).replace(/\s+/g,' ').slice(0,45).trim();
      log(`  Node ${i+1}: "${txt}"`);
      await sleep(1500);
      // أغلق sidebar إن فتح بالنقر على اللوحة
      await page.locator('.vue-flow__pane').click({ force: true }).catch(() => {});
      await sleep(400);
    }
  }

  /* ═══════════════════════════════
   * 5. فرع المنتجات (يمين + أعلى)
   * ═══════════════════════════════ */
  log('5️⃣  فرع المنتجات...');
  await fitView(page);
  await sleep(1000);

  // zoom out ثم انتقل نحو منطقة المنتجات (أعلى يمين)
  const boxP = await page.locator('.vue-flow__pane').boundingBox().catch(() => null);
  if (boxP) {
    await page.mouse.move(boxP.x + boxP.width/2, boxP.y + boxP.height/2);
    for (let i = 0; i < 4; i++) { await page.mouse.wheel(0, 120); await sleep(60); }
  }
  await sleep(500);

  // تحريك اليمين والأعلى لمنطقة المنتجات
  await panCanvas(page, -250, 150);
  await sleep(800);
  await panCanvas(page, -150, 80);
  await sleep(800);

  // انقر على products-intro و categories-menu
  for (let i = 3; i < 6; i++) {
    const ok = await clickNodeSafe(page, allNodes.nth(i));
    if (ok) {
      const txt = (await allNodes.nth(i).textContent().catch(() => '')).replace(/\s+/g,' ').slice(0,45).trim();
      log(`  Prod ${i+1}: "${txt}"`);
      await sleep(1300);
      await page.locator('.vue-flow__pane').click({ force: true }).catch(() => {});
      await sleep(350);
    }
  }

  /* ═══════════════════════════════
   * 6. فرع الأزياء (أقصى يمين أعلى)
   * ═══════════════════════════════ */
  log('6️⃣  فرع الأزياء...');
  await fitView(page); await sleep(800);

  const boxA = await page.locator('.vue-flow__pane').boundingBox().catch(() => null);
  if (boxA) {
    await page.mouse.move(boxA.x + boxA.width/2, boxA.y + boxA.height/2);
    for (let i = 0; i < 5; i++) { await page.mouse.wheel(0, 120); await sleep(60); }
  }
  await sleep(500);
  await panCanvas(page, -500, 200);
  await sleep(600);

  for (let i = 6; i < 10; i++) {
    const ok = await clickNodeSafe(page, allNodes.nth(i));
    if (ok) {
      const txt = (await allNodes.nth(i).textContent().catch(() => '')).replace(/\s+/g,' ').slice(0,45).trim();
      log(`  Azya ${i+1}: "${txt}"`);
      await sleep(1300);
      await page.locator('.vue-flow__pane').click({ force: true }).catch(() => {});
      await sleep(350);
    }
  }

  /* ═══════════════════════════════
   * 7. فرع الإلكترونيات
   * ═══════════════════════════════ */
  log('7️⃣  فرع الإلكترونيات...');
  await fitView(page); await sleep(800);

  const boxE = await page.locator('.vue-flow__pane').boundingBox().catch(() => null);
  if (boxE) {
    await page.mouse.move(boxE.x + boxE.width/2, boxE.y + boxE.height/2);
    for (let i = 0; i < 4; i++) { await page.mouse.wheel(0, 120); await sleep(60); }
  }
  await sleep(500);
  await panCanvas(page, -450, 100);
  await sleep(600);

  for (let i = 10; i < 17; i++) {
    const ok = await clickNodeSafe(page, allNodes.nth(i));
    if (ok) {
      const txt = (await allNodes.nth(i).textContent().catch(() => '')).replace(/\s+/g,' ').slice(0,45).trim();
      log(`  Elec ${i+1}: "${txt}"`);
      await sleep(1100);
      await page.locator('.vue-flow__pane').click({ force: true }).catch(() => {});
      await sleep(300);
    }
  }

  /* ═══════════════════════════════
   * 8. فرع المنزل
   * ═══════════════════════════════ */
  log('8️⃣  فرع المنزل...');
  await fitView(page); await sleep(800);

  const boxH = await page.locator('.vue-flow__pane').boundingBox().catch(() => null);
  if (boxH) {
    await page.mouse.move(boxH.x + boxH.width/2, boxH.y + boxH.height/2);
    for (let i = 0; i < 4; i++) { await page.mouse.wheel(0, 120); await sleep(60); }
  }
  await sleep(500);
  await panCanvas(page, -400, 0);
  await sleep(600);

  for (let i = 17; i < 20; i++) {
    const ok = await clickNodeSafe(page, allNodes.nth(i));
    if (ok) {
      const txt = (await allNodes.nth(i).textContent().catch(() => '')).replace(/\s+/g,' ').slice(0,45).trim();
      log(`  Home ${i+1}: "${txt}"`);
      await sleep(1100);
      await page.locator('.vue-flow__pane').click({ force: true }).catch(() => {});
      await sleep(300);
    }
  }

  /* ═══════════════════════════════
   * 9. فرع متابعة الطلبات
   * ═══════════════════════════════ */
  log('9️⃣  فرع متابعة الطلبات...');
  await fitView(page); await sleep(800);

  const boxO = await page.locator('.vue-flow__pane').boundingBox().catch(() => null);
  if (boxO) {
    await page.mouse.move(boxO.x + boxO.width/2, boxO.y + boxO.height/2);
    for (let i = 0; i < 4; i++) { await page.mouse.wheel(0, 120); await sleep(60); }
  }
  await sleep(500);
  await panCanvas(page, -300, -100);
  await sleep(600);

  for (let i = 20; i < 31; i++) {
    const ok = await clickNodeSafe(page, allNodes.nth(i));
    if (ok) {
      const txt = (await allNodes.nth(i).textContent().catch(() => '')).replace(/\s+/g,' ').slice(0,45).trim();
      log(`  Order ${i+1}: "${txt}"`);
      await sleep(900);
      await page.locator('.vue-flow__pane').click({ force: true }).catch(() => {});
      await sleep(250);
    }
  }

  /* ═══════════════════════════════
   * 10. فرع الدعم
   * ═══════════════════════════════ */
  log('🔟 فرع الدعم...');
  await fitView(page); await sleep(800);

  const boxS = await page.locator('.vue-flow__pane').boundingBox().catch(() => null);
  if (boxS) {
    await page.mouse.move(boxS.x + boxS.width/2, boxS.y + boxS.height/2);
    for (let i = 0; i < 4; i++) { await page.mouse.wheel(0, 120); await sleep(60); }
  }
  await sleep(500);
  await panCanvas(page, -300, -300);
  await sleep(600);

  for (let i = 31; i < totalNodes; i++) {
    const ok = await clickNodeSafe(page, allNodes.nth(i));
    if (ok) {
      const txt = (await allNodes.nth(i).textContent().catch(() => '')).replace(/\s+/g,' ').slice(0,45).trim();
      log(`  Supp ${i+1}: "${txt}"`);
      await sleep(900);
      await page.locator('.vue-flow__pane').click({ force: true }).catch(() => {});
      await sleep(250);
    }
  }

  /* ═══════════════════════════════
   * 11. نظرة كاملة ختامية
   * ═══════════════════════════════ */
  log('🌍 نظرة ختامية كاملة...');
  await fitView(page);
  await sleep(2500);

  const boxFinal = await page.locator('.vue-flow__pane').boundingBox().catch(() => null);
  if (boxFinal) {
    await page.mouse.move(boxFinal.x + boxFinal.width/2, boxFinal.y + boxFinal.height/2);
    for (let i = 0; i < 6; i++) { await page.mouse.wheel(0, 120); await sleep(80); }
  }
  await sleep(2000);
  await fitView(page);
  await sleep(3000);

  /* ═══════════════════════════════
   * 12. حفظ
   * ═══════════════════════════════ */
  log('💾 حفظ...');
  const saveBtn = page.locator('button').filter({ hasText: /^حفظ$|^Save$/ }).first();
  if (await saveBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
    await saveBtn.click(); await sleep(2000); log('  ✅ تم الحفظ');
  }
  await sleep(1000);

  /* ═══════════════════════════════
   * 13. Validate
   * ═══════════════════════════════ */
  log('✅ Validate...');
  const moreBtn = page.locator('button[title="المزيد"], button[title="More"]').first();
  if (await moreBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
    await moreBtn.click({ force: true }); await sleep(800);
    const vBtn = page.locator('button:visible').filter({ hasText: /شغّل التحقق|Run validation/ }).first();
    if (await vBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
      await vBtn.click(); await sleep(3500); log('  ✅ تم التحقق');
    } else {
      await page.keyboard.press('Escape');
      log('  ⚠️ لا validate button');
    }
  }
  await sleep(1500);

  /* ═══════════════════════════════
   * 14. معاينة
   * ═══════════════════════════════ */
  log('👁️  معاينة...');
  const previewBtn = page.locator('button').filter({ hasText: /معاينة|Preview/ }).first();
  if (await previewBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
    await previewBtn.click(); await sleep(2500);
    const portal = page.locator('#headlessui-portal-root');
    if (await portal.isVisible({ timeout: 4000 }).catch(() => false)) {
      log('  ✅ Preview مفتوح');
      await sleep(5000);
    }
    await page.keyboard.press('Escape'); await sleep(800);
    if (await portal.isVisible({ timeout: 500 }).catch(() => false)) {
      await page.keyboard.press('Escape'); await sleep(500);
    }
    log('  Preview مغلق');
  }

  /* ═══════════════════════════════
   * 15. نشر
   * ═══════════════════════════════ */
  log('🚀 نشر...');
  const publishBtn = page.locator('button').filter({ hasText: /^نشر$|^Publish$/ }).first();
  if (await publishBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
    const disabled = await publishBtn.isDisabled();
    log(`  Publish: ${disabled ? '🔒 معطّل' : '✅ نشط'}`);
    if (!disabled) {
      await publishBtn.click({ force: true });
      await page.waitForLoadState('networkidle').catch(() => {});
      await sleep(3000);
    }
  }
  await sleep(2000);

  /* ═══════════════════════════════
   * 16. نظرة ختامية بعد النشر
   * ═══════════════════════════════ */
  log('🎉 اكتمل!');
  await fitView(page);
  const statusTxt = await page.locator('span').filter({ hasText: /منشور|Published|مسودة|Draft/ }).first().textContent().catch(() => '');
  log(`  الحالة: "${statusTxt?.trim()}"`);
  await sleep(4000);

  /* ═══════════════════════════════
   * إنهاء وحفظ الفيديو
   * ═══════════════════════════════ */
  await ctx.close();
  await browser.close();

  const files = fs.readdirSync(OUTDIR)
    .filter(f => f.endsWith('.webm'))
    .map(f => ({ f, t: fs.statSync(path.join(OUTDIR, f)).mtimeMs }))
    .sort((a, b) => b.t - a.t);

  if (!files.length) { log('❌ لا فيديو'); process.exit(1); }

  const dest = path.join(OUTDIR, 'complex-flow-demo.webm');
  if (fs.existsSync(dest)) fs.unlinkSync(dest);
  fs.renameSync(path.join(OUTDIR, files[0].f), dest);

  const mb = (fs.statSync(dest).size / 1024 / 1024).toFixed(1);
  log(`\n🎬 الفيديو: ${dest}`);
  log(`📦 الحجم: ${mb} MB`);
  log(`🔗 الفلو: ${BASE}/automation/flows/${FLOW_ID}`);

})().catch(e => {
  console.error('❌ خطأ:', e.message);
  process.exit(1);
});
