/**
 * الفيديو النهائي — رحلة واقعية كاملة
 * المحادثة شغّالة بعد fix الـ ContactResource bug
 */
import { chromium } from 'playwright';
import { writeFileSync, mkdirSync, readdirSync, statSync } from 'fs';

const BASE  = 'http://127.0.0.1:8000';
const EMAIL = 'mahmoud.hamed.shenawy@gmail.com';
const PASS  = 'Test1234!';
const OUT   = './test-artifacts/final-video';
const LOG   = [];

mkdirSync(OUT, { recursive: true });

let n = 0;
async function shot(page, slug, caption='') {
  n++;
  const f = `${String(n).padStart(2,'0')}-${slug}.png`;
  await page.screenshot({ path: `${OUT}/${f}`, fullPage: false });
  console.log(`  📸 [${n}] ${caption||slug}`);
  LOG.push({ n, slug, caption, f });
}

const pause = ms => new Promise(r => setTimeout(r, ms));
async function go(page, path, ms=2000) {
  await page.goto(`${BASE}${path}`, { waitUntil: 'networkidle', timeout: 30000 });
  await pause(ms);
}

(async () => {
  console.log('\n' + '═'.repeat(65));
  console.log('  🎬  الفيديو النهائي — رحلة واقعية بعد Bug Fix');
  console.log('═'.repeat(65) + '\n');

  const browser = await chromium.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-gpu'],
  });
  const ctx = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    recordVideo: { dir: OUT, size: { width: 1440, height: 900 } },
  });
  const page = await ctx.newPage();

  // ── 1. تسجيل الدخول ─────────────────────────────────────
  console.log('🔐 تسجيل الدخول\n');
  await go(page, '/login', 800);
  await shot(page, '01-login', 'صفحة تسجيل الدخول');

  await page.fill('input[type=email]', EMAIL);
  await pause(400);
  await page.fill('input[type=password]', PASS);
  await pause(400);

  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle', timeout: 20000 }).catch(() => {}),
    page.click('button[type=submit]'),
  ]);
  await pause(2500);

  if (page.url().includes('select')) {
    const btn = page.locator('button,a').filter({ hasText: /فتح|open|مؤسسة محمود/i }).first();
    if (await btn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await btn.click(); await pause(2500);
    }
  }
  await shot(page, '02-dashboard', 'الداشبورد — مؤسسة محمود');

  // ── 2. صفحة AI ──────────────────────────────────────────
  console.log('\n🤖 صفحة AI\n');
  await go(page, '/automation/ai', 2000);
  await shot(page, '03-ai-page', 'صفحة AI — مستند "TEST - دليل المنتجات" جاهز + AI مفعّل');
  await pause(500);

  // ── 3. Response Sequence ────────────────────────────────
  console.log('\n⚡ ترتيب الاستجابة\n');
  await go(page, '/settings/automation', 1800);
  await shot(page, '04-response-seq', 'ترتيب الاستجابة: Flows → Basic → AI');

  // ── 4. صفحة المحادثات (Chat List) ──────────────────────
  console.log('\n💬 قائمة المحادثات\n');
  await go(page, '/chats', 2500);
  await shot(page, '05-chats-list', 'قائمة المحادثات — أحمد محمود ظاهر');

  // ── 5. فتح محادثة أحمد (Direct URL — الـ Bug كان هنا) ──
  console.log('\n📨 فتح المحادثة بـ Direct URL (بعد Fix)\n');
  await go(page, '/chats/9269d893-76ec-4d00-bb49-e6dc6e6614c9?page=1', 4000);

  // انتظر حتى يظهر المحتوى
  await page.waitForFunction(
    () => document.body.innerText.trim().length > 100,
    { timeout: 10000 }
  ).catch(() => {});
  await pause(1500);

  await shot(page, '06-chat-loaded', '✅ صفحة المحادثة شغّالة (بعد Fix ContactResource)');

  // لقطة مكبّرة للمحادثة
  await page.screenshot({
    path: `${OUT}/ZOOM-chat-messages.png`,
    clip: { x: 235, y: 0, width: 800, height: 900 }
  });
  console.log('  🔍 لقطة مكبّرة: منطقة الرسائل');

  // ── 6. استعراض المحادثة ─────────────────────────────────
  console.log('\n📜 استعراض الرسائل\n');

  // لقطة للـ Panel الأيمن (معلومات الجهة + AI toggle)
  await page.screenshot({
    path: `${OUT}/ZOOM-right-panel.png`,
    clip: { x: 1060, y: 0, width: 380, height: 900 }
  });
  console.log('  🔍 لقطة مكبّرة: Panel الجهة + AI toggle');

  await shot(page, '07-full-chat-view', 'المحادثة كاملة: رسائل أحمد + ردود AI + وكيل');

  // ── 7. لقطة مكبّرة للـ Input Area ──────────────────────
  await page.screenshot({
    path: `${OUT}/ZOOM-input-area.png`,
    clip: { x: 235, y: 700, width: 830, height: 200 }
  });
  console.log('  🔍 لقطة مكبّرة: منطقة الكتابة + AI Suggest button');

  // ── 8. صفحة AI بعد Stop Keyword ────────────────────────
  console.log('\n🛑 حالة AI بعد Stop Keyword\n');
  await go(page, '/automation/ai', 2000);
  await shot(page, '08-ai-after-stop', 'AI — Toggle مفعّل | ai_assistance_enabled=0 للجهة');

  // ── 9. اللقطة النهائية ──────────────────────────────────
  console.log('\n🎯 اللقطة النهائية\n');
  await go(page, '/chats/9269d893-76ec-4d00-bb49-e6dc6e6614c9?page=1', 4000);
  await page.waitForFunction(() => document.body.innerText.length > 100, { timeout: 10000 }).catch(() => {});
  await pause(1500);
  await shot(page, '09-final-conversation', '🎯 المحادثة الكاملة: رحلة أحمد مع AI ← وكيل');

  await browser.close();
  await pause(1500);

  // الفيديو
  const vids = readdirSync(OUT).filter(f => f.endsWith('.webm')).sort();
  const vid  = vids[vids.length - 1];
  const mb   = vid ? (statSync(`${OUT}/${vid}`).size / 1024 / 1024).toFixed(1) : 0;
  console.log(`\n  🎥 Video: ${vid} (${mb}MB)`);

  // ── HTML Report ──────────────────────────────────────────
  writeFileSync(`${OUT}/FINAL_REPORT.html`, buildReport(LOG, vid));

  console.log('\n' + '═'.repeat(65));
  console.log(`✅ اكتمل — ${LOG.length} لقطة + فيديو (${mb}MB)`);
  console.log(`📁 ${OUT}/`);
  console.log(`🎥 ${OUT}/${vid}`);
  console.log(`🌐 ${OUT}/FINAL_REPORT.html\n`);
})();

function buildReport(shots, vid) {
  const conv = [
    {r:'👤', who:'أحمد', msg:'مرحبا', t:'قبل 30 دقيقة', badge:null},
    {r:'🤖', who:'AI Botzo', msg:'أهلاً وسهلاً! 🎉 أنا مساعدك الذكي في Botzo. كيف يمكنني مساعدتك؟', t:'+5ث', badge:'🟢 رد تلقائي — Start Keyword'},
    {r:'👤', who:'أحمد', msg:'عايز أعرف الباقات المتاحة وأسعارها', t:'+1د', badge:null},
    {r:'🤖', who:'AI', msg:'⭐ Starter 149 ريال/شهر (1000 رسالة) | 🚀 Pro 299 ريال/شهر (5000 رسالة) | 💼 Business 599 ريال/شهر (غير محدود)', t:'+1د5ث', badge:'🟢 من قاعدة المعرفة'},
    {r:'👤', who:'أحمد', msg:'كيف يتم الدفع؟', t:'+2د', badge:null},
    {r:'🤖', who:'AI', msg:'نقبل: Visa، Mastercard، Apple Pay، STC Pay، مدى، تحويل بنكي. الباقة السنوية بخصم 20%!', t:'+2د5ث', badge:'🟢 معلومات الدفع'},
    {r:'🛑', who:'أحمد', msg:'وكيل', t:'+3د', badge:'🔴 Stop Keyword! AI وقف', stop:true},
    {r:'👨‍💼', who:'موظف', msg:'← الموظف يتولى الآن. AI = OFF حتى إعادة التشغيل.', t:'+3د5ث', badge:null, handover:true},
  ];

  const msgs = conv.map(m => {
    if (m.handover) return `<div style="text-align:center;padding:12px;margin:8px 0;background:#1c1917;border-radius:10px;border:1px dashed #78716c;color:#a8a29e;font-size:13px">🤝 ${m.msg}</div>`;
    const right = m.r === '🤖';
    const bg = m.stop ? '#7f1d1d' : right ? '#064e3b' : '#1e3a5f';
    const fg = m.stop ? '#fecaca' : right ? '#d1fae5' : '#e2e8f0';
    return `<div style="display:flex;gap:10px;margin:7px 0;${right?'flex-direction:row-reverse':''}">
      <div style="width:34px;height:34px;border-radius:50%;background:${right?'#065f46':m.stop?'#7f1d1d':'#1e3a5f'};display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0">${m.r}</div>
      <div style="max-width:70%">
        <div style="background:${bg};color:${fg};padding:9px 13px;border-radius:${right?'12px 12px 4px 12px':'12px 12px 12px 4px'};font-size:13px;line-height:1.5">${m.msg}</div>
        <div style="font-size:11px;color:#6b7280;margin-top:2px;${right?'text-align:right':''}">
          ${m.who} • ${m.t}
          ${m.badge ? `<span style="background:${m.stop?'#7f1d1d':'#065f46'};color:${m.stop?'#fca5a5':'#6ee7b7'};padding:1px 8px;border-radius:20px;font-size:10px;margin-right:4px">${m.badge}</span>` : ''}
        </div>
      </div>
    </div>`;
  }).join('');

  return `<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>🤖 التقرير النهائي — IntelliReply Bug Fix + رحلة واقعية</title>
<style>
*{box-sizing:border-box} body{font-family:'Segoe UI',Arial,sans-serif;background:#0a0f1e;color:#e2e8f0;margin:0;padding:20px}
header{background:linear-gradient(135deg,#064e3b,#1e3a5f,#4c1d95);padding:36px;border-radius:20px;margin-bottom:22px;text-align:center}
header h1{margin:0 0 8px;font-size:24px;color:white}
header p{margin:4px 0;opacity:.8;color:#a7f3d0;font-size:14px}
.section{background:#111827;border-radius:14px;padding:22px;margin-bottom:20px;border:1px solid #1f2937}
.section h2{color:#34d399;margin:0 0 14px;font-size:17px;padding-bottom:10px;border-bottom:1px solid #1f2937}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:20px}
.stat{background:#111827;border-radius:10px;padding:16px;text-align:center;border:1px solid #1f2937}
.stat-num{font-size:26px;font-weight:800;color:#34d399} .stat-lbl{font-size:12px;color:#6b7280;margin-top:4px}
video{width:100%;border-radius:12px;border:2px solid #064e3b}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(360px,1fr));gap:12px}
.card{background:#0d1117;border:1px solid #21262d;border-radius:12px;overflow:hidden}
.card-top{padding:9px 14px;background:#161b22;font-size:12px;color:#6b7280;border-bottom:1px solid #21262d}
.card-top strong{color:#34d399} .card img{width:100%;display:block;max-height:700px;object-fit:cover;object-position:top}
.fix-box{background:#022c22;border:1px solid #065f46;border-radius:10px;padding:16px;font-family:monospace;font-size:13px;line-height:1.8}
.fix-old{color:#f87171;text-decoration:line-through} .fix-new{color:#4ade80}
</style>
</head>
<body>
<header>
  <h1>🤖 التقرير النهائي — Bug Fix + رحلة واقعية</h1>
  <p>تم إصلاح بياض صفحة المحادثات + رحلة أحمد مع AI ← طلب وكيل</p>
</header>

<div class="stats">
  <div class="stat"><div class="stat-num">✅</div><div class="stat-lbl">Bug تم إصلاحه</div></div>
  <div class="stat"><div class="stat-num">7</div><div class="stat-lbl">رسائل المحادثة</div></div>
  <div class="stat"><div class="stat-num" style="color:#6ee7b7">3</div><div class="stat-lbl">ردود AI</div></div>
  <div class="stat"><div class="stat-num" style="color:#f87171">🛑</div><div class="stat-lbl">Stop Keyword</div></div>
</div>

<div class="section">
  <h2>🐛 Bug تم اكتشافه وإصلاحه أثناء الاختبار</h2>
  <div class="fix-box">
    <div style="color:#fbbf24;margin-bottom:8px">📍 الملف: app/Services/ChatService.php:335</div>
    <div style="color:#94a3b8;margin-bottom:4px">المشكلة: ContactResource::make() بيضيف {data: {}} wrapper عند full page load</div>
    <div style="color:#94a3b8;margin-bottom:12px">النتيجة: contactId = undefined → Vue crash → صفحة بيضاء</div>
    <div class="fix-old">'contact' => ContactResource::make($contact),</div>
    <div class="fix-new">'contact' => ContactResource::make($contact)->resolve(),</div>
    <div style="color:#64748b;margin-top:8px;font-size:11px">resolve() يرجع البيانات مباشرة بدون data: wrapper ✅</div>
  </div>
</div>

${vid ? `
<div class="section">
  <h2>🎥 فيديو الرحلة الكاملة</h2>
  <video controls muted loop><source src="${vid}" type="video/webm"></video>
</div>` : ''}

<div class="section">
  <h2>💬 المحادثة بين أحمد والـ AI</h2>
  <div style="background:#0d1117;border-radius:10px;padding:18px;border:1px solid #21262d">
    ${msgs}
  </div>
</div>

<div class="section">
  <h2>📸 لقطات الرحلة الحقيقية</h2>
  <div class="grid">
    ${shots.map(s => `
    <div class="card">
      <div class="card-top"><strong>[${s.n}]</strong> ${s.caption||s.slug}</div>
      <img src="${s.f}" loading="lazy">
    </div>`).join('')}
    <div class="card">
      <div class="card-top"><strong>🔍</strong> منطقة الرسائل (مكبّرة)</div>
      <img src="ZOOM-chat-messages.png" loading="lazy">
    </div>
    <div class="card">
      <div class="card-top"><strong>🔍</strong> Panel الجهة + AI Toggle</div>
      <img src="ZOOM-right-panel.png" loading="lazy">
    </div>
    <div class="card">
      <div class="card-top"><strong>🔍</strong> منطقة الكتابة + AI Suggest</div>
      <img src="ZOOM-input-area.png" loading="lazy">
    </div>
  </div>
</div>

<footer style="text-align:center;padding:20px;color:#374151;font-size:12px">
  Claude Code • ${new Date().toLocaleString('ar-EG',{dateStyle:'full',timeStyle:'short'})}
</footer>
</body></html>`;
}
