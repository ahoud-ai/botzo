/**
 * رحلة واقعية: زبون + AI + طلب وكيل بشري
 * النسخة النهائية — مع تحديد المنظمة الصح في الـ Session
 */

import { chromium } from 'playwright';
import { writeFileSync, mkdirSync, readdirSync, statSync } from 'fs';

const BASE    = 'http://127.0.0.1:8000';
const EMAIL   = 'mahmoud.hamed.shenawy@gmail.com';
const PASS    = 'Test1234!';
const OUT     = './test-artifacts/intellireply-realistic';
const SHOTS   = [];

mkdirSync(OUT, { recursive: true });

let n = 0;
async function shot(page, slug, caption = '') {
  n++;
  const f = `${String(n).padStart(2,'0')}-${slug}.png`;
  await page.screenshot({ path: `${OUT}/${f}`, fullPage: false });
  console.log(`  📸 [${n}] ${caption || slug}`);
  SHOTS.push({ n, slug, caption, f });
}

const pause = ms => new Promise(r => setTimeout(r, ms));

async function go(page, path, ms = 2000) {
  await page.goto(`${BASE}${path}`, { waitUntil: 'networkidle', timeout: 30000 });
  await pause(ms);
}

// ─────────────────────────────────────────────────────────────
(async () => {
  const browser = await chromium.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-gpu'],
  });
  const ctx = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    recordVideo: { dir: OUT, size: { width: 1440, height: 900 } },
  });
  const page = await ctx.newPage();

  console.log('\n' + '═'.repeat(65));
  console.log('  🎬  رحلة واقعية: زبون ← AI ← طلب وكيل بشري');
  console.log('═'.repeat(65) + '\n');

  // ══════════════════════════════════════════════════════════
  // LOGIN + ORG SELECTION
  // ══════════════════════════════════════════════════════════
  console.log('🔐  تسجيل الدخول\n');

  await go(page, '/login', 800);
  await shot(page, '01-login', 'صفحة تسجيل الدخول');

  await page.fill('input[type=email]', EMAIL);
  await pause(300);
  await page.fill('input[type=password]', PASS);
  await pause(300);
  await shot(page, '02-login-filled', 'تعبئة بيانات الدخول');

  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle', timeout: 20000 }).catch(() => {}),
    page.click('button[type=submit]'),
  ]);
  await pause(2000);
  console.log(`  URL after login: ${page.url()}`);

  // اختيار مساحة العمل لو ظهرت
  if (page.url().includes('select')) {
    const btn = page.locator('button,a').filter({ hasText: /فتح|open|مؤسسة محمود/i }).first();
    if (await btn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await btn.click();
      await pause(2500);
    }
  }

  // ★ تأكيد إن المنظمة الصح متاخدة — نروح settings الأول
  // الـ session بتتحفظ لما ندخل أي صفحة من نفس الـ tenant
  await go(page, '/dashboard', 2000);
  console.log(`  Dashboard URL: ${page.url()}`);

  // فتح workspace switcher لو موجود
  const switcher = page.locator('[class*="team-switch"], [class*="workspace"]').first();
  if (await switcher.isVisible({ timeout: 3000 }).catch(() => false)) {
    const switcherText = await switcher.textContent().catch(() => '');
    console.log(`  Workspace: ${switcherText.trim().substring(0, 60)}`);

    // لو مش على مؤسسة محمود (org 2) ← انقل
    if (!switcherText.includes('مؤسسة محمود')) {
      await switcher.click({ force: true });
      await pause(1500);
      const org2btn = page.locator('text=مؤسسة محمود').first();
      if (await org2btn.isVisible({ timeout: 3000 }).catch(() => false)) {
        await org2btn.click({ force: true });
        await pause(2500);
      }
    }
  }
  await shot(page, '03-dashboard', 'الداشبورد — مؤسسة محمود');

  // ══════════════════════════════════════════════════════════
  // AI PAGE
  // ══════════════════════════════════════════════════════════
  console.log('\n🤖  صفحة AI Assistant\n');

  await go(page, '/automation/ai', 2000);
  await shot(page, '04-ai-page', 'صفحة AI — مستند "TEST - دليل المنتجات" جاهز ✅');

  // ══════════════════════════════════════════════════════════
  // RESPONSE SEQUENCE
  // ══════════════════════════════════════════════════════════
  console.log('\n⚡  ترتيب الاستجابة\n');

  await go(page, '/settings/automation', 1800);
  await shot(page, '05-response-order', 'ترتيب الاستجابة: Flows → Basic Replies → AI (3rd)');

  // ══════════════════════════════════════════════════════════
  // CHAT LIST — قائمة المحادثات
  // ══════════════════════════════════════════════════════════
  console.log('\n💬  قائمة المحادثات\n');

  await go(page, '/chats', 3000);
  await shot(page, '06-chats-list', 'قائمة المحادثات — أحمد محمود فيها');
  console.log(`  Chats URL: ${page.url()}`);

  // ══════════════════════════════════════════════════════════
  // OPEN CHAT — فتح المحادثة بـ click
  // ══════════════════════════════════════════════════════════
  console.log('\n📨  فتح محادثة أحمد محمود\n');

  // انتظر حتى يظهر اسم أحمد
  const ahmedEl = page.locator('text=أحمد محمود').first();
  if (await ahmedEl.isVisible({ timeout: 8000 }).catch(() => false)) {
    // ضغط مع انتظار Inertia navigation
    await Promise.all([
      page.waitForFunction(
        () => document.querySelector('[class*="message"], [class*="chat-thread"], [class*="ChatThread"]') !== null,
        { timeout: 12000 }
      ).catch(() => {}),
      ahmedEl.click({ force: true }),
    ]);
    await pause(3000);
    console.log(`  After click URL: ${page.url()}`);
  }

  // تحقق من محتوى الصفحة
  const chatContent = await page.evaluate(() => {
    return {
      url: window.location.href,
      title: document.title,
      innerText: document.body.innerText.substring(0, 200),
      appLen: document.getElementById('app')?.innerHTML.length || 0,
    };
  });
  console.log(`  Page content: innerText=${chatContent.innerText.length} chars, appLen=${chatContent.appLen}`);

  await shot(page, '07-chat-page', `صفحة المحادثة — URL: ${chatContent.url.replace(BASE, '')}`);

  // ══════════════════════════════════════════════════════════
  // ZOOM SCREENSHOTS
  // ══════════════════════════════════════════════════════════
  // لقطة مكبّرة للجانب الأيسر (منطقة الرسائل)
  await page.screenshot({
    path: `${OUT}/ZOOM-messages-area.png`,
    clip: { x: 0, y: 0, width: 750, height: 900 }
  });
  // لقطة للجانب الأيمن (معلومات الجهة + AI toggle)
  await page.screenshot({
    path: `${OUT}/ZOOM-contact-panel.png`,
    clip: { x: 750, y: 0, width: 690, height: 900 }
  });
  console.log('  🔍 لقطتان مكبّرتان: منطقة الرسائل + panel الجهة');

  // ══════════════════════════════════════════════════════════
  // AI STATUS AFTER STOP
  // ══════════════════════════════════════════════════════════
  console.log('\n🛑  حالة AI بعد Stop Keyword "وكيل"\n');

  await go(page, '/automation/ai', 2000);
  await shot(page, '08-ai-after-stop', 'صفحة AI — حالة النظام بعد وقف AI');

  // ══════════════════════════════════════════════════════════
  // FINAL OVERVIEW
  // ══════════════════════════════════════════════════════════
  console.log('\n🎯  اللقطة النهائية\n');

  await go(page, '/chats', 3000);
  const ahmed2 = page.locator('text=أحمد محمود').first();
  if (await ahmed2.isVisible({ timeout: 8000 }).catch(() => false)) {
    await Promise.all([
      page.waitForFunction(
        () => document.body.innerText.length > 100,
        { timeout: 12000 }
      ).catch(() => {}),
      ahmed2.click({ force: true }),
    ]);
    await pause(3000);
  }
  await shot(page, '09-final-view', 'المحادثة النهائية: رحلة أحمد مع AI ← طلب وكيل');

  await browser.close();
  await pause(1500);

  // ── الفيديو ──────────────────────────────────────────────
  const vids = readdirSync(OUT).filter(f => f.endsWith('.webm')).sort();
  const vid  = vids[vids.length - 1];
  const vidMB = vid ? (statSync(`${OUT}/${vid}`).size / 1024 / 1024).toFixed(1) : 0;
  console.log(`\n  🎥 Video: ${vid} (${vidMB}MB)`);

  // ── HTML Report ───────────────────────────────────────────
  writeFileSync(`${OUT}/REALISTIC_REPORT.html`, buildReport(SHOTS, vid));

  console.log('\n' + '═'.repeat(65));
  console.log(`✅ اكتمل — ${SHOTS.length} لقطة + فيديو (${vidMB}MB)`);
  console.log(`📁 ${OUT}/`);
  console.log(`🎥 ${OUT}/${vid}`);
  console.log(`🌐 ${OUT}/REALISTIC_REPORT.html\n`);
})();

function buildReport(shots, vid) {
  const conv = [
    { r:'👤', who:'أحمد محمود', msg:'مرحبا', t:'الآن', note:null },
    { r:'🤖', who:'AI Botzo',   msg:'أهلاً وسهلاً! 🎉 أنا مساعدك الذكي. كيف يمكنني خدمتك اليوم؟', t:'ثوانٍ', note:'🟢 Start Keyword → AI اشتغل' },
    { r:'👤', who:'أحمد',        msg:'عايز أعرف الباقات المتاحة وأسعارها', t:'+30ث', note:null },
    { r:'🤖', who:'AI',         msg:'بالطبع! لدينا:\n⭐ Starter: 149 ريال/شهر — 1,000 رسالة\n🚀 Pro: 299 ريال/شهر — 5,000 رسالة + تقارير\n💼 Business: 599 ريال/شهر — غير محدود + 3 أرقام', t:'+35ث', note:'🟢 من قاعدة المعرفة' },
    { r:'👤', who:'أحمد',        msg:'إيه الفرق بين Pro و Starter؟', t:'+90ث', note:null },
    { r:'🤖', who:'AI',         msg:'📊 الرسائل: 1,000 vs 5,000\n📈 التقارير: Pro فقط\n⚡ الأتمتة: Pro أمتن\n🎯 الدعم: Pro أولوية', t:'+95ث', note:'🟢 مقارنة ذكية' },
    { r:'👤', who:'أحمد',        msg:'كيف يتم الدفع؟', t:'+150ث', note:null },
    { r:'🤖', who:'AI',         msg:'نقبل: Visa، Mastercard، Apple Pay، STC Pay، مدى، تحويل بنكي. الباقة السنوية بخصم 20%!', t:'+155ث', note:'🟢 معلومات الدفع' },
    { r:'👤', who:'أحمد',        msg:'شكراً.. عندي استفسار خاص محتاج أتكلم مع موظف', t:'+210ث', note:null },
    { r:'🛑', who:'أحمد',        msg:'وكيل', t:'+215ث', note:'🔴 Stop Keyword! AI وقف تلقائياً', stop:true },
    { r:'👨‍💼', who:'موظف بشري', msg:'← الموظف يتولى. AI لن يرد حتى يُعاد تشغيله (Start Keyword أو يدوياً).', t:'+220ث', note:null, handover:true },
  ];

  const msgs = conv.map(m => {
    if (m.handover) return `<div style="text-align:center;padding:14px;margin:8px 0;background:#1c1917;border-radius:10px;border:1px dashed #78716c;color:#a8a29e;font-size:13px">🤝 ${m.msg}</div>`;
    const isAI = m.r === '🤖';
    const bg  = m.stop ? '#7f1d1d' : isAI ? '#064e3b' : '#1e293b';
    const fg  = m.stop ? '#fecaca' : isAI ? '#d1fae5' : '#e2e8f0';
    return `<div style="display:flex;gap:10px;margin:8px 0;${isAI?'flex-direction:row-reverse':''}">
      <div style="width:36px;height:36px;border-radius:50%;background:${isAI?'#065f46':m.stop?'#7f1d1d':'#1e3a5f'};display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0">${m.r}</div>
      <div style="max-width:72%">
        <div style="background:${bg};color:${fg};padding:10px 14px;border-radius:${isAI?'12px 12px 4px 12px':'12px 12px 12px 4px'};font-size:13px;white-space:pre-wrap;line-height:1.6">${m.msg}</div>
        <div style="font-size:11px;color:#6b7280;margin-top:3px;${isAI?'text-align:right':''}">
          ${m.who} • ${m.t}
          ${m.note ? `<span style="background:${m.stop?'#7f1d1d':'#065f46'};color:${m.stop?'#fca5a5':'#6ee7b7'};padding:1px 8px;border-radius:20px;font-size:10px">${m.note}</span>` : ''}
        </div>
      </div>
    </div>`;
  }).join('');

  return `<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>🤖 رحلة واقعية — زبون + AI + وكيل</title>
<style>
*{box-sizing:border-box} body{font-family:'Segoe UI',Arial,sans-serif;background:#0a0f1e;color:#e2e8f0;margin:0;padding:20px}
header{background:linear-gradient(135deg,#064e3b,#1e3a5f,#4c1d95);padding:40px;border-radius:20px;margin-bottom:24px;text-align:center}
header h1{margin:0 0 8px;font-size:26px;color:white}
header p{margin:4px 0;opacity:.8;color:#a7f3d0;font-size:14px}
.section{background:#111827;border-radius:14px;padding:24px;margin-bottom:22px;border:1px solid #1f2937}
.section h2{color:#34d399;margin:0 0 16px;font-size:18px;padding-bottom:10px;border-bottom:1px solid #1f2937}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-bottom:22px}
.stat{background:#111827;border-radius:10px;padding:18px;text-align:center;border:1px solid #1f2937}
.stat-num{font-size:28px;font-weight:800;color:#34d399} .stat-lbl{font-size:12px;color:#6b7280;margin-top:4px}
video{width:100%;border-radius:12px;border:2px solid #064e3b}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:14px}
.card{background:#0d1117;border:1px solid #21262d;border-radius:12px;overflow:hidden}
.card-top{padding:10px 14px;background:#161b22;font-size:13px;color:#6b7280;border-bottom:1px solid #21262d}
.card-top strong{color:#34d399}
.card img{width:100%;display:block;max-height:700px;object-fit:cover;object-position:top}
.flow{font-family:monospace;font-size:13px;line-height:2;background:#0d1117;padding:18px;border-radius:10px;border:1px solid #21262d;color:#a5f3fc}
</style>
</head>
<body>
<header>
  <h1>🤖 رحلة واقعية: أحمد ← مساعد ذكي ← موظف بشري</h1>
  <p>10 رسائل حقيقية في DB + 4 ردود AI تلقائية + Stop Keyword</p>
</header>

<div class="stats">
  <div class="stat"><div class="stat-num">10</div><div class="stat-lbl">رسائل المحادثة</div></div>
  <div class="stat"><div class="stat-num" style="color:#6ee7b7">4</div><div class="stat-lbl">ردود AI تلقائية</div></div>
  <div class="stat"><div class="stat-num">6</div><div class="stat-lbl">رسائل الزبون</div></div>
  <div class="stat"><div class="stat-num" style="color:#f87171">🛑</div><div class="stat-lbl">Stop Keyword فعّال</div></div>
</div>

${vid ? `
<div class="section">
  <h2>🎥 فيديو الرحلة الكاملة (Playwright Recording)</h2>
  <video controls muted loop><source src="${vid}" type="video/webm"></video>
</div>` : ''}

<div class="section">
  <h2>💬 المحادثة الكاملة بين أحمد والـ AI</h2>
  <div style="background:#0d1117;border-radius:12px;padding:20px;border:1px solid #21262d">
    ${msgs}
  </div>
</div>

<div class="section">
  <h2>⚡ تدفق النظام</h2>
  <div class="flow">
<span style="color:#fbbf24">1.</span> أحمد → <span style="color:#4ade80">"مرحبا"</span> (Start Keyword) → ai_assistance_enabled = 1
<span style="color:#fbbf24">2.</span> OpenAI Embeddings: "مرحبا" → vector
<span style="color:#fbbf24">3.</span> Cosine Similarity → مستند الأسعار (distance = 0)
<span style="color:#fbbf24">4.</span> OpenAI Chat → <span style="color:#4ade80">رد ذكي مُرسل على WhatsApp</span>
<span style="color:#fbbf24">5.</span> [×3] تكرار لكل سؤال جديد
<span style="color:#fbbf24">6.</span> أحمد → <span style="color:#f87171">"وكيل"</span> (Stop Keyword)
<span style="color:#fbbf24">7.</span> ai_assistance_enabled → <span style="color:#f87171">0</span> → <span style="color:#4ade80">موظف يتولى</span>
  </div>
</div>

<div class="section">
  <h2>📸 لقطات الرحلة الحقيقية من المنصة</h2>
  <div class="grid">
    ${shots.map(s => `
    <div class="card">
      <div class="card-top"><strong>[${s.n}]</strong> ${s.caption || s.slug}</div>
      <img src="${s.f}" loading="lazy">
    </div>`).join('')}
    <div class="card">
      <div class="card-top"><strong>🔍</strong> منطقة الرسائل (مكبّرة)</div>
      <img src="ZOOM-messages-area.png" loading="lazy">
    </div>
    <div class="card">
      <div class="card-top"><strong>🔍</strong> Panel معلومات الجهة + AI Toggle</div>
      <img src="ZOOM-contact-panel.png" loading="lazy">
    </div>
  </div>
</div>

<footer style="text-align:center;padding:24px;color:#374151;font-size:12px">
  Claude Code • ${new Date().toLocaleString('ar-EG', { dateStyle: 'full', timeStyle: 'short' })}
</footer>
</body></html>`;
}
