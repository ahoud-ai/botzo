import { chromium } from 'playwright';
import path from 'path';
import fs from 'fs';

const BASE      = 'http://localhost:8000';
const EMAIL     = 'mahmoud.hamed.shenawy@gmail.com';
const PASS      = 'Test@1234';
const OUTDIR    = path.join(process.cwd(), 'recordings');
const TEST_FLOW = 'a8358432-1ce0-42eb-9ac5-eb9f40bce20a';

if (!fs.existsSync(OUTDIR)) fs.mkdirSync(OUTDIR, { recursive: true });
const sleep = ms => new Promise(r => setTimeout(r, ms));

const scrollChat = async (page) => {
  const box = page.locator('[class*="overflow-y"], [class*="scroll"], [class*="messages"]').nth(1);
  if (await box.isVisible({ timeout: 1000 }).catch(() => false)) {
    await box.evaluate(el => { el.scrollTop = 0; });
    await sleep(800);
    await box.evaluate(el => { el.scrollTop = el.scrollHeight / 2; });
    await sleep(700);
    await box.evaluate(el => { el.scrollTop = el.scrollHeight; });
    await sleep(800);
  }
};

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox', '--disable-gpu'] });
  const ctx = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    locale: 'ar',
    recordVideo: { dir: OUTDIR, size: { width: 1440, height: 900 } },
  });
  const page = await ctx.newPage();

  /* ── تسجيل الدخول ─────────────────────────────── */
  await page.goto(`${BASE}/login`);
  await page.waitForLoadState('networkidle');
  await sleep(1500);
  await page.locator('input[type=email]').first().fill(EMAIL);
  await sleep(400);
  await page.locator('input[type=password]').first().fill(PASS);
  await sleep(600);
  await page.locator('button[type=submit]').first().click();
  await page.waitForLoadState('networkidle');
  await sleep(2500);

  /* ── شاشة الدردشات ───────────────────────────── */
  await page.goto(`${BASE}/chats`);
  await page.waitForLoadState('networkidle');
  await sleep(3000);

  // ── محادثة أحمد ──────────────────────────────
  const chatLinks = await page.locator('a[href*="/chats/"]').all();

  if (chatLinks[0]) {
    await chatLinks[0].click();
    await page.waitForLoadState('networkidle');
    await sleep(3000);
    await scrollChat(page);
    await sleep(2500);
  }

  // ── محادثة سارة ──────────────────────────────
  if (chatLinks[1]) {
    await chatLinks[1].click();
    await page.waitForLoadState('networkidle');
    await sleep(2500);
    await scrollChat(page);
    await sleep(2500);
  }

  // ── محادثة محمد ──────────────────────────────
  if (chatLinks[2]) {
    await chatLinks[2].click();
    await page.waitForLoadState('networkidle');
    await sleep(2500);
    await scrollChat(page);
    await sleep(2500);
  }

  /* ── افتح محادثة أحمد تاني مرة وركّز عليها ── */
  if (chatLinks[0]) {
    await chatLinks[0].click();
    await page.waitForLoadState('networkidle');
    await sleep(2500);
    await scrollChat(page);
    await sleep(3000);
  }

  /* ── الـ Flow Builder ────────────────────────── */
  await page.goto(`${BASE}/automation/flows/${TEST_FLOW}`);
  await page.waitForLoadState('networkidle');
  await sleep(4000);

  const fit = page.locator('.vue-flow__controls button').last();
  if (await fit.isVisible({ timeout: 1000 }).catch(() => false)) { await fit.click(); await sleep(900); }
  await sleep(2000);

  // جولة في الـ Nodes
  for (const nid of ['trigger-1','welcome-1','menu-1','reply-pricing']) {
    const node = page.locator(`.vue-flow__node[data-id="${nid}"]`);
    if (await node.isVisible({ timeout: 1000 }).catch(() => false)) {
      await page.locator('.vue-flow__pane').first().click({ position:{x:50,y:50}, force:true }).catch(()=>{});
      await sleep(400);
      await node.click({ force: true }).catch(() => {});
      await sleep(700);
      await node.dblclick({ force: true }).catch(() => {});
      await sleep(2200);
    }
  }
  await page.locator('.vue-flow__pane').first().click({ position:{x:50,y:50}, force:true }).catch(()=>{});
  await sleep(800);

  // ── معاينة تفاعلية ──────────────────────────
  const prevBtn = page.locator('button:has-text("معاينة"), button:has-text("Preview")').first();
  if (await prevBtn.isVisible({ timeout: 1500 }).catch(() => false)) {
    await prevBtn.click();
    await sleep(3500);

    const modal = page.locator('[class*="preview"], [class*="modal"], dialog').first();
    if (await modal.isVisible({ timeout: 2000 }).catch(() => false)) {
      await sleep(2000);

      // اضغط على زر الأسعار
      const btn1 = modal.locator('button, [role="button"]').filter({ hasText: /سعر|باقة|pricing/i }).first();
      if (await btn1.isVisible({ timeout: 2000 }).catch(() => false)) {
        await btn1.click(); await sleep(3000);
      } else {
        // جرّب كل الأزرار الموجودة
        const allBtns = await modal.locator('button').all();
        for (const b of allBtns) {
          const txt = await b.textContent().catch(() => '');
          if (txt && txt.trim().length > 0 && txt.trim().length < 40) {
            console.log('Preview button found:', txt.trim());
          }
        }
        await sleep(2000);
      }

      // scroll
      await modal.evaluate(el => {
        const s = el.querySelector('[class*="overflow"], [class*="scroll"], [class*="messages"]');
        if (s) s.scrollTop = s.scrollHeight;
      }).catch(() => {});
      await sleep(2500);
    }
    await page.keyboard.press('Escape');
    await sleep(800);
  }

  // ── معاينة تانية – دعم تقني ─────────────────
  if (await prevBtn.isVisible({ timeout: 1500 }).catch(() => false)) {
    await prevBtn.click();
    await sleep(3000);
    const modal2 = page.locator('[class*="preview"], [class*="modal"], dialog').first();
    if (await modal2.isVisible({ timeout: 2000 }).catch(() => false)) {
      await sleep(1500);
      const btn2 = modal2.locator('button, [role="button"]').filter({ hasText: /دعم|support/i }).first();
      if (await btn2.isVisible({ timeout: 2000 }).catch(() => false)) {
        await btn2.click(); await sleep(3000);
      }
      await modal2.evaluate(el => {
        const s = el.querySelector('[class*="overflow"], [class*="scroll"]');
        if (s) s.scrollTop = s.scrollHeight;
      }).catch(() => {});
      await sleep(2500);
    }
    await page.keyboard.press('Escape');
    await sleep(800);
  }

  /* ── الرجوع للشاشة وأحمد ─────────────────── */
  await page.goto(`${BASE}/chats`);
  await page.waitForLoadState('networkidle');
  await sleep(3000);

  const chatLinks2 = await page.locator('a[href*="/chats/"]').all();
  if (chatLinks2[0]) {
    await chatLinks2[0].click();
    await page.waitForLoadState('networkidle');
    await sleep(3000);
    await scrollChat(page);
    await sleep(3500);
  }

  await sleep(2000);

  await ctx.close();
  await browser.close();

  const files = fs.readdirSync(OUTDIR)
    .filter(f => f.endsWith('.webm') && !['demo-clean.webm','flowbuilder-ai-full-demo.webm','real-test-part1-builder.webm','real-test-part2-results.webm'].includes(f))
    .map(f => ({ f, t: fs.statSync(path.join(OUTDIR, f)).mtimeMs }))
    .sort((a, b) => b.t - a.t);

  if (!files.length) { console.error('مفيش فيديو!'); process.exit(1); }

  const out = path.join(OUTDIR, 'real-test-chats-and-flow.webm');
  if (fs.existsSync(out)) fs.unlinkSync(out);
  fs.renameSync(path.join(OUTDIR, files[0].f), out);

  const mb = (fs.statSync(out).size / 1024 / 1024).toFixed(1);
  console.log(`\n✅  ${out}`);
  console.log(`📦  ${mb} MB`);
})().catch(e => { console.error('❌', e.message); process.exit(1); });
