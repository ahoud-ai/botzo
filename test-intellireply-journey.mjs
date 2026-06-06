/**
 * رحلة اختبار IntelliReply — النسخة الكاملة
 * الـ AI مضبوط والـ UI يظهر كامل
 */

import { chromium } from 'playwright';
import { writeFileSync, mkdirSync } from 'fs';

const BASE    = 'http://127.0.0.1:8000';
const EMAIL   = 'mahmoud.hamed.shenawy@gmail.com';
const PASS    = 'Test1234!';
const CONTACT = '9269d893-76ec-4d00-bb49-e6dc6e6614c9';
const OUT     = './test-artifacts/intellireply-journey';
const LOG     = [];

mkdirSync(OUT, { recursive: true });

let n = 0;
async function shot(page, slug, caption = '') {
  n++;
  const file = `${String(n).padStart(2, '0')}-${slug}.png`;
  await page.screenshot({ path: `${OUT}/${file}`, fullPage: true });
  console.log(`  📸 [${n}] ${caption || slug}`);
  LOG.push({ n, slug, caption, file });
}

async function go(page, path) {
  await page.goto(`${BASE}${path}`, { waitUntil: 'networkidle', timeout: 30000 });
  await page.waitForTimeout(1200);
}

// ─────────────────────────────────────────────────────────────────────────────
(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const ctx     = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page    = await ctx.newPage();

  console.log('\n' + '═'.repeat(65));
  console.log('  🤖 اختبار رحلة IntelliReply — Smart Router AI Assistant');
  console.log('═'.repeat(65) + '\n');

  // ══════════════════════════════════════════════════════════
  // PART 1: تسجيل الدخول
  // ══════════════════════════════════════════════════════════
  console.log('🔐  PART 1: تسجيل الدخول\n');

  await go(page, '/');
  await shot(page, 'homepage', '🏠 الصفحة الرئيسية للمنصة (Landing Page)');

  await go(page, '/login');
  await shot(page, 'login-empty', '🔑 صفحة تسجيل الدخول — قبل التعبئة');

  await page.locator('input[type="email"]').first().fill(EMAIL);
  await page.locator('input[type="password"]').first().fill(PASS);
  await shot(page, 'login-filled', '✏️ تسجيل الدخول — بعد تعبئة البيانات');

  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle', timeout: 20000 }).catch(() => {}),
    page.locator('button[type="submit"]').first().click(),
  ]);
  await page.waitForTimeout(2500);

  // اختيار مساحة العمل لو ظهرت
  if (page.url().includes('select') || page.url().includes('organization')) {
    const wsbtn = page.locator('button, a').filter({ hasText: /فتح|open workspace|مؤسسة محمود/i }).first();
    if (await wsbtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await wsbtn.click();
      await page.waitForTimeout(2500);
    }
    await shot(page, 'workspace-select', '🏢 اختيار مساحة العمل');
  }

  await shot(page, 'dashboard', '📊 الداشبورد — الصفحة الرئيسية بعد الدخول');

  // ══════════════════════════════════════════════════════════
  // PART 2: قائمة الأتمتة وصفحة AI
  // ══════════════════════════════════════════════════════════
  console.log('\n🤖  PART 2: صفحة Smart Router AI\n');

  // لقطة تظهر الـ Sidebar مع Automation menu مفتوح
  await go(page, '/automation/basic');
  await shot(page, 'automation-menu-visible', '📋 قائمة الأتمتة — الشريط الجانبي');

  // الصفحة الرئيسية للـ AI
  await go(page, '/automation/ai');
  await shot(page, 'ai-page-full', '🤖 صفحة /automation/ai — الصفحة كاملة');

  // تكبير لأعلى الصفحة
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.waitForTimeout(300);
  await page.screenshot({
    path: `${OUT}/ZOOM-ai-header.png`,
    clip: { x: 0, y: 0, width: 1440, height: 200 }
  });
  console.log('  🔍 لقطة مكبّرة: Header الـ AI page');

  // ══════════════════════════════════════════════════════════
  // PART 3: فتح نافذة الإعداد (Setup Modal)
  // ══════════════════════════════════════════════════════════
  console.log('\n⚙️  PART 3: نافذة إعداد AI\n');

  await go(page, '/automation/ai');

  // اضغط زر Update
  const updateBtn = page.locator('button').filter({ hasText: /^update$|^تحديث$/i }).first();
  const hasUpdate = await updateBtn.isVisible({ timeout: 5000 }).catch(() => false);
  if (hasUpdate) {
    await updateBtn.scrollIntoViewIfNeeded();
    await updateBtn.click({ force: true });
    await page.waitForTimeout(1800);
    await shot(page, 'setup-modal-open', '🔧 نافذة إعداد AI Assistant — الحقول الكاملة');

    // صوّر محتوى النافذة فقط
    const dialog = page.locator('[role="dialog"], .modal, [class*="Modal"]').first();
    if (await dialog.isVisible({ timeout: 3000 }).catch(() => false)) {
      const box = await dialog.boundingBox();
      if (box) {
        await page.screenshot({
          path: `${OUT}/ZOOM-setup-modal.png`,
          clip: { x: box.x, y: box.y, width: box.width, height: box.height }
        });
        console.log('  🔍 لقطة مكبّرة: Setup Modal');
      }
    }

    // إغلاق النافذة
    const cancelBtn = page.locator('button').filter({ hasText: /cancel|إلغاء/i }).first();
    if (await cancelBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
      await cancelBtn.click();
    } else {
      await page.keyboard.press('Escape');
    }
    await page.waitForTimeout(800);
  } else {
    // جرّب toggle
    const toggle = page.locator('[role="switch"]').first();
    if (await toggle.isVisible({ timeout: 3000 }).catch(() => false)) {
      const isOn = await toggle.getAttribute('aria-checked');
      console.log(`   Toggle state: ${isOn}`);
      if (isOn === 'false') {
        await toggle.click({ force: true });
        await page.waitForTimeout(1500);
        await shot(page, 'toggle-clicked-modal', '⚡ Toggle clicked — Modal ظهر');
        await page.keyboard.press('Escape');
        await page.waitForTimeout(500);
      }
    }
    await shot(page, 'setup-modal-state', '⚙️ حالة الـ Setup Modal');
  }

  // ══════════════════════════════════════════════════════════
  // PART 4: قسم AI Assistant Setup (الكلمات المفتاحية)
  // ══════════════════════════════════════════════════════════
  console.log('\n🔑  PART 4: الكلمات المفتاحية\n');

  await go(page, '/automation/ai');

  // البحث عن AI Assistant Setup section
  const setupTitles = await page.locator('h4, h3, div[class*="text"]').all();
  let foundSetup = false;
  for (const el of setupTitles) {
    const txt = await el.textContent().catch(() => '');
    if (/AI Assistant Setup/i.test(txt)) {
      await el.scrollIntoViewIfNeeded();
      await page.waitForTimeout(300);
      await el.click({ force: true });
      await page.waitForTimeout(1000);
      await shot(page, 'keywords-section', '🔑 قسم الكلمات المفتاحية — بعد الفتح');
      foundSetup = true;

      // صوّر القسم ده
      const parent = await el.locator('..').locator('..').boundingBox();
      if (parent) {
        await page.screenshot({
          path: `${OUT}/ZOOM-keywords.png`,
          clip: { x: 0, y: Math.max(0, parent.y - 20), width: 1440, height: Math.min(500, parent.height + 40) }
        });
        console.log('  🔍 لقطة مكبّرة: Keywords Section');
      }
      break;
    }
  }
  if (!foundSetup) {
    await shot(page, 'keywords-not-visible', '❌ قسم الكلمات المفتاحية مش ظاهر');
    console.log('   ⚠️ لم يتم إيجاد قسم الكلمات المفتاحية');
  }

  // ══════════════════════════════════════════════════════════
  // PART 5: قسم Knowledge Base (قاعدة المعرفة)
  // ══════════════════════════════════════════════════════════
  console.log('\n📚  PART 5: قاعدة المعرفة (Knowledge Base)\n');

  await go(page, '/automation/ai');
  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
  await page.waitForTimeout(500);
  await shot(page, 'knowledge-base', '📚 قسم قاعدة المعرفة — جدول المستندات');

  // زر Upload
  const uploadBtn = page.locator('button').filter({ hasText: /upload documents|رفع مستندات/i }).first();
  if (await uploadBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
    await uploadBtn.click({ force: true });
    await page.waitForTimeout(1200);
    await shot(page, 'upload-modal', '📤 نافذة رفع المستندات');

    // صوّر النافذة
    const dialog = page.locator('[role="dialog"], .modal, [class*="Modal"]').first();
    if (await dialog.isVisible({ timeout: 2000 }).catch(() => false)) {
      const box = await dialog.boundingBox();
      if (box) {
        await page.screenshot({
          path: `${OUT}/ZOOM-upload-modal.png`,
          clip: { x: box.x, y: box.y, width: box.width, height: box.height }
        });
        console.log('  🔍 لقطة مكبّرة: Upload Modal');
      }
    }
    await page.keyboard.press('Escape');
    await page.waitForTimeout(500);
  }

  // ══════════════════════════════════════════════════════════
  // PART 6: صفحة المحادثات (واجهة الأجنت)
  // ══════════════════════════════════════════════════════════
  console.log('\n💬  PART 6: صفحة المحادثات (واجهة الأجنت)\n');

  await go(page, `/chats/${CONTACT}`);
  await shot(page, 'chat-with-contact', `💬 صفحة المحادثة مع الزبون — UUID: ${CONTACT.substring(0,8)}...`);

  // صوّر مكبّرة لمنطقة الرسائل
  await page.waitForTimeout(500);
  await page.screenshot({
    path: `${OUT}/ZOOM-chat-area.png`,
    clip: { x: 0, y: 0, width: 1000, height: 900 }
  });
  console.log('  🔍 لقطة مكبّرة: منطقة المحادثة');

  // ابحث عن زر AI Suggest
  const suggestSelectors = [
    'button:has-text("AI")',
    'button:has-text("اقتراح")',
    '[data-testid*="suggest"]',
    'button[title*="AI"]',
  ];
  let foundSuggest = false;
  for (const sel of suggestSelectors) {
    const btn = page.locator(sel).first();
    if (await btn.isVisible({ timeout: 2000 }).catch(() => false)) {
      const box = await btn.boundingBox();
      if (box) {
        await page.screenshot({
          path: `${OUT}/ZOOM-ai-suggest-btn.png`,
          clip: { x: Math.max(0, box.x - 50), y: Math.max(0, box.y - 50), width: Math.min(400, box.width + 100), height: Math.min(200, box.height + 100) }
        });
        console.log('  🔍 لقطة مكبّرة: زر AI Suggest');
      }
      foundSuggest = true;
      await shot(page, 'ai-suggest-found', '✨ زر AI Suggest موجود في واجهة الأجنت');
      break;
    }
  }
  if (!foundSuggest) {
    console.log('   ℹ️ زر AI Suggest مش ظاهر (ربما محتاج scroll أو setup مختلف)');
  }

  // ══════════════════════════════════════════════════════════
  // PART 7: تفعيل/إيقاف AI لجهة الاتصال
  // ══════════════════════════════════════════════════════════
  console.log('\n🔄  PART 7: تفعيل AI لجهة الاتصال\n');

  // ابحث عن toggle للـ AI في صفحة المحادثة
  const toggles = await page.locator('[role="switch"], input[type="checkbox"]').all();
  console.log(`   Toggles موجودة في الصفحة: ${toggles.length}`);
  if (toggles.length > 0) {
    await shot(page, 'contact-ai-toggle', '🔄 Toggle لتفعيل AI للجهة هذه');
  }

  // ══════════════════════════════════════════════════════════
  // PART 8: الـ Response Sequence (ترتيب الأتمتة)
  // ══════════════════════════════════════════════════════════
  console.log('\n⚡  PART 8: ترتيب الاستجابة (Response Sequence)\n');

  await go(page, '/settings/automation');
  await shot(page, 'automation-settings', '⚡ إعدادات الأتمتة — ترتيب الاستجابة (AI vs Basic vs Flows)');

  // ══════════════════════════════════════════════════════════
  // PART 9: لقطات المقارنة
  // ══════════════════════════════════════════════════════════
  console.log('\n📊  PART 9: مقارنة أنواع الأتمتة\n');

  await go(page, '/automation/basic');
  await shot(page, 'basic-replies', '📌 الردود الأساسية (Basic Replies) — الأتمتة البسيطة');

  await go(page, '/automation/flows');
  await shot(page, 'flow-builder', '🔧 Flow Builder — الأتمتة المتقدمة');

  await go(page, '/automation/ai');
  await shot(page, 'ai-assistant-final', '🤖 مساعد الرد الذكي (AI) — الأتمتة الأذكى');

  // ══════════════════════════════════════════════════════════
  // PART 10: لقطة شاملة أخيرة مع تفصيل كامل
  // ══════════════════════════════════════════════════════════
  console.log('\n🎯  PART 10: اللقطة الشاملة النهائية\n');

  await go(page, '/automation/ai');

  // الجزء العلوي
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.waitForTimeout(300);
  await page.screenshot({ path: `${OUT}/FINAL-ai-top.png`, clip: { x: 0, y: 0, width: 1440, height: 600 } });
  console.log('  🖼️ لقطة نهائية: القسم العلوي');

  // الجزء الأوسط
  await page.evaluate(() => window.scrollTo(0, 600));
  await page.waitForTimeout(300);
  await page.screenshot({ path: `${OUT}/FINAL-ai-mid.png`, clip: { x: 0, y: 0, width: 1440, height: 900 } });
  console.log('  🖼️ لقطة نهائية: القسم الأوسط');

  // اللقطة الكاملة
  await shot(page, 'COMPLETE-ai-page', '🤖 الصفحة الكاملة لـ Smart Router AI Assistant');

  // ══════════════════════════════════════════════════════════
  // توليد تقرير HTML
  // ══════════════════════════════════════════════════════════
  const phases = [
    { title: '🔐 PART 1: تسجيل الدخول', range: [1, 4] },
    { title: '🤖 PART 2: صفحة Smart Router AI', range: [5, 7] },
    { title: '⚙️ PART 3: نافذة إعداد AI', range: [8, 10] },
    { title: '🔑 PART 4: الكلمات المفتاحية', range: [11, 12] },
    { title: '📚 PART 5: قاعدة المعرفة', range: [13, 14] },
    { title: '💬 PART 6: واجهة الأجنت في المحادثات', range: [15, 17] },
    { title: '📊 PART 7-10: الإعدادات والمقارنة والنهاية', range: [18, 99] },
  ];

  const html = buildReport(LOG, phases, { EMAIL, BASE, n });
  writeFileSync(`${OUT}/JOURNEY_REPORT.html`, html);

  console.log('\n' + '═'.repeat(65));
  console.log(`\n✅ اكتمل الاختبار — ${n} لقطة شاشة`);
  console.log(`📁 المجلد: ${OUT}/`);
  console.log(`🌐 التقرير HTML: ${OUT}/JOURNEY_REPORT.html\n`);

  await browser.close();
})();

// ─────────────────────────────────────────────────────────────────────────────
function buildReport(log, phases, { EMAIL, BASE, n }) {
  const grouped = phases.map(({ title, range }) => ({
    title,
    steps: log.filter(s => s.n >= range[0] && s.n <= range[1])
  })).filter(g => g.steps.length > 0);

  return `<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>🤖 رحلة اختبار Smart Router AI (IntelliReply)</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body { font-family: 'Segoe UI', Tahoma, Arial, sans-serif; background: #f1f5f9; margin: 0; padding: 20px; color: #0f172a; }

    header {
      background: linear-gradient(135deg, #1e40af 0%, #7c3aed 50%, #db2777 100%);
      color: white; padding: 40px 32px; border-radius: 20px; margin-bottom: 28px; text-align: center;
      box-shadow: 0 8px 32px rgba(30,64,175,.3);
    }
    header h1 { margin: 0 0 12px; font-size: 28px; letter-spacing: .5px; }
    header p { margin: 4px 0; opacity: .85; font-size: 15px; }

    .nav { background: white; border-radius: 12px; padding: 16px 20px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
    .nav a { display: inline-block; background: #eff6ff; color: #1d4ed8; border-radius: 8px; padding: 6px 14px; margin: 4px; text-decoration: none; font-size: 13px; transition: background .2s; }
    .nav a:hover { background: #dbeafe; }

    .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-bottom: 24px; }
    .stat { background: white; border-radius: 12px; padding: 20px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,.06); border-top: 4px solid #1e40af; }
    .stat-num { font-size: 32px; font-weight: 800; color: #1e40af; }
    .stat-lbl { font-size: 13px; color: #64748b; margin-top: 4px; }

    .phase { background: white; border-radius: 16px; padding: 24px; margin-bottom: 28px; box-shadow: 0 2px 12px rgba(0,0,0,.07); }
    .phase-title { font-size: 20px; font-weight: 700; color: #1e40af; margin: 0 0 20px; padding-bottom: 12px; border-bottom: 3px solid #eff6ff; }
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 20px; }

    .card { border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; transition: box-shadow .2s; }
    .card:hover { box-shadow: 0 4px 20px rgba(0,0,0,.12); }
    .card-header { background: linear-gradient(to left, #f8fafc, #eff6ff); padding: 12px 16px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #e2e8f0; }
    .step-badge { background: #1e40af; color: white; border-radius: 50%; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; flex-shrink: 0; }
    .step-caption { font-size: 13px; color: #374151; font-weight: 500; }
    .card img { width: 100%; display: block; max-height: 700px; object-fit: cover; object-position: top; cursor: zoom-in; }
    .card img:hover { object-fit: contain; max-height: none; }

    .info-box { background: #fefce8; border: 1px solid #fde047; border-radius: 10px; padding: 14px 18px; margin-bottom: 20px; font-size: 14px; line-height: 1.6; }
    .info-box strong { color: #854d0e; }

    footer { text-align: center; padding: 24px; color: #94a3b8; font-size: 13px; margin-top: 20px; }
  </style>
</head>
<body>
<header>
  <h1>🤖 رحلة اختبار Smart Router AI Assistant</h1>
  <p>IntelliReply — رحلة كاملة من تسجيل الدخول حتى واجهة الأجنت</p>
  <p style="margin-top:10px; opacity:.7">المستخدم: ${EMAIL} | المنصة: ${BASE}</p>
</header>

<nav class="nav">
  <strong>فهرس سريع:</strong>
  ${grouped.map(g => `<a href="#${g.title.replace(/[^a-z0-9أ-ي]/gi,'')}">${g.title}</a>`).join('')}
</nav>

<div class="stats">
  <div class="stat"><div class="stat-num">${n}</div><div class="stat-lbl">لقطة شاشة</div></div>
  <div class="stat"><div class="stat-num">${grouped.length}</div><div class="stat-lbl">مرحلة اختبار</div></div>
  <div class="stat"><div class="stat-num">✅</div><div class="stat-lbl">تسجيل الدخول</div></div>
  <div class="stat"><div class="stat-num">AI</div><div class="stat-lbl">/automation/ai</div></div>
</div>

<div class="phase">
  <div class="phase-title">🗺️ خريطة الرحلة</div>
  <div class="info-box">
    <strong>الرحلة الكاملة للمشترك:</strong><br>
    1️⃣ دخول المنصة → 2️⃣ الداشبورد → 3️⃣ قائمة الأتمتة → 4️⃣ صفحة AI Assistant → 5️⃣ إعداد مفتاح OpenAI → 6️⃣ رفع مستندات قاعدة المعرفة → 7️⃣ ضبط الكلمات المفتاحية → 8️⃣ فتح محادثة → 9️⃣ AI يرد تلقائياً على الزبون ✨<br><br>
    <strong>المسارات المختبرة:</strong> Homepage → Login → Dashboard → /automation/ai → Setup Modal → Keywords → Knowledge Base → /chats/{contact} → /automation/basic → /automation/flows
  </div>
</div>

${grouped.map(g => `
<div class="phase" id="${g.title.replace(/[^a-z0-9أ-ي]/gi,'')}">
  <div class="phase-title">${g.title}</div>
  <div class="grid">
    ${g.steps.map(s => `
    <div class="card">
      <div class="card-header">
        <div class="step-badge">${s.n}</div>
        <div class="step-caption">${s.caption || s.slug}</div>
      </div>
      <img src="${s.file}" alt="${s.caption || s.slug}" loading="lazy" title="اضغط للتكبير">
    </div>`).join('')}
  </div>
</div>`).join('')}

<footer>
  تقرير مُنشأ تلقائياً بواسطة Claude Code •
  ${new Date().toLocaleString('ar-EG', { dateStyle: 'full', timeStyle: 'short' })}
</footer>
</body>
</html>`;
}
